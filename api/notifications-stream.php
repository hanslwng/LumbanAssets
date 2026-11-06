<?php
/**
 * Kept for old bookmarks. Live sync now uses a short JSON poll
 * (api/notifications.php?head=1) so Apache/PHP is not held open.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/notifications_helpers.php';

PermissionHelper::require('view');
if (function_exists('session_write_close')) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $conn = getDBConnection();
    echo json_encode(['success' => true] + notificationsHeadForUser($conn));
} catch (Throwable $e) {
    echo json_encode(['success' => false]);
}
