<?php
/**
 * Lock physical-count (and similar) Excel exports as view-only snapshots.
 * Edits belong in the system; exported .xlsx sheets are sheet+workbook protected.
 *
 * Not DRM — protection can be removed with the password — but Excel will refuse
 * casual cell edits without Unprotect Sheet.
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Protection as SheetProtection;

if (!defined('LGUE_XLSX_LOCK_PASSWORD')) {
    $envPwd = getenv('LGUE_XLSX_LOCK_PASSWORD');
    define(
        'LGUE_XLSX_LOCK_PASSWORD',
        (is_string($envPwd) && $envPwd !== '') ? $envPwd : 'LGU-PIMS-VIEW-ONLY'
    );
}

/**
 * Mark every sheet + workbook structure as protected (password required to unprotect).
 */
function lgue_lock_export_spreadsheet(Spreadsheet $spreadsheet, ?string $password = null): void
{
    $password = ($password !== null && $password !== '')
        ? $password
        : (string) LGUE_XLSX_LOCK_PASSWORD;

    $security = $spreadsheet->getSecurity();
    $security->setLockWindows(true);
    $security->setLockStructure(true);
    $security->setLockRevision(true);
    if ($password !== '') {
        // Prefer modern hash when writing workbook protection
        if (method_exists($security, 'setWorkbookAlgorithmName')) {
            $security->setWorkbookAlgorithmName(SheetProtection::ALGORITHM_SHA_512);
            $security->setWorkbookSaltValue(random_bytes(16), true);
            $security->setWorkbookSpinCount(100000);
        }
        $security->setWorkbookPassword($password);
        $security->setRevisionsPassword($password);
    }

    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $prot = $sheet->getProtection();
        $prot->setAlgorithm(SheetProtection::ALGORITHM_SHA_512);
        $prot->setSpinCount(100000);
        if ($password !== '') {
            $prot->setPassword($password);
        }
        // OOXML: sheet/objects/scenarios true = protected;
        // format/insert/delete/sort true = action ALLOWED while protected.
        $prot->setSheet(true);
        $prot->setObjects(true);
        $prot->setScenarios(true);
        $prot->setFormatCells(false);
        $prot->setFormatColumns(false);
        $prot->setFormatRows(false);
        $prot->setInsertColumns(false);
        $prot->setInsertRows(false);
        $prot->setInsertHyperlinks(false);
        $prot->setDeleteColumns(false);
        $prot->setDeleteRows(false);
        $prot->setSort(false);
        $prot->setAutoFilter(false);
        $prot->setPivotTables(false);
        // Allow select/copy for reading; still cannot edit without password.
        $prot->setSelectLockedCells(true);
        $prot->setSelectUnlockedCells(true);
    }

    $props = $spreadsheet->getProperties();
    $props->setDescription(trim(
        ($props->getDescription() ?: '')
        . ' View-only export from LGU Property Inventory. Edit counts inside the system, not this file.'
    ));
}
