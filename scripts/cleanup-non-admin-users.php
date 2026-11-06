<?php
/**
 * Deletes only non-Administrator rows from `users` — tables stay unchanged.
 *
 * Requires an Admin browser session AND confirm=YES in the URL.
 * Browser (after login): /lgu_invent/scripts/cleanup-non-admin-users.php?confirm=YES
 *
 * Deletes users where role is not Admin (removes temporary User / Viewer registrations).
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

$db = getDBConnection();

$cBefore = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nAdminBefore = $db->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
$nOthers = $db->query("SELECT COUNT(*) FROM users WHERE COALESCE(TRIM(role), '') <> 'Admin'")->fetchColumn();

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Cleanup non-admin users</title>'
    . '<style>body{font-family:sans-serif;max-width:640px;margin:24px;line-height:1.5;} '
    . '.ok{color:#16733a;background:#edfaf3;padding:12px;border-left:4px solid #16733a;} '
    . '.warn{color:#9e5505;background:#fef6e7;padding:12px;border-left:4px solid #9e5505;} '
    . '.danger{color:#be1d1d;background:#fef0f0;padding:12px;border-left:4px solid #be1d1d;} '
    . 'table{border-collapse:collapse;width:100%;margin-top:12px;} th,td{border:1px solid #ddd;padding:8px;text-align:left;}</style>'
    . '</head><body>';

echo '<h1>Remove temporary users (keep Admin)</h1>';
echo '<p>This only <strong>deletes rows</strong> from the <code>users</code> table where role is not <strong>Admin</strong>. No tables are dropped.</p>';

echo '<p class="warn">Currently: <strong>' . (int) $nAdminBefore . '</strong> Administrator(s), <strong>'
    . (int) $nOthers . '</strong> User/Viewer/other account(s) (total rows: ' . (int) $cBefore . ').</p>';

if (!$confirmed) {
    if ((int) $nAdminBefore < 1) {
        echo '<p class="danger">There is no user with role <code>Admin</code>. Aborting so you do not lock yourself out.</p>';
        echo '</body></html>';
        exit;
    }
    $url = htmlspecialchars((isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '')
        ?: '/lgu_invent/scripts/cleanup-non-admin-users.php')
        . '?confirm=YES';
    echo '<p><a href="' . $url . '" '
        . 'onclick="return confirm(\'Delete all User and Viewer accounts? Admins stay.\');"'
        . 'style="display:inline-block;background:#B22222;color:#fff;padding:10px 18px;text-decoration:none;border-radius:8px;font-weight:bold;">Confirm: delete non-Admin users</a></p>';
    echo '</body></html>';
    exit;
}

if ((int) $nAdminBefore < 1) {
    http_response_code(400);
    echo '<p class="danger">No Administrator account exists. Nothing was deleted.</p></body></html>';
    exit;
}

try {
    $db->beginTransaction();
    // Match database/cleanup_non_admin_users.sql
    $st = $db->exec(
        "DELETE FROM users WHERE COALESCE(TRIM(role), '') <> 'Admin'"
    );
    if ($st === false) {
        throw new RuntimeException('DELETE failed.');
    }
    $deleted = (int) $st;

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(500);
    echo '<p class="danger">' . h($e->getMessage()) . '</p></body></html>';
    exit;
}

$cAfter = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

echo '<p class="ok">Done. Removed <strong>' . $deleted . '</strong> row(s). <strong>'
    . $cAfter . '</strong> user row(s) left (Administrators).</p>';

echo '<h3>Remaining users</h3>';
$rows = $db->query(
    'SELECT id, username, email, full_name, role FROM users ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo '<p class="danger">No users remain — unusual. Restore from backup or recreate admin.</p>';
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

echo '<p><a href="' . h(lgue_url('login')) . '">← Login</a></p>';
echo '</body></html>';
