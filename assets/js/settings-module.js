(function () {
    let cache = null;
    let section = 'profile';
    let loading = false;

    function esc(s) {
        if (typeof escapeHtml === 'function') return escapeHtml(s);
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function rootEl() {
        return document.getElementById('settings-root');
    }

    function peso(n) {
        const x = Number(n);
        return '₱' + (Number.isFinite(x) ? x.toLocaleString('en-PH', { maximumFractionDigits: 0 }) : '0');
    }

    function isAdminView(root) {
        return (root && root.getAttribute('data-settings-role') === 'admin') || !!(cache && cache.is_admin);
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type || 'success');
    }

    function applySideEffects() {
        if (!cache) return;
        const me = cache.me || {};
        const name = me.full_name || '';
        const email = me.email || '';
        const ud = document.getElementById('userData');
        if (ud) {
            if (name) ud.setAttribute('data-user-name', name);
            if (cache.ppe_threshold) ud.setAttribute('data-ppe-threshold', String(parseInt(cache.ppe_threshold, 10) || 50000));
            const prefs = cache.prefs || {};
            if (Object.prototype.hasOwnProperty.call(prefs, 'pr_printed_name')) {
                ud.setAttribute('data-pr-printed-name', String(prefs.pr_printed_name || ''));
            }
            if (Object.prototype.hasOwnProperty.call(prefs, 'pr_designation')) {
                ud.setAttribute('data-pr-designation', String(prefs.pr_designation || ''));
            }
        }
        document.querySelectorAll('.user-name, .user-menu-name, .staff-shell-user-name').forEach((el) => {
            if (name) el.textContent = name;
        });
        document.querySelectorAll('.user-menu-email').forEach((el) => {
            if (email) el.textContent = email;
        });
        document.querySelectorAll('.staff-shell-avatar').forEach((el) => {
            if (name) el.textContent = name.charAt(0).toUpperCase();
        });
    }

    function navItems(admin) {
        const both = [
            { id: 'profile', label: 'Profile', hint: 'Name, username, email' },
            { id: 'security', label: 'Password & security', hint: 'Password and session timeout' },
            { id: 'notifications', label: 'Notifications', hint: 'In-app and email alerts' },
        ];
        if (admin) {
            return both.concat([
                { id: 'letterhead', label: 'LGU & print', hint: 'Letterhead and signatories' },
                { id: 'inventory', label: 'Inventory rules', hint: 'PPE threshold' },
                { id: 'users', label: 'Users & departments', hint: 'Create accounts and offices' },
                { id: 'system', label: 'System', hint: 'Mail, forecast, import' },
            ]);
        }
        return both.concat([
            { id: 'department', label: 'My department', hint: 'Assigned office (read-only)' },
            { id: 'procurement', label: 'Procurement defaults', hint: 'Printed name on PR forms' },
            { id: 'activity', label: 'Activity log', hint: 'This office only, read-only' },
        ]);
    }

    function field(id, label, value, type, extra) {
        const t = type || 'text';
        const more = extra || '';
        return `<div class="fg"><label for="${id}">${esc(label)}</label><input id="${id}" type="${t}" value="${esc(value || '')}" ${more}></div>`;
    }

    function renderProfile(me) {
        return `
            <div class="set-card">
                <h3>Profile</h3>
                <p class="set-lead">This name appears in the header and on records you create.</p>
                <div class="form-grid">
                    ${field('set-full-name', 'Full name', me.full_name || '')}
                    ${field('set-username', 'Username', me.username || '')}
                    ${field('set-email', 'Email', me.email || '', 'email')}
                    <div class="fg"><label>Role</label><input type="text" value="${esc(me.role || '')}" disabled></div>
                </div>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="save-profile">Save profile</button>
                </div>
            </div>`;
    }

    function renderSecurity(prefs) {
        return `
            <div class="set-card">
                <h3>Change password</h3>
                <p class="set-lead">Use at least 8 characters with uppercase, lowercase, a number, and a special character.</p>
                <div class="form-grid">
                    ${field('set-pw-current', 'Current password', '', 'password', 'autocomplete="current-password"')}
                    ${field('set-pw-new', 'New password', '', 'password', 'autocomplete="new-password"')}
                    ${field('set-pw-confirm', 'Retype new password', '', 'password', 'autocomplete="new-password"')}
                </div>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="save-password">Update password</button>
                </div>
            </div>
            <div class="set-card">
                <h3>Session timeout</h3>
                <p class="set-lead">You will be signed out after this many minutes of inactivity (15–240).</p>
                <div class="form-grid">
                    ${field('set-session-mins', 'Minutes', prefs.session_minutes || 60, 'number', 'min="15" max="240"')}
                </div>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="save-session">Save timeout</button>
                </div>
            </div>`;
    }

    function renderNotifications(prefs) {
        const rows = [
            ['set-notify-inapp', 'notify_inapp', 'In-app notifications', 'Show alerts in the bell panel'],
            ['set-notify-email', 'notify_email', 'Email notifications', 'Send notices to your account email when mail is configured'],
            ['set-notify-pr', 'notify_pr', 'Procurement updates', 'Purchase request status and approvals'],
            ['set-notify-uns', 'notify_unserviceable', 'Unserviceable items', 'Reports of damaged or unusable property'],
            ['set-notify-stock', 'notify_stock', 'Stock and reorder', 'Low-stock and forecast watch items'],
        ];
        return `
            <div class="set-card">
                <h3>Notifications</h3>
                <p class="set-lead">Choose what this account receives. Turning off in-app alerts hides the notification list.</p>
                <div class="set-checks">
                    ${rows.map(([id, key, title, hint]) => `
                        <label class="set-check">
                            <input type="checkbox" id="${id}" ${prefs[key] ? 'checked' : ''}>
                            <span><strong>${esc(title)}</strong><em>${esc(hint)}</em></span>
                        </label>`).join('')}
                </div>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="save-notify">Save notifications</button>
                </div>
            </div>`;
    }

    function renderDepartment(me) {
        return `
            <div class="set-card">
                <h3>My department</h3>
                <p class="set-lead">Your office assignment is set by an administrator. You cannot change it here.</p>
                <div class="form-grid">
                    <div class="fg"><label>Department</label><input type="text" value="${esc(me.office_name || 'Not assigned')}" disabled></div>
                    <div class="fg"><label>Office code</label><input type="text" value="${esc(me.office_code || '—')}" disabled></div>
                </div>
            </div>`;
    }

    function renderActivity() {
        return `
            <div class="set-card staff-set-activity">
                <div class="staff-set-activity-head">
                    <div>
                        <h3>Activity log</h3>
                        <p class="set-lead">Read-only record of this office’s work — sign-in, inventory, issuance, returns, unserviceable, procurement, and account changes. Other departments are not shown.</p>
                    </div>
                    <div class="staff-set-activity-tools">
                        <label class="staff-pr-search-wrap">
                            <span class="sr-only">Search activity log</span>
                            <i class="fas fa-magnifying-glass staff-pr-search-ico" aria-hidden="true"></i>
                            <input class="search-inp staff-search staff-pr-search" type="search" id="activity-search" placeholder="Search who, action, record…" oninput="filterActivityLog()" autocomplete="off" />
                        </label>
                        <button type="button" class="btn btn-dark btn-sm" data-set-act="refresh-activity">Refresh</button>
                    </div>
                </div>
                <div id="tbl-activity"><p class="muted">Loading…</p></div>
            </div>`;
    }

    function renderProcurement(prefs) {
        const ud = document.getElementById('userData');
        const loginName = ud?.getAttribute('data-user-name') || '';
        const officeName = ud?.getAttribute('data-user-office-name') || '';
        return `
            <div class="set-card">
                <h3>Procurement defaults</h3>
                <p class="set-lead">New purchase requests start with your login name and department. You can change them here or on the PR form.</p>
                <div class="form-grid">
                    ${field('set-pr-name', 'Printed name', prefs.pr_printed_name || loginName)}
                    ${field('set-pr-desig', 'Designation', prefs.pr_designation || officeName)}
                </div>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="save-pr">Save defaults</button>
                </div>
            </div>`;
    }

    function renderLetterhead(sys) {
        return `
            <div class="set-card">
                <h3>LGU &amp; print letterhead</h3>
                <p class="set-lead">Used on RPCPPE, RPCSE, and procurement printouts when a value is set.</p>
                <div class="form-grid">
                    ${field('set-lgu-name', 'LGU name', sys.lgu_name || '', 'text', 'class="full-span"')}
                    ${field('set-print-underline', 'Print underline / locality', sys.print_underline_name || '')}
                    ${field('set-gso-officer', 'GSO / accountable officer', sys.gso_officer || '')}
                    ${field('set-gso-desig', 'GSO designation', sys.gso_designation || '')}
                    ${field('set-mayor-name', 'Approver / mayor', sys.mayor_name || '')}
                    ${field('set-mayor-desig', 'Approver designation', sys.mayor_designation || '')}
                </div>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="save-letterhead">Save letterhead</button>
                </div>
            </div>`;
    }

    function renderInventory(sys, threshold) {
        const th = sys.ppe_threshold || threshold || 50000;
        return `
            <div class="set-card">
                <h3>Inventory rules</h3>
                <p class="set-lead">Items at or above this unit value are classified as PPE. Lower amounts are Semi-Expendable.</p>
                <div class="form-grid">
                    ${field('set-ppe-threshold', 'PPE threshold (₱)', (typeof window.lgueMoneyFromNumber === 'function' ? window.lgueMoneyFromNumber(th) : th), 'text', 'inputmode="decimal" class="lgue-money" autocomplete="off"')}
                </div>
                <p class="set-note">Current rule: ${peso(th)} and above → PPE. This applies to new encoding and imports on the next save.</p>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="save-inventory">Save threshold</button>
                </div>
            </div>`;
    }

    function officeOptions(offices, selected, includeBlank) {
        const cur = selected == null || selected === '' ? '' : String(selected);
        const blank = includeBlank ? `<option value="">— No department —</option>` : `<option value="">Select department</option>`;
        return blank + (offices || []).map((o) =>
            `<option value="${esc(o.id)}"${String(o.id) === cur ? ' selected' : ''}>${esc(o.name)} (${esc(o.code || '—')})</option>`
        ).join('');
    }

    function renderUsers(data) {
        const offices = data.offices || [];
        const max = data.max_accounts_per_office || 3;

        const deptRows = offices.map((o) => {
            const n = parseInt(o.user_count, 10) || 0;
            const adm = parseInt(o.admin_count, 10) || 0;
            const full = n >= max;
            const adminBit = adm > 0 ? ` · ${adm} Admin` : '';
            return `<tr>
                <td><strong>${esc(o.name)}</strong></td>
                <td>${esc(o.code || '—')}</td>
                <td>${n} / ${max}${adminBit}${full ? ' <span class="set-pill set-pill-warn">Full</span>' : ''}</td>
            </tr>`;
        }).join('');

        return `
            <div class="set-card">
                <h3>Create account</h3>
                <p class="set-lead">Department User and Viewer accounts are limited to ${max} active accounts per office. The Administrator login is counted on GSO and does not use one of those ${max} slots.</p>
                <div class="form-grid">
                    ${field('set-nu-name', 'Full name', '')}
                    ${field('set-nu-user', 'Username', '')}
                    ${field('set-nu-email', 'Email', '', 'email')}
                    ${field('set-nu-pass', 'Temporary password', '', 'password')}
                    <div class="fg"><label for="set-nu-role">Role</label>
                        <select id="set-nu-role">
                            <option value="User">User</option>
                            <option value="Viewer">Viewer</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="fg"><label for="set-nu-office">Department</label>
                        <select id="set-nu-office">${officeOptions(offices, '', true)}</select>
                    </div>
                </div>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="create-user">Create user</button>
                </div>
            </div>
            <div class="set-card">
                <h3>Departments</h3>
                <p class="set-lead">Office codes appear on property numbers and staff workspace themes.</p>
                <div class="form-grid">
                    ${field('set-off-name', 'New department name', '')}
                    ${field('set-off-code', 'Code', '', 'text', 'maxlength="12" placeholder="e.g. ENGR"')}
                </div>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="create-office">Add department</button>
                </div>
                <div class="set-table-wrap" style="margin-top:14px">
                    <table class="set-table">
                        <thead><tr><th>Department</th><th>Code</th><th>Active accounts</th></tr></thead>
                        <tbody>${deptRows || '<tr><td colspan="3" class="muted">No departments</td></tr>'}</tbody>
                    </table>
                </div>
            </div>`;
    }

    function renderSystem(data) {
        const sys = data.system || {};
        const driver = String(data.mail_driver || 'smtp');
        const ok = !!data.mail_configured;
        const mailNote = driver === 'log'
            ? 'MAIL_DRIVER is log. Messages are written to the server mail outbox for local testing. SMTP credentials are not entered in this screen.'
            : (ok
                ? 'SMTP is configured on the server. Passwords stay in the environment file, not in this form.'
                : 'SMTP is not fully configured. Ask ICT to set MAIL_HOST, MAIL_USERNAME, and MAIL_PASSWORD in the server environment.');
        return `
            <div class="set-card">
                <h3>Mail</h3>
                <p class="set-lead">Password-reset codes and optional email notices use this connection. Credentials are never shown here.</p>
                <div class="set-meta">
                    <div><span>Driver</span><strong>${esc(driver)}</strong></div>
                    <div><span>Status</span><strong class="${ok ? 'set-ok' : 'set-warn'}">${ok ? 'Ready' : 'Not configured'}</strong></div>
                </div>
                <p class="set-note">${esc(mailNote)}</p>
            </div>
            <div class="set-card">
                <h3>Forecast mode</h3>
                <p class="set-lead">Live mode reads acquisition dates, property-number years, issuances, and open purchase orders. Demonstration regenerates synthetic demand for comparison only.</p>
                <label class="set-check">
                    <input type="radio" name="set-forecast" value="live" ${sys.forecast_mode !== 'demo' ? 'checked' : ''}>
                    <span><strong>Live</strong><em>Forecast from the latest dated inventory records. History stops on that date; the next 3 months are projections.</em></span>
                </label>
                <label class="set-check">
                    <input type="radio" name="set-forecast" value="demo" ${sys.forecast_mode === 'demo' ? 'checked' : ''}>
                    <span><strong>Demonstration</strong><em>Generated 2023–2026 demand for comparison. Does not replace live inventory dates.</em></span>
                </label>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="save-forecast">Save forecast mode</button>
                </div>
            </div>
            <div class="set-card">
                <h3>Imports</h3>
                <label class="set-check">
                    <input type="checkbox" id="set-allow-import" ${String(sys.allow_items_import) !== '0' ? 'checked' : ''}>
                    <span><strong>Allow Excel item import</strong><em>When off, the items import API rejects uploads.</em></span>
                </label>
                <div class="set-actions">
                    <button type="button" class="btn btn-dark" data-set-act="save-import">Save import rule</button>
                </div>
            </div>`;
    }

    function render() {
        const root = rootEl();
        if (!root || !cache) return;
        const admin = isAdminView(root);
        const me = cache.me || {};
        const prefs = cache.prefs || {};
        const sys = cache.system || {};
        const items = navItems(admin);
        if (!items.some((i) => i.id === section)) section = 'profile';

        let body = '';
        if (section === 'profile') body = renderProfile(me);
        else if (section === 'security') body = renderSecurity(prefs);
        else if (section === 'notifications') body = renderNotifications(prefs);
        else if (section === 'department') body = renderDepartment(me);
        else if (section === 'activity') body = renderActivity();
        else if (section === 'procurement') body = renderProcurement(prefs);
        else if (section === 'letterhead') body = renderLetterhead(sys);
        else if (section === 'inventory') body = renderInventory(sys, cache.ppe_threshold);
        else if (section === 'users') body = renderUsers(cache);
        else if (section === 'system') body = renderSystem(cache);

        const staff = document.body.classList.contains('staff-portal');
        if (staff) {
            const office = me.office_name || document.getElementById('userData')?.getAttribute('data-user-office-name') || 'Your department';
            const initial = (me.full_name || 'U').charAt(0).toUpperCase();
            root.innerHTML = `
                <div class="staff-set-shell">
                    <header class="staff-set-banner">
                        <div class="staff-set-avatar">${esc(initial)}</div>
                        <div class="staff-set-banner-copy">
                            <p class="staff-set-kicker">Account settings</p>
                            <h2>${esc(me.full_name || 'Department user')}</h2>
                            <p>${esc(office)} · ${esc(me.role || 'User')} · assigned office cannot be changed here</p>
                        </div>
                        <button type="button" class="staff-set-back" data-set-act="back-dashboard">
                            <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Dashboard
                        </button>
                    </header>
                    <nav class="staff-set-pills" aria-label="Settings sections">
                        ${items.map((i) => `
                            <button type="button" class="staff-set-pill${i.id === section ? ' active' : ''}" data-set-section="${i.id}">${esc(i.label)}</button>
                        `).join('')}
                    </nav>
                    <div class="staff-set-main">${body}</div>
                </div>`;
        } else {
            root.innerHTML = `
                <div class="settings-shell">
                    <nav class="set-nav" aria-label="Settings sections">
                        ${items.map((i) => `
                            <button type="button" class="set-nav-item${i.id === section ? ' active' : ''}" data-set-section="${i.id}">
                                <strong>${esc(i.label)}</strong>
                                <span>${esc(i.hint)}</span>
                            </button>`).join('')}
                    </nav>
                    <div class="set-main">${body}</div>
                </div>`;
        }
        bind();
        if (staff && section === 'activity' && typeof loadActivityLog === 'function') {
            loadActivityLog();
        }
    }

    function val(id) {
        return document.getElementById(id)?.value ?? '';
    }

    function checked(id) {
        return document.getElementById(id)?.checked ? 1 : 0;
    }

    async function post(action, extra, btn) {
        const work = async () => {
            const res = await apiPost('settings.php', Object.assign({ action }, extra || {}));
            if (res.success) {
                if (res.data) cache = res.data;
                toast(res.message || 'Saved', 'success');
                applySideEffects();
                render();
            } else {
                toast(res.message || 'Could not save', 'error');
            }
            return res;
        };
        if (typeof runBusy === 'function' && btn) {
            return runBusy(btn, work, 'Saving…');
        }
        return work();
    }

    function collectNotify() {
        return {
            notify_inapp: checked('set-notify-inapp'),
            notify_email: checked('set-notify-email'),
            notify_pr: checked('set-notify-pr'),
            notify_unserviceable: checked('set-notify-uns'),
            notify_stock: checked('set-notify-stock'),
        };
    }

    function bind() {
        const root = rootEl();
        if (!root) return;

        root.querySelectorAll('[data-set-section]').forEach((btn) => {
            btn.addEventListener('click', () => {
                section = btn.getAttribute('data-set-section') || 'profile';
                render();
            });
        });

        root.querySelectorAll('[data-set-act]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const act = btn.getAttribute('data-set-act');
                if (act === 'back-dashboard') {
                    if (typeof staffShowSection === 'function') staffShowSection('procurement');
                    return;
                }
                if (act === 'refresh-activity') {
                    if (typeof loadActivityLog === 'function') loadActivityLog();
                    return;
                }
                if (act === 'save-profile') {
                    const nameChk = typeof lgueRequireName === 'function'
                        ? lgueRequireName(val('set-full-name'), 'Full name')
                        : { ok: true, value: val('set-full-name').trim() };
                    if (!nameChk.ok) {
                        toast(nameChk.message, 'error');
                        return;
                    }
                    await post('profile', {
                        full_name: nameChk.value,
                        username: val('set-username').trim(),
                        email: val('set-email').trim(),
                    }, btn);
                    return;
                }
                if (act === 'save-password') {
                    await post('password', {
                        current_password: val('set-pw-current'),
                        new_password: val('set-pw-new'),
                        confirm_password: val('set-pw-confirm'),
                    }, btn);
                    return;
                }
                if (act === 'save-session') {
                    const minsChk = typeof lgueParseNumber === 'function'
                        ? lgueParseNumber(val('set-session-mins'), 'Session minutes', { integer: true, min: 15 })
                        : { ok: true, value: val('set-session-mins') };
                    if (!minsChk.ok) {
                        toast(minsChk.message, 'error');
                        return;
                    }
                    await post('prefs', { session_minutes: minsChk.value }, btn);
                    return;
                }
                if (act === 'save-notify') {
                    await post('prefs', collectNotify(), btn);
                    return;
                }
                if (act === 'save-pr') {
                    const prNameChk = typeof lgueRequireName === 'function'
                        ? lgueRequireName(val('set-pr-name'), 'Printed name')
                        : { ok: true, value: val('set-pr-name').trim() };
                    const prDesChk = typeof lgueOptionalText === 'function'
                        ? lgueOptionalText(val('set-pr-desig'), 'Designation')
                        : { ok: true, value: val('set-pr-desig').trim() };
                    if (!prNameChk.ok) {
                        toast(prNameChk.message, 'error');
                        return;
                    }
                    if (!prDesChk.ok) {
                        toast(prDesChk.message, 'error');
                        return;
                    }
                    await post('prefs', {
                        pr_printed_name: prNameChk.value,
                        pr_designation: prDesChk.value,
                    }, btn);
                    return;
                }
                if (act === 'save-letterhead') {
                    const lguChk = typeof lgueRequireText === 'function'
                        ? lgueRequireText(val('set-lgu-name'), 'LGU name', { minLen: 8, minWords: 2 })
                        : { ok: true, value: val('set-lgu-name').trim() };
                    const gsoChk = typeof lgueRequireName === 'function'
                        ? lgueRequireName(val('set-gso-officer'), 'GSO officer')
                        : { ok: true, value: val('set-gso-officer').trim() };
                    const mayorChk = typeof lgueRequireName === 'function'
                        ? lgueRequireName(val('set-mayor-name'), 'Mayor name')
                        : { ok: true, value: val('set-mayor-name').trim() };
                    const gsoDes = typeof lgueOptionalText === 'function'
                        ? lgueOptionalText(val('set-gso-desig'), 'GSO designation')
                        : { ok: true, value: val('set-gso-desig').trim() };
                    const mayorDes = typeof lgueOptionalText === 'function'
                        ? lgueOptionalText(val('set-mayor-desig'), 'Mayor designation')
                        : { ok: true, value: val('set-mayor-desig').trim() };
                    const under = typeof lgueOptionalText === 'function'
                        ? lgueOptionalText(val('set-print-underline'), 'Print underline name')
                        : { ok: true, value: val('set-print-underline').trim() };
                    const letterChk = [lguChk, gsoChk, mayorChk, gsoDes, mayorDes, under].find((c) => !c.ok);
                    if (letterChk) {
                        toast(letterChk.message, 'error');
                        return;
                    }
                    await post('system', {
                        lgu_name: lguChk.value,
                        print_underline_name: under.value,
                        gso_officer: gsoChk.value,
                        gso_designation: gsoDes.value,
                        mayor_name: mayorChk.value,
                        mayor_designation: mayorDes.value,
                    }, btn);
                    return;
                }
                if (act === 'save-inventory') {
                    const thChk = typeof lgueParseNumber === 'function'
                        ? lgueParseNumber(val('set-ppe-threshold'), 'PPE threshold', { min: 1 })
                        : { ok: true, value: val('set-ppe-threshold') };
                    if (!thChk.ok) {
                        toast(thChk.message, 'error');
                        return;
                    }
                    await post('system', { ppe_threshold: thChk.value }, btn);
                    return;
                }
                if (act === 'save-forecast') {
                    const picked = root.querySelector('input[name="set-forecast"]:checked');
                    await post('system', { forecast_mode: picked ? picked.value : 'live' }, btn);
                    return;
                }
                if (act === 'save-import') {
                    await post('system', { allow_items_import: checked('set-allow-import') }, btn);
                    return;
                }
                if (act === 'create-user') {
                    const nuName = typeof lgueRequireName === 'function'
                        ? lgueRequireName(val('set-nu-name'), 'Full name')
                        : { ok: true, value: val('set-nu-name').trim() };
                    if (!nuName.ok) {
                        toast(nuName.message, 'error');
                        return;
                    }
                    await post('user_create', {
                        full_name: nuName.value,
                        username: val('set-nu-user').trim(),
                        email: val('set-nu-email').trim(),
                        password: val('set-nu-pass'),
                        role: val('set-nu-role'),
                        office_id: val('set-nu-office'),
                    }, btn);
                    return;
                }
                if (act === 'create-office') {
                    const offName = typeof lgueRequireText === 'function'
                        ? lgueRequireText(val('set-off-name'), 'Department name', { minLen: 3 })
                        : { ok: true, value: val('set-off-name').trim() };
                    const offCode = val('set-off-code').trim().toUpperCase();
                    if (!offName.ok) {
                        toast(offName.message, 'error');
                        return;
                    }
                    if (typeof lgueLooksIllegible === 'function' && lgueLooksIllegible(offCode)) {
                        toast('Office code must be a real department code, not random letters.', 'error');
                        return;
                    }
                    if (!/^[A-Z][A-Z0-9\-]{1,15}$/.test(offCode)) {
                        toast('Office code must be letters and numbers only (example: GSO, MAYOR).', 'error');
                        return;
                    }
                    const res = typeof runBusy === 'function'
                        ? await runBusy(btn, () => apiPost('offices.php', {
                            name: offName.value,
                            code: offCode,
                        }), 'Saving…')
                        : await apiPost('offices.php', {
                        name: offName.value,
                        code: offCode,
                    });
                    if (res.success) {
                        toast(res.message || 'Department added', 'success');
                        await loadSettings(true);
                    } else {
                        toast(res.message || 'Could not add department', 'error');
                    }
                    return;
                }
            });
        });
    }

    async function loadSettings(force) {
        const root = rootEl();
        if (!root) return;
        if (loading) return;
        if (cache && !force) {
            render();
            return;
        }
        loading = true;
        root.innerHTML = '<div class="set-card"><p class="muted">Loading settings…</p></div>';
        try {
            const res = await apiGet('settings.php');
            if (!res.success) {
                root.innerHTML = `<div class="set-card"><p class="set-warn">${esc(res.message || 'Could not load settings.')}</p></div>`;
                return;
            }
            cache = res.data || {};
            applySideEffects();
            render();
        } catch (e) {
            root.innerHTML = '<div class="set-card"><p class="set-warn">Could not load settings.</p></div>';
        } finally {
            loading = false;
        }
    }

    window.loadSettings = loadSettings;
})();
