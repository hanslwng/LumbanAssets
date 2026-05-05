<?php
// Suppress errors to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config.php';
require_once '../app/helpers/PermissionHelper.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Database connection failed',
        'hint' => 'Please run setup-database.php to initialize the database'
    ]);
}

switch ($method) {
    case 'GET':
        PermissionHelper::require('view');
        getUnserviceables($conn);
        break;
    case 'POST':
        PermissionHelper::require('create');
        createUnserviceable($conn);
        break;
    case 'PUT':
        PermissionHelper::require('edit');
        updateDisposalStatus($conn);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

function getUnserviceables($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        // Get single unserviceable with details
        $sql = "SELECT u.*, p.property_number, p.description, p.category, o.name as office_name 
                FROM unserviceable_records u
                LEFT JOIN property_items p ON u.property_item_id = p.id
                LEFT JOIN offices o ON p.office_id = o.id
                WHERE u.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $unserv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($unserv) {
            jsonResponse(['success' => true, 'data' => $unserv]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Record not found']);
        }
    } else {
        // Get all unserviceable items
        $sql = "SELECT u.*, p.property_number, p.description, p.category, p.unit_value, o.name as office_name 
                FROM unserviceable_records u
                LEFT JOIN property_items p ON u.property_item_id = p.id
                LEFT JOIN offices o ON p.office_id = o.id
                ORDER BY u.marked_date DESC, u.created_at DESC";
        $result = $conn->query($sql);
        $items = $result->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['success' => true, 'data' => $items]);
    }
}

function createUnserviceable($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $property_item_id = intval($data['property_item_id'] ?? 0);
    $marked_date = sanitizeInput($data['marked_date'] ?? date('Y-m-d'));
    $reason = sanitizeInput($data['reason'] ?? '');
    $marked_by = sanitizeInput($data['marked_by'] ?? '');
    $remarks = sanitizeInput($data['remarks'] ?? '');
    
    if ($property_item_id <= 0 || empty($reason)) {
        jsonResponse(['success' => false, 'message' => 'Missing required fields']);
    }
    
    // Check if item exists and is not already unserviceable
    $sql = "SELECT status FROM property_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$property_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        jsonResponse(['success' => false, 'message' => 'Property item not found']);
    }
    
    if ($item['status'] === 'Unserviceable') {
        jsonResponse(['success' => false, 'message' => 'Item is already marked as unserviceable']);
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Insert unserviceable record
        $sql = "INSERT INTO unserviceable_records (property_item_id, marked_date, reason, marked_by, remarks) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$property_item_id, $marked_date, $reason, $marked_by, $remarks]);
        $unserv_id = $conn->lastInsertId();
        
        // Update item status
        $sql = "UPDATE property_items SET status = 'Unserviceable' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$property_item_id]);
        
        $conn->commit();
        jsonResponse(['success' => true, 'message' => 'Item marked as unserviceable', 'id' => $unserv_id]);
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(['success' => false, 'message' => 'Failed to mark item: ' . $e->getMessage()]);
    }
}

function updateDisposalStatus($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    $disposal_status = sanitizeInput($data['disposal_status'] ?? 'Pending');
    $disposal_date = isset($data['disposal_date']) ? sanitizeInput($data['disposal_date']) : null;
    $disposal_method = isset($data['disposal_method']) ? sanitizeInput($data['disposal_method']) : null;
    
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid ID']);
    }
    
    $sql = "UPDATE unserviceable_records SET disposal_status = ?, disposal_date = ?, disposal_method = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$disposal_status, $disposal_date, $disposal_method, $id])) {
        // If disposed, update item status
        if ($disposal_status === 'Disposed') {
            $sql = "SELECT property_item_id FROM unserviceable_records WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $sql = "UPDATE property_items SET status = 'Disposed' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$row['property_item_id']]);
            }
        }
        
        jsonResponse(['success' => true, 'message' => 'Disposal status updated']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update disposal status']);
    }
}
?>
