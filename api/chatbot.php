<?php
/**
 * Chatbot API Endpoint
 * Provides database data access for chatbot assistant
 * Returns comprehensive system data in JSON format
 */

require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow external chatbot access
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDBConnection();

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handlePost($conn);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

function handleGet($conn) {
    $action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'summary';
    
    switch ($action) {
        case 'summary':
            getSummary($conn);
            break;
        case 'items':
            getItems($conn);
            break;
        case 'offices':
            getOffices($conn);
            break;
        case 'issuances':
            getIssuances($conn);
            break;
        case 'returns':
            getReturns($conn);
            break;
        case 'unserviceable':
            getUnserviceable($conn);
            break;
        case 'search':
            searchData($conn);
            break;
        case 'stats':
            getStats($conn);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $query = isset($data['query']) ? sanitizeInput($data['query']) : '';
    
    if (empty($query)) {
        jsonResponse(['success' => false, 'message' => 'Query is required']);
    }
    
    // Natural language query processing
    processNaturalQuery($conn, $query);
}

// Get system summary
function getSummary($conn) {
    $summary = [];
    
    // Total items
    $result = $conn->query("SELECT COUNT(*) as total FROM property_items");
    $summary['total_items'] = $result->fetch_assoc()['total'];
    
    // By category
    $result = $conn->query("SELECT category, COUNT(*) as count FROM property_items GROUP BY category");
    while ($row = $result->fetch_assoc()) {
        $summary['by_category'][$row['category']] = $row['count'];
    }
    
    // By status
    $result = $conn->query("SELECT status, COUNT(*) as count FROM property_items GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        $summary['by_status'][$row['status']] = $row['count'];
    }
    
    // Total value
    $result = $conn->query("SELECT SUM(unit_value * quantity) as total_value FROM property_items");
    $summary['total_value'] = $result->fetch_assoc()['total_value'] ?? 0;
    
    // Offices count
    $result = $conn->query("SELECT COUNT(*) as total FROM offices");
    $summary['total_offices'] = $result->fetch_assoc()['total'];
    
    // Recent activity
    $result = $conn->query("SELECT COUNT(*) as total FROM property_items WHERE DATE(created_at) = CURDATE()");
    $summary['items_today'] = $result->fetch_assoc()['total'];
    
    jsonResponse(['success' => true, 'data' => $summary]);
}

// Get all items with filters
function getItems($conn) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $office_id = isset($_GET['office_id']) ? intval($_GET['office_id']) : 0;
    
    $sql = "SELECT p.*, o.name as office_name, o.code as office_code 
            FROM property_items p 
            LEFT JOIN offices o ON p.office_id = o.id 
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if ($category) {
        $sql .= " AND p.category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    if ($status) {
        $sql .= " AND p.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if ($office_id > 0) {
        $sql .= " AND p.office_id = ?";
        $params[] = $office_id;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    jsonResponse(['success' => true, 'data' => $items, 'count' => count($items)]);
}

// Get all offices with item counts
function getOffices($conn) {
    $sql = "SELECT o.*, 
            COUNT(DISTINCT p.id) as total_items,
            COUNT(DISTINCT CASE WHEN p.category = 'SE' THEN p.id END) as se_count,
            COUNT(DISTINCT CASE WHEN p.category = 'PPE' THEN p.id END) as ppe_count,
            SUM(p.unit_value * p.quantity) as total_value
            FROM offices o
            LEFT JOIN property_items p ON o.id = p.office_id
            GROUP BY o.id
            ORDER BY o.name ASC";
    
    $result = $conn->query($sql);
    $offices = [];
    while ($row = $result->fetch_assoc()) {
        $offices[] = $row;
    }
    
    jsonResponse(['success' => true, 'data' => $offices]);
}

// Get issuances
function getIssuances($conn) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    $sql = "SELECT i.*, p.property_number, p.description, p.category, o.name as office_name 
            FROM issuance_records i
            LEFT JOIN property_items p ON i.property_item_id = p.id
            LEFT JOIN offices o ON i.office_id = o.id
            ORDER BY i.issue_date DESC, i.created_at DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $issuances = [];
    while ($row = $result->fetch_assoc()) {
        $issuances[] = $row;
    }
    
    jsonResponse(['success' => true, 'data' => $issuances]);
}

// Get returns
function getReturns($conn) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    $sql = "SELECT r.*, p.property_number, p.description, i.issued_to, i.form_number 
            FROM return_records r
            LEFT JOIN property_items p ON r.property_item_id = p.id
            LEFT JOIN issuance_records i ON r.issuance_id = i.id
            ORDER BY r.return_date DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $returns = [];
    while ($row = $result->fetch_assoc()) {
        $returns[] = $row;
    }
    
    jsonResponse(['success' => true, 'data' => $returns]);
}

// Get unserviceable items
function getUnserviceable($conn) {
    $sql = "SELECT u.*, p.property_number, p.description, p.category, o.name as office_name 
            FROM unserviceable_records u
            LEFT JOIN property_items p ON u.property_item_id = p.id
            LEFT JOIN offices o ON p.office_id = o.id
            ORDER BY u.marked_date DESC";
    
    $result = $conn->query($sql);
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    jsonResponse(['success' => true, 'data' => $items]);
}

// Search across all data
function searchData($conn) {
    $query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
    
    if (empty($query)) {
        jsonResponse(['success' => false, 'message' => 'Search query is required']);
    }
    
    $searchTerm = "%$query%";
    $results = [];
    
    // Search items
    $sql = "SELECT 'item' as type, p.id, p.property_number, p.description, p.category, o.name as office_name 
            FROM property_items p 
            LEFT JOIN offices o ON p.office_id = o.id 
            WHERE p.description LIKE ? OR p.property_number LIKE ?
            LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results['items'][] = $row;
    }
    
    // Search issuances
    $sql = "SELECT 'issuance' as type, i.id, i.form_number, i.issued_to, p.description 
            FROM issuance_records i
            LEFT JOIN property_items p ON i.property_item_id = p.id
            WHERE i.issued_to LIKE ? OR i.form_number LIKE ?
            LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results['issuances'][] = $row;
    }
    
    jsonResponse(['success' => true, 'data' => $results, 'query' => $query]);
}

// Get statistics
function getStats($conn) {
    $stats = [];
    
    // Overall stats
    $sql = "SELECT 
            COUNT(*) as total_items,
            COUNT(CASE WHEN category = 'SE' THEN 1 END) as se_count,
            COUNT(CASE WHEN category = 'PPE' THEN 1 END) as ppe_count,
            COUNT(CASE WHEN status = 'Active' THEN 1 END) as active_count,
            COUNT(CASE WHEN status = 'Issued' THEN 1 END) as issued_count,
            COUNT(CASE WHEN status = 'Unserviceable' THEN 1 END) as unserviceable_count,
            COUNT(CASE WHEN status = 'Disposed' THEN 1 END) as disposed_count,
            SUM(unit_value * quantity) as total_value,
            AVG(unit_value) as avg_value
            FROM property_items";
    
    $result = $conn->query($sql);
    $stats['items'] = $result->fetch_assoc();
    
    // Issuance stats
    $result = $conn->query("SELECT COUNT(*) as total, form_type, COUNT(DISTINCT issued_to) as unique_recipients FROM issuance_records GROUP BY form_type");
    while ($row = $result->fetch_assoc()) {
        $stats['issuances'][$row['form_type']] = $row;
    }
    
    // Return stats
    $result = $conn->query("SELECT COUNT(*) as total, return_action FROM return_records GROUP BY return_action");
    while ($row = $result->fetch_assoc()) {
        $stats['returns'][$row['return_action']] = $row['total'];
    }
    
    // Top offices by item count
    $sql = "SELECT o.name, COUNT(p.id) as item_count, SUM(p.unit_value * p.quantity) as total_value
            FROM offices o
            LEFT JOIN property_items p ON o.id = p.office_id
            GROUP BY o.id
            ORDER BY item_count DESC
            LIMIT 10";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $stats['top_offices'][] = $row;
    }
    
    jsonResponse(['success' => true, 'data' => $stats]);
}

// Process natural language queries
function processNaturalQuery($conn, $query) {
    $query_lower = strtolower($query);
    $response = [];
    
    // Detect query intent
    if (strpos($query_lower, 'how many') !== false || strpos($query_lower, 'count') !== false) {
        // Count queries
        if (strpos($query_lower, 'item') !== false) {
            $result = $conn->query("SELECT COUNT(*) as count FROM property_items");
            $count = $result->fetch_assoc()['count'];
            $response['answer'] = "There are $count property items in the system.";
            $response['data'] = ['count' => $count];
        } elseif (strpos($query_lower, 'office') !== false) {
            $result = $conn->query("SELECT COUNT(*) as count FROM offices");
            $count = $result->fetch_assoc()['count'];
            $response['answer'] = "There are $count offices in the system.";
            $response['data'] = ['count' => $count];
        }
    } elseif (strpos($query_lower, 'total value') !== false || strpos($query_lower, 'worth') !== false) {
        // Value queries
        $result = $conn->query("SELECT SUM(unit_value * quantity) as total FROM property_items");
        $total = $result->fetch_assoc()['total'] ?? 0;
        $formatted = number_format($total, 2);
        $response['answer'] = "The total value of all property items is ₱$formatted.";
        $response['data'] = ['total_value' => $total];
    } elseif (strpos($query_lower, 'unserviceable') !== false) {
        // Unserviceable queries
        $result = $conn->query("SELECT COUNT(*) as count FROM property_items WHERE status = 'Unserviceable'");
        $count = $result->fetch_assoc()['count'];
        $response['answer'] = "There are $count unserviceable items.";
        $response['data'] = ['count' => $count];
    } elseif (strpos($query_lower, 'ppe') !== false) {
        // PPE queries
        $result = $conn->query("SELECT COUNT(*) as count FROM property_items WHERE category = 'PPE'");
        $count = $result->fetch_assoc()['count'];
        $response['answer'] = "There are $count PPE (Property, Plant & Equipment) items.";
        $response['data'] = ['count' => $count];
    } elseif (strpos($query_lower, 'se') !== false || strpos($query_lower, 'semi') !== false) {
        // SE queries
        $result = $conn->query("SELECT COUNT(*) as count FROM property_items WHERE category = 'SE'");
        $count = $result->fetch_assoc()['count'];
        $response['answer'] = "There are $count Semi-Expendable items.";
        $response['data'] = ['count' => $count];
    } else {
        $response['answer'] = "I can help you with queries about items, offices, values, and statistics. Try asking 'How many items?' or 'What is the total value?'";
    }
    
    $response['success'] = true;
    $response['query'] = $query;
    jsonResponse($response);
}

$conn->close();
?>
