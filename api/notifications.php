<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/notifications_helpers.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Database connection failed',
    ]);
}

notificationsEnsureSchema($conn);

switch ($method) {
    case 'GET':
        PermissionHelper::require('view');
        if (function_exists('session_write_close')) {
            session_write_close();
        }
        if (isset($_GET['head'])) {
            jsonResponse(['success' => true] + notificationsHeadForUser($conn));
        }
        $out = notificationsListForUser($conn);
        jsonResponse(['success' => true] + $out);
        break;
    case 'POST':
        PermissionHelper::require('view');
        $data = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? 'mark_read';
        notificationsMarkReadForUser($conn, (int) ($data['id'] ?? 0), $action === 'mark_all_read');
        jsonResponse([
            'success' => true,
            'message' => $action === 'mark_all_read' ? 'All notifications marked as read' : 'Notification marked as read',
        ]);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}
