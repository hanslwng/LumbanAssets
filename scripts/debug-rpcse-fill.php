<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/includes/rpcse_template_workbook.php';

$meta = [
    'as_of' => 'December 31, 2025',
    'type_label' => 'Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment',
    'officer_name' => 'TEST OFFICER',
    'officer_designation' => 'GSO',
    'lgu_name' => 'GSO',
    'assumption_date' => 'December 31, 2025',
    'prepared_name' => 'RHONALIZA A. ALVIAR',
    'prepared_designation' => 'GSO - Designate',
    'approver_name' => 'BELEN B. RAGA',
    'approver_designation' => 'Municipal Mayor',
];
$offices = [
    10 => ['id' => 10, 'name' => 'General Services Office', 'code' => 'GSO', 'head_name' => 'RHONALIZA A. ALVIAR', 'head_position' => 'GSO - Designate'],
];
$items = [
    [
        'id' => 1, 'office_id' => 10, 'office_code' => 'GSO', 'office_name' => 'GSO',
        'property_number' => '2025-SE-000001', 'article' => 'Table',
        'description' => 'Test item',
        'unit_of_measure' => 'pc', 'item_group' => 'OFFICE FIXTURES & FURNITURES',
        'unit_value' => 1000, 'quantity' => 1, 'quantity_physical_count' => 1, 'remarks' => '',
    ],
];

try {
    $ss = rpcse_fill_template($items, $meta, $offices, 10);
    echo "OK sheets=" . implode('|', $ss->getSheetNames()) . "\n";
    $tmp = $root . '/storage/templates/rpcse/archive/debug-fill.xlsx';
    $w = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
    $w->save($tmp);
    echo "saved $tmp bytes=" . filesize($tmp) . "\n";
} catch (Throwable $e) {
    echo "ERR " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
