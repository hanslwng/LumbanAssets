<?php
/**
 * Export Semi-Expendable / PPE property items to an Excel-openable CSV file.
 * GET params: category=SE|PPE (optional), office_id (optional), status (optional).
 */
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../app/helpers/PermissionHelper.php';

PermissionHelper::require('export');

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database connection failed';
    exit;
}

$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$office_id = isset($_GET['office_id']) ? intval($_GET['office_id']) : 0;

// Department (non-admin) users are confined to their own office, same as api/items.php.
if (!PermissionHelper::isAdmin()) {
    $office_id = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : -1;
}

$sql = "SELECT p.property_number, p.article, p.description, p.item_group, p.unit_of_measure, p.category,
               p.unit_value, p.quantity, p.quantity_physical_count, p.status, p.acquisition_date, p.remarks,
               o.name AS office_name
        FROM property_items p
        LEFT JOIN offices o ON p.office_id = o.id
        WHERE 1=1";
$params = [];
if ($category === 'SE' || $category === 'PPE') {
    $sql .= ' AND p.category = ?';
    $params[] = $category;
}
if ($status !== '') {
    $sql .= ' AND p.status = ?';
    $params[] = $status;
} else {
    // Default export matches the on-hand inventory list (issued items are out).
    $sql .= " AND p.status <> 'Issued'";
}
if ($office_id > 0) {
    $sql .= ' AND p.office_id = ?';
    $params[] = $office_id;
} elseif ($office_id < 0) {
    // Non-admin with no assigned office — return no rows.
    $sql .= ' AND 1=0';
}
$sql .= ' ORDER BY o.name ASC, p.property_number ASC';

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filenameCategory = ($category === 'SE' || $category === 'PPE') ? $category : 'ALL';
$filename = 'property-items-' . $filenameCategory . '-' . date('Ymd-His') . '.csv';

if (!function_exists('activityLog')) {
    require_once dirname(__DIR__) . '/includes/audit_log.php';
}
activityLog($conn, 'inventory', 'Inventory exported', [
    'entity_type' => 'report',
    'entity_label' => $filenameCategory,
    'office_id' => $office_id > 0 ? $office_id : null,
    'new_value' => count($rows) . ' row' . (count($rows) === 1 ? '' : 's'),
]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel renders special characters (₱, ñ, etc.) correctly.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Property Number', 'Article', 'Description', 'Report Section', 'Unit of Measure',
    'Category', 'Unit Value', 'Qty (Property Card)', 'Qty (Physical)', 'Status',
    'Acquisition Date', 'Office', 'Remarks',
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['property_number'],
        $r['article'],
        $r['description'],
        $r['item_group'],
        $r['unit_of_measure'],
        $r['category'],
        $r['unit_value'],
        $r['quantity'],
        $r['quantity_physical_count'],
        $r['status'],
        $r['acquisition_date'],
        $r['office_name'],
        $r['remarks'],
    ]);
}

fclose($out);
exit;
