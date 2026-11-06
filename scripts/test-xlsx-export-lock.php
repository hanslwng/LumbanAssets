<?php
/**
 * Smoke-test (no ZipArchive reload): lock flags on the in-memory workbook.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/includes/excel_export_lock.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

$ss = new Spreadsheet();
$ss->getActiveSheet()->setTitle('MSWDO');
$ss->getActiveSheet()->setCellValue('A1', 'Article');
$ss->createSheet()->setTitle('GSO');

lgue_lock_export_spreadsheet($ss);

$ok = true;
$sec = $ss->getSecurity();
echo 'lockStructure=' . ($sec->getLockStructure() ? 'yes' : 'no') . "\n";
echo 'lockWindows=' . ($sec->getLockWindows() ? 'yes' : 'no') . "\n";
if (!$sec->getLockStructure() || !$sec->getLockWindows()) {
    $ok = false;
}
foreach ($ss->getAllSheets() as $sheet) {
    $p = $sheet->getProtection();
    $sheetOk = (bool) $p->getSheet();
    $pwd = $p->getPassword();
    echo $sheet->getTitle() . ': protected=' . ($sheetOk ? 'yes' : 'no')
        . ' pwd=' . ($pwd !== '' ? 'set' : 'empty') . "\n";
    if (!$sheetOk || $pwd === '') {
        $ok = false;
    }
}

$out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lgue-lock-test.xlsx';
IOFactory::createWriter($ss, 'Xlsx')->save($out);
echo 'saved_bytes=' . filesize($out) . "\n";
echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
