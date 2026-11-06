<?php
/**
 * Seed EXAMPLE data for Admin Dashboard analytics charts.
 * All rows are tagged so they can be removed later without touching real data.
 *
 * Marker: remarks / purpose / form remarks contain [DASHBOARD_DEMO]
 * Also: property_number DEMO-…, pr_number DEMO-PR-…, form_number DEMO-…
 *
 * Run (CLI):
 *   C:\xampp\php\php.exe scripts\seed-dashboard-demo-data.php
 * Remove later:
 *   C:\xampp\php\php.exe scripts\remove-dashboard-demo-data.php
 *
 * Or open in browser while logged in as Admin:
 *   /lgu_invent/scripts/seed-dashboard-demo-data.php?confirm=YES
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
        $path = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/lgu_invent/scripts/seed-dashboard-demo-data.php');
        echo '<h1>Seed dashboard example data</h1>';
        echo '<p>Inserts temporary [DASHBOARD_DEMO] inventory + PR rows so analytics charts have sample numbers.</p>';
        echo '<p><a href="' . $path . '?confirm=YES" style="background:#16733A;color:#fff;padding:10px 16px;text-decoration:none;border-radius:8px;font-weight:700;">Confirm: seed example data</a></p>';
        exit;
    }
}

try {
    $db = getDBConnection();
    $summary = dashboardDemoSeed($db);
    $msg = 'Dashboard demo data seeded. '
        . 'Items: ' . $summary['items']
        . ', Issuances: ' . $summary['issuances']
        . ', PRs: ' . $summary['prs']
        . ', PR lines: ' . $summary['pr_lines']
        . '. Refresh the Admin Dashboard (Ctrl+F5).';
    if ($isCli) {
        echo $msg . PHP_EOL;
        echo 'Remove later: php scripts/remove-dashboard-demo-data.php' . PHP_EOL;
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
