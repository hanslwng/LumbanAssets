<?php
/**
 * Clears transactional demo data so the UI shows an empty slate (inventory, PRs, etc.).
 *
 * Keeps table structures, `offices`, `system_settings`, and Administrator rows in `users`.
 * Removes temporary User / Viewer rows from `users` (same rule as cleanup_non_admin_users.sql).
 *
 * Requires an Admin browser session AND confirm=YES.
 * Browser: scripts/cleanup-transactional-demo-data.php?confirm=YES
 *
 * Uses TRUNCATE (MySQL may implicit-commit between statements). Cannot fully roll back afterward.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/bootstrap.php';
require_once $projectRoot . '/app/helpers/PermissionHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('<p>Login as an Administrator first.</p><p><a href="' . htmlspecialchars(lgue_url('login')) . '">Go to login</a></p>');
}

if (!PermissionHelper::isAdmin()) {
    http_response_code(403);
    exit('<p>Only Administrators can run this cleanup.</p>');
}

$confirmed = isset($_GET['confirm']) && strtoupper(trim((string) $_GET['confirm'])) === 'YES';

function h($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Tables that contain demo transactional data (whitelist only). */
$truncateTables = [
    'disposal_records',
    'return_records',
    'issuance_records',
    'inventory_history',
    'unserviceable_records',
    'property_items',
    'proc_air_line',
    'proc_air',
    'proc_po_line',
    'proc_po',
    'proc_quotation_line',
    'proc_quotation',
    'proc_pr_line',
    'proc_pr',
];

$db = getDBConnection();

$nAdmin = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
$nItems = (int) $db->query('SELECT COUNT(*) FROM property_items')->fetchColumn();
$nPr = (int) $db->query('SELECT COUNT(*) FROM proc_pr')->fetchColumn();
$nUsersOther = (int) $db->query(
    "SELECT COUNT(*) FROM users WHERE COALESCE(TRIM(role), '') <> 'Admin'"
)->fetchColumn();

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Clear transactional demo data</title>'
    . '<style>body{font-family:sans-serif;max-width:720px;margin:24px;line-height:1.5;} '
    . '.ok{color:#16733a;background:#edfaf3;padding:12px;border-left:4px solid #16733a;} '
    . '.warn{color:#9e5505;background:#fef6e7;padding:12px;border-left:4px solid #9e5505;} '
    . '.danger{color:#be1d1d;background:#fef0f0;padding:12px;border-left:4px solid #be1d1d;} '
    . 'table{border-collapse:collapse;width:100%;margin-top:12px;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} code{font-size:92%;}</style>'
    . '</head><body>';

echo '<h1>Clear inventory &amp; procurement data</h1>';
echo '<p>This <strong>empties transactional tables</strong> (property PAR/ICS, disposal chain, procurement PR→PO→AIR). ';
echo '<code>offices</code>, <code>system_settings</code>, and Administrator accounts stay. ';
echo 'No tables are dropped.</p>';

echo '<p class="warn"><strong>Current counts:</strong><br>'
    . 'Administrators: <strong>' . $nAdmin . '</strong> · '
    . 'Non-admin user rows removed next pass: <strong>' . $nUsersOther . '</strong><br>'
    . '<code>property_items</code>: <strong>' . $nItems . '</strong> · '
    . '<code>proc_pr</code>: <strong>' . $nPr . '</strong></p>';

echo '<p class="danger"><strong>This cannot be undone</strong> from this tool. Backup the database first if you need recovery.</p>';

if (!$confirmed) {
    if ($nAdmin < 1) {
        echo '<p class="danger">No <code>Admin</code> user exists. Aborting so you are not locked out after non-admin purge.</p></body></html>';
        exit;
    }
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/lgu_invent/scripts/cleanup-transactional-demo-data.php'), '?');
    $url = htmlspecialchars($path) . '?confirm=YES';
    echo '<p><a href="' . $url . '" '
        . 'onclick="return confirm(\'Truncate all inventory/procurement data and delete non-Admin users? This is permanent.\');"'
        . 'style="display:inline-block;background:#B22222;color:#fff;padding:10px 18px;text-decoration:none;border-radius:8px;font-weight:bold;">Confirm: clear all demo transactional data</a></p>';
    echo '</body></html>';
    exit;
}

if ($nAdmin < 1) {
    http_response_code(400);
    echo '<p class="danger">No Administrator account exists. Nothing was cleared.</p></body></html>';
    exit;
}

try {
    $db->exec('SET FOREIGN_KEY_CHECKS=0');

    foreach ($truncateTables as $table) {
        if (!preg_match('/^[a-z0-9_]+$/', $table)) {
            throw new RuntimeException('Invalid table name.');
        }
        // TRUNCATE is literal (whitelist above); InnoDB resets AUTO_INCREMENT.
        $sql = sprintf('TRUNCATE TABLE `%s`', str_replace('`', '', $table));
        if ($db->exec($sql) === false) {
            throw new RuntimeException('TRUNCATE failed for `' . $table . '`.');
        }
    }

    $db->exec('SET FOREIGN_KEY_CHECKS=1');

    // Match database/cleanup_non_admin_users.sql
    $st = $db->exec(
        "DELETE FROM users WHERE COALESCE(TRIM(role), '') <> 'Admin'"
    );
    if ($st === false) {
        throw new RuntimeException('DELETE users failed.');
    }
} catch (Throwable $e) {
    try {
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $ignored) {
    }
    http_response_code(500);
    echo '<p class="danger">' . h($e->getMessage()) . '</p></body></html>';
    exit;
}

$nItemsAfter = (int) $db->query('SELECT COUNT(*) FROM property_items')->fetchColumn();
$nPrAfter = (int) $db->query('SELECT COUNT(*) FROM proc_pr')->fetchColumn();
$nUsersAfter = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

echo '<p class="ok">Done. Transactional tables are empty. ';
echo '<code>property_items</code>: ' . $nItemsAfter . ' · <code>proc_pr</code>: ' . $nPrAfter . ' · ';
echo '<code>users</code> (admins left): ' . $nUsersAfter . '.</p>';

echo '<h3>Remaining administrator accounts</h3>';
$rows = $db->query(
    'SELECT id, username, email, full_name, role FROM users ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo '<p class="danger">No users remain — recreate an admin.</p>';
} else {
    echo '<table><tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Role</th></tr>';
    foreach ($rows as $r) {
        echo '<tr>'
            . '<td>' . h($r['id']) . '</td>'
            . '<td>' . h($r['username']) . '</td>'
            . '<td>' . h($r['email']) . '</td>'
            . '<td>' . h($r['full_name']) . '</td>'
            . '<td><strong>' . h($r['role']) . '</strong></td>'
            . '</tr>';
    }
    echo '</table>';
}

echo '<p><a href="' . h(lgue_url('/')) . '">Open app home</a> · ';
echo '<a href="' . h(lgue_url('login')) . '">Login</a></p>';
echo '</body></html>';
