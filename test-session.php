<?php
/**
 * Session and Permission Test
 * Access this file to check if session and permissions are working
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'app/helpers/PermissionHelper.php';

header('Content-Type: application/json');

$response = [
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
    'session_id' => session_id(),
    'session_data' => [
        'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
        'user_email' => $_SESSION['user_email'] ?? 'NOT SET',
        'user_name' => $_SESSION['user_name'] ?? 'NOT SET',
        'user_role' => $_SESSION['user_role'] ?? 'NOT SET',
        'last_activity' => isset($_SESSION['last_activity']) ? date('Y-m-d H:i:s', $_SESSION['last_activity']) : 'NOT SET'
    ],
    'permission_helper' => [
        'getRole()' => PermissionHelper::getRole(),
        'can(view)' => PermissionHelper::can('view'),
        'can(create)' => PermissionHelper::can('create'),
        'can(edit)' => PermissionHelper::can('edit'),
        'can(delete)' => PermissionHelper::can('delete'),
        'isAdmin()' => PermissionHelper::isAdmin(),
        'isStaff()' => PermissionHelper::isStaff(),
        'isViewer()' => PermissionHelper::isViewer()
    ],
    'logged_in' => isset($_SESSION['user_id']),
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
