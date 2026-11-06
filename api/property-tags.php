<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/property_tag_helpers.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Database connection failed',
    ]);
}

propertyTagEnsureSchema($conn);

function propertyTagCanAccessItem(PDO $conn, int $itemId): ?array
{
    if ($itemId <= 0) {
        return null;
    }
    $st = $conn->prepare(
        'SELECT p.id, p.property_number, p.unit_value, p.office_id, p.category, p.status
         FROM property_items p WHERE p.id = ?'
    );
    $st->execute([$itemId]);
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return null;
    }
    if (!PermissionHelper::isAdmin()) {
        $oid = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : -1;
        if ((int) $item['office_id'] !== $oid) {
            return null;
        }
    }

    return $item;
}

if ($method === 'GET') {
    PermissionHelper::require('view');
    $itemId = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
    $item = propertyTagCanAccessItem($conn, $itemId);
    if (!$item) {
        jsonResponse(['success' => false, 'message' => 'Item not found']);
    }
    if (strtoupper((string) ($item['category'] ?? '')) !== 'SE') {
        jsonResponse(['success' => false, 'message' => 'Property tags are for semi-expendable items only.']);
    }
    jsonResponse([
        'success' => true,
        'data' => [
            'item' => [
                'id' => (int) $item['id'],
                'property_number' => $item['property_number'],
                'unit_value' => $item['unit_value'],
                'acquisition_cost' => propertyTagFormatCost($item['unit_value']),
            ],
            'tag' => propertyTagGet($conn, $itemId),
        ],
    ]);
}

if ($method === 'POST' || $method === 'PUT') {
    PermissionHelper::require('edit');
    $raw = file_get_contents('php://input');
    $body = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($body)) {
        jsonResponse(['success' => false, 'message' => 'Invalid JSON']);
    }
    $itemId = (int) ($body['item_id'] ?? $body['property_item_id'] ?? 0);
    $item = propertyTagCanAccessItem($conn, $itemId);
    if (!$item) {
        jsonResponse(['success' => false, 'message' => 'Item not found']);
    }
    if (strtoupper((string) ($item['category'] ?? '')) !== 'SE') {
        jsonResponse(['success' => false, 'message' => 'Property tags are for semi-expendable items only.']);
    }
    $fields = propertyTagNormalize($body);
    foreach ([
        'office_division' => 'Office/Division',
        'person_accountable' => 'Person accountable',
        'model_number' => 'Model number',
        'serial_number' => 'Serial number',
        'description' => 'Description',
    ] as $key => $label) {
        $err = function_exists('lgueOptionalTextError') ? lgueOptionalTextError($fields[$key], $label, 2) : null;
        if ($err) {
            jsonResponse(['success' => false, 'message' => $err]);
        }
    }
    $date = $fields['acquisition_date'];
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        jsonResponse(['success' => false, 'message' => 'Acquisition date must be YYYY-MM-DD.']);
    }
    if (!propertyTagSave($conn, $itemId, $fields)) {
        jsonResponse(['success' => false, 'message' => 'Could not save tag fields']);
    }
    if (!function_exists('activityLog')) {
        require_once dirname(__DIR__) . '/includes/audit_log.php';
    }
    activityLog($conn, 'inventory', 'Property tag saved', [
        'entity_type' => 'item',
        'entity_id' => $itemId,
        'entity_label' => (string) ($item['property_number'] ?? ''),
        'office_id' => isset($item['office_id']) ? (int) $item['office_id'] : null,
        'new_value' => trim((string) ($fields['person_accountable'] ?? '')),
    ]);
    jsonResponse([
        'success' => true,
        'message' => 'Tag fields saved',
        'data' => [
            'item' => [
                'id' => (int) $item['id'],
                'property_number' => $item['property_number'],
                'unit_value' => $item['unit_value'],
                'acquisition_cost' => propertyTagFormatCost($item['unit_value']),
            ],
            'tag' => propertyTagGet($conn, $itemId),
        ],
    ]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed']);
