<?php
require_once dirname(__DIR__) . '/includes/session.php';
lgueStartSession();

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . lgue_url('login'));
    exit;
}
if (!PermissionHelper::isAdmin()) {
    header('Location: ' . lgue_url('staff'));
    exit;
}

settingsApplySessionTimeout();
$activityOfficeLocked = PermissionHelper::isDepartmentScoped();
$activityOfficeId = PermissionHelper::officeId();
$activityOfficeName = trim((string) ($_SESSION['user_office_name'] ?? ''));

// Get user info for display
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'staff';
$userRoleDisplay = PermissionHelper::getRoleDisplayName($userRole);

// Check permissions
$canCreate = PermissionHelper::can('create');
$canEdit = PermissionHelper::can('edit');
$canDelete = PermissionHelper::can('delete');
$canExport = PermissionHelper::can('export');
$canPrint = PermissionHelper::can('print');
$isViewer = PermissionHelper::isViewer();
$ppeThreshold = (int) settingsPpeThreshold();
require_once dirname(__DIR__) . '/includes/consumable_pr_forecast.php';
$fcWindow = consumableForecastPrWindow(null, getDBConnection());
$fcForecastLbl = consumableForecastMonthLabel((string) ($fcWindow['forecast_ym'] ?? ''));
$fcTrainFromLbl = consumableForecastMonthLabel((string) ($fcWindow['train_start'] ?? '2026-01'));
$fcTrainToLbl = consumableForecastMonthLabel((string) ($fcWindow['train_end'] ?? '2026-08'));
$gsoOfficerName = defined('RPCPPE_ACCOUNTABLE_OFFICER') ? RPCPPE_ACCOUNTABLE_OFFICER : 'RHONALIZA A. ALVIAR';
if (function_exists('settingsGet')) {
    try {
        $savedOfficer = trim((string) settingsGet(getDBConnection(), 'gso_officer', $gsoOfficerName));
        if ($savedOfficer !== '') {
            $gsoOfficerName = $savedOfficer;
        }
    } catch (Throwable $e) {
        // Keep config default.
    }
}
$mayorPrintName = defined('RPCPPE_APPROVER_NAME') ? RPCPPE_APPROVER_NAME : 'BELEN B. RAGA';
$mayorPrintTitle = defined('RPCPPE_APPROVER_DESIGNATION') ? RPCPPE_APPROVER_DESIGNATION : 'Municipal Mayor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="lgue-web-base" content="<?php echo htmlspecialchars(LGUE_WEB_BASE, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo SYSTEM_NAME; ?></title>
    <?php require __DIR__ . '/../includes/favicon-links.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(lgue_url('assets/css/style.css', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(lgue_url('assets/css/app-loading.css', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(lgue_url('assets/css/procurement-ui.css', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(lgue_url('assets/css/property-tag.css', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body class="app-loading-active">

<div id="app-loading-screen" class="app-loading-screen" role="status" aria-live="polite" aria-busy="true">
    <div class="app-loading-screen__panel">
        <div class="app-loading-screen__logo">
            <img src="<?php echo htmlspecialchars(lgue_url('public/assets/logo-lumban.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Municipality of Lumban logo">
        </div>
        <p class="app-loading-screen__title"><?php echo htmlspecialchars(SYSTEM_NAME); ?></p>
        <p class="app-loading-screen__sub">Loading scripts and stylesheets. This screen hides when the browser completes the page load (including images and CDN assets on this page).</p>
        <div class="app-loading-screen__progress" aria-hidden="true"></div>
    </div>
</div>

<!-- Hidden user data for JavaScript -->
<div id="userData" style="display: none;"
     data-user-name="<?php echo htmlspecialchars($userName); ?>"
     data-user-role="<?php echo htmlspecialchars($userRole); ?>"
     data-user-office-id="<?php echo (int) ($_SESSION['user_office_id'] ?? 0); ?>"
     data-user-office-name="<?php echo htmlspecialchars((string) ($_SESSION['user_office_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
     data-user-is-admin="<?php echo PermissionHelper::isAdmin() ? '1' : '0'; ?>"
     data-user-dept-scoped="<?php echo PermissionHelper::isDepartmentScoped() ? '1' : '0'; ?>"
     data-can-create="<?php echo $canCreate ? '1' : '0'; ?>"
     data-can-edit="<?php echo $canEdit ? '1' : '0'; ?>"
     data-can-delete="<?php echo $canDelete ? '1' : '0'; ?>"
     data-can-print="<?php echo $canPrint ? '1' : '0'; ?>"
     data-is-viewer="<?php echo $isViewer ? '1' : '0'; ?>"
     data-ppe-threshold="<?php echo (int) $ppeThreshold; ?>"
     data-gso-officer="<?php echo htmlspecialchars((string) $gsoOfficerName, ENT_QUOTES, 'UTF-8'); ?>"
     data-gso-designation="GSO - Designate"
     data-mayor-name="<?php echo htmlspecialchars((string) $mayorPrintName, ENT_QUOTES, 'UTF-8'); ?>"
     data-mayor-title="<?php echo htmlspecialchars((string) $mayorPrintTitle, ENT_QUOTES, 'UTF-8'); ?>"></div>

<div class="app">
<!-- Sidebar -->
<aside class="sb">
  <div class="sb-logo">
    <div class="sb-logo-img">
      <img src="<?php echo htmlspecialchars(lgue_url('public/assets/logo-lumban.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Municipality of Lumban logo">
    </div>
    <div class="sb-logo-text">
      <div class="gov"><?php echo AGENCY_NAME; ?></div>
      <div class="sys">General Services Office</div>
    </div>
  </div>
  <nav class="sb-nav">
    <div class="sb-sec">Overview</div>
    <div class="sb-item active" onclick="nav('dashboard', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="5" height="5" rx="1"/><rect x="8" y="1" width="5" height="5" rx="1"/><rect x="1" y="8" width="5" height="5" rx="1"/><rect x="8" y="8" width="5" height="5" rx="1"/></svg>
      <span class="sb-item-text">Dashboard</span>
    </div>
    <div class="sb-sec">Inventory</div>
    <div class="sb-item" onclick="nav('semi', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="2" width="12" height="10" rx="1"/><path d="M4 7h6M7 4v6"/></svg>
      <span class="sb-item-text">Semi-Expendable</span>
    </div>
    <div class="sb-item" onclick="nav('ppe', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M7 4v3l2 2"/></svg>
      <span class="sb-item-text">PPE</span>
    </div>
    <div class="sb-sec">Transactions</div>
    <div class="sb-item" onclick="nav('issuance', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 7h10M8 3l4 4-4 4"/></svg>
      <span class="sb-item-text">Issuance (PAR)</span>
    </div>
    <div class="sb-item" onclick="nav('procurement', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="10" height="10" rx="1"/><path d="M5 9h5M9 10l2-5M4 10l2-5"/></svg>
      <span class="sb-item-text">Procurement</span>
    </div>
    <div class="sb-sec">Planning</div>
    <div class="sb-item" onclick="nav('forecast', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 11l3-3 2 1.5L12 4"/><path d="M9 4h3v3"/></svg>
      <span class="sb-item-text">Forecast</span>
    </div>
    <div class="sb-sec">Asset Status</div>
    <div class="sb-item" onclick="nav('unserviceable', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 1L1 13h12L7 1zM7 5v4M7 10.5v.5"/></svg>
      <span class="sb-item-text">Unserviceable</span>
    </div>
    <div class="sb-item" onclick="nav('disposal', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 4h10l-1 8H3L2 4zM5 4V3a2 2 0 014 0v1"/></svg>
      <span class="sb-item-text">Disposal Records</span>
    </div>
    <div class="sb-sec">Account</div>
    <div class="sb-item" onclick="nav('activity', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 2h8M3 7h8M3 12h5"/><circle cx="11.5" cy="12" r="1.2"/></svg>
      <span class="sb-item-text">Activity log</span>
    </div>
    <div class="sb-item" onclick="nav('settings', event)">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="2.4"/><path d="M7 1.4v1.4M7 11.2v1.4M1.4 7h1.4M11.2 7h1.4M2.9 2.9l1 1M10.1 10.1l1 1M2.9 11.1l1-1M10.1 3.9l1-1"/></svg>
      <span class="sb-item-text">Settings</span>
    </div>
    <div class="sb-item sb-item-logout" onclick="openLogoutConfirm()">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 13H2a1 1 0 01-1-1V2a1 1 0 011-1h3M10 10l3-3-3-3M13 7H5"/></svg>
      <span class="sb-item-text">Sign out</span>
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
      <?php if ($canCreate): ?>
      <button class="btn btn-dark" onclick="openModal('encode-modal')">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 1v12M1 7h12"/></svg>Encode Item
      </button>
      <?php endif; ?>

      <!-- Notification Bell (every department + Admin) -->
      <div class="notif-bell-wrapper">
        <button type="button" class="notif-bell-btn" onclick="toggleNotifPanel(event)" title="Notifications">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M7 1.5c-1.7 0-3 1.4-3 3.1v1.9c0 .5-.2 1-.5 1.4L2.7 9.2c-.5.6-.1 1.5.6 1.5h7.4c.7 0 1.1-.9.6-1.5l-.8-1.3c-.3-.4-.5-.9-.5-1.4V4.6c0-1.7-1.3-3.1-3-3.1z"/><path d="M5.5 12a1.5 1.5 0 003 0"/></svg>
          <span class="notif-bell-badge" id="notif-badge" style="display:none">0</span>
        </button>
        <div class="notif-panel" id="notif-panel">
          <div class="notif-panel-hd">
            <span>Notifications</span>
            <button type="button" class="btn btn-ghost btn-xs" onclick="markAllNotificationsRead()">Mark all read</button>
          </div>
          <div class="notif-panel-body" id="notif-panel-body"><p class="muted" style="padding:12px">No notifications yet</p></div>
        </div>
      </div>

      <?php
        $tbInitials = '';
        foreach (preg_split('/\s+/', trim((string) $userName)) as $tbPart) {
            if ($tbPart !== '') {
                $tbInitials .= strtoupper(substr($tbPart, 0, 1));
            }
            if (strlen($tbInitials) >= 2) {
                break;
            }
        }
        if ($tbInitials === '') {
            $tbInitials = 'U';
        }
      ?>
      <div class="tb-who" title="<?php echo htmlspecialchars($userName . ' · ' . $userRoleDisplay, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="tb-who-avatar" aria-hidden="true"><?php echo htmlspecialchars($tbInitials, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="tb-who-text">
          <span class="tb-who-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="tb-who-role"><?php echo htmlspecialchars($userRoleDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
        </span>
      </div>
      
      <?php if ($isViewer): ?>
      <div class="viewer-badge">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 14px; height: 14px;">
          <circle cx="7" cy="7" r="6"/><path d="M7 4v3M7 9v.5"/>
        </svg>
        Read-Only Mode
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">

    <!-- DASHBOARD — Admin analytics -->
    <div class="page active" id="pg-dashboard">
      <div class="dash-hero" aria-label="Dashboard overview">
        <div class="dash-hero-bg" aria-hidden="true">
          <span class="dash-hero-orb dash-hero-orb--a"></span>
          <span class="dash-hero-orb dash-hero-orb--b"></span>
          <span class="dash-hero-grid"></span>
        </div>
        <div class="dash-hero-inner">
          <div class="dash-hero-mark" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="4" y="22" width="8" height="18" rx="2" fill="currentColor" opacity=".95"/>
              <rect x="16" y="14" width="8" height="26" rx="2" fill="currentColor" opacity=".75"/>
              <rect x="28" y="8" width="8" height="32" rx="2" fill="currentColor" opacity=".55"/>
              <path d="M6 18l12-8 12 6 8-10" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="38" cy="6" r="2.5" fill="#FFD700"/>
            </svg>
          </div>
          <div class="dash-hero-copy">
            <div class="dash-hero-meta">
              <span class="dash-hero-kicker">GSO · Admin analytics</span>
              <span class="dash-hero-dot" aria-hidden="true"></span>
              <time class="dash-hero-date" id="dash-hero-date" datetime="<?php echo date('Y-m-d'); ?>"><?php echo date('l · F j, Y'); ?></time>
              <span class="dash-hero-clock" id="dash-wx-clock" aria-label="Current time">--:--</span>
            </div>
            <h2 class="dash-hero-title">Inventory &amp; procurement pulse</h2>
            <p class="dash-hero-sub">Live view of SE vs PPE stock, open purchase requests, and recent activity.</p>
            <ul class="dash-hero-pills" aria-label="Dashboard focus areas">
              <li>Stock mix</li>
              <li>PR status</li>
              <li>Recent activity</li>
            </ul>
          </div>
          <aside class="dash-hero-weather" id="dash-hero-weather" aria-label="Local weather">
            <div class="dash-wx-sky" aria-hidden="true">
              <span class="dash-wx-glow"></span>
              <span class="dash-wx-orb" id="dash-wx-icon">🌙</span>
              <span class="dash-wx-cloud dash-wx-cloud--a"></span>
              <span class="dash-wx-cloud dash-wx-cloud--b"></span>
              <span class="dash-wx-star dash-wx-star--a"></span>
              <span class="dash-wx-star dash-wx-star--b"></span>
              <span class="dash-wx-star dash-wx-star--c"></span>
            </div>
            <div class="dash-wx-body">
              <strong class="dash-wx-temp" id="dash-wx-temp">--°</strong>
              <span class="dash-wx-period" id="dash-wx-period">—</span>
              <span class="dash-wx-cond" id="dash-wx-cond">Loading…</span>
              <span class="dash-wx-place">Lumban, Laguna</span>
              <div class="dash-wx-meta">
                <span id="dash-wx-hum">Humidity —</span>
                <span id="dash-wx-wind">Wind —</span>
              </div>
            </div>
          </aside>
        </div>
      </div>

      <div class="stats dash-stats" id="stat-row"></div>

      <div class="dash-charts-grid">
        <div class="card dash-chart-card dash-chart-card--mix">
          <div class="dash-chart-hd">
            <div class="dash-chart-hd-icon dash-chart-hd-icon--red" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 3v9l7 4"/></svg>
            </div>
            <div class="dash-chart-hd-text">
              <span class="card-ttl">Stock mix</span>
              <p class="dash-card-hint">Share of encoded items — SE vs PPE</p>
            </div>
            <span class="dash-chip dash-chip--pair"><span class="dash-chip-dot dash-chip-dot--se"></span>SE<span class="dash-chip-sep">·</span><span class="dash-chip-dot dash-chip-dot--ppe"></span>PPE</span>
          </div>
          <div class="dash-chart-body dash-chart-body--split">
            <div class="dash-donut-wrap dash-donut-wrap--lg">
              <canvas id="dash-cat-chart" height="176"></canvas>
              <div class="dash-donut-center" id="dash-cat-center" hidden>
                <strong id="dash-cat-total">0</strong>
                <span id="dash-cat-center-lbl">items</span>
              </div>
            </div>
            <div class="dash-chart-side">
              <div class="dash-chart-legend dash-chart-legend--tiles" id="dash-cat-legend"></div>
              <p class="dash-chart-note" id="dash-cat-note" hidden></p>
            </div>
            <div id="dash-cat-empty" class="dash-chart-empty" hidden>No inventory encoded yet</div>
          </div>
        </div>

        <div class="card dash-chart-card dash-chart-card--pr">
          <div class="dash-chart-hd">
            <div class="dash-chart-hd-icon dash-chart-hd-icon--ink" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/></svg>
            </div>
            <div class="dash-chart-hd-text">
              <span class="card-ttl">PR status</span>
              <p class="dash-card-hint">Open vs completed requests at a glance</p>
            </div>
            <span class="dash-chip dash-chip--ink">Live</span>
          </div>
          <div class="dash-chart-body dash-chart-body--split">
            <div class="dash-donut-wrap dash-donut-wrap--lg">
              <canvas id="dash-pr-status-chart" height="176"></canvas>
              <div class="dash-donut-center" id="dash-pr-center" hidden>
                <strong id="dash-pr-total">0</strong>
                <span id="dash-pr-center-lbl">PRs</span>
              </div>
            </div>
            <div class="dash-chart-side">
              <div class="dash-chart-legend dash-chart-legend--tiles" id="dash-pr-legend"></div>
              <p class="dash-chart-note" id="dash-pr-note" hidden></p>
            </div>
            <div id="dash-pr-status-empty" class="dash-chart-empty" hidden>No submitted PRs yet</div>
          </div>
        </div>
      </div>

      <div class="dash-two-col">
        <div class="card dash-panel dash-panel--recent dash-panel--encoded">
          <div class="dash-panel-hd">
            <div class="dash-panel-hd-text">
              <span class="dash-panel-kicker">Fresh</span>
              <span class="card-ttl">Recent items encoded</span>
            </div>
            <button type="button" class="btn btn-ghost btn-xs" onclick="nav('semi', event)">Inventory →</button>
          </div>
          <div class="dash-panel-body" id="dash-recent"></div>
        </div>
        <div class="card dash-panel dash-panel--recent dash-panel--txn">
          <div class="dash-panel-hd">
            <div class="dash-panel-hd-text">
              <span class="dash-panel-kicker">Activity</span>
              <span class="card-ttl">Recent transactions</span>
            </div>
            <button type="button" class="btn btn-ghost btn-xs" onclick="nav('issuance', event)">All →</button>
          </div>
          <div class="dash-panel-body" id="dash-txn"></div>
        </div>
      </div>

      <div class="card dash-panel dash-panel--proc">
        <div class="dash-panel-hd">
          <span class="card-ttl">Procurement by office</span>
          <button type="button" class="btn btn-ghost btn-xs" onclick="nav('procurement', event)">Open →</button>
        </div>
        <div class="dash-panel-body dash-panel-body--scroll" id="dash-proc-office"></div>
      </div>
    </div>

    <!-- SEMI-EXPENDABLE -->
    <div class="page" id="pg-semi">
      <div class="card">
        <div class="card-hd card-hd-inv">
          <div class="inv-hd-left">
            <span class="card-ttl">Semi-Expendable by Office</span>
            <input class="search-inp" type="search" placeholder="Search office, item, property #…" id="s-se" oninput="filterSEItems()" autocomplete="off" aria-label="Search semi-expendable items"/>
            <select class="filter-sel" id="f-office-se" onchange="filterSEItems()" aria-label="Filter by department">
              <option value="">All departments</option>
            </select>
            <select class="filter-sel" id="f-status-se" onchange="filterSEItems()">
              <option value="">All statuses</option>
              <option value="Active">Active</option>
              <option value="Issued">Issued</option>
              <option value="Unserviceable">Unserviceable</option>
              <option value="Disposed">Disposed</option>
            </select>
          </div>
          <div class="inv-hd-right">
            <?php if ($canPrint): ?>
            <span class="ptag-selected-count" id="ptag-selected-count" aria-live="polite"></span>
            <button type="button" class="btn btn-ghost btn-sm" onclick="printPropertyTags('a4')" title="Print selected tags, 6 per Letter sheet">Print A4 Tag</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="printPropertyTags('long')" title="Print selected tags, 8 per long/Legal sheet">Print Long Tag</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="openPhysicalCountOfficePicker()" title="Open one office in the official RPCSE form, or export all offices (Excel is view-only / sheet-protected)">Print physical count</button>
            <?php endif; ?>
            <?php if (PermissionHelper::isAdmin()): ?>
            <span class="inv-hd-sep" aria-hidden="true"></span>
            <!-- DISABLED: Replace Template functionality -->
            <!-- <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('rpcse-template-input').click()" title="Replace storage/templates/rpcse/rpcse-template.xlsx (previous file is archived)">Replace template</button>
            <input type="file" id="rpcse-template-input" accept=".xlsx" style="display:none" onchange="uploadRpcseTemplate(this)"/> -->
            <?php endif; ?>
            <!-- DISABLED: Export functionality -->
            <!-- <button type="button" class="btn btn-ghost btn-sm" onclick="exportItemsExcel('SE')" title="Download this list as an Excel-openable .csv"><i class="fas fa-file-export"></i> Export</button> -->
            <?php if ($canCreate): ?>
            <!-- DISABLED: Import functionality -->
            <!-- <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('se-import-input').click()" title="Bulk-upload items from the RPCSE .xlsx (one sheet per office) or a .csv export"><i class="fas fa-file-import"></i> Import</button>
            <input type="file" id="se-import-input" accept=".xlsx,.xlsm,.csv" style="display:none" onchange="importItemsExcel(this, 'SE')"/> -->
            <?php endif; ?>
          </div>
        </div>
        <div id="se-offices-container"></div>
      </div>
    </div>

    <!-- PPE -->
    <div class="page" id="pg-ppe">
      <div class="card">
        <div class="card-hd card-hd-inv">
          <div class="inv-hd-left">
            <span class="card-ttl">PPE by Excel tab (RPCPPE)</span>
            <input class="search-inp" type="search" placeholder="Search tab, office, item, property #…" id="s-ppe" oninput="filterPPEItems()" autocomplete="off" aria-label="Search PPE items"/>
            <select class="filter-sel" id="f-status-ppe" onchange="filterPPEItems()">
              <option value="">All statuses</option>
              <option value="Active">Active</option>
              <option value="Issued">Issued</option>
              <option value="Unserviceable">Unserviceable</option>
              <option value="Disposed">Disposed</option>
            </select>
          </div>
          <div class="inv-hd-right">
            <?php if ($canPrint): ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="openPhysicalCountPpePicker()" title="Open one PPE tab in the official RPCPPE form, or export all tabs (Excel is view-only / sheet-protected)">Print physical count</button>
            <?php endif; ?>
            <?php if (PermissionHelper::isAdmin()): ?>
            <!-- DISABLED: Replace Template functionality -->
            <!-- <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('rpcppe-template-input').click()" title="Replace storage/templates/rpcppe/rpcppe-template.xlsx (previous file is archived)">Replace template</button>
            <input type="file" id="rpcppe-template-input" accept=".xlsx" style="display:none" onchange="uploadRpcppeTemplate(this)"/> -->
            <?php endif; ?>
            <!-- DISABLED: Export functionality -->
            <!-- <button type="button" class="btn btn-ghost btn-sm" onclick="exportItemsExcel('PPE')" title="Download this list as an Excel-openable .csv"><i class="fas fa-file-export"></i> Export</button> -->
            <?php if ($canCreate): ?>
            <!-- DISABLED: Import functionality -->
            <!-- <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('ppe-import-input').click()" title="Bulk-upload the official RPCPPE .xlsx (LAND FINAL, equipment, SEF, land titles) or a .csv export"><i class="fas fa-file-import"></i> Import</button>
            <input type="file" id="ppe-import-input" accept=".xlsx,.xlsm,.csv" style="display:none" onchange="importItemsExcel(this, 'PPE')"/> -->
            <?php endif; ?>
          </div>
        </div>
        <div id="ppe-offices-container"></div>
      </div>
    </div>

    <!-- ISSUANCE -->
    <div class="page" id="pg-issuance">
      <div class="card">
        <div class="card-hd card-hd-inv">
          <div class="inv-hd-left">
            <span class="card-ttl">Issuance Records (PAR)</span>
            <input class="search-inp" type="text" placeholder="Search..." id="s-iss" oninput="rt('iss')"/>
            <select class="filter-sel" id="f-iss-office" onchange="rt('iss')"><option value="">All offices</option></select>
            <select class="filter-sel" id="f-iss-category" onchange="rt('iss')" title="Consumable filter">
              <option value="all">All categories</option>
              <option value="consumable">Consumable</option>
              <option value="non_consumable">Non-consumable</option>
            </select>
          </div>
          <div class="inv-hd-right">
            <?php if ($canCreate): ?>
            <button type="button" class="btn btn-ghost inv-hd-action" onclick="openIssueParModal()">
              <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 7h10M8 3l4 4-4 4"/></svg>Issue PAR
            </button>
            <?php endif; ?>
          </div>
        </div>
        <div id="tbl-iss"></div>
      </div>
    </div>

    <!-- PROCUREMENT (PR · RFQ · PO · AIR) -->
    <div class="page" id="pg-procurement" data-can-print="<?php echo $canPrint ? '1' : '0'; ?>">
        <div class="alert alert-info proc-info-banner"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="6"/><path d="M7 6v4M7 4.5v.5"/></svg>Purchase Request · canvass quotations · Purchase Order · Acceptance &amp; Inspection Report. Submitting a PR opens a confirmation before it is sent for admin canvass approval.</div>
      <p id="proc-migrate-hint" class="alert alert-danger" style="display:none;"></p>
      <div class="proc-layout">
        <div class="card proc-pr-panel">
          <div class="card-hd card-hd-inv proc-panel-hd proc-panel-hd--pr">
            <div class="inv-hd-left">
              <span class="card-ttl"><i class="fas fa-file-invoice-dollar proc-heading-icon" aria-hidden="true"></i>Purchase requests</span>
              <input class="search-inp" type="text" placeholder="Search PR…" id="proc-s-pr" oninput="rtProcList()" />
              <select class="filter-sel" id="f-proc-office" onchange="loadProcurement()"><option value="">All offices</option></select>
              <select class="filter-sel" id="f-proc-status" onchange="loadProcurement()">
                <option value="">All statuses</option>
                <option value="draft">Draft</option>
                <option value="needs_revision">Need revision</option>
                <option value="submitted">Awaiting approval</option>
                <option value="quoting">Quoting</option>
                <option value="po_issued">PO Issued</option>
                <option value="completed">Completed</option>
              </select>
            </div>
            <div class="inv-hd-right">
              <?php if ($canCreate): ?>
              <button type="button" class="btn btn-dark inv-hd-action" onclick="procOpenProcPrModalCreate()"><i class="fas fa-plus" aria-hidden="true"></i> New PR</button>
              <?php endif; ?>
            </div>
          </div>
          <div class="proc-pr-scroll">
            <table class="proc-pr-table">
              <thead><tr><th>PR No.</th><th>Office</th><th>Date</th><th>Status</th><th class="proc-th-num">RFQ #</th><th class="proc-th-money">Estimate</th><th class="proc-th-ctr">PO?</th><th class="proc-th-ctr">Action</th></tr></thead>
              <tbody id="proc-pr-tbody"><tr><td colspan="8" class="muted">Open this page again after login.</td></tr></tbody>
            </table>
          </div>
        </div>
        <div class="card proc-detail-pane proc-workflow-panel" id="proc-workflow-panel" hidden>
          <div class="card-hd proc-panel-hd proc-panel-hd--workflow">
            <div class="proc-panel-lead">
              <span class="card-ttl"><i class="fas fa-diagram-project proc-heading-icon" aria-hidden="true"></i>Workflow &amp; print</span>
              <p class="proc-panel-desc muted">Quotations · PO · AIR · print previews open in a new tab.</p>
            </div>
            <div class="proc-panel-actions">
              <button type="button" class="btn btn-ghost btn-sm" id="proc-workflow-close" title="Back to purchase requests"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back</button>
            </div>
          </div>
          <div id="proc-detail"><p class="muted">Pick a purchase request in the list to record canvass quotes, award a PO, and file an AIR.</p></div>
        </div>
      </div>
    </div>

    <!-- FORECAST -->
    <div class="page" id="pg-forecast">
      <section class="fcx-hero fcx-hero-simple" aria-labelledby="fc-smart-title">
        <div class="fcx-hero-copy">
          <h2 id="fc-smart-title">Consumables PR spending forecast</h2>
          <p class="fcx-hero-lead" id="fc-hero-lead">Based on completed Purchase Requests by department. Forecast predicts <?php echo htmlspecialchars($fcForecastLbl, ENT_QUOTES, 'UTF-8'); ?> from <?php echo htmlspecialchars($fcTrainFromLbl, ENT_QUOTES, 'UTF-8'); ?>–<?php echo htmlspecialchars($fcTrainToLbl, ENT_QUOTES, 'UTF-8'); ?> spending.</p>
          <p class="fcx-hero-meta" id="fc-meta" hidden></p>
        </div>
        <div class="fcx-hero-side">
          <button type="button" class="fcx-info-btn" id="fc-hero-info-btn" title="How forecast works" aria-label="How this forecast works">
            <i class="fas fa-info" aria-hidden="true"></i>
          </button>
          <?php if ($canEdit): ?>
          <button type="button" class="btn btn-light" id="forecast-rebuild-btn" onclick="rebuildForecast()">Refresh</button>
          <?php endif; ?>
        </div>
      </section>

      <div class="fcx-panel fcx-panel-overall" id="fc-panel-overall">
        <div class="fcx-panel-hd fcx-panel-hd-row">
          <div class="fcx-panel-hd-text">
            <h3>Overall departments</h3>
            <p id="fc-overall-sub">Outlook from completed consumable Purchase Request spending</p>
          </div>
          <span class="fcx-overall-count" id="fc-overall-count" aria-live="polite"></span>
        </div>
        <div id="fc-overall-summary" class="fcx-overall-summary"></div>
        <div class="fcx-brief-charts fcx-overall-charts">
          <div class="fcx-brief-chart-card fcx-overall-chart-card fcx-overall-chart-fuse">
            <div class="fcx-chart-card-hd">
              <div>
                <h4 id="fc-overall-chart-title"><?php echo htmlspecialchars($fcForecastLbl, ENT_QUOTES, 'UTF-8'); ?> predicted ₱ &amp; PRs</h4>
                <span class="fcx-chart-axis-lbl">Grouped by department</span>
              </div>
              <div class="fcx-fuse-legend" aria-hidden="true">
                <span class="fcx-fuse-leg fcx-fuse-leg-peso"><i></i> Predicted ₱</span>
                <span class="fcx-fuse-leg fcx-fuse-leg-pr"><i></i> Predicted PRs</span>
              </div>
            </div>
            <div class="fcx-chart-box fcx-chart-box-overall fcx-chart-box-fuse" id="fc-overall-fuse-wrap">
              <canvas id="fc-overall-fuse-chart" aria-label="Predicted budget and PRs by office"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="fcx-panel fcx-panel-depts" id="fc-panel-depts">
        <div class="fcx-panel-hd fcx-panel-hd-row">
          <div class="fcx-panel-hd-text">
            <h3>Department briefing</h3>
            <p id="fc-dept-panel-sub">Pick an office for <?php echo htmlspecialchars($fcForecastLbl, ENT_QUOTES, 'UTF-8'); ?> predicted spending vs allotted budget (avg monthly spend)</p>
          </div>
          <div class="fcx-toolbar fcx-toolbar-inline">
            <label class="fcx-dept-select-wrap" for="fc-dept-select">
              <span class="sr-only">Department</span>
              <select id="fc-dept-select" aria-label="Select department">
                <option value="">Select department…</option>
              </select>
            </label>
          </div>
        </div>
        <div id="fc-dept-briefing" class="fcx-briefing fcx-briefing-full" aria-live="polite">
          <p class="muted fcx-brief-empty">Select a department to see the full forecast briefing.</p>
        </div>
      </div>
    </div>
    <!-- UNSERVICEABLE -->
    <div class="page" id="pg-unserviceable">
      <div class="card">
        <div class="card-hd card-hd-inv">
          <div class="inv-hd-left">
            <span class="card-ttl">Unserviceable Items</span>
            <input class="search-inp" type="search" id="uns-search" placeholder="Search…" oninput="applyUnserviceableFilters()" autocomplete="off" aria-label="Search unserviceable reports"/>
            <select class="filter-sel" id="f-uns-status" onchange="applyUnserviceableFilters()" title="Status">
              <option value="">All statuses</option>
              <option value="Pending">Needs action</option>
              <option value="Defective">Cannot repair</option>
            </select>
            <select class="filter-sel" id="f-uns-office" onchange="applyUnserviceableFilters()" title="Department">
              <option value="">All departments</option>
            </select>
            <button type="button" class="btn btn-ghost btn-sm" id="uns-more-btn" onclick="toggleUnsMoreFilters()">More</button>
          </div>
          <div class="inv-hd-right">
            <?php if (PermissionHelper::isAdmin()): ?>
            <button type="button" class="btn btn-red inv-hd-action" id="uns-bulk-dispose-btn" hidden onclick="openDisposeConfirmFromList()">Dispose selected</button>
            <?php endif; ?>
            <?php if ($canCreate): ?>
            <button type="button" class="btn btn-ghost inv-hd-action" onclick="openModal('unserv-modal');populateUnservSelect()">Mark unserviceable</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="uns-more-filters" id="uns-more-filters" hidden>
          <select class="filter-sel" id="f-uns-category" onchange="applyUnserviceableFilters()" title="Item category">
            <option value="">All categories</option>
            <option value="SE">Semi-Expendable</option>
            <option value="PPE">PPE</option>
          </select>
          <select class="filter-sel" id="f-uns-reason" onchange="applyUnserviceableFilters()" title="Reason for unserviceability">
            <option value="">All reasons</option>
            <option value="Damaged">Damaged</option>
            <option value="Not Working">Not Working</option>
            <option value="Broken">Broken</option>
            <option value="Missing Parts">Missing Parts</option>
            <option value="Worn Out">Worn Out</option>
            <option value="Other">Other</option>
          </select>
          <select class="filter-sel" id="f-uns-date" onchange="onUnserviceableDatePresetChange()" title="Date reported">
            <option value="">All dates</option>
            <option value="today">Today</option>
            <option value="week">This week</option>
            <option value="month">This month</option>
            <option value="year">This year</option>
            <option value="custom">Custom date range</option>
          </select>
          <span class="uns-date-custom" id="uns-date-custom" hidden>
            <input type="date" class="filter-sel" id="f-uns-date-from" onchange="applyUnserviceableFilters()" title="From"/>
            <span class="uns-date-sep">to</span>
            <input type="date" class="filter-sel" id="f-uns-date-to" onchange="applyUnserviceableFilters()" title="To"/>
          </span>
          <select class="filter-sel" id="f-uns-sort" onchange="applyUnserviceableFilters()" title="Sort">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="name_asc">Item name A–Z</option>
            <option value="name_desc">Item name Z–A</option>
            <option value="date_desc">Date reported (newest)</option>
            <option value="date_asc">Date reported (oldest)</option>
          </select>
        </div>
        <p class="uns-result-count" id="uns-result-count" hidden></p>
        <p class="uns-bulk-hint" id="uns-bulk-hint" hidden></p>
        <div id="tbl-uns"></div>
      </div>
    </div>

    <!-- DISPOSAL -->
    <div class="page" id="pg-disposal">
      <div class="info-box">
        <div class="info-box-title">Disposal Records</div>
        <div class="info-box-text">Items appear here after GSO records disposal. Disposed items stay on this page and do not return to Unserviceable Items.</div>
      </div>
      <div class="card">
        <div class="card-hd"><span class="card-ttl">Disposal Records</span></div>
        <div id="tbl-dis"></div>
      </div>
    </div>

    <div class="page" id="pg-activity">
      <div class="alert alert-info"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="6"/><path d="M7 6v4M7 4.5v.5"/></svg><?php echo $activityOfficeLocked
          ? 'This log is limited to your department. Activity from other offices is not shown.'
          : 'All activity shows inventory, issuance, procurement, unserviceable, reports, and GSO admin work. Department users’ sign-ins stay in that office’s log. Pick a department to filter.'; ?></div>
      <div class="card">
        <div class="card-hd card-hd-inv">
          <div class="inv-hd-left">
            <span class="card-ttl">Activity log</span>
            <input class="search-inp" type="search" id="activity-search" placeholder="Search who, action, record…" oninput="filterActivityLog()" autocomplete="off"/>
            <?php if ($activityOfficeLocked): ?>
            <input type="hidden" id="audit-office-filter" value="<?php echo (int) $activityOfficeId; ?>">
            <span class="filter-sel" style="display:inline-flex;align-items:center;pointer-events:none;"><?php echo htmlspecialchars($activityOfficeName !== '' ? $activityOfficeName : 'Your department', ENT_QUOTES, 'UTF-8'); ?></span>
            <?php else: ?>
            <select class="filter-sel" id="audit-office-filter" aria-label="Department" onchange="loadActivityLog()">
              <option value="all">All activity</option>
              <option value="admin">Admin only</option>
            </select>
            <?php endif; ?>
          </div>
          <div class="inv-hd-right">
            <button type="button" class="btn btn-ghost inv-hd-action" onclick="loadActivityLog()">Refresh</button>
          </div>
        </div>
        <div id="tbl-activity"></div>
      </div>
    </div>

    <div class="page" id="pg-settings">
      <div id="settings-root" data-settings-role="admin"></div>
    </div>

  </div>
</main>
</div>

<?php include dirname(__DIR__) . '/includes/modals.php'; ?>
<?php include dirname(__DIR__) . '/includes/gso_chatbot_panel.php'; ?>

<div class="toast" id="toast"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/app.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/property-tag.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/procurement-module.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/forecast-module.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/settings-module.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/gso-chatbot.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
