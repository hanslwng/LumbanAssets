<?php
/**
 * Remove [DASHBOARD_DEMO] example rows only (does not wipe real data).
 *
 * CLI: C:\xampp\php\php.exe scripts\remove-dashboard-demo-data.php
 * Browser (Admin): .../scripts/remove-dashboard-demo-data.php?confirm=YES
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/bootstrap.php';
require_once $projectRoot . '/app/helpers/PermissionHelper.php';
require_once $projectRoot . '/scripts/lib/dashboard_demo_data.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Content-Type: text/html; charset=UTF-8');
    if (!isset($_SESSION['user_id']) || !PermissionHelper::isAdmin()) {
        http_response_code(403);
        exit('<p>Login as Administrator first.</p>');
    }
    $ok = isset($_GET['confirm']) && strtoupper(trim((string) $_GET['confirm'])) === 'YES';
    if (!$ok) {
        $path = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/lgu_invent/scripts/remove-dashboard-demo-data.php');
        echo '<h1>Remove dashboard example data</h1>';
        echo '<p>Deletes only rows tagged [DASHBOARD_DEMO] / DEMO- prefixes.</p>';
        echo '<p><a href="' . $path . '?confirm=YES" style="background:#B22222;color:#fff;padding:10px 16px;text-decoration:none;border-radius:8px;font-weight:700;" onclick="return confirm(\'Remove dashboard demo data?\');">Confirm: remove example data</a></p>';
        exit;
    }
}

try {
    $db = getDBConnection();
    $summary = dashboardDemoRemove($db);
    $msg = 'Dashboard demo data removed. Deleted property items: ' . $summary['items']
        . ', PRs: ' . $summary['prs'] . '.';
    if ($isCli) {
        echo $msg . PHP_EOL;
    } else {
        echo '<p style="color:#16733A;font-weight:700;">' . htmlspecialchars($msg) . '</p>';
        echo '<p><a href="' . htmlspecialchars(lgue_url('/')) . '">Open app</a></p>';
    }
} catch (Throwable $e) {
    if ($isCli) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo '<p style="color:#BE1D1D;">' . htmlspecialchars($e->getMessage()) . '</p>';
}
