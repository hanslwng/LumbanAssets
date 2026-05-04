<?php
/**
 * Application Configuration
 */

// Application Settings
define('APP_NAME', 'LGU Property Inventory System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');

// Session Settings
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('SESSION_NAME', 'lgu_inventory_session');

// Paths
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('VIEW_PATH', APP_PATH . '/views');

// Asset URLs
define('ASSET_URL', '/assets');

// Timezone
date_default_timezone_set('Asia/Manila');

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
    
    // Session timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        header('Location: /login');
        exit;
    }
    $_SESSION['last_activity'] = time();
}
