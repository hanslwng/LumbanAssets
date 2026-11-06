/**
 * Procurement MVP: list PR → detail · quotations · PO · AIR · print
 */
(() => {
    let procSelId = 0;
    /** Invalidates in-flight detail fetch after overlay close / clear selection. */
    let procDetailReqId = 0;
    let procStaffEscapeBound = false;

    /** Matches PHP LGUE_WEB_BASE (see theme meta lgue-web-base); root-absolute avoids staff/* relative resolution. */
    function procPrintUrl(type, id) {
        const t = encodeURIComponent(String(type || '').toLowerCase());
        const sid = encodeURIComponent(String(id == null ? '' : id));
        const m = document.querySelector('meta[name="lgue-web-base"]');
        let base = '';
        if (m) base = String(m.getAttribute('content') || '').trim().replace(/\/$/, '');
        if (base === '/') base = '';
        const prefix = base ? `${base}` : '';
        return `${prefix}/print/print-procurement.php?type=${t}&id=${sid}`;
    }

    function pgCanPrint() {
        const el = document.getElementById('pg-procurement');
        return !!(el && el.dataset.canPrint === '1');
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function procFmtMoney(n) {
        return Number(n || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function procFmtQty(q) {
        if (typeof window.formatQty === 'function') {
            return window.formatQty(q);
        }
        const n = Number(q);
        if (!Number.isFinite(n)) {
            return q == null || q === '' ? '' : String(q);
        }
        if (Math.abs(n - Math.round(n)) < 1e-9) {
            return String(Math.round(n));
        }
        return String(n);
    }

    /** Safe slug for status modifier classes (draft, quoting, po_issued, …). */
    function procStatusSlug(st) {
        const raw = String(st || '')
            .trim()
            .replace(/^@+/, '')
            .toLowerCase()
            .replace(/\s+/g, '_')
            .replace(/[^a-z0-9_]/g, '');
        return raw || 'unknown';
    }

    function procIsNeedRevision(status, feedback) {
        const raw = String(status || '').trim().replace(/^@+/, '').toLowerCase();
        if (raw === 'needs_revision' || raw === 'needs_fix') return true;
        return raw === 'draft' && String(feedback || '').trim() !== '';
    }

    function procIsDraftLike(status) {
        const raw = String(status || '').trim().replace(/^@+/, '').toLowerCase();
        return raw === 'draft' || raw === 'needs_revision' || raw === 'needs_fix';
    }

    /** Strip accidental leading `@` sometimes stored with status text */
    function procDisplayStatus(st, feedback) {
        const raw = String(st || '').trim().replace(/^@+/, '');
        if (procIsNeedRevision(raw, feedback)) return 'Need revision';
        if (raw === 'submitted') return 'Awaiting approval';
        if (raw === 'quoting') return 'Quoting';
        if (raw === 'po_issued') return 'PO issued';
        if (raw === 'draft') return 'Draft';
        if (raw === 'completed') return 'Completed';
        return raw;
    }

    /** Date column / chip — avoid showing concatenated junk after `YYYYMMDD` junk from bad imports. */
    function procFmtPrDate(d) {
        const s = String(d ?? '').trim();
        const iso = /^(\d{4}-\d{2}-\d{2})/.exec(s);
        if (iso) return iso[1];
        if (s.includes('@')) return s.split('@')[0].trim() || '—';

        return s || '—';
    }

    const PROC_DETAIL_IDLE_HTML =
        '<div class="proc-wf-inner proc-wf-inner--idle">' +
        '<div class="proc-empty-state proc-empty-state--idle">' +
        '<div class="proc-empty-visual"><i class="fas fa-arrow-pointer"></i></div>' +
        '<p class="proc-empty-title">Choose a purchase request</p>' +
        '<p class="proc-empty-text muted">Pick a PR from the list to manage quotations and documents. Close this panel or click the same row again to dismiss.</p>' +
        '</div></div>';

    function procClearWorkflowModalNav() {
        const nav = document.getElementById('staff-workflow-modal-nav');
        if (nav) nav.innerHTML = '';
    }

    function procWireWorkflowNavScroll(navEl) {
        const root = navEl || document.getElementById('staff-workflow-modal-nav');
        if (!root) return;
        root.querySelectorAll('a.staff-wf-nav-pill').forEach((a) => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const href = a.getAttribute('href');
                if (!href || href.charAt(0) !== '#') return;
                const el = document.getElementById(href.slice(1));
                if (!el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    function procFillWorkflowModalNav(showIssue, showDelivery) {
        const nav = document.getElementById('staff-workflow-modal-nav');
        if (!nav) return;
        const pills = [
            '<a href="#wf-sec-summary" class="staff-wf-nav-pill"><i class="fas fa-layer-group" aria-hidden="true"></i><span>Overview</span></a>',
            '<a href="#wf-sec-lines" class="staff-wf-nav-pill"><i class="fas fa-list-ul" aria-hidden="true"></i><span>Items</span></a>',
            '<a href="#wf-sec-quotes" class="staff-wf-nav-pill"><i class="fas fa-comments-dollar" aria-hidden="true"></i><span>Quotes</span></a>',
        ];
        if (showIssue) {
            pills.push(
                '<a href="#wf-sec-issue" class="staff-wf-nav-pill"><i class="fas fa-file-circle-check" aria-hidden="true"></i><span>Award PO</span></a>',
            );
        }
        if (showDelivery) {
            pills.push(
                '<a href="#wf-sec-delivery" class="staff-wf-nav-pill"><i class="fas fa-truck-ramp-box" aria-hidden="true"></i><span>PO &amp; AIR</span></a>',
            );
        }
        nav.innerHTML =
            '<span class="staff-workflow-modal-nav-lead">Jump to</span><span class="staff-workflow-modal-nav-pills">' +
            pills.join('') +
            '</span>';
        procWireWorkflowNavScroll(nav);
    }

    function procDetailLoadingHtml(title) {
        return (
            '<div class="proc-wf-inner proc-wf-inner--loading">' +
            '<div class="proc-empty-state proc-empty-state--loading">' +
            '<div class="proc-empty-visual proc-empty-visual--pulse"><i class="fas fa-spinner fa-spin"></i></div>' +
            '<p class="proc-empty-title">' +
            esc(title || 'Loading…') +
            '</p></div></div>'
        );
    }

    function procDetailErrorHtml(message) {
        return (
            '<div class="proc-wf-inner proc-wf-inner--error">' +
            '<div class="proc-empty-state proc-empty-state--error">' +
            '<div class="proc-empty-visual"><i class="fas fa-circle-exclamation"></i></div>' +
            '<p class="proc-empty-title">Something went wrong</p>' +
            '<p class="proc-empty-text muted">' +
            esc(message || 'Could not load') +
            '</p></div></div>'
        );
    }

    function procStaffWorkflowOverlay() {
        return document.getElementById('proc-workflow-overlay');
    }

    function procOpenStaffWorkflowModal() {
        const el = procStaffWorkflowOverlay();
        if (!el) return;
        el.classList.add('staff-workflow-overlay--open');
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('staff-workflow-modal-open');
    }

    function procCloseStaffWorkflowModal() {
        const el = procStaffWorkflowOverlay();
        if (!el) return;
        el.classList.remove('staff-workflow-overlay--open');
        el.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('staff-workflow-modal-open');
    }

    function procAdminWorkflowPanel() {
        if (document.body.classList.contains('staff-portal')) return null;
        if (procStaffWorkflowOverlay()) return null;
        return document.getElementById('proc-workflow-panel')
            || document.querySelector('#pg-procurement .proc-workflow-panel');
    }

    function procSetAdminWorkflowVisible(on) {
        const panel = procAdminWorkflowPanel();
        const list = document.querySelector('#pg-procurement .proc-pr-panel');
        const layout = document.querySelector('#pg-procurement .proc-layout');
        if (!panel) return;
        const show = !!on;
        panel.hidden = !show;
        if (list) list.hidden = show;
        layout?.classList.toggle('has-selection', show);
        if (show) {
            requestAnimationFrame(() => {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    }

    function procIsAdminPrList() {
        return !!procAdminWorkflowPanel();
    }

    function procPrColspan() {
        return procIsAdminPrList() ? 8 : 7;
    }

    /** Department-scoped procurement: admins keep full office list; everyone else uses their assigned office. */
    function procDepartmentScopeFromSession() {
        const el = document.getElementById('userData');
        if (!el) {
            return { isAdmin: true, officeId: 0, officeName: '', userName: '' };
        }
        return {
            isAdmin: el.dataset.userIsAdmin === '1',
            departmentScoped: el.dataset.userIsAdmin !== '1' && (el.dataset.userDeptScoped === '1' || parseInt(el.dataset.userOfficeId || '0', 10) > 0),
            officeId: parseInt(el.dataset.userOfficeId || '0', 10) || 0,
            officeName: String(el.dataset.userOfficeName || '').trim(),
            userName: String(el.dataset.userName || '').trim(),
            prPrintedName: String(el.dataset.prPrintedName || '').trim(),
            prDesignation: String(el.dataset.prDesignation || '').trim(),
            gsoOfficer: String(el.dataset.gsoOfficer || '').trim() || 'RHONALIZA A. ALVIAR',
            gsoDesignation: String(el.dataset.gsoDesignation || '').trim() || 'GSO - Designate',
            mayorName: String(el.dataset.mayorName || '').trim() || 'BELEN B. RAGA',
            mayorTitle: String(el.dataset.mayorTitle || '').trim() || 'Municipal Mayor',
        };
    }

    function procOfficeList() {
        if (typeof offices !== 'undefined' && Array.isArray(offices)) return offices;
        return [];
    }

    function procFindGsoOfficeId() {
        const list = procOfficeList();
        const byCode = list.find((o) => String(o.code || '').toUpperCase() === 'GSO');
        if (byCode) return String(byCode.id);
        const byName = list.find((o) => /general\s*services/i.test(String(o.name || '')));
        return byName ? String(byName.id) : '';
    }

    function procApplyAdminPrSignatories() {
        const scope = procDepartmentScopeFromSession();
        if (!scope.isAdmin) return;
        const reqBy = document.getElementById('proc-pr-req-by');
        const reqTitle = document.getElementById('proc-pr-req-title');
        const appBy = document.getElementById('proc-pr-approved');
        const appTitle = document.getElementById('proc-pr-approved-title');
        if (reqBy) reqBy.value = scope.gsoOfficer;
        if (reqTitle) reqTitle.value = scope.gsoDesignation;
        if (appBy) appBy.value = scope.mayorName;
        if (appTitle) appTitle.value = scope.mayorTitle;
    }

    function procStaffRequestedName(scope) {
        return (scope.prPrintedName || scope.userName || '').trim();
    }

    function procStaffRequestedDesignation(scope) {
        return (scope.officeName || scope.prDesignation || '').trim();
    }

    function procApplyStaffPrSignatories() {
        const scope = procDepartmentScopeFromSession();
        if (scope.isAdmin) return;
        const reqBy = document.getElementById('proc-pr-req-by');
        const reqTitle = document.getElementById('proc-pr-req-title');
        const appBy = document.getElementById('proc-pr-approved');
        const appTitle = document.getElementById('proc-pr-approved-title');
        if (reqBy) {
            reqBy.disabled = false;
            reqBy.readOnly = false;
            reqBy.value = procStaffRequestedName(scope);
        }
        if (reqTitle) {
            reqTitle.disabled = false;
            reqTitle.readOnly = false;
            reqTitle.value = procStaffRequestedDesignation(scope);
        }
        if (appBy) {
            appBy.disabled = false;
            appBy.readOnly = false;
            appBy.value = scope.mayorName;
        }
        if (appTitle) {
            appTitle.disabled = false;
            appTitle.readOnly = false;
            appTitle.value = scope.mayorTitle;
        }
    }

    function procBindAdminOfficeSignatories() {
        const ob = document.getElementById('proc-pr-office');
        if (!ob || ob.dataset.adminSignBound === '1') return;
        ob.dataset.adminSignBound = '1';
        ob.addEventListener('change', () => {
            if (!procDepartmentScopeFromSession().isAdmin) return;
            if (document.getElementById('proc-pr-edit-id')?.value) return;
            procApplyAdminPrSignatories();
        });
    }

    function procBusy(btn, work, label) {
        if (typeof runBusy === 'function') {
            return runBusy(btn, work, label);
        }
        return work();
    }

    const PROC_DEFAULT_SECTIONS = [
        'OFFICE FIXTURES & FURNITURES',
        'OFFICE EQUIPMENTS, TOOLS & APPLIANCES',
        'IT EQUIPMENTS',
        'COMMUNICATION EQUIPMENTS',
        'MEDICAL EQUIPMENTS',
        'SOUND SYSTEM',
        'SERVICE VEHICLES',
        'OTHERS',
    ];

    async function procFillSectionOptions(selected, opts) {
        const sel = document.getElementById('proc-pr-section');
        if (!sel) return;
        const lockConsumable = !!(opts && opts.lockConsumable);
        let groups = PROC_DEFAULT_SECTIONS.slice();
        if (!lockConsumable) {
            try {
                const res = await apiGet('procurement.php?sections=1');
                if (res.success && Array.isArray(res.data) && res.data.length) {
                    groups = res.data.filter((g) => String(g).toLowerCase() !== 'consumable');
                }
            } catch (e) {
                // Keep defaults.
            }
        } else {
            groups = ['Consumable'];
        }
        const current = selected != null ? String(selected) : sel.value;
        sel.innerHTML = '<option value="">— inventory category —</option>' +
            groups.map((g) => `<option value="${esc(g)}">${esc(g)}</option>`).join('');
        if (current && !groups.includes(current)) {
            sel.insertAdjacentHTML('beforeend', `<option value="${esc(current)}">${esc(current)}</option>`);
        }
        if (lockConsumable) {
            sel.value = 'Consumable';
            sel.disabled = true;
        } else {
            sel.disabled = false;
            if (current) sel.value = current;
        }
        const hint = document.getElementById('proc-pr-section-hint');
        if (hint) hint.style.display = lockConsumable ? 'block' : 'none';
        const flag = document.getElementById('proc-pr-is-consumable');
        if (flag) flag.value = lockConsumable ? '1' : '0';
    }

    function procIsConsumablePr() {
        return document.getElementById('proc-pr-is-consumable')?.value === '1';
    }

    function procApplyConsumableClassification(isConsumable) {
        const flag = document.getElementById('proc-pr-is-consumable');
        if (flag) flag.value = isConsumable ? '1' : '0';
        procSyncConsumableLinesUi();
    }

    function procSyncConsumableLinesUi() {
        const isCons = procIsConsumablePr();
        const hint = document.getElementById('proc-pr-lines-hint');
        const addBtn = document.getElementById('proc-pr-add-article-btn');
        if (hint) {
            hint.textContent = isCons
                ? 'Article is fixed to Consumable (not Semi/PPE). Type each item name in Description — e.g. Bond Paper A4, Ballpen black, Toner HP 85A.'
                : 'Article is the title (Table). Each row is a different description under that article. Unit cost of ₱50,000+ becomes PPE after issue — not shown on the printed PR.';
        }
        if (addBtn) {
            addBtn.hidden = isCons;
            addBtn.textContent = isCons ? '' : undefined;
            if (!isCons) {
                addBtn.innerHTML = '<i class="fas fa-plus" aria-hidden="true"></i> Add article';
            }
        }
        const wrap = procArticleGroupsRoot();
        if (!wrap) return;
        if (isCons) {
            // Merge every description row into one locked "Consumable" article group.
            const allLines = [];
            wrap.querySelectorAll('tr.proc-pr-row').forEach((tr) => {
                allLines.push({
                    description: tr.querySelector('.proc-pr-desc')?.value || '',
                    quantity: tr.querySelector('.proc-pr-qty')?.value || '1',
                    unit: tr.querySelector('.proc-pr-unit')?.value || 'pc',
                    est_unit_cost: tr.querySelector('.proc-pr-est')?.value || '0',
                });
            });
            wrap.innerHTML = '';
            procAddPrArticleGroup({
                article: 'Consumable',
                lines: allLines.length ? allLines : [{ description: '' }],
            });
            const block = wrap.querySelector('.proc-pr-article-block');
            const art = block?.querySelector('.proc-pr-article');
            const rm = block?.querySelector('.proc-pr-article-rm');
            const artHint = block?.querySelector('.proc-pr-article-hint');
            if (art) {
                art.value = 'Consumable';
                art.disabled = true;
                art.readOnly = true;
            }
            if (rm) rm.hidden = true;
            if (artHint) artHint.textContent = 'fixed — consumable issuance only';
            wrap.querySelectorAll('.proc-pr-desc').forEach((inp) => {
                inp.placeholder = 'Item name — e.g. Bond Paper A4, Ballpen black';
            });
            const addDesc = block?.querySelector('.proc-pr-add-desc');
            if (addDesc) addDesc.textContent = '+ Add consumable item';
        } else {
            wrap.querySelectorAll('.proc-pr-article').forEach((art) => {
                art.disabled = false;
                art.readOnly = false;
            });
            wrap.querySelectorAll('.proc-pr-article-rm').forEach((rm) => {
                rm.hidden = false;
            });
            wrap.querySelectorAll('.proc-pr-article-hint').forEach((h) => {
                h.textContent = 'title — Table, Phone, Chair…';
            });
            wrap.querySelectorAll('.proc-pr-desc').forEach((inp) => {
                if (!inp.placeholder || inp.placeholder.includes('Bond Paper') || inp.placeholder.includes('Item name')) {
                    inp.placeholder = 'What type — e.g. Executive table, L-type';
                }
            });
            wrap.querySelectorAll('.proc-pr-add-desc').forEach((btn) => {
                btn.textContent = '+ Add description';
            });
        }
    }

    function procClassificationLabel() {
        const isCons = procIsConsumablePr();
        if (isCons) return 'Consumable';
        return (document.getElementById('proc-pr-section')?.value || '').trim() || 'Non-consumable';
    }

    function procScrollPrModalToTop() {
        const overlay = document.getElementById('proc-pr-modal');
        const body = overlay?.querySelector('.proc-pr-modal-body');
        if (body) body.scrollTop = 0;
        if (overlay) overlay.scrollTop = 0;
        const modal = overlay?.querySelector('.proc-modal-pr');
        if (modal) modal.scrollTop = 0;
        // Don't leave focus on the lines block (browser scrolls that into view).
        const active = document.activeElement;
        if (active && body && body.contains(active) && active.closest('.proc-pr-modal-section-lines')) {
            active.blur();
        }
    }

    function procOpenPrModalAtTop() {
        openModal('proc-pr-modal');
        procScrollPrModalToTop();
        requestAnimationFrame(() => {
            procScrollPrModalToTop();
            requestAnimationFrame(procScrollPrModalToTop);
        });
        setTimeout(procScrollPrModalToTop, 40);
        setTimeout(procScrollPrModalToTop, 120);
    }

    window.procOpenProcPrModalCreate = async () => {
        openModal('proc-pr-class-modal');
    };

    async function procOpenProcPrModalCreateWithClass(isConsumable) {
        closeModal('proc-pr-class-modal');
        document.getElementById('proc-pr-modal-mode').textContent =
            'New draft purchase request';
        document.getElementById('proc-pr-edit-id').value = '';
        const dp = document.getElementById('proc-pr-date');
        dp.disabled = false;
        dp.value = new Date().toISOString().slice(0, 10);
        document.getElementById('proc-pr-purpose').value = '';
        document.getElementById('proc-pr-section').value = '';
        document.getElementById('proc-pr-sai').value = '';
        document.getElementById('proc-pr-req-by').value = '';
        document.getElementById('proc-pr-req-title').value = '';
        document.getElementById('proc-pr-req-signed').value = '';
        document.getElementById('proc-pr-approved').value = '';
        document.getElementById('proc-pr-approved-title').value = '';
        document.getElementById('proc-pr-approved-signed').value = '';
        document.getElementById('proc-pr-budget').value = '';
        const scope = procDepartmentScopeFromSession();
        const ob = document.getElementById('proc-pr-office');
        if (scope.departmentScoped || !scope.isAdmin) {
            if (!scope.officeId) {
                showToast(
                    'Your account has no department. Ask an administrator to assign your office, then log out and log in again.',
                    'error',
                );
                return;
            }
        }
        if (ob) {
            if (scope.departmentScoped || !scope.isAdmin) {
                ob.value = String(scope.officeId);
                ob.disabled = true;
            } else {
                const gsoId = procFindGsoOfficeId();
                ob.value = gsoId || (scope.officeId ? String(scope.officeId) : '');
                ob.disabled = !!ob.value;
            }
        }
        procBindAdminOfficeSignatories();
        if (scope.isAdmin) {
            procApplyAdminPrSignatories();
        } else {
            procApplyStaffPrSignatories();
        }
        procApplyConsumableClassification(!!isConsumable);
        await procFillSectionOptions(isConsumable ? 'Consumable' : '', { lockConsumable: !!isConsumable });
        procResetArticleGroups(isConsumable ? [{ article: 'Consumable', description: '' }] : []);
        procSyncConsumableLinesUi();
        procSyncPrModalActions('draft');
        procOpenPrModalAtTop();
        // Prefer starting focus near the top of the form.
        const topFocus =
            document.getElementById('proc-pr-purpose') ||
            document.getElementById('proc-pr-date') ||
            document.getElementById('proc-pr-office');
        if (topFocus && !topFocus.disabled) {
            try {
                topFocus.focus({ preventScroll: true });
            } catch (e) {
                topFocus.focus();
            }
        }
        procScrollPrModalToTop();
    }

    function procArticleGroupsRoot() {
        return document.getElementById('proc-pr-article-groups');
    }

    function collectProcPrLinesFromModal() {
        const wrap = procArticleGroupsRoot();
        if (!wrap) return { ok: false, error: 'Add at least one article.', lines: [] };
        const isCons = procIsConsumablePr();
        const section = (document.getElementById('proc-pr-section')?.value || '').trim();
        if (!section) {
            return { ok: false, error: 'Select a section (inventory category).', lines: [] };
        }
        const rows = [];
        let error = '';
        let line = 0;
        wrap.querySelectorAll('.proc-pr-article-block').forEach((block) => {
            const articleEl = block.querySelector('.proc-pr-article');
            let article = (articleEl?.value || '').trim();
            if (isCons) article = 'Consumable';
            if (typeof lgueMarkField === 'function') lgueMarkField(articleEl, false);
            if (!article) {
                if (typeof lgueMarkField === 'function') lgueMarkField(articleEl, true);
                if (!error) error = 'Each article needs a name (example: Table or Phone).';
                return;
            }
            block.querySelectorAll('tr.proc-pr-row').forEach((tr) => {
                const descEl = tr.querySelector('.proc-pr-desc');
                const qtyEl = tr.querySelector('.proc-pr-qty');
                const unitEl = tr.querySelector('.proc-pr-unit');
                const estEl = tr.querySelector('.proc-pr-est');
                const description = (descEl?.value ?? '').trim();
                [descEl, qtyEl, unitEl, estEl].forEach((el) => {
                    if (typeof lgueMarkField === 'function') lgueMarkField(el, false);
                });
                if (!description) return;
                line += 1;
                const descLabel = isCons ? ('Consumable item ' + line) : ('Line ' + line + ' description');
                const descChk = typeof lgueRequireText === 'function'
                    ? lgueRequireText(description, descLabel, { minLen: 4 })
                    : { ok: true, value: description };
                const qtyChk = typeof lgueParseNumber === 'function'
                    ? lgueParseNumber(qtyEl?.value, 'Line ' + line + ' quantity', { min: 0.0001 })
                    : { ok: true, value: parseFloat(qtyEl?.value) };
                const unitChk = typeof lgueRequireUnit === 'function'
                    ? lgueRequireUnit(unitEl?.value)
                    : { ok: true, value: (unitEl?.value || 'pc') };
                const estRaw = String(estEl?.value ?? '').trim();
                const estChk = estRaw === ''
                    ? { ok: true, value: 0 }
                    : (typeof lgueParseNumber === 'function'
                        ? lgueParseNumber(estEl?.value, 'Line ' + line + ' estimated unit cost', { min: 0 })
                        : { ok: true, value: parseFloat(estEl?.value) || 0 });
                if (!descChk.ok) {
                    if (typeof lgueMarkField === 'function') lgueMarkField(descEl, true);
                    if (!error) error = descChk.message || (isCons
                        ? 'Type the consumable item name in Description.'
                        : 'Each line needs a description.');
                    return;
                }
                if (!qtyChk.ok) {
                    if (typeof lgueMarkField === 'function') lgueMarkField(qtyEl, true);
                    if (!error) error = qtyChk.message;
                    return;
                }
                if (!unitChk.ok) {
                    if (typeof lgueMarkField === 'function') lgueMarkField(unitEl, true);
                    if (!error) error = unitChk.message;
                    return;
                }
                if (!estChk.ok) {
                    if (typeof lgueMarkField === 'function') lgueMarkField(estEl, true);
                    if (!error) error = estChk.message;
                    return;
                }
                rows.push({
                    article,
                    item_group: isCons ? 'Consumable' : section,
                    quantity: qtyChk.value,
                    unit: unitChk.value,
                    description: descChk.value,
                    est_unit_cost: estChk.value,
                });
            });
        });
        if (error) return { ok: false, error, lines: [] };
        if (rows.length === 0) {
            return {
                ok: false,
                error: isCons
                    ? 'Add at least one consumable item name in Description.'
                    : 'Add at least one description under an article.',
                lines: [],
            };
        }
        return { ok: true, error: '', lines: rows };
    }

    function procAppendPrLineRow(prefill, tbody) {
        const body = tbody || document.querySelector('.proc-pr-article-block:last-child .proc-pr-lines-body');
        if (!body) return;
        const p = prefill || {};
        const isCons = procIsConsumablePr();
        const descPh = isCons
            ? 'Item name — e.g. Bond Paper A4, Ballpen black'
            : 'What type — e.g. Executive table, L-type';
        body.insertAdjacentHTML(
            'beforeend',
            `<tr class="proc-pr-row"><td class="proc-pr-n" style="vertical-align:middle"></td>` +
                `<td><input type="text" class="inp proc-pr-desc" placeholder="${esc(descPh)}" style="width:100%" value="${esc(p.description || '')}"/></td>` +
                `<td><input type="number" class="inp proc-pr-qty" min="0.0001" step="any" value="${esc(procFmtQty(p.quantity != null ? p.quantity : 1))}"/></td>` +
                `<td><input type="text" class="inp proc-pr-unit" value="${esc((p.unit || 'pc'))}" style="width:56px"/></td>` +
                `<td><input type="text" inputmode="decimal" class="inp proc-pr-est lgue-money" autocomplete="off" value="${esc(typeof window.lgueMoneyFromNumber === 'function' ? window.lgueMoneyFromNumber(p.est_unit_cost != null ? p.est_unit_cost : 0) : String(p.est_unit_cost != null ? p.est_unit_cost : '0'))}"/></td>` +
                `<td><button type="button" class="btn btn-ghost btn-xs proc-pr-rm" aria-label="Remove row">&times;</button></td></tr>`,
        );
        const row = body.querySelector('tr.proc-pr-row:last-child');
        if (row) {
            row.querySelector('.proc-pr-rm').onclick = () => {
                const block = row.closest('.proc-pr-article-block');
                row.remove();
                if (block && !block.querySelector('tr.proc-pr-row')) {
                    procAppendPrLineRow({}, block.querySelector('.proc-pr-lines-body'));
                }
                procRenumberProcPrLines();
            };
        }
        procRenumberProcPrLines();
    }

    function procAddPrArticleGroup(prefill) {
        const wrap = procArticleGroupsRoot();
        if (!wrap) return null;
        const isCons = procIsConsumablePr();
        if (isCons && wrap.querySelector('.proc-pr-article-block')) {
            // Consumable PRs use one fixed article group only.
            const body = wrap.querySelector('.proc-pr-article-block .proc-pr-lines-body');
            procAppendPrLineRow(prefill || {}, body);
            return wrap.querySelector('.proc-pr-article-block');
        }
        const p = prefill || {};
        if (isCons) {
            p.article = 'Consumable';
        }
        const block = document.createElement('div');
        block.className = 'proc-pr-article-block';
        block.innerHTML =
            `<div class="proc-pr-article-head">` +
                `<label>Article <span class="proc-pr-article-hint">${isCons ? 'fixed — consumable issuance only' : 'title — Table, Phone, Chair…'}</span></label>` +
                `<input type="text" class="inp proc-pr-article" maxlength="255" placeholder="${isCons ? 'Consumable' : 'e.g. Table'}" value="${esc(p.article || (isCons ? 'Consumable' : ''))}" ${isCons ? 'disabled readonly' : ''}/>` +
                `<button type="button" class="btn btn-ghost btn-xs proc-pr-article-rm" ${isCons ? 'hidden' : ''}>Remove article</button>` +
            `</div>` +
            `<div class="proc-lines-table-wrap proc-pr-lines-table-wrap">` +
                `<table class="proc-lines-table"><thead><tr><th>#</th><th>${isCons ? 'Item name *' : 'Description *'}</th><th>Qty</th><th>Unit</th><th>Est ₱ unit</th><th></th></tr></thead>` +
                `<tbody class="proc-pr-lines-body"></tbody></table>` +
            `</div>` +
            `<button type="button" class="btn btn-ghost btn-sm proc-pr-add-desc">${isCons ? '+ Add consumable item' : '+ Add description'}</button>`;
        wrap.appendChild(block);
        block.querySelector('.proc-pr-add-desc').onclick = () => {
            procAppendPrLineRow({}, block.querySelector('.proc-pr-lines-body'));
        };
        block.querySelector('.proc-pr-article-rm').onclick = () => {
            if (procIsConsumablePr()) return;
            if (wrap.querySelectorAll('.proc-pr-article-block').length <= 1) {
                block.querySelector('.proc-pr-article').value = '';
                block.querySelectorAll('tr.proc-pr-row').forEach((tr) => tr.remove());
                procAppendPrLineRow({}, block.querySelector('.proc-pr-lines-body'));
                return;
            }
            block.remove();
            procRenumberProcPrLines();
        };
        if (Array.isArray(p.lines) && p.lines.length) {
            p.lines.forEach((ln) => procAppendPrLineRow(ln, block.querySelector('.proc-pr-lines-body')));
        } else {
            procAppendPrLineRow(p, block.querySelector('.proc-pr-lines-body'));
        }
        return block;
    }

    function procResetArticleGroups(lines) {
        const wrap = procArticleGroupsRoot();
        if (!wrap) return;
        wrap.innerHTML = '';
        const groups = [];
        (lines || []).forEach((ln) => {
            const art = String(ln.article || '').trim() || 'Item';
            let g = groups.find((x) => x.article.toLowerCase() === art.toLowerCase());
            if (!g) {
                g = { article: art, lines: [] };
                groups.push(g);
            }
            g.lines.push(ln);
        });
        if (!groups.length) {
            procAddPrArticleGroup({});
            return;
        }
        groups.forEach((g) => procAddPrArticleGroup(g));
    }

    function procRenumberProcPrLines() {
        const wrap = procArticleGroupsRoot();
        if (!wrap) return;
        let n = 0;
        wrap.querySelectorAll('tr.proc-pr-row').forEach((tr) => {
            n += 1;
            const cell = tr.querySelector('.proc-pr-n');
            if (cell) cell.textContent = String(n);
        });
    }

    window.procAddPrArticleGroup = () => procAddPrArticleGroup({});
    window.procAddPrModalLineRow = () => {
        const body = document.querySelector('.proc-pr-article-block:last-child .proc-pr-lines-body');
        procAppendPrLineRow({}, body);
    };

    window.procOpenProcPrModalEdit = async (data) => {
        const pr = data.purchase_request;
        document.getElementById('proc-pr-modal-mode').textContent =
            (pr.status === 'submitted'
                ? 'Edit request · '
                : (procIsNeedRevision(pr.status, pr.canvass_feedback) ? 'Revise PR · ' : 'Edit draft PR · ')) + pr.pr_number;
        document.getElementById('proc-pr-edit-id').value = String(pr.id);
        document.getElementById('proc-pr-office').value = String(pr.office_id);
        document.getElementById('proc-pr-office').disabled = true;
        const dp2 = document.getElementById('proc-pr-date');
        dp2.value = String(pr.pr_date).slice(0, 10);
        dp2.disabled = true;
        document.getElementById('proc-pr-purpose').value = pr.purpose || '';
        const isCons = Number(pr.is_consumable) === 1 || String(pr.pr_section || '').toLowerCase() === 'consumable';
        procApplyConsumableClassification(isCons);
        await procFillSectionOptions(
            pr.pr_section || (data.lines && data.lines[0] && data.lines[0].item_group) || '',
            { lockConsumable: isCons },
        );
        document.getElementById('proc-pr-sai').value = pr.sai_number || '';
        document.getElementById('proc-pr-req-by').value = pr.requested_by || '';
        document.getElementById('proc-pr-req-title').value = pr.requested_by_title || '';
        document.getElementById('proc-pr-req-signed').value = pr.requested_signed_date
            ? String(pr.requested_signed_date).slice(0, 10)
            : '';
        document.getElementById('proc-pr-approved').value = pr.approved_by || '';
        document.getElementById('proc-pr-approved-title').value = pr.approved_by_title || '';
        const editScope = procDepartmentScopeFromSession();
        if (editScope.isAdmin) {
            const reqBy = document.getElementById('proc-pr-req-by');
            const reqTitle = document.getElementById('proc-pr-req-title');
            const appBy = document.getElementById('proc-pr-approved');
            const appTitle = document.getElementById('proc-pr-approved-title');
            if (reqBy && !reqBy.value.trim()) reqBy.value = editScope.gsoOfficer;
            if (reqTitle) reqTitle.value = editScope.gsoDesignation;
            if (appBy && !appBy.value.trim()) appBy.value = editScope.mayorName;
            if (appTitle && !appTitle.value.trim()) appTitle.value = editScope.mayorTitle;
        } else {
            const reqBy = document.getElementById('proc-pr-req-by');
            const reqTitle = document.getElementById('proc-pr-req-title');
            const appBy = document.getElementById('proc-pr-approved');
            const appTitle = document.getElementById('proc-pr-approved-title');
            if (reqBy) {
                reqBy.disabled = false;
                reqBy.readOnly = false;
                if (!reqBy.value.trim()) reqBy.value = procStaffRequestedName(editScope);
            }
            if (reqTitle) {
                reqTitle.disabled = false;
                reqTitle.readOnly = false;
                if (!reqTitle.value.trim()) reqTitle.value = procStaffRequestedDesignation(editScope);
            }
            if (appBy) {
                appBy.disabled = false;
                appBy.readOnly = false;
                if (!appBy.value.trim()) appBy.value = editScope.mayorName;
            }
            if (appTitle) {
                appTitle.disabled = false;
                appTitle.readOnly = false;
                if (!appTitle.value.trim()) appTitle.value = editScope.mayorTitle;
            }
        }
        document.getElementById('proc-pr-approved-signed').value = pr.approved_signed_date
            ? String(pr.approved_signed_date).slice(0, 10)
            : '';
        document.getElementById('proc-pr-budget').value = pr.budget_received_note || '';
        procResetArticleGroups(data.lines || []);
        procSyncConsumableLinesUi();
        procSyncPrModalActions(pr.status || 'draft');
        procOpenPrModalAtTop();
    };

    function procSyncPrModalActions(status) {
        const st = String(status || 'draft').toLowerCase();
        const submitBtn = document.getElementById('proc-btn-submit-modal');
        const draftBtn = document.getElementById('proc-btn-save-modal');
        const delBtn = document.getElementById('proc-btn-delete-modal');
        const editId = parseInt(document.getElementById('proc-pr-edit-id')?.value || '0', 10);
        if (draftBtn) draftBtn.textContent = 'Draft';
        if (submitBtn) {
            submitBtn.textContent = st === 'needs_revision' ? 'Resubmit' : 'Submit';
            submitBtn.hidden = !procIsDraftLike(st);
        }
        if (delBtn) {
            delBtn.hidden = !(procIsDraftLike(st) && editId > 0 && procCanDeleteDraftPr());
        }
    }

    function procCanDeleteDraftPr() {
        if (typeof userPermissions === 'undefined' || userPermissions.isViewer) return false;
        return !!(userPermissions.canDelete || userPermissions.canEdit);
    }

    async function procSaveProcPrModal(andSubmit) {
        const oid = parseInt(document.getElementById('proc-pr-office').value, 10);
        const prDate = document.getElementById('proc-pr-date').value;
        const purposeEl = document.getElementById('proc-pr-purpose');
        if (typeof lgueMarkField === 'function') lgueMarkField(purposeEl, false);
        const purposeChk = typeof lgueRequireText === 'function'
            ? lgueRequireText(purposeEl?.value || '', 'Purpose', { minLen: 12, minWords: 2 })
            : { ok: true, value: (purposeEl?.value || '').trim() };
        if (!purposeChk.ok) {
            if (typeof lgueMarkField === 'function') lgueMarkField(purposeEl, true);
            showToast(purposeChk.message, 'error');
            return;
        }
        const collected = collectProcPrLinesFromModal();
        if (!collected.ok) {
            showToast(collected.error, 'error');
            return;
        }
        const lines = collected.lines;
        const purpose = purposeChk.value;
        const reqByEl = document.getElementById('proc-pr-req-by');
        const appByEl = document.getElementById('proc-pr-approved');
        const reqByRaw = (reqByEl?.value || '').trim();
        const appByRaw = (appByEl?.value || '').trim();
        if (reqByRaw && typeof lgueRequireName === 'function') {
            const reqChk = lgueRequireName(reqByRaw, 'Requested by');
            if (!reqChk.ok) {
                showToast(reqChk.message, 'error');
                return;
            }
        }
        if (appByRaw && typeof lgueRequireName === 'function') {
            const appChk = lgueRequireName(appByRaw, 'Approved by');
            if (!appChk.ok) {
                showToast(appChk.message, 'error');
                return;
            }
        }
        const reqTitleChk = typeof lgueOptionalText === 'function'
            ? lgueOptionalText(document.getElementById('proc-pr-req-title')?.value || '', 'Requested designation')
            : { ok: true };
        const appTitleChk = typeof lgueOptionalText === 'function'
            ? lgueOptionalText(document.getElementById('proc-pr-approved-title')?.value || '', 'Approved designation')
            : { ok: true };
        if (!reqTitleChk.ok) {
            showToast(reqTitleChk.message, 'error');
            return;
        }
        if (!appTitleChk.ok) {
            showToast(appTitleChk.message, 'error');
            return;
        }
        const hdrExtra = {
            pr_section: document.getElementById('proc-pr-section').value.trim(),
            is_consumable: document.getElementById('proc-pr-is-consumable')?.value === '1' ? 1 : 0,
            sai_number: document.getElementById('proc-pr-sai').value.trim(),
            requested_by_title:
                document.getElementById('proc-pr-req-title').value.trim(),
            approved_by_title:
                document.getElementById('proc-pr-approved-title').value.trim(),
            requested_signed_date:
                document.getElementById('proc-pr-req-signed').value || '',
            approved_signed_date:
                document.getElementById('proc-pr-approved-signed').value || '',
        };
        const editId = document.getElementById('proc-pr-edit-id').value.trim();

        if (editId) {
            if (!checkPermission('edit')) return;
        } else if (!checkPermission('create')) {
            return;
        }

        if (!editId && (!oid || oid <= 0)) {
            showToast('Select an office', 'error');
            return;
        }

        if (andSubmit) {
            const label = procClassificationLabel();
            const txt = document.getElementById('proc-pr-class-confirm-text');
            if (txt) {
                txt.textContent = 'You’re submitting this as: ' + label + '. Continue only if this is correct.';
            }
            const go = document.getElementById('proc-pr-class-confirm-go');
            if (go) {
                go.onclick = async () => {
                    go.onclick = null;
                    closeModal('proc-pr-class-confirm-modal');
                    await procPersistProcPrModal(true, { oid, prDate, purpose, lines, hdrExtra, editId });
                };
            }
            openModal('proc-pr-class-confirm-modal');
            return;
        }

        await procPersistProcPrModal(false, { oid, prDate, purpose, lines, hdrExtra, editId });
    }

    async function procPersistProcPrModal(andSubmit, ctx) {
        const { oid, prDate, purpose, lines, hdrExtra, editId } = ctx;
        const busyId = andSubmit ? 'proc-btn-submit-modal' : 'proc-btn-save-modal';
        await procBusy(busyId, async () => {
            let prId = editId ? parseInt(editId, 10) : 0;
            if (editId) {
                const r = await apiPut('procurement.php', {
                    pr_id: prId,
                    purpose,
                    ...hdrExtra,
                    requested_by: document.getElementById('proc-pr-req-by').value.trim() || null,
                    approved_by: document.getElementById('proc-pr-approved').value.trim() || null,
                    budget_received_note: document.getElementById('proc-pr-budget').value.trim() || null,
                    lines,
                });
                if (!r.success) {
                    showToast(r.message || 'Update failed', 'error');
                    return;
                }
            } else {
                const r = await apiPost('procurement.php', {
                    action: 'create_pr',
                    office_id: oid,
                    pr_date: prDate,
                    purpose,
                    ...hdrExtra,
                    requested_by: document.getElementById('proc-pr-req-by').value.trim() || undefined,
                    approved_by: document.getElementById('proc-pr-approved').value.trim() || undefined,
                    budget_received_note:
                        document.getElementById('proc-pr-budget').value.trim() || undefined,
                    lines,
                });
                if (!r.success) {
                    showToast(r.message || 'Could not save PR', 'error');
                    return;
                }
                prId = r.id ? parseInt(r.id, 10) : 0;
            }

            if (andSubmit && prId) {
                const sent = await apiPost('procurement.php', {
                    action: 'submit_pr',
                    pr_id: prId,
                });
                if (!sent.success) {
                    showToast(sent.message || 'Saved as draft, but submit failed', 'error');
                    closeModal('proc-pr-modal');
                    procSelId = prId;
                    await window.loadProcurement();
                    procSelect(prId);
                    return;
                }
                showToast(sent.message || 'Submitted for canvass approval', 'success');
            } else {
                showToast(andSubmit ? 'Submitted' : 'Draft saved', 'success');
            }
            closeModal('proc-pr-modal');
            procSelId = prId || procSelId;
            await window.loadProcurement();
            if (prId) {
                procSelect(prId);
            }
            if (andSubmit && typeof loadNotifications === 'function') {
                loadNotifications();
            }
        }, andSubmit ? 'Submitting…' : 'Saving…');
    }

    function procOpenSubmitModal(prId) {
        if (!checkPermission('edit')) return;
        const id = parseInt(prId, 10);
        if (!id) return;
        const idEl = document.getElementById('proc-submit-pr-id');
        if (idEl) idEl.value = String(id);
        const isResubmit = String(document.getElementById('proc-btn-submit-pr')?.textContent || '')
            .toLowerCase()
            .includes('resubmit');
        const ttl = document.getElementById('proc-submit-modal-ttl');
        const btn = document.getElementById('proc-submit-confirm-btn');
        if (ttl) ttl.textContent = isResubmit ? 'Resubmit for canvass approval' : 'Submit for canvass approval';
        if (btn) btn.textContent = isResubmit ? 'Resubmit for approval' : 'Submit for approval';
        if (typeof openModal === 'function') openModal('proc-submit-modal');
    }

    async function procConfirmSubmitCanvass() {
        if (!checkPermission('edit')) return;
        const prId = parseInt(document.getElementById('proc-submit-pr-id')?.value || '0', 10);
        if (!prId) {
            showToast('Missing purchase request', 'error');
            return;
        }
        await procBusy('proc-submit-confirm-btn', async () => {
            const r = await apiPost('procurement.php', {
                action: 'submit_pr',
                pr_id: prId,
            });
            if (!r.success) {
                showToast(r.message || 'Submit failed', 'error');
                return;
            }
            if (typeof closeModal === 'function') closeModal('proc-submit-modal');
            showToast(r.message || 'Submitted', 'success');
            await window.loadProcurement();
            await procSelect(prId, { force: true });
            if (typeof loadNotifications === 'function') loadNotifications();
        }, 'Sending…');
    }

    async function procApproveCanvass(prId) {
        if (!checkPermission('edit')) return;
        if (!userPermissions.isAdmin) return;
        await procBusy('proc-btn-approve-pr', async () => {
            const r = await apiPost('procurement.php', {
                action: 'approve_pr',
                pr_id: prId,
            });
            if (!r.success) {
                showToast(r.message || 'Approve failed', 'error');
                return;
            }
            showToast(r.message || 'Approved', 'success');
            await window.loadProcurement();
            await procSelect(prId, { force: true });
            if (typeof loadNotifications === 'function') loadNotifications();
        }, 'Sending…');
    }

    function procOpenRejectModal(prId) {
        if (!checkPermission('edit')) return;
        if (!userPermissions.isAdmin) return;
        const idEl = document.getElementById('proc-reject-pr-id');
        const fbEl = document.getElementById('proc-reject-feedback');
        if (idEl) idEl.value = String(prId);
        if (fbEl) fbEl.value = '';
        if (typeof openModal === 'function') openModal('proc-reject-modal');
    }

    async function procConfirmReject() {
        if (!checkPermission('edit')) return;
        const prId = parseInt(document.getElementById('proc-reject-pr-id')?.value || '0', 10);
        const fbEl = document.getElementById('proc-reject-feedback');
        if (typeof lgueMarkField === 'function') lgueMarkField(fbEl, false);
        const fbChk = typeof lgueRequireText === 'function'
            ? lgueRequireText(fbEl?.value || '', 'Feedback', { minLen: 12, minWords: 2 })
            : { ok: !!(fbEl?.value || '').trim(), value: (fbEl?.value || '').trim(), message: 'Feedback is required when rejecting a canvass submission' };
        if (!prId) {
            showToast('Missing purchase request', 'error');
            return;
        }
        if (!fbChk.ok) {
            if (typeof lgueMarkField === 'function') lgueMarkField(fbEl, true);
            showToast(fbChk.message, 'error');
            return;
        }
        const feedback = fbChk.value;
        await procBusy('proc-reject-confirm-btn', async () => {
            const r = await apiPost('procurement.php', {
                action: 'reject_pr',
                pr_id: prId,
                feedback,
            });
            if (!r.success) {
                showToast(r.message || 'Reject failed', 'error');
                return;
            }
            if (typeof closeModal === 'function') closeModal('proc-reject-modal');
            showToast(r.message || 'Rejected', 'success');
            await window.loadProcurement();
            await procSelect(prId, { force: true });
            if (typeof loadNotifications === 'function') loadNotifications();
        }, 'Sending…');
    }

    function procShowOverlay(id) {
        const el = document.getElementById(id);
        if (!el) return false;
        if (typeof openModal === 'function') {
            openModal(id);
        } else {
            el.classList.add('open');
        }
        return el.classList.contains('open');
    }

    function procOpenDeleteModal(prId) {
        if (!procCanDeleteDraftPr()) return;
        const id = parseInt(prId, 10);
        if (!id) return;
        const idEl = document.getElementById('proc-delete-pr-id');
        if (idEl) idEl.value = String(id);
        if (!procShowOverlay('proc-delete-modal')) {
            showToast('Delete confirmation is unavailable', 'error');
        }
    }

    async function procConfirmDeleteDraft() {
        if (!procCanDeleteDraftPr()) return;
        const prId = parseInt(document.getElementById('proc-delete-pr-id')?.value || '0', 10);
        if (!prId) {
            showToast('Missing purchase request', 'error');
            return;
        }
        await procBusy('proc-delete-confirm-btn', async () => {
            const r = await apiDelete('procurement.php?id=' + prId);
            if (!r.success) {
                showToast(r.message || 'Delete failed', 'error');
                return;
            }
            if (typeof closeModal === 'function') {
                closeModal('proc-delete-modal');
                closeModal('proc-pr-modal');
            }
            showToast(r.message || 'Deleted', 'success');
            procSelId = 0;
            window.loadProcurement();
            const detail = document.getElementById('proc-detail');
            if (detail) {
                detail.innerHTML =
                    '<p class="muted">Select a PR in the table to review quotations, PO, and AIR.</p>';
            }
        }, 'Deleting…');
    }

    function procPrepQuoteModal(prId, slot, detail) {
        document.getElementById('proc-q-pr-id').value = String(prId);
        document.getElementById('proc-q-slot').value = String(slot);

        let match = null;
        (detail.quotations || []).forEach((q) => {
            if (parseInt(q.slot, 10) === slot) {
                match = q;
            }
        });

        document.getElementById('proc-q-supplier').value =
            match?.supplier_name != null ? match.supplier_name : '';
        document.getElementById('proc-q-addr').value =
            match?.supplier_address != null ? match.supplier_address : '';
        document.getElementById('proc-q-date').value = (
            match?.quotation_date ? String(match.quotation_date).slice(0, 10) : new Date().toISOString().slice(0, 10));

        const byLine = {};
        if (match && match.lines) {
            match.lines.forEach((ln) => {
                byLine[ln.pr_line_id] = ln;
            });
        }

        const lines = detail.lines || [];
        const tbody = document.getElementById('proc-q-price-body');
        tbody.innerHTML = lines
            .map((pl) => {
                const ql = byLine[pl.id];
                const up =
                    ql && ql.unit_price != null ? ql.unit_price : '';
                const tot =
                    ql && ql.line_total != null ? ql.line_total : '';
                const desc = `${procFmtQty(pl.quantity)} × ${pl.unit}: ${pl.description}`;
                return `
        <tr>
          <td>${esc(desc)}</td>
          <td><input type="text" inputmode="decimal" class="inp proc-price-inp lgue-money"
              data-plid="${pl.id}" autocomplete="off" style="width:140px;text-align:right" value="${esc(typeof window.lgueMoneyFromNumber === 'function' ? window.lgueMoneyFromNumber(up !== '' ? up : 0) : String(up !== '' ? up : '0'))}"/></td>
          <td class="muted">${esc(tot !== '' ? Number(tot).toFixed(2) : '')}</td>
        </tr>`;
            })
            .join('');

        openModal('proc-quote-modal');
    }


    async function procSaveQuoteFromModal(detailLines) {
        if (!checkPermission('create')) return;
        const prId = parseInt(document.getElementById('proc-q-pr-id').value, 10);
        const slot = parseInt(document.getElementById('proc-q-slot').value, 10);
        const supplierEl = document.getElementById('proc-q-supplier');
        if (typeof lgueMarkField === 'function') lgueMarkField(supplierEl, false);
        const supplierChk = typeof lgueRequireText === 'function'
            ? lgueRequireText(supplierEl?.value || '', 'Supplier name', { minLen: 3 })
            : { ok: !!(supplierEl?.value || '').trim(), value: (supplierEl?.value || '').trim(), message: 'Supplier name is required' };
        if (!supplierChk.ok) {
            if (typeof lgueMarkField === 'function') lgueMarkField(supplierEl, true);
            showToast(supplierChk.message, 'error');
            return;
        }
        const supplier = supplierChk.value;

        const prices = [];
        for (const pl of detailLines || []) {
            const inp = document.querySelector(`.proc-price-inp[data-plid="${pl.id}"]`);
            if (typeof lgueMarkField === 'function') lgueMarkField(inp, false);
            const chk = typeof lgueParseNumber === 'function'
                ? lgueParseNumber(inp?.value, 'Unit price', { min: 0 })
                : { ok: true, value: parseFloat(inp?.value) || 0 };
            if (!chk.ok) {
                if (typeof lgueMarkField === 'function') lgueMarkField(inp, true);
                showToast(chk.message, 'error');
                return;
            }
            prices.push({ pr_line_id: pl.id, unit_price: chk.value });
        }

        await procBusy('proc-btn-save-quote', async () => {
            const r = await apiPost('procurement.php', {
                action: 'add_quotation',
                pr_id: prId,
                slot,
                supplier_name: supplier,
                supplier_address: document.getElementById('proc-q-addr').value.trim(),
                quotation_date: document.getElementById('proc-q-date').value || undefined,
                prices,
            });
            if (!r.success) {
                showToast(r.message || 'Unable to save quotation', 'error');
                return;
            }
            closeModal('proc-quote-modal');
            showToast(r.message || 'Quotation saved', 'success');
            procSelect(prId);
            window.loadProcurement();
        }, 'Saving…');
    }

    async function procDeleteQuote(qId, prId) {
        if (!checkPermission('delete')) return;
        if (!confirm('Remove this quotation?')) return;
        const r = await apiPost('procurement.php', {
            action: 'delete_quotation',
            quotation_id: qId,
        });
        if (!r.success) {
            showToast(r.message || 'Could not delete', 'error');
            return;
        }
        showToast('Quotation deleted', 'success');
        procSelect(prId);
        window.loadProcurement();
    }

    async function procCreatePo(prId) {
        if (!checkPermission('create')) return;
        const sel = document.getElementById('proc-po-quote');
        const qId = sel ? parseInt(sel.value, 10) : 0;
        if (!qId) {
            showToast('Select a quotation to award', 'error');
            return;
        }
        await procBusy('proc-btn-create-po', async () => {
            const r = await apiPost('procurement.php', {
                action: 'create_po',
                pr_id: prId,
                quotation_id: qId,
                po_date: document.getElementById('proc-po-date').value || undefined,
                mode_of_procurement:
                    (
                        document.getElementById('proc-po-mode')?.value ?? 'SVP'
                    ).trim() || 'SVP',
                place_of_delivery:
                    (
                        document.getElementById('proc-po-place')?.value ?? ''
                    ).trim() || undefined,
                payment_term:
                    (
                        document.getElementById('proc-po-pay')?.value ?? ''
                    ).trim() || undefined,
            });
            if (!r.success) {
                showToast(r.message || 'PO not created', 'error');
                return;
            }
            showToast(`PO saved · ${r.po_number}`, 'success');
            procSelect(prId);
            window.loadProcurement();
            if (typeof loadNotifications === 'function') loadNotifications();
        }, 'Saving…');
    }

    async function procCreateAir(poId, prId) {
        if (!checkPermission('create')) return;
        await procBusy('proc-btn-save-air', async () => {
        const r = await apiPost('procurement.php', {
            action: 'create_air',
            po_id: poId,
            air_date:
                (
                    document.getElementById('proc-air-date')?.value ?? ''
                ).trim() ||
                undefined,
            si_dr_notes:
                (
                    document.getElementById('proc-air-dr')?.value ?? ''
                ).trim() || undefined,
            requisitioning_office_display:
                (
                    document.getElementById('proc-air-office')?.value ?? ''
                ).trim() || undefined,
        });
        if (!r.success) {
            showToast(r.message || 'AIR not saved', 'error');
            return;
        }
        showToast(`AIR saved · ${r.air_number}`, 'success');
        procSelect(prId);
        window.loadProcurement();
        if (typeof loadNotifications === 'function') loadNotifications();
        }, 'Saving…');
    }

    function procRenderDetail(d) {
        window.__procDetail = d;
        const pr = d.purchase_request;
        const lines = d.lines || [];
        const quotes = [...(d.quotations || [])].sort((a, b) => a.slot - b.slot);
        const po = d.purchase_order;
        const air = d.acceptance_report;
        const best = quotes.reduce(
            (b, q) =>
                (!b ||
                    Number(q.total_amount || 1e99) <
                        Number(b.total_amount || 1e99)
                    ? q
                    : b),
            null,
        );

        const canPrint = pgCanPrint();
        const canC = !!(userPermissions.canCreate && !userPermissions.isViewer);
        const canE = !!(userPermissions.canEdit && !userPermissions.isViewer);
        const canD = !!(userPermissions.canDelete && !userPermissions.isViewer);
        // Department (non-admin) users may only create/submit their own draft PR.
        // Quotations, PO issuance, and AIR filing are admin/GSO-only — staff sees
        // these sections read-only (view_only_after_submit RBAC scope).
        const canManage = canC && !!userPermissions.isAdmin;
        const canRemoveQuote = canD && !!userPermissions.isAdmin;

        const printPr = canPrint
            ? `<button type="button" class="btn btn-ghost btn-sm" id="proc-btn-print-pr"><i class="fas fa-print" aria-hidden="true"></i> Print PR</button>`
            : '';

        let draftActs = '';
        const feedback = String(pr.canvass_feedback || '').trim();
        const draftLike = procIsDraftLike(pr.status);
        const needRevision = procIsNeedRevision(pr.status, feedback);
        if (draftLike && canE) {
            draftActs += `<button type="button" class="btn btn-blue btn-sm" id="proc-btn-edit-pr">${needRevision ? 'Revise' : 'Edit draft'}</button>`;
        }
        if (draftLike && canE) {
            const submitLabel = needRevision ? 'Resubmit for canvass' : 'Submit for canvass';
            draftActs += `<button type="button" class="btn btn-dark btn-sm" id="proc-btn-submit-pr">${submitLabel}</button>`;
        }
        if (draftLike && procCanDeleteDraftPr()) {
            draftActs +=
                `<button type="button" class="btn btn-red btn-sm" id="proc-btn-del-draft" data-pr-id="${esc(pr.id)}">Delete PR</button>`;
        }
        if (pr.status === 'submitted' && canE && userPermissions.isAdmin) {
            draftActs += `<button type="button" class="btn btn-blue btn-sm" id="proc-btn-edit-pr" title="Minor revision — edit this request without sending it back">Draft</button>`;
            draftActs += `<button type="button" class="btn btn-green btn-sm" id="proc-btn-approve-pr">Approve canvass</button>`;
            draftActs += `<button type="button" class="btn btn-red btn-sm" id="proc-btn-reject-pr">Reject</button>`;
        }

        let statusBanner = '';
        if (needRevision) {
            statusBanner =
                `<div class="proc-reject-banner" role="status">` +
                `<strong>Canvass returned for revision</strong>` +
                `<p>${esc(feedback)}</p>` +
                `<span class="muted">Address the admin's feedback and resubmit.</span>` +
                `</div>`;
        } else if (pr.status === 'submitted') {
            statusBanner =
                `<div class="proc-await-banner" role="status">` +
                `This purchase request is waiting for admin canvass approval. Quotations cannot be entered until it is approved.` +
                (userPermissions.isAdmin
                    ? ` Minor change: use <strong>Draft</strong>. Bigger change: <strong>Reject</strong> so the department revises it.`
                    : '') +
                `</div>`;
        }

        const linesHtml =
            `<section id="wf-sec-lines" class="proc-sec proc-sec-lines" aria-labelledby="proc-sec-lines-h">` +
            `<div class="proc-sec-head">` +
            `<div class="proc-sec-head-primary">` +
            `<span class="proc-sec-step" aria-hidden="true">01</span>` +
            `<h4 id="proc-sec-lines-h" class="proc-sec-title"><i class="fas fa-table-list" aria-hidden="true"></i> Requested items</h4>` +
            `</div>` +
            `<span class="proc-sec-meta">${lines.length} line${lines.length === 1 ? '' : 's'}</span>` +
            `</div>` +
            `<div class="proc-lines-scroll">` +
            `<table class="proc-lines-table"><thead><tr>` +
            `<th scope="col">#</th><th scope="col">Qty</th><th scope="col">UoM</th><th scope="col">Description</th>` +
            `<th scope="col">Est. unit</th><th scope="col">Est. line</th>` +
            `</tr></thead><tbody>` +
            lines
                .map(
                    (l) =>
                        `<tr><td>${esc(l.line_no)}</td><td>${esc(procFmtQty(l.quantity))}</td><td>${esc(l.unit)}</td>` +
                        `<td>${esc(l.description)}</td>` +
                        `<td>${esc(procFmtMoney(l.est_unit_cost))}</td>` +
                        `<td>${esc(procFmtMoney(l.est_line_total))}</td></tr>`,
                )
                .join('') +
            `</tbody></table></div></section>`;

        let qHtml =
            `<section id="wf-sec-quotes" class="proc-sec proc-sec-quotes" aria-labelledby="proc-sec-quotes-h">` +
            `<div class="proc-sec-head">` +
            `<div class="proc-sec-head-primary">` +
            `<span class="proc-sec-step" aria-hidden="true">02</span>` +
            `<h4 id="proc-sec-quotes-h" class="proc-sec-title"><i class="fas fa-comments-dollar" aria-hidden="true"></i> Supplier quotations</h4>` +
            `</div>` +
            `<span class="proc-sec-meta">3 canvass slots</span>` +
            `</div>` +
            `<div class="proc-slots-grid">`;

        let poBlock = '';

        const bestBanner = best
            ? `<div class="proc-best-banner" role="status"><span class="proc-best-icon" aria-hidden="true"><i class="fas fa-medal"></i></span>` +
              `<div class="proc-best-copy"><strong>Lowest canvass</strong>` +
              `<span>Slot ${best.slot} · ${esc(best.supplier_name)} · ₱${esc(procFmtMoney(best.total_amount))}</span></div></div>`
            : '';

        for (let slot = 1; slot <= 3; slot++) {
            const qFound = quotes.find((x) => parseInt(x.slot, 10) === slot);

            const rowState = qFound ? 'proc-slot-card--filled' : 'proc-slot-card--empty';
            const bodyHtml = qFound
                ? `<div class="proc-slot-supplier">${esc(qFound.supplier_name)}</div>` +
                  `<div class="proc-slot-amount">₱${esc(procFmtMoney(qFound.total_amount))}</div>`
                : `<div class="proc-slot-empty muted">${
                      pr.status === 'quoting'
                          ? 'No quotation yet — add prices for this slot.'
                          : pr.status === 'submitted'
                            ? 'Waiting for admin canvass approval.'
                            : 'No quotation yet.'
                  }</div>`;

            qHtml +=
                `<article class="proc-slot-card proc-slot card ${rowState}">` +
                `<div class="proc-slot-card-main">` +
                `<span class="proc-slot-num" aria-hidden="true">${slot}</span>` +
                `<div class="proc-slot-body">` +
                `<span class="proc-slot-label">Canvas ${slot}</span>` +
                bodyHtml +
                `</div></div>` +
                `<div class="proc-slot-actions filters">`;

            if (qFound && canPrint) {
                qHtml += `<button type="button" class="btn btn-ghost btn-xs proc-quote-print" data-qid="${qFound.id}"><i class="fas fa-print" aria-hidden="true"></i> Print RFQ</button>`;
            }
            if (qFound && canRemoveQuote && po == null) {
                qHtml += `<button type="button" class="btn btn-red btn-xs proc-quote-del" data-qid="${qFound.id}" data-pr="${pr.id}">Remove</button>`;
            }
            if (canManage && pr.status === 'quoting') {
                qHtml += `<button type="button" class="btn btn-dark btn-xs proc-quote-fill" data-slot="${slot}" data-pr="${pr.id}">` +
                    (qFound ? 'Update prices' : 'Enter prices') +
                    `</button>`;
            }
            qHtml += '</div></article>';
        }
        qHtml += '</div></section>';

        if (
            po == null &&
            canManage &&
            quotes.length &&
            pr.status === 'quoting'
        ) {
            const opts =
                quotes
                    .map(
                        (q) =>
                            `<option value="${q.id}">Slot ${q.slot}: ${esc(q.supplier_name)} · ₱${esc(procFmtMoney(q.total_amount))}</option>`,
                    )
                    .join('') ?? '';
            poBlock =
                `<section id="wf-sec-issue" class="proc-sec proc-sec-issue proc-po-act card" aria-labelledby="proc-sec-issue-h">` +
                `<div class="proc-sec-head">` +
                `<div class="proc-sec-head-primary">` +
                `<span class="proc-sec-step" aria-hidden="true">03</span>` +
                `<h4 id="proc-sec-issue-h" class="proc-sec-title"><i class="fas fa-file-circle-check" aria-hidden="true"></i> Issue purchase order</h4>` +
                `</div>` +
                `<span class="proc-sec-meta">Award</span>` +
                `</div>` +
                `<p class="proc-sec-lead muted">Choose the winning quotation and record the PO details below.</p>` +
                `<div class="proc-po-form-grid">` +
                `<select id="proc-po-quote" class="filter-sel proc-po-form-sel">${opts}</select>` +
                `<input type="date" id="proc-po-date" class="inp" value="${esc(new Date().toISOString().slice(0, 10))}"/>` +
                `<input type="text" id="proc-po-mode" class="inp" placeholder="Modality (e.g. SVP)" value="SVP"/>` +
                `<input type="text" id="proc-po-place" class="inp" placeholder="Delivery / place (optional)"/>` +
                `<input type="text" id="proc-po-pay" class="inp" placeholder="Payment terms (optional)"/>` +
                `<button type="button" class="btn btn-dark btn-sm" id="proc-btn-create-po">Create PO</button>` +
                `</div>` +
                `<p class="muted proc-form-hint">One PO per PR. After issuance, quotations cannot be cleared while the PO references them.</p>` +
                `</section>`;
        }

        let poAirHtml = '';

        if (po) {
            const printPoBtn =
                canPrint
                    ? `<button type="button" class="btn btn-ghost btn-sm proc-po-print" data-poid="${po.id}"><i class="fas fa-print" aria-hidden="true"></i> Print PO</button>`
                    : '';
            poAirHtml +=
                `<section id="wf-sec-delivery" class="proc-sec proc-sec-delivery" aria-labelledby="proc-sec-del-h">` +
                `<div class="proc-sec-head">` +
                `<div class="proc-sec-head-primary">` +
                `<span class="proc-sec-step" aria-hidden="true">03</span>` +
                `<h4 id="proc-sec-del-h" class="proc-sec-title"><i class="fas fa-truck-ramp-box" aria-hidden="true"></i> Purchase order &amp; AIR</h4>` +
                `</div>` +
                `<span class="proc-sec-meta">Fulfillment</span>` +
                `</div>` +
                `<div class="proc-po card">` +
                `<div class="proc-po-hd">` +
                `<div class="proc-po-hd-main"><i class="fas fa-file-contract" aria-hidden="true"></i><strong>Purchase order</strong></div>` +
                `${printPoBtn}</div>` +
                `<div class="muted proc-po-lines">` +
                `${esc(po.po_number)} · ${esc(po.po_date)} · ${esc(po.supplier_name)} · ₱${esc(procFmtMoney(po.total_amount))}` +
                `</div>` +
                `</div>`;

            if (air) {
                const printAir =
                    canPrint
                        ? `<button type="button" class="btn btn-ghost btn-sm proc-air-print" data-airid="${air.id}"><i class="fas fa-print" aria-hidden="true"></i> Print AIR</button>`
                        : '';
                poAirHtml +=
                    `<div class="proc-air-block proc-air-block--done proc-air-shell">` +
                    `<div class="proc-air-hd"><strong>Acceptance &amp; inspection</strong>${printAir ? printAir : ''}</div>` +
                    `<span class="muted">${esc(air.air_number)} · ${esc(air.air_date)} · claimant: ${esc(air.supplier_or_claimant)}</span>` +
                    `</div>`;
            } else if (canManage) {
                poAirHtml +=
                    `<div class="proc-air-block proc-air-block--form proc-air-shell">` +
                    `<strong class="proc-air-form-title">Record AIR (one per PO)</strong>` +
                    `<div class="proc-air-form-grid">` +
                    `<label class="proc-fld"><span class="proc-fld-lbl muted">Date</span>` +
                    `<input id="proc-air-date" type="date" class="inp" value="${esc(new Date().toISOString().slice(0, 10))}"/></label>` +
                    `<label class="proc-fld"><span class="proc-fld-lbl muted">SI / DR notes</span>` +
                    `<input id="proc-air-dr" class="inp" value="see attached"/></label>` +
                    `<label class="proc-fld"><span class="proc-fld-lbl muted">Office (optional)</span>` +
                    `<input id="proc-air-office" class="inp"/></label>` +
                    `<button type="button" id="proc-btn-save-air" class="btn btn-dark btn-sm proc-air-submit">Create AIR</button>` +
                    `</div>` +
                    `</div>`;
            }
            const linkedIssuance = d.linked_issuance;
            if (linkedIssuance) {
                poAirHtml +=
                    `<div class="proc-air-block proc-air-block--done proc-air-shell">` +
                    `<div class="proc-air-hd"><strong><i class="fas fa-circle-check" aria-hidden="true"></i> PAR generated — item received into inventory</strong></div>` +
                    `<span class="muted">${esc(linkedIssuance.form_type)} ${esc(linkedIssuance.form_number)} · ${esc(linkedIssuance.property_number || '')} · issued to ${esc(linkedIssuance.issued_to || '')} on ${esc(linkedIssuance.issue_date || '')}</span>` +
                    `</div>`;
            }

            poAirHtml += '</section>';
        }

        const metaBits = [];
        if (pr.pr_section) {
            metaBits.push(`<span class="proc-reg-chip"><i class="fas fa-folder-open" aria-hidden="true"></i> ${esc(pr.pr_section)}</span>`);
        }
        if (pr.sai_number) {
            metaBits.push(`<span class="proc-reg-chip"><i class="fas fa-hashtag" aria-hidden="true"></i> SAI ${esc(pr.sai_number)}</span>`);
        }
        const metaLine = metaBits.length
            ? `<div class="proc-reg-line">${metaBits.join('')}</div>`
            : '';

        const stSlug = procStatusSlug(pr.status);
        const heroWfClass = procStaffWorkflowOverlay() ? ' proc-detail-hero--wf' : '';
        const html =
            `<div class="proc-wf-inner proc-wf-inner--detail">` +
            `<header id="wf-sec-summary" class="proc-head proc-detail-hero${heroWfClass}">` +
            `<div class="proc-detail-hero-main">` +
            `<p class="proc-detail-kicker">${esc(pr.office_name || 'Requesting office')}</p>` +
            `<h3 class="proc-detail-prno">${esc(pr.pr_number)}</h3>` +
            `${metaLine}` +
            `<div class="proc-detail-chips">` +
            `<span class="proc-chip"><i class="fas fa-calendar-day" aria-hidden="true"></i>${esc(procFmtPrDate(pr.pr_date))}</span>` +
            `<span class="proc-chip proc-chip-grow"><i class="fas fa-bullseye" aria-hidden="true"></i><span class="proc-chip-purpose">${esc(pr.purpose || '—')}</span></span>` +
            `<span class="proc-status-pill proc-status-pill--${stSlug}">${esc(procDisplayStatus(pr.status))}</span>` +
            `</div>` +
            `${bestBanner}` +
            `${statusBanner}` +
            `</div>` +
            `<div class="proc-detail-actions filters proc-detail-actions--toolbar">` +
            printPr +
            draftActs +
            `</div>` +
            `</header>` +
            `${linesHtml}${qHtml}${poBlock}${poAirHtml}` +
            `</div>`;

        const pane = document.getElementById('proc-detail');
        pane.innerHTML = html;
        if (procStaffWorkflowOverlay()) {
            procFillWorkflowModalNav(!!poBlock, !!po);
        }

        document.getElementById('proc-btn-print-pr')?.addEventListener('click', () => {
            window.open(procPrintUrl('pr', pr.id), '_blank', 'noopener');
        });
        document.getElementById('proc-btn-submit-pr')?.addEventListener('click', () => {
            procOpenSubmitModal(pr.id);
        });
        document.getElementById('proc-btn-approve-pr')?.addEventListener('click', () => {
            procApproveCanvass(pr.id);
        });
        document.getElementById('proc-btn-reject-pr')?.addEventListener('click', () => {
            procOpenRejectModal(pr.id);
        });
        document.getElementById('proc-btn-del-draft')?.addEventListener('click', (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            const fromBtn = parseInt(ev.currentTarget.getAttribute('data-pr-id') || '0', 10);
            procOpenDeleteModal(fromBtn || pr.id);
        });
        document.getElementById('proc-btn-edit-pr')?.addEventListener(
            'click',
            () => {
                window.procOpenProcPrModalEdit(d);
            },
        );

        pane.querySelectorAll('.proc-quote-print').forEach((btn) => {
            btn.onclick = () => {
                const qId = parseInt(btn.getAttribute('data-qid') || '0', 10);
                if (!qId) {
                    showToast('Nothing to print for this slot yet.', 'error');
                    return;
                }
                window.open(procPrintUrl('rfq', qId), '_blank', 'noopener');
            };
        });
        pane.querySelectorAll('.proc-quote-del').forEach((btn) => {
            btn.onclick = () => {
                const qId = btn.getAttribute('data-qid');
                const prIdAttr = btn.getAttribute('data-pr');
                procDeleteQuote(parseInt(qId, 10), parseInt(prIdAttr, 10));
            };
        });
        pane.querySelectorAll('.proc-quote-fill').forEach((btn) => {
            btn.onclick = () => {
                const slot = btn.getAttribute('data-slot');
                const prAttr = btn.getAttribute('data-pr');
                procPrepQuoteModal(
                    parseInt(prAttr, 10),
                    parseInt(slot, 10),
                    d,
                );
            };
        });

        document.getElementById('proc-btn-create-po')?.addEventListener('click', () => {
            procCreatePo(pr.id);
        });

        pane.querySelector('.proc-po-print')?.addEventListener('click', (ev) => {
            const poid = parseInt(ev.currentTarget.getAttribute('data-poid') || '0', 10);
            if (!poid) return;
            window.open(procPrintUrl('po', poid), '_blank', 'noopener');
        });
        pane.querySelector('.proc-air-print')?.addEventListener('click', (ev) => {
            const aid = parseInt(ev.currentTarget.getAttribute('data-airid') || '0', 10);
            if (!aid) return;
            window.open(procPrintUrl('air', aid), '_blank', 'noopener');
        });
        document.getElementById('proc-btn-save-air')?.addEventListener('click', () =>
            procCreateAir(po.id, pr.id),
        );
    }

    function procClearSelection() {
        procDetailReqId += 1;
        procSelId = 0;
        window.__procDetail = null;
        document.querySelectorAll('#proc-pr-tbody tr.proc-row-sel').forEach((tr) => {
            tr.classList.remove('proc-row-sel');
        });
        procCloseStaffWorkflowModal();
        procClearWorkflowModalNav();
        procSetAdminWorkflowVisible(false);
        const pane = document.getElementById('proc-detail');
        if (pane) {
            pane.innerHTML = PROC_DETAIL_IDLE_HTML;
        }
    }

    async function procSelect(id, opts) {
        const nid = parseInt(id, 10) || 0;
        if (!nid) {
            return;
        }
        const force = !!(opts && opts.force);
        const silent = !!(opts && opts.silent);
        /* Staff: second click on the active row dismisses the workflow modal. */
        if (procSelId === nid && !procIsAdminPrList() && !force) {
            procClearSelection();
            return;
        }

        const reqId = procDetailReqId + 1;
        procDetailReqId = reqId;

        procSelId = nid;
        document.querySelectorAll('#proc-pr-tbody tr').forEach((tr) => {
            tr.classList.toggle('proc-row-sel', tr.dataset.id === String(nid));
        });
        const pane = document.getElementById('proc-detail');
        if (!silent) {
            procClearWorkflowModalNav();
            pane.innerHTML = procDetailLoadingHtml('Loading PR…');
            procOpenStaffWorkflowModal();
            procSetAdminWorkflowVisible(true);
        }

        const res = await apiGet(`procurement.php?id=${nid}`);
        if (reqId !== procDetailReqId) {
            return;
        }

        if (!res.success || !res.data) {
            if (silent) {
                return;
            }
            pane.innerHTML = procDetailErrorHtml(res.message || 'Could not load');
            procClearWorkflowModalNav();
            procSelId = 0;
            document.querySelectorAll('#proc-pr-tbody tr').forEach((tr) => {
                tr.classList.remove('proc-row-sel');
            });
            procSetAdminWorkflowVisible(false);
            return;
        }
        procRenderDetail(res.data);
    }

    function procEnsureOfficeFilterPopulated() {
        const sel = document.getElementById('f-proc-office');
        if (!sel) return;
        const scope = procDepartmentScopeFromSession();
        if (scope.departmentScoped || !scope.isAdmin) {
            sel.style.display = 'none';
            return;
        }
        sel.style.display = '';
        if (sel.dataset.populated === '1') return;
        const list = (typeof offices !== 'undefined' && Array.isArray(offices)) ? offices : [];
        if (!list.length) return;
        const current = sel.value;
        sel.innerHTML =
            '<option value="">All offices</option>' +
            list.map((o) => `<option value="${o.id}">${esc(o.name)}</option>`).join('');
        sel.value = current;
        sel.dataset.populated = '1';
    }

    function procRelabelAdminStatusFilter() {
        const sel = document.getElementById('f-proc-status');
        if (!sel || !procIsAdminPrList()) return;
        const draftOpt = sel.querySelector('option[value="draft"]');
        if (draftOpt) draftOpt.textContent = 'My drafts';
    }

    function procListFingerprint(rows) {
        return (rows || [])
            .map((r) =>
                [
                    r.id,
                    r.status,
                    r.quotation_count,
                    r.has_po,
                    String(r.canvass_feedback || '').length,
                    r.updated_at || '',
                ].join(':'),
            )
            .join('|');
    }

    function procRenderPrRows(rows) {
        const tbody = document.getElementById('proc-pr-tbody');
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML =
                `<tr><td colspan="${procPrColspan()}" class="muted">No purchase requests yet.</td></tr>`;
            return;
        }
        const adminList = procIsAdminPrList();
        tbody.innerHTML = rows
            .map((r) => {
                const hasPo = r.has_po == 1 || r.has_po === true;
                const poPill = hasPo
                    ? '<span class="staff-pr-po staff-pr-po--yes">Yes</span>'
                    : '<span class="staff-pr-po staff-pr-po--no">—</span>';
                const revision = procIsNeedRevision(r.status, r.canvass_feedback);
                const stSlug = revision ? 'needs_revision' : procStatusSlug(r.status);
                const viewCell = adminList
                    ? `<td class="proc-pr-action"><button type="button" class="btn btn-ghost btn-xs proc-pr-view" data-pr-id="${Number(r.id)}">View</button></td>`
                    : '';
                return `<tr data-id="${
                    r.id
                }" data-status="${esc(r.status || '')}" data-needs-fix="${revision ? '1' : '0'}" data-has-po="${hasPo ? '1' : '0'}" data-consumable="${Number(r.is_consumable) === 1 ? '1' : '0'}"><td>${esc(r.pr_number)}</td>` +
                    `<td>${esc(r.office_name || '')}</td>` +
                    `<td>${esc(procFmtPrDate(r.pr_date))}</td>` +
                    `<td><span class="staff-pr-status staff-pr-status--${stSlug}">${esc(procDisplayStatus(r.status, r.canvass_feedback))}</span></td>` +
                    `<td style="text-align:center"><span class="staff-pr-rfq">${esc(
                        String(r.quotation_count ?? 0),
                    )}</span></td>` +
                    `<td style="text-align:right">₱ ${esc(procFmtMoney(r.estimate_total))}</td>` +
                    `<td>${poPill}</td>` +
                    viewCell +
                    `</tr>`;
            })
            .join('');
        if (adminList) {
            tbody.querySelectorAll('.proc-pr-view').forEach((btn) => {
                btn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    procSelect(parseInt(btn.getAttribute('data-pr-id'), 10));
                };
            });
        } else {
            tbody.querySelectorAll('tr[data-id]').forEach((tr) => {
                tr.onclick = () => procSelect(parseInt(tr.dataset.id, 10));
            });
        }
        tbody.querySelectorAll('tr[data-id]').forEach((tr) => {
            if (!adminList) tr.style.cursor = 'pointer';
            tr.classList.toggle('proc-row-sel', tr.dataset.id === String(procSelId));
        });
        rtProcList();
    }

    window.loadProcurement = async (opts) => {
        const silent = !!(opts && opts.silent);
        const tbody = document.getElementById('proc-pr-tbody');
        const hint = document.getElementById('proc-migrate-hint');
        if (!tbody || !hint) return;

        procEnsureOfficeFilterPopulated();
        procRelabelAdminStatusFilter();

        if (!silent) {
            tbody.innerHTML = `<tr><td colspan="${procPrColspan()}" class="muted">Loading…</td></tr>`;
        }
        hint.style.display = 'none';

        const scope = procDepartmentScopeFromSession();
        const officeFilter = (scope.departmentScoped || !scope.isAdmin)
            ? ''
            : (document.getElementById('f-proc-office')?.value || '');
        const statusFilter = document.getElementById('f-proc-status')?.value || '';
        const qs = new URLSearchParams();
        if (officeFilter) qs.set('office_id', officeFilter);
        const serverStatus = !scope.departmentScoped && scope.isAdmin
            && statusFilter
            && !['needs_fix', 'po'].includes(statusFilter);
        if (serverStatus) qs.set('status', statusFilter);
        const query = qs.toString();
        const res = await apiGet(`procurement.php${query ? `?${query}` : ''}`);
        if (!res.success) {
            if (silent) return;
            tbody.innerHTML = `<tr><td colspan="${procPrColspan()}">${esc(res.message || 'Unavailable')}</td></tr>`;
            if (res.hint) {
                hint.textContent = res.hint;
                hint.style.display = '';
            }
            document.getElementById('proc-detail').innerHTML = procDetailErrorHtml(
                res.message || 'Purchase requests unavailable.',
            );
            procClearWorkflowModalNav();
            procSetAdminWorkflowVisible(false);
            return;
        }

        const rows = res.data || [];
        const fp = procListFingerprint(rows);
        const same = silent && fp === window.__procListFp;
        window.__staffPrs = rows;
        window.__procListFp = fp;
        if (typeof window.refreshStaffDashboard === 'function') {
            window.refreshStaffDashboard(rows);
        }
        if (same) {
            return;
        }

        procRenderPrRows(rows);

        if (!silent) {
            document.getElementById('proc-detail').innerHTML = procSelId
                ? procDetailLoadingHtml('Refreshing…')
                : PROC_DETAIL_IDLE_HTML;

            if (!procSelId) {
                procCloseStaffWorkflowModal();
                procSetAdminWorkflowVisible(false);
            }
        }

        if (procSelId) {
            const overlayOpen = !!document.querySelector('.overlay.open');
            if (!overlayOpen) {
                await procSelect(procSelId, { force: true, silent: silent });
            }
        }
    };

    window.procBindProcurementUi = () => {
        document
            .getElementById('proc-btn-save-modal')
            ?.addEventListener('click', () => procSaveProcPrModal(false));
        document
            .getElementById('proc-btn-submit-modal')
            ?.addEventListener('click', () => procSaveProcPrModal(true));
        document.getElementById('proc-btn-delete-modal')?.addEventListener('click', () => {
            const id = parseInt(document.getElementById('proc-pr-edit-id')?.value || '0', 10);
            if (id) procOpenDeleteModal(id);
        });

        document.getElementById('proc-pr-class-yes')?.addEventListener('click', () => {
            procOpenProcPrModalCreateWithClass(true);
        });
        document.getElementById('proc-pr-class-no')?.addEventListener('click', () => {
            procOpenProcPrModalCreateWithClass(false);
        });

        document
            .getElementById('proc-btn-save-quote')
            ?.addEventListener('click', () =>
                procSaveQuoteFromModal(window.__procDetail?.lines),
            );

        document.getElementById('proc-s-pr')?.addEventListener(
            'input',
            rtProcList,
        );

        procStaffSeedIdleDetail();

        if (!document.body.dataset.procDeleteBound) {
            document.body.dataset.procDeleteBound = '1';
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('#proc-btn-del-draft');
                if (!btn) return;
                e.preventDefault();
                const fromBtn = parseInt(btn.getAttribute('data-pr-id') || '0', 10);
                const fromDetail = parseInt(window.__procDetail?.purchase_request?.id || '0', 10);
                procOpenDeleteModal(fromBtn || fromDetail);
            });
        }

        document.getElementById('proc-workflow-close')?.addEventListener('click', () => {
            procClearSelection();
        });

        const wfOv = procStaffWorkflowOverlay();
        if (wfOv && wfOv.dataset.workflowBound !== '1') {
            wfOv.dataset.workflowBound = '1';
            document
                .getElementById('proc-workflow-modal-close')
                ?.addEventListener('click', () => procClearSelection());
            wfOv.addEventListener('click', (e) => {
                if (e.target === wfOv) {
                    procClearSelection();
                }
            });
        }
    };

    function procStaffSeedIdleDetail() {
        const pane = document.getElementById('proc-detail');
        if (!pane || !procStaffWorkflowOverlay()) return;
        if (!pane.innerHTML.trim()) {
            pane.innerHTML = PROC_DETAIL_IDLE_HTML;
        }
    }

    function procStaffEscapeWorkflow(ev) {
        if (ev.key !== 'Escape') return;
        const wfOv = procStaffWorkflowOverlay();
        if (!wfOv?.classList.contains('staff-workflow-overlay--open')) return;
        if (document.querySelector('.overlay.open')) return;
        procClearSelection();
    }

    function rtProcList() {
        const q = document.getElementById('proc-s-pr')?.value?.toLowerCase?.() ?? '';
        const status = document.getElementById('f-proc-status')?.value || '';
        const kind = document.getElementById('f-proc-kind')?.value || '';
        const rows = document.querySelectorAll('#proc-pr-tbody tr[data-id]');
        if (!rows.length) return;
        const staffLike = document.body.classList.contains('staff-portal')
            || !!(typeof userPermissions !== 'undefined' && userPermissions.departmentScoped);
        rows.forEach((tr) => {
            const textOk = !q || tr.innerText.toLowerCase().includes(q);
            const rowSt = (tr.getAttribute('data-status') || '').toLowerCase();
            const fix = tr.getAttribute('data-needs-fix') === '1';
            const hasPo = tr.getAttribute('data-has-po') === '1';
            const consumable = tr.getAttribute('data-consumable') === '1';
            let statusOk = true;
            if (status) {
                if (staffLike) {
                    if (status === 'draft') statusOk = rowSt === 'draft' && !fix;
                    else if (status === 'needs_fix' || status === 'needs_revision') {
                        statusOk = fix || rowSt === 'needs_revision' || rowSt === 'needs_fix';
                    }
                    else if (status === 'submitted') statusOk = rowSt === 'submitted';
                    else if (status === 'quoting') statusOk = rowSt === 'quoting';
                    else if (status === 'po') statusOk = rowSt === 'po_issued' || rowSt === 'completed' || hasPo;
                    else statusOk = rowSt === status;
                } else {
                    statusOk = rowSt === status
                        || ((status === 'needs_revision' || status === 'needs_fix')
                            && (rowSt === 'needs_revision' || rowSt === 'needs_fix' || fix));
                }
            }
            const kindOk = !kind
                || (kind === 'consumable' && consumable)
                || (kind === 'non_consumable' && !consumable);
            tr.style.display = textOk && statusOk && kindOk ? '' : 'none';
        });
    }

    window.procSelect = procSelect;
    window.rtProcList = rtProcList;
    window.procConfirmReject = procConfirmReject;
    window.procConfirmSubmitCanvass = procConfirmSubmitCanvass;
    window.procOpenDeleteModal = procOpenDeleteModal;
    window.procConfirmDeleteDraft = procConfirmDeleteDraft;

    function bindProcurementWhenReady() {
        window.procBindProcurementUi?.();
        if (!procStaffEscapeBound) {
            procStaffEscapeBound = true;
            document.addEventListener('keydown', procStaffEscapeWorkflow);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindProcurementWhenReady);
    } else {
        bindProcurementWhenReady();
    }
})();
