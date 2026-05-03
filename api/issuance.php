<?php
// Suppress errors to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config.php';

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
        getIssuances($conn);
        break;
    case 'POST':
        createIssuance($conn);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

function getIssuances($conn) {
    $form_type = isset($_GET['form_type']) ? sanitizeInput($_GET['form_type']) : '';
    $office_id = isset($_GET['office_id']) ? intval($_GET['office_id']) : 0;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        // Get single issuance with details
        $sql = "SELECT i.*, p.property_number, p.description, p.category, o.name as office_name 
                FROM issuance_records i
                LEFT JOIN property_items p ON i.property_item_id = p.id
                LEFT JOIN offices o ON i.office_id = o.id
                WHERE i.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $issuance = $result->fetch_assoc();
        
        if ($issuance) {
            jsonResponse(['success' => true, 'data' => $issuance]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Issuance not found']);
        }
    } else {
        // Get all issuances with filters
        $sql = "SELECT i.*, p.property_number, p.description, p.category, o.name as office_name 
                FROM issuance_records i
                LEFT JOIN property_items p ON i.property_item_id = p.id
                LEFT JOIN offices o ON i.office_id = o.id
                WHERE 1=1";
        
        $params = [];
        $types = '';
        
        if ($form_type) {
            $sql .= " AND i.form_type = ?";
            $params[] = $form_type;
            $types .= 's';
        }
        
        if ($office_id > 0) {
            $sql .= " AND i.office_id = ?";
            $params[] = $office_id;
            $types .= 'i';
        }
        
        if ($search) {
            $sql .= " AND (i.issued_to LIKE ? OR i.form_number LIKE ? OR p.description LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        $sql .= " ORDER BY i.issue_date DESC, i.created_at DESC";
        
        if ($types) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        $issuances = [];
        while ($row = $result->fetch_assoc()) {
            $issuances[] = $row;
        }
        
        jsonResponse(['success' => true, 'data' => $issuances]);
    }
}

function createIssuance($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $property_item_id = intval($data['property_item_id'] ?? 0);
    $issued_to = sanitizeInput($data['issued_to'] ?? '');
    $position = sanitizeInput($data['position'] ?? '');
    $issue_date = sanitizeInput($data['issue_date'] ?? date('Y-m-d'));
    $form_number = sanitizeInput($data['form_number'] ?? '');
    $remarks = sanitizeInput($data['remarks'] ?? '');
    
    if ($property_item_id <= 0 || empty($issued_to) || empty($form_number)) {
        jsonResponse(['success' => false, 'message' => 'Missing required fields']);
    }
    
    // Get item details
    $sql = "SELECT category, office_id, status FROM property_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $property_item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    
    if (!$item) {
        jsonResponse(['success' => false, 'message' => 'Property item not found']);
    }
    
    if ($item['status'] === 'Issued') {
        jsonResponse(['success' => false, 'message' => 'Item is already issued']);
    }
    
    // Determine form type based on category
    $form_type = $item['category'] === 'PPE' ? 'PAR' : 'ICS';
    $office_id = $item['office_id'];
    
    // Insert issuance record
    $sql = "INSERT INTO issuance_records (form_type, form_number, property_item_id, office_id, issued_to, position, issue_date, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssiissss', $form_type, $form_number, $property_item_id, $office_id, $issued_to, $position, $issue_date, $remarks);
    
    if ($stmt->execute()) {
        // Update item status to Issued
        $sql = "UPDATE property_items SET status = 'Issued' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $property_item_id);
        $stmt->execute();
        
        $issuance_id = $conn->insert_id;
        jsonResponse(['success' => true, 'message' => 'Issuance created successfully', 'id' => $issuance_id, 'form_type' => $form_type]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create issuance: ' . $conn->error]);
    }
}

$conn->close();
?>
