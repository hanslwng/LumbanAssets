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
require_once dirname(__DIR__) . '/includes/settings_helpers.php';

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

/**
 * @return array<string, mixed>
 */
function itemsReadJsonBody() {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim((string) $raw) === '') {
        jsonResponse(['success' => false, 'message' => 'Empty request body']);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse(['success' => false, 'message' => 'Invalid JSON in request body']);
    }

    return $data;
}

function getItems($conn) {
    unserviceableEnsureSchema($conn);

    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $office_id = isset($_GET['office_id']) ? intval($_GET['office_id']) : 0;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $year = isset($_GET['year']) ? intval($_GET['year']) : 0;

    // Department (non-admin) users are always confined to their own office's
    // property list, regardless of any office_id passed in the request —
    // mirrors the procurement department scoping in api/procurement.php.
    if (!PermissionHelper::isAdmin()) {
        $office_id = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : -1;
    }
    
    $latestUnservJoin = " LEFT JOIN (
            SELECT u.property_item_id, u.repair_status, u.id AS unserviceable_id
            FROM unserviceable_records u
            INNER JOIN (
                SELECT property_item_id, MAX(id) AS max_id
                FROM unserviceable_records
                GROUP BY property_item_id
            ) latest ON latest.max_id = u.id
        ) uns ON uns.property_item_id = p.id";

    if ($id > 0) {
        // Get single item with details
        $sql = "SELECT p.*, o.name as office_name, o.code as office_code,
                       uns.repair_status, uns.unserviceable_id
                FROM property_items p 
                LEFT JOIN offices o ON p.office_id = o.id
                {$latestUnservJoin}
                WHERE p.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item && !PermissionHelper::isAdmin() && (int) $item['office_id'] !== (int) $office_id) {
            $item = null;
        }

        if ($item) {
            jsonResponse(['success' => true, 'data' => $item]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Item not found']);
        }
    } else {
        $lite = isset($_GET['lite']) && ($_GET['lite'] === '1' || $_GET['lite'] === 'true');
        $selectCols = $lite
            ? 'p.id, p.property_number, p.description, p.article, p.item_group, p.category, p.status, p.office_id, o.name as office_name'
            : 'p.*, o.name as office_name, o.code as office_code, uns.repair_status, uns.unserviceable_id';
        $joinUnserv = $lite ? '' : $latestUnservJoin;

        $sql = "SELECT {$selectCols}
                FROM property_items p 
                LEFT JOIN offices o ON p.office_id = o.id
                {$joinUnserv}
                WHERE 1=1";
        
        $params = [];
        
        if ($category) {
            $sql .= " AND p.category = ?";
            $params[] = $category;
        }
        
        if ($status === 'Pending') {
            $sql .= " AND p.status = 'Unserviceable' AND COALESCE(uns.repair_status, 'Pending') = 'Pending'";
        } elseif ($status === 'Defective') {
            $sql .= " AND p.status = 'Unserviceable' AND uns.repair_status = 'Defective'";
        } elseif ($status === 'Serviceable') {
            $sql .= " AND uns.repair_status = 'Serviceable'";
        } elseif ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        if ($office_id > 0) {
            $sql .= " AND p.office_id = ?";
            $params[] = $office_id;
        }

        if ($year > 0) {
            $sql .= " AND YEAR(COALESCE(p.acquisition_date, p.created_at)) = ?";
            $params[] = $year;
        }
        
        if ($search) {
            $sql .= " AND (p.description LIKE ? OR p.property_number LIKE ? OR p.article LIKE ? OR p.item_group LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY p.office_id ASC, (CASE WHEN p.item_group IS NULL OR TRIM(IFNULL(p.item_group,'')) = '' THEN 1 ELSE 0 END), p.item_group ASC, p.sort_order ASC, p.id ASC";
        
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
    $data = itemsReadJsonBody();

    $article = sanitizeInput($data['article'] ?? '');
    $article = ($article === '') ? null : $article;
    $description = sanitizeInput($data['description'] ?? '');
    $unit_of_measure = sanitizeInput($data['unit_of_measure'] ?? 'pc');
    if ($unit_of_measure === '') {
        $unit_of_measure = 'pc';
    }
    $unitValueRaw = $data['unit_value'] ?? 0;
    $unit_value = lgueUnitValueNumeric($unitValueRaw);
    $unit_value_db = lgueUnitValueForStorage($unitValueRaw);
    $quantity = intval($data['quantity'] ?? 1);
    $quantity_physical_raw = $data['quantity_physical_count'] ?? null;
    $quantity_physical_count = null;
    if ($quantity_physical_raw !== null && $quantity_physical_raw !== '') {
        $quantity_physical_count = intval($quantity_physical_raw);
    }
    $office_id = intval($data['office_id'] ?? 0);
    $acquisition_date = sanitizeInput($data['acquisition_date'] ?? '');
    if ($acquisition_date === '') {
        $acquisition_date = null;
    }
    $remarks = sanitizeInput($data['remarks'] ?? '');
    
    $item_group = sanitizeInput($data['item_group'] ?? '');
    $item_group = ($item_group === '') ? null : $item_group;
    
    if ($description === '' && $article) {
        $description = $article;
    }
    $itemErr = lgueItemFieldsError(
        $article,
        $description,
        $unit_of_measure,
        $data['unit_value'] ?? $unitValueRaw,
        $data['quantity'] ?? $quantity,
        $quantity_physical_raw,
        $remarks
    );
    if ($itemErr !== null) {
        jsonResponse(['success' => false, 'message' => $itemErr]);
    }
    if ($unit_value < 0) {
        jsonResponse(['success' => false, 'message' => 'Unit value cannot be negative']);
    }
    if ($office_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Please select an office for this item']);
    }
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    // Determine category based on unit value
    $category = $unit_value >= settingsPpeThreshold($conn) ? 'PPE' : 'SE';

    try {
        $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM property_items WHERE office_id = ? AND category = ? AND (item_group <=> ?)");
        $stmt->execute([$office_id, $category, $item_group]);
        $sort_order = intval($stmt->fetch(PDO::FETCH_ASSOC)['n']);

        // Generate property number
        $year = date('Y');
        $sql = "SELECT COUNT(*) as count FROM property_items WHERE category = ? AND YEAR(created_at) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$category, $year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = str_pad($row['count'] + 1, 6, '0', STR_PAD_LEFT);
        $property_number = "$year-$category-$sequence";

        // Insert item
        $sql = "INSERT INTO property_items (property_number, article, description, unit_of_measure, category, item_group, sort_order, unit_value, quantity, quantity_physical_count, office_id, acquisition_date, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        $stmt->execute([$property_number, $article, $description, $unit_of_measure, $category, $item_group, $sort_order, $unit_value_db, $quantity, $quantity_physical_count, $office_id, $acquisition_date, $remarks]);
        $item_id = $conn->lastInsertId();
        if (!function_exists('auditLogWrite')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        auditLogWrite($conn, (int) $item_id, 'Created', null, trim($property_number . ' — ' . $description));
        jsonResponse(['success' => true, 'message' => 'Item created successfully', 'id' => (int) $item_id, 'property_number' => $property_number]);
    } catch (PDOException $e) {
        jsonResponse([
            'success' => false,
            'message' => 'Could not save item. If you upgraded the app, run the SQL migrations for `property_items` (e.g. quantity_physical_count, item_group, sort_order).',
            'error' => $e->getMessage(),
        ]);
    }
}

function updateItem($conn) {
    $data = itemsReadJsonBody();

    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid item ID']);
    }

    $stmt = $conn->prepare("SELECT * FROM property_items WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        jsonResponse(['success' => false, 'message' => 'Item not found']);
    }

    if (array_key_exists('article', $data)) {
        $a = sanitizeInput($data['article']);
        $article = ($a === '') ? null : $a;
    } else {
        $article = $existing['article'] ?? null;
        if ($article === '') {
            $article = null;
        }
    }
    $description = array_key_exists('description', $data) ? sanitizeInput($data['description']) : $existing['description'];
    $unit_of_measure = array_key_exists('unit_of_measure', $data) ? sanitizeInput($data['unit_of_measure']) : ($existing['unit_of_measure'] ?? 'pc');
    if ($unit_of_measure === '') {
        $unit_of_measure = 'pc';
    }
    $unitValueRaw = array_key_exists('unit_value', $data) ? $data['unit_value'] : ($existing['unit_value'] ?? 0);
    $unit_value = lgueUnitValueNumeric($unitValueRaw);
    $unit_value_db = lgueUnitValueForStorage($unitValueRaw);
    $quantity = array_key_exists('quantity', $data) ? intval($data['quantity']) : intval($existing['quantity']);
    $status = array_key_exists('status', $data) ? sanitizeInput($data['status']) : $existing['status'];
    $condition_status = array_key_exists('condition_status', $data) ? sanitizeInput($data['condition_status']) : $existing['condition_status'];
    $allowedStatus = ['Active', 'Issued', 'Unserviceable', 'Disposed', 'Transferred'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = $existing['status'];
    }
    $allowedCond = ['Good', 'Fair', 'Damaged'];
    if (!in_array($condition_status, $allowedCond, true)) {
        $condition_status = $existing['condition_status'];
    }
    $remarks = array_key_exists('remarks', $data) ? sanitizeInput($data['remarks']) : ($existing['remarks'] ?? '');

    $property_number = (string) ($existing['property_number'] ?? '');
    if (array_key_exists('property_number', $data)) {
        $property_number = lgueNormalizePropertyNumber(sanitizeInput($data['property_number']));
        $pnErr = lguePropertyNumberError($property_number);
        if ($pnErr !== null) {
            jsonResponse(['success' => false, 'message' => $pnErr]);
        }
        if (strcasecmp($property_number, 'NONE') !== 0) {
            $dup = $conn->prepare('SELECT id FROM property_items WHERE property_number = ? AND id <> ? LIMIT 1');
            $dup->execute([$property_number, $id]);
            if ($dup->fetch(PDO::FETCH_ASSOC)) {
                jsonResponse(['success' => false, 'message' => 'That property number is already used by another item.']);
            }
        }
    }

    if (array_key_exists('item_group', $data)) {
        $ig = sanitizeInput($data['item_group']);
        $item_group = ($ig === '') ? null : $ig;
    } else {
        $ig = $existing['item_group'] ?? null;
        $item_group = ($ig === null || $ig === '') ? null : $ig;
    }

    $quantity_physical_count = $existing['quantity_physical_count'] ?? null;
    if (array_key_exists('quantity_physical_count', $data)) {
        $raw = $data['quantity_physical_count'];
        if ($raw === null || $raw === '') {
            $quantity_physical_count = null;
        } else {
            $quantity_physical_count = intval($raw);
        }
    }

    $itemErr = lgueItemFieldsError(
        $article,
        (string) $description,
        (string) $unit_of_measure,
        array_key_exists('unit_value', $data) ? $data['unit_value'] : $unitValueRaw,
        array_key_exists('quantity', $data) ? $data['quantity'] : $quantity,
        array_key_exists('quantity_physical_count', $data) ? ($data['quantity_physical_count'] ?? '') : ($quantity_physical_count ?? ''),
        (string) $remarks
    );
    if ($itemErr !== null) {
        jsonResponse(['success' => false, 'message' => $itemErr]);
    }

    $office_id = array_key_exists('office_id', $data) ? intval($data['office_id']) : intval($existing['office_id']);
    if ($office_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid office']);
    }

    $acquisition_date = array_key_exists('acquisition_date', $data) ? sanitizeInput($data['acquisition_date']) : ($existing['acquisition_date'] ?? '');
    if ($acquisition_date === '') {
        $acquisition_date = null;
    }

    $category = $unit_value >= settingsPpeThreshold($conn) ? 'PPE' : 'SE';
    
    $sql = "UPDATE property_items SET property_number = ?, article = ?, description = ?, unit_of_measure = ?, category = ?, item_group = ?, unit_value = ?, quantity = ?, quantity_physical_count = ?, office_id = ?, acquisition_date = ?, status = ?, condition_status = ?, remarks = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);

    try {
        $stmt->execute([$property_number, $article, $description, $unit_of_measure, $category, $item_group, $unit_value_db, $quantity, $quantity_physical_count, $office_id, $acquisition_date, $status, $condition_status, $remarks, $id]);

        if (!function_exists('auditLogItemDiff')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        auditLogItemDiff($conn, $id, $existing, [
            'property_number' => $property_number,
            'status' => $status,
            'office_id' => $office_id,
            'description' => $description,
            'article' => $article,
            'unit_value' => $unit_value,
            'quantity' => $quantity,
            'quantity_physical_count' => $quantity_physical_count,
            'condition_status' => $condition_status,
            'remarks' => $remarks,
            'acquisition_date' => $acquisition_date,
            'category' => $category,
        ]);

        // Keep the Unserviceable Records module in sync when an item is marked
        // Unserviceable directly from the item edit form (not via the Returns flow).
        if ($status === 'Unserviceable' && $existing['status'] !== 'Unserviceable') {
            auditLogWrite($conn, $id, 'Marked unserviceable', (string) ($existing['status'] ?? ''), 'Via item edit');
            if (function_exists('unserviceableRemoveWorkingReports')) {
                unserviceableRemoveWorkingReports($conn, $id);
            }
            $existsStmt = $conn->prepare(
                "SELECT id FROM unserviceable_records
                 WHERE property_item_id = ?
                   AND COALESCE(repair_status, 'Pending') <> 'Serviceable'
                 LIMIT 1"
            );
            $existsStmt->execute([$id]);
            if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
                $unsSql = "INSERT INTO unserviceable_records (property_item_id, marked_date, reason, marked_by, remarks)
                           VALUES (?, CURDATE(), ?, ?, ?)";
                $unsStmt = $conn->prepare($unsSql);
                $unsStmt->execute([
                    $id,
                    'Marked unserviceable via item edit',
                    $_SESSION['user_name'] ?? 'System',
                    $remarks ?? '',
                ]);
            }
        }

        jsonResponse(['success' => true, 'message' => 'Item updated successfully']);
    } catch (PDOException $e) {
        jsonResponse([
            'success' => false,
            'message' => 'Could not update item. Check database schema and constraints.',
            'error' => $e->getMessage(),
        ]);
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
    
    $itemRow = $conn->prepare('SELECT property_number, description, office_id FROM property_items WHERE id = ?');
    $itemRow->execute([$id]);
    $deleted = $itemRow->fetch(PDO::FETCH_ASSOC) ?: [];

    $sql = "DELETE FROM property_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$id])) {
        if (!function_exists('auditLogWrite')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        $label = trim((string) ($deleted['property_number'] ?? ''));
        if ($label === '') {
            $label = 'Item #' . $id;
        }
        auditLogWrite($conn, $id, 'Deleted', $label, (string) ($deleted['description'] ?? ''));
        jsonResponse(['success' => true, 'message' => 'Item deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to delete item']);
    }
}
?>
