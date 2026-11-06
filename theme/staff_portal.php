<?php
/**
 * Staff / departmental procurement workspace — procurement only (same APIs & procurement-module.js).
 * Admins use the main dashboard shell (theme/index.php).
 */

require_once dirname(__DIR__) . '/includes/session.php';
lgueStartSession();

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';

PermissionHelper::require('view');

if (PermissionHelper::isAdmin()) {
    header('Location: ' . lgue_url('/'));
    exit;
}

settingsApplySessionTimeout();

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'staff';
$userRoleDisplay = PermissionHelper::getRoleDisplayName($userRole);

$canCreate = PermissionHelper::can('create');
$canEdit = PermissionHelper::can('edit');
$canDelete = PermissionHelper::can('delete');
$canPrint = PermissionHelper::can('print');
$isViewer = PermissionHelper::isViewer();

require_once dirname(__DIR__) . '/includes/consumable_pr_forecast.php';
$fcWindow = consumableForecastPrWindow(null, getDBConnection());
$fcForecastLbl = consumableForecastMonthLabel((string) ($fcWindow['forecast_ym'] ?? ''));

$officeId = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : 0;
$officeName = (string) ($_SESSION['user_office_name'] ?? '');
$officeCode = '';
$prPrintedName = '';
$prDesignation = '';
$mayorPrintName = defined('RPCPPE_APPROVER_NAME') ? RPCPPE_APPROVER_NAME : 'BELEN B. RAGA';
$mayorPrintTitle = defined('RPCPPE_APPROVER_DESIGNATION') ? RPCPPE_APPROVER_DESIGNATION : 'Municipal Mayor';

$themeKey = 'default';
if ($officeId > 0) {
    try {
        $odb = getDBConnection();
        $ost = $odb->prepare('SELECT code, name FROM offices WHERE id = ? LIMIT 1');
        $ost->execute([$officeId]);
        $orow = $ost->fetch(PDO::FETCH_ASSOC);
        if ($orow) {
            $officeCode = strtoupper(trim((string) ($orow['code'] ?? '')));
            if ($officeName === '' && !empty($orow['name'])) {
                $officeName = (string) $orow['name'];
            }
            $map = [
                'ENGR' => 'engr',
                'ACCT' => 'acct',
                'BUDG' => 'budg',
                'TRES' => 'tres',
                'MAYOR' => 'mayor',
                'VMAY' => 'vmay',
                'HLTH' => 'hlth',
                'SWDO' => 'swdo',
                'PLAN' => 'plan',
                'GSO' => 'gso',
            ];
            $themeKey = $map[$officeCode] ?? 'default';
        }
        if (function_exists('settingsUserPrefs')) {
            $prPrefs = settingsUserPrefs($odb, (int) ($_SESSION['user_id'] ?? 0));
            $prPrintedName = trim((string) ($prPrefs['pr_printed_name'] ?? ''));
            $prDesignation = trim((string) ($prPrefs['pr_designation'] ?? ''));
        }
    } catch (Exception $e) {
        $themeKey = 'default';
    }
    if ($officeName !== '' && trim((string) ($_SESSION['user_office_name'] ?? '')) === '') {
        $_SESSION['user_office_name'] = $officeName;
    }
}

if ($prPrintedName === '' && $prDesignation === '') {
    try {
        $prefConn = isset($odb) && $odb instanceof PDO ? $odb : getDBConnection();
        if (function_exists('settingsUserPrefs')) {
            $prPrefs = settingsUserPrefs($prefConn, (int) ($_SESSION['user_id'] ?? 0));
            $prPrintedName = trim((string) ($prPrefs['pr_printed_name'] ?? ''));
            $prDesignation = trim((string) ($prPrefs['pr_designation'] ?? ''));
        }
        if (function_exists('settingsGet')) {
            $savedMayor = trim((string) settingsGet($prefConn, 'mayor_name', $mayorPrintName));
            $savedMayorTitle = trim((string) settingsGet($prefConn, 'mayor_designation', $mayorPrintTitle));
            if ($savedMayor !== '') {
                $mayorPrintName = $savedMayor;
            }
            if ($savedMayorTitle !== '') {
                $mayorPrintTitle = $savedMayorTitle;
            }
        }
    } catch (Exception $e) {
        // Defaults stay login name + department on the PR form.
    }
}

$pageTitle = 'Procurement · ' . ($officeName !== '' ? $officeName : 'Department');
?>
<!DOCTYPE html>
<html lang="en" class="staff-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="lgue-web-base" content="<?php echo htmlspecialchars(LGUE_WEB_BASE, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?> · LGU</title>
    <?php require __DIR__ . '/../includes/favicon-links.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(lgue_url('assets/css/style.css', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(lgue_url('assets/css/app-loading.css', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(lgue_url('assets/css/staff-portal.css', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(lgue_url('assets/css/procurement-ui.css', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="staff-portal app-loading-active"
      data-theme="<?php echo htmlspecialchars($themeKey, ENT_QUOTES, 'UTF-8'); ?>"
      data-office-code="<?php echo htmlspecialchars($officeCode, ENT_QUOTES, 'UTF-8'); ?>">

<div id="app-loading-screen" class="app-loading-screen" role="status" aria-live="polite" aria-busy="true">
    <div class="app-loading-screen__panel">
        <div class="app-loading-screen__logo">
            <img src="<?php echo htmlspecialchars(lgue_url('public/assets/logo-lumban.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Municipality of Lumban logo">
        </div>
        <p class="app-loading-screen__title"><?php echo htmlspecialchars($pageTitle); ?></p>
        <p class="app-loading-screen__sub">Loading your workspace. Hides when the page load completes, then procurement data loads in the background.</p>
        <div class="app-loading-screen__progress" aria-hidden="true"></div>
    </div>
</div>

<div class="staff-ambient" aria-hidden="true"></div>

<div class="staff-app">
<aside class="staff-sb" aria-label="Department navigation">
    <div class="staff-sb-brand">
        <div class="staff-sb-logo">
            <img src="<?php echo htmlspecialchars(lgue_url('public/assets/logo-lumban.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="">
        </div>
        <div class="staff-sb-brand-text">
            <span class="staff-sb-gov">Municipality of Lumban</span>
            <strong class="staff-sb-sys">Department portal</strong>
        </div>
    </div>
    <nav class="staff-section-tabs staff-sb-nav" role="tablist" aria-label="Workspace sections">
        <div class="staff-sb-sec">Overview</div>
        <button type="button" class="staff-section-tab staff-sb-item active" id="staff-tab-dashboard" onclick="staffShowSection('dashboard')">
            <i class="fas fa-gauge-high" aria-hidden="true"></i>
            <span>Dashboard</span>
        </button>
        <div class="staff-sb-sec">Workspace</div>
        <button type="button" class="staff-section-tab staff-sb-item" id="staff-tab-procurement" onclick="staffShowSection('procurement')">
            <i class="fas fa-file-signature" aria-hidden="true"></i>
            <span>Procurement</span>
        </button>
        <button type="button" class="staff-section-tab staff-sb-item" id="staff-tab-inventory" onclick="staffShowSection('inventory')">
            <i class="fas fa-boxes-stacked" aria-hidden="true"></i>
            <span>My inventory</span>
        </button>
        <button type="button" class="staff-section-tab staff-sb-item" id="staff-tab-forecast" onclick="staffShowSection('forecast')">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            <span>Forecast</span>
        </button>
        <div class="staff-sb-sec">Account</div>
        <button type="button" class="staff-section-tab staff-sb-item" id="staff-tab-settings" onclick="staffShowSection('settings')">
            <i class="fas fa-gear" aria-hidden="true"></i>
            <span>Settings</span>
        </button>
    </nav>
    <div class="staff-sb-foot">
        <button type="button" class="staff-sb-logout" onclick="openLogoutConfirm()">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            <span>Sign out</span>
        </button>
    </div>
</aside>

<div class="staff-workspace">
<header class="staff-shell-header">
    <div class="staff-shell-brand">
        <div class="staff-shell-titles">
            <span class="staff-shell-kicker">Dashboard</span>
            <h1 class="staff-shell-title"><?php echo htmlspecialchars($officeName !== '' ? $officeName : 'Procurement'); ?></h1>
        </div>
    </div>

    <div class="staff-shell-actions">
        <div class="staff-shell-chip staff-rise">
            <i class="fas fa-diagram-project"></i>
            <span>PR · RFQ · PO · AIR</span>
        </div>
        <div class="notif-bell-wrapper staff-rise">
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
        <div class="staff-shell-user staff-rise">
            <div class="staff-shell-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            <div class="staff-shell-user-meta">
                <span class="staff-shell-user-name"><?php echo htmlspecialchars($userName); ?></span>
                <span class="staff-shell-user-role"><?php echo htmlspecialchars($userRoleDisplay); ?></span>
            </div>
        </div>
    </div>
</header>

<main class="staff-shell-main">
    <div id="staff-section-dashboard" class="staff-section-pane">
    <section class="staff-dash staff-rise staff-stagger-1" id="staff-dash" aria-label="Department pulse">
        <div class="staff-dash-intro">
            <div>
                <p class="staff-dash-kicker">Office pulse</p>
                <h2 class="staff-dash-title">Your queue, <?php echo htmlspecialchars($officeName !== '' ? $officeName : 'this department'); ?></h2>
                <p class="staff-dash-lead">Live counts from this office only.</p>
            </div>
            <div class="staff-dash-est" id="staff-dash-est">
                <span>Estimated pipeline</span>
                <strong>—</strong>
            </div>
        </div>
        <div class="staff-dash-tiles" id="staff-dash-tiles">
            <div class="staff-dash-tile staff-dash-tile--blue" data-pr-stat="draft" role="group" aria-label="Drafts">
                <span class="staff-dash-tile-top"><span class="staff-dash-tile-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg></span><em>Drafts</em></span>
                <strong>—</strong><span class="staff-dash-tile-hint">Not submitted yet</span>
            </div>
            <div class="staff-dash-tile staff-dash-tile--amber" data-pr-stat="submitted" role="group" aria-label="Waiting">
                <span class="staff-dash-tile-top"><span class="staff-dash-tile-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg></span><em>Waiting</em></span>
                <strong>—</strong><span class="staff-dash-tile-hint">Admin canvass review</span>
            </div>
            <div class="staff-dash-tile staff-dash-tile--red" data-pr-stat="needs_revision" role="group" aria-label="Need revision">
                <span class="staff-dash-tile-top"><span class="staff-dash-tile-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span><em>Need revision</em></span>
                <strong>—</strong><span class="staff-dash-tile-hint">Returned with feedback</span>
            </div>
            <div class="staff-dash-tile staff-dash-tile--teal" data-pr-stat="quoting" role="group" aria-label="Quoting">
                <span class="staff-dash-tile-top"><span class="staff-dash-tile-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><em>Quoting</em></span>
                <strong>—</strong><span class="staff-dash-tile-hint">Three canvass slots</span>
            </div>
            <div class="staff-dash-tile staff-dash-tile--green" data-pr-stat="po" role="group" aria-label="With PO">
                <span class="staff-dash-tile-top"><span class="staff-dash-tile-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/></svg></span><em>With PO</em></span>
                <strong>—</strong><span class="staff-dash-tile-hint">PO issued or done</span>
            </div>
            <div class="staff-dash-tile staff-dash-tile--gold" data-inv-stat="1" role="group" aria-label="On hand">
                <span class="staff-dash-tile-top"><span class="staff-dash-tile-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/></svg></span><em>On hand</em></span>
                <strong>—</strong><span class="staff-dash-tile-hint">Office inventory items</span>
            </div>
        </div>
        <div class="staff-dash-chart-card">
            <div class="staff-dash-chart-hd">
                <div>
                    <h3>PR pipeline</h3>
                    <p class="staff-dash-sub">This office’s drafts, waiting, need revision, quoting, and with PO</p>
                </div>
            </div>
            <div class="staff-dash-chart-body">
                <div class="staff-dash-donut-wrap">
                    <canvas id="staff-pr-mix-chart" height="240"></canvas>
                    <div class="staff-dash-donut-center" id="staff-pr-mix-center">
                        <strong id="staff-pr-mix-total">0</strong>
                        <span>PRs</span>
                    </div>
                </div>
                <div class="staff-dash-chart-legend" id="staff-pr-mix-legend"></div>
            </div>
        </div>
        <div class="staff-dash-lower">
            <div class="staff-dash-mix">
                <h3>Do next</h3>
                <p class="staff-dash-sub">What this office should handle now — not another status chart.</p>
                <div id="staff-dash-next"><p class="muted">Checking your queue…</p></div>
            </div>
            <div class="staff-dash-attn">
                <h3>Needs attention</h3>
                <div id="staff-dash-attn"><p class="muted">Checking purchase requests…</p></div>
            </div>
        </div>
    </section>
    </div>

    <!-- Procurement module attaches here (same IDs as admin SPA for shared JS) -->
    <div class="staff-proc-board staff-rise staff-stagger-2 staff-section-pane" id="staff-section-procurement" style="display:none;">
        <div id="pg-procurement"
             class="staff-proc-mount page active"
             style="display:block;"
             data-can-print="<?php echo $canPrint ? '1' : '0'; ?>">
            <p id="proc-migrate-hint" class="staff-alert-danger" style="display:none;"></p>
            <div class="staff-proc-single">
                <div class="staff-card staff-card-list staff-pr-workspace" id="staff-pr-panel">
                    <div class="staff-pr-panel-head">
                        <div class="staff-pr-panel-brand">
                            <div class="staff-pr-panel-icon" aria-hidden="true">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div class="staff-pr-panel-text">
                                <h3 class="staff-pr-panel-title">Purchase requests</h3>
                                <p class="staff-pr-panel-desc" id="staff-pr-count">Scroll the list to see every request. Click a row to open workflow.</p>
                            </div>
                        </div>
                        <div class="staff-pr-toolbar">
                            <?php if ($canCreate): ?>
                            <button type="button" class="staff-pr-btn-new staff-btn staff-btn-primary btn-dark btn-sm" onclick="procOpenProcPrModalCreate()">
                                <span class="staff-pr-btn-new-inner"><i class="fas fa-plus"></i> New PR</span>
                            </button>
                            <?php endif; ?>
                            <select class="filter-sel staff-search staff-pr-filter" id="f-proc-status" onchange="rtProcList()" aria-label="Filter by status">
                                <option value="">All statuses</option>
                                <option value="draft">Drafts</option>
                                <option value="submitted">Waiting</option>
                                <option value="needs_revision">Need revision</option>
                                <option value="quoting">Quoting</option>
                                <option value="po">With PO</option>
                            </select>
                            <select class="filter-sel staff-search staff-pr-filter" id="f-proc-kind" onchange="rtProcList()" aria-label="Filter by type">
                                <option value="">All types</option>
                                <option value="consumable">Consumable</option>
                                <option value="non_consumable">Non-consumable</option>
                            </select>
                            <label class="staff-pr-search-wrap">
                                <span class="sr-only">Search purchase requests</span>
                                <i class="fas fa-magnifying-glass staff-pr-search-ico" aria-hidden="true"></i>
                                <input class="search-inp staff-search staff-pr-search" type="text" placeholder="Search PR…" id="proc-s-pr" oninput="rtProcList()" autocomplete="off" />
                            </label>
                        </div>
                    </div>
                    <div class="staff-table-wrap staff-pr-table-shell">
                        <table class="staff-pr-table">
                            <colgroup>
                                <col class="staff-pr-col-num" />
                                <col class="staff-pr-col-office" />
                                <col class="staff-pr-col-date" />
                                <col class="staff-pr-col-status" />
                                <col class="staff-pr-col-rfq" />
                                <col class="staff-pr-col-est" />
                                <col class="staff-pr-col-po" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col" title="Purchase request number">PR No.</th>
                                    <th scope="col" title="Requesting office">Office</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" title="Quotation canvass count">RFQ #</th>
                                    <th scope="col" title="Estimated total">Est. ₱</th>
                                    <th scope="col" title="Purchase order recorded">PO</th>
                                </tr>
                            </thead>
                            <tbody id="proc-pr-tbody">
                                <tr><td colspan="7" class="muted">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Read-only inventory of items belonging to this office (Semi-Expendable, PPE, Issued, Unserviceable, Disposed) -->
    <div class="staff-proc-board staff-rise" id="staff-section-inventory" style="display:none;">
        <div class="staff-card staff-card-list">
            <div class="staff-pr-panel-head">
                <div class="staff-pr-panel-brand">
                    <div class="staff-pr-panel-icon" aria-hidden="true">
                        <i class="fas fa-boxes-stacked"></i>
                    </div>
                    <div class="staff-pr-panel-text">
                        <h3 class="staff-pr-panel-title">My Office Inventory</h3>
                        <p class="staff-pr-panel-desc">Property items assigned to <?php echo htmlspecialchars($officeName !== '' ? $officeName : 'your office'); ?>. Report an item as unserviceable when it is damaged or not working.</p>
                    </div>
                </div>
                <div class="staff-pr-toolbar">
                    <select class="filter-sel staff-search" id="inv-dept-category" onchange="loadDeptInventory()">
                        <option value="">All categories</option>
                        <option value="SE">Semi-Expendable</option>
                        <option value="PPE">PPE</option>
                    </select>
                    <select class="filter-sel staff-search" id="inv-dept-status" onchange="loadDeptInventory()">
                        <option value="">All statuses</option>
                        <option value="Active">Active</option>
                        <option value="Issued">Issued</option>
                        <option value="Pending">Pending</option>
                        <option value="Unserviceable">Unserviceable</option>
                        <option value="Defective">Defective</option>
                        <option value="Disposed">Disposed</option>
                    </select>
                    <label class="staff-pr-search-wrap">
                        <span class="sr-only">Search inventory</span>
                        <i class="fas fa-magnifying-glass staff-pr-search-ico" aria-hidden="true"></i>
                        <input class="search-inp staff-search staff-pr-search" type="search" placeholder="Search property #, article, description…" id="inv-dept-search" oninput="loadDeptInventory()" autocomplete="off" />
                    </label>
                </div>
            </div>
            <div class="staff-table-wrap staff-pr-table-shell" id="inv-dept-table">
                <p class="muted" style="padding:16px">Loading…</p>
            </div>
        </div>
    </div>

    <!-- Department-scoped consumables forecast (own office only) -->
    <div class="staff-proc-board staff-rise" id="staff-section-forecast" style="display:none;">
        <div id="pg-forecast">
            <section class="fcx-hero fcx-hero-simple" aria-labelledby="fc-smart-title">
                <div class="fcx-hero-copy">
                    <p class="fcx-kicker">Consumables forecast</p>
                    <h2 id="fc-smart-title">Your office <?php echo htmlspecialchars($fcForecastLbl, ENT_QUOTES, 'UTF-8'); ?> forecast</h2>
                    <p class="fcx-hero-lead">Based on this department’s completed consumable Purchase Requests.</p>
                    <p class="fcx-hero-meta" id="fc-meta"></p>
                </div>
                <div class="fcx-hero-side">
                    <button type="button" class="fcx-info-btn" id="fc-hero-info-btn" title="How forecast works" aria-label="How this forecast works">
                        <i class="fas fa-info" aria-hidden="true"></i>
                    </button>
                </div>
            </section>

            <div id="fc-dept-briefing" class="fcx-briefing" aria-live="polite">
                <p class="muted">Loading department forecast…</p>
            </div>

            <!-- Kept for JS compatibility (admin list uses this id) -->
            <div id="fc-panel-depts" hidden>
                <tbody id="fc-dept-tbody"></tbody>
                <input type="search" id="fc-dept-search" hidden />
            </div>
        </div>
    </div>

    <div class="staff-proc-board staff-rise" id="staff-section-settings" style="display:none;">
        <div id="settings-root" data-settings-role="staff"></div>
    </div>
</main>
</div><!-- /.staff-workspace -->
</div><!-- /.staff-app -->

<!-- Workflow detail modal (staff only — #proc-detail shared with procurement-module.js) -->
<div id="proc-workflow-overlay" class="staff-workflow-overlay" aria-hidden="true">
    <div class="staff-workflow-modal" role="dialog" aria-modal="true" aria-labelledby="staff-workflow-modal-title">
        <header class="staff-workflow-modal-head">
            <div class="staff-workflow-modal-brand">
                <span class="staff-workflow-modal-badge" aria-hidden="true"><i class="fas fa-diagram-project"></i></span>
                <div class="staff-workflow-modal-head-main">
                    <div class="staff-workflow-modal-head-text">
                        <p class="staff-workflow-modal-kicker">Staff procurement</p>
                        <h2 id="staff-workflow-modal-title" class="staff-workflow-modal-title">Workflow &amp; print</h2>
                        <p class="staff-workflow-modal-tagline">Canvass slots, PO issuance, AIR, and print-ready previews in one place.</p>
                    </div>
                    <nav id="staff-workflow-modal-nav" class="staff-workflow-modal-nav" aria-label="Jump to section"></nav>
                </div>
            </div>
            <button type="button" class="staff-workflow-modal-close" id="proc-workflow-modal-close" aria-label="Close workflow panel">
                <i class="fas fa-xmark"></i>
            </button>
        </header>
        <div id="proc-detail" class="staff-workflow-modal-body staff-detail-body"></div>
    </div>
</div>

<!-- Hidden payload for procurement-module.js + permission checks -->
<div id="userData" style="display:none;"
     data-user-name="<?php echo htmlspecialchars($userName); ?>"
     data-user-role="<?php echo htmlspecialchars($userRole); ?>"
     data-user-office-id="<?php echo (int) ($_SESSION['user_office_id'] ?? 0); ?>"
     data-user-office-name="<?php echo htmlspecialchars($officeName !== '' ? $officeName : (string) ($_SESSION['user_office_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
     data-pr-printed-name="<?php echo htmlspecialchars($prPrintedName, ENT_QUOTES, 'UTF-8'); ?>"
     data-pr-designation="<?php echo htmlspecialchars($prDesignation, ENT_QUOTES, 'UTF-8'); ?>"
     data-mayor-name="<?php echo htmlspecialchars($mayorPrintName, ENT_QUOTES, 'UTF-8'); ?>"
     data-mayor-title="<?php echo htmlspecialchars($mayorPrintTitle, ENT_QUOTES, 'UTF-8'); ?>"
     data-user-is-admin="0"
     data-user-dept-scoped="1"
     data-can-create="<?php echo $canCreate ? '1' : '0'; ?>"
     data-can-edit="<?php echo $canEdit ? '1' : '0'; ?>"
     data-can-delete="<?php echo $canDelete ? '1' : '0'; ?>"
     data-is-viewer="<?php echo $isViewer ? '1' : '0'; ?>"
     data-ppe-threshold="<?php echo (int) settingsPpeThreshold(); ?>"></div>

<?php include dirname(__DIR__) . '/includes/modals.php'; ?>
<?php include dirname(__DIR__) . '/includes/gso_chatbot_panel.php'; ?>

<div class="toast" id="toast"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/app.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/procurement-module.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/forecast-module.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/staff-dashboard.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/settings-module.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(lgue_url('assets/js/gso-chatbot.js', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
