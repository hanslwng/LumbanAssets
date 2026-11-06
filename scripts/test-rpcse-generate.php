<?php
/**
 * Smoke-test RPCSE template filler: 2 offices, 2 categories, formulas, no grand total,
 * signature after last item, large-office row insertion (350 items).
 *
 * Run: C:\xampp\php\php.exe scripts/test-rpcse-generate.php
 */
$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/includes/rpcse_template_workbook.php';

$meta = [
    'as_of' => 'December 31, 2025',
    'type_label' => 'Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment',
    'officer_name' => 'ROBEL M. DEL VALLE',
    'officer_designation' => 'PESO Manager',
    'lgu_name' => 'Public Employment Services Office',
    'assumption_date' => 'December 31, 2025',
    'prepared_name' => 'RHONALIZA A. ALVIAR',
    'prepared_designation' => 'GSO - Designate / PRO III',
    'approver_name' => 'BELEN B. RAGA',
    'approver_designation' => 'Municipal Mayor',
];

$offices = [
    10 => ['id' => 10, 'name' => 'General Services Office', 'code' => 'GSO', 'head_name' => 'RHONALIZA A. ALVIAR', 'head_position' => 'GSO - Designate'],
    9 => ['id' => 9, 'name' => 'Public Employment Services Office', 'code' => 'PESO', 'head_name' => 'ROBEL M. DEL VALLE', 'head_position' => 'PESO Manager'],
    7 => ['id' => 7, 'name' => 'Rural Health Unit', 'code' => 'RHU', 'head_name' => 'MHO', 'head_position' => 'Municipal Health Officer'],
];

$items = [
    [
        'id' => 1, 'office_id' => 9, 'office_code' => 'PESO', 'office_name' => 'PESO',
        'property_number' => '2025-SE-000001', 'article' => '1. Table',
        'description' => 'Laminated Table With 3 Drawers Dark Brown',
        'unit_of_measure' => 'pc', 'item_group' => 'OFFICE FIXTURES & FURNITURES',
        'unit_value' => 4200.00, 'quantity' => 1, 'quantity_physical_count' => 1, 'remarks' => '',
    ],
    [
        'id' => 2, 'office_id' => 9, 'office_code' => 'PESO', 'office_name' => 'PESO',
        'property_number' => '2025-SE-000002', 'article' => '1. Table',
        'description' => 'L_Type Table brown',
        'unit_of_measure' => 'pc', 'item_group' => 'OFFICE FIXTURES & FURNITURES',
        'unit_value' => 4700.00, 'quantity' => 1, 'quantity_physical_count' => 1, 'remarks' => 'Defective',
    ],
    [
        'id' => 3, 'office_id' => 9, 'office_code' => 'PESO', 'office_name' => 'PESO',
        'property_number' => '2025-SE-000010', 'article' => '8. Computer',
        'description' => 'Acer Monitor with built-in Computer System unit',
        'unit_of_measure' => 'unit', 'item_group' => 'IT EQUIPMENTS',
        'unit_value' => 45000.00, 'quantity' => 1, 'quantity_physical_count' => 0, 'remarks' => 'Defective',
    ],
    [
        'id' => 4, 'office_id' => 10, 'office_code' => 'GSO', 'office_name' => 'GSO',
        'property_number' => '2025-SE-000100', 'article' => 'Chair',
        'description' => 'Office chair',
        'unit_of_measure' => 'pc', 'item_group' => 'OFFICE FIXTURES & FURNITURES',
        'unit_value' => 2500.00, 'quantity' => 2, 'quantity_physical_count' => 2, 'remarks' => '',
    ],
];

for ($i = 0; $i < 342; $i++) {
    $items[] = [
        'id' => 1000 + $i,
        'office_id' => 7,
        'office_code' => 'RHU',
        'office_name' => 'RHU',
        'property_number' => sprintf('2025-SE-%06d', 2000 + $i),
        'article' => ($i % 20 === 0) ? 'Supply' : '',
        'description' => 'RHU item ' . ($i + 1) . ' with a longer description that should wrap without cutting off the form layout when printed landscape legal.',
        'unit_of_measure' => 'pc',
        'item_group' => $i < 170 ? 'MEDICAL EQUIPMENTS' : 'OFFICE EQUIPMENTS, TOOLS & APPLIANCES',
        'unit_value' => 100 + $i,
        'quantity' => 1,
        'quantity_physical_count' => ($i === 5) ? 0 : 1,
        'remarks' => $i === 5 ? 'Shortage' : '',
    ];
}

$ss = rpcse_fill_template($items, $meta, $offices, 0);
$names = $ss->getSheetNames();
$fail = [];

if (!in_array('PESO', $names, true) || !in_array('GSO', $names, true) || !in_array('RHU', $names, true)) {
    $fail[] = 'Expected PESO, GSO, RHU tabs; got ' . implode(',', $names);
}
if (in_array('Sheet', $names, true)) {
    $fail[] = 'Blank master Sheet should not remain in the output';
}

$peso = $ss->getSheetByName('PESO');
if ($peso) {
    $a1 = (string) $peso->getCell('A1')->getValue();
    if (stripos($a1, 'REPUBLIC') === false) {
        $fail[] = 'PESO A1 republic header';
    }
    $a5 = (string) $peso->getCell('A5')->getValue();
    if (stripos($a5, 'SEMI-EXPENDABLE') === false) {
        $fail[] = 'PESO A5 title';
    }
    $e18 = (string) $peso->getCell('E18')->getValue();
    if (strpos($e18, '=SUM(') !== 0) {
        $fail[] = 'PESO category SUM at E18, got ' . $e18;
    }
    $foundTotal = false;
    $foundPrepared = false;
    $highest = (int) $peso->getHighestRow();
    for ($r = 18; $r <= $highest; $r++) {
        $a = strtoupper(trim((string) $peso->getCell('A' . $r)->getValue()));
        $c = strtoupper(trim((string) $peso->getCell('C' . $r)->getValue()));
        if ($a === 'TOTAL' || $c === 'TOTAL') {
            $foundTotal = true;
        }
        if (stripos($a, 'PREPARED BY') !== false) {
            $foundPrepared = $r;
        }
    }
    if ($foundTotal) {
        $fail[] = 'Grand-total row must not be added';
    }
    if (!$foundPrepared) {
        $fail[] = 'Prepared by signature missing on PESO';
    }
    $hVar = (string) $peso->getCell('H21')->getValue();
    if (strpos($hVar, '=G') !== 0) {
        // IT item is 3rd item after 1 cat + 2 furniture items → row 21 if cat at 18, items 19-20 furniture, cat IT 21...
        // Don't hard-fail on exact row; scan for formula
        $hasVar = false;
        for ($r = 18; $r <= $highest; $r++) {
            $hv = (string) $peso->getCell('H' . $r)->getValue();
            if (strpos($hv, '=G') === 0) {
                $hasVar = true;
                break;
            }
        }
        if (!$hasVar) {
            $fail[] = 'Shortage formula =G-F missing on variance row';
        }
    }
}

$rhu = $ss->getSheetByName('RHU');
if ($rhu) {
    $highest = (int) $rhu->getHighestRow();
    $itemish = 0;
    $preparedRow = 0;
    $lastPnRow = 0;
    for ($r = 18; $r <= $highest; $r++) {
        $a = (string) $rhu->getCell('A' . $r)->getValue();
        if (stripos($a, 'Prepared by') !== false) {
            $preparedRow = $r;
            break;
        }
        $pn = trim((string) $rhu->getCell('C' . $r)->getValue());
        if ($pn !== '') {
            $itemish++;
            $lastPnRow = $r;
        }
    }
    if ($itemish < 342) {
        $fail[] = "RHU should have 342 item property numbers, got {$itemish}";
    }
    if ($preparedRow && $lastPnRow && $preparedRow < $lastPnRow + 2) {
        $fail[] = "RHU signature at row {$preparedRow} overlaps data (last item {$lastPnRow})";
    }
}

$out = $root . '/storage/templates/rpcse/archive/test-rpcse-output.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
$writer->save($out);
$ss->disconnectWorksheets();

foreach ($fail as $f) {
    echo "FAIL: {$f}\n";
}
if ($fail) {
    echo "Wrote {$out} for inspection.\n";
    exit(1);
}
echo "OK: RPCSE template fill passed (" . implode(', ', $names) . ")\n";
echo "Wrote {$out}\n";
exit(0);
