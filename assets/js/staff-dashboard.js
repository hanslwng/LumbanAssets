(function () {
    let invCache = null;
    let invLoading = false;
    let prMixChart = null;

    const TILE_ICONS = {
        draft: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>',
        submitted: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
        needs_fix: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
        needs_revision: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
        quoting: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        po: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/></svg>',
        inv: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/></svg>',
    };

    const TILE_META = {
        draft: { cls: 'staff-dash-tile--blue', color: '#1849B4', label: 'Drafts' },
        submitted: { cls: 'staff-dash-tile--amber', color: '#9E5505', label: 'Waiting' },
        needs_fix: { cls: 'staff-dash-tile--red', color: '#BE1D1D', label: 'Need revision' },
        needs_revision: { cls: 'staff-dash-tile--red', color: '#BE1D1D', label: 'Need revision' },
        quoting: { cls: 'staff-dash-tile--teal', color: '#0A7A5A', label: 'Quoting' },
        po: { cls: 'staff-dash-tile--green', color: '#16733A', label: 'With PO' },
        inv: { cls: 'staff-dash-tile--gold', color: '#B8860B', label: 'On hand', jump: 'inv' },
    };

    function esc(s) {
        if (typeof escapeHtml === 'function') return escapeHtml(s);
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function peso(n) {
        const x = Number(n);
        if (!Number.isFinite(x)) return '₱0';
        return '₱' + x.toLocaleString('en-PH', { maximumFractionDigits: 0 });
    }

    function st(row) {
        return String(row?.status || '').toLowerCase();
    }

    function needsFix(row) {
        const s = st(row);
        return s === 'needs_revision' || s === 'needs_fix' || (s === 'draft' && String(row.canvass_feedback || '').trim() !== '');
    }

    function summarize(rows) {
        const out = {
            total: rows.length,
            draft: 0,
            submitted: 0,
            needs_fix: 0,
            quoting: 0,
            canvass_open: 0,
            po: 0,
            completed: 0,
            estimate: 0,
        };
        rows.forEach((r) => {
            const s = st(r);
            out.estimate += Number(r.estimate_total) || 0;
            if (needsFix(r)) out.needs_fix += 1;
            else if (s === 'draft') out.draft += 1;
            if (s === 'submitted') out.submitted += 1;
            if (s === 'quoting') {
                out.quoting += 1;
                if ((parseInt(r.quotation_count, 10) || 0) < 3) out.canvass_open += 1;
            }
            if (s === 'po_issued' || s === 'completed' || r.has_po == 1 || r.has_po === true) out.po += 1;
            if (s === 'completed') out.completed += 1;
        });
        return out;
    }

    function tile(filter, label, n, hint, extraClass) {
        const meta = TILE_META[filter] || {};
        const ico = TILE_ICONS[filter] || '';
        const dataAttr = meta.jump === 'inv' ? 'data-inv-stat="1"' : `data-pr-stat="${filter}"`;
        return `<div class="staff-dash-tile ${meta.cls || ''} ${extraClass || ''}" ${dataAttr} role="group" aria-label="${esc(label)}">
            <span class="staff-dash-tile-top">
                <span class="staff-dash-tile-ico" aria-hidden="true">${ico}</span>
                <em>${esc(label)}</em>
            </span>
            <strong>${esc(String(n))}</strong>
            <span class="staff-dash-tile-hint">${esc(hint)}</span>
        </div>`;
    }

    function pipelineMix(sum) {
        return [
            { key: 'draft', label: 'Drafts', value: Number(sum.draft) || 0, color: TILE_META.draft.color },
            { key: 'submitted', label: 'Waiting', value: Number(sum.submitted) || 0, color: TILE_META.submitted.color },
            { key: 'needs_revision', label: 'Need revision', value: Number(sum.needs_fix) || 0, color: TILE_META.needs_revision.color },
            { key: 'quoting', label: 'Quoting', value: Number(sum.quoting) || 0, color: TILE_META.quoting.color },
            { key: 'po', label: 'With PO', value: Number(sum.po) || 0, color: TILE_META.po.color },
        ];
    }

    function renderPrMixChart(sum) {
        const canvas = document.getElementById('staff-pr-mix-chart');
        const empty = document.getElementById('staff-pr-mix-empty');
        const center = document.getElementById('staff-pr-mix-center');
        const totalEl = document.getElementById('staff-pr-mix-total');
        const legend = document.getElementById('staff-pr-mix-legend');
        const mix = pipelineMix(sum);
        const total = mix.reduce((s, r) => s + r.value, 0);
        if (legend) {
            legend.innerHTML = `<div class="staff-dash-legend">${mix.map((r) => {
                const pct = total ? Math.round(r.value / total * 100) : 0;
                return `<div class="staff-dash-legend-row" style="--sw:${esc(r.color)};--pct:${pct}%">
                    <span class="staff-dash-legend-swatch"></span>
                    <span>
                        <span class="staff-dash-legend-lbl">${esc(r.label)}</span>
                        <span class="staff-dash-legend-meter" aria-hidden="true"><i></i></span>
                    </span>
                    <span class="staff-dash-legend-nums">
                        <strong class="staff-dash-legend-val">${esc(String(r.value))}</strong>
                        <span class="staff-dash-legend-pct">${esc(String(pct))}%</span>
                    </span>
                </div>`;
            }).join('')}</div>`;
        }
        if (prMixChart && typeof prMixChart.destroy === 'function') {
            try { prMixChart.destroy(); } catch (e) { /* ignore */ }
            prMixChart = null;
        }
        if (!canvas || typeof Chart === 'undefined') {
            if (center) center.hidden = false;
            if (totalEl) totalEl.textContent = String(total);
            if (empty) empty.hidden = total > 0;
            return;
        }
        if (totalEl) totalEl.textContent = String(total);
        if (empty) empty.hidden = true;
        if (center) center.hidden = false;
        canvas.style.display = '';
        const emptyRing = total === 0;
        const drawn = emptyRing ? [{ label: 'None', value: 1, color: '#E2E8F0' }] : mix.filter((r) => r.value > 0);
        prMixChart = new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: drawn.map((r) => r.label),
                datasets: [{
                    data: drawn.map((r) => r.value),
                    backgroundColor: drawn.map((r) => r.color),
                    borderWidth: emptyRing ? 0 : 5,
                    borderColor: '#fff',
                    hoverOffset: emptyRing ? 0 : 4,
                    spacing: emptyRing ? 0 : 4,
                    borderRadius: emptyRing ? 0 : 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: emptyRing ? { enabled: false } : {
                        callbacks: {
                            label: (ctx) => {
                                const v = Number(ctx.raw) || 0;
                                const p = total ? Math.round(v / total * 100) : 0;
                                return ` ${ctx.label}: ${v} (${p}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    function nextHtml(sum, inv) {
        const steps = [
            {
                n: sum.needs_fix,
                title: 'Revise returned requests',
                hint: 'Admin sent the canvass back. Update it and resubmit.',
                attr: 'data-pr-filter="needs_revision"',
                warn: true,
            },
            {
                n: sum.draft,
                title: 'Submit drafts',
                hint: 'Finish the form, then send it for GSO canvass approval.',
                attr: 'data-pr-filter="draft"',
            },
            {
                n: sum.canvass_open || sum.quoting,
                title: 'Complete 3 quotations',
                hint: 'Approved PRs still need three supplier prices.',
                attr: 'data-pr-filter="quoting"',
            },
            {
                n: inv ? inv.uns : 0,
                title: 'Review unserviceable items',
                hint: 'Damaged office property that may need a report.',
                attr: 'data-inv-status="Unserviceable"',
                warn: !!(inv && inv.uns),
            },
        ];
        const active = steps.filter((s) => s.n > 0);
        if (!active.length) {
            return '<p class="muted">Nothing is blocking this office. Start a new PR or open inventory when you need it.</p>';
        }
        return `<ul class="staff-dash-next-list">${active.map((s) => `
            <li>
                <button type="button" class="${s.warn ? 'is-warn' : ''}" ${s.attr}>
                    <b>${esc(String(s.n))}</b>
                    <span><strong>${esc(s.title)}</strong><em>${esc(s.hint)}</em></span>
                </button>
            </li>`).join('')}</ul>`;
    }

    function attnHtml(rows) {
        const items = [];
        rows.forEach((r) => {
            if (needsFix(r)) {
                items.push({
                    id: r.id,
                    title: r.pr_number,
                    note: 'Returned — ' + String(r.canvass_feedback || 'address admin feedback').slice(0, 90),
                    kind: 'alert',
                });
            } else if (st(r) === 'submitted') {
                items.push({
                    id: r.id,
                    title: r.pr_number,
                    note: 'Waiting for admin canvass approval',
                    kind: 'wait',
                });
            }
        });
        const top = items.slice(0, 5);
        if (!top.length) {
            return '<p class="muted">Nothing waiting on you right now. Drafts can stay here until you submit.</p>';
        }
        return `<ul class="staff-dash-attn-list">${top.map((it) => `
            <li>
                <button type="button" data-pr-open="${it.id}">
                    <strong>${esc(it.title)}</strong>
                    <span class="staff-dash-attn-${it.kind}">${esc(it.note)}</span>
                </button>
            </li>`).join('')}</ul>`;
    }

    function bind(root) {
        root.querySelectorAll('.staff-dash-next [data-pr-filter], .staff-dash-attn [data-pr-filter]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (typeof staffShowSection === 'function') staffShowSection('procurement');
                const key = btn.getAttribute('data-pr-filter');
                const statusSel = document.getElementById('f-proc-status');
                if (statusSel && key) statusSel.value = key === 'needs_fix' ? 'needs_revision' : key;
                const search = document.getElementById('proc-s-pr');
                if (search) search.value = '';
                if (typeof rtProcList === 'function') rtProcList();
                const shell = document.querySelector('.staff-pr-table-shell');
                if (shell) shell.scrollTop = 0;
            });
        });
        root.querySelectorAll('[data-inv-jump], [data-inv-status]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const status = btn.getAttribute('data-inv-status') || '';
                const sel = document.getElementById('inv-dept-status');
                if (sel && status) sel.value = status;
                if (typeof staffShowSection === 'function') staffShowSection('inventory');
            });
        });
        root.querySelectorAll('[data-pr-open]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (typeof staffShowSection === 'function') staffShowSection('procurement');
                const id = parseInt(btn.getAttribute('data-pr-open'), 10);
                if (id && typeof window.procSelect === 'function') {
                    window.procSelect(id);
                }
            });
        });
    }

    async function loadInventoryStats() {
        if (invCache || invLoading || typeof apiGet !== 'function') return invCache;
        invLoading = true;
        try {
            const res = typeof apiGetItems === 'function'
                ? await apiGetItems('lite=1')
                : await apiGet('items.php?lite=1');
            const items = res.success ? (res.data || []) : [];
            invCache = {
                total: items.length,
                se: items.filter((i) => String(i.category || '').toUpperCase() === 'SE').length,
                ppe: items.filter((i) => String(i.category || '').toUpperCase() === 'PPE').length,
                uns: items.filter((i) => String(i.status || '') === 'Unserviceable').length,
                issued: items.filter((i) => String(i.status || '') === 'Issued').length,
            };
        } catch (e) {
            invCache = { total: 0, se: 0, ppe: 0, uns: 0 };
        } finally {
            invLoading = false;
        }
        return invCache;
    }

    async function refreshStaffDashboard(rows) {
        const root = document.getElementById('staff-dash');
        if (!root) return;
        try {
        const list = Array.isArray(rows) ? rows : (window.__staffPrs || []);
        const sum = summarize(list);
        const inv = await loadInventoryStats();
        const est = document.getElementById('staff-dash-est');
        if (est) est.innerHTML = `<span>Estimated pipeline</span><strong>${peso(sum.estimate)}</strong>`;
        const tiles = document.getElementById('staff-dash-tiles');
        if (tiles) {
            tiles.innerHTML =
                tile('draft', 'Drafts', sum.draft, 'Not submitted yet') +
                tile('submitted', 'Waiting', sum.submitted, 'Admin canvass review') +
                tile('needs_revision', 'Need revision', sum.needs_fix, 'Returned with feedback', sum.needs_fix ? 'staff-dash-tile-alert' : '') +
                tile('quoting', 'Quoting', sum.quoting, 'Three canvass slots') +
                tile('po', 'With PO', sum.po, 'PO issued or completed') +
                tile('inv', 'On hand', inv ? inv.total : '—', inv ? (inv.se + ' SE · ' + inv.ppe + ' PPE') : 'Office inventory');
        }
        renderPrMixChart(sum);
        const next = document.getElementById('staff-dash-next');
        if (next) next.innerHTML = nextHtml(sum, inv);
        const attn = document.getElementById('staff-dash-attn');
        if (attn) attn.innerHTML = attnHtml(list);
        const count = document.getElementById('staff-pr-count');
        if (count) {
            count.textContent = sum.total
                ? `${sum.total} request${sum.total === 1 ? '' : 's'} · scroll inside the list to see all`
                : 'No purchase requests yet. Use New PR to start one.';
        }
        bind(root);
        } catch (e) {
            console.error('staff dashboard refresh failed', e);
        }
    }

    window.refreshStaffDashboard = refreshStaffDashboard;
})();
