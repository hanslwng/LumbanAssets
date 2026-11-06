<?php
/**
 * View / edit / download the official PAR Excel for any issuance.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';

PermissionHelper::require('view');

require_once dirname(__DIR__) . '/includes/issuance_print_prepare.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

$issuance = loadIssuancePrintForView($conn, $id);
if (!$issuance) {
    http_response_code(404);
    echo 'Issuance not found.';
    exit;
}

if (PermissionHelper::isDepartmentScoped()) {
    $mine = PermissionHelper::officeId();
    if ($mine <= 0 || (int) ($issuance['office_id'] ?? 0) !== $mine) {
        http_response_code(403);
        echo 'You can only view issuance records for your department.';
        exit;
    }
}

$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'html';
$canEdit = PermissionHelper::isAdmin() || PermissionHelper::can('edit');

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    require dirname(__DIR__) . '/app/views/issuances/print_par.php';
    exit;
}
require_once $autoload;
require_once dirname(__DIR__) . '/includes/par_template_workbook.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$canEdit) {
        echo json_encode(['success' => false, 'message' => 'Only an editor can change this PAR.']);
        exit;
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        echo json_encode(['success' => false, 'message' => 'Invalid save data']);
        exit;
    }
    $cells = $payload['cells'] ?? [];
    if (!is_array($cells)) {
        echo json_encode(['success' => false, 'message' => 'Invalid cells']);
        exit;
    }
    try {
        $spreadsheet = par_load_or_fill_workbook($issuance, $id);
        par_apply_cell_edits($spreadsheet, $cells);
        par_save_workbook($id, $spreadsheet);
        par_mark_edited($id);
        if (!function_exists('auditLogWrite')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        $itemId = (int) ($issuance['property_item_id'] ?? 0);
        if ($itemId <= 0) {
            try {
                $ist = $conn->prepare('SELECT property_item_id FROM issuance_records WHERE id = ?');
                $ist->execute([$id]);
                $itemId = (int) $ist->fetchColumn();
            } catch (PDOException $e) {
                $itemId = 0;
            }
        }
        if ($itemId > 0) {
            auditLogWrite($conn, $itemId, 'PAR form edited', null, (string) ($issuance['form_number'] ?? ''));
        }
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        echo json_encode(['success' => true, 'message' => 'PAR saved']);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Could not save the PAR.']);
    }
    exit;
}

try {
    $spreadsheet = par_load_or_fill_workbook($issuance, $id);
} catch (Throwable $e) {
    if ($format === 'xlsx' || $format === 'download') {
        http_response_code(500);
        echo 'Could not open the official PAR Excel. ' . $e->getMessage();
        exit;
    }
    require dirname(__DIR__) . '/app/views/issuances/print_par.php';
    exit;
}

if ($format === 'xlsx' || $format === 'download') {
    require_once dirname(__DIR__) . '/includes/rpcppe_workbook.php';
    $safeNo = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($issuance['form_number'] ?? 'PAR')) ?: 'PAR';
    rpcppe_stream_download($spreadsheet, $safeNo . '.xlsx');
}

$preview = par_excel_preview_model($spreadsheet);
$issuanceId = $id;
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
require dirname(__DIR__) . '/app/views/issuances/par_excel_preview.php';
