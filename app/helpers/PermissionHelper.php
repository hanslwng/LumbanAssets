<?php
/**
 * Permission Helper
 * Handles role-based access control (RBAC)
 */

class PermissionHelper {
    
    // Role definitions
    const ROLE_ADMIN = 'admin';
    const ROLE_STAFF = 'staff';
    const ROLE_VIEWER = 'viewer';
    
    // Permission definitions
    const PERM_VIEW = 'view';
    const PERM_CREATE = 'create';
    const PERM_EDIT = 'edit';
    const PERM_DELETE = 'delete';
    const PERM_EXPORT = 'export';
    const PERM_PRINT = 'print';
    
    /**
     * Role permissions matrix
     * Defines what each role can do
     */
    private static $rolePermissions = [
        'admin' => [
            'view' => true,
            'create' => true,
            'edit' => true,
            'delete' => true,
            'export' => true,
            'print' => true,
            'manage_users' => true,
            'manage_offices' => true,
            'approve' => true
        ],
        'staff' => [
            'view' => true,
            'create' => true,
            'edit' => true,
            'delete' => false,
            'export' => true,
            'print' => true,
            'manage_users' => false,
            'manage_offices' => false,
            'approve' => false
        ],
        'viewer' => [
            'view' => true,
            'create' => false,
            'edit' => false,
            'delete' => false,
            'export' => true,
            'print' => true,
            'manage_users' => false,
            'manage_offices' => false,
            'approve' => false
        ]
    ];
    
    /**
     * Check if current user has permission
     */
    public static function can($permission) {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        
        // Get normalized role
        $role = self::getRole();
        
        if (!isset(self::$rolePermissions[$role])) {
            return false;
        }
        
        return self::$rolePermissions[$role][$permission] ?? false;
    }
    
    /**
     * Check if current user is admin
     */
    public static function isAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === self::ROLE_ADMIN;
    }
    
    /**
     * Check if current user is staff
     */
    public static function isStaff() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === self::ROLE_STAFF;
    }
    
    /**
     * Check if current user is viewer
     */
    public static function isViewer() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === self::ROLE_VIEWER;
    }
    
    /**
     * Get current user role
     */
    public static function getRole() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_role'])) {
            return 'guest';
        }
        
        // Normalize role to lowercase and map 'User' to 'staff'
        $role = strtolower($_SESSION['user_role']);
        if ($role === 'user') {
            $role = 'staff';
            // Update session with normalized role
            $_SESSION['user_role'] = $role;
        }
        
        return $role;
    }
    
    /**
     * Require permission (throw error if not allowed)
     */
    public static function require($permission) {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            if (self::isApiRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Authentication required',
                    'error' => 'You must be logged in to access this resource'
                ]);
            } else {
                header('Location: /lgu_invent/login');
            }
            exit;
        }
        
        if (!self::can($permission)) {
            http_response_code(403);
            if (self::isApiRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Permission denied',
                    'error' => 'You do not have permission to perform this action',
                    'required_permission' => $permission,
                    'your_role' => self::getRole()
                ]);
            } else {
                echo '<!DOCTYPE html>
<html>
<head>
    <title>403 - Permission Denied</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #B22222, #8B0000);
            color: white;
        }
        .container {
            text-align: center;
            max-width: 500px;
            padding: 40px;
            background: rgba(0,0,0,0.3);
            border-radius: 14px;
            backdrop-filter: blur(10px);
        }
        h1 {
            font-size: 72px;
            margin: 0;
            font-weight: 800;
        }
        p {
            font-size: 18px;
            margin: 20px 0;
            line-height: 1.6;
        }
        a {
            color: #FFD700;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: opacity 0.2s;
        }
        a:hover {
            opacity: 0.8;
        }
        .role-badge {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
            border: 1px solid rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <p>Permission Denied</p>
        <p>You do not have permission to perform this action.</p>
        <div class="role-badge">Your role: ' . htmlspecialchars(self::getRole()) . '</div>
        <p style="margin-top: 30px;"><a href="/lgu_invent/">← Back to Dashboard</a></p>
    </div>
</body>
</html>';
            }
            exit;
        }
    }
    
    /**
     * Check if current request is an API request
     */
    private static function isApiRequest() {
        return strpos($_SERVER['REQUEST_URI'], '/api/') !== false ||
               (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }
    
    /**
     * Get role display name
     */
    public static function getRoleDisplayName($role = null) {
        $role = $role ?? self::getRole();
        
        $names = [
            'admin' => 'Administrator',
            'staff' => 'Staff Member',
            'viewer' => 'Viewer (Read-Only)',
            'guest' => 'Guest'
        ];
        
        return $names[$role] ?? ucfirst($role);
    }
    
    /**
     * Get all permissions for a role
     */
    public static function getRolePermissions($role) {
        return self::$rolePermissions[$role] ?? [];
    }
    
    /**
     * Check multiple permissions (AND logic)
     */
    public static function canAll($permissions) {
        foreach ($permissions as $permission) {
            if (!self::can($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Check multiple permissions (OR logic)
     */
    public static function canAny($permissions) {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }
        return false;
    }
}
