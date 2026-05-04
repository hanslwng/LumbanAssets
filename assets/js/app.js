// LGU Inventory System - Main JavaScript Application

// Global state
let currentPage = 'dashboard';
let offices = [];
let allItems = [];
let seItemsByOffice = {};
let ppeItemsByOffice = {};
let selectedReturnAction = null;

// Initialize app on page load
document.addEventListener('DOMContentLoaded', function() {
    loadOffices();
    loadDashboard();
    setTodayDate();
});

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
        'issuance': ['Issuance Records', 'PAR and ICS forms'],
        'returns': ['Returns', 'Returned items tracking'],
        'unserviceable': ['Unserviceable', 'Damaged or unusable items'],
        'disposal': ['Disposal Records', 'Disposed items'],
        'coa': ['COA Report', 'Commission on Audit report']
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
        case 'coa':
            renderCOA();
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
            showToast('Database not initialized. Please run setup-database.php', 'error');
            return { 
                success: false, 
                message: 'Database not initialized',
                hint: 'Please run setup-database.php to set up the database'
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
            showToast('Database error. Please check setup-database.php', 'error');
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

// Load offices
async function loadOffices() {
    const result = await apiGet('offices.php');
    if (result.success) {
        offices = result.data;
        populateOfficeSelects();
        updateBadges();
    }
}

// Populate office dropdowns
function populateOfficeSelects() {
    const selects = ['e-off', 'f-iss-office', 'ret-xfer-office'];
    selects.forEach(id => {
        const select = document.getElementById(id);
        if (select) {
            const currentValue = select.value;
            select.innerHTML = '<option value="">— select office —</option>' + 
                offices.map(o => `<option value="${o.id}">${o.name}</option>`).join('');
            if (currentValue) select.value = currentValue;
        }
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
        updateBadges(result.stats);
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

// Update sidebar badges
function updateBadges(stats) {
    if (stats) {
        document.getElementById('nb-se').textContent = stats.se_count || 0;
        document.getElementById('nb-ppe').textContent = stats.ppe_count || 0;
        document.getElementById('nb-iss').textContent = stats.issuance_count || 0;
        document.getElementById('nb-ret').textContent = stats.return_count || 0;
        document.getElementById('nb-uns').textContent = stats.unserviceable_count || 0;
        document.getElementById('nb-dis').textContent = stats.disposed_count || 0;
    }
}

// Render recent items
function renderRecentItems(items) {
    const container = document.getElementById('dash-recent');
    if (!items || items.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No items yet</p></div>';
        return;
    }
    
    container.innerHTML = `<table><thead><tr><th>Property #</th><th>Description</th><th>Category</th><th>Office</th></tr></thead><tbody>
        ${items.map(item => `
            <tr onclick="viewItemDetail(${item.id})">
                <td class="mono">${item.property_number}</td>
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

// Set today's date in date inputs
function setTodayDate() {
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = ['e-date', 'iss-date', 'ret-date', 'uns-date'];
    dateInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input && !input.value) input.value = today;
    });
}

// Live classification for encode modal
function liveClassify() {
    const amount = parseFloat(document.getElementById('e-amt').value) || 0;
    const office = document.getElementById('e-off').value;
    
    if (amount > 0) {
        const category = amount >= 50000 ? 'PPE' : 'SE';
        const year = new Date().getFullYear();
        const classifyBox = document.getElementById('e-classify');
        const propNum = document.getElementById('e-propnum');
        
        classifyBox.innerHTML = `<span class="badge b-${category.toLowerCase()}">${category}</span> ${category === 'PPE' ? 'Property, Plant & Equipment (₱50,000 & above)' : 'Semi-Expendable (below ₱50,000)'}`;
        propNum.textContent = office ? `${year}-${category}-XXXXXX` : '—';
    }
}

// Save new item
async function saveItem() {
    const data = {
        description: document.getElementById('e-name').value,
        unit_value: parseFloat(document.getElementById('e-amt').value),
        quantity: parseInt(document.getElementById('e-qty').value),
        office_id: parseInt(document.getElementById('e-off').value),
        acquisition_date: document.getElementById('e-date').value,
        remarks: document.getElementById('e-remarks').value
    };
    
    if (!data.description || !data.unit_value || !data.office_id) {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    
    const result = await apiPost('items.php', data);
    if (result.success) {
        showToast('Item encoded successfully');
        closeModal('encode-modal');
        loadDashboard();
        loadPageData(currentPage);
        // Reset form
        document.getElementById('e-name').value = '';
        document.getElementById('e-amt').value = '';
        document.getElementById('e-qty').value = '1';
        document.getElementById('e-off').value = '';
        document.getElementById('e-remarks').value = '';
    } else {
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
                <div class="card-hd"><span class="card-ttl">Office Items</span></div>
                <table>
                    <thead>
                        <tr>
                            <th>Property #</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${result.data.map(item => `
                            <tr onclick="viewItemDetail(${item.id})">
                                <td class="mono">${item.property_number}</td>
                                <td>${item.description}</td>
                                <td><span class="badge b-${item.category.toLowerCase()}">${item.category}</span></td>
                                <td>${formatCurrency(item.unit_value)}</td>
                                <td><span class="badge b-${item.status.toLowerCase()}">${item.status}</span></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
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
                    ${renderOfficeItemsTable(office.items, 'se')}
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
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

function renderOfficeItemsTable(items, category) {
    if (!items || items.length === 0) {
        return '<p style="padding:10px;color:var(--text3)">No items in this office</p>';
    }
    
    return `
        <table>
            <thead>
                <tr>
                    <th>Property #</th>
                    <th>Description</th>
                    <th>Unit Value</th>
                    <th>Qty</th>
                    <th>Status</th>
                    <th>Condition</th>
                    <th>Acquisition Date</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(item => `
                    <tr onclick="viewItemDetail(${item.id})" style="cursor:pointer">
                        <td class="mono">${item.property_number}</td>
                        <td>${item.description}</td>
                        <td>${formatCurrency(item.unit_value)}</td>
                        <td>${item.quantity}</td>
                        <td><span class="badge b-${item.status.toLowerCase()}">${item.status}</span></td>
                        <td><span class="badge b-${item.condition_status.toLowerCase()}">${item.condition_status}</span></td>
                        <td>${formatDate(item.acquisition_date)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
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
                item.description.toLowerCase().includes(search);
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
                        ${renderOfficeItemsTable(filteredItems, 'se')}
                    </div>
                </div>
            `;
        }
    }
    
    if (!hasResults) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">🔍</div><p>No items match your search</p></div>';
    } else {
        container.innerHTML = html;
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
                    ${renderOfficeItemsTable(office.items, 'ppe')}
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
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
                item.description.toLowerCase().includes(search);
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
                        ${renderOfficeItemsTable(filteredItems, 'ppe')}
                    </div>
                </div>
            `;
        }
    }
    
    if (!hasResults) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">🔍</div><p>No items match your search</p></div>';
    } else {
        container.innerHTML = html;
    }
}

// Render items table
function renderItemsTable(containerId, items) {
    const container = document.getElementById(containerId);
    if (!items || items.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No items found</p></div>';
        return;
    }
    
    container.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Property #</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Unit Value</th>
                    <th>Qty</th>
                    <th>Office</th>
                    <th>Status</th>
                    <th>Condition</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(item => `
                    <tr onclick="viewItemDetail(${item.id})">
                        <td class="mono">${item.property_number}</td>
                        <td>${item.description}</td>
                        <td><span class="badge b-${item.category.toLowerCase()}">${item.category}</span></td>
                        <td>${formatCurrency(item.unit_value)}</td>
                        <td>${item.quantity}</td>
                        <td>${item.office_name || '—'}</td>
                        <td><span class="badge b-${item.status.toLowerCase()}">${item.status}</span></td>
                        <td><span class="badge b-${item.condition_status.toLowerCase()}">${item.condition_status}</span></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
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
                        <td>${item.reason}</td>
                        <td>${formatDate(item.marked_date)}</td>
                        <td><span class="badge b-${item.disposal_status.toLowerCase().replace(' ', '')}">${item.disposal_status}</span></td>
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

// Render COA report
async function renderCOA() {
    const year = document.getElementById('coa-year')?.value || new Date().getFullYear();
    const result = await apiGet(`items.php?year=${year}`);
    
    if (result.success) {
        const container = document.getElementById('coa-body');
        const items = result.data;
        
        // Group by category
        const se = items.filter(i => i.category === 'SE');
        const ppe = items.filter(i => i.category === 'PPE');
        
        container.innerHTML = `
            <div class="coa-section">
                <div class="coa-sec-title">Semi-Expendable Items</div>
                ${renderCOATable(se)}
            </div>
            <div class="coa-section">
                <div class="coa-sec-title">Property, Plant & Equipment</div>
                ${renderCOATable(ppe)}
            </div>
        `;
    }
}

function renderCOATable(items) {
    if (!items || items.length === 0) {
        return '<p>No items in this category</p>';
    }
    
    const total = items.reduce((sum, item) => sum + (item.unit_value * item.quantity), 0);
    
    return `
        <table>
            <thead>
                <tr>
                    <th>Property Number</th>
                    <th>Description</th>
                    <th>Unit Value</th>
                    <th>Quantity</th>
                    <th>Total Value</th>
                    <th>Office</th>
                </tr>
            </thead>
            <tbody>
                ${items.map(item => `
                    <tr>
                        <td class="mono">${item.property_number}</td>
                        <td>${item.description}</td>
                        <td>${formatCurrency(item.unit_value)}</td>
                        <td>${item.quantity}</td>
                        <td>${formatCurrency(item.unit_value * item.quantity)}</td>
                        <td>${item.office_name || '—'}</td>
                    </tr>
                `).join('')}
                <tr style="font-weight: bold; background: #f8f8f8;">
                    <td colspan="4">TOTAL</td>
                    <td>${formatCurrency(total)}</td>
                    <td></td>
                </tr>
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
                `<option value="${item.id}" data-category="${item.category}" data-office="${item.office_id}">${item.property_number} - ${item.description}</option>`
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
        const category = option.getAttribute('data-category');
        const formType = category === 'PPE' ? 'PAR' : 'ICS';
        document.getElementById('iss-type').value = formType;
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
    const formNumber = document.getElementById('iss-num').value;
    const issuedTo = document.getElementById('iss-to').value;
    const position = document.getElementById('iss-pos').value;
    const issueDate = document.getElementById('iss-date').value;
    const remarks = document.getElementById('iss-remarks').value;
    
    if (!itemId || !formNumber || !issuedTo) {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    
    const data = {
        property_item_id: parseInt(itemId),
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

// View item detail
async function viewItemDetail(id) {
    const result = await apiGet(`items.php?id=${id}`);
    if (result.success && result.data) {
        const item = result.data;
        const modal = document.getElementById('detail-modal');
        const content = document.getElementById('detail-content');
        
        content.innerHTML = `
            <div class="pc-wrap">
                <div class="pc-top">
                    <div>
                        <div class="pc-gov">LGU Property Office</div>
                        <div class="pc-title">${item.description}</div>
                        <div class="pc-sub">${item.property_number}</div>
                    </div>
                    <span class="badge b-${item.category.toLowerCase()}">${item.category}</span>
                </div>
                <div class="pc-grid">
                    <div class="pc-f">
                        <div class="pc-fl">Unit Value</div>
                        <div class="pc-fv">${formatCurrency(item.unit_value)}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Quantity</div>
                        <div class="pc-fv">${item.quantity}</div>
                    </div>
                    <div class="pc-f">
                        <div class="pc-fl">Total Value</div>
                        <div class="pc-fv">${formatCurrency(item.unit_value * item.quantity)}</div>
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
                        <div class="pc-fl">Created</div>
                        <div class="pc-fv">${formatDate(item.created_at)}</div>
                    </div>
                </div>
                ${item.remarks ? `<div class="pc-f"><div class="pc-fl">Remarks</div><div class="pc-fv">${item.remarks}</div></div>` : ''}
            </div>
        `;
        
        openModal('detail-modal');
    }
}

// Export PDF
function exportAllOfficesPDF() {
    showToast('PDF export feature coming soon', 'info');
}

// ═══════════════════════════════════════════════════════════════
// SEMI-EXPANDABLE TABLE FUNCTIONALITY
// ═══════════════════════════════════════════════════════════════

/**
 * Toggle a category row in semi-expandable table
 * @param {string} categoryId - The unique identifier for the category
 */
function toggleCategory(categoryId) {
    const headerRow = document.querySelector(`[data-category-id="${categoryId}"]`);
    const itemsRow = document.getElementById(`items-${categoryId}`);
    
    if (!headerRow || !itemsRow) return;
    
    const isExpanded = headerRow.classList.contains('expanded');
    
    if (isExpanded) {
        // Collapse
        headerRow.classList.remove('expanded');
        itemsRow.classList.remove('visible');
    } else {
        // Expand
        headerRow.classList.add('expanded');
        itemsRow.classList.add('visible');
    }
}

/**
 * Expand all categories in semi-expandable table
 */
function expandAllCategories() {
    const allHeaders = document.querySelectorAll('.category-header-row');
    const allItems = document.querySelectorAll('.category-items-row');
    
    allHeaders.forEach(header => header.classList.add('expanded'));
    allItems.forEach(items => items.classList.add('visible'));
    
    // Update button text
    const btn = document.getElementById('expand-all-btn');
    if (btn) {
        btn.textContent = '▼ Collapse All';
        btn.onclick = collapseAllCategories;
    }
}

/**
 * Collapse all categories in semi-expandable table
 */
function collapseAllCategories() {
    const allHeaders = document.querySelectorAll('.category-header-row');
    const allItems = document.querySelectorAll('.category-items-row');
    
    allHeaders.forEach(header => header.classList.remove('expanded'));
    allItems.forEach(items => items.classList.remove('visible'));
    
    // Update button text
    const btn = document.getElementById('expand-all-btn');
    if (btn) {
        btn.textContent = '▶ Expand All';
        btn.onclick = expandAllCategories;
    }
}

/**
 * Render semi-expandable inventory report grouped by category
 * @param {Array} items - Array of inventory items
 * @param {string} containerId - ID of the container element
 */
function renderSemiExpandableReport(items, containerId) {
    const container = document.getElementById(containerId);
    
    if (!items || items.length === 0) {
        container.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No items found</p></div>';
        return;
    }
    
    // Group items by category (e.g., "OFFICE FIXTURES & FURNITURES", "INFORMATION AND COMMUNICATION TECHNOLOGY EQUIPMENT")
    const groupedItems = {};
    let grandTotal = 0;
    
    items.forEach(item => {
        // Use a category field or create one based on description
        const category = item.item_category || item.category || 'UNCATEGORIZED';
        
        if (!groupedItems[category]) {
            groupedItems[category] = {
                items: [],
                total: 0
            };
        }
        
        const itemTotal = (item.unit_value || 0) * (item.quantity || 1);
        groupedItems[category].items.push(item);
        groupedItems[category].total += itemTotal;
        grandTotal += itemTotal;
    });
    
    // Build the table HTML
    let tableHTML = `
        <div style="margin-bottom: 10px;">
            <button id="expand-all-btn" class="expand-all-btn" onclick="expandAllCategories()">▶ Expand All</button>
        </div>
        <table class="semi-expandable-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Article / Item</th>
                    <th style="width: 25%;">Description</th>
                    <th style="width: 15%;">Semi-expendable Property No.</th>
                    <th style="width: 10%;">Unit of Measure</th>
                    <th style="width: 10%;">Unit Value</th>
                    <th style="width: 10%;">Balance Per Card (Quantity)</th>
                    <th style="width: 10%;">On Hand Per Count (Quantity)</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    // Add each category
    Object.keys(groupedItems).sort().forEach((category, index) => {
        const categoryData = groupedItems[category];
        const categoryId = `cat-${index}`;
        
        // Category header row
        tableHTML += `
            <tr class="category-header-row" data-category-id="${categoryId}" onclick="toggleCategory('${categoryId}')">
                <td class="category-header-cell" colspan="5">
                    <span class="expand-icon">▶</span>
                    ${category}
                </td>
                <td class="category-total-cell" colspan="3">
                    ${formatCurrency(categoryData.total)}
                </td>
            </tr>
        `;
        
        // Category items row (hidden by default)
        tableHTML += `
            <tr class="category-items-row" id="items-${categoryId}">
                <td colspan="8" class="category-items-cell">
                    <table class="nested-items-table">
        `;
        
        // Add each item in the category
        categoryData.items.forEach(item => {
            const itemTotal = (item.unit_value || 0) * (item.quantity || 1);
            tableHTML += `
                <tr>
                    <td style="width: 20%;">${item.item_name || item.description || '—'}</td>
                    <td style="width: 25%;">${item.description || item.item_name || '—'}</td>
                    <td style="width: 15%;" class="mono">${item.property_number || '—'}</td>
                    <td style="width: 10%;">${item.unit_of_measure || 'pc'}</td>
                    <td style="width: 10%;">${formatCurrency(item.unit_value || 0)}</td>
                    <td style="width: 10%; text-align: center;">${item.quantity || 0}</td>
                    <td style="width: 10%; text-align: center;">${item.available_quantity || item.quantity || 0}</td>
                    <td>${item.remarks || '—'}</td>
                </tr>
            `;
        });
        
        // Category subtotal
        tableHTML += `
                        <tr class="category-subtotal-row">
                            <td colspan="5"><strong>Subtotal - ${category}</strong></td>
                            <td colspan="3" style="text-align: right;"><strong>${formatCurrency(categoryData.total)}</strong></td>
                        </tr>
                    </table>
                </td>
            </tr>
        `;
    });
    
    // Grand total row
    tableHTML += `
                <tr class="grand-total-row">
                    <td colspan="5">GRAND TOTAL</td>
                    <td colspan="3" style="text-align: right;">${formatCurrency(grandTotal)}</td>
                </tr>
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHTML;
}

/**
 * Load semi-expandable report view
 */
async function loadSemiExpandableReport() {
    const result = await apiGet('items.php?classification=Semi-Expendable');
    if (result.success) {
        renderSemiExpandableReport(result.data, 'semi-expandable-container');
    }
}
