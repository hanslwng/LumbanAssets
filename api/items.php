<?php
// Suppress errors to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        getItems($conn);
        break;
    case 'POST':
        PermissionHelper::require('create');
        createItem($conn);
        break;
    case 'PUT':
        PermissionHelper::require('edit');
        updateItem($conn);
        break;
    case 'DELETE':
        PermissionHelper::require('delete');
        deleteItem($conn);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

function getItems($conn) {
    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $office_id = isset($_GET['office_id']) ? intval($_GET['office_id']) : 0;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        // Get single item with details
        $sql = "SELECT p.*, o.name as office_name, o.code as office_code 
                FROM property_items p 
                LEFT JOIN offices o ON p.office_id = o.id 
                WHERE p.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            jsonResponse(['success' => true, 'data' => $item]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Item not found']);
        }
    } else {
        // Get all items with filters
        $sql = "SELECT p.*, o.name as office_name, o.code as office_code 
                FROM property_items p 
                LEFT JOIN offices o ON p.office_id = o.id 
                WHERE 1=1";
        
        $params = [];
        
        if ($category) {
            $sql .= " AND p.category = ?";
            $params[] = $category;
        }
        
        if ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        if ($office_id > 0) {
            $sql .= " AND p.office_id = ?";
            $params[] = $office_id;
        }
        
        if ($search) {
            $sql .= " AND (p.description LIKE ? OR p.property_number LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        if (count($params) > 0) {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $result = $conn->query($sql);
            $items = $result->fetchAll(PDO::FETCH_ASSOC);
        }
        
        jsonResponse(['success' => true, 'data' => $items]);
    }
}

function createItem($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $description = sanitizeInput($data['description'] ?? '');
    $unit_value = floatval($data['unit_value'] ?? 0);
    $quantity = intval($data['quantity'] ?? 1);
    $office_id = intval($data['office_id'] ?? 0);
    $acquisition_date = sanitizeInput($data['acquisition_date'] ?? date('Y-m-d'));
    $remarks = sanitizeInput($data['remarks'] ?? '');
    
    // Validate required fields
    if (empty($description) || $unit_value <= 0 || $office_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Missing required fields']);
    }
    
    // Determine category based on unit value
    $category = $unit_value >= PPE_THRESHOLD ? 'PPE' : 'SE';
    
    // Generate property number
    $year = date('Y');
    $sql = "SELECT COUNT(*) as count FROM property_items WHERE category = ? AND YEAR(created_at) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$category, $year]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = str_pad($row['count'] + 1, 6, '0', STR_PAD_LEFT);
    $property_number = "$year-$category-$sequence";
    
    // Insert item
    $sql = "INSERT INTO property_items (property_number, description, category, unit_value, quantity, office_id, acquisition_date, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$property_number, $description, $category, $unit_value, $quantity, $office_id, $acquisition_date, $remarks])) {
        $item_id = $conn->lastInsertId();
        jsonResponse(['success' => true, 'message' => 'Item created successfully', 'id' => $item_id, 'property_number' => $property_number]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create item']);
    }
}

function updateItem($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    $description = sanitizeInput($data['description'] ?? '');
    $unit_value = floatval($data['unit_value'] ?? 0);
    $quantity = intval($data['quantity'] ?? 1);
    $status = sanitizeInput($data['status'] ?? 'Active');
    $condition_status = sanitizeInput($data['condition_status'] ?? 'Good');
    $remarks = sanitizeInput($data['remarks'] ?? '');
    
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid item ID']);
    }
    
    $sql = "UPDATE property_items SET description = ?, unit_value = ?, quantity = ?, status = ?, condition_status = ?, remarks = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$description, $unit_value, $quantity, $status, $condition_status, $remarks, $id])) {
        jsonResponse(['success' => true, 'message' => 'Item updated successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update item']);
    }
}

function deleteItem($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid item ID']);
    }
    
    // Check if item has related records
    $sql = "SELECT COUNT(*) as count FROM issuance_records WHERE property_item_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row['count'] > 0) {
        jsonResponse(['success' => false, 'message' => 'Cannot delete item with existing issuance records']);
    }
    
    $sql = "DELETE FROM property_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$id])) {
        jsonResponse(['success' => true, 'message' => 'Item deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to delete item']);
    }
}
?>
