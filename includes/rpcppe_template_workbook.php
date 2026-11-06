<?php
/**
 * Fill the official RPCPPE Excel template with live PPE inventory.
 * Never writes to rpcppe-template.xlsx in place — only temporary copies.
 */

require_once __DIR__ . '/rpcppe_cell_map.php';
require_once __DIR__ . '/physical_count_report_data.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function rpcppe_ensure_storage_dirs(): void
{
    $dir = rpcppe_template_dir();
    $archive = rpcppe_archive_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!is_dir($archive)) {
        mkdir($archive, 0775, true);
    }
}

function rpcppe_replace_template_bytes(string $bytes, string $sourceName = ''): array
{
    rpcppe_ensure_storage_dirs();
    $path = rpcppe_template_path();
    if (is_file($path)) {
        $stamp = date('Y-m-d-His');
        $dest = rpcppe_archive_dir() . DIRECTORY_SEPARATOR . 'rpcppe-template-' . $stamp . '.xlsx';
        if (!@rename($path, $dest)) {
            copy($path, $dest);
            unlink($path);
        }
    }
    $ok = file_put_contents($path, $bytes) !== false;

    return [
        'ok' => $ok,
        'path' => $path,
        'source' => $sourceName,
    ];
}

function rpcppe_ensure_template(): string
{
    rpcppe_ensure_storage_dirs();
    $path = rpcppe_template_path();
    if (!is_file($path) || filesize($path) <= 0) {
        throw new RuntimeException('Official RPCPPE template is missing: ' . $path);
    }

    return $path;
}

function rpcppe_ink_black(): array
{
    return ['rgb' => '000000'];
}

function rpcppe_hair_border(): array
{
    return [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_HAIR,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];
}

function rpcppe_apply_official_page_setup(Worksheet $sheet): void
{
    $ps = $sheet->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $ps->setPaperSize(RPCPPE_PAPER_SIZE);
    $ps->setFitToPage(true);
    $ps->setFitToWidth(1);
    $ps->setFitToHeight(0);
    $ps->setRowsToRepeatAtTop(['', '']);

    $m = $sheet->getPageMargins();
    $m->setTop(0.51);
    $m->setBottom(0.39);
    $m->setLeft(0);
    $m->setRight(0);
    $m->setHeader(0);
    $m->setFooter(0);
}

function rpcppe_is_data_sheet(Worksheet $sheet): bool
{
    $title = trim($sheet->getTitle());
    if (in_array($title, rpcppe_data_tabs(), true)) {
        return true;
    }
    $a1 = strtoupper((string) $sheet->getCell('A1')->getValue());

    return str_contains($a1, 'PROPERTY, PLANT AND EQUIPMENT');
}

function rpcppe_unmerge_from_row(Worksheet $sheet, int $startRow): void
{
    foreach (array_keys($sheet->getMergeCells()) as $range) {
        [$start, $end] = Coordinate::rangeBoundaries($range);
        $r1 = (int) $start[1];
        $r2 = (int) $end[1];
        if ($r1 >= $startRow || $r2 >= $startRow) {
            $sheet->unmergeCells($range);
        }
    }
}

function rpcppe_reset_row_style(Worksheet $sheet, int $row, bool $withGrid): void
{
    $style = [
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'italic' => false, 'color' => rpcppe_ink_black()],
        'fill' => ['fillType' => Fill::FILL_NONE],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_GENERAL,
            'vertical' => Alignment::VERTICAL_BOTTOM,
            'wrapText' => false,
        ],
    ];
    $style['borders'] = $withGrid
        ? rpcppe_hair_border()['borders']
        : [
            'allBorders' => [
                'borderStyle' => Border::BORDER_NONE,
                'color' => ['rgb' => '000000'],
            ],
        ];
    $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray($style);
}

function rpcppe_clear_from_row(Worksheet $sheet, int $startRow): void
{
    rpcppe_unmerge_from_row($sheet, $startRow);
    $highest = (int) $sheet->getHighestRow();
    if ($highest < $startRow) {
        return;
    }
    for ($r = $startRow; $r <= $highest; $r++) {
        foreach (range('A', 'J') as $col) {
            $sheet->setCellValue($col . $r, null);
        }
        rpcppe_reset_row_style($sheet, $r, false);
        $sheet->getRowDimension($r)->setRowHeight(15);
    }
}

function rpcppe_stamp_header(Worksheet $sheet, array $map, array $meta): void
{
    $h = $map['header'];
    $asOf = trim((string) ($meta['as_of'] ?? ''));
    if ($asOf !== '' && stripos($asOf, 'as of') !== 0 && stripos($asOf, 'as at') !== 0) {
        $asOf = 'As of ' . $asOf;
    }
    if ($asOf === '') {
        $asOf = 'As of December 31, ' . date('Y');
    }
    $sheet->setCellValue($h['as_of'], $asOf);

    $type = trim((string) ($meta['type_label'] ?? ''));
    if ($type !== '') {
        $sheet->setCellValue($h['type_label'], $type);
    }

    $sheet->setCellValue($h['officer'], (string) ($meta['officer_name'] ?? ''));
    $sheet->setCellValue($h['designation'], (string) ($meta['officer_designation'] ?? ''));
    $sheet->setCellValue($h['lgu'], (string) ($meta['lgu_name'] ?? ''));
    $assumption = trim((string) ($meta['assumption_date'] ?? ''));
    if ($assumption === '') {
        $assumption = $asOf;
    }
    $sheet->setCellValue($h['assumption'], $assumption);
}

function rpcppe_item_article_label(array $item, string $groupTitle): string
{
    $article = trim((string) ($item['article'] ?? ''));
    $group = trim($groupTitle);
    if ($article === '') {
        return '';
    }
    if ($group !== '' && strcasecmp($article, $group) === 0) {
        return '';
    }
    $tab = ppe_excel_tab_from_item($item);
    if (strcasecmp($article, $tab) === 0) {
        return '';
    }

    return $article;
}

function rpcppe_write_signature(Worksheet $sheet, int $row, array $meta): void
{
    $static = rpcppe_cell_map()['static'];
    $prepared = trim((string) ($meta['prepared_name'] ?? $meta['officer_name'] ?? ''));
    $preparedDesig = trim((string) ($meta['prepared_designation'] ?? $meta['officer_designation'] ?? ''));
    $approver = trim((string) ($meta['approver_name'] ?? ''));
    $approverDesig = trim((string) ($meta['approver_designation'] ?? ''));

    $sheet->setCellValue('A' . $row, $static['prepared_by']);
    $sheet->setCellValue('F' . $row, $static['approved_by']);
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'color' => rpcppe_ink_black()],
    ]);
    $sheet->getStyle('F' . $row)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 11, 'bold' => false, 'color' => rpcppe_ink_black()],
    ]);

    $nameRow = $row + 3;
    $sheet->setCellValue('A' . $nameRow, $prepared);
    $sheet->setCellValue('F' . $nameRow, $approver);
    $sheet->getStyle('A' . $nameRow)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'color' => rpcppe_ink_black()],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    ]);
    $sheet->getStyle('F' . $nameRow)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'color' => rpcppe_ink_black()],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    ]);

    $desigRow = $nameRow + 1;
    $sheet->setCellValue('A' . $desigRow, $preparedDesig);
    $sheet->setCellValue('F' . $desigRow, $approverDesig);
    $sheet->getStyle('A' . $desigRow . ':F' . $desigRow)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 11, 'bold' => false, 'color' => rpcppe_ink_black()],
    ]);
}

function rpcppe_fill_tab_sheet(Worksheet $sheet, array $items, array $meta): void
{
    $map = rpcppe_cell_map();
    $start = (int) $map['data_start_row'];

    rpcppe_apply_official_page_setup($sheet);
    rpcppe_stamp_header($sheet, $map, $meta);
    rpcppe_clear_from_row($sheet, $start);

    $row = $start;
    $categories = physical_count_report_segments_rpcppe($items);
    $catERows = [];

    foreach ($categories as $cat) {
        $catRow = $row;
        $title = function_exists('mb_strtoupper')
            ? mb_strtoupper((string) $cat['title'], 'UTF-8')
            : strtoupper((string) $cat['title']);
        rpcppe_reset_row_style($sheet, $row, true);
        $sheet->setCellValue('A' . $row, $title);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true, 'italic' => true, 'color' => rpcppe_ink_black()],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(16.5);
        $row++;

        $firstItem = $row;
        $lastArticle = '';
        foreach ($cat['items'] as $item) {
            rpcppe_reset_row_style($sheet, $row, true);

            $article = rpcppe_item_article_label($item, (string) $cat['title']);
            $articleCell = '';
            if ($article !== '' && $article !== $lastArticle) {
                $articleCell = $article;
                $lastArticle = $article;
            }

            $desc = trim((string) ($item['description'] ?? ''));
            $pn = trim((string) ($item['property_number'] ?? ''));
            $uom = trim((string) ($item['unit_of_measure'] ?? ''));
            if ($uom === '') {
                $uom = 'pc';
            }
            $uv = (float) ($item['unit_value'] ?? 0);
            $qc = pc_book_qty($item);
            $qp = pc_physical_qty($item);
            $remarks = trim((string) ($item['remarks'] ?? ''));

            $sheet->setCellValue('A' . $row, $articleCell);
            $sheet->setCellValue('B' . $row, $desc);
            $sheet->setCellValueExplicit('C' . $row, $pn, DataType::TYPE_STRING);
            $sheet->setCellValue('D' . $row, $uom);
            if ($uv != 0.0) {
                $sheet->setCellValue('E' . $row, $uv);
            }
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode(RPCPPE_NUM_FMT);
            $sheet->setCellValue('F' . $row, $qc);
            $sheet->setCellValue('G' . $row, $qp);
            if ($qp !== $qc) {
                $sheet->setCellValue('H' . $row, '=G' . $row . '-F' . $row);
                $sheet->setCellValue('I' . $row, '=H' . $row . '*E' . $row);
                $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode(RPCPPE_NUM_FMT);
            }
            $sheet->setCellValue('J' . $row, $remarks);

            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 11, 'bold' => false, 'color' => rpcppe_ink_black()],
            ]);
            $wrap = (function_exists('mb_strlen') ? mb_strlen($desc, 'UTF-8') : strlen($desc)) > 55;
            $sheet->getStyle('B' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'color' => rpcppe_ink_black()],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_BOTTOM, 'wrapText' => $wrap],
            ]);
            $sheet->getStyle('C' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'color' => rpcppe_ink_black()],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle('D' . $row . ':G' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'color' => rpcppe_ink_black()],
            ]);
            $sheet->getStyle('F' . $row . ':G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $descLen = function_exists('mb_strlen') ? mb_strlen($desc, 'UTF-8') : strlen($desc);
            $sheet->getRowDimension($row)->setRowHeight($descLen > 80 ? max(28, (int) ceil($descLen / 55) * 14) : 15);

            $row++;
        }

        $lastItem = $row - 1;
        if ($lastItem >= $firstItem) {
            $sheet->setCellValue('E' . $catRow, '=SUM(E' . $firstItem . ':E' . $lastItem . ')');
        } else {
            $sheet->setCellValue('E' . $catRow, 0);
        }
        $sheet->getStyle('E' . $catRow)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true, 'italic' => true, 'color' => rpcppe_ink_black()],
        ]);
        $sheet->getStyle('E' . $catRow)->getNumberFormat()->setFormatCode(RPCPPE_NUM_FMT);
        $catERows[] = $catRow;
    }

    if ($catERows) {
        $sheet->setCellValue('C' . $row, 'Total');
        $sheet->setCellValue('E' . $row, '=' . implode('+', array_map(static fn ($r) => 'E' . $r, $catERows)));
        $sheet->getStyle('C' . $row)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'italic' => true, 'color' => rpcppe_ink_black()],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle('E' . $row)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true, 'italic' => true, 'color' => rpcppe_ink_black()],
        ]);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode(RPCPPE_NUM_FMT);
        $row += 2;
    }

    rpcppe_write_signature($sheet, $row, $meta);
}

function rpcppe_buckets_by_tab(array $items): array
{
    $buckets = [];
    foreach (rpcppe_data_tabs() as $tab) {
        $buckets[$tab] = ['sheet_title' => $tab, 'items' => []];
    }
    foreach ($items as $item) {
        $tab = ppe_excel_tab_from_item($item);
        if (!isset($buckets[$tab])) {
            $buckets[$tab] = ['sheet_title' => $tab, 'items' => []];
        }
        $buckets[$tab]['items'][] = $item;
    }

    return $buckets;
}

function rpcppe_find_tab_sheet(Spreadsheet $ss, string $tab): ?Worksheet
{
    $hit = $ss->getSheetByName($tab);
    if ($hit) {
        return $hit;
    }
    foreach ($ss->getAllSheets() as $sh) {
        if (strcasecmp($sh->getTitle(), $tab) === 0) {
            return $sh;
        }
    }

    return null;
}

function rpcppe_show_only_sheet(Spreadsheet $ss, Worksheet $keep): void
{
    $keepIndex = 0;
    foreach ($ss->getAllSheets() as $i => $sh) {
        if ($sh === $keep) {
            $keepIndex = $i;
            $sh->setSheetState(Worksheet::SHEETSTATE_VISIBLE);
        } else {
            $sh->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        }
    }
    $ss->setActiveSheetIndex($keepIndex);
}

function rpcppe_hide_support_sheets(Spreadsheet $ss): void
{
    foreach ($ss->getAllSheets() as $sh) {
        $title = trim($sh->getTitle());
        if ($title === 'FORM' || $title === 'IIRUP' || !rpcppe_is_data_sheet($sh)) {
            $sh->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        }
    }
}

function rpcppe_save_workbook(Spreadsheet $spreadsheet, string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $tmp = $path . '.writing.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tmp);
    if (is_file($path) && !@unlink($path)) {
        copy($tmp, $path);
        @unlink($tmp);

        return;
    }
    if (!@rename($tmp, $path)) {
        copy($tmp, $path);
        @unlink($tmp);
    }
}

function rpcppe_save_preview_file(Spreadsheet $spreadsheet): void
{
    require_once __DIR__ . '/excel_export_lock.php';
    lgue_lock_export_spreadsheet($spreadsheet);
    rpcppe_save_workbook($spreadsheet, rpcppe_preview_path());
}

function rpcppe_open_in_excel(string $path): array
{
    if (!function_exists('rpcse_open_in_excel')) {
        require_once __DIR__ . '/rpcse_template_workbook.php';
    }

    return rpcse_open_in_excel($path);
}

/**
 * @param array<int, string> $wanted
 */
function rpcppe_tab_is_wanted(string $tab, array $wanted): bool
{
    if ($wanted === []) {
        return true;
    }
    foreach ($wanted as $name) {
        if (strcasecmp($tab, $name) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Fill official rpcppe-template.xlsx from live PPE inventory.
 * $tabName empty = all inventory tabs; string or list = those tabs only.
 *
 * @param string|array<int, string> $tabName
 */
function rpcppe_fill_template(array $items, array $meta, $tabName = ''): Spreadsheet
{
    $templatePath = rpcppe_ensure_template();
    $ss = IOFactory::load($templatePath);
    $map = rpcppe_cell_map();
    $wanted = [];
    if (is_array($tabName)) {
        foreach ($tabName as $t) {
            $t = trim((string) $t);
            if ($t !== '') {
                $wanted[] = $t;
            }
        }
    } elseif (trim((string) $tabName) !== '') {
        $wanted[] = trim((string) $tabName);
    }

    $asOf = trim((string) ($meta['as_of'] ?? ''));
    if ($asOf !== '' && stripos($asOf, 'as of') !== 0 && stripos($asOf, 'as at') !== 0) {
        $asOf = 'As of ' . $asOf;
    }
    if ($asOf === '') {
        $asOf = 'As of December 31, ' . date('Y');
        $meta['as_of'] = $asOf;
    }

    foreach ($ss->getAllSheets() as $sh) {
        if (rpcppe_is_data_sheet($sh) || strcasecmp($sh->getTitle(), 'FORM') === 0) {
            rpcppe_stamp_header($sh, $map, $meta);
            rpcppe_apply_official_page_setup($sh);
        }
    }

    $buckets = rpcppe_buckets_by_tab($items);
    $filled = [];
    $keep = null;
    foreach ($buckets as $tab => $bucket) {
        if (!rpcppe_tab_is_wanted((string) $tab, $wanted)) {
            continue;
        }
        $sheet = rpcppe_find_tab_sheet($ss, (string) $tab);
        if ($sheet === null) {
            continue;
        }
        rpcppe_fill_tab_sheet($sheet, $bucket['items'], $meta);
        $filled[] = $sheet;
        if (count($wanted) === 1 && strcasecmp((string) $tab, $wanted[0]) === 0) {
            $keep = $sheet;
        }
    }

    if ($keep instanceof Worksheet) {
        rpcppe_show_only_sheet($ss, $keep);
    } else {
        rpcppe_keep_visible_sheets($ss, $filled);
    }

    return $ss;
}

/**
 * @param array<int, Worksheet> $keep
 */
function rpcppe_keep_visible_sheets(Spreadsheet $ss, array $keep): void
{
    $keepIds = [];
    foreach ($keep as $sh) {
        if ($sh instanceof Worksheet) {
            $keepIds[spl_object_id($sh)] = true;
        }
    }
    $firstVisible = 0;
    foreach ($ss->getAllSheets() as $i => $sh) {
        if (isset($keepIds[spl_object_id($sh)])) {
            $sh->setSheetState(Worksheet::SHEETSTATE_VISIBLE);
            if ($firstVisible === 0) {
                $firstVisible = $i;
            }
        } else {
            $sh->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        }
    }
    $ss->setActiveSheetIndex($firstVisible);
}
