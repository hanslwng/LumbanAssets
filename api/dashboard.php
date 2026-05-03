<?php
// Suppress errors to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Database connection failed',
        'hint' => 'Please run setup-database.php to initialize the database'
    ]);
}

// Get dashboard statistics
$stats = [];

// Total items
$sql = "SELECT COUNT(*) as total FROM property_items";
$result = $conn->query($sql);
$stats['total_items'] = $result->fetch_assoc()['total'];

// Semi-Expendable count
$sql = "SELECT COUNT(*) as total FROM property_items WHERE category = 'SE'";
$result = $conn->query($sql);
$stats['se_count'] = $result->fetch_assoc()['total'];

// PPE count
$sql = "SELECT COUNT(*) as total FROM property_items WHERE category = 'PPE'";
$result = $conn->query($sql);
$stats['ppe_count'] = $result->fetch_assoc()['total'];

// Active items
$sql = "SELECT COUNT(*) as total FROM property_items WHERE status = 'Active'";
$result = $conn->query($sql);
$stats['active_count'] = $result->fetch_assoc()['total'];

// Issued items
$sql = "SELECT COUNT(*) as total FROM property_items WHERE status = 'Issued'";
$result = $conn->query($sql);
$stats['issued_count'] = $result->fetch_assoc()['total'];

// Unserviceable items
$sql = "SELECT COUNT(*) as total FROM property_items WHERE status = 'Unserviceable'";
$result = $conn->query($sql);
$stats['unserviceable_count'] = $result->fetch_assoc()['total'];

// Disposed items
$sql = "SELECT COUNT(*) as total FROM property_items WHERE status = 'Disposed'";
$result = $conn->query($sql);
$stats['disposed_count'] = $result->fetch_assoc()['total'];

// Total issuances
$sql = "SELECT COUNT(*) as total FROM issuance_records";
$result = $conn->query($sql);
$stats['issuance_count'] = $result->fetch_assoc()['total'];

// Total returns
$sql = "SELECT COUNT(*) as total FROM return_records";
$result = $conn->query($sql);
$stats['return_count'] = $result->fetch_assoc()['total'];

// Total value of all items
$sql = "SELECT SUM(unit_value * quantity) as total_value FROM property_items";
$result = $conn->query($sql);
$stats['total_value'] = $result->fetch_assoc()['total_value'] ?? 0;

// Recent items (last 5)
$sql = "SELECT p.*, o.name as office_name 
        FROM property_items p 
        LEFT JOIN offices o ON p.office_id = o.id 
        ORDER BY p.created_at DESC 
        LIMIT 5";
$result = $conn->query($sql);
$recent_items = [];
while ($row = $result->fetch_assoc()) {
    $recent_items[] = $row;
}

// Recent transactions (last 5)
$sql = "SELECT i.*, p.property_number, p.description, o.name as office_name 
        FROM issuance_records i
        LEFT JOIN property_items p ON i.property_item_id = p.id
        LEFT JOIN offices o ON i.office_id = o.id
        ORDER BY i.created_at DESC 
        LIMIT 5";
$result = $conn->query($sql);
$recent_transactions = [];
while ($row = $result->fetch_assoc()) {
    $recent_transactions[] = $row;
}

// Items needing attention (damaged or unserviceable)
$sql = "SELECT p.*, o.name as office_name 
        FROM property_items p 
        LEFT JOIN offices o ON p.office_id = o.id 
        WHERE p.condition_status = 'Damaged' OR p.status = 'Unserviceable'
        ORDER BY p.updated_at DESC 
        LIMIT 10";
$result = $conn->query($sql);
$attention_items = [];
while ($row = $result->fetch_assoc()) {
    $attention_items[] = $row;
}

$response = [
    'success' => true,
    'stats' => $stats,
    'recent_items' => $recent_items,
    'recent_transactions' => $recent_transactions,
    'attention_items' => $attention_items
];

jsonResponse($response);

$conn->close();
?>
