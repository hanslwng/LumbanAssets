<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Session timeout check (1 hour)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header('Location: /login');
    exit;
}

// Update last activity time
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

// Get user info for display
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/style.css">
    <link rel="stylesheet" href="public/assets/css/dashboard-modern.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script
  src="https://www.tuqlas.com/chatbot.js"
  data-key="tq_live_6942abb054d728b3d5e5e924cb228034af1d63d9"
  data-api="https://www.tuqlas.com"
  defer
></script>
</head>

<body>

<!-- Hidden user data for JavaScript -->
<div style="display: none;" 
     data-user-name="<?php echo htmlspecialchars($userName); ?>" 
     data-user-role="<?php echo htmlspecialchars($userRole); ?>"></div>

<div class="app">
<!-- Sidebar -->
<aside class="sb">
  <div class="sb-logo">
    <div class="gov"><?php echo AGENCY_NAME; ?></div>
    <div class="sys"><?php echo SYSTEM_NAME; ?></div>
    <div class="dept">Internal Use Only</div>
  </div>
  <nav class="sb-nav">
    <div class="sb-sec">Overview</div>
    <div class="sb-item active" onclick="nav('dashboard', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="5" height="5" rx="1"/><rect x="8" y="1" width="5" height="5" rx="1"/><rect x="1" y="8" width="5" height="5" rx="1"/><rect x="8" y="8" width="5" height="5" rx="1"/></svg>
      Dashboard
    </div>
    <div class="sb-sec">Inventory</div>
    <div class="sb-item" onclick="nav('semi', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="2" width="12" height="10" rx="1"/><path d="M4 7h6M7 4v6"/></svg>
      Semi-Expendable
      <span class="sb-badge" id="nb-se">0</span>
    </div>
    <div class="sb-item" onclick="nav('ppe', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M7 4v3l2 2"/></svg>
      PPE
      <span class="sb-badge" id="nb-ppe">0</span>
    </div>
    <div class="sb-sec">Transactions</div>
    <div class="sb-item" onclick="nav('issuance', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 7h10M8 3l4 4-4 4"/></svg>
      Issuance (PAR / ICS)
      <span class="sb-badge" id="nb-iss">0</span>
    </div>
    <div class="sb-item" onclick="nav('returns', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 7H2M6 3L2 7l4 4"/></svg>
      Returns
      <span class="sb-badge" id="nb-ret">0</span>
    </div>
    <div class="sb-sec">Asset Status</div>
    <div class="sb-item" onclick="nav('unserviceable', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 1L1 13h12L7 1zM7 5v4M7 10.5v.5"/></svg>
      Unserviceable
      <span class="sb-badge" id="nb-uns">0</span>
    </div>
    <div class="sb-item" onclick="nav('disposal', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 4h10l-1 8H3L2 4zM5 4V3a2 2 0 014 0v1"/></svg>
      Disposal Records
      <span class="sb-badge" id="nb-dis">0</span>
    </div>
    <div class="sb-sec">Reports</div>
    <div class="sb-item" onclick="nav('coa', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="1" width="10" height="12" rx="1"/><path d="M5 5h4M5 8h4M5 11h2"/></svg>
      COA Report
    </div>
  </nav>
  <div class="sb-foot"><?php echo SYSTEM_VERSION; ?> · Property Inv. System</div>
</aside>

<!-- Main -->
<main class="main">
  <div class="topbar">
    <div class="tb-left">
      <div>
        <div class="tb-title" id="tb-title">Dashboard</div>
        <div class="tb-sub" id="tb-sub">Property inventory overview</div>
      </div>
    </div>
    <div class="tb-right">
      <button class="btn btn-ghost btn-sm" onclick="openModal('return-modal');populateReturnSelect()">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 7H2M6 3L2 7l4 4"/></svg>Record Return
      </button>
      <button class="btn btn-ghost btn-sm" onclick="openModal('issue-modal');populateIssueSelect()">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 7h10M8 3l4 4-4 4"/></svg>Issue Item
      </button>
      <button class="btn btn-dark" onclick="openModal('encode-modal')">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 1v12M1 7h12"/></svg>Encode Item
      </button>
      
      <!-- User Profile & Logout -->
      <div class="tb-divider"></div>
      <a href="/logout" class="btn-logout" onclick="return confirm('Are you sure you want to logout?');">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
    </div>
      <button class="btn btn-ghost btn-sm" onclick="openModal('issue-modal');populateIssueSelect()">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 7h10M8 3l4 4-4 4"/></svg>Issue Item
      </button>
      <button class="btn btn-dark" onclick="openModal('encode-modal')">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 1v12M1 7h12"/></svg>Encode Item
      </button>
    </div>
  </div>

  <div class="content">

    <!-- DASHBOARD -->
    <div class="page active" id="pg-dashboard">
      <div class="stats" id="stat-row"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
        <div class="card">
          <div class="card-hd"><span class="card-ttl">Recent Items Encoded</span></div>
          <div id="dash-recent"></div>
        </div>
        <div class="card">
          <div class="card-hd"><span class="card-ttl">Recent Transactions</span><button class="btn btn-ghost btn-xs" onclick="nav('issuance', event)">View all →</button></div>
          <div id="dash-txn"></div>
        </div>
      </div>
      <div class="card">
        <div class="card-hd"><span class="card-ttl">Items Needing Attention</span></div>
        <div id="dash-alerts"></div>
      </div>
    </div>

    <!-- SEMI-EXPENDABLE -->
    <div class="page" id="pg-semi">
      <div class="alert alert-info"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="6"/><path d="M7 6v4M7 4.5v.5"/></svg>Semi-expendable items are below ₱50,000. Property number format: YYYY-SE-XXXXXX. ICS is issued per person.</div>
      <div class="card">
        <div class="card-hd">
          <span class="card-ttl">Semi-Expendable by Office</span>
          <div class="filters">
            <input class="search-inp" type="text" placeholder="Search items..." id="s-se" oninput="filterSEItems()"/>
            <select class="filter-sel" id="f-status-se" onchange="filterSEItems()">
              <option value="">All statuses</option>
              <option value="Active">Active</option>
              <option value="Issued">Issued</option>
              <option value="Unserviceable">Unserviceable</option>
              <option value="Disposed">Disposed</option>
            </select>
          </div>
        </div>
        <div id="se-offices-container"></div>
      </div>
    </div>

    <!-- PPE -->
    <div class="page" id="pg-ppe">
      <div class="alert alert-warn"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 1L1 13h12L7 1zM7 5v4M7 10.5v.5"/></svg>PPE items are ₱50,000 and above. Property number format: YYYY-PPE-XXXXXX (per article). PAR is issued per person assigned.</div>
      <div class="card">
        <div class="card-hd">
          <span class="card-ttl">Property, Plant &amp; Equipment</span>
          <div class="filters">
            <input class="search-inp" type="text" placeholder="Search items..." id="s-ppe" oninput="filterPPEItems()"/>
            <select class="filter-sel" id="f-status-ppe" onchange="filterPPEItems()">
              <option value="">All statuses</option>
              <option value="Active">Active</option>
              <option value="Issued">Issued</option>
              <option value="Unserviceable">Unserviceable</option>
              <option value="Disposed">Disposed</option>
            </select>
          </div>
        </div>
        <div id="ppe-offices-container"></div>
      </div>
    </div>

    <!-- ISSUANCE -->
    <div class="page" id="pg-issuance">
      <div class="alert alert-info"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="6"/><path d="M7 6v4M7 4.5v.5"/></svg>PAR is issued for PPE items (per person). ICS is issued for semi-expendable items (per person). Click "Issue Item" to create a record.</div>
      <div class="card">
        <div class="card-hd">
          <span class="card-ttl">Issuance Records (PAR / ICS)</span>
          <div class="filters">
            <input class="search-inp" type="text" placeholder="Search..." id="s-iss" oninput="rt('iss')"/>
            <select class="filter-sel" id="f-iss-type" onchange="rt('iss')"><option value="">All forms</option><option value="PAR">PAR (PPE)</option><option value="ICS">ICS (Semi-exp.)</option></select>
            <select class="filter-sel" id="f-iss-office" onchange="rt('iss')"><option value="">All offices</option></select>
          </div>
        </div>
        <div id="tbl-iss"></div>
      </div>
    </div>

    <!-- RETURNS -->
    <div class="page" id="pg-returns">
      <div class="alert alert-info"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="6"/><path d="M7 6v4M7 4.5v.5"/></svg>When an item is returned, you can mark it as unserviceable or transfer it to another office.</div>
      <div class="card">
        <div class="card-hd"><span class="card-ttl">Return Records</span><input class="search-inp" type="text" placeholder="Search..." id="s-ret" oninput="rt('ret')"/></div>
        <div id="tbl-ret"></div>
      </div>
    </div>

    <!-- UNSERVICEABLE -->
    <div class="page" id="pg-unserviceable">
      <div class="alert alert-danger"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 1L1 13h12L7 1zM7 5v4M7 10.5v.5"/></svg>Unserviceable items remain in inventory. Disposal and appraisal are handled by a separate team. Mark for disposal only after appraisal.</div>
      <div class="card">
        <div class="card-hd">
          <span class="card-ttl">Unserviceable Items</span>
          <button class="btn btn-ghost btn-sm" onclick="openModal('unserv-modal');populateUnservSelect()">+ Mark as Unserviceable</button>
        </div>
        <div id="tbl-uns"></div>
      </div>
    </div>

    <!-- DISPOSAL -->
    <div class="page" id="pg-disposal">
      <div class="info-box">
        <div class="info-box-title">Disposal Records</div>
        <div class="info-box-text">Disposal is handled by a separate appraisal team. Items appear here after the disposal process is completed and recorded by property officers.</div>
      </div>
      <div class="card">
        <div class="card-hd"><span class="card-ttl">Disposal Records</span></div>
        <div id="tbl-dis"></div>
      </div>
    </div>

    <!-- COA REPORT -->
    <div class="page" id="pg-coa">
      <div class="coa-header">
        <div class="coa-h-title">COA Inventory Report</div>
        <div class="coa-h-sub" id="coa-date-range">Annual property inventory · generated on load</div>
      </div>
      <div style="display:flex;gap:8px;margin-bottom:16px;align-items:center">
        <select class="filter-sel" id="coa-year" onchange="renderCOA()"><option>2025</option><option>2024</option><option>2023</option></select>
        <button class="btn btn-dark btn-sm" onclick="window.print()">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 5V1h8v4M3 9H2a1 1 0 01-1-1V6a1 1 0 011-1h10a1 1 0 011 1v2a1 1 0 01-1 1h-1M3 8h8v5H3V8z"/></svg>Print Report
        </button>
      </div>
      <div id="coa-body"></div>
    </div>

  </div>
</main>
</div>

<?php include 'includes/modals.php'; ?>

<div class="toast" id="toast"></div>

<script src="public/assets/js/app.js"></script>
<script src="public/assets/js/dashboard-enhancements.js"></script>
</body>
</html>
