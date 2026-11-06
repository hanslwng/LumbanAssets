// LGU Inventory System - Main JavaScript Application

// Global state
let currentPage = 'dashboard';
let offices = [];
let allItems = [];
let seItemsByOffice = {};
let ppeItemsByOffice = {};
let currentDetailItemId = null;
let selectedReturnAction = null;

// User permissions
let userPermissions = {
    canCreate: false,
    canEdit: false,
    canDelete: false,
    isViewer: false
};

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s === null || s === undefined ? '' : String(s);
    return d.innerHTML;
}

// Load user permissions on page load
document.addEventListener('DOMContentLoaded', async function() {
    const userData = document.getElementById('userData');
    if (userData) {
        userPermissions.canCreate = userData.dataset.canCreate === '1';
        userPermissions.canEdit = userData.dataset.canEdit === '1';
        userPermissions.canDelete = userData.dataset.canDelete === '1';
        userPermissions.isViewer = userData.dataset.isViewer === '1';
    }

    const staffPortal = document.body.classList.contains('staff-portal');
    if (staffPortal) {
        await loadOffices();
        if (typeof window.loadProcurement === 'function') {
            await window.loadProcurement();
        }
        return;
    }

    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        const app = document.querySelector('.app');
        if (app) {
            app.classList.add('collapsed');
        }
    }

    loadOffices();
    loadDashboard();
    setTodayDate();
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
// Sidebar Toggle Function
// ─────────────────────────────────────────────────────────
function toggleSidebar() {
    const app = document.querySelector('.app');
    if (!app) return;
    
    app.classList.toggle('collapsed');
    
    // Save state to localStorage
    const isCollapsed = app.classList.contains('collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
    
    console.log('Sidebar toggled:', isCollapsed ? 'collapsed' : 'expanded');
}

// Navigation
function nav(page, event) {
    // Remove active class from all pages and sidebar items
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.sb-item').forEach(item => item.classList.remove('active'));
    
    // Add active class to selected page
    document.getElementById('pg-' + page).classList.add('active');
    
    // Add active class to clicked sidebar item if event is provided
    if (event && event.target) {
        const clickedItem = event.target.closest('.sb-item');
        if (clickedItem) {
            clickedItem.classList.add('active');
        }
    }
    
    currentPage = page;
    
    // Update topbar title
    const titles = {
        'dashboard': ['Dashboard', 'Property inventory overview'],
        'semi': ['Semi-Expendable', 'Items below ₱50,000 grouped by office'],
        'ppe': ['PPE', 'Property, Plant & Equipment (₱50,000 & above)'],
        'issuance': ['Issuance Records', 'PAR forms'],
        'returns': ['Returns', 'Returned items tracking'],
        'unserviceable': ['Unserviceable', 'Damaged or unusable items'],
        'disposal': ['Disposal Records', 'Disposed items'],
        'procurement': ['Procurement', 'PR · Quotations · PO · AIR workflow']
    };
    
    if (titles[page]) {
        document.getElementById('tb-title').textContent = titles[page][0];
        document.getElementById('tb-sub').textContent = titles[page][1];
    }
    
    // Load page data
    loadPageData(page);
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
        case 'procurement':
            loadProcurement();
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
}

// Load dashboard
async function loadDashboard() {
    const result = await apiGet('dashboard.php');
    if (result.success) {
        renderDashboardStats(result.stats);
        renderRecentItems(result.recent_items);
        renderRecentTransactions(result.recent_transactions);
        renderAttentionItems(result.attention_items);
    }
}

// Render dashboard stats
function renderDashboardStats(stats) {
    const statRow = document.getElementById('stat-row');
    statRow.innerHTML = `
        <div class="stat" onclick="nav('semi', event)">
            <div class="stat-lbl">Semi-Expendable</div>
            <div class="stat-val">${stats.se_count || 0}</div>
            <div class="stat-sub">Below ₱50k</div>
        </div>
        <div class="stat" onclick="nav('ppe', event)">
            <div class="stat-lbl">PPE</div>
            <div class="stat-val">${stats.ppe_count || 0}</div>
            <div class="stat-sub">₱50k & above</div>
        </div>
        <div class="stat" onclick="nav('issuance', event)">
            <div class="stat-lbl">Issued</div>
            <div class="stat-val">${stats.issued_count || 0}</div>
            <div class="stat-sub">Currently issued</div>
        </div>
        <div class="stat" onclick="nav('unserviceable', event)">
            <div class="stat-lbl">Unserviceable</div>
            <div class="stat-val">${stats.unserviceable_count || 0}</div>
            <div class="stat-sub">Needs attention</div>
        </div>
        <div class="stat" onclick="nav('disposal', event)">
            <div class="stat-lbl">Disposed</div>
            <div class="stat-val">${stats.disposed_count || 0}</div>
            <div class="stat-sub">Disposal records</div>
        </div>
    `;
}

// Render recent items
function renderRecentItems(items) {
    const container = document.getElementById('dash-recent');
    if (!items || items.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No items yet</p></div>';
        return;
    }
    
    container.innerHTML = `<table><thead><tr><th>Property #</th><th>Article</th><th>Description</th><th>Category</th><th>Office</th></tr></thead><tbody>
        ${items.map(item => `
            <tr onclick="viewItemDetail(${item.id})">
                <td class="mono">${item.property_number}</td>
                <td>${gsoDisplayArticle(item)}</td>
                <td>${item.description}</td>
                <td><span class="badge b-${item.category.toLowerCase()}">${item.category}</span></td>
                <td>${item.office_name}</td>
            </tr>
        `).join('')}
    </tbody></table>`;
}

// Render recent transactions
function renderRecentTransactions(txns) {
    const container = document.getElementById('dash-txn');
    if (!txns || txns.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📋</div><p>No transactions yet</p></div>';
        return;
    }
    
    container.innerHTML = `<table><thead><tr><th>Form</th><th>Item</th><th>Issued To</th><th>Date</th></tr></thead><tbody>
        ${txns.map(txn => `
            <tr>
                <td class="mono">${txn.form_number}</td>
                <td>${txn.description}</td>
                <td>${txn.issued_to}</td>
                <td>${formatDate(txn.issue_date)}</td>
            </tr>
        `).join('')}
    </tbody></table>`;
}

// Render attention items
function renderAttentionItems(items) {
    const container = document.getElementById('dash-alerts');
    if (!items || items.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">✓</div><p>No items need attention</p></div>';
        return;
    }
    
    container.innerHTML = `<table><thead><tr><th>Property #</th><th>Description</th><th>Status</th><th>Condition</th><th>Office</th></tr></thead><tbody>
        ${items.map(item => `
            <tr onclick="viewItemDetail(${item.id})">
                <td class="mono">${item.property_number}</td>
                <td>${item.description}</td>
                <td><span class="badge b-${item.status.toLowerCase().replace(' ', '')}">${item.status}</span></td>
                <td><span class="badge b-${item.condition_status.toLowerCase()}">${item.condition_status}</span></td>
                <td>${item.office_name}</td>
            </tr>
        `).join('')}
    </tbody></table>`;
}

// Modal functions
function openModal(id) {
    document.getElementById(id).classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
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
    window.location.href = '/lgu_invent/logout';
}

/**
 * Full-page shell loader: dismiss after window "load" (document + stylesheets + images + synchronous scripts).
 * Minimum visible time avoids a flash on warm cache; failsafe covers stalled networks.
 */
(function setupAppLoadingScreen() {
    const el = document.getElementById('app-loading-screen');
    if (!el) return;

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
        : (host === 'localhost' || host === '127.0.0.1' ? 3000 : 580);
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

    window.addEventListener(
        'load',
        () => {
            scheduleDismiss();
        },
        { once: true },
    );

    window.setTimeout(() => {
        if (!finished) {
            scheduleDismiss();
        }
    }, failSafeMs);

    if (document.readyState === 'complete') {
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
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Format currency
function formatCurrency(amount) {
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

/** Leading list index in article text e.g. "1. Chair", "2) Desk", "10. Shelf" — for display order. */
function gsoArticleLeadIndex(article) {
    if (article == null || typeof article !== 'string') return null;
    const m = String(article).trim().match(/^(\d+)\s*[\.\)]\s*/);
    return m ? parseInt(m[1], 10) : null;
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
        const na = effMap.get(String(a.id)) ?? gsoArticleLeadIndex(a.article);
        const nb = effMap.get(String(b.id)) ?? gsoArticleLeadIndex(b.article);
        if (na != null && nb != null && na !== nb) return na - nb;
        if (na != null && nb == null) return -1;
        if (na == null && nb != null) return 1;
        const sa = parseInt(a.sort_order, 10);
        const sb = parseInt(b.sort_order, 10);
        const oa = Number.isFinite(sa) ? sa : 0;
        const ob = Number.isFinite(sb) ? sb : 0;
        if (oa !== ob) return oa - ob;
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
    return order.map(k => ({
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
    return order.map(k => {
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
function gsoTableColCount(showOffice, withDrag) {
    const bodyCols = showOffice ? 11 : 10;
    return withDrag ? bodyCols + 1 : bodyCols;
}

/** Section title spans Article–U/M; book subtotal sits in the Unit Value column (paper form layout). */
function gsoSectionHeaderRow(showOffice, withDrag, title, bookTotal) {
    const safeTitle = escAttr(title);
    const sub = formatCurrency(bookTotal);
    let html = `<tr class="gso-section-head">`;
    if (withDrag) {
        html += `<td class="gso-drag-col"></td>`;
    }
    html += `<td colspan="4" class="gso-section-title">${safeTitle}</td>`;
    html += `<td class="gso-section-subtotal" style="text-align:right">${sub}</td>`;
    const restAfterUnitValue = showOffice ? 6 : 5;
    for (let i = 0; i < restAfterUnitValue; i++) {
        html += '<td></td>';
    }
    html += `</tr>`;
    return html;
}

function gsoTableHeadHtml(showOffice, withDrag) {
    const dragTh = withDrag ? '<th rowspan="2" class="gso-drag-col" title="Reorder"> </th>' : '';
    const officeTh = showOffice ? '<th rowspan="2">Office</th>' : '';
    return `<thead>
      <tr>
        ${dragTh}
        <th rowspan="2">Article</th>
        <th rowspan="2">Description</th>
        <th rowspan="2">Property No.</th>
        <th rowspan="2">Unit of Measure</th>
        <th rowspan="2" style="text-align:right">Unit Value</th>
        <th rowspan="2" style="text-align:center">Qty (Property Card)</th>
        <th rowspan="2" style="text-align:center">Qty (Physical Count)</th>
        <th colspan="2" style="text-align:center">Shortage / Overage</th>
        ${officeTh}
        <th rowspan="2">Remarks</th>
      </tr>
      <tr>
        <th style="text-align:center">Quantity</th>
        <th style="text-align:right">Value</th>
      </tr>
    </thead>`;
}

function gsoItemRowHtml(item, showOffice, withDrag, articleOverride) {
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
    let articleTd;
    if (articleOverride !== undefined) {
        articleTd = articleOverride === '' ? '' : escAttr(articleOverride);
    } else {
        articleTd = escAttr(gsoDisplayArticle(item));
    }
    return `
      <tr data-item-id="${item.id}">
        ${dragTd}
        <td class="gso-art-cell">${articleTd}</td>
        <td>${item.description || '—'}</td>
        <td class="mono">${item.property_number}</td>
        <td>${uom}</td>
        <td style="text-align:right">${formatCurrency(item.unit_value)}</td>
        <td style="text-align:center">${gsoBookQty(item)}</td>
        <td style="text-align:center"${physTitle}>${physQtyDisplay}</td>
        <td style="text-align:center">${varQtyDisplay}</td>
        <td style="text-align:right">${varValDisplay}</td>
        ${officeTd}
        <td>${rem}</td>
      </tr>`;
}

function renderGsoTableShell(showOffice, withDrag, tbodiesInnerHtml) {
    return `<table class="gso-inventory-table">${gsoTableHeadHtml(showOffice, withDrag)}${tbodiesInnerHtml}</table>`;
}

function renderOfficeItemsTable(items, category, officeId, disableDrag, mixedCategory) {
    if (!items || items.length === 0) {
        return '<p style="padding:10px;color:var(--text3)">No items in this office</p>';
    }
    const withDrag = !!(userPermissions.canEdit && !disableDrag);
    const showOffice = false;
    const catUpper = String(category || '').toUpperCase();
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
            tbodies += gsoSectionHeaderRow(showOffice, withDrag, seg.title, sub);
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
                tbodies += gsoItemRowHtml(it, showOffice, withDrag, artOverride);
            } else {
                tbodies += gsoItemRowHtml(it, showOffice, withDrag);
            }
        }
        tbodies += '</tbody>';
    }
    return `<div class="gso-table-wrap" data-gso-root="1">${renderGsoTableShell(showOffice, withDrag, tbodies)}</div>`;
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
            if (!tr || e.target.closest('.gso-drag-handle')) return;
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
            if (!tr || e.target.closest('.gso-section-head')) return;
            viewItemDetail(parseInt(tr.dataset.itemId, 10));
        });
    });
}

function wireGsoTablesIn(container) {
    if (!container) return;
    container.querySelectorAll('[data-gso-root="1"]').forEach(bindGsoTableRoot);
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
    const raw = amtEl ? String(amtEl.value ?? '').trim() : '';
    const parsed = raw === '' ? 0 : parseFloat(raw);
    const amount = Number.isFinite(parsed) ? parsed : NaN;
    const office = offEl ? offEl.value : '';
    const classifyBox = document.getElementById('e-classify');
    const propNum = document.getElementById('e-propnum');
    const year = new Date().getFullYear();
    const category = (Number.isFinite(amount) && amount >= 50000) ? 'PPE' : 'SE';

    if (classifyBox) {
        if (raw !== '' && !Number.isFinite(amount)) {
            classifyBox.innerHTML = '<span class="badge b-se">SE</span> <span style="color:var(--text3)">Enter a valid amount or leave blank for SE</span>';
        } else if (Number.isFinite(amount) && amount >= 50000) {
            classifyBox.innerHTML = `<span class="badge b-ppe">PPE</span> Property, Plant & Equipment (₱50,000 & above)`;
        } else {
            classifyBox.innerHTML = '<span class="badge b-se">SE</span> Semi-Expendable (below ₱50,000, blank, or ₱0)';
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
    if (!description && article) {
        description = article;
    }
    if (!description) {
        description = '—';
    }

    const unit_of_measure = encodeFieldValue('e-uom', []).trim() || 'pc';
    const amtRaw = encodeFieldValue('e-amt').trim();
    const unit_value = amtRaw === '' || isNaN(parseFloat(amtRaw)) ? 0 : parseFloat(amtRaw);
    if (unit_value < 0) {
        showToast('Unit value cannot be negative', 'error');
        return;
    }

    let qtyPcStr = encodeFieldValue('e-qty-pc', ['e-qty']).trim();
    let quantity = qtyPcStr === '' ? 1 : parseInt(qtyPcStr, 10);
    if (!quantity || quantity < 1 || isNaN(quantity)) {
        quantity = 1;
    }

    const officeStr = encodeFieldValue('e-off');
    const office_id = parseInt(officeStr, 10);

    const acquisition_date = encodeFieldValue('e-date');
    const remarks = encodeFieldValue('e-remarks');
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
    if (qtyPhysRaw !== '' && (qtyPhysical === null || isNaN(qtyPhysical) || qtyPhysical < 0)) {
        showToast('Physical count must be a valid number', 'error');
        return;
    }

    const result = await apiPost('items.php', data);
    if (result.success) {
        showToast('Item encoded successfully');
        closeModal('encode-modal');
        loadDashboard();
        loadPageData(currentPage);
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
    const result = await apiGet('items.php?category=SE');
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
        
        renderSEOfficeAccordion();
    }
}

function renderSEOfficeAccordion() {
    const container = document.getElementById('se-offices-container');
    
    if (Object.keys(seItemsByOffice).length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No semi-expendable items found</p></div>';
        return;
    }
    
    let html = '';
    for (const officeId in seItemsByOffice) {
        const office = seItemsByOffice[officeId];
        const itemCount = office.items.length;
        
        html += `
            <div class="office-accordion">
                <div class="office-header" onclick="toggleOfficeItems('se', ${officeId})">
                    <div class="office-name">
                        <svg class="office-chevron" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 3l4 4-4 4"/>
                        </svg>
                        ${office.office_name || 'Unknown Office'}
                    </div>
                    <div class="office-count">${itemCount} item${itemCount !== 1 ? 's' : ''}</div>
                </div>
                <div class="office-items" id="se-office-${officeId}">
                    ${renderOfficeItemsTable(office.items, 'se', officeId, false)}
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
    wireGsoTablesIn(container);
}

function toggleOfficeItems(category, officeId) {
    const itemsDiv = document.getElementById(`${category}-office-${officeId}`);
    const header = itemsDiv.previousElementSibling;
    
    if (itemsDiv.classList.contains('expanded')) {
        itemsDiv.classList.remove('expanded');
        header.classList.remove('active');
    } else {
        itemsDiv.classList.add('expanded');
        header.classList.add('active');
    }
}

function filterSEItems() {
    const search = document.getElementById('s-se')?.value.toLowerCase() || '';
    const status = document.getElementById('f-status-se')?.value || '';
    
    const container = document.getElementById('se-offices-container');
    let html = '';
    let hasResults = false;
    
    for (const officeId in seItemsByOffice) {
        const office = seItemsByOffice[officeId];
        
        // Filter items
        let filteredItems = office.items.filter(item => {
            const matchesSearch = !search || 
                item.property_number.toLowerCase().includes(search) ||
                item.description.toLowerCase().includes(search) ||
                (item.article || '').toLowerCase().includes(search) ||
                (item.item_group || '').toLowerCase().includes(search);
            const matchesStatus = !status || item.status === status;
            return matchesSearch && matchesStatus;
        });
        
        if (filteredItems.length > 0) {
            hasResults = true;
            html += `
                <div class="office-accordion">
                    <div class="office-header" onclick="toggleOfficeItems('se', ${officeId})">
                        <div class="office-name">
                            <svg class="office-chevron" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 3l4 4-4 4"/>
                            </svg>
                            ${office.office_name || 'Unknown Office'}
                        </div>
                        <div class="office-count">${filteredItems.length} item${filteredItems.length !== 1 ? 's' : ''}</div>
                    </div>
                    <div class="office-items" id="se-office-${officeId}">
                        ${renderOfficeItemsTable(filteredItems, 'se', officeId, !!search)}
                    </div>
                </div>
            `;
        }
    }
    
    if (!hasResults) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">🔍</div><p>No items match your search</p></div>';
    } else {
        container.innerHTML = html;
        wireGsoTablesIn(container);
    }
}

// Load PPE items with office grouping
async function loadPPE() {
    const result = await apiGet('items.php?category=PPE');
    if (result.success) {
        // Group items by office
        ppeItemsByOffice = {};
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
        });
        
        renderPPEOfficeAccordion();
    }
}

function renderPPEOfficeAccordion() {
    const container = document.getElementById('ppe-offices-container');
    
    if (Object.keys(ppeItemsByOffice).length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No PPE items found</p></div>';
        return;
    }
    
    let html = '';
    for (const officeId in ppeItemsByOffice) {
        const office = ppeItemsByOffice[officeId];
        const itemCount = office.items.length;
        
        html += `
            <div class="office-accordion">
                <div class="office-header" onclick="toggleOfficeItems('ppe', ${officeId})">
                    <div class="office-name">
                        <svg class="office-chevron" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 3l4 4-4 4"/>
                        </svg>
                        ${office.office_name || 'Unknown Office'}
                    </div>
                    <div class="office-count">${itemCount} item${itemCount !== 1 ? 's' : ''}</div>
                </div>
                <div class="office-items" id="ppe-office-${officeId}">
                    ${renderOfficeItemsTable(office.items, 'ppe', officeId, false)}
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
    wireGsoTablesIn(container);
}

function filterPPEItems() {
    const search = document.getElementById('s-ppe')?.value.toLowerCase() || '';
    const status = document.getElementById('f-status-ppe')?.value || '';
    
    const container = document.getElementById('ppe-offices-container');
    let html = '';
    let hasResults = false;
    
    for (const officeId in ppeItemsByOffice) {
        const office = ppeItemsByOffice[officeId];
        
        // Filter items
        let filteredItems = office.items.filter(item => {
            const matchesSearch = !search || 
                item.property_number.toLowerCase().includes(search) ||
                item.description.toLowerCase().includes(search) ||
                (item.article || '').toLowerCase().includes(search) ||
                (item.item_group || '').toLowerCase().includes(search);
            const matchesStatus = !status || item.status === status;
            return matchesSearch && matchesStatus;
        });
        
        if (filteredItems.length > 0) {
            hasResults = true;
            html += `
                <div class="office-accordion">
                    <div class="office-header" onclick="toggleOfficeItems('ppe', ${officeId})">
                        <div class="office-name">
                            <svg class="office-chevron" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 3l4 4-4 4"/>
                            </svg>
                            ${office.office_name || 'Unknown Office'}
                        </div>
                        <div class="office-count">${filteredItems.length} item${filteredItems.length !== 1 ? 's' : ''}</div>
                    </div>
                    <div class="office-items" id="ppe-office-${officeId}">
                        ${renderOfficeItemsTable(filteredItems, 'ppe', officeId, !!search)}
                    </div>
                </div>
            `;
        }
    }
    
    if (!hasResults) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">🔍</div><p>No items match your search</p></div>';
    } else {
        container.innerHTML = html;
        wireGsoTablesIn(container);
    }
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

/** COA physical count — SE fills the client RPCSE file and launches Microsoft Excel. PPE opens the editable print tab. */
async function openPhysicalCountReport(category) {
    if (category !== 'SE' && category !== 'PPE') return;
    const statusEl = document.getElementById(category === 'SE' ? 'f-status-se' : 'f-status-ppe');
    const searchEl = document.getElementById(category === 'SE' ? 's-se' : 's-ppe');
    if (category === 'SE') {
        const params = new URLSearchParams({ category: 'SE', format: 'open-excel' });
        if (statusEl && statusEl.value) params.set('status', statusEl.value);
        if (searchEl && searchEl.value.trim()) params.set('search', searchEl.value.trim());
        try {
            showToast('Filling the RPCSE workbook and opening Microsoft Excel…', 'info');
            const response = await fetch('print/print-physical-count-report.php?' + params.toString(), {
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => null);
            if (data && data.ok) {
                showToast(data.message || 'Opened in Microsoft Excel', 'success');
                return;
            }
            if (data && data.protocol_url) {
                window.location.href = data.protocol_url;
                showToast('Allow this site to open Microsoft Excel if the browser asks', 'info');
                return;
            }
            params.set('format', 'xlsx');
            window.location.href = 'print/print-physical-count-report.php?' + params.toString();
            showToast((data && data.message) || 'Excel did not start. The file will download — open it in Microsoft Excel, not Chrome.', 'error');
        } catch (e) {
            showToast('Could not open Excel: ' + e.message, 'error');
        }
        return;
    }
    const params = new URLSearchParams({ category: category, format: 'html' });
    if (statusEl && statusEl.value) params.set('status', statusEl.value);
    if (searchEl && searchEl.value.trim()) params.set('search', searchEl.value.trim());
    const win = window.open('print/print-physical-count-report.php?' + params.toString(), '_blank');
    if (!win) {
        showToast('Allow pop-ups to open the physical count report in a new tab', 'error');
    }
}

function openPhysicalCountEditor(category) {
    openPhysicalCountReport(category);
}

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

/** Re-upload an edited RPCPPE/RPCSE .xlsx to update physical counts / remarks. */
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

// Load issuances
async function loadIssuances() {
    const search = document.getElementById('s-iss')?.value || '';
    const formType = document.getElementById('f-iss-type')?.value || '';
    const officeId = document.getElementById('f-iss-office')?.value || '';
    
    let url = 'issuance.php?';
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (formType) url += `form_type=${formType}&`;
    if (officeId) url += `office_id=${officeId}&`;
    
    const result = await apiGet(url);
    if (result.success) {
        renderIssuancesTable(result.data);
    }
}

function renderIssuancesTable(issuances) {
    const container = document.getElementById('tbl-iss');
    if (!issuances || issuances.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📋</div><p>No issuance records found</p></div>';
        return;
    }
    
    container.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Form #</th>
                    <th>Type</th>
                    <th>Property #</th>
                    <th>Description</th>
                    <th>Issued To</th>
                    <th>Office</th>
                    <th>Date</th>
                    <th class="no-print">Print</th>
                </tr>
            </thead>
            <tbody>
                ${issuances.map(iss => `
                    <tr>
                        <td class="mono">${iss.form_number}</td>
                        <td><span class="badge b-${iss.form_type.toLowerCase()}">${iss.form_type}</span></td>
                        <td class="mono">${iss.property_number || '—'}</td>
                        <td>${iss.description || '—'}</td>
                        <td>${iss.issued_to}</td>
                        <td>${iss.office_name || '—'}</td>
                        <td>${formatDate(iss.issue_date)}</td>
                        <td class="no-print"><a class="link-print" href="print/print-issuance.php?id=${iss.id}" target="_blank" rel="noopener">Print</a></td>
                    </tr>
                `).join('')}
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
async function loadUnserviceable() {
    const result = await apiGet('unserviceable.php');
    if (result.success) {
        renderUnserviceableTable(result.data);
    }
}

function renderUnserviceableTable(items) {
    const container = document.getElementById('tbl-uns');
    if (!items || items.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">⚠️</div><p>No unserviceable items</p></div>';
        return;
    }
    
    container.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Property #</th>
                    <th>Description</th>
                    <th>Office</th>
                    <th>Reason</th>
                    <th>Marked Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(item => `
                    <tr>
                        <td class="mono">${item.property_number || '—'}</td>
                        <td>${item.description || '—'}</td>
                        <td>${item.office_name || '—'}</td>
                        <td>${item.reason || '—'}</td>
                        <td>${formatDate(item.marked_date)}</td>
                        <td><span class="badge b-${(item.disposal_status || 'Pending').toLowerCase().replace(' ', '')}">${item.disposal_status || 'Pending'}</span></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

// Load disposal records
async function loadDisposal() {
    const result = await apiGet('unserviceable.php');
    if (result.success) {
        const disposed = result.data.filter(item => item.disposal_status === 'Disposed');
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


// Render table (for filters)
function rt(page) {
    switch(page) {
        case 'semi': filterSEItems(); break;
        case 'ppe': filterPPEItems(); break;
        case 'iss': loadIssuances(); break;
        case 'ret': loadReturns(); break;
    }
}

// Populate issue select
async function populateIssueSelect() {
    const result = await apiGet('items.php?status=Active');
    const select = document.getElementById('iss-item');
    
    if (result.success && result.data.length > 0) {
        select.innerHTML = '<option value="">— select item —</option>' +
            result.data.map(item => 
                `<option value="${item.id}" data-category="${item.category}" data-office-id="${item.office_id}" data-office-name="${item.office_name || 'Unknown Office'}">${item.property_number} — ${gsoDisplayArticle(item)}: ${item.description}</option>`
            ).join('');
    } else {
        select.innerHTML = '<option value="">No active items available</option>';
    }
}

// Populate return select
async function populateReturnSelect() {
    const result = await apiGet('items.php?status=Issued');
    const select = document.getElementById('ret-item');
    
    if (result.success && result.data.length > 0) {
        select.innerHTML = '<option value="">— select issued item —</option>' +
            result.data.map(item => 
                `<option value="${item.id}">${item.property_number} - ${item.description}</option>`
            ).join('');
    } else {
        select.innerHTML = '<option value="">No issued items available</option>';
    }
}

// Populate unserviceable select
async function populateUnservSelect() {
    const result = await apiGet('items.php?status=Active');
    const select = document.getElementById('uns-item');
    
    if (result.success && result.data.length > 0) {
        select.innerHTML = '<option value="">— select item —</option>' +
            result.data.map(item => 
                `<option value="${item.id}">${item.property_number} - ${item.description}</option>`
            ).join('');
    } else {
        select.innerHTML = '<option value="">No items available</option>';
    }
}

// Update issue form
function updateIssueForm() {
    const select = document.getElementById('iss-item');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        const officeName = option.getAttribute('data-office-name');
        // All issuances now use the PAR (Property Acknowledgement Receipt) form.
        document.getElementById('iss-type').value = 'PAR';
        document.getElementById('iss-office').value = officeName || '';
    } else {
        document.getElementById('iss-type').value = '';
        document.getElementById('iss-office').value = '';
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
    const itemId = document.getElementById('iss-item').value;
    const formNumber = document.getElementById('iss-num').value.trim();
    const issuedTo = document.getElementById('iss-to').value.trim();
    const position = document.getElementById('iss-pos').value;
    let issueDate = document.getElementById('iss-date').value;
    const remarks = document.getElementById('iss-remarks').value;
    
    if (!itemId || !formNumber || !issuedTo) {
        showToast('Please fill in all required fields (item, form number, issued to)', 'error');
        return;
    }
    if (!issueDate) {
        issueDate = new Date().toISOString().split('T')[0];
        const dateEl = document.getElementById('iss-date');
        if (dateEl) dateEl.value = issueDate;
    }
    
    const data = {
        property_item_id: parseInt(itemId, 10),
        form_number: formNumber,
        issued_to: issuedTo,
        position: position,
        issue_date: issueDate,
        remarks: remarks
    };
    
    const result = await apiPost('issuance.php', data);
    if (result.success) {
        showToast('Item issued successfully');
        closeModal('issue-modal');
        loadDashboard();
        loadPageData(currentPage);
    } else {
        showToast(result.message || 'Failed to issue item', 'error');
    }
}

// Save return
async function saveReturn() {
    const itemId = document.getElementById('ret-item').value;
    const returnedBy = document.getElementById('ret-by').value;
    const returnDate = document.getElementById('ret-date').value;
    const condition = document.getElementById('ret-cond').value;
    const remarks = document.getElementById('ret-remarks').value;
    
    if (!itemId || !returnedBy || !selectedReturnAction) {
        showToast('Please fill in all required fields and select return action', 'error');
        return;
    }
    
    const data = {
        property_item_id: parseInt(itemId),
        issuance_id: 1, // This should be fetched from the item
        returned_by: returnedBy,
        return_date: returnDate,
        return_action: selectedReturnAction === 'uns' ? 'Unserviceable' : 'Transfer',
        condition_on_return: condition,
        remarks: remarks
    };
    
    if (selectedReturnAction === 'xfer') {
        const transferOffice = document.getElementById('ret-xfer-office').value;
        if (!transferOffice) {
            showToast('Please select transfer office', 'error');
            return;
        }
        data.transfer_to_office_id = parseInt(transferOffice);
    }
    
    const result = await apiPost('returns.php', data);
    if (result.success) {
        showToast('Return recorded successfully');
        closeModal('return-modal');
        loadDashboard();
        loadPageData(currentPage);
    } else {
        showToast(result.message || 'Failed to record return', 'error');
    }
}

// Save unserviceable
async function saveUnserviceable() {
    if (!checkPermission('create')) {
        return;
    }

    const itemId = document.getElementById('uns-item').value;
    const markedDate = document.getElementById('uns-date').value;
    const markedBy = document.getElementById('uns-by').value;
    const reason = document.getElementById('uns-reason').value;
    const remarks = document.getElementById('uns-remarks').value;
    
    if (!itemId || !reason) {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    
    const data = {
        property_item_id: parseInt(itemId),
        marked_date: markedDate,
        marked_by: markedBy,
        reason: reason,
        remarks: remarks
    };
    
    const result = await apiPost('unserviceable.php', data);
    if (result.success) {
        showToast('Item marked as unserviceable');
        closeModal('unserv-modal');
        loadDashboard();
        loadPageData(currentPage);
    } else {
        showToast(result.message || 'Failed to mark item', 'error');
    }
}

function setDetailModalFooterMode(mode) {
    const editBtn = document.getElementById('detail-btn-edit');
    const cancelBtn = document.getElementById('detail-btn-cancel');
    const saveBtn = document.getElementById('detail-btn-save');
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
                        <div class="pc-fv">${formatCurrency(item.unit_value)}</div>
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
                <p style="font-size:12px;color:var(--text3);margin:0 0 12px">Property number stays the same. SE/PPE follows unit value (≥ ₱50,000 = PPE).</p>
                <div class="form-grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                    <div class="fg full"><label>Property number</label><input type="text" value="${escAttr(item.property_number)}" readonly style="opacity:0.85"/></div>
                    <div class="fg"><label>Article <span style="color:var(--text3);font-weight:400">(optional)</span></label><input type="text" id="d-article" value="${escAttr(item.article || '')}"/></div>
                    <div class="fg"><label>Unit of Measure *</label><input type="text" id="d-uom" value="${escAttr(item.unit_of_measure || 'pc')}"/></div>
                    <div class="fg full"><label>Description *</label><input type="text" id="d-desc" value="${escAttr(item.description || '')}"/></div>
                    <div class="fg full"><label>Report section (optional)</label><input type="text" id="d-group" placeholder="e.g. OFFICE FIXTURES & FURNITURES" value="${escAttr(item.item_group || '')}"/></div>
                    <div class="fg"><label>Unit value (₱) *</label><input type="number" id="d-amt" min="0" step="0.01" value="${escAttr(item.unit_value)}"/></div>
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
        const uvNum = parseFloat(uv.value);
        const isPpeAmt = Number.isFinite(uvNum) && uvNum >= 50000;
        if (isPpeAmt && ph.value.trim() === '') {
            outQ.textContent = '—';
            outV.textContent = '—';
            return;
        }
        const mock = {
            quantity: pc.value,
            quantity_physical_count: ph.value.trim() === '' ? null : ph.value,
            unit_value: uv.value
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
        const v = parseFloat(amt.value);
        const num = Number.isFinite(v) ? v : 0;
        const cat = num >= 50000 ? 'PPE' : 'SE';
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
    const unit_of_measure = document.getElementById('d-uom').value.trim() || 'pc';
    const unit_value = parseFloat(document.getElementById('d-amt').value);
    const uv = Number.isFinite(unit_value) ? unit_value : 0;
    let quantity = qtyPcRaw === '' ? NaN : parseInt(qtyPcRaw, 10);
    if (uv >= 50000 && qtyPcRaw === '') {
        quantity = 1;
    }
    const office_id = parseInt(document.getElementById('d-off').value, 10);
    const acquisition_date = document.getElementById('d-date').value;
    const status = document.getElementById('d-status').value;
    const condition_status = document.getElementById('d-cond').value;
    const remarks = document.getElementById('d-remarks').value;
    const item_group = document.getElementById('d-group') ? document.getElementById('d-group').value.trim() : '';

    let descOut = description;
    if (!descOut && article) descOut = article;
    if (!descOut) descOut = '—';
    if (uv < 0) {
        showToast('Unit value cannot be negative', 'error');
        return;
    }
    if (!office_id || office_id <= 0 || !Number.isFinite(quantity) || quantity < 1) {
        showToast('Please fix office and quantity (property card)', 'error');
        return;
    }
    if (qtyPhysRaw !== '' && (qtyPhysical === null || isNaN(qtyPhysical) || qtyPhysical < 0)) {
        showToast('Physical count must be valid', 'error');
        return;
    }

    const payload = {
        id: currentDetailItemId,
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
        remarks,
        item_group: item_group || ''
    };

    const result = await apiPut('items.php', payload);
    if (result.success) {
        showToast('Item updated');
        await viewItemDetail(currentDetailItemId);
        loadPageData(currentPage);
        loadDashboard();
    } else {
        showToast(result.message || 'Update failed', 'error');
    }
}

// View item detail
async function viewItemDetail(id) {
    const result = await apiGet(`items.php?id=${id}`);
    if (result.success && result.data) {
        const item = result.data;
        currentDetailItemId = id;
        const content = document.getElementById('detail-content');
        content.innerHTML = detailViewHtml(item);
        setDetailModalFooterMode(userPermissions.canEdit ? 'view' : 'readonly');
        openModal('detail-modal');
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
        initUserProfile();
        initScrollToTop();
        console.log('✨ UI Enhancements loaded');
    }
    
    // ─────────────────────────────────────────────────────────
    // User Profile Display
    // ─────────────────────────────────────────────────────────
    function initUserProfile() {
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
