// LGU Inventory System - Main JavaScript Application

// Global state
let currentPage = 'dashboard';
let __highlightLatestIssuance = false;
let offices = [];
let allItems = [];
let seItemsByOffice = {};
let ppeItemsByOffice = {};
let ppeItemsByTab = {};
/** Admin Semi/PPE drill-down: show one office/tab inventory instead of nested accordions. */
let seDrillOfficeId = null;
let ppeDrillTabSlug = null;
let currentDetailItemId = null;
let currentDetailItem = null;
let selectedReturnAction = null;

// User permissions
let userPermissions = {
    canCreate: false,
    canEdit: false,
    canDelete: false,
    canPrint: false,
    isViewer: false,
    isAdmin: false,
    officeId: 0,
    departmentScoped: false
};

function getPpeThreshold() {
    const el = document.getElementById('userData');
    const n = parseFloat(el?.dataset?.ppeThreshold || '50000');
    return Number.isFinite(n) && n > 0 ? n : 50000;
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s === null || s === undefined ? '' : String(s);
    return d.innerHTML;
}

/** Theme meta lgue-web-base — empty string when app runs at domain root */
function lgueWebBase() {
    const m = document.querySelector('meta[name="lgue-web-base"]');
    let b = m ? String(m.getAttribute('content') || '').trim() : '';
    b = b.replace(/\/$/, '');
    if (b === '/') b = '';

    return b;
}

/** Root-absolute route from app mount (e.g. /logout) */
function lgueAppUrl(path) {
    const p = path.startsWith('/') ? path : `/${path}`;
    const b = lgueWebBase();

    return b ? `${b}${p}` : p;
}

// Load user permissions on page load
document.addEventListener('DOMContentLoaded', async function() {
    const userData = document.getElementById('userData');
    if (userData) {
        userPermissions.canCreate = userData.dataset.canCreate === '1';
        userPermissions.canEdit = userData.dataset.canEdit === '1';
        userPermissions.canDelete = userData.dataset.canDelete === '1';
        userPermissions.canPrint = userData.dataset.canPrint === '1';
        userPermissions.isViewer = userData.dataset.isViewer === '1';
        userPermissions.isAdmin = userData.dataset.userIsAdmin === '1';
        userPermissions.officeId = parseInt(userData.dataset.userOfficeId || '0', 10) || 0;
        userPermissions.departmentScoped = !userPermissions.isAdmin && (userData.dataset.userDeptScoped === '1' || userPermissions.officeId > 0);
    }

    const staffPortal = document.body.classList.contains('staff-portal');
    if (staffPortal) {
        await loadOffices();
        if (typeof window.loadProcurement === 'function') {
            await window.loadProcurement();
        }
        if (document.getElementById('notif-panel')) {
            loadNotifications();
        }
        startLiveSync();
        return;
    }

    const app = document.querySelector('.app');
    if (app) app.classList.remove('collapsed');
    try { localStorage.removeItem('sidebarCollapsed'); } catch (e) {}

    loadOffices();
    loadDashboard();
    setTodayDate();
    if (document.getElementById('notif-panel')) {
        loadNotifications();
    }
    startLiveSync();
});

// Check if user has permission
function checkPermission(action) {
    if (action === 'create' && !userPermissions.canCreate) {
        showToast('You do not have permission to create items (Read-Only Mode)', 'error');
        return false;
    }
    if (action === 'edit' && !userPermissions.canEdit) {
        showToast('You do not have permission to edit items (Read-Only Mode)', 'error');
        return false;
    }
    if (action === 'delete' && !userPermissions.canDelete) {
        showToast('You do not have permission to delete items (Read-Only Mode)', 'error');
        return false;
    }
    return true;
}

// ─────────────────────────────────────────────────────────
// User Menu Toggle Function
// ─────────────────────────────────────────────────────────
function toggleUserMenu(event) {
    event.stopPropagation();
    const userMenu = document.getElementById('userMenu');
    const userProfile = event.currentTarget;
    
    if (!userMenu) {
        console.error('User menu element not found');
        return;
    }
    
    // Toggle menu visibility
    const isShowing = userMenu.classList.contains('show');
    
    if (isShowing) {
        userMenu.classList.remove('show');
        userProfile.classList.remove('active');
        console.log('User menu closed');
    } else {
        userMenu.classList.add('show');
        userProfile.classList.add('active');
        console.log('User menu opened');
    }
}

// Close user menu when clicking outside
document.addEventListener('click', function(event) {
    const userMenu = document.getElementById('userMenu');
    const userProfileWrapper = document.querySelector('.user-profile-wrapper');
    
    if (userMenu && userProfileWrapper) {
        // Check if click is outside the wrapper (which contains both profile and menu)
        if (!userProfileWrapper.contains(event.target)) {
            userMenu.classList.remove('show');
            const userProfile = userProfileWrapper.querySelector('.user-profile');
            if (userProfile) {
                userProfile.classList.remove('active');
            }
            console.log('User menu closed (clicked outside)');
        }
    }
});

// ─────────────────────────────────────────────────────────
// Notifications (dashboard bell — Admin/Property Custodian)
// ─────────────────────────────────────────────────────────
function toggleNotifPanel(event) {
    event.stopPropagation();
    const panel = document.getElementById('notif-panel');
    if (!panel) return;
    const isShowing = panel.classList.contains('show');
    if (isShowing) {
        panel.classList.remove('show');
    } else {
        panel.classList.add('show');
        loadNotifications();
    }
}

document.addEventListener('click', function(event) {
    const panel = document.getElementById('notif-panel');
    const wrapper = document.querySelector('.notif-bell-wrapper');
    if (panel && wrapper && !wrapper.contains(event.target)) {
        panel.classList.remove('show');
    }
});

function notificationsApiUrl() {
    return typeof lgueAppUrl === 'function' ? lgueAppUrl('/api/notifications.php') : 'api/notifications.php';
}

function formatNotifTime(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(String(dateStr).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return formatDate(dateStr);
    const diff = Date.now() - date.getTime();
    if (diff < 45000) return 'Just now';
    if (diff < 3600000) return Math.max(1, Math.round(diff / 60000)) + 'm ago';
    if (diff < 86400000) return Math.max(1, Math.round(diff / 3600000)) + 'h ago';
    return date.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

let __notifLoading = false;
let __notifReady = false;
let __notifLatestId = 0;
let __livePollTimer = null;
let __procStamp = '';
let __liveReady = false;
let __procReloadBusy = false;
let __procReloadQueued = false;
let __liveBound = false;

function notificationsHeadUrl() {
    return (typeof lgueAppUrl === 'function' ? lgueAppUrl('/api/notifications.php') : 'api/notifications.php') + '?head=1';
}

function requestProcurementSync() {
    if (typeof window.loadProcurement !== 'function') return;
    if (__procReloadBusy) {
        __procReloadQueued = true;
        return;
    }
    __procReloadBusy = true;
    Promise.resolve(window.loadProcurement({ silent: true })).finally(() => {
        __procReloadBusy = false;
        if (__procReloadQueued) {
            __procReloadQueued = false;
            requestProcurementSync();
        }
    });
}

function applyLivePulse(payload) {
    if (!payload || payload.success === false) return;
    const latestId = Number(payload.latest_id || 0);
    const procStamp = String(payload.proc_stamp || '');
    const notifChanged = latestId > 0 && latestId !== __notifLatestId;
    const procChanged = procStamp !== '' && procStamp !== __procStamp;
    if (procStamp !== '') {
        __procStamp = procStamp;
    }
    if (__liveReady && procChanged) {
        requestProcurementSync();
    }
    if (__liveReady && notifChanged) {
        loadNotifications({ silent: true });
    }
    __liveReady = true;
}

async function pollLiveHead() {
    if (document.visibilityState === 'hidden') return;
    try {
        const res = await fetch(notificationsHeadUrl(), { credentials: 'same-origin', cache: 'no-store' });
        const payload = await res.json();
        applyLivePulse(payload);
    } catch (e) {
        // next tick
    }
}

function startLiveSync() {
    if (__livePollTimer) return;
    pollLiveHead();
    __livePollTimer = setInterval(pollLiveHead, 3500);
    if (__liveBound) return;
    __liveBound = true;
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            pollLiveHead();
        }
    });
}

async function loadNotifications(opts) {
    const silent = !!(opts && opts.silent);
    const badge = document.getElementById('notif-badge');
    const body = document.getElementById('notif-panel-body');
    if (!badge && !body) return;
    if (__notifLoading) return;
    __notifLoading = true;

    let result;
    try {
        const res = await fetch(notificationsApiUrl(), { credentials: 'same-origin' });
        result = await res.json();
    } catch (e) {
        __notifLoading = false;
        return;
    }
    __notifLoading = false;
    if (!result || !result.success) return;

    const unread = result.unread_count || 0;
    const latestId = Number(result.latest_id || 0);
    if (badge) {
        if (unread > 0) {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.style.display = 'flex';
            badge.classList.toggle('notif-bell-badge--pulse', __notifReady && latestId > __notifLatestId);
        } else {
            badge.style.display = 'none';
            badge.classList.remove('notif-bell-badge--pulse');
        }
    }

    if (__notifReady && latestId > __notifLatestId) {
        const newest = (result.data || []).find(n => Number(n.id) === latestId) || (result.data || [])[0];
        if (newest && !Number(newest.is_read) && typeof showToast === 'function') {
            showToast(newest.title || 'New notification', 'success');
        }
    }
    if (latestId > __notifLatestId) {
        __notifLatestId = latestId;
    }
    __notifReady = true;

    if (body && (!silent || document.getElementById('notif-panel')?.classList.contains('show'))) {
        const items = result.data || [];
        if (items.length === 0) {
            body.innerHTML = '<p class="muted" style="padding:12px">No notifications yet</p>';
        } else {
            body.innerHTML = items.map(n => `
                <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick='openNotification(${Number(n.id)}, ${JSON.stringify(String(n.related_type || ''))}, ${Number(n.related_id || 0)})'>
                    <div class="notif-item-title">${escAttr(n.title)}</div>
                    <div class="notif-item-msg">${escAttr(n.message || '')}</div>
                    <div class="notif-item-time">${escAttr(formatNotifTime(n.created_at))}</div>
                </div>
            `).join('');
        }
    }
}

async function markNotificationRead(id) {
    try {
        await fetch(notificationsApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'mark_read', id }),
        });
    } catch (e) {
        // ignore
    }
    loadNotifications();
}

async function markAllNotificationsRead() {
    try {
        await fetch(notificationsApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'mark_all_read' }),
        });
    } catch (e) {
        // ignore
    }
    loadNotifications();
}

function openNotification(id, relatedType, relatedId) {
    markNotificationRead(id);
    const staff = document.body.classList.contains('staff-portal');
    const type = String(relatedType || '');
    if (type === 'proc_pr') {
        if (staff && typeof staffShowSection === 'function') staffShowSection('procurement');
        else if (typeof nav === 'function') nav('procurement');
        if (relatedId && typeof window.procSelect === 'function') {
            setTimeout(() => window.procSelect(relatedId), 80);
        }
        return;
    }
    if (type === 'unserviceable_record') {
        if (staff && typeof staffShowSection === 'function') staffShowSection('inventory');
        else if (typeof nav === 'function') nav('unserviceable');
        return;
    }
    if (type === 'forecast') {
        if (!staff && typeof nav === 'function') nav('forecast');
    }
}

function startNotificationAutoRefresh() {
    startLiveSync();
}

const SE_VIEW_SELECTED_KEY = 'lgueSeViewSelectedIds';
let seViewSelectedOpen = false;
let sePendingNav = null;

function seLiveSelectedIds() {
    if (typeof window.ptagSelectedIds === 'function') {
        return window.ptagSelectedIds().map((n) => Number(n)).filter((n) => n > 0);
    }
    return [];
}

function seDraftSelectedIds() {
    try {
        const raw = sessionStorage.getItem(SE_VIEW_SELECTED_KEY);
        const ids = raw ? JSON.parse(raw) : [];
        return Array.isArray(ids) ? ids.map((n) => Number(n)).filter((n) => n > 0) : [];
    } catch (e) {
        return [];
    }
}

function seSaveDraftSelectedIds(ids) {
    const clean = (ids || []).map((n) => Number(n)).filter((n) => n > 0);
    if (!clean.length) {
        sessionStorage.removeItem(SE_VIEW_SELECTED_KEY);
        return;
    }
    sessionStorage.setItem(SE_VIEW_SELECTED_KEY, JSON.stringify(clean));
}

function seAllSEItems() {
    const out = [];
    Object.keys(seItemsByOffice || {}).forEach((key) => {
        const pack = seItemsByOffice[key] || {};
        (pack.items || []).forEach((item) => out.push(item));
    });
    return out;
}

function seFindItemsByIds(ids) {
    const want = (ids || []).map((n) => Number(n)).filter((n) => n > 0);
    const byId = new Map(seAllSEItems().map((item) => [Number(item.id), item]));
    return want.map((id) => byId.get(id)).filter(Boolean);
}

function refreshSEViewSelectedChrome() {
    // View selected UI removed — keep as no-op for older call sites.
}

function renderSEViewSelected() {
    const container = document.getElementById('se-offices-container');
    if (!container) return;
    seViewSelectedOpen = true;
    seDrillOfficeId = null;
    setInvDrillChrome('semi', false);
    const search = document.getElementById('s-se')?.value.toLowerCase() || '';
    const status = document.getElementById('f-status-se')?.value || '';
    const officeFilter = document.getElementById('f-office-se')?.value || '';
    let items = seFindItemsByIds(seDraftSelectedIds());
    items = items.filter((item) => {
        const matchesSearch = !search
            || lgueItemMatchesSearch(item, search, item.office_name);
        const matchesStatus = !status || item.status === status;
        const matchesOffice = !officeFilter || String(item.office_id) === String(officeFilter);
        return matchesSearch && matchesStatus && matchesOffice;
    });
    refreshSEViewSelectedChrome();
    if (!seDraftSelectedIds().length) {
        seViewSelectedOpen = false;
        refreshSEViewSelectedChrome();
        renderSEOfficeAccordion();
        return;
    }
    if (!items.length) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">🔍</div><p>No saved selected items match this search</p></div>';
        return;
    }
    let tbodies = '<tbody>';
    items.forEach((item) => {
        tbodies += gsoItemRowHtml(item, false, false, undefined, true);
    });
    tbodies += '</tbody>';
    container.innerHTML = `<div class="gso-table-wrap se-view-selected-table" data-gso-root="1">${renderGsoTableShell(false, false, tbodies, true)}</div>`;
    wireGsoTablesIn(container);
    if (typeof window.__ptagSyncChecks === 'function') window.__ptagSyncChecks();
}

function openSEViewSelected() {
    if (!seDraftSelectedIds().length) {
        showToast('No saved selected items yet', 'error');
        return;
    }
    renderSEViewSelected();
}

function closeSEViewSelected() {
    seViewSelectedOpen = false;
    refreshSEViewSelectedChrome();
    const search = document.getElementById('s-se')?.value || '';
    const status = document.getElementById('f-status-se')?.value || '';
    const officeFilter = document.getElementById('f-office-se')?.value || '';
    if (search || status || officeFilter) filterSEItems();
    else renderSEOfficeAccordion();
}

function clearSEViewSelectedDraft() {
    seSaveDraftSelectedIds([]);
    seViewSelectedOpen = false;
    refreshSEViewSelectedChrome();
    const search = document.getElementById('s-se')?.value || '';
    const status = document.getElementById('f-status-se')?.value || '';
    const officeFilter = document.getElementById('f-office-se')?.value || '';
    if (search || status || officeFilter) filterSEItems();
    else renderSEOfficeAccordion();
    showToast('Cleared the saved selected list');
}

function finishSeLeaveNav() {
    const pending = sePendingNav;
    sePendingNav = null;
    closeModal('se-leave-selected-modal');
    if (pending && pending.page) applyNav(pending.page, pending.event);
}

function cancelSeLeaveSelected() {
    sePendingNav = null;
    closeModal('se-leave-selected-modal');
}

function confirmSeLeaveSaveDraft() {
    // View selected removed — leaving keeps current checkbox selection only in-session.
    finishSeLeaveNav();
}

function confirmSeLeaveDontSave() {
    finishSeLeaveNav();
}

function confirmSeLeaveUnselect() {
    if (typeof window.ptagClearSelection === 'function') window.ptagClearSelection();
    finishSeLeaveNav();
}

// Navigation
function nav(page, event) {
    if (currentPage === 'semi' && page && page !== 'semi') {
        const live = seLiveSelectedIds();
        if (live.length) {
            sePendingNav = { page, event };
            const msg = document.getElementById('se-leave-selected-msg');
            if (msg) {
                msg.textContent = live.length === 1
                    ? 'You selected 1 property item on Semi-Expendable for tagging. Leave with the selection, or unselect it first.'
                    : `You selected ${live.length} property items on Semi-Expendable for tagging. Leave with the selection, or unselect them first.`;
            }
            openModal('se-leave-selected-modal');
            return;
        }
    }
    applyNav(page, event);
}

function applyNav(page, event) {
    if (page === 'returns' || page === 'ret') {
        page = 'unserviceable';
    }

    const prevPage = currentPage;

    // Sidebar / page switch: leave department drill — always land on the main office/tab list
    if (prevPage === 'semi' && page !== 'semi') {
        resetInvOfficeDrill('semi');
    }
    if (prevPage === 'ppe' && page !== 'ppe') {
        resetInvOfficeDrill('ppe');
    }
    if (page === 'semi') {
        resetInvOfficeDrill('semi');
    }
    if (page === 'ppe') {
        resetInvOfficeDrill('ppe');
    }

    // Remove active class from all pages and sidebar items
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.sb-item').forEach(item => item.classList.remove('active'));
    
    // Add active class to selected page
    document.getElementById('pg-' + page).classList.add('active');
    
    if (event && event.target) {
        const clickedItem = event.target.closest('.sb-item');
        if (clickedItem) {
            clickedItem.classList.add('active');
        }
    } else {
        document.querySelectorAll('.sb-item').forEach((item) => {
            const oc = item.getAttribute('onclick') || '';
            if (oc.includes(`nav('${page}'`)) {
                item.classList.add('active');
            }
        });
    }
    
    currentPage = page;

    const contentEl = document.querySelector('.content');
    if (contentEl) {
        contentEl.classList.toggle('inv-page-fill', page === 'semi' || page === 'ppe');
    }
    
    // Update topbar title
    const titles = {
        'dashboard': ['Dashboard', 'Property inventory overview'],
        'semi': ['Semi-Expendable', `Items below ₱${getPpeThreshold().toLocaleString('en-PH')} grouped by office`],
        'ppe': ['PPE', `Property, Plant & Equipment (₱${getPpeThreshold().toLocaleString('en-PH')} & above)`],
        'issuance': ['Issuance Records', 'PAR — one receipt for PPE and semi-expendable'],
        'returns': ['Returns', 'Returned items tracking'],
        'unserviceable': ['Unserviceable', 'Damaged or unusable items'],
        'disposal': ['Disposal Records', 'Disposed items'],
        'procurement': ['Procurement', 'PR · Quotations · PO · AIR workflow'],
        'forecast': ['Forecast', 'Smart recommendations — what to buy, hold, or watch'],
        'activity': ['Activity log', userPermissions.departmentScoped ? 'This department only — other offices are hidden' : 'Who did what, and when'],
        'settings': ['Settings', 'Your account and system preferences']
    };
    
    if (titles[page]) {
        document.getElementById('tb-title').textContent = titles[page][0];
        document.getElementById('tb-sub').textContent = titles[page][1];
    }
    
    requestAnimationFrame(() => loadPageData(page));
}

/** Clear SE/PPE department drill so sidebar returns to the all-depts grid. */
function resetInvOfficeDrill(kind) {
    if (kind === 'semi') {
        seDrillOfficeId = null;
        setInvDrillChrome('semi', false);
        const officeFilter = document.getElementById('f-office-se');
        if (officeFilter) officeFilter.value = '';
        return;
    }
    if (kind === 'ppe') {
        ppeDrillTabSlug = null;
        setInvDrillChrome('ppe', false);
    }
}

// Load page-specific data
function loadPageData(page) {
    switch(page) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'semi':
            loadSemiExpendable();
            break;
        case 'ppe':
            loadPPE();
            break;
        case 'issuance':
            loadIssuances();
            break;
        case 'returns':
            loadReturns();
            break;
        case 'unserviceable':
            loadUnserviceable();
            break;
        case 'disposal':
            loadDisposal();
            break;
        case 'activity':
            loadActivityLog();
            break;
        case 'procurement':
            loadProcurement();
            break;
        case 'forecast':
            if (typeof window.loadForecast === 'function') {
                window.loadForecast();
            }
            break;
        case 'settings':
            if (typeof window.loadSettings === 'function') {
                window.loadSettings();
            }
            break;
    }
}

// API Helper Functions
async function apiGet(endpoint) {
    try {
        const response = await fetch(`api/${endpoint}`);
        const text = await response.text();
        
        // Try to parse as JSON
        try {
            return JSON.parse(text);
        } catch (jsonError) {
            console.error('API Error: Invalid JSON response', text.substring(0, 200));
            showToast('Database not initialized. Run scripts/setup-database.php', 'error');
            return { 
                success: false, 
                message: 'Database not initialized',
                hint: 'Please run scripts/setup-database.php to set up the database'
            };
        }
    } catch (error) {
        console.error('API Error:', error);
        showToast('Error loading data', 'error');
        return { success: false, message: error.message };
    }
}

const __itemsGetCache = new Map();

function invalidateItemsCache() {
    __itemsGetCache.clear();
}

function yieldToPaint() {
    return new Promise((resolve) => requestAnimationFrame(resolve));
}

async function apiGetItems(query) {
    const key = query || '';
    const hit = __itemsGetCache.get(key);
    if (hit && (Date.now() - hit.at) < 25000) {
        return hit.result;
    }
    const result = await apiGet(key ? `items.php?${key}` : 'items.php');
    if (result && result.success) {
        __itemsGetCache.set(key, { at: Date.now(), result });
    }
    return result;
}

function inferBusyLabel(btn) {
    const t = String(btn && btn.textContent ? btn.textContent : '').toLowerCase();
    if (t.includes('save') || t.includes('create') || t.includes('encode')) return 'Saving…';
    if (t.includes('delete')) return 'Deleting…';
    return 'Sending…';
}

async function runBusy(button, work, label) {
    const btn = typeof button === 'string' ? document.getElementById(button) : button;
    if (btn && btn.dataset.busy === '1') {
        return;
    }
    const prevHtml = btn ? btn.innerHTML : '';
    const prevDisabled = btn ? btn.disabled : false;
    if (btn) {
        btn.dataset.busy = '1';
        btn.disabled = true;
        btn.classList.add('is-busy');
        const text = String(label || inferBusyLabel(btn));
        btn.innerHTML =
            '<span class="btn-send-ico" aria-hidden="true"><i class="fas fa-paper-plane"></i></span>' +
            '<span class="btn-send-txt">' + text + '</span>';
    }
    try {
        return await work();
    } finally {
        if (btn) {
            btn.dataset.busy = '';
            btn.disabled = prevDisabled;
            btn.classList.remove('is-busy');
            btn.innerHTML = prevHtml;
        }
    }
}
window.runBusy = runBusy;

function lgueMarkField(el, invalid) {
    if (!el) return;
    el.classList.toggle('inp-invalid', !!invalid);
    if (invalid) el.setAttribute('aria-invalid', 'true');
    else el.removeAttribute('aria-invalid');
}

function lgueLooksIllegible(raw) {
    const compact = String(raw || '').trim().replace(/\s+/g, '').toLowerCase();
    if (compact.length < 6) return false;
    if (/(.)\1{4,}/.test(compact)) return true;
    if (/^(asd|asdf|qwe|qwer|zxc|xxx|aaa|test|qwerty|asdfgh)+$/i.test(compact)) return true;
    const letters = compact.replace(/[^a-zñáéíóúü]/g, '');
    if (letters.length >= 8) {
        const uniq = new Set(letters.split(''));
        if (uniq.size <= 3) return true;
        const vowels = (letters.match(/[aeiouáéíóúüñ]/g) || []).length;
        if (vowels / letters.length < 0.12) return true;
    }
    return false;
}

function lgueSearchNorm(raw) {
    return String(raw ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function lgueSearchTokens(raw) {
    return lgueSearchNorm(raw).split(' ').filter(Boolean);
}

function lgueEditDistance(a, b) {
    if (a === b) return 0;
    if (!a || !b) return 99;
    if (Math.abs(a.length - b.length) > 2) return 99;
    const rows = a.length + 1;
    const cols = b.length + 1;
    const dp = new Array(rows);
    for (let i = 0; i < rows; i++) {
        dp[i] = new Array(cols);
        dp[i][0] = i;
    }
    for (let j = 0; j < cols; j++) dp[0][j] = j;
    for (let i = 1; i < rows; i++) {
        for (let j = 1; j < cols; j++) {
            dp[i][j] = Math.min(
                dp[i - 1][j] + 1,
                dp[i][j - 1] + 1,
                dp[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1)
            );
        }
    }
    return dp[a.length][b.length];
}

function lgueWordCloseToToken(word, token) {
    if (!word || !token) return false;
    if (word === token || word.startsWith(token)) return true;
    if (token.length >= 3 && word.includes(token)) return true;
    // Property serials and PR numbers must not fuzzy-match (000320 ≈ 000021).
    if (/^\d+$/.test(token) || /^\d+$/.test(word)) return false;
    if (token.length >= 4 && word.length >= 4) {
        const allow = token.length >= 6 ? 2 : 1;
        if (lgueEditDistance(word, token) <= allow) return true;
    }
    return false;
}

function lgueTextMatchesQuery(haystack, query) {
    const q = lgueSearchNorm(query);
    if (!q) return true;
    const hay = lgueSearchNorm(haystack);
    if (!hay) return false;
    if (q.length >= 3 && hay.includes(q)) return true;
    const tokens = q.split(' ').filter(Boolean);
    const words = hay.split(' ').filter(Boolean);
    if (!tokens.length) return true;
    if (words.length >= 2 && words.length <= 6) {
        const initials = words.map((w) => w.charAt(0)).join('');
        if (tokens.length === 1 && tokens[0].length >= 2 && initials.startsWith(tokens[0])) return true;
        if (tokens.join('') === initials) return true;
    }
    return tokens.every((t) => {
        if (t.length <= 2) {
            return words.some((w) => w === t || w.startsWith(t));
        }
        if (hay.includes(t)) return true;
        return words.some((w) => lgueWordCloseToToken(w, t));
    });
}

function lgueItemSearchBlob(item, extra) {
    return [
        item && item.property_number,
        item && item.article,
        item && item.description,
        item && item.item_group,
        item && item.office_name,
        item && item.remarks,
        item && item.status,
        extra,
    ].filter((v) => v != null && String(v).trim() !== '').join(' ');
}

function lgueCompactPropertyNo(raw) {
    return String(raw ?? '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

function lgueLooksPropertyNoQuery(query) {
    const compact = lgueCompactPropertyNo(query);
    if (!compact) return false;
    if (/^\d{4}(ppe|se)\d{3,}$/.test(compact)) return true;
    if (/^\d{4}\d{2,}/.test(compact) && compact.length >= 12 && /[a-z]/.test(compact)) return true;
    return false;
}

function lgueItemMatchesSearch(item, query, extra) {
    const q = String(query || '').trim();
    if (!q) return true;
    if (lgueLooksPropertyNoQuery(q)) {
        const want = lgueCompactPropertyNo(q);
        const have = lgueCompactPropertyNo(item && item.property_number);
        return have !== '' && have === want;
    }
    const tokens = lgueSearchNorm(q).split(' ').filter(Boolean);
    const catToken = tokens.find((t) => t === 'ppe' || t === 'se');
    if (catToken) {
        const cat = String((item && item.category) || '').toLowerCase();
        if (cat && cat !== catToken) return false;
    }
    return lgueTextMatchesQuery(lgueItemSearchBlob(item, extra), q);
}

window.lgueTextMatchesQuery = lgueTextMatchesQuery;
window.lgueItemMatchesSearch = lgueItemMatchesSearch;

function lgueParseNumber(raw, label, opts) {
    const o = opts || {};
    const s = lgueMoneyPlain(raw);
    if (s === '' || s === '.') {
        return { ok: false, message: label + ' must be a number.' };
    }
    const re = o.integer ? /^\d+$/ : /^\d+(\.\d+)?$/;
    if (!re.test(s)) {
        return { ok: false, message: label + ' must be a number only (example: 12 or 12.50). Letters are not allowed.' };
    }
    const n = Number(s);
    if (!Number.isFinite(n)) {
        return { ok: false, message: label + ' must be a number.' };
    }
    if (o.min != null && n < o.min) {
        return { ok: false, message: label + ' must be at least ' + o.min + '.' };
    }
    return { ok: true, value: n };
}

function lgueMoneyPlain(raw) {
    return String(raw ?? '').replace(/[₱,\s]/g, '');
}

function isDonationUnitValue(v) {
    return String(v == null ? '' : v).replace(/\u00a0/g, ' ').trim().toUpperCase() === 'DONATION';
}

function itemUnitValueIsDonation(item) {
    if (!item) return false;
    if (isDonationUnitValue(item.unit_value)) return true;
    const mode = String(item.acquisition_mode || '').trim().toLowerCase();
    return mode === 'donation' && (parseFloat(item.unit_value) || 0) === 0;
}

function formatItemUnitValue(item) {
    return itemUnitValueIsDonation(item) ? 'DONATION' : formatCurrency(item && item.unit_value);
}

function lgueMoneyFromNumber(n) {
    if (isDonationUnitValue(n)) return 'DONATION';
    if (n == null || n === '') return '';
    const x = Number(n);
    if (!Number.isFinite(x)) return '';
    if (Math.abs(x - Math.round(x)) < 1e-9) {
        return Math.round(x).toLocaleString('en-US');
    }
    return x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function lgueFormatMoneyTyping(raw) {
    const typed = String(raw ?? '').replace(/[₱,\s]/g, '');
    const upper = typed.toUpperCase();
    if (upper === 'DONATION' || ('DONATION'.startsWith(upper) && /^[A-Za-z]+$/.test(typed))) {
        return upper;
    }
    let s = lgueMoneyPlain(raw);
    if (s === '') return '';
    if (s === '.') return '0.';
    s = s.replace(/[^\d.]/g, '');
    const firstDot = s.indexOf('.');
    if (firstDot !== -1) {
        s = s.slice(0, firstDot + 1) + s.slice(firstDot + 1).replace(/\./g, '');
    }
    const trailingDot = s.endsWith('.') && (s.match(/\./g) || []).length === 1;
    const parts = s.split('.');
    let intPart = (parts[0] || '').replace(/\D/g, '');
    if (intPart === '') intPart = '0';
    intPart = intPart.replace(/^0+(?=\d)/, '');
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (trailingDot) return grouped + '.';
    if (parts.length > 1) return grouped + '.' + String(parts[1] || '').replace(/\D/g, '').slice(0, 2);
    return grouped;
}

function lgueIsMoneyField(el) {
    return !!(el && el.matches && el.matches('#e-amt, #d-amt, input.proc-pr-est, input.proc-price-inp, input.lgue-money'));
}

function lgueMoneyInputApply(el) {
    if (!el) return;
    if (el.type === 'number') el.type = 'text';
    el.setAttribute('inputmode', 'decimal');
    el.setAttribute('autocomplete', 'off');
    const start = el.selectionStart;
    const old = String(el.value || '');
    const sigBefore = old.slice(0, start == null ? old.length : start).replace(/[^\d.]/g, '');
    const next = lgueFormatMoneyTyping(old);
    el.value = next;
    let seen = 0;
    let pos = next.length;
    for (let i = 0; i < next.length; i++) {
        if (/[\d.]/.test(next[i])) {
            seen += 1;
            if (seen >= sigBefore.length) {
                pos = i + 1;
                break;
            }
        }
    }
    if (sigBefore.length === 0) pos = next.length;
    try { el.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
    if (el.id === 'e-amt' && typeof liveClassify === 'function') liveClassify();
    if (el.classList.contains('proc-price-inp')) {
        const tr = el.closest('tr');
        const tot = tr && tr.querySelector('td.muted');
        const plid = el.getAttribute('data-plid');
        const line = ((window.__procDetail && window.__procDetail.lines) || []).find((l) => String(l.id) === String(plid));
        const qty = Number(line && line.quantity != null ? line.quantity : 1);
        const chk = lgueParseNumber(el.value, 'Unit price', { min: 0 });
        if (tot && chk.ok) {
            tot.textContent = (chk.value * qty).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
}

function lgueMoneyBlur(el) {
    const plain = lgueMoneyPlain(el.value);
    if (plain === '' || plain === '.') return;
    const n = Number(plain);
    if (!Number.isFinite(n) || n < 0) return;
    el.value = lgueMoneyFromNumber(n);
}

document.addEventListener('focusin', (e) => {
    const el = e.target;
    if (!lgueIsMoneyField(el)) return;
    if (el.type === 'number') el.type = 'text';
    el.setAttribute('inputmode', 'decimal');
});
document.addEventListener('input', (e) => {
    if (lgueIsMoneyField(e.target)) lgueMoneyInputApply(e.target);
});
document.addEventListener('blur', (e) => {
    if (lgueIsMoneyField(e.target)) lgueMoneyBlur(e.target);
}, true);

window.lgueMoneyFromNumber = lgueMoneyFromNumber;
window.lgueMoneyPlain = lgueMoneyPlain;

function lgueRequireText(raw, label, opts) {
    const o = opts || {};
    const s = String(raw || '').trim();
    const min = o.minLen || 3;
    if (s.length < min) {
        return { ok: false, message: label + ' needs a clear official entry (at least ' + min + ' characters).' };
    }
    if (!/[A-Za-zÑñÁÉÍÓÚáéíóúüÜ]/.test(s)) {
        return { ok: false, message: label + ' must contain letters — a number alone is not enough.' };
    }
    if (lgueLooksIllegible(s)) {
        return { ok: false, message: label + ' must be readable official text. Random letters cannot be submitted.' };
    }
    if (o.minWords) {
        const words = s.split(/\s+/).filter((w) => /[A-Za-zÑñ]/.test(w));
        if (words.length < o.minWords) {
            return { ok: false, message: label + ' must be a complete phrase (at least ' + o.minWords + ' words).' };
        }
    }
    return { ok: true, value: s };
}

function lgueRequireName(raw, label) {
    const s = String(raw || '').trim();
    const base = lgueRequireText(s, label, { minLen: 3 });
    if (!base.ok) return base;
    if (!/^[A-Za-zÑñÁÉÍÓÚáéíóúüÜ][A-Za-zÑñÁÉÍÓÚáéíóúüÜ .,'\-]{1,99}$/.test(s)) {
        return { ok: false, message: label + ' must be a proper name (letters only).' };
    }
    return { ok: true, value: s };
}

function lgueRequireUnit(raw) {
    const s = String(raw || '').trim() || 'pc';
    if (!/^[A-Za-z][A-Za-z.\-]{0,15}$/.test(s)) {
        return { ok: false, message: 'Unit must be text only (example: pc, box, ream, set).' };
    }
    if (s.length >= 6 && lgueLooksIllegible(s)) {
        return { ok: false, message: 'Unit must be a real measure (pc, box, set, ream).' };
    }
    return { ok: true, value: s };
}

function lgueOptionalText(raw, label) {
    const s = String(raw || '').trim();
    if (s === '') return { ok: true, value: '' };
    return lgueRequireText(s, label, { minLen: 3 });
}

window.lgueParseNumber = lgueParseNumber;
window.lgueRequireText = lgueRequireText;
window.lgueRequireName = lgueRequireName;
window.lgueRequireUnit = lgueRequireUnit;
window.lgueOptionalText = lgueOptionalText;
window.lgueLooksIllegible = lgueLooksIllegible;
window.lgueMarkField = lgueMarkField;

function refreshAfterWrite() {
    invalidateItemsCache();
    if (currentPage === 'dashboard') {
        loadDashboard();
    } else {
        loadPageData(currentPage);
    }
}

async function apiPost(endpoint, data) {
    try {
        const response = await fetch(`api/${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const text = await response.text();
        
        // Try to parse as JSON
        try {
            return JSON.parse(text);
        } catch (jsonError) {
            console.error('API Error: Invalid JSON response', text.substring(0, 200));
            showToast('Database error. Check scripts/setup-database.php', 'error');
            return { 
                success: false, 
                message: 'Database error occurred',
                hint: 'Please ensure the database is properly set up'
            };
        }
    } catch (error) {
        console.error('API Error:', error);
        showToast('Error saving data', 'error');
        return { success: false, message: error.message };
    }
}

async function apiPut(endpoint, data) {
    try {
        const response = await fetch(`api/${endpoint}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (jsonError) {
            console.error('API Error: Invalid JSON response', text.substring(0, 200));
            showToast('Database error.', 'error');
            return { success: false, message: 'Database error occurred' };
        }
    } catch (error) {
        console.error('API Error:', error);
        showToast('Error saving data', 'error');
        return { success: false, message: error.message };
    }
}

async function apiDelete(endpoint) {
    try {
        const response = await fetch(`api/${endpoint}`, { method: 'DELETE' });
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (jsonError) {
            console.error('API Error: Invalid JSON response', text.substring(0, 200));
            showToast('Database error.', 'error');
            return { success: false, message: 'Database error occurred' };
        }
    } catch (error) {
        console.error('API Error:', error);
        showToast('Error deleting record', 'error');
        return { success: false, message: error.message };
    }
}

// Load offices
async function loadOffices() {
    const result = await apiGet('offices.php');
    if (result.success) {
        offices = result.data;
        populateOfficeSelects();
    }
}

// Populate office dropdowns
function populateOfficeSelects() {
    const ud = document.getElementById('userData');
    const isProcAdmin = ud && ud.dataset.userIsAdmin === '1';
    const procOfficeId = ud && ud.dataset.userOfficeId ? parseInt(ud.dataset.userOfficeId, 10) || 0 : 0;
    const procOfficeName =
        ud && ud.dataset.userOfficeName ? String(ud.dataset.userOfficeName).trim() : '';

    const selects = ['e-off', 'd-off', 'f-iss-office', 'ret-xfer-office', 'proc-pr-office'];
    selects.forEach((id) => {
        const select = document.getElementById(id);
        if (!select) {
            return;
        }

        if (id === 'proc-pr-office' && !isProcAdmin) {
            if (procOfficeId > 0) {
                const label =
                    procOfficeName !== '' ? procOfficeName : 'My department';
                select.innerHTML = `<option value="${procOfficeId}">${escapeHtml(label)}</option>`;
                select.value = String(procOfficeId);
            } else {
                select.innerHTML =
                    '<option value="">— no department on account —</option>';
                select.value = '';
            }
            select.disabled = true;
            return;
        }

        const currentValue = select.value;
        select.disabled = false;
        select.innerHTML =
            '<option value="">— select office —</option>' +
            offices.map((o) => `<option value="${o.id}">${o.name}</option>`).join('');
        if (currentValue) select.value = currentValue;
    });

    const seOff = document.getElementById('f-office-se');
    if (seOff) {
        const currentValue = seOff.value;
        seOff.innerHTML =
            '<option value="">All departments</option>' +
            offices.map((o) => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('');
        if (currentValue) seOff.value = currentValue;
    }

    const unsOff = document.getElementById('f-uns-office');
    if (unsOff) {
        const currentValue = unsOff.value;
        unsOff.innerHTML =
            '<option value="">All departments</option>' +
            offices.map((o) => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('');
        if (currentValue) unsOff.value = currentValue;
    }
    const unsPickOff = document.getElementById('uns-item-office');
    if (unsPickOff) {
        const currentValue = unsPickOff.value;
        unsPickOff.innerHTML =
            '<option value="">All departments</option>' +
            offices.map((o) => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('');
        if (currentValue) unsPickOff.value = currentValue;
    }

    const auditOff = document.getElementById('audit-office-filter');
    if (auditOff && auditOff.tagName === 'SELECT' && !userPermissions.departmentScoped) {
        const currentValue = auditOff.value;
        auditOff.innerHTML =
            '<option value="all">All activity</option>' +
            '<option value="admin">Admin only</option>' +
            offices.map((o) => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('');
        auditOff.value = currentValue || 'all';
    }
}

// Load dashboard
let __dashCatChart = null;
let __dashTopItemsChart = null;
let __dashPrStatusChart = null;
let __dashDeptFreqChart = null;
let __dashDeptFreq = [];
let __dashTopRequested = [];
let __dashAnalytics = null;

function dashDestroyChart(ref) {
    if (ref && typeof ref.destroy === 'function') {
        try { ref.destroy(); } catch (e) { /* ignore */ }
    }
    return null;
}

function dashShortLabel(text, max = 28) {
    const s = String(text || '').trim();
    if (s.length <= max) return s;
    return s.slice(0, max - 1) + '…';
}

function dashFormatMoney(n) {
    const v = Number(n) || 0;
    if (v >= 1e6) return '₱' + (v / 1e6).toFixed(1) + 'M';
    if (v >= 1e3) return '₱' + (v / 1e3).toFixed(1) + 'K';
    return '₱' + v.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

const DASH_PALETTE = ['#B22222', '#1849B4', '#0A7A5A', '#9E5505', '#5025A0', '#2193b0', '#B8860B', '#16733A'];

async function loadDashboard() {
    loadDashHeroWeather();
    const result = await apiGet('dashboard.php');
    if (result.success) {
        __dashAnalytics = result;
        renderDashboardStats(result.stats);
        renderAnalyticsCharts(result);
        renderRecentItems(result.recent_items);
        renderRecentTransactions(result.recent_transactions);
        renderProcurementByOffice(result.procurement_by_office, result.procurement_recent);
    }
}

/** Manila / Lumban time-of-day period for hero mood. */
function dashHeroPeriod(date = new Date()) {
    let h = Number(
        new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', hour12: false }).format(date)
    );
    if (h === 24) h = 0;
    if (h >= 5 && h < 7) return { id: 'dawn', label: 'Sunrise', icon: '🌤' };
    if (h >= 7 && h < 11) return { id: 'morning', label: 'Morning', icon: '☀' };
    if (h >= 11 && h < 15) return { id: 'day', label: 'Daytime', icon: '☀️' };
    if (h >= 15 && h < 17) return { id: 'afternoon', label: 'Afternoon', icon: '🌤' };
    if (h >= 17 && h < 19) return { id: 'sunset', label: 'Sunset', icon: '🌇' };
    return { id: 'night', label: 'Night', icon: '🌙' };
}

function dashWeatherLabel(code) {
    const c = Number(code);
    if (c === 0) return 'Clear sky';
    if (c === 1) return 'Mainly clear';
    if (c === 2) return 'Partly cloudy';
    if (c === 3) return 'Overcast';
    if (c === 45 || c === 48) return 'Foggy';
    if (c >= 51 && c <= 57) return 'Drizzle';
    if (c >= 61 && c <= 67) return 'Rain';
    if (c >= 71 && c <= 77) return 'Snow';
    if (c >= 80 && c <= 82) return 'Rain showers';
    if (c >= 95) return 'Thunderstorm';
    return 'Local weather';
}

function dashWeatherMood(code, periodId) {
    const c = Number(code);
    const raining = c >= 95 || (c >= 80 && c <= 82) || (c >= 61 && c <= 67) || (c >= 51 && c <= 57);
    if (periodId === 'night') return raining ? 'rain' : 'moon';
    if (raining) return 'rain';
    if (c === 2 || c === 3 || c === 45 || c === 48) return 'cloud';
    return 'sun';
}

function dashWeatherIcon(code, periodId) {
    const c = Number(code);
    if (c >= 95) return '⛈';
    if (c >= 80 || (c >= 61 && c <= 67)) return '🌧';
    if (c >= 51 && c <= 57) return '🌦';
    // At night, prefer moon over cloudy day icons
    if (periodId === 'night') return '🌙';
    if (c === 45 || c === 48) return '🌫';
    if (c === 3) return '☁';
    if (c === 2) return '⛅';
    if (c === 1) return '🌤';
    if (periodId === 'sunset' || periodId === 'dawn') return '🌤';
    return '☀';
}

function dashWxFormatClock(date = new Date()) {
    return new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    }).format(date);
}

function wireDashWxClock() {
    const tick = () => {
        const el = document.getElementById('dash-wx-clock');
        if (el) el.textContent = dashWxFormatClock();
        const periodEl = document.getElementById('dash-wx-period');
        const period = dashHeroPeriod();
        if (periodEl) periodEl.textContent = period.label;
    };
    tick();
    if (window.__dashWxClockTimer) clearInterval(window.__dashWxClockTimer);
    window.__dashWxClockTimer = setInterval(tick, 1000);
}

async function loadDashHeroWeather() {
    const hero = document.querySelector('.dash-hero');
    const wx = document.getElementById('dash-hero-weather');
    const period = dashHeroPeriod();
    if (hero) hero.removeAttribute('data-period');
    wireDashWxClock();

    const set = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    };
    const setMood = (mood) => {
        const icon = document.getElementById('dash-wx-icon');
        if (icon) icon.setAttribute('data-mood', mood || 'sun');
    };
    const setSky = (sky) => {
        if (wx) wx.setAttribute('data-sky', sky || 'partly');
    };
    set('dash-wx-period', period.label);
    set('dash-wx-icon', period.icon);
    setMood(period.id === 'night' ? 'moon' : 'sun');
    setSky(period.id === 'night' ? 'night' : 'partly');

    const dateEl = document.getElementById('dash-hero-date');
    if (dateEl) {
        const now = new Date();
        dateEl.dateTime = now.toISOString().slice(0, 10);
        dateEl.textContent = new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        }).format(now).replace(/,\s*/, ' · ');
    }

    try {
        const url =
            'https://api.open-meteo.com/v1/forecast' +
            '?latitude=14.2978&longitude=121.4597' +
            '&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m' +
            '&timezone=Asia%2FManila';
        const res = await fetch(url);
        if (!res.ok) throw new Error('weather fetch failed');
        const data = await res.json();
        const cur = data.current || {};
        const temp = Math.round(Number(cur.temperature_2m));
        const hum = Math.round(Number(cur.relative_humidity_2m));
        const wind = Math.round(Number(cur.wind_speed_10m));
        const code = cur.weather_code;
        const mood = dashWeatherMood(code, period.id);
        set('dash-wx-temp', Number.isFinite(temp) ? temp + '°' : '--°');
        set('dash-wx-cond', dashWeatherLabel(code));
        set('dash-wx-hum', Number.isFinite(hum) ? 'Humidity ' + hum + '%' : 'Humidity —');
        set('dash-wx-wind', Number.isFinite(wind) ? 'Wind ' + wind + ' km/h' : 'Wind —');
        set('dash-wx-icon', dashWeatherIcon(code, period.id));
        setMood(mood);
        if (period.id === 'night') setSky('night');
        else if (mood === 'rain') setSky('rain');
        else if (mood === 'sun' && Number(code) === 0) setSky('clear');
        else setSky('partly');
    } catch (err) {
        set('dash-wx-temp', '--°');
        set('dash-wx-cond', period.label + ' in Lumban');
        set('dash-wx-hum', 'Humidity —');
        set('dash-wx-wind', 'Offline');
        set('dash-wx-icon', period.icon);
        setMood(period.id === 'night' ? 'moon' : 'sun');
        setSky(period.id === 'night' ? 'night' : 'partly');
    }
}

function populateDeptFreqOfficeSelect(offices) {
    const sel = document.getElementById('dash-freq-office');
    if (!sel) return;
    const prev = sel.value;
    sel.innerHTML = '<option value="">All departments (overview)</option>' +
        (offices || []).map(o =>
            `<option value="${o.office_id}">${escAttr(o.office_name)}${o.office_code ? ' (' + escAttr(o.office_code) + ')' : ''}</option>`
        ).join('');
    if (prev && [...sel.options].some(opt => opt.value === prev)) {
        sel.value = prev;
    }
}

function dashLegendHtml(rows) {
    return `<div class="dash-legend">${rows.map(r => `
      <div class="dash-legend-row">
        <span class="dash-legend-swatch" style="--sw:${escAttr(r.color)}"></span>
        <span class="dash-legend-lbl">${escAttr(r.label)}</span>
        <strong class="dash-legend-val">${escAttr(String(r.value))}</strong>
        ${r.pct != null ? `<span class="dash-legend-pct">${escAttr(String(r.pct))}%</span>` : ''}
      </div>`).join('')}</div>`;
}

function dashMixTilesHtml(rows) {
    return `<div class="dash-mix-tiles">${rows.map((r) => {
        const tone = r.tone ? ` dash-mix-tile--${escAttr(r.tone)}` : '';
        const pct = r.pct != null ? Number(r.pct) : 0;
        const go = r.go || '';
        const click = go
            ? ` role="button" tabindex="0" onclick="nav('${escAttr(go)}', event)" onkeydown="if(event.key==='Enter')nav('${escAttr(go)}', event)"`
            : '';
        return `
      <div class="dash-mix-tile${tone}${go ? ' dash-mix-tile--link' : ''}"${click}>
        <div class="dash-mix-tile-top">
          <span class="dash-mix-tile-badge" aria-hidden="true">${r.icon || ''}</span>
          <span class="dash-mix-tile-kicker">${escAttr(r.kicker || '')}</span>
          <span class="dash-mix-tile-pct">${escAttr(String(pct))}%</span>
        </div>
        <strong class="dash-mix-tile-name">${escAttr(r.label)}</strong>
        <div class="dash-mix-tile-val">${escAttr(String(r.value))}<small> items</small></div>
        <div class="dash-mix-tile-bar" aria-hidden="true"><i style="width:${Math.max(0, Math.min(100, pct))}%"></i></div>
      </div>`;
    }).join('')}</div>`;
}

function dashStatusTilesHtml(rows) {
    return `<div class="dash-status-tiles">${rows.map((r) => {
        const pct = r.pct != null ? Number(r.pct) : 0;
        const go = r.go || '';
        const click = go
            ? ` role="button" tabindex="0" onclick="nav('${escAttr(go)}', event)" onkeydown="if(event.key==='Enter')nav('${escAttr(go)}', event)"`
            : '';
        return `
      <div class="dash-status-tile${go ? ' dash-status-tile--link' : ''}"${click}>
        <span class="dash-status-swatch" style="--sw:${escAttr(r.color)}"></span>
        <div class="dash-status-copy">
          <strong>${escAttr(r.label)}</strong>
          <div class="dash-status-bar" aria-hidden="true"><i style="width:${Math.max(0, Math.min(100, pct))}%;background:${escAttr(r.color)}"></i></div>
        </div>
        <div class="dash-status-nums">
          <em>${escAttr(String(r.value))}</em>
          <span>${escAttr(String(pct))}%</span>
        </div>
      </div>`;
    }).join('')}</div>`;
}

/** Draw % labels on doughnut slices (readable, no extra library). */
function dashDonutPctPlugin(total) {
    return {
        id: 'dashDonutPct',
        afterDatasetsDraw(chart) {
            const sum = Number(total) || chart.data.datasets[0].data.reduce((a, b) => a + (Number(b) || 0), 0);
            if (!sum) return;
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            meta.data.forEach((arc, i) => {
                const val = Number(chart.data.datasets[0].data[i]) || 0;
                if (val <= 0) return;
                const pct = Math.round((val / sum) * 100);
                if (pct < 7) return;
                const pos = arc.tooltipPosition();
                ctx.fillStyle = '#fff';
                ctx.font = "800 11px 'IBM Plex Mono', ui-monospace, monospace";
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.shadowColor = 'rgba(0,0,0,.25)';
                ctx.shadowBlur = 3;
                ctx.fillText(pct + '%', pos.x, pos.y);
            });
            ctx.restore();
        },
    };
}

function dashSetNote(id, text) {
    const el = document.getElementById(id);
    if (!el) return;
    if (!text) {
        el.hidden = true;
        el.textContent = '';
        return;
    }
    el.hidden = false;
    el.textContent = text;
}

function renderAnalyticsCharts(data) {
    if (typeof Chart === 'undefined') return;
    const chartCommon = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 650, easing: 'easeOutQuart' },
    };
    const stats = data.stats || {};
    const SE_COLOR = '#B22222';
    const PPE_COLOR = '#1C1917';

    // Stock mix doughnut
    const catCanvas = document.getElementById('dash-cat-chart');
    const catEmpty = document.getElementById('dash-cat-empty');
    const catCenter = document.getElementById('dash-cat-center');
    const catTotalEl = document.getElementById('dash-cat-total');
    const catCenterLbl = document.getElementById('dash-cat-center-lbl');
    const catLegend = document.getElementById('dash-cat-legend');
    const se = Number(stats.se_count) || 0;
    const ppe = Number(stats.ppe_count) || 0;
    const stockTotal = se + ppe;
    __dashCatChart = dashDestroyChart(__dashCatChart);
    if (catCanvas) {
        if (stockTotal === 0) {
            if (catEmpty) catEmpty.hidden = false;
            if (catCenter) catCenter.hidden = true;
            if (catLegend) catLegend.innerHTML = '';
            dashSetNote('dash-cat-note', '');
            catCanvas.style.display = 'none';
        } else {
            if (catEmpty) catEmpty.hidden = true;
            if (catCenter) catCenter.hidden = false;
            if (catTotalEl) catTotalEl.textContent = String(stockTotal);
            const sePct = Math.round((se / stockTotal) * 100);
            const ppePct = Math.round((ppe / stockTotal) * 100);
            if (catCenterLbl) {
                catCenterLbl.textContent = se >= ppe ? `${sePct}% SE` : `${ppePct}% PPE`;
            }
            catCanvas.style.display = '';
            if (catLegend) {
                catLegend.innerHTML = dashMixTilesHtml([
                    {
                        tone: 'se',
                        kicker: 'SE',
                        label: 'Semi-Expendable',
                        value: se.toLocaleString('en-PH'),
                        pct: sePct,
                        icon: '<i class="fas fa-box"></i>',
                        go: 'semi',
                    },
                    {
                        tone: 'ppe',
                        kicker: 'PPE',
                        label: 'Property, Plant & Equipment',
                        value: ppe.toLocaleString('en-PH'),
                        pct: ppePct,
                        icon: '<i class="fas fa-building"></i>',
                        go: 'ppe',
                    },
                ]);
            }
            const gap = Math.abs(sePct - ppePct);
            dashSetNote(
                'dash-cat-note',
                gap < 8
                    ? `Almost even split · ${stockTotal.toLocaleString('en-PH')} items total. Tap a tile to open the list.`
                    : `${se >= ppe ? 'SE' : 'PPE'} leads by ${gap}% · ${stockTotal.toLocaleString('en-PH')} items. Tap a tile to open.`
            );
            __dashCatChart = new Chart(catCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Semi-Expendable', 'PPE'],
                    datasets: [{
                        data: [se, ppe],
                        backgroundColor: [SE_COLOR, PPE_COLOR],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 4,
                        spacing: 1,
                    }],
                },
                options: {
                    ...chartCommon,
                    cutout: '74%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const v = Number(ctx.raw) || 0;
                                    const p = stockTotal ? Math.round((v / stockTotal) * 100) : 0;
                                    return ` ${ctx.label}: ${v.toLocaleString('en-PH')} items (${p}%)`;
                                },
                            },
                        },
                    },
                    onClick: (_evt, elements) => {
                        if (!elements?.length || typeof nav !== 'function') return;
                        nav(elements[0].index === 0 ? 'semi' : 'ppe');
                    },
                },
                plugins: [dashDonutPctPlugin(stockTotal)],
            });
        }
    }

    // Top requested items (horizontal bar) — kept if canvas still exists
    const topCanvas = document.getElementById('dash-top-items-chart');
    const topEmpty = document.getElementById('dash-top-items-empty');
    const topFoot = document.getElementById('dash-top-items-foot');
    const topItems = (data.top_requested_items || []).slice(0, 8);
    __dashTopItemsChart = dashDestroyChart(__dashTopItemsChart);
    if (topCanvas) {
        if (!topItems.length) {
            if (topEmpty) topEmpty.hidden = false;
            if (topFoot) topFoot.innerHTML = '';
            topCanvas.style.display = 'none';
        } else {
            if (topEmpty) topEmpty.hidden = true;
            topCanvas.style.display = '';
            const labels = topItems.map((r) => dashShortLabel(r.item_label, 26));
            const counts = topItems.map((r) => Number(r.request_count) || 0);
            if (topFoot) {
                const lead = topItems[0];
                topFoot.innerHTML = `<div class="dash-foot-note">
                  <span>Top ask</span>
                  <strong>${escAttr(lead.item_label)}</strong>
                  <em>${Number(lead.request_count) || 0}×</em>
                </div>`;
            }
            __dashTopItemsChart = new Chart(topCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Times on PRs',
                        data: counts,
                        backgroundColor: counts.map((_, i) => DASH_PALETTE[i % DASH_PALETTE.length]),
                        borderRadius: 8,
                        borderSkipped: false,
                        barThickness: 13,
                        maxBarThickness: 16,
                    }],
                },
                options: {
                    ...chartCommon,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => {
                                    const i = items[0]?.dataIndex ?? 0;
                                    return topItems[i]?.item_label || '';
                                },
                                label: (ctx) => ` ${ctx.raw}× on purchase requests`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0, font: { size: 10, family: "'IBM Plex Mono', monospace" }, color: '#9C9A8E' },
                            grid: { color: 'rgba(22,21,14,.05)', drawBorder: false },
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { size: 10, weight: '600' }, color: '#64624F' },
                            border: { display: false },
                        },
                    },
                },
            });
        }
    }

    // PR status doughnut
    const prCanvas = document.getElementById('dash-pr-status-chart');
    const prEmpty = document.getElementById('dash-pr-status-empty');
    const prCenter = document.getElementById('dash-pr-center');
    const prTotalEl = document.getElementById('dash-pr-total');
    const prCenterLbl = document.getElementById('dash-pr-center-lbl');
    const prLegend = document.getElementById('dash-pr-legend');
    const mix = data.pr_status_mix || [];
    __dashPrStatusChart = dashDestroyChart(__dashPrStatusChart);
    if (prCanvas) {
        if (!mix.length) {
            if (prEmpty) prEmpty.hidden = false;
            if (prCenter) prCenter.hidden = true;
            if (prLegend) prLegend.innerHTML = '';
            dashSetNote('dash-pr-note', '');
            prCanvas.style.display = 'none';
        } else {
            if (prEmpty) prEmpty.hidden = true;
            if (prCenter) prCenter.hidden = false;
            prCanvas.style.display = '';
            const statusColors = {
                submitted: '#1849B4',
                quoting: '#C2410C',
                po_issued: '#B22222',
                completed: '#15803D',
            };
            const statusLabels = {
                submitted: 'Submitted',
                quoting: 'Quoting',
                po_issued: 'PO issued',
                completed: 'Completed',
            };
            const prTotal = mix.reduce((s, r) => s + (Number(r.total) || 0), 0);
            if (prTotalEl) prTotalEl.textContent = String(prTotal);
            const openCount = mix
                .filter((r) => r.status !== 'completed')
                .reduce((s, r) => s + (Number(r.total) || 0), 0);
            const top = [...mix].sort((a, b) => (Number(b.total) || 0) - (Number(a.total) || 0))[0];
            const topLabel = statusLabels[top?.status] || String(top?.status || '').replace(/_/g, ' ');
            if (prCenterLbl) {
                prCenterLbl.textContent = openCount ? `${openCount} open` : 'all done';
            }
            if (prLegend) {
                prLegend.innerHTML = dashStatusTilesHtml(mix.map((r) => ({
                    color: statusColors[r.status] || '#9C9A8E',
                    label: statusLabels[r.status] || String(r.status || '').replace(/_/g, ' '),
                    value: Number(r.total) || 0,
                    pct: prTotal ? Math.round(((Number(r.total) || 0) / prTotal) * 100) : 0,
                    go: 'procurement',
                })));
            }
            const donePct = prTotal
                ? Math.round((((Number(mix.find((r) => r.status === 'completed')?.total) || 0) / prTotal) * 100))
                : 0;
            dashSetNote(
                'dash-pr-note',
                openCount
                    ? `${openCount} still open · most are “${topLabel}” · ${donePct}% completed. Tap to open PRs.`
                    : `All ${prTotal} PR${prTotal === 1 ? '' : 's'} completed.`
            );
            __dashPrStatusChart = new Chart(prCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: mix.map((r) => statusLabels[r.status] || String(r.status || '').replace(/_/g, ' ')),
                    datasets: [{
                        data: mix.map((r) => Number(r.total) || 0),
                        backgroundColor: mix.map((r) => statusColors[r.status] || '#9C9A8E'),
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 4,
                        spacing: 1,
                    }],
                },
                options: {
                    ...chartCommon,
                    cutout: '74%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const v = Number(ctx.raw) || 0;
                                    const p = prTotal ? Math.round((v / prTotal) * 100) : 0;
                                    return ` ${ctx.label}: ${v} (${p}%)`;
                                },
                            },
                        },
                    },
                    onClick: () => {
                        if (typeof nav === 'function') nav('procurement');
                    },
                },
                plugins: [dashDonutPctPlugin(prTotal)],
            });
        }
    }
}

function renderDeptFrequencyCharts() {
    const canvas = document.getElementById('dash-dept-freq-chart');
    const emptyEl = document.getElementById('dash-dept-freq-empty');
    const listEl = document.getElementById('dash-dept-freq-list');
    const chartLabel = document.getElementById('dash-freq-chart-label');
    const listSub = document.getElementById('dash-freq-list-sub');
    const officeId = document.getElementById('dash-freq-office')?.value || '';

    __dashDeptFreqChart = dashDestroyChart(__dashDeptFreqChart);

    let chartLabels = [];
    let chartData = [];
    let listHtml = '';
    let titleNote = 'All departments';
    let fullLabels = [];

    if (!officeId) {
        const rows = (__dashTopRequested || []).slice(0, 8);
        fullLabels = rows.map(r => r.item_label);
        chartLabels = rows.map(r => dashShortLabel(r.item_label, 22));
        chartData = rows.map(r => Number(r.request_count) || 0);
        const max = Math.max(...chartData, 1);
        listHtml = rows.length
            ? `<ol class="dash-rank-list">${rows.map((r, i) => {
                const w = Math.round((Number(r.request_count) || 0) / max * 100);
                return `
                <li>
                  <span class="dash-rank-n">${i + 1}</span>
                  <div class="dash-rank-body">
                    <strong>${escAttr(r.item_label)}</strong>
                    <span class="dash-rank-meta">${Number(r.office_count) || 0} dept(s)${r.last_requested ? ' · last ' + formatDate(r.last_requested) : ''}</span>
                    <span class="dash-rank-meter"><i style="width:${w}%"></i></span>
                  </div>
                  <span class="dash-rank-val">${Number(r.request_count) || 0}<small>×</small></span>
                </li>`;
            }).join('')}</ol>`
            : '<div class="empty"><div class="empty-icon">📋</div><p>No PR line items to rank yet</p></div>';
        if (chartLabel) chartLabel.textContent = 'Most frequent across all departments';
        if (listSub) listSub.textContent = 'All departments';
    } else {
        const dept = (__dashDeptFreq || []).find(d => String(d.office_id) === String(officeId));
        titleNote = dept ? dept.office_name : 'Department';
        const items = (dept && dept.items) ? dept.items : [];
        fullLabels = items.map(r => r.item_label);
        chartLabels = items.map(r => dashShortLabel(r.item_label, 22));
        chartData = items.map(r => Number(r.request_count) || 0);
        const max = Math.max(...chartData, 1);
        listHtml = items.length
            ? `<ol class="dash-rank-list">${items.map((r, i) => {
                const w = Math.round((Number(r.request_count) || 0) / max * 100);
                return `
                <li>
                  <span class="dash-rank-n">${i + 1}</span>
                  <div class="dash-rank-body">
                    <strong>${escAttr(r.item_label)}</strong>
                    <span class="dash-rank-meta">qty ${Number(r.total_qty) || 0}${r.last_requested ? ' · last ' + formatDate(r.last_requested) : ''}</span>
                    <span class="dash-rank-meter"><i style="width:${w}%"></i></span>
                  </div>
                  <span class="dash-rank-val">${Number(r.request_count) || 0}<small>×</small></span>
                </li>`;
            }).join('')}</ol>`
            : '<div class="empty"><div class="empty-icon">📋</div><p>This department has no submitted PR items yet</p></div>';
        if (chartLabel) chartLabel.textContent = titleNote;
        if (listSub) listSub.textContent = titleNote;
    }

    if (listEl) listEl.innerHTML = listHtml;

    if (!canvas) return;
    if (!chartData.length) {
        if (emptyEl) emptyEl.hidden = false;
        canvas.style.display = 'none';
        return;
    }
    if (emptyEl) emptyEl.hidden = true;
    canvas.style.display = '';

    if (typeof Chart === 'undefined') return;

    const maxBar = Math.max(...chartData, 1);
    __dashDeptFreqChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Times on PRs',
                data: chartData,
                backgroundColor: chartData.map((n, i) => DASH_PALETTE[i % DASH_PALETTE.length]),
                borderRadius: 10,
                borderSkipped: false,
                maxBarThickness: 36
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 700, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => {
                            const i = items[0]?.dataIndex ?? 0;
                            return fullLabels[i] || chartLabels[i] || '';
                        },
                        label: (ctx) => ` ${ctx.raw}× on purchase requests`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: Math.ceil(maxBar * 1.15),
                    ticks: { precision: 0, font: { size: 10, family: "'IBM Plex Mono', monospace" }, color: '#9C9A8E' },
                    grid: { color: 'rgba(22,21,14,.05)', drawBorder: false },
                    border: { display: false }
                },
                x: {
                    ticks: { font: { size: 10, weight: '600' }, color: '#64624F', maxRotation: 40, minRotation: 0 },
                    grid: { display: false },
                    border: { display: false }
                }
            }
        }
    });
}

// "Needs Attention" — unserviceable items still Pending disposal action
function renderNeedsAttentionWidget(items) {
    const container = document.getElementById('dash-needs-attention');
    if (!container) return;

    if (!items || items.length === 0) {
        container.innerHTML = '<p class="dash-quiet">Nothing pending</p>';
        return;
    }

    container.innerHTML = `<table class="dash-simple-table"><thead><tr>
      <th>Property #</th><th>Item</th><th>Office</th><th>Marked</th><th></th>
    </tr></thead><tbody>${items.map(item => `
      <tr>
        <td class="mono">${escAttr(item.property_number || '—')}</td>
        <td class="dash-cell-strong">${escAttr(item.description || '—')}</td>
        <td class="dash-cell-muted">${escAttr(item.office_name || '—')}</td>
        <td class="dash-cell-muted">${formatDate(item.marked_date)}</td>
        <td><button type="button" class="btn btn-ghost btn-xs" onclick="viewUnserviceableReason(${item.id})">Reason</button></td>
      </tr>`).join('')}
    </tbody></table>`;
}

// Procurement requests by office — simple clickable rows
function renderProcurementByOffice(byOffice, recent) {
    const container = document.getElementById('dash-proc-office');
    if (!container) return;

    if (!byOffice || byOffice.length === 0) {
        container.innerHTML = '<p class="dash-quiet">No purchase requests yet</p>';
        return;
    }

    container.innerHTML = `<table class="dash-simple-table dash-simple-table--click"><thead><tr>
      <th>Office</th><th>Total</th><th>Awaiting</th><th>Quoting</th><th>PO</th><th>Done</th><th>Last</th>
    </tr></thead><tbody>${byOffice.map(o => `
      <tr onclick="goToProcurementForOffice(${o.office_id})">
        <td class="dash-cell-strong">${escAttr(o.office_name)}</td>
        <td class="dash-cell-num">${o.total_pr || 0}</td>
        <td class="dash-cell-muted">${o.submitted_count || 0}</td>
        <td class="dash-cell-muted">${o.quoting_count || 0}</td>
        <td class="dash-cell-muted">${o.po_issued_count || 0}</td>
        <td class="dash-cell-muted">${o.completed_count || 0}</td>
        <td class="dash-cell-muted">${o.last_pr_date ? formatDate(o.last_pr_date) : '—'}</td>
      </tr>`).join('')}
    </tbody></table>`;
}

// Jump to the Procurement page pre-filtered to a specific office (from the dashboard card).
function goToProcurementForOffice(officeId) {
    nav('procurement');
    const sel = document.getElementById('f-proc-office');
    if (sel) {
        sel.value = String(officeId);
    }
    if (typeof loadProcurement === 'function') {
        loadProcurement();
    }
}

// ─────────────────────────────────────────────────────────
// Staff portal — section tabs (Procurement vs read-only office inventory)
// ─────────────────────────────────────────────────────────
function staffShowSection(section) {
    const dashPane = document.getElementById('staff-section-dashboard');
    const proc = document.getElementById('staff-section-procurement');
    const inv = document.getElementById('staff-section-inventory');
    const set = document.getElementById('staff-section-settings');
    const fc = document.getElementById('staff-section-forecast');
    const tabDash = document.getElementById('staff-tab-dashboard');
    const tabProc = document.getElementById('staff-tab-procurement');
    const tabInv = document.getElementById('staff-tab-inventory');
    const tabFc = document.getElementById('staff-tab-forecast');
    const tabSet = document.getElementById('staff-tab-settings');
    if (!proc || !inv) return;

    const allowed = ['dashboard', 'procurement', 'inventory', 'forecast', 'settings'];
    const which = allowed.includes(section) ? section : 'dashboard';

    if (dashPane) dashPane.style.display = which === 'dashboard' ? '' : 'none';
    proc.style.display = which === 'procurement' ? '' : 'none';
    inv.style.display = which === 'inventory' ? '' : 'none';
    if (fc) fc.style.display = which === 'forecast' ? '' : 'none';
    if (set) set.style.display = which === 'settings' ? '' : 'none';

    if (tabDash) tabDash.classList.toggle('active', which === 'dashboard');
    if (tabProc) tabProc.classList.toggle('active', which === 'procurement');
    if (tabInv) tabInv.classList.toggle('active', which === 'inventory');
    if (tabFc) tabFc.classList.toggle('active', which === 'forecast');
    if (tabSet) tabSet.classList.toggle('active', which === 'settings');

    const kicker = document.querySelector('.staff-shell-kicker');
    if (kicker) {
        const labels = {
            dashboard: 'Dashboard',
            procurement: 'Procurement',
            inventory: 'My inventory',
            forecast: 'Forecast',
            settings: 'Settings',
        };
        kicker.textContent = labels[which] || 'Department workspace';
    }

    if (which === 'dashboard' && typeof window.refreshStaffDashboard === 'function') {
        window.refreshStaffDashboard(window.__staffPrs || []);
    }
    if (which === 'inventory') {
        loadDeptInventory();
    }
    if (which === 'forecast' && typeof window.loadForecast === 'function') {
        window.loadForecast();
    }
    if (which === 'settings' && typeof window.loadSettings === 'function') {
        window.loadSettings();
    }
}

// Read-only inventory list for the logged-in department user's own office.
// The backend (api/items.php) auto-scopes non-admin requests to the caller's office,
// so no office_id needs to be sent here.
async function loadDeptInventory() {
    const container = document.getElementById('inv-dept-table');
    if (!container) return;

    const category = document.getElementById('inv-dept-category')?.value || '';
    const status = document.getElementById('inv-dept-status')?.value || '';
    const search = (document.getElementById('inv-dept-search')?.value || '').trim().toLowerCase();

    container.innerHTML = '<p class="muted" style="padding:16px">Loading…</p>';

    const qs = new URLSearchParams();
    if (category) qs.set('category', category);
    if (status) qs.set('status', status);
    const query = qs.toString();
    const res = await apiGetItems(query);

    if (!res.success) {
        container.innerHTML = `<p class="muted" style="padding:16px">${escAttr(res.message || 'Unable to load inventory')}</p>`;
        return;
    }

    let items = res.data || [];
    if (search) {
        items = items.filter((it) => lgueItemMatchesSearch(it, search));
    }

    if (items.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No items found for your office</p></div>';
        return;
    }

    window.__deptInvItems = items;
    const canReport = userPermissions.canCreate && !userPermissions.isViewer;

    container.innerHTML = `
        <table class="staff-pr-table staff-inv-table">
            <thead>
                <tr>
                    <th>Property #</th>
                    <th>Article</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th style="text-align:right">Qty</th>
                    <th style="text-align:right">Unit Value</th>
                    <th>Acquired</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(it => {
                    const statusLabel = deptItemStatusLabel(it);
                    const reportable = itemIsUnserviceableReportable(it);
                    const histBtn = `<button type="button" class="btn btn-ghost btn-xs" onclick='openAuditHistory(${Number(it.id)}, ${JSON.stringify(String(it.property_number || 'Item'))})'>History</button>`;
                    const unsBtn = (canReport && reportable)
                        ? `<button type="button" class="btn btn-red btn-xs staff-unserv-btn" onclick="openDeptUnserviceableModal(${Number(it.id)})"><i class="fas fa-triangle-exclamation"></i> Unserviceable</button>`
                        : '';
                    return `
                    <tr>
                        <td class="mono">${escAttr(it.property_number || '—')}</td>
                        <td>${escAttr(gsoDisplayArticle(it))}</td>
                        <td>${escAttr(it.description || '')}</td>
                        <td><span class="badge b-${String(it.category || '').toLowerCase()}">${escAttr(it.category || '')}</span></td>
                        <td><span class="badge ${deptItemStatusClass(statusLabel)}">${escAttr(statusLabel)}</span></td>
                        <td style="text-align:right">${escAttr(it.quantity ?? 1)}</td>
                        <td style="text-align:right">${formatItemUnitValue(it)}</td>
                        <td>${formatDate(it.acquisition_date)}</td>
                        <td>${histBtn}${unsBtn ? ' ' + unsBtn : ''}</td>
                    </tr>
                `;
                }).join('')}
            </tbody>
        </table>
    `;
}

function deptItemStatusLabel(it) {
    if ((it.status || '') === 'Unserviceable') {
        const rs = it.repair_status || 'Pending';
        if (rs === 'Defective' || rs === 'Serviceable') return rs;
        return 'Pending';
    }
    return it.status || '';
}

function deptItemStatusClass(label) {
    const key = String(label || '').toLowerCase().replace(/\s+/g, '');
    return 'b-' + key;
}

function itemIsUnserviceableReportable(it) {
    const st = String(it?.status || '');
    if (st === 'Disposed' || st === 'Transferred') return false;
    if (st === 'Unserviceable') {
        return String(it.repair_status || 'Pending') === 'Serviceable';
    }
    return true;
}

function syncStaffUnservOtherReason() {
    const cat = document.getElementById('staff-uns-reason-category')?.value || '';
    const wrap = document.getElementById('staff-uns-other-wrap');
    if (wrap) wrap.style.display = cat === 'Other' ? '' : 'none';
}

function openDeptUnserviceableModal(itemId) {
    if (!checkPermission('create')) return;
    const items = window.__deptInvItems || [];
    const it = items.find(row => Number(row.id) === Number(itemId));
    const idEl = document.getElementById('staff-uns-item-id');
    const labelEl = document.getElementById('staff-uns-item-label');
    const catEl = document.getElementById('staff-uns-reason-category');
    const otherEl = document.getElementById('staff-uns-other');
    const remarksEl = document.getElementById('staff-uns-remarks');
    if (!idEl || !document.getElementById('staff-unserv-modal')) {
        showToast('Unable to open report form', 'error');
        return;
    }
    idEl.value = String(itemId);
    const article = it ? (gsoDisplayArticle(it) || it.description || '') : '';
    const prop = it ? (it.property_number || '—') : '—';
    if (labelEl) labelEl.value = `${prop} — ${article}`;
    if (catEl) catEl.value = '';
    if (otherEl) otherEl.value = '';
    if (remarksEl) remarksEl.value = '';
    syncStaffUnservOtherReason();
    openModal('staff-unserv-modal');
}

async function submitDeptUnserviceable() {
    if (!checkPermission('create')) return;
    const itemId = parseInt(document.getElementById('staff-uns-item-id')?.value || '0', 10);
    const category = document.getElementById('staff-uns-reason-category')?.value || '';
    let other = (document.getElementById('staff-uns-other')?.value || '').trim();
    const remarks = (document.getElementById('staff-uns-remarks')?.value || '').trim();
    if (!itemId) {
        showToast('Missing item', 'error');
        return;
    }
    if (!category) {
        showToast('Please select a reason', 'error');
        return;
    }
    if (category === 'Other') {
        const otherChk = lgueRequireText(other, 'Other reason', { minLen: 8, minWords: 2 });
        if (!otherChk.ok) {
            showToast(otherChk.message, 'error');
            return;
        }
        other = otherChk.value;
    }
    const staffRemChk = lgueOptionalText(remarks, 'Remarks');
    if (!staffRemChk.ok) {
        showToast(staffRemChk.message, 'error');
        return;
    }
    const userDataEl = document.getElementById('userData');
    const markedBy = userDataEl?.getAttribute('data-user-name') || 'Department user';
    const result = await runBusy('staff-unserv-save-btn', () => apiPost('unserviceable.php', {
        property_item_id: itemId,
        marked_date: new Date().toISOString().slice(0, 10),
        marked_by: markedBy,
        reason_category: category,
        reason: category === 'Other' ? other : category,
        remarks: staffRemChk.value
    }));
    if (!result) return;
    if (result.success) {
        invalidateItemsCache();
        showToast('Item reported as unserviceable. GSO has been notified.');
        closeModal('staff-unserv-modal');
        loadDeptInventory();
        loadNotifications();
    } else {
        showToast(result.message || 'Failed to report item', 'error');
    }
}

// Render dashboard stats
function renderDashboardStats(stats) {
    const statRow = document.getElementById('stat-row');
    if (!statRow) return;
    const totalVal = dashFormatMoney(stats.total_value);
    const totalItems = Math.max(1, Number(stats.total_items) || 0);
    const prTotal = Math.max(1, Number(stats.pr_count) || 0);
    const pct = (n) => Math.min(100, Math.round((Number(n) || 0) / totalItems * 100));
    const cards = [
        {
            cls: 'stat--blue', nav: 'semi', lbl: 'Semi-Expendable', val: stats.se_count || 0, sub: 'Below ₱50k',
            bar: pct(stats.se_count), tip: 'Share of inventory',
            ico: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/></svg>'
        },
        {
            cls: 'stat--amber', nav: 'ppe', lbl: 'PPE', val: stats.ppe_count || 0, sub: '₱50k & above',
            bar: pct(stats.ppe_count), tip: 'Share of inventory',
            ico: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/></svg>'
        },
        {
            cls: 'stat--green', nav: 'issuance', lbl: 'Issued', val: stats.issued_count || 0, sub: 'Currently issued',
            bar: pct(stats.issued_count), tip: 'Of total items',
            ico: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 19V5M5 12l7-7 7 7"/></svg>'
        },
        {
            cls: 'stat--red', nav: 'unserviceable', lbl: 'Unserviceable', val: stats.unserviceable_count || 0, sub: 'Needs attention',
            bar: pct(stats.unserviceable_count), tip: 'Of total items',
            alert: Number(stats.unserviceable_count) > 0,
            ico: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>'
        },
        {
            cls: 'stat--teal', nav: 'procurement', lbl: 'Purchase Requests', val: stats.pr_count || 0, sub: `${stats.pr_open_count || 0} open · non-draft`,
            bar: Math.min(100, Math.round((Number(stats.pr_open_count) || 0) / prTotal * 100)), tip: 'Open vs total PRs',
            ico: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>'
        },
        {
            cls: 'stat--gold', nav: '', lbl: 'Inventory Value', val: totalVal, sub: `${stats.disposed_count || 0} disposed · ${stats.total_items || 0} items`, valSm: true,
            bar: 100, tip: 'Encoded asset value', skipCount: true, static: true,
            ico: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M15 9.5c0-1.1-1.3-2-3-2s-3 .9-3 2 1.3 2 3 2 3 .9 3 2-1.3 2-3 2-3-.9-3-2"/></svg>'
        }
    ];

    statRow.innerHTML = cards.map((c, i) => {
        const tag = c.static ? 'div' : 'button';
        const click = c.static ? '' : ` type="button" onclick="nav('${c.nav}', event)"`;
        const go = c.static ? '' : `<span class="dash-stat-go" aria-hidden="true">
                  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                </span>`;
        return `
        <${tag}${click} class="stat dash-stat ${c.cls}${c.alert ? ' dash-stat--alert' : ''}${c.static ? ' dash-stat--static' : ''}" style="--dash-i:${i};--dash-bar:${c.bar || 0}%">
            <div class="dash-stat-top">
                <span class="dash-stat-ico" aria-hidden="true">${c.ico}</span>
                <span class="stat-lbl">${c.lbl}</span>
                ${c.alert ? '<span class="dash-stat-live" title="Needs attention">!</span>' : ''}
            </div>
            <div class="dash-stat-mid">
                <div class="stat-val${c.valSm ? ' stat-val--sm' : ''}" data-count="${c.skipCount ? '' : (c.val)}" data-display="${escAttr(String(c.val))}">${c.val}</div>
                ${go}
            </div>
            <div class="dash-stat-bar" title="${escAttr(c.tip || '')}"><span class="dash-stat-bar-fill"></span></div>
            <div class="stat-sub">${c.sub}</div>
        </${tag}>`;
    }).join('');

    dashAnimateStatCounts(statRow);
}

function dashAnimateStatCounts(root) {
    const nodes = root.querySelectorAll('.stat-val[data-count]');
    nodes.forEach((el) => {
        const raw = el.getAttribute('data-count');
        if (raw === '' || raw == null) return;
        const target = Number(raw);
        if (!Number.isFinite(target)) return;
        const start = performance.now();
        const dur = 650;
        const tick = (now) => {
            const t = Math.min(1, (now - start) / dur);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = String(Math.round(target * eased));
            if (t < 1) requestAnimationFrame(tick);
            else el.textContent = el.getAttribute('data-display') || String(target);
        };
        requestAnimationFrame(tick);
    });
}

// Render recent items
function renderRecentItems(items) {
    const container = document.getElementById('dash-recent');
    if (!container) return;
    if (!items || items.length === 0) {
        container.innerHTML = '<p class="dash-quiet">No items encoded yet</p>';
        return;
    }

    container.innerHTML = `<ul class="dash-feed">${items.map((item) => {
        const cat = String(item.category || '').toUpperCase();
        const tone = cat === 'PPE' ? 'ppe' : 'se';
        const article = gsoDisplayArticle(item) || item.description || '—';
        return `
      <li class="dash-feed-row dash-feed-row--click" onclick="viewItemDetail(${item.id})" title="${escAttr(item.description || '')}">
        <span class="dash-feed-mark dash-feed-mark--${tone}" aria-hidden="true">${escAttr(cat === 'PPE' ? 'P' : 'S')}</span>
        <div class="dash-feed-copy">
          <strong>${escAttr(article)}</strong>
          <span>${escAttr(item.property_number || '—')}${item.office_name ? ' · ' + escAttr(item.office_name) : ''}</span>
        </div>
        <span class="dash-tag dash-tag--${tone}">${escAttr(cat || '—')}</span>
      </li>`;
    }).join('')}</ul>`;
}

// Render recent transactions
function renderRecentTransactions(txns) {
    const container = document.getElementById('dash-txn');
    if (!container) return;
    if (!txns || txns.length === 0) {
        container.innerHTML = '<p class="dash-quiet">No transactions yet</p>';
        return;
    }

    container.innerHTML = `<ul class="dash-feed">${txns.map((txn) => {
        const itemLabel = txn.description || txn.property_number || '—';
        const when = formatDate(txn.issue_date || txn.created_at);
        return `
      <li class="dash-feed-row">
        <span class="dash-feed-mark dash-feed-mark--txn" aria-hidden="true"><i class="fas fa-receipt"></i></span>
        <div class="dash-feed-copy">
          <strong>${escAttr(itemLabel)}</strong>
          <span>${escAttr(txn.form_number || '')}${txn.issued_to ? ' · ' + escAttr(txn.issued_to) : ''}</span>
        </div>
        <time class="dash-feed-when">${escAttr(when)}</time>
      </li>`;
    }).join('')}</ul>`;
}

// Render attention items
function renderAttentionItems(items) {
    const container = document.getElementById('dash-alerts');
    const countEl = document.getElementById('dash-alerts-count');
    if (countEl) countEl.textContent = String((items && items.length) || 0);
    if (!container) return;

    if (!items || items.length === 0) {
        container.innerHTML = '<p class="dash-quiet">None</p>';
        return;
    }

    container.innerHTML = `<table class="dash-simple-table dash-simple-table--click"><thead><tr>
      <th>Property #</th><th>Item</th><th>Status</th><th>Office</th>
    </tr></thead><tbody>${items.map(item => `
      <tr onclick="viewItemDetail(${item.id})">
        <td class="mono">${escAttr(item.property_number || '')}</td>
        <td class="dash-cell-strong">${escAttr(item.description || '—')}</td>
        <td class="dash-cell-muted">${escAttr(item.status || '—')}${item.condition_status ? ' · ' + escAttr(item.condition_status) : ''}</td>
        <td class="dash-cell-muted">${escAttr(item.office_name || '—')}</td>
      </tr>`).join('')}
    </tbody></table>`;
}

// Modal functions
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('open');
}

function openLogoutConfirm() {
    const userMenu = document.getElementById('userMenu');
    if (userMenu) {
        userMenu.classList.remove('show');
    }
    const userProfile = document.querySelector('.user-profile');
    if (userProfile) {
        userProfile.classList.remove('active');
    }
    openModal('logout-confirm-modal');
}

function confirmLogout() {
    try {
        window.sessionStorage.removeItem('lgue_app_loading_shown');
    } catch (e) {
        // ignore
    }
    window.location.href = lgueAppUrl('/logout');
}

/**
 * Full-page shell loader: dismiss after window "load" (document + stylesheets + images + synchronous scripts).
 * Minimum visible time avoids a flash on warm cache; failsafe covers stalled networks.
 */
(function setupAppLoadingScreen() {
    const el = document.getElementById('app-loading-screen');
    if (!el) return;

    // Only show the full splash once per browser session (e.g. right after
    // login). Subsequent reloads/navigations in the same tab session skip
    // the long minimum-visible delay and dismiss immediately.
    let alreadyShown = false;
    try {
        alreadyShown = window.sessionStorage.getItem('lgue_app_loading_shown') === '1';
        window.sessionStorage.setItem('lgue_app_loading_shown', '1');
    } catch (e) {
        alreadyShown = false;
    }

    const host = String(window.location.hostname || '').toLowerCase();
    const minVisibleMs = alreadyShown
        ? 0
        : (host === 'localhost' || host === '127.0.0.1' ? 150 : 350);
    const fadeMs = alreadyShown ? 0 : 480;
    const failSafeMs = 14000;
    const t0 = typeof performance !== 'undefined' ? performance.now() : Date.now();
    let finished = false;

    function teardown() {
        if (finished) return;
        finished = true;
        document.body.classList.remove('app-loading-active');
        el.setAttribute('aria-busy', 'false');
        el.classList.add('app-loading-screen--exiting');
        window.setTimeout(() => {
            el.remove();
        }, fadeMs + 80);
    }

    function scheduleDismiss() {
        const t1 = typeof performance !== 'undefined' ? performance.now() : Date.now();
        const wait = Math.max(0, minVisibleMs - (t1 - t0));
        window.setTimeout(() => {
            requestAnimationFrame(() => requestAnimationFrame(teardown));
        }, wait);
    }

    window.addEventListener('DOMContentLoaded', scheduleDismiss, { once: true });
    window.addEventListener('load', scheduleDismiss, { once: true });

    window.setTimeout(() => {
        if (!finished) {
            scheduleDismiss();
        }
    }, failSafeMs);

    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        scheduleDismiss();
    }
})();

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} show`;
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Format date
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const raw = String(dateStr).trim();
    const iso = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
    if (iso) {
        const d = new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]));
        if (!Number.isNaN(d.getTime())) {
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
    }
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return raw;
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function lgueTodayYmd() {
    const n = new Date();
    const m = String(n.getMonth() + 1).padStart(2, '0');
    const d = String(n.getDate()).padStart(2, '0');
    return `${n.getFullYear()}-${m}-${d}`;
}

function formatQty(q) {
    if (q === null || q === undefined || q === '') return '';
    const n = Number(q);
    if (!Number.isFinite(n)) return String(q);
    if (Math.abs(n - Math.round(n)) < 1e-9) return String(Math.round(n));
    return String(parseFloat(n.toFixed(4)));
}
window.formatQty = formatQty;

// Format currency
function formatCurrency(amount) {
    if (isDonationUnitValue(amount)) return 'DONATION';
    return '₱' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escAttr(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;');
}

// GSO inventory: property card qty vs physical count → shortage/overage
function gsoBookQty(item) {
    return parseInt(item.quantity, 10) || 0;
}

function gsoPhysicalQty(item) {
    if (item.quantity_physical_count === null || item.quantity_physical_count === undefined || item.quantity_physical_count === '') {
        return gsoBookQty(item);
    }
    return parseInt(item.quantity_physical_count, 10) || 0;
}

function gsoVarianceQty(item) {
    return gsoPhysicalQty(item) - gsoBookQty(item);
}

function gsoVarianceValue(item) {
    return gsoVarianceQty(item) * (parseFloat(item.unit_value) || 0);
}

function gsoDisplayArticle(item) {
    const a = (item.article || '').trim();
    return a || '—';
}

/** Leading list index in article text e.g. "1. Chair", "1 Table", "2) Desk", "10. Shelf". */
function gsoArticleLeadIndex(article) {
    if (article == null || typeof article !== 'string') return null;
    const m = String(article).trim().match(/^(\d+)\s*(?:[\.\)]\s*|\s+)/);
    if (m) return parseInt(m[1], 10);
    const only = String(article).trim().match(/^(\d+)\s*[\.\)]?\s*$/);
    return only ? parseInt(only[1], 10) : null;
}

/**
 * Continuation rows (blank article) inherit the last lead index from sort_order / id order.
 * @returns {Map<string, number|null>}
 */
function gsoEffectiveLeadIndexById(items) {
    const serverOrder = [...items].sort((a, b) => {
        const sa = parseInt(a.sort_order, 10);
        const sb = parseInt(b.sort_order, 10);
        const oa = Number.isFinite(sa) ? sa : 0;
        const ob = Number.isFinite(sb) ? sb : 0;
        if (oa !== ob) return oa - ob;
        return (parseInt(a.id, 10) || 0) - (parseInt(b.id, 10) || 0);
    });
    let last = null;
    const map = new Map();
    for (const it of serverOrder) {
        const raw = gsoArticleLeadIndex(it.article);
        if (raw != null) last = raw;
        map.set(String(it.id), last);
    }
    return map;
}

/**
 * Within one section/office group: numbered articles (1., 2., …) sort numerically on top;
 * continuations (empty article) follow their headline by sort_order; then sort_order, id.
 */
function gsoSortItemsByArticleNumber(items) {
    if (!items || items.length < 2) return items ? items.slice() : [];
    const effMap = gsoEffectiveLeadIndexById(items);
    return [...items].sort((a, b) => {
        const sa = parseInt(a.sort_order, 10);
        const sb = parseInt(b.sort_order, 10);
        const oa = Number.isFinite(sa) ? sa : 0;
        const ob = Number.isFinite(sb) ? sb : 0;
        if (oa !== ob) return oa - ob;
        const na = effMap.get(String(a.id)) ?? gsoArticleLeadIndex(a.article);
        const nb = effMap.get(String(b.id)) ?? gsoArticleLeadIndex(b.article);
        if (na != null && nb != null && na !== nb) return na - nb;
        if (na != null && nb == null) return -1;
        if (na == null && nb != null) return 1;
        return (parseInt(a.id, 10) || 0) - (parseInt(b.id, 10) || 0);
    });
}

function gsoVarianceQtyCell(item) {
    const v = gsoVarianceQty(item);
    if (v === 0) return '—';
    return (v > 0 ? '+' + v : String(v));
}

function gsoVarianceValueCell(item) {
    if (gsoVarianceQty(item) === 0) return '—';
    return formatCurrency(gsoVarianceValue(item));
}

function gsoBookLineTotal(item) {
    return (parseFloat(item.unit_value) || 0) * gsoBookQty(item);
}

function gsoGroupKey(item) {
    const g = (item.item_group || '').trim();
    return g === '' ? '__ungrouped__' : g;
}

/** Red section header + subtotal only when a report section (item_group) is set. */
function gsoIsExplicitSectionSegment(seg) {
    const k = seg.key || '';
    if (k.includes('||')) {
        return !k.endsWith('||__ungrouped__');
    }
    const pipeParts = k.split('|');
    if (pipeParts.length > 1) {
        return pipeParts[pipeParts.length - 1] !== '__ungrouped__';
    }
    return k !== '__ungrouped__';
}

/** Lowest 1. / 2. article index in a section so Fixtures (1.) stay above Equipment (4.) and IT (5.). */
function gsoSectionLeadRank(items) {
    let minArt = Infinity;
    let minId = Infinity;
    for (const it of items || []) {
        const n = gsoArticleLeadIndex(it.article);
        if (n != null && n < minArt) minArt = n;
        const id = parseInt(it.id, 10);
        if (Number.isFinite(id) && id < minId) minId = id;
    }
    if (minArt !== Infinity) return minArt;
    return minId !== Infinity ? minId + 10000 : 99999;
}

function gsoOrderedGroupKeys(order, map) {
    return order.slice().sort((ka, kb) => {
        const ra = gsoSectionLeadRank(map.get(ka));
        const rb = gsoSectionLeadRank(map.get(kb));
        if (ra !== rb) return ra - rb;
        return order.indexOf(ka) - order.indexOf(kb);
    });
}

function segmentItemsByGroup(items) {
    const order = [];
    const map = new Map();
    for (const item of items) {
        const k = gsoGroupKey(item);
        if (!map.has(k)) {
            map.set(k, []);
            order.push(k);
        }
        map.get(k).push(item);
    }
    return gsoOrderedGroupKeys(order, map).map(k => ({
        key: k,
        title: k === '__ungrouped__' ? 'OTHER ITEMS' : k,
        items: gsoSortItemsByArticleNumber(map.get(k))
    }));
}

function segmentItemsByCategoryAndGroup(items) {
    const order = [];
    const map = new Map();
    for (const item of items) {
        const k = `${item.category}||${gsoGroupKey(item)}`;
        if (!map.has(k)) {
            map.set(k, []);
            order.push(k);
        }
        map.get(k).push(item);
    }
    return gsoOrderedGroupKeys(order, map).map(k => {
        const [cat, gk] = k.split('||');
        const gLabel = gk === '__ungrouped__' ? 'OTHER ITEMS' : gk;
        return {
            key: k,
            title: `${cat} · ${gLabel}`,
            category: cat,
            items: gsoSortItemsByArticleNumber(map.get(k))
        };
    });
}

function segmentItemsForAllTable(items) {
    const out = [];
    let bucket = null;
    let curOffice = null;
    let curGroup = null;
    for (const item of items) {
        const oid = item.office_id;
        const gk = gsoGroupKey(item);
        if (!bucket || curOffice !== oid || curGroup !== gk) {
            curOffice = oid;
            curGroup = gk;
            const gLabel = gk === '__ungrouped__' ? 'OTHER ITEMS' : gk;
            bucket = {
                title: `${item.office_name || 'Office'} · ${item.category} · ${gLabel}`,
                items: [],
                key: `${oid}|${gk}`
            };
            out.push(bucket);
        }
        bucket.items.push(item);
    }
    return out.map(bucket => ({
        ...bucket,
        items: gsoSortItemsByArticleNumber(bucket.items)
    }));
}

/** Data columns in gso row: 4 label cols + Unit Value + rest (matches thead / item rows). */
function gsoTableColCount(showOffice, withDrag, withTag) {
    const bodyCols = showOffice ? 11 : 10;
    return bodyCols + (withDrag ? 1 : 0) + (withTag ? 2 : 0);
}

/** Section title spans Article–U/M; book subtotal sits in the Unit Value column (paper form layout). */
function gsoSectionHeaderRow(showOffice, withDrag, title, bookTotal, withTag) {
    const safeTitle = escAttr(title);
    const sub = formatCurrency(bookTotal);
    let html = `<tr class="gso-section-head">`;
    if (withTag) {
        html += `<td class="ptag-check-col ptag-row-act"></td>`;
    }
    if (withDrag) {
        html += `<td class="gso-drag-col"></td>`;
    }
    html += `<td colspan="5" class="gso-section-title">${safeTitle}</td>`;
    html += `<td class="gso-section-subtotal" style="text-align:right">${sub}</td>`;
    const restAfterUnitValue = (showOffice ? 6 : 5) + (withTag ? 1 : 0);
    for (let i = 0; i < restAfterUnitValue; i++) {
        html += '<td></td>';
    }
    html += `</tr>`;
    return html;
}

function gsoTableHeadHtml(showOffice, withDrag, withTag) {
    const tagCheckTh = withTag
        ? '<th rowspan="2" class="ptag-check-col" title="Select for tag print"><input type="checkbox" class="ptag-check-all" onclick="event.stopPropagation();ptagToggleOfficeAll(this)" aria-label="Select all items in this office"/></th>'
        : '';
    const tagViewTh = withTag ? '<th rowspan="2" class="ptag-view-col">Tag</th>' : '';
    const dragTh = withDrag ? '<th rowspan="2" class="gso-drag-col" title="Reorder"> </th>' : '';
    const officeTh = showOffice ? '<th rowspan="2">Office</th>' : '';
    return `<thead>
      <tr>
        ${tagCheckTh}
        ${dragTh}
        <th rowspan="2">Article</th>
        <th rowspan="2">Description</th>
        <th rowspan="2">Property No.</th>
        <th rowspan="2">Status</th>
        <th rowspan="2">Unit of Measure</th>
        <th rowspan="2" style="text-align:right">Unit Value</th>
        <th rowspan="2" style="text-align:center">Qty (Property Card)</th>
        <th rowspan="2" style="text-align:center">Qty (Physical Count)</th>
        <th colspan="2" style="text-align:center">Shortage / Overage</th>
        ${officeTh}
        <th rowspan="2">Remarks</th>
        ${tagViewTh}
      </tr>
      <tr>
        <th style="text-align:center">Quantity</th>
        <th style="text-align:right">Value</th>
      </tr>
    </thead>`;
}

function gsoItemRowHtml(item, showOffice, withDrag, articleOverride, withTag) {
    const officeTd = showOffice ? `<td>${item.office_name || '—'}</td>` : '';
    const rem = (item.remarks || '').trim() || '—';
    const uom = (item.unit_of_measure || 'pc').trim();
    const isPpeRow = String(item.category || '').toUpperCase() === 'PPE';
    const noPhys = item.quantity_physical_count === null || item.quantity_physical_count === undefined || item.quantity_physical_count === '';
    const physTitle = (!isPpeRow && noPhys)
        ? ' title="Physical count not recorded separately; treated as same as property card qty"'
        : '';
    const physQtyDisplay = isPpeRow && noPhys ? '—' : String(gsoPhysicalQty(item));
    const varQtyDisplay = isPpeRow && noPhys ? '—' : gsoVarianceQtyCell(item);
    const varValDisplay = isPpeRow && noPhys ? '—' : gsoVarianceValueCell(item);
    const dragTd = withDrag
        ? `<td class="gso-drag-col" onclick="event.stopPropagation()"><span class="gso-drag-handle" draggable="true" title="Drag to reorder rows">⠿</span></td>`
        : '';
    const tagCheckTd = withTag
        ? `<td class="ptag-check-col ptag-row-act" onclick="event.stopPropagation()"><input type="checkbox" class="ptag-check" data-item-id="${item.id}" onclick="event.stopPropagation();ptagToggleRow(this)" aria-label="Select for tag print"/></td>`
        : '';
    const tagViewTd = withTag
        ? `<td class="ptag-view-col ptag-row-act" onclick="event.stopPropagation()"><button type="button" class="btn btn-ghost btn-sm ptag-view-btn" onclick="event.stopPropagation();openPropertyTag(${item.id})">View</button></td>`
        : '';
    let articleTd;
    if (articleOverride !== undefined) {
        articleTd = articleOverride === '' ? '' : escAttr(articleOverride);
    } else {
        articleTd = escAttr(gsoDisplayArticle(item));
    }
    return `
      <tr data-item-id="${item.id}">
        ${tagCheckTd}
        ${dragTd}
        <td class="gso-art-cell">${articleTd}</td>
        <td>${item.description || '—'}</td>
        <td class="mono">${item.property_number}</td>
        <td><span class="badge b-${String(item.status || 'Active').toLowerCase()}">${item.status || 'Active'}</span></td>
        <td>${uom}</td>
        <td style="text-align:right">${formatItemUnitValue(item)}</td>
        <td style="text-align:center">${gsoBookQty(item)}</td>
        <td style="text-align:center"${physTitle}>${physQtyDisplay}</td>
        <td style="text-align:center">${varQtyDisplay}</td>
        <td style="text-align:right">${varValDisplay}</td>
        ${officeTd}
        <td>${rem}</td>
        ${tagViewTd}
      </tr>`;
}

function renderGsoTableShell(showOffice, withDrag, tbodiesInnerHtml, withTag) {
    return `<table class="gso-inventory-table">${gsoTableHeadHtml(showOffice, withDrag, withTag)}${tbodiesInnerHtml}</table>`;
}

function renderOfficeItemsTable(items, category, officeId, disableDrag, mixedCategory) {
    if (!items || items.length === 0) {
        return '<p style="padding:10px;color:var(--text3)">No items in this office</p>';
    }
    const withDrag = !!(userPermissions.canEdit && !disableDrag);
    const showOffice = false;
    const catUpper = String(category || '').toUpperCase();
    const withTag = !mixedCategory && catUpper === 'SE';
    const segs = mixedCategory ? segmentItemsByCategoryAndGroup(items) : segmentItemsByGroup(items);
    let tbodies = '';
    for (const seg of segs) {
        const sub = seg.items.reduce((s, i) => s + gsoBookLineTotal(i), 0);
        const catForDnd = seg.category || category;
        const dnd = withDrag
            ? ` data-gso-dnd="1" data-gso-office="${officeId}" data-gso-cat="${catForDnd}" data-gso-group-key="${encodeURIComponent(seg.key)}"`
            : '';
        const mergeArticles = String(seg.category || catUpper).toUpperCase() === 'SE';
        let runningArticleKey = '';
        tbodies += `<tbody${dnd}>`;
        if (gsoIsExplicitSectionSegment(seg)) {
            tbodies += gsoSectionHeaderRow(showOffice, withDrag, seg.title, sub, withTag);
        }
        for (const it of seg.items) {
            let artOverride;
            if (mergeArticles) {
                const cur = (it.article || '').trim();
                if (cur) {
                    if (cur !== runningArticleKey) {
                        artOverride = gsoDisplayArticle(it);
                        runningArticleKey = cur;
                    } else {
                        artOverride = '';
                    }
                } else {
                    artOverride = '';
                }
                tbodies += gsoItemRowHtml(it, showOffice, withDrag, artOverride, withTag);
            } else {
                tbodies += gsoItemRowHtml(it, showOffice, withDrag, undefined, withTag);
            }
        }
        tbodies += '</tbody>';
    }
    return `<div class="gso-table-wrap" data-gso-root="1">${renderGsoTableShell(showOffice, withDrag, tbodies, withTag)}</div>`;
}

async function persistGsoSectionOrder(tbody) {
    const ids = [...tbody.querySelectorAll('tr[data-item-id]')].map(tr => parseInt(tr.dataset.itemId, 10));
    if (ids.length < 1) return;
    const result = await apiPost('reorder-items.php', { ids });
    if (result.success) {
        showToast('Order saved');
    } else {
        showToast(result.message || 'Could not save order', 'error');
        loadPageData(currentPage);
    }
}

function bindGsoTableRoot(wrapEl) {
    if (!wrapEl) return;
    wrapEl.querySelectorAll('tbody[data-gso-dnd]').forEach(tbody => {
        tbody.addEventListener('click', (e) => {
            const tr = e.target.closest('tr[data-item-id]');
            if (!tr || e.target.closest('.gso-drag-handle, .ptag-row-act')) return;
            viewItemDetail(parseInt(tr.dataset.itemId, 10));
        });
        tbody.querySelectorAll('.gso-drag-handle').forEach(handle => {
            const tr = handle.closest('tr[data-item-id]');
            if (!tr) return;
            handle.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', tr.dataset.itemId);
                e.dataTransfer.effectAllowed = 'move';
                tr.classList.add('gso-row-dragging');
                e.stopPropagation();
            });
            handle.addEventListener('dragend', () => tr.classList.remove('gso-row-dragging'));
        });
        tbody.querySelectorAll('tr[data-item-id]').forEach(tr => {
            tr.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            tr.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const fromId = e.dataTransfer.getData('text/plain');
                const fromRow = tbody.querySelector('tr[data-item-id="' + String(fromId) + '"]');
                if (!fromRow || fromRow === tr) return;
                const rect = tr.getBoundingClientRect();
                const before = e.clientY < rect.top + rect.height / 2;
                if (before) {
                    tbody.insertBefore(fromRow, tr);
                } else {
                    tbody.insertBefore(fromRow, tr.nextSibling);
                }
                persistGsoSectionOrder(tbody);
            });
        });
    });

    wrapEl.querySelectorAll('tbody:not([data-gso-dnd])').forEach(tbody => {
        tbody.addEventListener('click', (e) => {
            const tr = e.target.closest('tr[data-item-id]');
            if (!tr || e.target.closest('.gso-section-head, .ptag-row-act')) return;
            viewItemDetail(parseInt(tr.dataset.itemId, 10));
        });
    });
}

function wireGsoTablesIn(container) {
    if (!container) return;
    container.querySelectorAll('[data-gso-root="1"]').forEach(bindGsoTableRoot);
    if (typeof window.__ptagSyncChecks === 'function') window.__ptagSyncChecks();
}

function setTodayDate() {
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = ['e-date', 'iss-date', 'ret-date', 'uns-date'];
    dateInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input && !input.value) input.value = today;
    });
}

// Safe read for encode modal (handles missing nodes + legacy field ids)
function encodeFieldValue(id, legacyIds = []) {
    let el = document.getElementById(id);
    if (!el && legacyIds.length) {
        for (const lid of legacyIds) {
            el = document.getElementById(lid);
            if (el) break;
        }
    }
    return el ? String(el.value ?? '') : '';
}

// Live classification for encode modal
function liveClassify() {
    const amtEl = document.getElementById('e-amt');
    const offEl = document.getElementById('e-off');
    const raw = amtEl ? lgueMoneyPlain(amtEl.value) : '';
    const parsed = raw === '' ? 0 : parseFloat(raw);
    const amount = Number.isFinite(parsed) ? parsed : NaN;
    const office = offEl ? offEl.value : '';
    const classifyBox = document.getElementById('e-classify');
    const propNum = document.getElementById('e-propnum');
    const year = new Date().getFullYear();
    const ppeMin = getPpeThreshold();
    const category = (Number.isFinite(amount) && amount >= ppeMin) ? 'PPE' : 'SE';
    const ppeLabel = ppeMin.toLocaleString('en-PH', { maximumFractionDigits: 0 });

    if (classifyBox) {
        if (raw !== '' && !Number.isFinite(amount)) {
            classifyBox.innerHTML = '<span class="badge b-se">SE</span> <span style="color:var(--text3)">Enter a valid amount or leave blank for SE</span>';
        } else if (Number.isFinite(amount) && amount >= ppeMin) {
            classifyBox.innerHTML = `<span class="badge b-ppe">PPE</span> Property, Plant & Equipment (₱${ppeLabel} & above)`;
        } else {
            classifyBox.innerHTML = `<span class="badge b-se">SE</span> Semi-Expendable (below ₱${ppeLabel}, blank, or ₱0)`;
        }
    }
    if (propNum) {
        propNum.textContent = office ? `${year}-${category}-XXXXXX (preview)` : '—';
    }
}

// Save new item
async function saveItem() {
    // Check permission
    if (!checkPermission('create')) {
        return;
    }

    const qtyPhysRaw = encodeFieldValue('e-qty-phys').trim();
    const qtyPhysical = qtyPhysRaw === '' ? null : parseInt(qtyPhysRaw, 10);

    let article = encodeFieldValue('e-article').trim();
    let description = encodeFieldValue('e-desc', ['e-name']).trim();
    const artEl = document.getElementById('e-article');
    const descEl = document.getElementById('e-desc') || document.getElementById('e-name');
    if (typeof lgueMarkField === 'function') {
        lgueMarkField(artEl, false);
        lgueMarkField(descEl, false);
    }
    if (!description && article) {
        description = article;
    }
    const itemText = description || article;
    const itemChk = lgueRequireText(itemText, 'Article or description', { minLen: 3 });
    if (!itemChk.ok) {
        if (typeof lgueMarkField === 'function') lgueMarkField(descEl || artEl, true);
        showToast(itemChk.message, 'error');
        return;
    }
    description = itemChk.value;

    const unitChk = lgueRequireUnit(encodeFieldValue('e-uom', []));
    if (!unitChk.ok) {
        showToast(unitChk.message, 'error');
        return;
    }
    const unit_of_measure = unitChk.value;
    const amtRaw = encodeFieldValue('e-amt').trim();
    const amtEl = document.getElementById('e-amt');
    if (typeof lgueMarkField === 'function') lgueMarkField(amtEl, false);
    let unit_value = 0;
    if (isDonationUnitValue(amtRaw)) {
        unit_value = 'DONATION';
    } else if (amtRaw !== '') {
        const amtChk = lgueParseNumber(amtRaw, 'Unit value', { min: 0 });
        if (!amtChk.ok) {
            if (typeof lgueMarkField === 'function') lgueMarkField(amtEl, true);
            showToast(amtChk.message, 'error');
            return;
        }
        unit_value = amtChk.value;
    }

    let qtyPcStr = encodeFieldValue('e-qty-pc', ['e-qty']).trim();
    const qtyEl = document.getElementById('e-qty-pc') || document.getElementById('e-qty');
    if (typeof lgueMarkField === 'function') lgueMarkField(qtyEl, false);
    if (qtyPcStr === '') qtyPcStr = '1';
    const qtyChk = lgueParseNumber(qtyPcStr, 'Quantity', { integer: true, min: 1 });
    if (!qtyChk.ok) {
        if (typeof lgueMarkField === 'function') lgueMarkField(qtyEl, true);
        showToast(qtyChk.message, 'error');
        return;
    }
    const quantity = qtyChk.value;

    const officeStr = encodeFieldValue('e-off');
    const office_id = parseInt(officeStr, 10);

    const acquisition_date = encodeFieldValue('e-date');
    const remarksChk = lgueOptionalText(encodeFieldValue('e-remarks'), 'Remarks');
    if (!remarksChk.ok) {
        showToast(remarksChk.message, 'error');
        return;
    }
    const remarks = remarksChk.value;
    const item_group = encodeFieldValue('e-group').trim();

    const data = {
        article,
        description,
        unit_of_measure,
        unit_value,
        quantity,
        quantity_physical_count: qtyPhysical,
        office_id,
        acquisition_date,
        remarks,
        item_group: item_group || ''
    };

    if (!office_id || office_id <= 0 || isNaN(office_id)) {
        showToast('Please select an office to assign this item', 'error');
        return;
    }
    if (qtyPhysRaw !== '') {
        const physChk = lgueParseNumber(qtyPhysRaw, 'Physical count', { integer: true, min: 0 });
        if (!physChk.ok) {
            showToast(physChk.message, 'error');
            return;
        }
    }

    const result = await runBusy('encode-save-btn', () => apiPost('items.php', data));
    if (!result) return;
    if (result.success) {
        showToast('Item encoded successfully');
        closeModal('encode-modal');
        refreshAfterWrite();
        // Reset form (only clear nodes that exist)
        const resetIds = [
            ['e-article', ''],
            ['e-desc', ''],
            ['e-name', ''],
            ['e-uom', 'pc'],
            ['e-amt', ''],
            ['e-qty-pc', '1'],
            ['e-qty', '1'],
            ['e-qty-phys', ''],
            ['e-off', ''],
            ['e-remarks', ''],
            ['e-group', '']
        ];
        resetIds.forEach(([id, def]) => {
            const node = document.getElementById(id);
            if (node) node.value = def;
        });
        liveClassify();
    } else {
        if (result.error) {
            console.error('Encode save failed:', result.error);
        }
        showToast(result.message || 'Failed to save item', 'error');
    }
}

// Load offices page
async function loadOfficesPage() {
    const result = await apiGet('offices.php');
    if (result.success) {
        renderOfficeGrid(result.data);
    }
}

function renderOfficeGrid(offices) {
    const grid = document.getElementById('office-grid');
    if (!offices || offices.length === 0) {
        grid.innerHTML = '<div class="empty"><div class="empty-icon">🏢</div><p>No offices found</p></div>';
        return;
    }
    
    grid.innerHTML = offices.map(office => `
        <div class="office-card" onclick="viewOfficeItems(${office.id})">
            <div>
                <div class="oc-name">${office.name}</div>
                <div class="oc-stats">${office.total_items || 0} items</div>
            </div>
            <div class="oc-count">${office.total_items || 0}</div>
        </div>
    `).join('');
}

async function viewOfficeItems(officeId) {
    const result = await apiGet(`items.php?office_id=${officeId}`);
    if (result.success) {
        const detailDiv = document.getElementById('office-detail');
        if (result.data.length === 0) {
            detailDiv.innerHTML = '<div class="card"><div class="empty"><div class="empty-icon">📦</div><p>No items in this office</p></div></div>';
            return;
        }
        
        detailDiv.innerHTML = `
            <div class="card">
                <div class="card-hd"><span class="card-ttl">Office Items (GSO format)</span></div>
                ${renderOfficeItemsTable(result.data, 'SE', officeId, false, true)}
            </div>
        `;
        wireGsoTablesIn(detailDiv);
    }
}

// Load all items
async function loadAllItems() {
    const search = document.getElementById('s-all')?.value || '';
    const category = document.getElementById('f-cat')?.value || '';
    const status = document.getElementById('f-status')?.value || '';
    const officeId = document.getElementById('f-office-all')?.value || '';
    
    let url = 'items.php?';
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (category) url += `category=${category}&`;
    if (status) url += `status=${status}&`;
    if (officeId) url += `office_id=${officeId}&`;
    
    const result = await apiGet(url);
    if (result.success) {
        renderItemsTable('tbl-all', result.data);
    }
}

// Load semi-expendable items with office grouping
async function loadSemiExpendable() {
    const result = await apiGetItems('category=SE');
    if (result.success) {
        // Group items by office
        seItemsByOffice = {};
        result.data.forEach(item => {
            const officeId = item.office_id;
            if (!seItemsByOffice[officeId]) {
                seItemsByOffice[officeId] = {
                    office_name: item.office_name,
                    office_code: item.office_code,
                    items: []
                };
            }
            seItemsByOffice[officeId].items.push(item);
        });
        
        if (seViewSelectedOpen && seDraftSelectedIds().length) {
            renderSEViewSelected();
        } else {
            seViewSelectedOpen = false;
            const search = document.getElementById('s-se')?.value || '';
            const status = document.getElementById('f-status-se')?.value || '';
            const officeFilter = document.getElementById('f-office-se')?.value || '';
            if (seDrillOfficeId && seItemsByOffice[seDrillOfficeId]) {
                openSEOfficeDetail(seDrillOfficeId);
            } else if (search || status || officeFilter) {
                filterSEItems();
            } else {
                renderSEOfficeAccordion();
            }
            refreshSEViewSelectedChrome();
        }
    }
}

function invDeptIconClass(code, name) {
    const c = String(code || '').trim().toUpperCase();
    const n = String(name || '').toLowerCase();
    const byCode = {
        MAYOR: 'fa-landmark',
        VMAY: 'fa-gavel',
        ACCT: 'fa-calculator',
        ADMIN: 'fa-building',
        AGRI: 'fa-seedling',
        ASSO: 'fa-map-location-dot',
        BUDG: 'fa-coins',
        COA: 'fa-scale-balanced',
        DEPED: 'fa-graduation-cap',
        DILG: 'fa-flag',
        MDRRMO: 'fa-truck-medical',
        ENGR: 'fa-helmet-safety',
        ENRO: 'fa-leaf',
        GSO: 'fa-boxes-stacked',
        HLTH: 'fa-heart-pulse',
        HRMO: 'fa-users',
        LICENSE: 'fa-id-card',
        MARKET: 'fa-store',
        LWS: 'fa-droplet',
        MCR: 'fa-book',
        PDAO: 'fa-wheelchair',
        SENIOR: 'fa-person-cane',
        OTP: 'fa-basket-shopping',
        PLAN: 'fa-map',
        PESO: 'fa-briefcase',
        SWDO: 'fa-hand-holding-heart',
        MSWDO: 'fa-hand-holding-heart',
        TRES: 'fa-money-bill-wave',
    };
    if (byCode[c]) return byCode[c];

    if (n.includes('mayor') && n.includes('vice')) return 'fa-gavel';
    if (n.includes('mayor')) return 'fa-landmark';
    if (n.includes('account')) return 'fa-calculator';
    if (n.includes('budget')) return 'fa-coins';
    if (n.includes('treasur')) return 'fa-money-bill-wave';
    if (n.includes('engineer')) return 'fa-helmet-safety';
    if (n.includes('health')) return 'fa-heart-pulse';
    if (n.includes('social welfare') || n.includes('welfare') || n.includes('mswdo') || n.includes('swdo')) return 'fa-hand-holding-heart';
    if (n.includes('agricultur')) return 'fa-seedling';
    if (n.includes('environment') || n.includes('enro')) return 'fa-leaf';
    if (n.includes('disaster') || n.includes('mdrrmo')) return 'fa-truck-medical';
    if (n.includes('education') || n.includes('deped')) return 'fa-graduation-cap';
    if (n.includes('planning') || n.includes('mpdc')) return 'fa-map';
    if (n.includes('assessor')) return 'fa-map-location-dot';
    if (n.includes('civil registrar') || n.includes('mcr')) return 'fa-book';
    if (n.includes('human resource') || n.includes('hrmo')) return 'fa-users';
    if (n.includes('license') || n.includes('permit')) return 'fa-id-card';
    if (n.includes('market')) return 'fa-store';
    if (n.includes('water')) return 'fa-droplet';
    if (n.includes('peso') || n.includes('employment')) return 'fa-briefcase';
    if (n.includes('senior')) return 'fa-person-cane';
    if (n.includes('disability') || n.includes('pdao') || n.includes('pwd')) return 'fa-wheelchair';
    if (n.includes('organic') || n.includes('trading')) return 'fa-basket-shopping';
    if (n.includes('audit') || n.includes('coa')) return 'fa-scale-balanced';
    if (n.includes('dilg') || n.includes('interior')) return 'fa-flag';
    if (n.includes('general services') || n.includes('gso')) return 'fa-boxes-stacked';
    if (n.includes('admin')) return 'fa-building';
    if (n.includes('land title')) return 'fa-file-contract';
    if (n.includes('school')) return 'fa-school';
    if (n.includes('land')) return 'fa-mountain-sun';
    if (n.includes('supplies') || n.includes('equipment')) return 'fa-laptop';
    return 'fa-building';
}

function invOfficeCardHtml(opts) {
    const {
        onclick,
        title,
        code = '',
        meta = '',
        iconCode = '',
    } = opts;
    const icon = invDeptIconClass(iconCode || code, title);
    const codeHtml = code
        ? `<span class="inv-office-card-code">${escapeHtml(code)}</span>`
        : '';
    return `
        <button type="button" class="inv-office-card" onclick="${onclick}">
            <span class="inv-office-card-ico" aria-hidden="true"><i class="fas ${icon}"></i></span>
            <span class="inv-office-card-body">
                <span class="inv-office-card-top">
                    <span class="inv-office-card-name">${escapeHtml(title || 'Unknown')}</span>
                    ${codeHtml}
                </span>
                <span class="inv-office-card-meta">${escapeHtml(meta)}</span>
                <span class="inv-office-card-go">Open inventory <span aria-hidden="true">→</span></span>
            </span>
        </button>`;
}

/** Hide Semi/PPE page header while a department/tab inventory is open. */
function setInvDrillChrome(page, open) {
    const el = document.getElementById(page === 'ppe' ? 'pg-ppe' : 'pg-semi');
    if (!el) return;
    el.classList.toggle('inv-drill-open', !!open);
}

function invSyncFilterFromDetail(kind, field, value) {
    if (kind === 'se') {
        if (field === 'search') {
            const el = document.getElementById('s-se');
            if (el) el.value = value;
            filterSEItems();
            return;
        }
        if (field === 'status') {
            const el = document.getElementById('f-status-se');
            if (el) el.value = value;
            filterSEItems();
        }
        return;
    }
    if (field === 'search') {
        const el = document.getElementById('s-ppe');
        if (el) el.value = value;
        filterPPEItems();
        return;
    }
    if (field === 'status') {
        const el = document.getElementById('f-status-ppe');
        if (el) el.value = value;
        filterPPEItems();
    }
}

function invStatusOptionsHtml(selected) {
    const opts = [
        ['', 'All statuses'],
        ['Active', 'Active'],
        ['Issued', 'Issued'],
        ['Unserviceable', 'Unserviceable'],
        ['Disposed', 'Disposed'],
    ];
    return opts.map(([v, label]) =>
        `<option value="${escapeHtml(v)}"${String(selected) === String(v) ? ' selected' : ''}>${escapeHtml(label)}</option>`
    ).join('');
}

function invOfficeDetailToolsHtml(kind, ctx = {}) {
    const searchVal = escapeHtml(
        kind === 'se'
            ? (document.getElementById('s-se')?.value || '')
            : (document.getElementById('s-ppe')?.value || '')
    );
    const statusVal = kind === 'se'
        ? (document.getElementById('f-status-se')?.value || '')
        : (document.getElementById('f-status-ppe')?.value || '');

    let actionsHtml = '';
    if (userPermissions.canPrint) {
        if (kind === 'se') {
            const oid = Number(ctx.officeId) || 0;
            actionsHtml = `
                <div class="inv-office-detail-actions" role="group" aria-label="Print actions">
                    <span class="ptag-selected-count" id="ptag-selected-count-detail" aria-live="polite"></span>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="printPropertyTags('a4')" title="Print selected tags, 6 per Letter sheet"><i class="fas fa-tags" aria-hidden="true"></i> A4 Tag</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="printPropertyTags('long')" title="Print selected tags, 8 per long/Legal sheet"><i class="fas fa-tags" aria-hidden="true"></i> Long Tag</button>
                    <button type="button" class="btn btn-dark btn-sm" onclick="openPhysicalCountReport('SE', ${oid})" title="Print physical count for this office"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Physical count</button>
                </div>`;
        } else {
            const tabName = String(ctx.tabName || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
            actionsHtml = `
                <div class="inv-office-detail-actions" role="group" aria-label="Print actions">
                    <button type="button" class="btn btn-dark btn-sm" onclick="openPhysicalCountReport('PPE', '${tabName}')" title="Print physical count for this tab"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Physical count</button>
                </div>`;
        }
    }

    return `
        <div class="inv-office-detail-tools">
            <div class="inv-office-detail-filters">
                <label class="inv-office-detail-search">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input class="search-inp" type="search" placeholder="Search item, property #…" value="${searchVal}"
                        oninput="invSyncFilterFromDetail('${kind}', 'search', this.value)"
                        autocomplete="off" aria-label="Search inventory"/>
                </label>
                <select class="filter-sel" aria-label="Filter by status"
                    onchange="invSyncFilterFromDetail('${kind}', 'status', this.value)">
                    ${invStatusOptionsHtml(statusVal)}
                </select>
            </div>
            ${actionsHtml}
        </div>`;
}

function invOfficeDetailBarHtml(opts) {
    const {
        backOnclick,
        backLabel,
        iconClass,
        title,
        meta,
        toolsHtml,
    } = opts;
    return `
        <div class="inv-office-detail-bar">
            <div class="inv-office-detail-top">
                <div class="inv-office-detail-title">
                    <strong><span class="inv-office-detail-ico" aria-hidden="true"><i class="fas ${iconClass}"></i></span>${escapeHtml(title)}</strong>
                    <span class="inv-office-detail-meta">${meta}</span>
                </div>
                <button type="button" class="btn btn-ghost btn-sm inv-back-btn" onclick="${backOnclick}">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> ${escapeHtml(backLabel)}
                </button>
            </div>
            ${toolsHtml}
        </div>`;
}

function renderSEOfficeAccordion() {
    seDrillOfficeId = null;
    setInvDrillChrome('semi', false);
    const container = document.getElementById('se-offices-container');
    const ttl = document.querySelector('#pg-semi .card-ttl');
    if (ttl) ttl.textContent = 'Semi-Expendable by Office';

    const ids = Object.keys(seItemsByOffice).sort((a, b) =>
        String(seItemsByOffice[a].office_name || '').localeCompare(String(seItemsByOffice[b].office_name || ''), undefined, { sensitivity: 'base' })
    );

    if (ids.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No semi-expendable items found</p></div>';
        refreshSEViewSelectedChrome();
        return;
    }

    const cards = ids.map((officeId) => {
        const office = seItemsByOffice[officeId];
        const n = office.items.length;
        return invOfficeCardHtml({
            onclick: `openSEOfficeDetail(${Number(officeId)})`,
            title: office.office_name || 'Unknown Office',
            code: office.office_code || '',
            meta: `${n} item${n !== 1 ? 's' : ''}`,
        });
    }).join('');

    container.innerHTML = `<div class="inv-office-grid">${cards}</div>`;
    refreshSEViewSelectedChrome();
}

function openSEOfficeDetail(officeId, itemsOverride) {
    const office = seItemsByOffice[officeId];
    const container = document.getElementById('se-offices-container');
    if (!office || !container) return;

    seDrillOfficeId = officeId;
    setInvDrillChrome('semi', true);
    const items = itemsOverride || office.items || [];
    const n = items.length;
    const ttl = document.querySelector('#pg-semi .card-ttl');
    if (ttl) ttl.textContent = office.office_name || 'Department inventory';

    container.innerHTML = `
        <div class="inv-office-detail">
            ${invOfficeDetailBarHtml({
                backOnclick: 'closeSEOfficeDetail()',
                backLabel: 'Back to departments',
                iconClass: invDeptIconClass(office.office_code, office.office_name),
                title: office.office_name || 'Unknown Office',
                meta: `${n} item${n !== 1 ? 's' : ''}${office.office_code ? ' · ' + escapeHtml(office.office_code) : ''}`,
                toolsHtml: invOfficeDetailToolsHtml('se', { officeId }),
            })}
            <div class="inv-office-detail-body" id="se-office-detail-body">
                ${n ? renderOfficeItemsTable(items, 'se', officeId, false) : '<div class="empty"><p>No items in this department for the current filters.</p></div>'}
            </div>
        </div>`;
    if (n) wireGsoTablesIn(container);
    refreshSEViewSelectedChrome();
    if (typeof window.__ptagSyncChecks === 'function') window.__ptagSyncChecks();
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeSEOfficeDetail() {
    seDrillOfficeId = null;
    setInvDrillChrome('semi', false);
    const officeFilter = document.getElementById('f-office-se');
    if (officeFilter) officeFilter.value = '';
    const search = document.getElementById('s-se')?.value || '';
    const status = document.getElementById('f-status-se')?.value || '';
    if (search || status) {
        filterSEItems();
    } else {
        renderSEOfficeAccordion();
    }
}

function fillOfficeAccordion(category, key, itemsDiv, itemsOverride) {
    if (category === 'se') {
        const items = itemsOverride || (seItemsByOffice[key] && seItemsByOffice[key].items) || [];
        itemsDiv.innerHTML = renderOfficeItemsTable(items, 'se', key, false);
    } else {
        const tab = ppeItemsByTab[key];
        const items = itemsOverride || (tab && tab.items) || [];
        const officeId = items[0] ? items[0].office_id : 0;
        itemsDiv.innerHTML = renderOfficeItemsTable(items, 'ppe', officeId, false);
    }
    wireGsoTablesIn(itemsDiv);
}

/** Legacy name kept for any leftover callers — now opens the department/tab detail page. */
function toggleOfficeItems(category, officeId) {
    if (category === 'se') {
        openSEOfficeDetail(officeId);
    } else {
        openPPETabDetail(officeId);
    }
}

function filterSEItems() {
    if (seViewSelectedOpen && seDraftSelectedIds().length) {
        renderSEViewSelected();
        return;
    }
    const search = document.getElementById('s-se')?.value.toLowerCase() || '';
    const status = document.getElementById('f-status-se')?.value || '';
    const officeFilter = document.getElementById('f-office-se')?.value || '';

    // If a single department is chosen from the dropdown, open its inventory page.
    if (officeFilter && !search && !status) {
        openSEOfficeDetail(officeFilter);
        return;
    }

    // Stay inside an open department and filter its table only.
    if (seDrillOfficeId != null && seItemsByOffice[seDrillOfficeId]) {
        const office = seItemsByOffice[seDrillOfficeId];
        if (officeFilter && String(seDrillOfficeId) !== String(officeFilter)) {
            openSEOfficeDetail(officeFilter);
            return;
        }
        const filtered = office.items.filter((item) => {
            const matchesSearch = !search
                || lgueItemMatchesSearch(item, search, office.office_name)
                || (!lgueLooksPropertyNoQuery(search) && lgueTextMatchesQuery(office.office_name || '', search));
            const matchesStatus = !status || item.status === status;
            return matchesSearch && matchesStatus;
        });
        openSEOfficeDetail(seDrillOfficeId, filtered);
        return;
    }

    const container = document.getElementById('se-offices-container');
    const ttl = document.querySelector('#pg-semi .card-ttl');
    if (ttl) ttl.textContent = 'Semi-Expendable by Office';

    let cards = '';
    let hasResults = false;

    for (const officeId in seItemsByOffice) {
        const office = seItemsByOffice[officeId];
        if (officeFilter && String(officeId) !== String(officeFilter)) {
            continue;
        }

        const filteredItems = office.items.filter((item) => {
            const matchesSearch = !search
                || lgueItemMatchesSearch(item, search, office.office_name)
                || (!lgueLooksPropertyNoQuery(search) && lgueTextMatchesQuery(office.office_name || '', search));
            const matchesStatus = !status || item.status === status;
            return matchesSearch && matchesStatus;
        });

        if (filteredItems.length > 0) {
            hasResults = true;
            cards += invOfficeCardHtml({
                onclick: `openSEOfficeDetail(${Number(officeId)})`,
                title: office.office_name || 'Unknown Office',
                code: office.office_code || '',
                meta: `${filteredItems.length} match${filteredItems.length !== 1 ? 'es' : ''}`,
            });
        }
    }

    if (!hasResults) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">🔍</div><p>No items match your search</p></div>';
    } else {
        container.innerHTML = `<div class="inv-office-grid">${cards}</div>`;
    }
    setInvDrillChrome('semi', false);
    refreshSEViewSelectedChrome();
}

// Load PPE items grouped like the official RPCPPE workbook tabs
const PPE_EXCEL_TAB_ORDER = [
    'LAND FINAL',
    'SUPPLIES AND EQUIPMENT FINAL',
    'SEF SCHOOL BUILDING',
    'LAND TITLE',
];

function ppeTabSlug(name) {
    return String(name).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

function ppeExcelTabFromItem(item) {
    const g = String(item.item_group || '').trim().toUpperCase().replace(/\s+/g, ' ');
    if (g.startsWith('LAND FINAL')) return 'LAND FINAL';
    if (g.startsWith('SUPPLIES AND EQUIPMENT')) return 'SUPPLIES AND EQUIPMENT FINAL';
    if (g.startsWith('SEF') || g.includes(' - SEF') || g.includes('SEF ·')) return 'SEF SCHOOL BUILDING';
    if (g === 'LAND TITLE' || g.startsWith('LAND TITLE')) return 'LAND TITLE';
    const landSections = {
        'ROAD NETWORKS': 1,
        'SCHOOL BUILDINGS': 1,
        'FLOOD CONTROL SYSTEM': 1,
        'BUILDINGS': 1,
        'OTHER LAND IMPROVEMENTS': 1,
        'OTHER INFRASTRUCTURE ASSETS': 1,
        'OTHER STRUCTURES': 1,
        'HOSPITALS AND HEALTH CENTERS': 1,
        'MARKETS': 1,
    };
    if (landSections[g]) return 'LAND FINAL';
    return 'SUPPLIES AND EQUIPMENT FINAL';
}

function ppeTabAccordionHtml(slug, tabName, items, disableDrag, lazy) {
    const n = items.length;
    const safeSlug = String(slug).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    return invOfficeCardHtml({
        onclick: `openPPETabDetail('${safeSlug}')`,
        title: tabName,
        meta: `${n} item${n !== 1 ? 's' : ''}`,
        iconCode: tabName,
    });
}

async function loadPPE() {
    const result = await apiGetItems('category=PPE');
    if (result.success) {
        ppeItemsByOffice = {};
        ppeItemsByTab = {};
        result.data.forEach(item => {
            const officeId = item.office_id;
            if (!ppeItemsByOffice[officeId]) {
                ppeItemsByOffice[officeId] = {
                    office_name: item.office_name,
                    office_code: item.office_code,
                    items: []
                };
            }
            ppeItemsByOffice[officeId].items.push(item);

            const tab = ppeExcelTabFromItem(item);
            const slug = ppeTabSlug(tab);
            if (!ppeItemsByTab[slug]) {
                ppeItemsByTab[slug] = { tab_name: tab, items: [] };
            }
            ppeItemsByTab[slug].items.push(item);
        });

        if (ppeDrillTabSlug && ppeItemsByTab[ppeDrillTabSlug]) {
            openPPETabDetail(ppeDrillTabSlug);
        } else {
            renderPPEOfficeAccordion();
        }
    }
}

function renderPPEOfficeAccordion() {
    ppeDrillTabSlug = null;
    setInvDrillChrome('ppe', false);
    const container = document.getElementById('ppe-offices-container');
    const ttl = document.querySelector('#pg-ppe .card-ttl');
    if (ttl) ttl.textContent = 'PPE by Excel tab (RPCPPE)';

    const slugs = Object.keys(ppeItemsByTab);
    if (slugs.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No PPE items found</p></div>';
        return;
    }

    const ordered = PPE_EXCEL_TAB_ORDER.map(ppeTabSlug).filter((slug) => ppeItemsByTab[slug]);
    slugs.forEach((slug) => {
        if (!ordered.includes(slug)) ordered.push(slug);
    });

    const cards = ordered.map((slug) => {
        const tab = ppeItemsByTab[slug];
        return ppeTabAccordionHtml(slug, tab.tab_name, tab.items, false, true);
    }).join('');

    container.innerHTML = `<div class="inv-office-grid">${cards}</div>`;
}

function openPPETabDetail(slug, itemsOverride) {
    const tab = ppeItemsByTab[slug];
    const container = document.getElementById('ppe-offices-container');
    if (!tab || !container) return;

    ppeDrillTabSlug = slug;
    setInvDrillChrome('ppe', true);
    const items = itemsOverride || tab.items || [];
    const n = items.length;
    const officeId = items[0] ? items[0].office_id : 0;
    const ttl = document.querySelector('#pg-ppe .card-ttl');
    if (ttl) ttl.textContent = tab.tab_name || 'PPE inventory';

    container.innerHTML = `
        <div class="inv-office-detail">
            ${invOfficeDetailBarHtml({
                backOnclick: 'closePPETabDetail()',
                backLabel: 'Back to PPE tabs',
                iconClass: invDeptIconClass('', tab.tab_name),
                title: tab.tab_name || 'PPE',
                meta: `${n} item${n !== 1 ? 's' : ''}`,
                toolsHtml: invOfficeDetailToolsHtml('ppe', { tabName: tab.tab_name || '' }),
            })}
            <div class="inv-office-detail-body">
                ${n ? renderOfficeItemsTable(items, 'ppe', officeId, false) : '<div class="empty"><p>No items in this tab for the current filters.</p></div>'}
            </div>
        </div>`;
    if (n) wireGsoTablesIn(container);
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closePPETabDetail() {
    ppeDrillTabSlug = null;
    setInvDrillChrome('ppe', false);
    const search = document.getElementById('s-ppe')?.value || '';
    const status = document.getElementById('f-status-ppe')?.value || '';
    if (search || status) {
        filterPPEItems();
    } else {
        renderPPEOfficeAccordion();
    }
}

function filterPPEItems() {
    const search = document.getElementById('s-ppe')?.value.toLowerCase() || '';
    const status = document.getElementById('f-status-ppe')?.value || '';

    if (ppeDrillTabSlug && ppeItemsByTab[ppeDrillTabSlug]) {
        const tab = ppeItemsByTab[ppeDrillTabSlug];
        const filteredItems = tab.items.filter((item) => {
            const matchesSearch = !search
                || lgueItemMatchesSearch(item, search, tab.tab_name)
                || (!lgueLooksPropertyNoQuery(search) && lgueTextMatchesQuery(tab.tab_name || '', search));
            const matchesStatus = !status || item.status === status;
            return matchesSearch && matchesStatus;
        });
        openPPETabDetail(ppeDrillTabSlug, filteredItems);
        return;
    }

    const container = document.getElementById('ppe-offices-container');
    const ttl = document.querySelector('#pg-ppe .card-ttl');
    if (ttl) ttl.textContent = 'PPE by Excel tab (RPCPPE)';

    let cards = '';
    let hasResults = false;

    const ordered = PPE_EXCEL_TAB_ORDER.map(ppeTabSlug).filter((slug) => ppeItemsByTab[slug]);
    Object.keys(ppeItemsByTab).forEach((slug) => {
        if (!ordered.includes(slug)) ordered.push(slug);
    });

    ordered.forEach((slug) => {
        const tab = ppeItemsByTab[slug];
        const filteredItems = tab.items.filter((item) => {
            const matchesSearch = !search
                || lgueItemMatchesSearch(item, search, tab.tab_name)
                || (!lgueLooksPropertyNoQuery(search) && lgueTextMatchesQuery(tab.tab_name || '', search));
            const matchesStatus = !status || item.status === status;
            return matchesSearch && matchesStatus;
        });
        if (filteredItems.length > 0) {
            hasResults = true;
            cards += ppeTabAccordionHtml(slug, tab.tab_name, filteredItems, !!search, true);
        }
    });

    if (!hasResults) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">🔍</div><p>No items match your search</p></div>';
    } else {
        container.innerHTML = `<div class="inv-office-grid">${cards}</div>`;
    }
    setInvDrillChrome('ppe', false);
}

// Render items table
function renderItemsTable(containerId, items) {
    const container = document.getElementById(containerId);
    if (!items || items.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No items found</p></div>';
        return;
    }
    const showOffice = true;
    const withDrag = false;
    const segs = segmentItemsForAllTable(items);
    const colCount = gsoTableColCount(showOffice, withDrag);
    let tbodies = '';
    for (const seg of segs) {
        const sub = seg.items.reduce((s, i) => s + gsoBookLineTotal(i), 0);
        tbodies += '<tbody>';
        if (gsoIsExplicitSectionSegment(seg)) {
            tbodies += gsoSectionHeaderRow(showOffice, withDrag, seg.title, sub);
        }
        for (const it of seg.items) {
            tbodies += gsoItemRowHtml(it, showOffice, withDrag);
        }
        tbodies += '</tbody>';
    }
    const total = items.reduce((s, i) => s + gsoBookLineTotal(i), 0);
    tbodies += `<tbody><tr class="gso-grand-total"><td colspan="${colCount}" style="text-align:right;font-weight:700;background:var(--surface2)">Total book value (Σ unit value × qty per property card): ${formatCurrency(total)}</td></tr></tbody>`;
    container.innerHTML = `<div class="gso-table-wrap" data-gso-root="1">${renderGsoTableShell(showOffice, withDrag, tbodies)}</div>`;
    wireGsoTablesIn(container);
}

/** DISABLED: Download the current SE/PPE list as an Excel-openable .csv (honors the active status filter). */
/*
function exportItemsExcel(category) {
    const statusEl = document.getElementById(category === 'SE' ? 'f-status-se' : 'f-status-ppe');
    const status = statusEl && statusEl.value ? statusEl.value : '';
    const qs = new URLSearchParams({ category });
    if (status) qs.set('status', status);
    window.location.href = `api/items-export.php?${qs.toString()}`;
}
*/

/** DISABLED: Bulk-upload SE/PPE items from an RPCSE/RPCPPE .xlsx or a .csv export. */
/*
async function importItemsExcel(inputEl, category) {
    const file = inputEl.files && inputEl.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('category', category);

    showToast('Importing items…', 'info');
    try {
        const response = await fetch('api/items-import.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Import complete', 'success');
            if (result.errors && result.errors.length) {
                console.warn('Import issues:', result.errors);
            }
            if (category === 'SE') loadSemiExpendable(); else loadPPE();
        } else {
            showToast(result.message || 'Import failed', 'error');
        }
    } catch (e) {
        showToast('Import failed: ' + e.message, 'error');
    } finally {
        inputEl.value = '';
    }
}
*/

function rpcseIsAdminUser() {
    const ud = document.getElementById('userData');
    return !!(ud && ud.dataset.userIsAdmin === '1');
}

function rpcseSessionOfficeId() {
    const ud = document.getElementById('userData');
    return ud && ud.dataset.userOfficeId ? parseInt(ud.dataset.userOfficeId, 10) || 0 : 0;
}

function rpcseExpandedOfficeId() {
    if (seDrillOfficeId != null) {
        const drilled = parseInt(seDrillOfficeId, 10);
        if (Number.isFinite(drilled) && drilled > 0) return drilled;
    }
    const el = document.querySelector('#se-offices-container .office-items.expanded');
    if (!el || !el.id) return 0;
    const m = String(el.id).match(/se-office-(\d+)/);
    return m ? parseInt(m[1], 10) : 0;
}

function rpcseOfficeChoices() {
    const byId = new Map();
    (offices || []).forEach((o) => {
        const id = parseInt(o.id, 10);
        if (!id) return;
        byId.set(id, {
            id,
            name: o.name || o.office_name || 'Office',
            code: o.code || o.office_code || '',
            count: 0
        });
    });
    Object.keys(seItemsByOffice || {}).forEach((key) => {
        const id = parseInt(key, 10);
        if (!id) return;
        const pack = seItemsByOffice[key] || {};
        const count = (pack.items || []).length;
        if (byId.has(id)) {
            byId.get(id).count = count;
            if (!byId.get(id).name && pack.office_name) byId.get(id).name = pack.office_name;
            if (!byId.get(id).code && pack.office_code) byId.get(id).code = pack.office_code;
        } else {
            byId.set(id, {
                id,
                name: pack.office_name || 'Office',
                code: pack.office_code || '',
                count
            });
        }
    });
    return [...byId.values()].sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { sensitivity: 'base' }));
}

function rpcseSelectedOfficeIds() {
    const hidden = document.getElementById('rpcse-office-id');
    if (!hidden || hidden.value === '') return [];
    if (hidden.value === '0') return [0];
    return hidden.value.split(',').map((s) => parseInt(s, 10)).filter((n) => Number.isFinite(n) && n > 0);
}

function rpcseSetSelectedOfficeIds(ids) {
    const hidden = document.getElementById('rpcse-office-id');
    if (!hidden) return;
    if (ids.length === 1 && ids[0] === 0) {
        hidden.value = '0';
        return;
    }
    hidden.value = ids.filter((id) => id > 0).join(',');
}

function renderRpcseOfficePickList() {
    const box = document.getElementById('rpcse-office-results');
    const chosen = document.getElementById('rpcse-office-chosen');
    if (!box) return;
    const q = ((document.getElementById('rpcse-office-q') || {}).value || '').trim().toLowerCase();
    const selectedIds = rpcseSelectedOfficeIds();
    const allOn = selectedIds.length === 1 && selectedIds[0] === 0;
    const selectedSet = new Set(allOn ? [] : selectedIds);
    const rows = rpcseOfficeChoices().filter((o) => {
        if (!q) return true;
        const hay = `${o.name} ${o.code}`.toLowerCase();
        return hay.includes(q);
    });
    const allRow = `<button type="button" class="uns-pick-row uns-pick-row-all${allOn ? ' is-on' : ''}" role="option" aria-selected="${allOn ? 'true' : 'false'}" onclick="rpcsePickOffice(0)"><strong>All offices</strong><span>Export every department from live Semi-Expendable inventory</span></button>`;
    const printBtn = document.getElementById('rpcse-office-print-btn');
    if (printBtn) {
        if (allOn) printBtn.textContent = 'Export all offices';
        else if (selectedSet.size > 1) printBtn.textContent = `View ${selectedSet.size} offices`;
        else printBtn.textContent = 'View this office';
    }
    if (!rows.length) {
        box.innerHTML = allRow + '<p class="uns-pick-empty">No matching office. Try another name or code.</p>';
    } else {
        box.innerHTML = allRow + rows.map((o) => {
            const on = selectedSet.has(o.id);
            const meta = [o.code, o.count === 1 ? '1 item' : `${o.count} items`].filter(Boolean).join(' · ');
            return `<button type="button" class="uns-pick-row${on ? ' is-on' : ''}" role="option" aria-selected="${on ? 'true' : 'false'}" onclick="rpcsePickOffice(${o.id})"><span class="uns-pick-check" aria-hidden="true"></span><span class="uns-pick-copy"><strong>${escAttr(o.name)}</strong><span>${escAttr(meta)}</span></span></button>`;
        }).join('');
    }
    if (chosen) {
        if (allOn) {
            chosen.hidden = false;
            chosen.textContent = 'Selected: All offices — Excel workbook';
        } else if (selectedSet.size > 0) {
            const names = rpcseOfficeChoices().filter((o) => selectedSet.has(o.id)).map((o) => o.name);
            const n = names.length;
            chosen.hidden = false;
            chosen.textContent = n === 1
                ? `Selected: ${names[0]} — 1 RPCSE sheet`
                : `Selected (${n}): ${names.join(', ')} — ${n} RPCSE sheets`;
        } else {
            chosen.hidden = true;
            chosen.textContent = '';
        }
    }
}

function rpcsePickOffice(officeId) {
    if (officeId === 0) {
        rpcseSetSelectedOfficeIds([0]);
        renderRpcseOfficePickList();
        return;
    }
    let ids = rpcseSelectedOfficeIds().filter((id) => id > 0);
    const pos = ids.indexOf(officeId);
    if (pos >= 0) ids.splice(pos, 1);
    else ids.push(officeId);
    rpcseSetSelectedOfficeIds(ids);
    renderRpcseOfficePickList();
}

async function openPhysicalCountOfficePicker() {
    if (!rpcseIsAdminUser()) {
        const mine = rpcseSessionOfficeId();
        if (mine <= 0) {
            showToast('Your account has no office assigned, so this report cannot be printed', 'error');
            return;
        }
        await openPhysicalCountReport('SE', mine);
        return;
    }
    if (!offices || offices.length === 0) {
        await loadOffices();
    }
    const hidden = document.getElementById('rpcse-office-id');
    const q = document.getElementById('rpcse-office-q');
    const expanded = rpcseExpandedOfficeId();
    if (hidden) hidden.value = expanded > 0 ? String(expanded) : '';
    if (q) q.value = '';
    renderRpcseOfficePickList();
    openModal('rpcse-office-modal');
    if (q) q.focus();
}

function confirmPhysicalCountOfficePrint() {
    const ids = rpcseSelectedOfficeIds();
    if (!ids.length) {
        showToast('Choose one or more offices', 'error');
        return;
    }
    closeModal('rpcse-office-modal');
    openPhysicalCountReport('SE', ids.length === 1 ? ids[0] : ids);
}

async function openPhysicalCountExcel(officeId) {
    const params = new URLSearchParams({ category: 'SE', format: 'xlsx' });
    const oid = parseInt(officeId, 10);
    if (Number.isFinite(oid) && oid > 0) params.set('office_id', String(oid));
    try {
        showToast('Building the all-offices RPCSE workbook from live inventory…', 'info');
        const response = await fetch('print/print-physical-count-report.php?' + params.toString(), {
            credentials: 'same-origin',
        });
        if (!response.ok) {
            const text = await response.text();
            showToast((text || 'Could not export the all-offices workbook').slice(0, 180), 'error');
            return;
        }
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'RPCSE-all-offices.xlsx';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        showToast('Downloaded view-only RPCSE workbook (sheets are protected in Excel)', 'success');
    } catch (e) {
        showToast('Could not export all offices: ' + e.message, 'error');
    }
}

function rpcppeTabChoices() {
    return PPE_EXCEL_TAB_ORDER.map((name) => {
        const slug = ppeTabSlug(name);
        const pack = ppeItemsByTab[slug] || {};
        return { name, count: (pack.items || []).length };
    });
}

function rpcppeExpandedTab() {
    if (ppeDrillTabSlug && ppeItemsByTab[ppeDrillTabSlug]) {
        return ppeItemsByTab[ppeDrillTabSlug].tab_name || '';
    }
    const el = document.querySelector('#ppe-offices-container .office-items.expanded');
    if (!el || !el.id) return '';
    const m = String(el.id).match(/^ppe-office-(.+)$/);
    if (!m) return '';
    const slug = m[1];
    return PPE_EXCEL_TAB_ORDER.find((t) => ppeTabSlug(t) === slug) || '';
}

function rpcppeSelectedTabs() {
    const hidden = document.getElementById('rpcppe-tab-id');
    if (!hidden || hidden.value === '') return [];
    if (hidden.value === '*') return ['*'];
    return hidden.value.split(',').map((s) => s.trim()).filter(Boolean);
}

function rpcppeSetSelectedTabs(tabs) {
    const hidden = document.getElementById('rpcppe-tab-id');
    if (!hidden) return;
    if (tabs.length === 1 && tabs[0] === '*') {
        hidden.value = '*';
        return;
    }
    hidden.value = tabs.filter((t) => t && t !== '*').join(',');
}

function renderRpcppeTabPickList() {
    const box = document.getElementById('rpcppe-tab-results');
    const chosen = document.getElementById('rpcppe-tab-chosen');
    if (!box) return;
    const q = ((document.getElementById('rpcppe-tab-q') || {}).value || '').trim().toLowerCase();
    const selected = rpcppeSelectedTabs();
    const allOn = selected.length === 1 && selected[0] === '*';
    const selectedSet = new Set(allOn ? [] : selected);
    const rows = rpcppeTabChoices().filter((t) => {
        if (!q) return true;
        return String(t.name).toLowerCase().includes(q);
    });
    const allRow = `<button type="button" class="uns-pick-row uns-pick-row-all${allOn ? ' is-on' : ''}" role="option" aria-selected="${allOn ? 'true' : 'false'}" onclick="rpcppePickTab('*')"><strong>All tabs</strong><span>Export every PPE asset group from live inventory</span></button>`;
    const printBtn = document.getElementById('rpcppe-tab-print-btn');
    if (printBtn) {
        if (allOn) printBtn.textContent = 'Export all tabs';
        else if (selectedSet.size > 1) printBtn.textContent = `View ${selectedSet.size} tabs`;
        else printBtn.textContent = 'View this tab';
    }
    if (!rows.length) {
        box.innerHTML = allRow + '<p class="uns-pick-empty">No matching tab. Try another name.</p>';
    } else {
        box.innerHTML = allRow + rows.map((t) => {
            const on = selectedSet.has(t.name);
            const meta = t.count === 1 ? '1 item' : `${t.count} items`;
            return `<button type="button" class="uns-pick-row${on ? ' is-on' : ''}" role="option" aria-selected="${on ? 'true' : 'false'}" onclick='rpcppePickTab(${JSON.stringify(t.name)})'><span class="uns-pick-check" aria-hidden="true"></span><span class="uns-pick-copy"><strong>${escAttr(t.name)}</strong><span>${escAttr(meta)}</span></span></button>`;
        }).join('');
    }
    if (chosen) {
        if (allOn) {
            chosen.hidden = false;
            chosen.textContent = 'Selected: All tabs — Excel workbook';
        } else if (selectedSet.size > 0) {
            const names = rpcppeTabChoices().filter((t) => selectedSet.has(t.name)).map((t) => t.name);
            const n = names.length;
            chosen.hidden = false;
            chosen.textContent = n === 1
                ? `Selected: ${names[0]} — 1 RPCPPE sheet`
                : `Selected (${n}): ${names.join(', ')} — ${n} RPCPPE sheets`;
        } else {
            chosen.hidden = true;
            chosen.textContent = '';
        }
    }
}

function rpcppePickTab(tabName) {
    if (tabName === '*') {
        rpcppeSetSelectedTabs(['*']);
        renderRpcppeTabPickList();
        return;
    }
    let tabs = rpcppeSelectedTabs().filter((t) => t && t !== '*');
    const pos = tabs.indexOf(tabName);
    if (pos >= 0) tabs.splice(pos, 1);
    else tabs.push(tabName);
    rpcppeSetSelectedTabs(tabs);
    renderRpcppeTabPickList();
}

function openPhysicalCountPpePicker() {
    const hidden = document.getElementById('rpcppe-tab-id');
    const q = document.getElementById('rpcppe-tab-q');
    const expanded = rpcppeExpandedTab();
    if (hidden) hidden.value = expanded || '';
    if (q) q.value = '';
    renderRpcppeTabPickList();
    openModal('rpcppe-tab-modal');
    if (q) q.focus();
}

function confirmPhysicalCountPpePrint() {
    const tabs = rpcppeSelectedTabs();
    if (!tabs.length) {
        showToast('Choose one or more PPE tabs', 'error');
        return;
    }
    closeModal('rpcppe-tab-modal');
    openPhysicalCountReport('PPE', tabs.length === 1 ? tabs[0] : tabs);
}

async function openPhysicalCountPpeExcel() {
    const params = new URLSearchParams({ category: 'PPE', format: 'xlsx' });
    try {
        showToast('Building the all-tabs RPCPPE workbook from live inventory…', 'info');
        const response = await fetch('print/print-physical-count-report.php?' + params.toString(), {
            credentials: 'same-origin',
        });
        if (!response.ok) {
            const text = await response.text();
            showToast((text || 'Could not export the all-tabs workbook').slice(0, 180), 'error');
            return;
        }
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'RPCPPE-all-tabs.xlsx';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        showToast('Downloaded view-only RPCPPE workbook (sheets are protected in Excel)', 'success');
    } catch (e) {
        showToast('Could not export all tabs: ' + e.message, 'error');
    }
}

/** COA physical count — SE opens the official Annex A form in the system. PPE opens the editable print tab. */
async function openPhysicalCountReport(category, officeId) {
    if (category !== 'SE' && category !== 'PPE') return;
    const statusEl = document.getElementById(category === 'SE' ? 'f-status-se' : 'f-status-ppe');
    const searchEl = document.getElementById(category === 'SE' ? 's-se' : 's-ppe');
    if (category === 'SE') {
        const ids = (Array.isArray(officeId) ? officeId : [officeId])
            .map((n) => parseInt(n, 10))
            .filter((n) => Number.isFinite(n));
        if (ids.length === 1 && ids[0] === 0) {
            await openPhysicalCountExcel(0);
            return;
        }
        const selected = ids.filter((n) => n > 0);
        if (!selected.length) return;
        const params = new URLSearchParams({ category: 'SE', format: 'html' });
        if (statusEl && statusEl.value) params.set('status', statusEl.value);
        params.set('office_ids', selected.join(','));
        if (selected.length === 1) params.set('office_id', String(selected[0]));
        const win = window.open('print/print-physical-count-report.php?' + params.toString(), '_blank');
        if (!win) {
            showToast('Allow pop-ups to open the RPCSE form in a new tab', 'error');
            return;
        }
        if (selected.length === 1) {
            const label = (rpcseOfficeChoices().find((o) => o.id === selected[0]) || {}).name || 'that office';
            showToast(`Opened the RPCSE form for ${label}`, 'success');
        } else {
            showToast(`Opened ${selected.length} RPCSE sheets — one form per selected office`, 'success');
        }
        return;
    }
    const tabs = (Array.isArray(officeId) ? officeId : (officeId != null && officeId !== '' ? [officeId] : []))
        .map((s) => String(s || '').trim())
        .filter(Boolean);
    if (tabs.length === 1 && (tabs[0] === '*' || tabs[0] === '0')) {
        await openPhysicalCountPpeExcel();
        return;
    }
    const selectedTabs = tabs.filter((t) => t !== '*' && t !== '0');
    if (!selectedTabs.length) {
        openPhysicalCountPpePicker();
        return;
    }
    const params = new URLSearchParams({ category: 'PPE', format: 'html' });
    if (statusEl && statusEl.value) params.set('status', statusEl.value);
    if (searchEl && searchEl.value.trim()) params.set('search', searchEl.value.trim());
    params.set('tabs', selectedTabs.join(','));
    if (selectedTabs.length === 1) params.set('tab', selectedTabs[0]);
    const win = window.open('print/print-physical-count-report.php?' + params.toString(), '_blank');
    if (!win) {
        showToast('Allow pop-ups to open the RPCPPE form in a new tab', 'error');
        return;
    }
    if (selectedTabs.length === 1) {
        showToast(`Opened the RPCPPE form for ${selectedTabs[0]}`, 'success');
    } else {
        showToast(`Opened ${selectedTabs.length} RPCPPE sheets — one form per selected tab`, 'success');
    }
}

function openPhysicalCountEditor(category) {
    if (category === 'PPE') {
        openPhysicalCountPpePicker();
        return;
    }
    openPhysicalCountReport(category);
}

/** DISABLED: Upload RPCPPE template */
/*
async function uploadRpcppeTemplate(inputEl) {
    if (!inputEl || !inputEl.files || !inputEl.files[0]) return;
    const formData = new FormData();
    formData.append('file', inputEl.files[0]);
    try {
        showToast('Uploading RPCPPE template…', 'info');
        const response = await fetch('api/rpcppe-template-upload.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Template replaced', 'success');
        } else {
            showToast(result.message || 'Upload failed', 'error');
        }
    } catch (e) {
        showToast('Upload failed: ' + e.message, 'error');
    } finally {
        inputEl.value = '';
    }
}
*/

/** DISABLED: Upload RPCSE template */
/*
async function uploadRpcseTemplate(inputEl) {
    if (!inputEl || !inputEl.files || !inputEl.files[0]) return;
    const formData = new FormData();
    formData.append('file', inputEl.files[0]);
    try {
        showToast('Uploading RPCSE template…', 'info');
        const response = await fetch('api/rpcse-template-upload.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Template replaced', 'success');
        } else {
            showToast(result.message || 'Upload failed', 'error');
        }
    } catch (e) {
        showToast('Upload failed: ' + e.message, 'error');
    } finally {
        inputEl.value = '';
    }
}
*/

/** DISABLED: Re-upload an edited RPCPPE/RPCSE .xlsx to update physical counts / remarks. */
/*
async function importPhysicalCountExcel(inputEl) {
    if (!inputEl || !inputEl.files || !inputEl.files[0]) return;
    const file = inputEl.files[0];
    const formData = new FormData();
    formData.append('file', file);
    try {
        showToast('Importing RPCPPE workbook…', 'info');
        const response = await fetch('api/physical-count-import.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'RPCPPE import complete', 'success');
            if (typeof loadSEItems === 'function') loadSEItems();
            if (typeof loadPPEItems === 'function') loadPPEItems();
            if (typeof loadItems === 'function') loadItems();
        } else {
            const detail = (result.errors && result.errors[0]) ? (' — ' + result.errors[0]) : '';
            showToast((result.message || 'Import failed') + detail, 'error');
        }
    } catch (e) {
        showToast('Import failed: ' + e.message, 'error');
    } finally {
        inputEl.value = '';
    }
}
*/

// Load issuances
async function loadIssuances() {
    const container = document.getElementById('tbl-iss');
    if (!container) return;
    const search = document.getElementById('s-iss')?.value || '';
    const officeId = document.getElementById('f-iss-office')?.value || '';
    const category = document.getElementById('f-iss-category')?.value || 'all';
    
    let url = 'issuance.php?';
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (officeId) url += `office_id=${officeId}&`;
    if (category && category !== 'all') url += `category=${encodeURIComponent(category)}&`;
    
    const result = await apiGet(url);
    if (result.success) {
        renderIssuancesTable(Array.isArray(result.data) ? result.data : []);
    }
}

function renderIssuancesTable(issuances) {
    const container = document.getElementById('tbl-iss');
    if (!container) return;
    const cat = document.getElementById('f-iss-category')?.value || 'all';
    const rows = Array.isArray(issuances) ? issuances : [];
    if (rows.length === 0) {
        const tip = cat === 'consumable'
            ? '<p class="muted" style="margin-top:8px;max-width:42ch;margin-left:auto;margin-right:auto;line-height:1.45">Consumable item names you inputted are in <strong>Procurement</strong> (Purchase requests). They appear here only after you release a completed consumable PR with <strong>Issue PAR</strong>.</p>'
            : '';
        container.innerHTML = `<div class="empty"><div class="empty-icon">📋</div><p>No issuance records found</p>${tip}</div>`;
        return;
    }
    const markFresh = !!__highlightLatestIssuance;
    __highlightLatestIssuance = false;
    
    container.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Form #</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Property #</th>
                    <th>Description</th>
                    <th>Issued To</th>
                    <th>Office</th>
                    <th>Date</th>
                    <th class="no-print">View</th>
                </tr>
            </thead>
            <tbody>
                ${rows.map((iss, idx) => {
                    const isCons = Number(iss.is_consumable) === 1;
                    const desc = String(iss.description || '').trim()
                        || (isCons ? 'Consumable items' : '—');
                    return `
                    <tr class="${markFresh && idx === 0 ? 'iss-row-fresh' : ''}">
                        <td class="mono">${escapeHtml(iss.form_number || '—')}</td>
                        <td><span class="badge b-par">PAR</span></td>
                        <td>${isCons ? '<span class="badge">Consumable</span>' : '<span class="badge b-par">Non-consumable</span>'}</td>
                        <td class="mono">${escapeHtml(iss.property_number || '—')}</td>
                        <td>${escapeHtml(desc)}${Number(iss.item_count) > 1 ? ` <span class="iss-note">(${Number(iss.item_count)} items)</span>` : ''}</td>
                        <td>${escapeHtml(iss.issued_to || '—')}</td>
                        <td>${escapeHtml(iss.office_name || '—')}</td>
                        <td>${formatDate(iss.issue_date)}</td>
                        <td class="no-print"><a class="link-print" href="print/print-issuance.php?id=${iss.id}" target="_blank" rel="noopener">View</a></td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>
    `;
}

// Load returns
async function loadReturns() {
    const search = document.getElementById('s-ret')?.value || '';
    let url = 'returns.php?';
    if (search) url += `search=${encodeURIComponent(search)}`;
    
    const result = await apiGet(url);
    if (result.success) {
        renderReturnsTable(result.data);
    }
}

function renderReturnsTable(returns) {
    const container = document.getElementById('tbl-ret');
    if (!returns || returns.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">↩️</div><p>No return records found</p></div>';
        return;
    }
    
    container.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Property #</th>
                    <th>Description</th>
                    <th>Returned By</th>
                    <th>Action</th>
                    <th>Condition</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                ${returns.map(ret => `
                    <tr>
                        <td class="mono">${ret.property_number || '—'}</td>
                        <td>${ret.description || '—'}</td>
                        <td>${ret.returned_by}</td>
                        <td><span class="badge b-${ret.return_action.toLowerCase()}">${ret.return_action}</span></td>
                        <td><span class="badge b-${ret.condition_on_return.toLowerCase()}">${ret.condition_on_return}</span></td>
                        <td>${formatDate(ret.return_date)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

// Load unserviceable items
let __unsAllItems = [];
let __unsSelectedIds = new Set();
let __unsDisposeIds = [];

function unsSelectedList() {
    return Array.from(__unsSelectedIds).filter((id) => id > 0);
}

function syncUnsBulkBar(shownIds) {
    const btn = document.getElementById('uns-bulk-dispose-btn');
    const hint = document.getElementById('uns-bulk-hint');
    const ids = unsSelectedList();
    const n = ids.length;
    if (btn) btn.hidden = !(userPermissions.isAdmin && n > 0);
    if (hint) {
        hint.hidden = n === 0;
        hint.textContent = n === 0 ? '' : (n === 1 ? '1 item selected' : `${n} items selected`);
    }
    const pageIds = Array.isArray(shownIds) && shownIds.length
        ? shownIds
        : Array.from(document.querySelectorAll('#tbl-uns .uns-row-check')).map((el) => Number(el.value) || 0).filter((id) => id > 0);
    const all = document.getElementById('uns-check-all');
    if (all && pageIds.length) {
        all.checked = pageIds.every((id) => __unsSelectedIds.has(id));
        all.indeterminate = !all.checked && pageIds.some((id) => __unsSelectedIds.has(id));
    }
}

function toggleUnsSelect(id, checked) {
    const n = Number(id) || 0;
    if (n <= 0) return;
    if (checked) __unsSelectedIds.add(n);
    else __unsSelectedIds.delete(n);
    syncUnsBulkBar();
}

function toggleUnsSelectAll(checked) {
    document.querySelectorAll('#tbl-uns .uns-row-check').forEach((el) => {
        const n = Number(el.value) || 0;
        el.checked = !!checked;
        if (n <= 0) return;
        if (checked) __unsSelectedIds.add(n);
        else __unsSelectedIds.delete(n);
    });
    syncUnsBulkBar();
}

function openDisposeConfirmFromList() {
    openDisposeConfirm(unsSelectedList());
}

function openDisposeConfirmFromDetail() {
    const id = parseInt(document.getElementById('uns-detail-id')?.value || '0', 10);
    openDisposeConfirm(id > 0 ? [id] : []);
}

function openDisposeConfirm(ids) {
    if (!userPermissions.isAdmin) {
        showToast('Only GSO / administrators can dispose items', 'error');
        return;
    }
    const clean = (Array.isArray(ids) ? ids : [ids]).map((id) => Number(id) || 0).filter((id) => id > 0);
    if (!clean.length) {
        showToast('Select at least one item to dispose', 'error');
        return;
    }
    __unsDisposeIds = clean;
    const msg = document.getElementById('uns-dispose-msg');
    if (msg) {
        msg.textContent = clean.length === 1
            ? 'This cannot be undone. The item will leave Unserviceable Items and stay in Disposal Records only.'
            : `This cannot be undone. ${clean.length} items will leave Unserviceable Items and stay in Disposal Records only.`;
    }
    openModal('uns-dispose-modal');
}

async function confirmDisposeUnserviceable() {
    const ids = (__unsDisposeIds || []).map((id) => Number(id) || 0).filter((id) => id > 0);
    if (!ids.length) {
        showToast('Select at least one item to dispose', 'error');
        return;
    }
    const result = await runBusy('uns-dispose-confirm-btn', () => apiPut('unserviceable.php', {
        action: 'dispose',
        ids,
        disposal_status: 'Disposed',
        disposal_date: typeof lgueTodayYmd === 'function' ? lgueTodayYmd() : undefined,
        disposal_method: 'GSO recorded'
    }), 'Disposing…');
    if (!result) return;
    const ok = !!(result.success || Number(result.count) > 0);
    if (!ok) {
        showToast(result.message || 'Could not save disposal', 'error');
        return;
    }
    ids.forEach((id) => __unsSelectedIds.delete(id));
    __unsDisposeIds = [];
    closeModal('uns-dispose-modal');
    closeModal('uns-detail-modal');
    showToast(result.message || 'Item moved to Disposal Records');
    invalidateItemsCache();
    if (typeof nav === 'function') nav('disposal');
    else loadDisposal();
}

window.toggleUnsSelect = toggleUnsSelect;
window.toggleUnsSelectAll = toggleUnsSelectAll;
window.openDisposeConfirm = openDisposeConfirm;
window.openDisposeConfirmFromList = openDisposeConfirmFromList;
window.openDisposeConfirmFromDetail = openDisposeConfirmFromDetail;
window.confirmDisposeUnserviceable = confirmDisposeUnserviceable;

function unsLocalYmd(dateObj) {
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function unsItemName(item) {
    return String(item.article || '').trim() || String(item.description || '').trim() || '';
}

function unsReasonCategory(item) {
    if (item.reason_category) return String(item.reason_category);
    const r = String(item.reason || '').trim();
    const cats = ['Damaged', 'Not Working', 'Broken', 'Missing Parts', 'Worn Out'];
    for (const c of cats) {
        if (r.toLowerCase().startsWith(c.toLowerCase())) return c;
    }
    if (r.toLowerCase().startsWith('other')) return 'Other';
    return r ? 'Other' : '';
}

function unsDateInPreset(ymd, preset, from, to) {
    if (!preset) return true;
    if (!ymd) return false;
    const now = new Date();
    if (preset === 'today') return ymd === unsLocalYmd(now);
    if (preset === 'week') {
        const day = now.getDay();
        const mondayOffset = day === 0 ? -6 : 1 - day;
        const monday = new Date(now.getFullYear(), now.getMonth(), now.getDate() + mondayOffset);
        const sunday = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6);
        return ymd >= unsLocalYmd(monday) && ymd <= unsLocalYmd(sunday);
    }
    if (preset === 'month') {
        const start = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
        const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        return ymd >= start && ymd <= unsLocalYmd(last);
    }
    if (preset === 'year') return ymd.startsWith(String(now.getFullYear()) + '-');
    if (preset === 'custom') {
        if (from && ymd < from) return false;
        if (to && ymd > to) return false;
        return true;
    }
    return true;
}

function onUnserviceableDatePresetChange() {
    const wrap = document.getElementById('uns-date-custom');
    const preset = document.getElementById('f-uns-date')?.value || '';
    if (wrap) wrap.hidden = preset !== 'custom';
    applyUnserviceableFilters();
}

function setUnsStatusFilter(status) {
    const sel = document.getElementById('f-uns-status');
    if (sel) sel.value = String(status || '');
    applyUnserviceableFilters();
}

function toggleUnsMoreFilters() {
    const wrap = document.getElementById('uns-more-filters');
    const btn = document.getElementById('uns-more-btn');
    if (!wrap) return;
    wrap.hidden = !wrap.hidden;
    if (btn) btn.textContent = wrap.hidden ? 'More' : 'Less';
}

function applyUnserviceableFilters() {
    const items = Array.isArray(__unsAllItems) ? __unsAllItems.slice() : [];
    const status = document.getElementById('f-uns-status')?.value || '';
    const q = (document.getElementById('uns-search')?.value || '').trim().toLowerCase();
    const officeId = document.getElementById('f-uns-office')?.value || '';
    const category = document.getElementById('f-uns-category')?.value || '';
    const reason = document.getElementById('f-uns-reason')?.value || '';
    const preset = document.getElementById('f-uns-date')?.value || '';
    const from = document.getElementById('f-uns-date-from')?.value || '';
    const to = document.getElementById('f-uns-date-to')?.value || '';
    const sort = document.getElementById('f-uns-sort')?.value || 'newest';

    let filtered = items.filter((item) => {
        if (String(item.disposal_status || '') === 'Disposed') return false;
        if (String(item.item_status || '') === 'Disposed') return false;
        if (status && String(item.repair_status || 'Pending') !== status) return false;
        if (officeId && String(item.office_id || '') !== String(officeId)) return false;
        if (category && String(item.category || '') !== category) return false;
        if (reason && unsReasonCategory(item) !== reason) return false;
        const ymd = String(item.marked_date || '').slice(0, 10);
        if (!unsDateInPreset(ymd, preset, from, to)) return false;
        if (q) {
            const hay = [
                item.property_number,
                item.article,
                item.description,
                item.office_name,
                item.reason,
            ].join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });

    const stamp = (row) => {
        const created = Date.parse(row.created_at || '') || 0;
        const marked = Date.parse(String(row.marked_date || '').slice(0, 10) + 'T00:00:00') || 0;
        const id = Number(row.id) || 0;
        return { created, marked, id, name: unsItemName(row).toLowerCase() };
    };

    filtered.sort((a, b) => {
        const A = stamp(a);
        const B = stamp(b);
        if (sort === 'oldest') return A.created - B.created || A.marked - B.marked || A.id - B.id;
        if (sort === 'name_asc') return A.name.localeCompare(B.name) || B.id - A.id;
        if (sort === 'name_desc') return B.name.localeCompare(A.name) || B.id - A.id;
        if (sort === 'date_asc') return A.marked - B.marked || A.id - B.id;
        if (sort === 'date_desc') return B.marked - A.marked || B.id - A.id;
        return B.created - A.created || B.marked - A.marked || B.id - A.id;
    });

    renderUnserviceableTable(filtered, items.length);
}

async function loadUnserviceable() {
    const result = await apiGet('unserviceable.php');
    if (result.success) {
        __unsAllItems = result.data || [];
        if (typeof offices !== 'undefined' && (!offices || offices.length === 0)) {
            await loadOffices();
        } else if (typeof populateOfficeSelects === 'function' && offices && offices.length) {
            populateOfficeSelects();
        }
        applyUnserviceableFilters();
    }
}

function renderUnserviceableTable(items, totalCount) {
    const container = document.getElementById('tbl-uns');
    if (!container) return;
    const total = typeof totalCount === 'number' ? totalCount : (items ? items.length : 0);
    const shown = items ? items.length : 0;
    const countEl = document.getElementById('uns-result-count');
    if (countEl) {
        countEl.hidden = total === 0;
        countEl.textContent = shown === total
            ? `${total} report${total === 1 ? '' : 's'}`
            : `Showing ${shown} of ${total} reports`;
    }
    if (!items || items.length === 0) {
        const msg = total > 0
            ? 'Nothing matches that search. Clear the box or pick All.'
            : 'No unserviceable items';
        container.innerHTML = `<div class="empty"><div class="empty-icon">⚠️</div><p>${msg}</p></div>`;
        syncUnsBulkBar([]);
        return;
    }

    const canSelect = !!userPermissions.isAdmin;
    const shownIds = items.map((row) => Number(row.id) || 0).filter((id) => id > 0);
    const allChecked = canSelect && shownIds.length > 0 && shownIds.every((id) => __unsSelectedIds.has(id));
    const checkHead = canSelect
        ? `<th class="uns-check-cell"><input type="checkbox" id="uns-check-all" ${allChecked ? 'checked' : ''} onchange="toggleUnsSelectAll(this.checked)" aria-label="Select all"></th>`
        : '';

    container.innerHTML = `
        <table>
            <thead>
                <tr>
                    ${checkHead}
                    <th>Property #</th>
                    <th>Description</th>
                    <th>Office</th>
                    <th>Reason</th>
                    <th>Marked Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(item => {
                    const reasonText = String(item.reason || '');
                    const preview = reasonText.length > 60 ? reasonText.slice(0, 60) + '…' : (reasonText || '—');
                    const repair = item.repair_status || 'Pending';
                    const pending = repair === 'Pending';
                    const id = Number(item.id) || 0;
                    const checked = __unsSelectedIds.has(id) ? 'checked' : '';
                    const checkCell = canSelect
                        ? `<td class="uns-check-cell"><input type="checkbox" class="uns-row-check" value="${id}" ${checked} onchange="toggleUnsSelect(${id}, this.checked)" aria-label="Select ${escAttr(item.property_number || 'item')}"></td>`
                        : '';
                    const viewBtn = `<button type="button" class="btn btn-ghost btn-xs" onclick="viewUnserviceableReason(${item.id})">View</button>`;
                    const disposeBtn = canSelect
                        ? `<button type="button" class="btn btn-red btn-xs" onclick="openDisposeConfirm([${item.id}])">Dispose</button>`
                        : '';
                    const actions = pending && canSelect
                        ? `<button type="button" class="btn btn-green btn-xs" onclick="resolveUnserviceableRepair('Serviceable', ${item.id})">Still works</button>
                           <button type="button" class="btn btn-ghost btn-xs" onclick="resolveUnserviceableRepair('Defective', ${item.id})">Cannot repair</button>
                           ${disposeBtn}
                           ${viewBtn}`
                        : `${canSelect ? `${disposeBtn} ` : ''}${viewBtn}`;
                    return `
                    <tr>
                        ${checkCell}
                        <td class="mono">${escAttr(item.property_number || '—')}</td>
                        <td>${escAttr(item.description || '—')}</td>
                        <td>${escAttr(item.office_name || '—')}</td>
                        <td>
                            <span class="uns-reason-preview">${escAttr(preview)}</span>
                        </td>
                        <td>${formatDate(item.marked_date)}</td>
                        <td><span class="badge ${deptItemStatusClass(repair)}">${escAttr(repair)}</span></td>
                        <td class="uns-actions-cell">${actions}</td>
                    </tr>
                `;
                }).join('')}
            </tbody>
        </table>
    `;
    syncUnsBulkBar(shownIds);
}

// Show the complete/long unserviceable reason + details in a modal (clicked from the
// Unserviceable table or the dashboard "Needs Attention" widget).
async function viewUnserviceableReason(id) {
    const res = await apiGet('unserviceable.php?id=' + encodeURIComponent(id));
    if (!res.success || !res.data) {
        showToast(res.message || 'Could not load details', 'error');
        return;
    }
    const item = res.data;
    document.getElementById('uns-detail-propnum').textContent = item.property_number || '—';
    document.getElementById('uns-detail-desc').textContent = item.description || '—';
    document.getElementById('uns-detail-office').textContent = item.office_name || '—';
    document.getElementById('uns-detail-date').textContent = formatDate(item.marked_date);
    const repair = item.repair_status || 'Pending';
    document.getElementById('uns-detail-status').textContent = repair;
    document.getElementById('uns-detail-reason').textContent = item.reason || '—';
    document.getElementById('uns-detail-remarks').textContent = item.remarks || '—';
    const idEl = document.getElementById('uns-detail-id');
    if (idEl) idEl.value = String(item.id);
    const resolveEl = document.getElementById('uns-detail-resolve');
    if (resolveEl) {
        const show = userPermissions.isAdmin && repair === 'Pending';
        resolveEl.hidden = !show;
    }
    const disposeBtn = document.getElementById('uns-detail-dispose-btn');
    if (disposeBtn) {
        const disposed = String(item.disposal_status || '') === 'Disposed' || String(item.item_status || '') === 'Disposed';
        disposeBtn.hidden = !(userPermissions.isAdmin && !disposed);
    }
    openModal('uns-detail-modal');
}

// Load disposal records
async function loadDisposal() {
    const result = await apiGet('unserviceable.php?for=disposal');
    if (result.success) {
        const disposed = (result.data || []).filter((item) => String(item.disposal_status || '') === 'Disposed');
        renderDisposalTable(disposed);
    }
}

function renderDisposalTable(items) {
    const container = document.getElementById('tbl-dis');
    if (!items || items.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">🗑️</div><p>No disposal records</p></div>';
        return;
    }
    
    container.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Property #</th>
                    <th>Description</th>
                    <th>Office</th>
                    <th>Disposal Date</th>
                    <th>Method</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(item => `
                    <tr>
                        <td class="mono">${item.property_number || '—'}</td>
                        <td>${item.description || '—'}</td>
                        <td>${item.office_name || '—'}</td>
                        <td>${formatDate(item.disposal_date)}</td>
                        <td>${item.disposal_method || '—'}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function auditModuleLabel(module) {
    const map = {
        inventory: 'Inventory',
        issuance: 'Issuance',
        returns: 'Returns',
        unserviceable: 'Unserviceable',
        procurement: 'Procurement',
        settings: 'Settings',
        offices: 'Offices',
        auth: 'Sign-in',
        reports: 'Reports'
    };
    const key = String(module || '').toLowerCase();
    return map[key] || (key ? key.charAt(0).toUpperCase() + key.slice(1) : 'System');
}

function auditRowHtml(row) {
    const who = row.changed_by && String(row.changed_by).trim() ? escAttr(row.changed_by) : 'Unknown (before named log)';
    const when = formatDate(row.changed_at);
    const oldV = row.old_value != null && String(row.old_value) !== '' ? escAttr(row.old_value) : '—';
    const newV = row.new_value != null && String(row.new_value) !== '' ? escAttr(row.new_value) : '—';
    return {
        who,
        when,
        oldV,
        newV,
        action: escAttr(row.action || ''),
        module: escAttr(auditModuleLabel(row.module)),
        record: escAttr(row.entity_label || row.property_number || row.office_name || '—')
    };
}

function auditApiUrl(query) {
    const q = query ? (query.startsWith('?') ? query : '?' + query) : '';
    return (typeof lgueAppUrl === 'function' ? lgueAppUrl('/api/audit.php') : 'api/audit.php') + q;
}

function activityOfficeQuery() {
    const staff = document.body.classList.contains('staff-portal');
    if (staff || userPermissions.departmentScoped) {
        const locked = userPermissions.officeId > 0 ? String(userPermissions.officeId) : '';
        return locked ? '&office_id=' + encodeURIComponent(locked) : '';
    }
    const sel = document.getElementById('audit-office-filter');
    const officeId = sel && sel.value ? String(sel.value) : 'all';
    return officeId ? '&office_id=' + encodeURIComponent(officeId) : '&office_id=all';
}

let __activityRows = [];

function activitySearchValue() {
    return (document.getElementById('activity-search')?.value || '').trim();
}

function activityRowMatches(row, q) {
    if (!q) return true;
    const needle = q.toLowerCase();
    const a = auditRowHtml(row);
    const hay = [
        a.when, a.who, a.module, a.action, a.record, a.oldV, a.newV,
        row.property_number, row.description, row.article, row.office_name, row.changed_by
    ].join(' ').toLowerCase();
    return hay.includes(needle);
}

function renderActivityTable(rows) {
    const container = document.getElementById('tbl-activity');
    if (!container) return;
    const staff = document.body.classList.contains('staff-portal');
    const q = activitySearchValue();
    const list = rows || [];
    const filtered = q ? list.filter((row) => activityRowMatches(row, q)) : list;
    if (list.length === 0) {
        const allView = !staff && document.getElementById('audit-office-filter')?.value === 'all';
        const adminView = !staff && document.getElementById('audit-office-filter')?.value === 'admin';
        container.innerHTML = staff || userPermissions.departmentScoped
            ? '<div class="empty"><p>No activity for this office yet. Only this department’s sign-in, inventory, issuance, returns, procurement, and account changes appear here. Other offices are never shown. This log cannot be edited.</p></div>'
            : adminView
                ? '<div class="empty"><p>No GSO administrator account activity yet. Switch to All activity to see inventory, issuance, procurement, and unserviceable records.</p></div>'
                : allView
                    ? '<div class="empty"><p>No recorded activity yet. Encoding, Issue PAR, procurement, unserviceable, dispose, settings, and report actions appear here after they happen.</p></div>'
                    : '<div class="empty"><p>No activity for this department yet. Staff sign-ins and personal account changes are not included in this admin view. This log cannot be edited.</p></div>';
        return;
    }
    if (filtered.length === 0) {
        const shown = typeof escapeHtml === 'function' ? escapeHtml(q) : q;
        container.innerHTML = `<div class="empty"><p>No activity matches “${shown}”.</p></div>`;
        return;
    }
    container.innerHTML = `
        <p class="audit-readonly-note">Read-only record. Past entries cannot be edited or deleted.${q ? ` Showing ${filtered.length} of ${list.length}.` : ''}</p>
        <table class="${staff ? 'staff-pr-table' : ''}">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Record</th>
                    <th>From</th>
                    <th>To</th>
                </tr>
            </thead>
            <tbody>
                ${filtered.map((row) => {
                    const a = auditRowHtml(row);
                    const itemId = Number(row.property_item_id || 0);
                    const recordLabel = a.record;
                    const recordCell = itemId
                        ? `<button type="button" class="btn btn-ghost btn-xs" onclick='openAuditHistory(${itemId}, ${JSON.stringify(String(row.property_number || row.entity_label || 'Item'))})'>${recordLabel}</button>`
                        : recordLabel;
                    return `<tr>
                        <td>${a.when}</td>
                        <td>${a.who}</td>
                        <td>${a.module}</td>
                        <td>${a.action}</td>
                        <td class="mono">${recordCell}</td>
                        <td>${a.oldV}</td>
                        <td>${a.newV}</td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>
    `;
}

function filterActivityLog() {
    renderActivityTable(__activityRows);
}

async function loadActivityLog() {
    const container = document.getElementById('tbl-activity');
    if (!container) return;
    const staff = document.body.classList.contains('staff-portal') || userPermissions.departmentScoped;
    const officeSel = document.getElementById('audit-office-filter');
    if (!staff && officeSel && officeSel.tagName === 'SELECT' && offices && offices.length && officeSel.options.length < offices.length + 2) {
        const currentValue = officeSel.value;
        officeSel.innerHTML =
            '<option value="all">All activity</option>' +
            '<option value="admin">Admin only</option>' +
            offices.map((o) => `<option value="${o.id}">${typeof escapeHtml === 'function' ? escapeHtml(o.name) : o.name}</option>`).join('');
        officeSel.value = currentValue || 'all';
    }
    if (!staff && officeSel && officeSel.tagName === 'SELECT' && !officeSel.value) {
        officeSel.value = 'all';
    }
    container.innerHTML = '<p class="muted" style="padding:16px">Loading…</p>';
    let result;
    const q = activitySearchValue();
    try {
        const qs = '?limit=300' + activityOfficeQuery() + (q ? '&q=' + encodeURIComponent(q) : '');
        const res = await fetch(auditApiUrl(qs), { credentials: 'same-origin' });
        result = await res.json();
    } catch (e) {
        container.innerHTML = '<div class="empty"><p>Could not load the activity log.</p></div>';
        return;
    }
    if (!result.success) {
        container.innerHTML = '<div class="empty"><p>Could not load the activity log.</p></div>';
        return;
    }
    __activityRows = Array.isArray(result.data) ? result.data : [];
    renderActivityTable(__activityRows);
}

function renderAuditRowsHtml(rows) {
    return `<table class="audit-readonly-table">
        <thead><tr><th>When</th><th>Who</th><th>What</th><th>From</th><th>To</th></tr></thead>
        <tbody>
            ${(rows || []).map((row) => {
                const a = auditRowHtml(row);
                return `<tr><td>${a.when}</td><td>${a.who}</td><td>${a.action}</td><td>${a.oldV}</td><td>${a.newV}</td></tr>`;
            }).join('')}
        </tbody>
    </table>`;
}

async function fetchAuditRows(query) {
    const res = await fetch(auditApiUrl(query), { credentials: 'same-origin' });
    return res.json();
}

async function loadItemHistory(itemId) {
    const box = document.getElementById('detail-history');
    if (!box) return;
    let result;
    try {
        result = await fetchAuditRows('?item_id=' + encodeURIComponent(itemId) + '&limit=30');
    } catch (e) {
        box.textContent = 'Could not load change history.';
        return;
    }
    if (!result.success || !result.data || result.data.length === 0) {
        box.textContent = 'No named changes yet. Future edits will list who did them.';
        return;
    }
    box.innerHTML = renderAuditRowsHtml(result.data);
}

async function openAuditHistory(itemId, label) {
    const id = Number(itemId || 0);
    if (!id) return;
    const title = document.getElementById('audit-history-title');
    const body = document.getElementById('audit-history-body');
    if (!body) return;
    if (title) title.textContent = 'Activity log · ' + (label || 'Item');
    body.innerHTML = '<p class="muted">Loading…</p>';
    if (typeof openModal === 'function') openModal('audit-history-modal');
    let result;
    try {
        result = await fetchAuditRows('?item_id=' + encodeURIComponent(id) + '&limit=50');
    } catch (e) {
        body.innerHTML = '<p class="muted">Could not load the activity log.</p>';
        return;
    }
    if (!result.success || !result.data || result.data.length === 0) {
        body.innerHTML = '<p class="muted">No activity recorded for this item yet.</p>';
        return;
    }
    body.innerHTML = renderAuditRowsHtml(result.data);
}

// Render table (for filters)
function rt(page) {
    switch(page) {
        case 'semi': filterSEItems(); break;
        case 'ppe': filterPPEItems(); break;
        case 'iss': loadIssuances(); break;
        case 'ret': loadReturns(); break;
    }
}

let __pendingIssuePrs = [];
let __issuePrFilterMode = 'today';

function pendingIssuePrById(prId) {
    return (__pendingIssuePrs || []).find((row) => String(row.pr_id) === String(prId)) || null;
}

function pendingIssuePrDate(pr) {
    const raw = String(pr?.completed_date || pr?.pr_date || '').trim();
    const m = /^(\d{4}-\d{2}-\d{2})/.exec(raw);
    return m ? m[1] : '';
}

function filteredPendingIssuePrs() {
    const all = Array.isArray(__pendingIssuePrs) ? __pendingIssuePrs.slice() : [];
    all.sort((a, b) => {
        const da = pendingIssuePrDate(b).localeCompare(pendingIssuePrDate(a));
        if (da !== 0) return da;
        return Number(b.pr_id || 0) - Number(a.pr_id || 0);
    });
    if (__issuePrFilterMode === 'all') return all;
    const want = (document.getElementById('iss-pr-date')?.value || lgueTodayYmd()).slice(0, 10);
    return all.filter((pr) => pendingIssuePrDate(pr) === want);
}

function syncIssuePrFilterChips() {
    const todayBtn = document.getElementById('iss-filter-today');
    const allBtn = document.getElementById('iss-filter-all');
    const dateEl = document.getElementById('iss-pr-date');
    const today = lgueTodayYmd();
    if (todayBtn) todayBtn.classList.toggle('is-on', __issuePrFilterMode === 'today' || (__issuePrFilterMode !== 'all' && dateEl?.value === today));
    if (allBtn) allBtn.classList.toggle('is-on', __issuePrFilterMode === 'all');
    if (dateEl && __issuePrFilterMode === 'all') {
        dateEl.value = '';
    } else if (dateEl && !dateEl.value) {
        dateEl.value = today;
    }
}

function fillIssueSelectFromPending() {
    const select = document.getElementById('iss-item');
    const hint = document.getElementById('iss-pr-filter-hint');
    if (!select) return;
    const rows = filteredPendingIssuePrs();
    const total = (__pendingIssuePrs || []).length;
    const today = lgueTodayYmd();
    const dateVal = (document.getElementById('iss-pr-date')?.value || today).slice(0, 10);
    if (rows.length > 0) {
        select.innerHTML = '<option value="">— select completed PR —</option>' +
            rows.map((row) => {
                const label = formatPendingIssueLabel(row);
                return `<option value="${row.pr_id}" data-pr-id="${row.pr_id}" data-category="${escAttr(row.category || '')}" data-office-id="${row.office_id}" data-office-name="${escAttr(row.office_name || '')}">${escAttr(label)}</option>`;
            }).join('');
    } else if (total === 0) {
        select.innerHTML = '<option value="">No completed purchase requests waiting to be issued</option>';
    } else {
        select.innerHTML = '<option value="">No completed PRs on this date — try All dates</option>';
    }
    if (hint) {
        if (__issuePrFilterMode === 'all') {
            hint.textContent = total
                ? `${rows.length} completed PR${rows.length === 1 ? '' : 's'} waiting to be issued (all dates).`
                : 'No completed purchase requests waiting to be issued.';
        } else if (dateVal === today) {
            hint.textContent = rows.length
                ? `${rows.length} completed today, waiting to be issued${total > rows.length ? ` (${total} total)` : ''}.`
                : `No PRs completed today. ${total} older request${total === 1 ? '' : 's'} still waiting — click All dates.`;
        } else {
            hint.textContent = rows.length
                ? `${rows.length} completed on ${formatDate(dateVal)}, waiting to be issued.`
                : `No completed PRs on ${formatDate(dateVal)}. Click All dates to see the rest.`;
        }
    }
    updateIssueForm();
}

function setIssuePrFilter(mode, ymd) {
    __issuePrFilterMode = mode === 'all' ? 'all' : (mode === 'today' ? 'today' : 'date');
    const dateEl = document.getElementById('iss-pr-date');
    if (dateEl) {
        if (__issuePrFilterMode === 'all') dateEl.value = '';
        else dateEl.value = (ymd || lgueTodayYmd()).slice(0, 10);
    }
    syncIssuePrFilterChips();
    fillIssueSelectFromPending();
}

function formatPendingIssueLabel(pr) {
    const n = Number(pr.pending_count || (pr.lines || []).length || 0);
    const names = (pr.lines || []).map((line) => {
        const art = String(line.article || '').trim();
        const desc = String(line.description || '').trim();
        return art && desc && art.toLowerCase() !== desc.toLowerCase() ? `${art} (${desc})` : (desc || art);
    }).filter(Boolean);
    const preview = names.slice(0, 3).join(', ') + (names.length > 3 ? '…' : '');
    const when = formatDate(pr.completed_date || pr.pr_date || '');
    return [
        pr.pr_number,
        pr.office_name,
        n + (n === 1 ? ' item' : ' items'),
        when && when !== '—' ? when : '',
        preview,
    ].filter(Boolean).join(' · ');
}

function issueSuggestedFormNumber(pr) {
    if (!pr) return '';
    if (pr.existing_form_number) return String(pr.existing_form_number);
    if (pr.suggested_form_number) return String(pr.suggested_form_number);
    const raw = String(pr.pr_number || '').trim();
    if (!raw) return '';
    if (/^PAR[\-\s]/i.test(raw)) return raw.replace(/^par/i, 'PAR');
    if (/^PR[\-\s]/i.test(raw)) return raw.replace(/^PR/i, 'PAR');
    return 'PAR-' + raw;
}

function renderIssuePrMeta(pr) {
    const box = document.getElementById('iss-pr-meta');
    if (!box) return;
    if (!pr) {
        box.hidden = true;
        box.innerHTML = '';
        return;
    }
    const pending = Number(pr.pending_count || 0);
    const total = Number(pr.total_count || (pr.lines || []).length || 0);
    const cards = [
        ['PR date', formatDate(pr.pr_date)],
        ['Completed', formatDate(pr.completed_date || pr.pr_date)],
        ['Office', pr.office_name || '—'],
        ['Items', pending + ' pending' + (total && total !== pending ? ' / ' + total : '')],
    ];
    box.hidden = false;
    box.className = 'iss-pr-meta';
    box.innerHTML = cards.map(([lbl, val]) => (
        `<div class="iss-pr-meta-card"><span>${escapeHtml(lbl)}</span><strong>${escapeHtml(String(val || '—'))}</strong></div>`
    )).join('');
}

function renderIssueLinesPreview(pr) {
    const wrap = document.getElementById('iss-lines-wrap');
    const list = document.getElementById('iss-lines-preview');
    const note = document.getElementById('iss-lines-note');
    if (!wrap || !list) return;
    if (!pr) {
        wrap.hidden = true;
        list.innerHTML = '';
        if (note) note.textContent = '';
        renderIssuePrMeta(null);
        const details = document.getElementById('iss-details-sec');
        if (details) details.hidden = true;
        return;
    }
    wrap.hidden = false;
    const details = document.getElementById('iss-details-sec');
    if (details) details.hidden = false;
    const rows = (pr.lines || []).map((line) => {
        const pending = !line.already_issued;
        const cat = Number(line.is_consumable) === 1
            ? 'Consumable'
            : (line.category === 'PPE' ? 'PPE' : 'SE');
        const title = escapeHtml([line.article, line.description].filter(Boolean).join(' — ') || 'Item');
        const qty = escapeHtml(formatQty(line.quantity || 1));
        const unit = escapeHtml(line.unit || 'pc');
        const status = pending ? '' : ' <span class="iss-note">already on PAR</span>';
        return `<tr><td><strong>${title}</strong>${status}</td><td class="iss-qty">${qty}</td><td>${unit}</td><td class="iss-cat">${escapeHtml(cat)}</td></tr>`;
    }).join('');
    list.innerHTML =
        `<table class="iss-lines-table"><thead><tr><th>Item</th><th class="iss-qty">Qty</th><th>Unit</th><th>After issue</th></tr></thead><tbody>${rows}</tbody></table>`;
    if (note) {
        if (pr.existing_form_number) {
            note.textContent = `Remaining items will be added to the same PAR (${pr.existing_form_number}). After issue, ₱50,000+ items go to PPE and the rest to Semi-Expendable.`;
        } else if (Number(pr.is_consumable) === 1) {
            note.textContent = 'All consumable lines on this purchase go on one PAR. Item names appear in Issuance Records after you confirm.';
        } else {
            note.textContent = 'All items on this purchase go on one PAR. After issue they split: ₱50,000+ to PPE, below that to Semi-Expendable.';
        }
    }
    renderIssuePrMeta(pr);
}

let __issueSaveLock = false;
let __issueModalWired = false;

function resetIssueModalFields() {
    const setVal = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.value = v;
    };
    setVal('iss-type', '');
    setVal('iss-class', '');
    setVal('iss-num', '');
    setVal('iss-to', '');
    setVal('iss-pos', '');
    setVal('iss-office', '');
    setVal('iss-proc-pr', '');
    setVal('iss-remarks', '');
    const numEl = document.getElementById('iss-num');
    if (numEl) numEl.readOnly = false;
    const dateEl = document.getElementById('iss-date');
    if (dateEl) dateEl.value = lgueTodayYmd();
    __issuePrFilterMode = 'today';
    const prDateEl = document.getElementById('iss-pr-date');
    if (prDateEl) prDateEl.value = lgueTodayYmd();
    syncIssuePrFilterChips();
    ['iss-to', 'iss-num', 'iss-item'].forEach((id) => {
        const el = document.getElementById(id);
        if (typeof lgueMarkField === 'function') lgueMarkField(el, false);
    });
    renderIssueLinesPreview(null);
    const hint = document.getElementById('iss-num-hint');
    if (hint) hint.textContent = 'Filled from the selected PR (PAR + the same year and number).';
    const confirmHint = document.getElementById('iss-confirm-hint');
    if (confirmHint) confirmHint.textContent = 'Select a purchase request and enter who will receive it, then confirm.';
    const btn = document.getElementById('issue-save-btn');
    if (btn) {
        btn.disabled = true;
        btn.dataset.busy = '';
        btn.classList.remove('is-busy');
    }
}

function syncIssueConfirmState() {
    const btn = document.getElementById('issue-save-btn');
    const hint = document.getElementById('iss-confirm-hint');
    const prId = document.getElementById('iss-item')?.value || '';
    const pr = prId ? pendingIssuePrById(prId) : null;
    const to = (document.getElementById('iss-to')?.value || '').trim();
    const num = (document.getElementById('iss-num')?.value || '').trim();
    const ready = !!(pr && to.length >= 3 && num.length >= 2 && !__issueSaveLock);
    if (btn) btn.disabled = !ready;
    if (hint) {
        if (!pr) hint.textContent = 'Select a purchase request and enter who will receive it, then confirm.';
        else if (to.length < 3) hint.textContent = 'Enter the name of the person who will receive these items, then confirm.';
        else hint.textContent = `Confirm to issue ${num || 'this PAR'} to ${to}. Nothing is saved until then.`;
    }
}

function wireIssueModalOnce() {
    if (__issueModalWired) return;
    __issueModalWired = true;
    const overlay = document.getElementById('issue-modal');
    if (overlay) {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeIssueParModal();
        });
    }
    ['iss-to', 'iss-num', 'iss-pos', 'iss-remarks'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', syncIssueConfirmState);
    });
    document.getElementById('iss-date')?.addEventListener('change', syncIssueConfirmState);
    document.getElementById('iss-filter-today')?.addEventListener('click', () => setIssuePrFilter('today'));
    document.getElementById('iss-filter-all')?.addEventListener('click', () => setIssuePrFilter('all'));
    document.getElementById('iss-pr-date')?.addEventListener('change', () => {
        const v = (document.getElementById('iss-pr-date')?.value || '').slice(0, 10);
        if (!v) setIssuePrFilter('all');
        else if (v === lgueTodayYmd()) setIssuePrFilter('today', v);
        else setIssuePrFilter('date', v);
    });
}

function openIssueParModal() {
    wireIssueModalOnce();
    __issueSaveLock = false;
    resetIssueModalFields();
    openModal('issue-modal');
    populateIssueSelect();
}

function closeIssueParModal() {
    if (__issueSaveLock) return;
    closeModal('issue-modal');
    resetIssueModalFields();
}

window.openIssueParModal = openIssueParModal;
window.closeIssueParModal = closeIssueParModal;

// Populate issue select
async function populateIssueSelect() {
    const select = document.getElementById('iss-item');
    if (select) select.innerHTML = '<option value="">Loading…</option>';
    __pendingIssuePrs = [];
    if (__issuePrFilterMode !== 'all' && __issuePrFilterMode !== 'date') {
        __issuePrFilterMode = 'today';
    }
    const prDateEl = document.getElementById('iss-pr-date');
    if (prDateEl && __issuePrFilterMode !== 'all' && !prDateEl.value) {
        prDateEl.value = lgueTodayYmd();
    }
    syncIssuePrFilterChips();
    const result = await apiGet('issuance.php?pending_pr=1');
    await yieldToPaint();
    __pendingIssuePrs = (result.success && Array.isArray(result.data)) ? result.data : [];
    fillIssueSelectFromPending();
}

// Populate return select
async function populateReturnSelect() {
    const select = document.getElementById('ret-item');
    if (select) select.innerHTML = '<option value="">Loading…</option>';
    const result = await apiGetItems('status=Issued&lite=1');
    await yieldToPaint();
    
    if (result.success && result.data.length > 0) {
        select.innerHTML = '<option value="">— select issued item —</option>' +
            result.data.map(item => 
                `<option value="${item.id}">${item.property_number} - ${item.description}</option>`
            ).join('');
    } else {
        select.innerHTML = '<option value="">No issued items available</option>';
    }
}

let __unsPickItems = [];

function unsPickLabel(item) {
    const pn = item.property_number || ('#' + item.id);
    const desc = String(item.article || item.description || '').trim();
    const off = String(item.office_name || '').trim();
    return `${pn}${desc ? ' — ' + desc : ''}${off ? ' · ' + off : ''}`;
}

function bindUnsPickUi() {
    const q = document.getElementById('uns-item-q');
    const off = document.getElementById('uns-item-office');
    if (q && q.dataset.bound !== '1') {
        q.dataset.bound = '1';
        q.addEventListener('input', () => {
            const hid = document.getElementById('uns-item');
            if (hid) hid.value = '';
            const chosen = document.getElementById('uns-item-chosen');
            if (chosen) chosen.hidden = true;
            renderUnsPickList();
        });
        q.addEventListener('focus', () => renderUnsPickList());
    }
    if (off && off.dataset.bound !== '1') {
        off.dataset.bound = '1';
        off.addEventListener('change', () => renderUnsPickList());
    }
}

function unsPickSelect(id, lock) {
    const hid = document.getElementById('uns-item');
    const q = document.getElementById('uns-item-q');
    const chosen = document.getElementById('uns-item-chosen');
    const box = document.getElementById('uns-item-results');
    const off = document.getElementById('uns-item-office');
    const item = (__unsPickItems || []).find((row) => String(row.id) === String(id));
    if (hid) hid.value = id ? String(id) : '';
    if (q) {
        q.disabled = !!lock;
        if (lock && item) q.value = unsPickLabel(item);
    }
    if (chosen) {
        chosen.hidden = !item;
        if (item) {
            chosen.textContent = 'Selected: ' + unsPickLabel(item);
        }
    }
    if (box) box.hidden = !!lock;
    if (off) off.disabled = !!lock;
    renderUnsPickList();
}

function renderUnsPickList() {
    const box = document.getElementById('uns-item-results');
    if (!box) return;
    const q = (document.getElementById('uns-item-q')?.value || '').trim().toLowerCase();
    const office = document.getElementById('uns-item-office')?.value || '';
    const selected = document.getElementById('uns-item')?.value || '';
    let rows = Array.isArray(__unsPickItems) ? __unsPickItems.slice() : [];
    if (office) {
        rows = rows.filter((r) => String(r.office_id) === String(office));
    }
    if (q) {
        const parts = q.split(/\s+/).filter((p) => /[a-z0-9]/i.test(p));
        rows = rows.filter((r) => {
            const hay = [
                r.property_number,
                r.article,
                r.description,
                r.office_name,
                r.item_group,
            ].join(' ').toLowerCase();
            return parts.every((p) => hay.includes(p));
        });
    }
    const shown = rows.slice(0, 40);
    if (shown.length === 0) {
        box.innerHTML = `<p class="uns-pick-empty">${rows.length === 0 && !q && !office ? 'No items available to mark.' : 'No matching items. Try another property # or office.'}</p>`;
        return;
    }
    box.innerHTML = shown.map((item) => {
        const on = String(item.id) === String(selected);
        const title = escAttr(item.property_number || ('#' + item.id));
        const sub = escAttr(
            [item.article || item.description || '', item.office_name || ''].filter(Boolean).join(' · ')
        );
        return `<button type="button" class="uns-pick-row${on ? ' is-on' : ''}" role="option" aria-selected="${on ? 'true' : 'false'}" onclick="unsPickSelect(${Number(item.id)})"><strong>${title}</strong><span>${sub || '—'}</span></button>`;
    }).join('') + (rows.length > shown.length
        ? `<p class="uns-pick-empty">Showing 40 of ${rows.length}. Type more to narrow the list.</p>`
        : '');
}

async function populateUnservSelect() {
    bindUnsPickUi();
    const hid = document.getElementById('uns-item');
    if (hid) hid.value = '';
    const q = document.getElementById('uns-item-q');
    if (q) {
        q.disabled = false;
        q.value = '';
    }
    const chosen = document.getElementById('uns-item-chosen');
    if (chosen) {
        chosen.hidden = true;
        chosen.textContent = '';
    }
    const box = document.getElementById('uns-item-results');
    if (box) {
        box.hidden = false;
        box.innerHTML = '<p class="uns-pick-empty">Loading items…</p>';
    }
    const offReset = document.getElementById('uns-item-office');
    if (offReset) offReset.disabled = false;
    if (typeof offices !== 'undefined' && (!offices || offices.length === 0) && typeof loadOffices === 'function') {
        await loadOffices();
    } else if (typeof populateOfficeSelects === 'function' && offices && offices.length) {
        populateOfficeSelects();
    }
    const result = await apiGetItems('lite=1');
    await yieldToPaint();
    __unsPickItems = (result.success && Array.isArray(result.data))
        ? result.data.filter(itemIsUnserviceableReportable)
        : [];
    renderUnsPickList();
    const cat = document.getElementById('uns-reason-category');
    if (cat) cat.value = '';
    const reason = document.getElementById('uns-reason');
    if (reason) reason.value = '';
    syncUnservOtherReason();
}

async function reportUnserviceableAgain(itemId) {
    if (!checkPermission('create')) return;
    const id = Number(itemId);
    if (!id) return;
    openModal('unserv-modal');
    await populateUnservSelect();
    if (typeof unsPickSelect === 'function') {
        const known = (__unsPickItems || []).find((row) => Number(row.id) === id)
            || (__unsAllItems || []).find((row) => Number(row.property_item_id) === id);
        if (known && !(__unsPickItems || []).some((row) => Number(row.id) === id)) {
            __unsPickItems.unshift({
                id,
                property_number: known.property_number,
                description: known.description,
                article: known.article,
                office_name: known.office_name,
                office_id: known.office_id,
            });
        }
        unsPickSelect(id);
    }
    const dateEl = document.getElementById('uns-date');
    if (dateEl && !dateEl.value) dateEl.value = new Date().toISOString().slice(0, 10);
}

// Update issue form
function updateIssueForm() {
    const select = document.getElementById('iss-item');
    const option = select?.options?.[select.selectedIndex];
    const classEl = document.getElementById('iss-class');
    const prHidden = document.getElementById('iss-proc-pr');
    const pr = option && option.value ? pendingIssuePrById(option.value) : null;
    
    const numEl = document.getElementById('iss-num');
    const numHint = document.getElementById('iss-num-hint');
    if (pr) {
        const officeName = pr.office_name || option.getAttribute('data-office-name') || '';
        const cat = pr.category || option.getAttribute('data-category') || '';
        document.getElementById('iss-type').value = 'PAR';
        document.getElementById('iss-office').value = officeName;
        if (classEl) {
            classEl.value = cat === 'MIXED'
                ? 'PPE and Semi-Expendable (split after this PAR)'
                : (cat === 'PPE' ? 'PPE (₱50,000+)' : 'Semi-Expendable');
        }
        if (prHidden) prHidden.value = String(pr.pr_id || '');
        if (numEl) {
            numEl.value = issueSuggestedFormNumber(pr);
            numEl.readOnly = !!pr.existing_form_number;
        }
        if (numHint) {
            numHint.textContent = pr.existing_form_number
                ? ('Same PAR as the rest of this purchase (' + pr.existing_form_number + ').')
                : ('Filled from ' + (pr.pr_number || 'the selected PR') + '.');
        }
        if (pr.existing_form_number) {
            const toEl = document.getElementById('iss-to');
            if (toEl && !toEl.value) toEl.value = pr.existing_issued_to || '';
            const posEl = document.getElementById('iss-pos');
            if (posEl && !posEl.value) posEl.value = pr.existing_position || '';
        }
        renderIssueLinesPreview(pr);
        syncIssueConfirmState();
    } else {
        document.getElementById('iss-type').value = '';
        document.getElementById('iss-office').value = '';
        if (classEl) classEl.value = '';
        if (prHidden) prHidden.value = '';
        if (numEl) {
            numEl.value = '';
            numEl.readOnly = false;
        }
        if (numHint) numHint.textContent = 'Filled from the selected PR (PAR + the same year and number).';
        const toEl = document.getElementById('iss-to');
        if (toEl && !pr) toEl.value = '';
        renderIssueLinesPreview(null);
        syncIssueConfirmState();
    }
}

// Optional link to a completed Purchase Request, scoped to the item's office
async function populateIssueProcPrSelect(officeId) {
    const sel = document.getElementById('iss-proc-pr');
    if (!sel) return;
    if (!officeId) {
        sel.innerHTML = '<option value="">— none —</option>';
        return;
    }
    const result = await apiGet(`procurement.php?status=completed&office_id=${encodeURIComponent(officeId)}`);
    if (result.success && result.data && result.data.length > 0) {
        sel.innerHTML = '<option value="">— none —</option>' +
            result.data.map(pr => `<option value="${pr.id}">${escAttr(pr.pr_number)} — ${escAttr(pr.purpose || '')}</option>`).join('');
    } else {
        sel.innerHTML = '<option value="">— no completed PRs for this office —</option>';
    }
}

// Update return form
function updateReturnForm() {
    const select = document.getElementById('ret-item');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        document.getElementById('ret-by').value = '';
    }
}
function selectReturnAction(action) { 
    selectedReturnAction = action;
    document.querySelectorAll('.raction').forEach(r => r.classList.remove('selected-uns', 'selected-xfer'));
    document.getElementById('ra-' + action).classList.add('selected-' + action);
    document.getElementById('xfer-office-wrap').style.display = action === 'xfer' ? 'block' : 'none';
}

// Save issuance
async function saveIssuance() {
    if (__issueSaveLock) return;
    const itemId = document.getElementById('iss-item').value;
    let formNumber = document.getElementById('iss-num').value.trim();
    const selectedPr = pendingIssuePrById(itemId);
    if (!formNumber && selectedPr) {
        formNumber = issueSuggestedFormNumber(selectedPr);
        const autoNum = document.getElementById('iss-num');
        if (autoNum) autoNum.value = formNumber;
    }
    const issuedTo = document.getElementById('iss-to').value.trim();
    const position = document.getElementById('iss-pos').value;
    let issueDate = document.getElementById('iss-date').value;
    const remarks = document.getElementById('iss-remarks').value;
    
    if (!itemId) {
        showToast('Please select a completed purchase request', 'error');
        return;
    }
    const toEl = document.getElementById('iss-to');
    const numEl = document.getElementById('iss-num');
    if (typeof lgueMarkField === 'function') {
        lgueMarkField(toEl, false);
        lgueMarkField(numEl, false);
    }
    const nameChk = lgueRequireName(issuedTo, 'Issued to');
    if (!nameChk.ok) {
        if (typeof lgueMarkField === 'function') lgueMarkField(toEl, true);
        showToast(nameChk.message, 'error');
        syncIssueConfirmState();
        return;
    }
    if (!formNumber || formNumber.length < 2) {
        if (typeof lgueMarkField === 'function') lgueMarkField(numEl, true);
        showToast('Form number is required.', 'error');
        return;
    }
    if (lgueLooksIllegible(formNumber)) {
        if (typeof lgueMarkField === 'function') lgueMarkField(numEl, true);
        showToast('Form number must be a real reference (not random letters).', 'error');
        return;
    }
    const posChk = lgueOptionalText(position, 'Position');
    if (!posChk.ok) {
        showToast(posChk.message, 'error');
        return;
    }
    const remChk = lgueOptionalText(remarks, 'Remarks');
    if (!remChk.ok) {
        showToast(remChk.message, 'error');
        return;
    }
    if (!issueDate) {
        issueDate = new Date().toISOString().split('T')[0];
        const dateEl = document.getElementById('iss-date');
        if (dateEl) dateEl.value = issueDate;
    }

    const procPrId = parseInt(itemId, 10);
    const data = {
        proc_pr_id: procPrId,
        form_number: formNumber,
        issued_to: nameChk.value,
        position: posChk.value,
        issue_date: issueDate,
        remarks: remChk.value
    };

    __issueSaveLock = true;
    syncIssueConfirmState();
    const result = await runBusy('issue-save-btn', () => apiPost('issuance.php', data), 'Issuing…');
    __issueSaveLock = false;
    if (!result) {
        syncIssueConfirmState();
        return;
    }
    if (result.success) {
        showToast(result.message || 'Issued and added to that office’s inventory');
        closeIssueParModal();
        __highlightLatestIssuance = true;
        refreshAfterWrite();
    } else {
        showToast(result.message || 'Failed to issue item', 'error');
        syncIssueConfirmState();
    }
}

// Save return
async function saveReturn() {
    const itemId = document.getElementById('ret-item').value;
    const returnedBy = document.getElementById('ret-by').value;
    const returnDate = document.getElementById('ret-date').value;
    const condition = document.getElementById('ret-cond').value;
    const remarks = document.getElementById('ret-remarks').value;
    
    if (!itemId || !selectedReturnAction) {
        showToast('Please fill in all required fields and select return action', 'error');
        return;
    }
    const byEl = document.getElementById('ret-by');
    if (typeof lgueMarkField === 'function') lgueMarkField(byEl, false);
    const byChk = lgueRequireName(returnedBy, 'Returned by');
    if (!byChk.ok) {
        if (typeof lgueMarkField === 'function') lgueMarkField(byEl, true);
        showToast(byChk.message, 'error');
        return;
    }
    const retRemChk = lgueOptionalText(remarks, 'Remarks');
    if (!retRemChk.ok) {
        showToast(retRemChk.message, 'error');
        return;
    }
    
    const data = {
        property_item_id: parseInt(itemId),
        issuance_id: 1, // This should be fetched from the item
        returned_by: byChk.value,
        return_date: returnDate,
        return_action: selectedReturnAction === 'uns' ? 'Unserviceable' : 'Transfer',
        condition_on_return: condition,
        remarks: retRemChk.value
    };
    
    if (selectedReturnAction === 'xfer') {
        const transferOffice = document.getElementById('ret-xfer-office').value;
        if (!transferOffice) {
            showToast('Please select transfer office', 'error');
            return;
        }
        data.transfer_to_office_id = parseInt(transferOffice);
    }
    
    const result = await runBusy('return-save-btn', () => apiPost('returns.php', data));
    if (!result) return;
    if (result.success) {
        showToast('Return recorded successfully');
        closeModal('return-modal');
        refreshAfterWrite();
    } else {
        showToast(result.message || 'Failed to record return', 'error');
    }
}

// Save unserviceable
function syncUnservOtherReason() {
    const cat = document.getElementById('uns-reason-category')?.value || '';
    const wrap = document.getElementById('uns-reason-other-wrap');
    if (wrap) wrap.style.display = cat === 'Other' ? '' : 'none';
}

async function saveUnserviceable() {
    if (!checkPermission('create')) {
        return;
    }

    const itemId = document.getElementById('uns-item').value;
    const markedDate = document.getElementById('uns-date').value;
    const markedBy = document.getElementById('uns-by').value;
    const category = document.getElementById('uns-reason-category')?.value || '';
    let reasonExtra = document.getElementById('uns-reason')?.value || '';
    const remarks = document.getElementById('uns-remarks').value;
    
    if (!itemId) {
        showToast('Type to find the item, then click a match.', 'error');
        return;
    }
    if (!category) {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    if (category === 'Other') {
        const otherChk = lgueRequireText(reasonExtra, 'Other reason', { minLen: 8, minWords: 2 });
        if (!otherChk.ok) {
            showToast(otherChk.message, 'error');
            return;
        }
        reasonExtra = otherChk.value;
    }
    const unsByChk = lgueOptionalText(markedBy, 'Marked by');
    if (!unsByChk.ok) {
        showToast(unsByChk.message, 'error');
        return;
    }
    const unsRemChk = lgueOptionalText(remarks, 'Remarks');
    if (!unsRemChk.ok) {
        showToast(unsRemChk.message, 'error');
        return;
    }
    
    const data = {
        property_item_id: parseInt(itemId),
        marked_date: markedDate,
        marked_by: unsByChk.value,
        reason_category: category,
        reason: category === 'Other' ? reasonExtra : category,
        remarks: unsRemChk.value
    };
    
    const result = await runBusy('unserv-save-btn', () => apiPost('unserviceable.php', data));
    if (!result) return;
    if (result.success) {
        showToast('Item marked as unserviceable');
        closeModal('unserv-modal');
        const unsItem = document.getElementById('uns-item');
        if (unsItem) unsItem.disabled = false;
        refreshAfterWrite();
        loadNotifications();
    } else {
        showToast(result.message || 'Failed to mark item', 'error');
    }
}

async function resolveUnserviceableRepair(status, recordId) {
    if (!userPermissions.isAdmin) {
        showToast('Only GSO / administrators can update repair status', 'error');
        return;
    }
    const id = recordId || parseInt(document.getElementById('uns-detail-id')?.value || '0', 10);
    if (!id) {
        showToast('Missing record', 'error');
        return;
    }
    const result = await apiPut('unserviceable.php', {
        action: 'resolve_repair',
        id,
        repair_status: status
    });
    if (result.success) {
        showToast(status === 'Serviceable'
            ? (result.message || 'Item still works and was removed from the unserviceable list')
            : (result.message || 'Status updated'));
        closeModal('uns-detail-modal');
        invalidateItemsCache();
        loadUnserviceable();
        if (currentPage === 'dashboard') loadDashboard();
        loadNotifications();
    } else {
        showToast(result.message || 'Failed to update status', 'error');
    }
}

function setDetailModalFooterMode(mode, item) {
    const editBtn = document.getElementById('detail-btn-edit');
    const cancelBtn = document.getElementById('detail-btn-cancel');
    const saveBtn = document.getElementById('detail-btn-save');
    const unservBtn = document.getElementById('detail-btn-unserv');
    const transferBtn = document.getElementById('detail-btn-transfer');
    if (!editBtn || !cancelBtn || !saveBtn) return;
    if (mode === 'edit') {
        editBtn.style.display = 'none';
        cancelBtn.style.display = 'inline-flex';
        saveBtn.style.display = 'inline-flex';
    } else if (mode === 'view' && userPermissions.canEdit) {
        editBtn.style.display = 'inline-flex';
        cancelBtn.style.display = 'none';
        saveBtn.style.display = 'none';
    } else {
        editBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
        saveBtn.style.display = 'none';
    }

    // Mark Unserviceable / Transfer to Office are only offered in view mode,
    // for users who can create/edit records, and only while the item is
    // still actionable (not already Unserviceable/Disposed).
    const it = item || currentDetailItem;
    const showUnserv = mode === 'view' && it && itemIsUnserviceableReportable(it);
    const showTransfer = mode === 'view' && it && ['Active', 'Transferred'].includes(it.status);
    if (unservBtn) {
        unservBtn.style.display = (showUnserv && userPermissions.canCreate) ? 'inline-flex' : 'none';
    }
    if (transferBtn) {
        transferBtn.style.display = (showTransfer && userPermissions.canEdit) ? 'inline-flex' : 'none';
    }
}

function detailViewHtml(item) {
    const remarksBlock = `<div class="pc-f"><div class="pc-fl">Remarks</div><div class="pc-fv">${item.remarks ? escAttr(item.remarks) : '—'}</div></div>`;
    const isPpe = String(item.category || '').toUpperCase() === 'PPE';
    const noPhysRecorded = item.quantity_physical_count === null || item.quantity_physical_count === undefined || item.quantity_physical_count === '';
    const physicalCell = isPpe && noPhysRecorded
        ? '—'
        : `${gsoPhysicalQty(item)}${noPhysRecorded ? ' <span style="color:var(--text3);font-size:11px">(same as card if not recorded)</span>' : ''}`;
    const varQtyCell = isPpe && noPhysRecorded ? '—' : gsoVarianceQtyCell(item);
    const varValCell = isPpe && noPhysRecorded ? '—' : gsoVarianceValueCell(item);
    return `
            <div class="pc-wrap">
                <div class="pc-top">
                    <div>
                        <div class="pc-gov">LGU Property Office</div>
                        <div class="pc-title">${[(item.article || '').trim(), (item.description || '').trim()].filter(Boolean).join(' — ') || '—'}</div>
                        <div class="pc-sub">${item.property_number}</div>
                    </div>
                    <span class="badge b-${item.category.toLowerCase()}">${item.category}</span>
                </div>
                <div class="pc-grid">
                    <div class="pc-f">
                        <div class="pc-fl">Report section</div>
                        <div class="pc-fv">${(item.item_group && String(item.item_group).trim()) ? escAttr(String(item.item_group).trim()) : '—'}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Unit Value</div>
                        <div class="pc-fv">${formatItemUnitValue(item)}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Unit of Measure</div>
                        <div class="pc-fv">${(item.unit_of_measure || 'pc').toString()}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Qty (Property Card)</div>
                        <div class="pc-fv">${gsoBookQty(item)}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Qty (Physical Count)</div>
                        <div class="pc-fv">${physicalCell}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Shortage / Overage (qty)</div>
                        <div class="pc-fv">${varQtyCell}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Shortage / Overage (value)</div>
                        <div class="pc-fv">${varValCell}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Total Value (book)</div>
                        <div class="pc-fv">${formatCurrency((parseFloat(item.unit_value) || 0) * gsoBookQty(item))}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Office</div>
                        <div class="pc-fv">${item.office_name || '—'}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Acquisition Date</div>
                        <div class="pc-fv">${formatDate(item.acquisition_date)}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Status</div>
                        <div class="pc-fv"><span class="badge b-${item.status.toLowerCase()}">${item.status}</span></div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Condition</div>
                        <div class="pc-fv"><span class="badge b-${item.condition_status.toLowerCase()}">${item.condition_status}</span></div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Encoded</div>
                        <div class="pc-fv">${formatDate(item.created_at)}</div>
                    </div>
                </div>
                ${remarksBlock}
                <div class="pc-f audit-readonly" style="margin-top:14px">
                    <div class="pc-fl">Change history <span class="audit-readonly-note" style="display:inline;margin:0">(read-only)</span></div>
                    <div class="pc-fv" id="detail-history">Loading…</div>
                </div>
            </div>
        `;
}

function detailEditFormHtml(item) {
    const isPpeCat = String(item.category || '').toUpperCase() === 'PPE';
    const qtyCardLabel = isPpeCat
        ? 'Qty (Property Card)'
        : 'Qty (Property Card) *';
    const qtyCardHint = isPpeCat ? '<span style="color:var(--text3);font-weight:400">(optional — leave blank for 1)</span>' : '';
    const qtyCardInputAttrs = isPpeCat
        ? 'type="number" id="d-qty-pc" min="0" step="1" placeholder="Blank → 1" value="' + escAttr(String(gsoBookQty(item))) + '"'
        : 'type="number" id="d-qty-pc" min="1" step="1" value="' + escAttr(String(gsoBookQty(item))) + '"';
    const physPlaceholder = isPpeCat ? 'Optional; blank if not counted' : 'Blank = same as card';
    const physHint = isPpeCat
        ? '<span style="color:var(--text3);font-weight:400">(optional for PPE)</span>'
        : '';
    const varHint = isPpeCat
        ? '<span style="color:var(--text3);font-weight:400">(shown only when physical count is recorded)</span>'
        : '';
    const physVal = item.quantity_physical_count != null && item.quantity_physical_count !== '' ? String(item.quantity_physical_count) : '';
    const acq = item.acquisition_date ? String(item.acquisition_date).slice(0, 10) : '';
    const statuses = ['Active', 'Issued', 'Unserviceable', 'Disposed', 'Transferred'];
    const conds = ['Good', 'Fair', 'Damaged'];
    const stOpts = statuses.map(s => `<option value="${s}"${item.status === s ? ' selected' : ''}>${s}</option>`).join('');
    const coOpts = conds.map(c => `<option value="${c}"${item.condition_status === c ? ' selected' : ''}>${c}</option>`).join('');
    const officeOpts = (offices && offices.length)
        ? offices.map(o => `<option value="${o.id}"${String(item.office_id) === String(o.id) ? ' selected' : ''}>${escAttr(o.name)}</option>`).join('')
        : `<option value="${escAttr(String(item.office_id))}">${escAttr(item.office_name || 'Office')}</option>`;
    return `
            <div class="pc-wrap detail-edit-form">
                <p style="font-size:12px;color:var(--text3);margin:0 0 12px">You can change the property number. Use <strong>NONE</strong> if it is not assigned yet. SE/PPE still follows unit value (≥ ₱${getPpeThreshold().toLocaleString('en-PH')} = PPE).</p>
                <div class="form-grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                    <div class="fg full"><label>Property number *</label><input type="text" id="d-propnum" value="${escAttr(item.property_number || '')}" autocomplete="off"/></div>
                    <div class="fg"><label>Article <span style="color:var(--text3);font-weight:400">(optional)</span></label><input type="text" id="d-article" value="${escAttr(item.article || '')}"/></div>
                    <div class="fg"><label>Unit of Measure *</label><input type="text" id="d-uom" value="${escAttr(item.unit_of_measure || 'pc')}"/></div>
                    <div class="fg full"><label>Description *</label><input type="text" id="d-desc" value="${escAttr(item.description || '')}"/></div>
                    <div class="fg full"><label>Report section (optional)</label><input type="text" id="d-group" placeholder="e.g. OFFICE FIXTURES & FURNITURES" value="${escAttr(item.item_group || '')}"/></div>
                    <div class="fg"><label>Unit value (₱) *</label><input type="text" inputmode="decimal" id="d-amt" class="lgue-money" autocomplete="off" value="${escAttr(itemUnitValueIsDonation(item) ? 'DONATION' : (typeof lgueMoneyFromNumber === 'function' ? lgueMoneyFromNumber(item.unit_value) : item.unit_value))}"/></div>
                    <div class="fg"><label>${qtyCardLabel} ${qtyCardHint}</label><input ${qtyCardInputAttrs}/></div>
                    <div class="fg"><label>Qty (Physical Count) ${physHint}</label><input type="number" id="d-qty-phys" min="0" placeholder="${escAttr(physPlaceholder)}" value="${escAttr(physVal)}"/></div>
                    <div class="fg full"><label>Category (from unit value)</label><div id="d-classify-ed" class="classify-box">—</div></div>
                    <div class="fg"><label>Shortage / Overage (qty) ${varHint}</label><div id="d-var-qty" class="pc-fv" style="padding:8px 0">—</div></div>
                    <div class="fg"><label>Shortage / Overage (value) ${varHint}</label><div id="d-var-val" class="pc-fv" style="padding:8px 0">—</div></div>
                    <div class="fg"><label>Office *</label><select id="d-off">${officeOpts}</select></div>
                    <div class="fg"><label>Acquisition date</label><input type="date" id="d-date" value="${escAttr(acq)}"/></div>
                    <div class="fg"><label>Status</label><select id="d-status">${stOpts}</select></div>
                    <div class="fg"><label>Condition</label><select id="d-cond">${coOpts}</select></div>
                    <div class="fg full"><label>Remarks</label><textarea id="d-remarks" rows="2">${escAttr(item.remarks || '')}</textarea></div>
                </div>
            </div>
        `;
}

function wireDetailEditVariancePreview() {
    const pc = document.getElementById('d-qty-pc');
    const ph = document.getElementById('d-qty-phys');
    const uv = document.getElementById('d-amt');
    const outQ = document.getElementById('d-var-qty');
    const outV = document.getElementById('d-var-val');
    if (!pc || !ph || !uv || !outQ || !outV) return;
    const tick = () => {
        const uvNum = (typeof lgueParseNumber === 'function'
            ? lgueParseNumber(uv.value, 'Unit value', { min: 0 })
            : { ok: Number.isFinite(parseFloat(lgueMoneyPlain(uv.value))), value: parseFloat(lgueMoneyPlain(uv.value)) }).value;
        const isPpeAmt = Number.isFinite(uvNum) && uvNum >= getPpeThreshold();
        if (isPpeAmt && ph.value.trim() === '') {
            outQ.textContent = '—';
            outV.textContent = '—';
            return;
        }
        const mock = {
            quantity: pc.value,
            quantity_physical_count: ph.value.trim() === '' ? null : ph.value,
            unit_value: Number.isFinite(uvNum) ? uvNum : 0
        };
        outQ.textContent = gsoVarianceQtyCell(mock);
        outV.textContent = gsoVarianceValueCell(mock);
    };
    [pc, ph, uv].forEach(el => el.addEventListener('input', tick));
    tick();
}

function wireDetailEditClassification() {
    const amt = document.getElementById('d-amt');
    const el = document.getElementById('d-classify-ed');
    if (!amt || !el) return;
    const tick = () => {
        const parsed = typeof lgueParseNumber === 'function'
            ? lgueParseNumber(amt.value, 'Unit value', { min: 0 })
            : { ok: Number.isFinite(parseFloat(lgueMoneyPlain(amt.value))), value: parseFloat(lgueMoneyPlain(amt.value)) };
        const num = parsed.ok && Number.isFinite(parsed.value) ? parsed.value : 0;
        const cat = num >= getPpeThreshold() ? 'PPE' : 'SE';
        el.innerHTML = `<span class="badge b-${cat.toLowerCase()}">${cat}</span> · property number prefix may not match until re-numbered`;
    };
    amt.addEventListener('input', tick);
    tick();
}

async function enterItemDetailEdit() {
    if (!checkPermission('edit')) return;
    if (!currentDetailItemId) return;
    const result = await apiGet(`items.php?id=${currentDetailItemId}`);
    if (!result.success || !result.data) {
        showToast('Could not load item', 'error');
        return;
    }
    if (!offices || offices.length === 0) await loadOffices();
    const content = document.getElementById('detail-content');
    content.innerHTML = detailEditFormHtml(result.data);
    wireDetailEditVariancePreview();
    wireDetailEditClassification();
    const sel = document.getElementById('d-off');
    if (sel && offices && offices.length) {
        const cur = String(result.data.office_id);
        sel.innerHTML = offices.map(o => `<option value="${o.id}"${String(o.id) === cur ? ' selected' : ''}>${escAttr(o.name)}</option>`).join('');
    }
    setDetailModalFooterMode('edit');
}

async function cancelItemDetailEdit() {
    if (!currentDetailItemId) {
        closeModal('detail-modal');
        return;
    }
    await viewItemDetail(currentDetailItemId);
}

async function saveItemDetail() {
    if (!checkPermission('edit')) return;
    if (!currentDetailItemId) return;
    const qtyPhysRaw = document.getElementById('d-qty-phys') ? document.getElementById('d-qty-phys').value.trim() : '';
    const qtyPhysical = qtyPhysRaw === '' ? null : parseInt(qtyPhysRaw, 10);
    const qtyPcRaw = document.getElementById('d-qty-pc') ? document.getElementById('d-qty-pc').value.trim() : '';
    const article = document.getElementById('d-article').value.trim();
    const description = document.getElementById('d-desc').value.trim();
    const unitChk = lgueRequireUnit(document.getElementById('d-uom').value);
    if (!unitChk.ok) {
        showToast(unitChk.message, 'error');
        return;
    }
    const unit_of_measure = unitChk.value;
    const detAmtEl = document.getElementById('d-amt');
    const detAmtRaw = detAmtEl ? String(detAmtEl.value || '').trim() : '';
    let uv = 0;
    if (isDonationUnitValue(detAmtRaw)) {
        uv = 'DONATION';
    } else if (detAmtRaw !== '') {
        const detAmtChk = lgueParseNumber(detAmtRaw, 'Unit value', { min: 0 });
        if (!detAmtChk.ok) {
            showToast(detAmtChk.message, 'error');
            return;
        }
        uv = detAmtChk.value;
    }
    let quantity = qtyPcRaw === '' ? NaN : parseInt(qtyPcRaw, 10);
    if (uv >= getPpeThreshold() && qtyPcRaw === '') {
        quantity = 1;
    }
    const office_id = parseInt(document.getElementById('d-off').value, 10);
    const acquisition_date = document.getElementById('d-date').value;
    const status = document.getElementById('d-status').value;
    const condition_status = document.getElementById('d-cond').value;
    const remarks = document.getElementById('d-remarks').value;
    const item_group = document.getElementById('d-group') ? document.getElementById('d-group').value.trim() : '';
    const property_number = document.getElementById('d-propnum')
        ? document.getElementById('d-propnum').value.trim()
        : '';
    if (!property_number) {
        showToast('Property number is required. Use NONE if it is not assigned yet.', 'error');
        return;
    }
    if (property_number.toUpperCase() !== 'NONE' && property_number.length < 3) {
        showToast('Property number is too short.', 'error');
        return;
    }

    let descOut = description;
    if (!descOut && article) descOut = article;
    const detTextChk = lgueRequireText(descOut, 'Article or description', { minLen: 3 });
    if (!detTextChk.ok) {
        showToast(detTextChk.message, 'error');
        return;
    }
    descOut = detTextChk.value;
    if (typeof uv === 'number' && uv < 0) {
        showToast('Unit value cannot be negative', 'error');
        return;
    }
    if (qtyPcRaw !== '') {
        const detQtyChk = lgueParseNumber(qtyPcRaw, 'Quantity', { integer: true, min: 1 });
        if (!detQtyChk.ok) {
            showToast(detQtyChk.message, 'error');
            return;
        }
    }
    if (!office_id || office_id <= 0 || !Number.isFinite(quantity) || quantity < 1) {
        showToast('Please fix office and quantity (property card)', 'error');
        return;
    }
    if (qtyPhysRaw !== '') {
        const detPhysChk = lgueParseNumber(qtyPhysRaw, 'Physical count', { integer: true, min: 0 });
        if (!detPhysChk.ok) {
            showToast(detPhysChk.message, 'error');
            return;
        }
    }
    const detRemChk = lgueOptionalText(remarks, 'Remarks');
    if (!detRemChk.ok) {
        showToast(detRemChk.message, 'error');
        return;
    }

    const payload = {
        id: currentDetailItemId,
        property_number: property_number.toUpperCase() === 'NONE' ? 'NONE' : property_number,
        article,
        description: descOut,
        unit_of_measure,
        unit_value: uv,
        quantity,
        quantity_physical_count: qtyPhysical,
        office_id,
        acquisition_date,
        status,
        condition_status,
        remarks: detRemChk.value,
        item_group: item_group || ''
    };

    const result = await runBusy('detail-btn-save', () => apiPut('items.php', payload));
    if (!result) return;
    if (result.success) {
        showToast('Item updated');
        await viewItemDetail(currentDetailItemId);
        refreshAfterWrite();
    } else {
        showToast(result.message || 'Update failed', 'error');
    }
}

// View item detail
async function viewItemDetail(id) {
    const content = document.getElementById('detail-content');
    if (content) content.innerHTML = '<p class="muted">Loading…</p>';
    openModal('detail-modal');
    const result = await apiGet(`items.php?id=${id}`);
    if (result.success && result.data) {
        const item = result.data;
        currentDetailItemId = id;
        currentDetailItem = item;
        if (content) content.innerHTML = detailViewHtml(item);
        setDetailModalFooterMode(userPermissions.canEdit ? 'view' : 'readonly', item);
        loadItemHistory(id);
    } else if (content) {
        content.innerHTML = '<p class="muted">Could not load this item.</p>';
    }
}

// Mark Unserviceable action launched from Property Item Details
async function markUnserviceableFromDetail() {
    if (!checkPermission('create')) return;
    if (!currentDetailItemId) return;
    closeModal('detail-modal');
    openModal('unserv-modal');
    await populateUnservSelect();
    if (typeof unsPickSelect === 'function') {
        const id = Number(currentDetailItemId);
        if (currentDetailItem && !(__unsPickItems || []).some((row) => Number(row.id) === id)) {
            __unsPickItems.unshift({
                id,
                property_number: currentDetailItem.property_number,
                description: currentDetailItem.description,
                article: currentDetailItem.article,
                office_name: currentDetailItem.office_name,
                office_id: currentDetailItem.office_id,
            });
        }
        unsPickSelect(id, true);
    }
    const dateEl = document.getElementById('uns-date');
    if (dateEl && !dateEl.value) dateEl.value = new Date().toISOString().slice(0, 10);
}

// Transfer to Office action launched from Property Item Details
async function openTransferToOfficeFromDetail() {
    if (!checkPermission('edit')) return;
    if (!currentDetailItemId || !currentDetailItem) return;
    if (!offices || offices.length === 0) await loadOffices();

    const labelEl = document.getElementById('xfer-item-label');
    if (labelEl) {
        labelEl.value = `${currentDetailItem.property_number} — ${currentDetailItem.description || ''}`;
    }
    const officeSel = document.getElementById('xfer-office');
    if (officeSel) {
        const currentOfficeId = String(currentDetailItem.office_id);
        officeSel.innerHTML = '<option value="">— select office —</option>' +
            (offices || [])
                .filter(o => String(o.id) !== currentOfficeId)
                .map(o => `<option value="${o.id}">${escAttr(o.name)}</option>`)
                .join('');
    }
    const remarksEl = document.getElementById('xfer-remarks');
    if (remarksEl) remarksEl.value = '';

    closeModal('detail-modal');
    openModal('transfer-office-modal');
}

async function confirmTransferToOffice() {
    if (!checkPermission('edit')) return;
    if (!currentDetailItemId) return;

    const officeSel = document.getElementById('xfer-office');
    const officeId = officeSel ? parseInt(officeSel.value, 10) : 0;
    const remarks = document.getElementById('xfer-remarks') ? document.getElementById('xfer-remarks').value.trim() : '';

    if (!officeId) {
        showToast('Please select a destination office', 'error');
        return;
    }
    const xferRemChk = lgueOptionalText(remarks, 'Remarks');
    if (!xferRemChk.ok) {
        showToast(xferRemChk.message, 'error');
        return;
    }

    const payload = {
        id: currentDetailItemId,
        office_id: officeId,
        status: 'Transferred',
    };
    if (xferRemChk.value) payload.remarks = xferRemChk.value;

    const result = await runBusy('xfer-save-btn', () => apiPut('items.php', payload));
    if (!result) return;
    if (result.success) {
        showToast('Item transferred to new office');
        closeModal('transfer-office-modal');
        refreshAfterWrite();
    } else {
        showToast(result.message || 'Transfer failed', 'error');
    }
}

// Export PDF
function exportAllOfficesPDF() {
    showToast('PDF export feature coming soon', 'info');
}


// ═══════════════════════════════════════════════════════════
// UI ENHANCEMENTS - Collapsible Sidebar & User Profile
// ═══════════════════════════════════════════════════════════

(function() {
    'use strict';
    
    // Initialize UI enhancements when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUIEnhancements);
    } else {
        initUIEnhancements();
    }
    
    function initUIEnhancements() {
        // Admin profile lives in the sidebar (Sign out). Do not rebuild the topbar dropdown.
        initScrollToTop();
        console.log('✨ UI Enhancements loaded');
    }
    
    // ─────────────────────────────────────────────────────────
    // User Profile Display
    // ─────────────────────────────────────────────────────────
    function initUserProfile() {
        if (document.querySelector('.tb-who')) return;
        const userDataEl = document.querySelector('[data-user-name]');
        if (!userDataEl) return;
        
        const userName = userDataEl.getAttribute('data-user-name') || 'User';
        const userRole = userDataEl.getAttribute('data-user-role') || 'Staff';
        
        // Get initials
        const initials = userName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        
        // Create user profile section
        const topbarRight = document.querySelector('.tb-right');
        if (!topbarRight) return;
        
        // Check if already exists
        if (document.querySelector('.user-profile')) return;
        
        const userProfile = document.createElement('div');
        userProfile.className = 'user-profile';
        userProfile.innerHTML = `
            <div class="profile-circle" title="User Profile">${initials}</div>
            <div class="user-info-text">
                <div class="user-name">${userName}</div>
                <div class="user-role">${userRole}</div>
            </div>
        `;
        
        // Insert before first button or at the beginning
        const firstBtn = topbarRight.querySelector('.btn');
        if (firstBtn) {
            topbarRight.insertBefore(userProfile, firstBtn);
        } else {
            topbarRight.prepend(userProfile);
        }
        
        // Add divider after profile
        const divider = document.createElement('div');
        divider.className = 'tb-divider';
        userProfile.after(divider);
    }
    
    // ─────────────────────────────────────────────────────────
    // Scroll to Top Button
    // ─────────────────────────────────────────────────────────
    function initScrollToTop() {
        const content = document.querySelector('.content');
        if (!content) return;
        
        // Create button
        const scrollBtn = document.createElement('button');
        scrollBtn.className = 'scroll-to-top';
        scrollBtn.innerHTML = '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 12V2M3 6L7 2L11 6"/></svg>';
        scrollBtn.title = 'Scroll to Top';
        scrollBtn.style.cssText = `
            position:fixed;bottom:30px;right:30px;width:44px;height:44px;
            border-radius:50%;background:var(--ink);color:#fff;border:none;
            cursor:pointer;opacity:0;visibility:hidden;transition:all 0.3s ease;
            z-index:99;box-shadow:0 4px 12px rgba(0,0,0,0.2);display:flex;
            align-items:center;justify-content:center;
        `;
        document.body.appendChild(scrollBtn);
        
        // Show/hide on scroll
        content.addEventListener('scroll', function() {
            if (content.scrollTop > 300) {
                scrollBtn.style.opacity = '1';
                scrollBtn.style.visibility = 'visible';
            } else {
                scrollBtn.style.opacity = '0';
                scrollBtn.style.visibility = 'hidden';
            }
        });
        
        // Scroll to top
        scrollBtn.addEventListener('click', function() {
            content.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Hover effect
        scrollBtn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px) scale(1.05)';
            this.style.boxShadow = '0 6px 20px rgba(0,0,0,0.3)';
        });
        
        scrollBtn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
        });
    }
    
})();
