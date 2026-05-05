<?php
// Suppress PHP errors in production (prevent HTML errors in JSON responses)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Database configuration
define('DB_HOST', 'sql12.freesqldatabase.com
');
define('DB_USER', 'sql12825446');
define('DB_PASS', 'MwqXtXXLkk');
define('DB_NAME', 'sql12825446');

// Create database connection (PDO)
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $conn;
    } catch (PDOException $e) {
        // For non-JSON requests, show user-friendly error
        if (!isset($_SERVER['HTTP_ACCEPT']) || strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false) {
            die('Database connection failed. Please ensure the database is set up. Error: ' . $e->getMessage());
        }
        
        header('Content-Type: application/json');
        http_response_code(500);
        die(json_encode([
            'success' => false, 
            'message' => 'Database connection failed. Please ensure the database is set up.',
            'error' => 'Connection error',
            'hint' => 'Run setup-database.php to initialize the database'
        ]));
    }
}

// System settings
define('SYSTEM_NAME', 'Property Inventory Management System');
define('AGENCY_NAME', 'LGU · Property Office');
define('SYSTEM_VERSION', 'v2.0');

// Property classification thresholds
define('PPE_THRESHOLD', 50000); // Items >= 50,000 are PPE
define('SE_THRESHOLD', 50000);  // Items < 50,000 are Semi-Expendable

// Session configuration (only start if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function to return JSON response
function jsonResponse($data) {
    // Clear any output buffers that might contain errors
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Helper function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>
