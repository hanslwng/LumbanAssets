<?php
// Suppress errors to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/audit_log.php';
require_once dirname(__DIR__) . '/includes/unserviceable_helpers.php';
require_once '../app/helpers/PermissionHelper.php';

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
        getReturns($conn);
        break;
    case 'POST':
        jsonResponse([
            'success' => false,
            'message' => 'Returns are no longer used. Report the item under Unserviceable, then mark Still works if it is working.',
        ]);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

function getReturns($conn) {
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        // Get single return with details
        $sql = "SELECT r.*, p.property_number, p.description, i.issued_to, i.form_number, 
                o1.name as from_office, o2.name as to_office
                FROM return_records r
                LEFT JOIN property_items p ON r.property_item_id = p.id
                LEFT JOIN issuance_records i ON r.issuance_id = i.id
                LEFT JOIN offices o1 ON p.office_id = o1.id
                LEFT JOIN offices o2 ON r.transfer_to_office_id = o2.id
                WHERE r.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $return = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($return) {
            jsonResponse(['success' => true, 'data' => $return]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Return not found']);
        }
    } else {
        // Get all returns
        $sql = "SELECT r.*, p.property_number, p.description, i.issued_to, i.form_number, 
                o1.name as from_office, o2.name as to_office
                FROM return_records r
                LEFT JOIN property_items p ON r.property_item_id = p.id
                LEFT JOIN issuance_records i ON r.issuance_id = i.id
                LEFT JOIN offices o1 ON p.office_id = o1.id
                LEFT JOIN offices o2 ON r.transfer_to_office_id = o2.id
                WHERE 1=1";
        
        if ($search) {
            $sql .= " AND (r.returned_by LIKE ? OR p.description LIKE ? OR i.issued_to LIKE ?) ORDER BY r.return_date DESC, r.created_at DESC";
            $searchTerm = "%$search%";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql .= " ORDER BY r.return_date DESC, r.created_at DESC";
            $result = $conn->query($sql);
            $returns = $result->fetchAll(PDO::FETCH_ASSOC);
        }
        
        jsonResponse(['success' => true, 'data' => $returns]);
    }
}

function createReturn($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $issuance_id = intval($data['issuance_id'] ?? 0);
    $property_item_id = intval($data['property_item_id'] ?? 0);
    $returned_by = sanitizeInput($data['returned_by'] ?? '');
    $return_date = sanitizeInput($data['return_date'] ?? date('Y-m-d'));
    $return_action = sanitizeInput($data['return_action'] ?? 'Returned');
    $transfer_to_office_id = isset($data['transfer_to_office_id']) ? intval($data['transfer_to_office_id']) : null;
    $condition_on_return = sanitizeInput($data['condition_on_return'] ?? 'Good');
    $remarks = sanitizeInput($data['remarks'] ?? '');
    
    if ($issuance_id <= 0 || $property_item_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Missing required fields']);
    }
    $nameErr = lgueNameError($returned_by, 'Returned by');
    if ($nameErr !== null) {
        jsonResponse(['success' => false, 'message' => $nameErr]);
    }
    $remErr = lgueOptionalTextError($remarks, 'Remarks');
    if ($remErr !== null) {
        jsonResponse(['success' => false, 'message' => $remErr]);
    }
    
    // Validate return action
    if (!in_array($return_action, ['Unserviceable', 'Transfer', 'Returned'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid return action']);
    }
    
    if ($return_action === 'Transfer' && !$transfer_to_office_id) {
        jsonResponse(['success' => false, 'message' => 'Transfer office is required']);
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Insert return record
        $sql = "INSERT INTO return_records (issuance_id, property_item_id, return_date, returned_by, return_action, transfer_to_office_id, condition_on_return, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$issuance_id, $property_item_id, $return_date, $returned_by, $return_action, $transfer_to_office_id, $condition_on_return, $remarks]);
        $return_id = $conn->lastInsertId();
        
        // Update item status based on return action
        if ($return_action === 'Unserviceable') {
            $new_status = 'Unserviceable';

            // Ensure the item shows up in the Unserviceable Records module too
            if (function_exists('unserviceableRemoveWorkingReports')) {
                unserviceableRemoveWorkingReports($conn, $property_item_id);
            }
            $existsStmt = $conn->prepare(
                "SELECT id FROM unserviceable_records
                 WHERE property_item_id = ?
                   AND COALESCE(repair_status, 'Pending') <> 'Serviceable'
                 LIMIT 1"
            );
            $existsStmt->execute([$property_item_id]);
            if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
                $unsSql = "INSERT INTO unserviceable_records (property_item_id, marked_date, reason, marked_by, remarks)
                           VALUES (?, ?, ?, ?, ?)";
                $unsStmt = $conn->prepare($unsSql);
                $unsStmt->execute([
                    $property_item_id,
                    $return_date,
                    'Returned in unserviceable condition' . ($remarks ? ": $remarks" : ''),
                    $returned_by,
                    $remarks,
                ]);
            }
        } elseif ($return_action === 'Transfer') {
            $new_status = 'Transferred';
            // Update office_id if transferring
            $sql = "UPDATE property_items SET office_id = ?, status = ?, condition_status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$transfer_to_office_id, $new_status, $condition_on_return, $property_item_id]);
        } else {
            $new_status = 'Active';
        }
        
        // Update item status and condition
        if ($return_action !== 'Transfer') {
            $sql = "UPDATE property_items SET status = ?, condition_status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$new_status, $condition_on_return, $property_item_id]);
        }
        
        $conn->commit();
        if (!function_exists('auditLogWrite')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        $note = $return_action;
        if ($return_action === 'Transfer' && $transfer_to_office_id) {
            $note = 'Transfer to ' . auditLogOfficeName($conn, $transfer_to_office_id);
        }
        auditLogWrite($conn, $property_item_id, 'Returned', 'Issued', $note . ' by ' . $returned_by);
        jsonResponse(['success' => true, 'message' => 'Return recorded successfully', 'id' => $return_id]);
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(['success' => false, 'message' => 'Failed to record return: ' . $e->getMessage()]);
    }
}
?>
