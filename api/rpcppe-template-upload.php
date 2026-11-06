<?php
/**
 * Admin: replace storage/templates/rpcppe/rpcppe-template.xlsx
 * POST multipart: file=<xlsx>
 */
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

if (!PermissionHelper::isAdmin()) {
    jsonResponse(['success' => false, 'message' => 'Only administrators can replace the RPCPPE template']);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'Upload a .xlsx file']);
}

$name = (string) ($_FILES['file']['name'] ?? '');
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if ($ext !== 'xlsx') {
    jsonResponse(['success' => false, 'message' => 'File must be .xlsx']);
}

$tmp = $_FILES['file']['tmp_name'];
$bytes = file_get_contents($tmp);
if ($bytes === false || strlen($bytes) < 100) {
    jsonResponse(['success' => false, 'message' => 'Uploaded file is empty or unreadable']);
}

if (substr($bytes, 0, 2) !== 'PK') {
    jsonResponse(['success' => false, 'message' => 'Not a valid Excel .xlsx workbook']);
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    jsonResponse(['success' => false, 'message' => 'PhpSpreadsheet is not installed']);
}
require_once $autoload;
require_once dirname(__DIR__) . '/includes/rpcppe_template_workbook.php';

try {
    $probe = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
    $probe->disconnectWorksheets();
    unset($probe);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Excel file could not be opened. Export as .xlsx and try again.']);
}

$result = rpcppe_replace_template_bytes($bytes, $name);
if (!$result['ok']) {
    jsonResponse(['success' => false, 'message' => 'Could not save template on the server']);
}

if (!function_exists('activityLog')) {
    require_once dirname(__DIR__) . '/includes/audit_log.php';
}
activityLog(getDBConnection(), 'reports', 'RPCPPE template replaced', [
    'entity_type' => 'template',
    'entity_label' => 'RPCPPE workbook',
]);

jsonResponse([
    'success' => true,
    'message' => 'RPCPPE template replaced. Previous file was archived. Print physical count report now uses this workbook.',
]);
