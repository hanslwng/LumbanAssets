<?php
/**
 * Bulk import Semi-Expendable / PPE items from CSV or official RPCSE .xlsx
 * (one worksheet per office).
 * POST multipart/form-data: file=<csv|xlsx>, category=SE|PPE, office_id (optional default).
 */
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/items_import_helpers.php';
require_once dirname(__DIR__) . '/includes/settings_helpers.php';

@set_time_limit(180);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

PermissionHelper::require('create');

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed']);
}

if (!settingsAllowItemsImport($conn)) {
    jsonResponse(['success' => false, 'message' => 'Item import is turned off in Settings.']);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded, or upload failed']);
}

$originalName = (string) ($_FILES['file']['name'] ?? '');
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$tmpPath = $_FILES['file']['tmp_name'];
$category = strtoupper(trim((string) ($_POST['category'] ?? 'SE')));
if ($category !== 'PPE') {
    $category = 'SE';
}

$isAdmin = PermissionHelper::isAdmin();
$sessionOfficeId = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : 0;
$defaultOfficeId = isset($_POST['office_id']) ? (int) $_POST['office_id'] : 0;

if ($ext === 'xlsx' || $ext === 'xlsm') {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        jsonResponse(['success' => false, 'message' => 'PhpSpreadsheet is not installed. Run composer install.']);
    }
    require_once $autoload;
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Could not read the Excel workbook. Use .xlsx.']);
    }
    $result = importItemsFromXlsx($conn, $spreadsheet, $category, $isAdmin, $sessionOfficeId, $defaultOfficeId);
} elseif ($ext === 'csv' || $ext === '') {
    $result = importItemsFromCsv($conn, $tmpPath, $category, $isAdmin, $sessionOfficeId, $defaultOfficeId);
} else {
    jsonResponse(['success' => false, 'message' => 'Upload a .xlsx workbook or .csv file']);
}

$created = (int) ($result['created'] ?? 0);
$skipped = (int) ($result['skipped'] ?? 0);
$errors = $result['errors'] ?? [];
$notes = $result['notes'] ?? [];
$unserviceable = importItemsBackfillUnserviceableFromRemarks($conn);

if (!function_exists('activityLog')) {
    require_once dirname(__DIR__) . '/includes/audit_log.php';
}
activityLog($conn, 'inventory', 'Excel import', [
    'entity_type' => 'import',
    'entity_label' => 'Item workbook',
    'new_value' => $created . ' created, ' . $skipped . ' skipped'
        . ($unserviceable ? ', ' . $unserviceable . ' unserviceable' : ''),
]);

jsonResponse([
    'success' => true,
    'message' => "Imported $created item(s)"
        . ($skipped ? ", skipped $skipped duplicate(s)" : '')
        . ($unserviceable ? ", $unserviceable defective item(s) sent to Unserviceable" : '')
        . ($errors ? ' with ' . count($errors) . ' issue(s)' : ''),
    'created' => $created,
    'skipped' => $skipped,
    'unserviceable' => $unserviceable,
    'errors' => $errors,
    'notes' => $notes,
]);
