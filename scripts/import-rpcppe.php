<?php
/**
 * CLI: import official RPCPPE .xlsx into property_items (PPE, GSO).
 * Usage: C:\xampp\php\php.exe scripts/import-rpcppe.php [path-to.xlsx]
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from command line.\n");
    exit(1);
}

set_time_limit(180);
require_once dirname(__DIR__) . '/bootstrap.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once dirname(__DIR__) . '/includes/items_import_helpers.php';

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "PhpSpreadsheet is not installed. Run composer install.\n");
    exit(1);
}
require_once $autoload;

$candidates = array_slice($argv, 1);
if (!$candidates) {
    $candidates = [
        dirname(__DIR__) . '/storage/demo/RPCPPE-update-to-2025.xlsx',
        'E:\\RPCPPE-update-to-2025.xlsx',
        'e:\\RPCPPE-update-to-2025.xlsx',
    ];
}

$path = null;
foreach ($candidates as $c) {
    if (is_file($c)) {
        $path = $c;
        break;
    }
}
if ($path === null) {
    fwrite(STDERR, "RPCPPE workbook not found. Pass the .xlsx path as an argument.\n");
    exit(1);
}

echo "Loading $path\n";
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
if (!importItemsWorkbookLooksLikeRpcppe($spreadsheet)) {
    fwrite(STDERR, "This file does not look like the official RPCPPE register.\n");
    exit(1);
}

$conn = getDBConnection();
$t0 = microtime(true);
$result = importItemsFromRpcppeXlsx($conn, $spreadsheet, true);
$elapsed = round(microtime(true) - $t0, 2);
$spreadsheet->disconnectWorksheets();

echo "Created: {$result['created']}\n";
echo "Skipped duplicates: {$result['skipped']}\n";
echo "Elapsed: {$elapsed}s\n";
foreach ($result['notes'] ?? [] as $n) {
    echo "Note: $n\n";
}
foreach ($result['errors'] ?? [] as $e) {
    echo "Error: $e\n";
}

$n = (int) $conn->query("SELECT COUNT(*) FROM property_items WHERE category = 'PPE'")->fetchColumn();
echo "PPE rows in database: $n\n";
