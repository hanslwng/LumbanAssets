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
require_once dirname(__DIR__) . '/includes/unserviceable_helpers.php';

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

unserviceableEnsureSchema($conn);

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
        updateUnserviceable($conn);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

function getUnserviceables($conn) {
    try {
        getUnserviceablesInner($conn);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to load unserviceable items']);
    }
}

function getUnserviceablesInner($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $officeScope = null;
    if (!PermissionHelper::isAdmin()) {
        $officeScope = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : -1;
    }
    
    if ($id > 0) {
        $sql = "SELECT u.*, p.property_number, p.article, p.description, p.category, p.status AS item_status, p.office_id, o.name as office_name 
                FROM unserviceable_records u
                LEFT JOIN property_items p ON u.property_item_id = p.id
                LEFT JOIN offices o ON p.office_id = o.id
                WHERE u.id = ?";
        $params = [$id];
        if ($officeScope !== null) {
            $sql .= ' AND p.office_id = ?';
            $params[] = $officeScope;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $unserv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($unserv) {
            unserviceableAttachDerived($unserv);
            jsonResponse(['success' => true, 'data' => $unserv]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Record not found']);
        }
    } else {
        $for = isset($_GET['for']) ? strtolower(trim((string) $_GET['for'])) : 'open';
        $sql = "SELECT u.*, p.property_number, p.article, p.description, p.category, p.unit_value, p.status AS item_status, p.office_id, o.name as office_name 
                FROM unserviceable_records u
                LEFT JOIN property_items p ON u.property_item_id = p.id
                LEFT JOIN offices o ON p.office_id = o.id";
        $params = [];
        if ($for === 'disposal') {
            $sql .= " WHERE COALESCE(u.disposal_status, '') = 'Disposed'";
        } else {
            $sql .= " WHERE COALESCE(u.repair_status, 'Pending') <> 'Serviceable'
                      AND COALESCE(u.disposal_status, 'Pending') <> 'Disposed'
                      AND COALESCE(p.status, '') <> 'Disposed'";
        }
        if ($officeScope !== null) {
            $sql .= ' AND p.office_id = ?';
            $params[] = $officeScope;
        }
        $sql .= $for === 'disposal'
            ? ' ORDER BY u.disposal_date DESC, u.marked_date DESC, u.id DESC'
            : ' ORDER BY u.marked_date DESC, u.created_at DESC';
        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $result = $conn->query($sql);
            $items = $result->fetchAll(PDO::FETCH_ASSOC);
        }
        foreach ($items as &$row) {
            unserviceableAttachDerived($row);
        }
        unset($row);
        
        jsonResponse(['success' => true, 'data' => $items]);
    }
}

function createUnserviceable($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    
    $property_item_id = intval($data['property_item_id'] ?? 0);
    $marked_date = sanitizeInput($data['marked_date'] ?? date('Y-m-d'));
    $marked_by = sanitizeInput($data['marked_by'] ?? ($_SESSION['user_name'] ?? ''));
    $remarks = sanitizeInput($data['remarks'] ?? '');
    $reason = sanitizeInput(unserviceableComposeReason($data));
    
    if ($property_item_id <= 0 || $reason === '') {
        jsonResponse(['success' => false, 'message' => 'Missing required fields']);
    }

    $category = trim((string) ($data['reason_category'] ?? ''));
    if ($category !== '' && !in_array($category, UNSERVICEABLE_REASON_CATEGORIES, true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid unserviceable reason']);
    }
    if ($category === 'Other') {
        $otherErr = lgueRequiredTextError(trim((string) ($data['reason'] ?? '')), 'Other reason', 8, 2);
        if ($otherErr !== null) {
            jsonResponse(['success' => false, 'message' => $otherErr]);
        }
    }
    $byErr = lgueOptionalTextError($marked_by, 'Marked by');
    if ($byErr !== null) {
        jsonResponse(['success' => false, 'message' => $byErr]);
    }
    $remErr = lgueOptionalTextError($remarks, 'Remarks');
    if ($remErr !== null) {
        jsonResponse(['success' => false, 'message' => $remErr]);
    }
    
    $sql = "SELECT status, property_number, office_id FROM property_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$property_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        jsonResponse(['success' => false, 'message' => 'Property item not found']);
    }

    if (!PermissionHelper::isAdmin()) {
        $userOffice = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : 0;
        if ($userOffice <= 0 || (int) $item['office_id'] !== $userOffice) {
            jsonResponse(['success' => false, 'message' => 'You can only report items from your own department']);
        }
    }
    
    $itemStatus = (string) ($item['status'] ?? '');
    $latestRepair = null;
    try {
        $lst = $conn->prepare(
            'SELECT repair_status FROM unserviceable_records WHERE property_item_id = ? ORDER BY id DESC LIMIT 1'
        );
        $lst->execute([$property_item_id]);
        $latestVal = $lst->fetchColumn();
        $latestRepair = $latestVal !== false && $latestVal !== null ? (string) $latestVal : null;
    } catch (PDOException $e) {
        $latestRepair = null;
    }

    if ($itemStatus === 'Disposed') {
        jsonResponse(['success' => false, 'message' => 'Disposed items cannot be reported as unserviceable']);
    }
    if ($itemStatus === 'Unserviceable' && $latestRepair !== 'Serviceable') {
        jsonResponse(['success' => false, 'message' => 'Item already has an open unserviceable report']);
    }
    
    $conn->beginTransaction();
    
    try {
        unserviceableRemoveWorkingReports($conn, $property_item_id);
        $sql = "INSERT INTO unserviceable_records (property_item_id, marked_date, reason, marked_by, remarks, disposal_status, repair_status) 
                VALUES (?, ?, ?, ?, ?, 'Pending', 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$property_item_id, $marked_date, $reason, $marked_by, $remarks]);
        $unserv_id = $conn->lastInsertId();
        
        $sql = "UPDATE property_items SET status = 'Unserviceable' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$property_item_id]);
        
        $conn->commit();

        if (!function_exists('auditLogWrite')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        auditLogWrite($conn, $property_item_id, 'Marked unserviceable', (string) $itemStatus, $reason);

        $gsoOfficeId = unserviceableGsoOfficeId($conn);
        $propLabel = $item['property_number'] ?? $property_item_id;
        $officeName = '';
        try {
            $ost = $conn->prepare('SELECT name FROM offices WHERE id = ?');
            $ost->execute([(int) $item['office_id']]);
            $officeName = (string) ($ost->fetchColumn() ?: '');
        } catch (PDOException $e) {
            $officeName = '';
        }
        $reasonPreview = function_exists('mb_substr')
            ? mb_substr($reason, 0, 200)
            : substr($reason, 0, 200);
        $gsoMsg = 'Property #' . $propLabel . ' was reported unserviceable'
            . ($officeName !== '' ? ' by ' . $officeName : '')
            . '. Status: Pending. Reason: ' . $reasonPreview;

        unserviceableNotify(
            $conn,
            'unserviceable_flagged',
            'Unserviceable report - Pending',
            $gsoMsg,
            (int) $unserv_id,
            $gsoOfficeId
        );

        jsonResponse(['success' => true, 'message' => 'Item reported as unserviceable', 'id' => $unserv_id]);
        
    } catch (Exception $e) {
        unserviceableSafeRollback($conn);
        jsonResponse(['success' => false, 'message' => 'Failed to mark item: ' . $e->getMessage()]);
    }
}

function updateUnserviceable($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $data['action'] ?? '';
    if ($action === 'resolve_repair') {
        resolveRepairStatus($conn, $data);
        return;
    }
    if ($action === 'dispose') {
        disposeUnserviceableRecords($conn, $data);
        return;
    }
    updateDisposalStatus($conn, $data);
}

function resolveRepairStatus(PDO $conn, array $data): void {
    if (!PermissionHelper::isAdmin()) {
        http_response_code(403);
        jsonResponse(['success' => false, 'message' => 'Only GSO / administrators can update repair status']);
    }

    $id = intval($data['id'] ?? 0);
    $repair_status = sanitizeInput($data['repair_status'] ?? '');
    if ($id <= 0 || !in_array($repair_status, ['Serviceable', 'Defective'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid repair status']);
    }

    $sql = "SELECT u.*, p.property_number, p.office_id, p.status AS item_status
            FROM unserviceable_records u
            LEFT JOIN property_items p ON u.property_item_id = p.id
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Record not found']);
    }

    $current = $row['repair_status'] ?? 'Pending';
    if (strcasecmp((string) ($row['disposal_status'] ?? ''), 'Disposed') === 0
        || strcasecmp((string) ($row['item_status'] ?? ''), 'Disposed') === 0) {
        jsonResponse(['success' => false, 'message' => 'Disposed items cannot be returned from Disposal Records']);
    }
    if ($current === $repair_status) {
        jsonResponse(['success' => true, 'message' => 'Repair status already set']);
    }

    $conn->beginTransaction();
    try {
        $ust = $conn->prepare('UPDATE unserviceable_records SET repair_status = ? WHERE id = ?');
        $ust->execute([$repair_status, $id]);

        $itemId = (int) $row['property_item_id'];
        $officeId = isset($row['office_id']) ? (int) $row['office_id'] : null;
        $propLabel = $row['property_number'] ?? $itemId;
        $notifyType = '';
        $notifyTitle = '';
        $notifyMessage = '';

        if ($repair_status === 'Serviceable') {
            $pst = $conn->prepare("UPDATE property_items SET status = 'Active', condition_status = 'Good' WHERE id = ?");
            $pst->execute([$itemId]);
            unserviceableRemoveWorkingReports($conn, $itemId, $id);
            $notifyType = 'unserviceable_serviceable';
            $notifyTitle = 'Item ready for pickup';
            $notifyMessage = 'The item is already working and ready for pickup at the GSO Office. Property #' . $propLabel . '.';
            $message = 'Item still works and was removed from the unserviceable list.';
        } else {
            $pst = $conn->prepare("UPDATE property_items SET status = 'Unserviceable', condition_status = 'Damaged' WHERE id = ?");
            $pst->execute([$itemId]);
            $notifyType = 'unserviceable_defective';
            $notifyTitle = 'Item remains unserviceable';
            $notifyMessage = 'Property #' . $propLabel . ' can no longer be repaired. The item will remain under Unserviceable/Defective status.';
            $message = 'Item marked Defective. Department has been notified.';
        }

        $conn->commit();

        if (!function_exists('auditLogWrite')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        auditLogWrite(
            $conn,
            $itemId,
            $repair_status === 'Serviceable' ? 'Returned to inventory' : 'Cannot repair',
            'Pending',
            $repair_status
        );

        if ($notifyType !== '') {
            unserviceableNotify($conn, $notifyType, $notifyTitle, $notifyMessage, $id, $officeId);
        }

        jsonResponse(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        unserviceableSafeRollback($conn);
        jsonResponse(['success' => false, 'message' => 'Failed to update repair status: ' . $e->getMessage()]);
    }
}

function disposeUnserviceableRecords(PDO $conn, array $data): void {
    if (!PermissionHelper::isAdmin()) {
        http_response_code(403);
        jsonResponse(['success' => false, 'message' => 'Only GSO / administrators can dispose items']);
    }

    $ids = [];
    if (!empty($data['ids']) && is_array($data['ids'])) {
        foreach ($data['ids'] as $raw) {
            $n = (int) $raw;
            if ($n > 0) {
                $ids[] = $n;
            }
        }
    }
    $single = (int) ($data['id'] ?? 0);
    if ($single > 0) {
        $ids[] = $single;
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) {
        jsonResponse(['success' => false, 'message' => 'Select at least one unserviceable item']);
    }

    $method = sanitizeInput((string) ($data['disposal_method'] ?? 'GSO recorded'));
    if ($method === '') {
        $method = 'GSO recorded';
    }
    $date = sanitizeInput((string) ($data['disposal_date'] ?? date('Y-m-d')));
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT u.id, u.property_item_id, u.disposal_status, p.status AS item_status, p.property_number
            FROM unserviceable_records u
            LEFT JOIN property_items p ON u.property_item_id = p.id
            WHERE u.id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        jsonResponse(['success' => false, 'message' => 'Record not found']);
    }

    $conn->beginTransaction();
    $moved = 0;
    $auditIds = [];
    try {
        $updRec = $conn->prepare(
            "UPDATE unserviceable_records
             SET disposal_status = 'Disposed', disposal_date = ?, disposal_method = ?
             WHERE id = ? AND COALESCE(disposal_status, 'Pending') <> 'Disposed'"
        );
        $updItem = $conn->prepare("UPDATE property_items SET status = 'Disposed' WHERE id = ?");
        foreach ($rows as $row) {
            if (strcasecmp((string) ($row['disposal_status'] ?? ''), 'Disposed') === 0
                || strcasecmp((string) ($row['item_status'] ?? ''), 'Disposed') === 0) {
                continue;
            }
            $updRec->execute([$date, $method, (int) $row['id']]);
            $itemId = (int) ($row['property_item_id'] ?? 0);
            if ($itemId > 0) {
                $updItem->execute([$itemId]);
                $auditIds[] = $itemId;
            }
            $moved++;
        }
        if ($conn->inTransaction()) {
            $conn->commit();
        }
    } catch (Exception $e) {
        unserviceableSafeRollback($conn);
        jsonResponse(['success' => false, 'message' => 'Could not save disposal. Please try again.']);
    }

    if ($moved <= 0) {
        jsonResponse(['success' => false, 'message' => 'Those items are already in Disposal Records']);
    }

    if (!function_exists('auditLogWrite')) {
        require_once dirname(__DIR__) . '/includes/audit_log.php';
    }
    foreach ($auditIds as $itemId) {
        try {
            auditLogWrite($conn, $itemId, 'Disposed', 'Unserviceable', $method);
        } catch (Throwable $e) {
            // Disposal already saved; do not fail the response.
        }
    }

    $msg = $moved === 1
        ? 'Item moved to Disposal Records'
        : $moved . ' items moved to Disposal Records';
    jsonResponse(['success' => true, 'message' => $msg, 'count' => $moved]);
}

function updateDisposalStatus($conn, $data = null) {
    if ($data === null) {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
    }

    $disposal_status = sanitizeInput($data['disposal_status'] ?? 'Pending');
    if (strcasecmp($disposal_status, 'Disposed') !== 0) {
        jsonResponse(['success' => false, 'message' => 'Disposed items cannot be returned from Disposal Records']);
    }
    $data['action'] = 'dispose';
    disposeUnserviceableRecords($conn, $data);
}
?>
