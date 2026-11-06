<?php
/**
 * Smoke-test RPCPPE generator with 2 seeded categories.
 * Run: php scripts/test-rpcppe-generate.php
 */
$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/includes/rpcppe_workbook.php';

$items = [
    [
        'id' => 1,
        'property_number' => '2025-PPE-000001',
        'article' => 'IT EQUIPMENTS',
        'description' => 'Desktop Computer Set',
        'unit_of_measure' => 'set',
        'item_group' => 'IT EQUIPMENTS',
        'unit_value' => 55000.00,
        'quantity' => 2,
        'quantity_physical_count' => 2,
        'remarks' => '',
        'category' => 'PPE',
    ],
    [
        'id' => 2,
        'property_number' => '2025-PPE-000002',
        'article' => 'IT EQUIPMENTS',
        'description' => 'Laptop',
        'unit_of_measure' => 'unit',
        'item_group' => 'IT EQUIPMENTS',
        'unit_value' => 62000.50,
        'quantity' => 1,
        'quantity_physical_count' => 0,
        'remarks' => 'Missing on count',
        'category' => 'PPE',
    ],
    [
        'id' => 3,
        'property_number' => '2025-PPE-000010',
        'article' => 'OFFICE EQUIPMENTS',
        'description' => 'Photocopier',
        'unit_of_measure' => 'unit',
        'item_group' => 'OFFICE EQUIPMENTS, TOOLS & APPLIANCES',
        'unit_value' => 89000.00,
        'quantity' => 1,
        'quantity_physical_count' => 1,
        'remarks' => '',
        'category' => 'PPE',
    ],
];

$meta = [
    'category' => 'PPE',
    'title' => 'REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT',
    'type_label' => 'Office Equipment and IT Equipment',
    'type_caption' => '(Type of Property, Plant and Equipment)',
    'as_of' => 'As of December 31, 2025',
    'officer_name' => 'RHONALIZA A. ALVIAR',
    'officer_designation' => 'Supply Officer II/GSO-Designate',
    'lgu_name' => 'Municipal Government of Lumban, Laguna',
    'assumption_date' => 'January 1, 2024',
    'approver_name' => 'BELEN B. RAGA',
    'approver_designation' => 'Municipal Mayor',
    'sheet_title' => 'RPCPPE',
];

$ss = rpcppe_build_spreadsheet($items, $meta);
$sheet = $ss->getActiveSheet();

$checks = [];
$checks[] = [$sheet->getCell('A1')->getValue() === $meta['title'], 'Title A1'];
$checks[] = [str_contains((string) $sheet->getCell('E14')->getValue(), '=SUM('), 'Category SUM formula E14'];
$eCat2 = null;
for ($r = 14; $r <= 30; $r++) {
    $v = $sheet->getCell('A' . $r)->getValue();
    if (is_string($v) && stripos($v, 'OFFICE EQUIPMENTS') !== false) {
        $eCat2 = $sheet->getCell('E' . $r)->getValue();
        $checks[] = [is_string($eCat2) && str_starts_with($eCat2, '=SUM('), "Category2 SUM at E{$r}"];
        break;
    }
}
$totalFormula = null;
for ($r = 14; $r <= 40; $r++) {
    if (strcasecmp((string) $sheet->getCell('C' . $r)->getValue(), 'TOTAL') === 0) {
        $totalFormula = (string) $sheet->getCell('E' . $r)->getValue();
        $checks[] = [str_starts_with($totalFormula, '=E') && str_contains($totalFormula, '+'), "Grand total formula: {$totalFormula}"];
        $checks[] = [$sheet->getCell('A' . ($r + 2))->getValue() === 'Prepared by:', 'Prepared by label'];
        $checks[] = [$sheet->getCell('A' . ($r + 5))->getValue() === $meta['officer_name'], 'Officer name in signature'];
        break;
    }
}

// Variance formula on laptop row (qty differs)
$foundVar = false;
for ($r = 14; $r <= 30; $r++) {
    if ((string) $sheet->getCell('C' . $r)->getValue() === '2025-PPE-000002') {
        $h = (string) $sheet->getCell('H' . $r)->getValue();
        $checks[] = [str_starts_with($h, '=G'), "Shortage formula H{$r}={$h}"];
        $foundVar = true;
        break;
    }
}
$checks[] = [$foundVar, 'Found variance item row'];

$out = $root . '/scripts/_rpcppe_test_out.xlsx';
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($ss, 'Xlsx');
$writer->save($out);

// Round-trip parse
$loaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($out);
$parsed = rpcppe_parse_workbook($loaded);
$checks[] = [count($parsed['updates']) === 3, 'Parser found 3 item rows (got ' . count($parsed['updates']) . ')'];
$checks[] = [empty($parsed['errors']), 'Parser has no errors'];

$fail = 0;
foreach ($checks as [$ok, $label]) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
}

echo PHP_EOL . 'Wrote ' . $out . PHP_EOL;
exit($fail > 0 ? 1 : 0);
