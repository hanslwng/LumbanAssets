<?php
// Suppress errors to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once '../app/helpers/PermissionHelper.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Database connection failed',
        'hint' => 'Please run scripts/setup-database.php to initialize the database'
    ]);
}

switch ($method) {
    case 'GET':
        PermissionHelper::require('view');
        getOffices($conn);
        break;
    case 'POST':
        PermissionHelper::require('manage_offices');
        createOffice($conn);
        break;
    case 'PUT':
        PermissionHelper::require('manage_offices');
        updateOffice($conn);
        break;
    case 'DELETE':
        PermissionHelper::require('manage_offices');
        deleteOffice($conn);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

function getOffices($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        // Get single office with item counts
        $sql = "SELECT o.*, 
                COUNT(DISTINCT p.id) as total_items,
                COUNT(DISTINCT CASE WHEN p.category = 'SE' THEN p.id END) as se_count,
                COUNT(DISTINCT CASE WHEN p.category = 'PPE' THEN p.id END) as ppe_count,
                COUNT(DISTINCT CASE WHEN p.status = 'Active' THEN p.id END) as active_count,
                COUNT(DISTINCT CASE WHEN p.status = 'Issued' THEN p.id END) as issued_count
                FROM offices o
                LEFT JOIN property_items p ON o.id = p.office_id
                WHERE o.id = ?
                GROUP BY o.id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $office = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($office) {
            jsonResponse(['success' => true, 'data' => $office]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Office not found']);
        }
    } else {
        // Get all offices with item counts
        $sql = "SELECT o.*, 
                COUNT(DISTINCT p.id) as total_items,
                COUNT(DISTINCT CASE WHEN p.category = 'SE' THEN p.id END) as se_count,
                COUNT(DISTINCT CASE WHEN p.category = 'PPE' THEN p.id END) as ppe_count,
                COUNT(DISTINCT CASE WHEN p.status = 'Active' THEN p.id END) as active_count,
                COUNT(DISTINCT CASE WHEN p.status = 'Issued' THEN p.id END) as issued_count
                FROM offices o
                LEFT JOIN property_items p ON o.id = p.office_id
                GROUP BY o.id
                ORDER BY o.name ASC";
        $result = $conn->query($sql);
        $offices = $result->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['success' => true, 'data' => $offices]);
    }
}

function createOffice($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitizeInput($data['name'] ?? '');
    $code = sanitizeInput($data['code'] ?? '');
    
    if (empty($name) || empty($code)) {
        jsonResponse(['success' => false, 'message' => 'Name and code are required']);
    }
    $nameErr = lgueRequiredTextError($name, 'Department name', 3);
    if ($nameErr !== null) {
        jsonResponse(['success' => false, 'message' => $nameErr]);
    }
    $codeErr = lgueOfficeCodeError($code);
    if ($codeErr !== null) {
        jsonResponse(['success' => false, 'message' => $codeErr]);
    }
    
    $sql = "INSERT INTO offices (name, code) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$name, $code])) {
        $office_id = $conn->lastInsertId();
        if (!function_exists('activityLog')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        activityLog($conn, 'offices', 'Office created', [
            'office_id' => (int) $office_id,
            'entity_type' => 'office',
            'entity_id' => (int) $office_id,
            'entity_label' => $name,
            'new_value' => $code,
        ]);
        jsonResponse(['success' => true, 'message' => 'Office created successfully', 'id' => $office_id]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create office']);
    }
}

function updateOffice($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    $name = sanitizeInput($data['name'] ?? '');
    $code = sanitizeInput($data['code'] ?? '');
    
    if ($id <= 0 || empty($name) || empty($code)) {
        jsonResponse(['success' => false, 'message' => 'Invalid data']);
    }
    $nameErr = lgueRequiredTextError($name, 'Department name', 3);
    if ($nameErr !== null) {
        jsonResponse(['success' => false, 'message' => $nameErr]);
    }
    $codeErr = lgueOfficeCodeError($code);
    if ($codeErr !== null) {
        jsonResponse(['success' => false, 'message' => $codeErr]);
    }
    
    $sql = "UPDATE offices SET name = ?, code = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$name, $code, $id])) {
        if (!function_exists('activityLog')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        activityLog($conn, 'offices', 'Office updated', [
            'office_id' => $id,
            'entity_type' => 'office',
            'entity_id' => $id,
            'entity_label' => $name,
            'new_value' => $code,
        ]);
        jsonResponse(['success' => true, 'message' => 'Office updated successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update office']);
    }
}

function deleteOffice($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid office ID']);
    }
    
    // Check if office has items
    $sql = "SELECT COUNT(*) as count FROM property_items WHERE office_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row['count'] > 0) {
        jsonResponse(['success' => false, 'message' => 'Cannot delete office with existing items']);
    }
    
    $label = '';
    try {
        $nm = $conn->prepare('SELECT name, code FROM offices WHERE id = ?');
        $nm->execute([$id]);
        $orow = $nm->fetch(PDO::FETCH_ASSOC) ?: [];
        $label = trim((string) ($orow['name'] ?? '') . ' ' . (string) ($orow['code'] ?? ''));
    } catch (PDOException $e) {
        $label = 'Office #' . $id;
    }

    $sql = "DELETE FROM offices WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$id])) {
        if (!function_exists('activityLog')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        activityLog($conn, 'offices', 'Office deleted', [
            'office_id' => $id,
            'entity_type' => 'office',
            'entity_id' => $id,
            'entity_label' => $label !== '' ? $label : ('Office #' . $id),
        ]);
        jsonResponse(['success' => true, 'message' => 'Office deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to delete office']);
    }
}
?>
