<?php
// Suppress PHP errors in production (prevent HTML errors in JSON responses)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lgu_inventory');

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
            'hint' => 'Run scripts/setup-database.php to initialize the database'
        ]));
    }
}

// System settings
define('SYSTEM_NAME', 'Property Inventory Management System');
define('AGENCY_NAME', 'LGU · Property Office');
define('SYSTEM_VERSION', 'v2.11.75');

/*
 * Public URL path where this app is mounted (no trailing slash).
 * Stylesheets/scripts use this when the folder name is not literally /lgu_invent.
 * Auto-detected from SCRIPT_NAME; override with getenv('LGUE_WEB_BASE') if needed.
 */
if (!defined('LGUE_WEB_BASE')) {
    $lgueDetected = '';
    $envBase = getenv('LGUE_WEB_BASE');
    if (is_string($envBase) && $envBase !== '') {
        $lgueDetected = str_replace('\\', '/', $envBase);
    } elseif (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
        $sn = str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']);
        if (
            str_ends_with($sn, '/theme/index.php')
            || str_ends_with($sn, '/theme/staff_portal.php')
        ) {
            $lgueDetected = dirname(dirname($sn));
        } elseif (basename($sn) === 'index.php') {
            $lgueDetected = dirname($sn);
        }
        $lgueDetected = ($lgueDetected === '/' || $lgueDetected === '.' || $lgueDetected === '\\') ? '' : rtrim((string) $lgueDetected, '/');
        if ($lgueDetected !== '' && !str_starts_with($lgueDetected, '/')) {
            $lgueDetected = '/' . $lgueDetected;
        }
    }
    define('LGUE_WEB_BASE', $lgueDetected !== '' ? $lgueDetected : '/lgu_invent');
}

/**
 * Absolute path URL from app root (e.g. /assets/css/style.css).
 */
function lgue_url(string $path, ?string $cacheBuster = null): string {
    $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
    $base = LGUE_WEB_BASE;

    $url = ($base !== '' ? rtrim((string) $base, '/') : '') . $path;
    if ($cacheBuster !== null && $cacheBuster !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($cacheBuster);
    }

    return $url;
}

// Official procurement letterhead (blanket print views). Override for other LGUs if needed.
if (!defined('PROC_PRINT_UNDERLINE_NAME')) {
    define('PROC_PRINT_UNDERLINE_NAME', 'Municipality of Lumban');
}
if (!defined('PROC_PRINT_PO_HEADER_TITLE')) {
    define('PROC_PRINT_PO_HEADER_TITLE', 'Municipality of Lumban, Laguna');
}
if (!defined('PROC_PRINT_RFQ_REPUBLIC_LINE')) {
    define('PROC_PRINT_RFQ_REPUBLIC_LINE', 'Republic of the Philippines');
}
if (!defined('PROC_PRINT_RFQ_PROVINCE_LINE')) {
    define('PROC_PRINT_RFQ_PROVINCE_LINE', 'Province of Laguna');
}
if (!defined('PROC_PRINT_RFQ_MUN_LINE')) {
    define('PROC_PRINT_RFQ_MUN_LINE', 'MUNICIPALITY OF LUMBAN');
}
if (!defined('PROC_PRINT_RFQ_TEL')) {
    define('PROC_PRINT_RFQ_TEL', 'Telephone No.: (049) 250-5998');
}

// Property classification thresholds
define('PPE_THRESHOLD', 50000); // Items >= 50,000 are PPE
define('SE_THRESHOLD', 50000);  // Items < 50,000 are Semi-Expendable

// Official RPCPPE (physical count) Excel header / signature defaults
if (!defined('RPCPPE_LGU_NAME')) {
    define('RPCPPE_LGU_NAME', 'Municipal Government of Lumban, Laguna');
}
if (!defined('RPCPPE_ACCOUNTABLE_OFFICER')) {
    define('RPCPPE_ACCOUNTABLE_OFFICER', 'RHONALIZA A. ALVIAR');
}
if (!defined('RPCPPE_ACCOUNTABLE_DESIGNATION')) {
    define('RPCPPE_ACCOUNTABLE_DESIGNATION', 'Supply Officer II/GSO-Designate');
}
if (!defined('RPCPPE_APPROVER_NAME')) {
    define('RPCPPE_APPROVER_NAME', 'BELEN B. RAGA');
}
if (!defined('RPCPPE_APPROVER_DESIGNATION')) {
    define('RPCPPE_APPROVER_DESIGNATION', 'Municipal Mayor');
}
if (!defined('RPCPPE_ASSUMPTION_DATE')) {
    define('RPCPPE_ASSUMPTION_DATE', '');
}

// Session configuration (only start if not already started)
if (!function_exists('lgueStartSession')) {
    require_once dirname(__DIR__) . '/includes/session.php';
}
lgueStartSession();

// Helper function to return JSON response
function jsonResponse($data) {
    // Clear any output buffers that might contain errors
    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json');
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode($data, $flags);
    exit;
}

// Helper function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>
