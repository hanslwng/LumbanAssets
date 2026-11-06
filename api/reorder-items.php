<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../app/helpers/PermissionHelper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

PermissionHelper::require('edit');

$data = json_decode(file_get_contents('php://input'), true);
$ids = isset($data['ids']) && is_array($data['ids']) ? $data['ids'] : [];

$ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($x) {
    return $x > 0;
})));

if (count($ids) < 1) {
    jsonResponse(['success' => false, 'message' => 'No items to reorder']);
}

function normalizeItemGroup($g) {
    if ($g === null || $g === '') {
        return null;
    }
    $t = trim((string) $g);
    return $t === '' ? null : $t;
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed']);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT id, office_id, category, item_group FROM property_items WHERE id IN ($placeholders)";
$stmt = $conn->prepare($sql);
$stmt->execute($ids);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) !== count($ids)) {
    jsonResponse(['success' => false, 'message' => 'One or more items not found']);
}

$officeId = (int) $rows[0]['office_id'];
$category = $rows[0]['category'];
$groupKey = normalizeItemGroup($rows[0]['item_group']);

foreach ($rows as $r) {
    if ((int) $r['office_id'] !== $officeId || $r['category'] !== $category) {
        jsonResponse(['success' => false, 'message' => 'Items must belong to the same office and category']);
    }
    if (normalizeItemGroup($r['item_group']) !== $groupKey) {
        jsonResponse(['success' => false, 'message' => 'Items must be in the same section']);
    }
}

try {
    $conn->beginTransaction();
    $upd = $conn->prepare('UPDATE property_items SET sort_order = ? WHERE id = ?');
    foreach ($ids as $i => $id) {
        $upd->execute([$i, $id]);
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    jsonResponse(['success' => false, 'message' => 'Failed to save order']);
}

if (!function_exists('activityLog')) {
    require_once dirname(__DIR__) . '/includes/audit_log.php';
}
activityLog($conn, 'inventory', 'Item order saved', [
    'office_id' => $officeId,
    'entity_type' => 'item',
    'entity_label' => $category . ' sort order',
    'new_value' => count($ids) . ' items',
]);

jsonResponse(['success' => true, 'message' => 'Order saved']);
