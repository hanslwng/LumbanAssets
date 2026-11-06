<?php
$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/includes/rpcppe_template_workbook.php';

$meta = [
    'as_of' => 'December 31, 2025',
    'type_label' => 'Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment',
    'officer_name' => 'RHONALIZA A. ALVIAR',
    'officer_designation' => 'GSO - Designate',
    'lgu_name' => 'Municipal Government of Lumban, Laguna',
    'assumption_date' => 'December 31, 2025',
    'prepared_name' => 'RHONALIZA A. ALVIAR',
    'prepared_designation' => 'GSO - Designate',
    'approver_name' => 'BELEN B. RAGA',
    'approver_designation' => 'Municipal Mayor',
];

$items = [
    [
        'id' => 1, 'item_group' => 'ROAD NETWORKS', 'article' => 'ROAD NETWORKS',
        'description' => 'Access Road test', 'property_number' => '2019-03-010-01F001-01F',
        'unit_of_measure' => 'lot', 'unit_value' => 1000, 'quantity' => 1,
        'quantity_physical_count' => 1, 'remarks' => '', 'sort_order' => 0,
    ],
    [
        'id' => 2, 'item_group' => 'IT EQUIPMENTS', 'article' => 'Computer',
        'description' => 'Desktop set', 'property_number' => '2020-07-010-01001-01',
        'unit_of_measure' => 'unit', 'unit_value' => 55000, 'quantity' => 1,
        'quantity_physical_count' => 0, 'remarks' => 'Missing', 'sort_order' => 0,
    ],
    [
        'id' => 3, 'item_group' => 'IT EQUIPMENTS', 'article' => 'Computer',
        'description' => 'Laptop', 'property_number' => '2020-07-010-01002-01',
        'unit_of_measure' => 'unit', 'unit_value' => 62000, 'quantity' => 1,
        'quantity_physical_count' => 1, 'remarks' => '', 'sort_order' => 1,
    ],
];

$ss = rpcppe_fill_template($items, $meta, '');
$fail = [];
$land = $ss->getSheetByName('LAND FINAL');
$sup = $ss->getSheetByName('SUPPLIES AND EQUIPMENT FINAL');
if (!$land || !$sup) {
    $fail[] = 'Official tabs missing';
}
if ($land) {
    $repeat = $land->getPageSetup()->getRowsToRepeatAtTop();
    if (!($repeat === ['', ''] || $repeat === [0, 0])) {
        $fail[] = 'LAND print titles still set: ' . json_encode($repeat);
    }
    $a14 = (string) $land->getCell('A14')->getValue();
    if (stripos($a14, 'ROAD NETWORKS') === false) {
        $fail[] = 'LAND A14 category, got ' . $a14;
    }
    $a15 = trim((string) $land->getCell('A15')->getValue());
    if ($a15 !== '') {
        $fail[] = 'LAND item article should be blank, got ' . $a15;
    }
    $republics = 0;
    for ($r = 1; $r <= (int) $land->getHighestRow(); $r++) {
        if (stripos((string) $land->getCell('A' . $r)->getValue(), 'REPUBLIC') !== false) {
            $republics++;
        }
    }
    if ($republics !== 0) {
        $fail[] = 'PPE form should not have a Republic header';
    }
}
if ($sup) {
    $a14 = (string) $sup->getCell('A14')->getValue();
    if (stripos($a14, 'IT EQUIPMENTS') === false) {
        $fail[] = 'SUPPLIES A14 category, got ' . $a14;
    }
    $a15 = (string) $sup->getCell('A15')->getValue();
    if ($a15 !== 'Computer') {
        $fail[] = 'First IT article should be Computer, got ' . $a15;
    }
    $a16 = trim((string) $sup->getCell('A16')->getValue());
    if ($a16 !== '') {
        $fail[] = 'Second Computer row article should be blank';
    }
}

$out = $root . '/storage/templates/rpcppe/archive/test-rpcppe-template-output.xlsx';
$writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
$writer->save($out);
$ss->disconnectWorksheets();

foreach ($fail as $f) {
    echo "FAIL: {$f}\n";
}
if ($fail) {
    exit(1);
}
echo "OK: official RPCPPE template fill\n";
echo "Wrote {$out}\n";
exit(0);
