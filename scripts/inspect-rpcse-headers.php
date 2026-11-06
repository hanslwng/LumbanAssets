<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/includes/rpcse_cell_map.php';

$path = rpcse_template_path();
$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
echo "sheets: " . implode(', ', $ss->getSheetNames()) . "\n\n";

foreach (['Sheet', 'GSO', 'PESO'] as $name) {
    $sh = $ss->getSheetByName($name);
    if (!$sh) {
        echo "== $name MISSING ==\n";
        continue;
    }
    echo "== $name row1-20 / 55-62 ==\n";
    echo "highestRow=" . $sh->getHighestRow() . " col=" . $sh->getHighestColumn() . "\n";
    for ($r = 1; $r <= 20; $r++) {
        $vals = [];
        foreach (range('A', 'J') as $c) {
            $v = $sh->getCell($c . $r)->getValue();
            if ($v !== null && $v !== '') {
                $vals[] = $c . $r . '=' . (is_string($v) ? str_replace(["\n", "\r"], ' ', mb_substr((string) $v, 0, 60)) : $v);
            }
        }
        if ($vals) {
            echo "R$r: " . implode(' | ', $vals) . "\n";
        }
    }
    for ($r = 50; $r <= min(70, (int) $sh->getHighestRow()); $r++) {
        $vals = [];
        foreach (range('A', 'J') as $c) {
            $v = $sh->getCell($c . $r)->getValue();
            if ($v !== null && $v !== '') {
                $vals[] = $c . $r . '=' . (is_string($v) ? str_replace(["\n", "\r"], ' ', mb_substr((string) $v, 0, 50)) : $v);
            }
        }
        if ($vals) {
            echo "R$r: " . implode(' | ', $vals) . "\n";
        }
    }
    echo "\n";
}
