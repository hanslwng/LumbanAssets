<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/includes/rpcse_template_workbook.php';

$path = rpcse_template_path();
echo "path=$path\n";
echo "exists=" . (is_file($path) ? 'yes' : 'no') . " size=" . (is_file($path) ? filesize($path) : 0) . "\n";

$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
echo "sheets=" . implode(' | ', $ss->getSheetNames()) . "\n";
echo "count=" . $ss->getSheetCount() . "\n";
$m = $ss->getSheetByName('Sheet') ?: $ss->getSheet(0);
echo "master=" . $m->getTitle() . " highestRow=" . $m->getHighestRow() . " col=" . $m->getHighestColumn() . "\n";
echo "merges=" . count($m->getMergeCells()) . "\n";
echo "mem=" . round(memory_get_peak_usage(true) / 1048576, 1) . "MB\n";
