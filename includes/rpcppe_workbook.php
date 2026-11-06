<?php
/**
 * RPCPPE Excel workbook writer + round-trip parser (PhpSpreadsheet).
 */

require_once __DIR__ . '/rpcppe_cell_map.php';
require_once __DIR__ . '/physical_count_report_data.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Group sorted items into RPCPPE category sections (by item_group, else article).
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array{title:string,items:array<int,array<string,mixed>>}>
 */
function rpcppe_group_items(array $items): array
{
    $items = physical_count_sort_items($items);
    $groups = [];
    $order = [];

    foreach ($items as $item) {
        $g = trim((string) ($item['item_group'] ?? ''));
        if ($g === '') {
            $g = trim((string) ($item['article'] ?? ''));
        }
        if ($g === '') {
            $g = 'ITEMS';
        }
        $key = mb_strtoupper($g, 'UTF-8');
        if (!isset($groups[$key])) {
            $groups[$key] = ['title' => $g, 'items' => []];
            $order[] = $key;
        }
        $groups[$key]['items'][] = $item;
    }

    $out = [];
    foreach ($order as $key) {
        $out[] = $groups[$key];
    }

    return $out;
}

/**
 * Build a filled RPCPPE workbook from live property_items rows.
 *
 * @param array<int, array<string, mixed>> $items
 * @param array<string, mixed> $meta from rpcppe_default_meta() (+ overrides)
 */
function rpcppe_build_spreadsheet(array $items, array $meta): Spreadsheet
{
    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    // One sheet for this category run (SE or PPE). Major account groups become
    // category header rows inside the sheet — matching the filled LAND FINAL pattern.
    // If callers later pass multiple "account group" buckets, each becomes its own sheet.
    $buckets = [
        [
            'sheet_title' => (string) ($meta['sheet_title'] ?? 'RPCPPE'),
            'type_label' => (string) ($meta['type_label'] ?? ''),
            'items' => $items,
        ],
    ];

    foreach ($buckets as $i => $bucket) {
        $sheet = new Worksheet($spreadsheet, rpcppe_safe_sheet_title((string) $bucket['sheet_title']));
        $spreadsheet->addSheet($sheet, $i);
        $sheetMeta = $meta;
        $sheetMeta['type_label'] = $bucket['type_label'] !== '' ? $bucket['type_label'] : $meta['type_label'];
        rpcppe_write_sheet($sheet, $bucket['items'], $sheetMeta);
    }

    $spreadsheet->setActiveSheetIndex(0);

    return $spreadsheet;
}

function rpcppe_safe_sheet_title(string $title): string
{
    $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title) ?? 'RPCPPE';
    $title = trim($title);
    if ($title === '') {
        $title = 'RPCPPE';
    }

    return mb_substr($title, 0, 31, 'UTF-8');
}

/**
 * Write one RPCPPE sheet (header + grouped body + TOTAL + signatures).
 *
 * @param array<int, array<string, mixed>> $items
 * @param array<string, mixed> $meta
 */
function rpcppe_write_sheet(Worksheet $sheet, array $items, array $meta): void
{
    $map = rpcppe_cell_map();
    $static = $map['static'];

    rpcppe_apply_page_setup($sheet);
    rpcppe_apply_column_widths($sheet, $map['column_widths']);

    // --- Header block ---
    $sheet->mergeCells('A1:J1');
    $sheet->setCellValue('A1', (string) ($meta['title'] ?? $static['title_ppe']));
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['name' => 'Arial Black', 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->mergeCells('A3:J3');
    $sheet->setCellValue('A3', (string) ($meta['type_label'] ?? ''));
    $sheet->getStyle('A3')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->mergeCells('A4:J4');
    $sheet->setCellValue('A4', (string) ($meta['type_caption'] ?? $static['type_caption_ppe']));
    $sheet->getStyle('A4')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $asOf = trim((string) ($meta['as_of'] ?? ''));
    if ($asOf !== '' && stripos($asOf, 'as of') !== 0) {
        $asOf = 'As of ' . $asOf;
    }
    $sheet->mergeCells('A5:J5');
    $sheet->setCellValue('A5', $asOf !== '' ? $asOf : 'As of ' . date('F j, Y'));
    $sheet->getStyle('A5')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->setCellValue('A7', $static['for_which']);
    $sheet->getStyle('A7')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);

    $sheet->setCellValue('B7', (string) ($meta['officer_name'] ?? ''));
    $sheet->getStyle('B7')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->mergeCells('C7:D7');
    $sheet->setCellValue('C7', (string) ($meta['officer_designation'] ?? ''));
    $sheet->getStyle('C7')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->mergeCells('E7:G7');
    $sheet->setCellValue('E7', (string) ($meta['lgu_name'] ?? ''));
    $sheet->getStyle('E7')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->setCellValue('H7', $static['is_accountable']);
    $sheet->getStyle('H7')->getFont()->setName('Arial')->setSize(10);

    $sheet->setCellValue('B8', $static['cap_officer']);
    $sheet->mergeCells('C8:D8');
    $sheet->setCellValue('C8', $static['cap_designation']);
    $sheet->mergeCells('E8:G8');
    $sheet->setCellValue('E8', $static['cap_lgu']);
    foreach (['B8', 'C8', 'E8'] as $cap) {
        $sheet->getStyle($cap)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 9, 'italic' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    $sheet->setCellValueExplicit('A9', $static['accountability_on'], DataType::TYPE_STRING);
    $sheet->getStyle('A9')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->setCellValue('B9', (string) ($meta['assumption_date'] ?? ''));
    $sheet->getStyle('B9')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->setCellValue('B10', $static['cap_assumption']);
    $sheet->getStyle('B10')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 9, 'italic' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ]);

    // --- Table headers (rows 12–13) ---
    foreach ($map['headers_row12'] as $col => $label) {
        $sheet->setCellValue($col . '12', $label);
    }
    foreach ($map['headers_row13'] as $col => $label) {
        $sheet->setCellValue($col . '13', $label);
    }

    $sheet->mergeCells('A12:A13');
    $sheet->mergeCells('B12:B13');
    $sheet->mergeCells('C12:C13');
    $sheet->mergeCells('D12:D13');
    $sheet->mergeCells('E12:E13');
    $sheet->mergeCells('H12:I12');
    $sheet->mergeCells('J12:J13');

    $headerStyle = [
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_HAIR,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];
    $sheet->getStyle('A12:J13')->applyFromArray($headerStyle);
    $sheet->getStyle('F12')->getFont()->setSize(9);
    $sheet->getStyle('G12')->getFont()->setSize(9);
    $sheet->getRowDimension(12)->setRowHeight(24);

    // --- Body ---
    $groups = rpcppe_group_items($items);
    $row = (int) $map['data_start_row'];
    $categoryHeaderRows = [];
    $tableStart = $row;

    foreach ($groups as $group) {
        $catRow = $row;
        $categoryHeaderRows[] = $catRow;
        $firstItem = $row + 1;
        $itemCount = count($group['items']);
        $lastItem = $itemCount > 0 ? ($firstItem + $itemCount - 1) : $firstItem;

        $sheet->setCellValue('A' . $catRow, (string) $group['title']);
        $sheet->getStyle('A' . $catRow)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        if ($itemCount > 0) {
            $sheet->setCellValue('E' . $catRow, '=SUM(E' . $firstItem . ':E' . $lastItem . ')');
        } else {
            $sheet->setCellValue('E' . $catRow, 0);
        }
        $sheet->getStyle('E' . $catRow)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true],
            'numberFormat' => ['formatCode' => RPCPPE_NUM_FMT],
        ]);

        $row++;

        foreach ($group['items'] as $item) {
            rpcppe_write_item_row($sheet, $row, $item);
            $row++;
        }
    }

    $tableEnd = max($tableStart, $row - 1);
    if ($row > $tableStart) {
        $sheet->getStyle('A' . $tableStart . ':J' . $tableEnd)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_HAIR,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
    }

    // --- Grand TOTAL ---
    $totalRow = $row;
    $sheet->setCellValue('C' . $totalRow, $static['total_label']);
    $sheet->getStyle('C' . $totalRow)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);

    if ($categoryHeaderRows) {
        $parts = array_map(static fn ($r) => 'E' . $r, $categoryHeaderRows);
        $sheet->setCellValue('E' . $totalRow, '=' . implode('+', $parts));
    } else {
        $sheet->setCellValue('E' . $totalRow, 0);
    }
    $sheet->getStyle('E' . $totalRow)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'italic' => true],
        'numberFormat' => ['formatCode' => RPCPPE_NUM_FMT],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getStyle('A' . $totalRow . ':J' . $totalRow)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_HAIR,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ]);

    // --- Signature block ---
    $sig = $totalRow + 2;
    $sheet->setCellValue('A' . $sig, $static['prepared_by']);
    $sheet->setCellValue('F' . $sig, $static['approved_by']);
    $sheet->getStyle('A' . $sig)->getFont()->setName('Arial')->setSize(10);
    $sheet->getStyle('F' . $sig)->getFont()->setName('Arial')->setSize(10);

    $nameRow = $sig + 3;
    $sheet->mergeCells('A' . $nameRow . ':B' . $nameRow);
    $sheet->mergeCells('F' . $nameRow . ':J' . $nameRow);
    $sheet->setCellValue('A' . $nameRow, (string) ($meta['officer_name'] ?? ''));
    $sheet->setCellValue('F' . $nameRow, (string) ($meta['approver_name'] ?? ''));
    foreach (['A' . $nameRow, 'F' . $nameRow] as $c) {
        $sheet->getStyle($c)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    $desigRow = $nameRow + 1;
    $sheet->mergeCells('A' . $desigRow . ':B' . $desigRow);
    $sheet->mergeCells('F' . $desigRow . ':J' . $desigRow);
    $sheet->setCellValue('A' . $desigRow, (string) ($meta['officer_designation'] ?? ''));
    $sheet->setCellValue('F' . $desigRow, (string) ($meta['approver_designation'] ?? ''));
    foreach (['A' . $desigRow, 'F' . $desigRow] as $c) {
        $sheet->getStyle($c)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    $capRow = $desigRow + 1;
    $sheet->mergeCells('A' . $capRow . ':B' . $capRow);
    $sheet->mergeCells('F' . $capRow . ':J' . $capRow);
    $sheet->setCellValue('A' . $capRow, $static['sig_left']);
    $sheet->setCellValue('F' . $capRow, $static['sig_right']);
    foreach (['A' . $capRow, 'F' . $capRow] as $c) {
        $sheet->getStyle($c)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 8, 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
    }

    $dateRow = $capRow + 1;
    $sheet->setCellValue('A' . $dateRow, $static['date_label']);
    $sheet->setCellValue('F' . $dateRow, $static['date_label']);
    $sheet->getStyle('A' . $dateRow)->getFont()->setName('Arial')->setSize(10);
    $sheet->getStyle('F' . $dateRow)->getFont()->setName('Arial')->setSize(10);
}

/**
 * @param array<string, mixed> $item
 */
function rpcppe_write_item_row(Worksheet $sheet, int $row, array $item): void
{
    $desc = trim((string) ($item['description'] ?? ''));
    $pn = trim((string) ($item['property_number'] ?? ''));
    $uom = trim((string) ($item['unit_of_measure'] ?? ''));
    if ($uom === '') {
        $uom = 'pc';
    }
    $uv = (float) ($item['unit_value'] ?? 0);
    $qc = pc_book_qty($item);
    $rawPhys = $item['quantity_physical_count'] ?? null;
    $hasPhys = !($rawPhys === null || $rawPhys === '');
    $qp = $hasPhys ? (int) $rawPhys : $qc;
    $rem = trim((string) ($item['remarks'] ?? ''));

    $sheet->setCellValue('B' . $row, $desc);
    $sheet->setCellValueExplicit('C' . $row, $pn, DataType::TYPE_STRING);
    $sheet->setCellValue('D' . $row, $uom);
    $sheet->setCellValue('E' . $row, $uv);
    $sheet->setCellValue('F' . $row, $qc);
    $sheet->setCellValue('G' . $row, $qp);
    if ($rem !== '') {
        $sheet->setCellValue('J' . $row, $rem);
    }

    $sheet->getStyle('A' . $row . ':J' . $row)->getFont()->setName('Arial')->setSize(10);
    $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode(RPCPPE_NUM_FMT);
    $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Shortage/overage formulas only when quantities differ (or physical was explicitly recorded and differs).
    if ($qp !== $qc) {
        $sheet->setCellValue('H' . $row, '=G' . $row . '-F' . $row);
        $sheet->setCellValue('I' . $row, '=H' . $row . '*E' . $row);
        $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode(RPCPPE_NUM_FMT);
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
}

function rpcppe_apply_column_widths(Worksheet $sheet, array $widths): void
{
    foreach ($widths as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth((float) $w);
    }
}

function rpcppe_apply_page_setup(Worksheet $sheet): void
{
    $ps = $sheet->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $ps->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
    // One header only — later pages continue item rows.
    $ps->setRowsToRepeatAtTop(['', '']);
    $ps->setFitToPage(true);
    $ps->setFitToWidth(1);
    $ps->setFitToHeight(0);

    $m = $sheet->getPageMargins();
    $m->setLeft(0);
    $m->setRight(0);
    $m->setTop(0.51);
    $m->setBottom(0.39);
}

/**
 * Stream workbook as .xlsx download and exit.
 */
function rpcppe_stream_download(Spreadsheet $spreadsheet, string $filename): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    require_once __DIR__ . '/excel_export_lock.php';
    lgue_lock_export_spreadsheet($spreadsheet);

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    if ($tmp === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Could not create a temporary file for the Excel download.';
        exit;
    }

    try {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tmp);
    } catch (Throwable $e) {
        @unlink($tmp);
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Could not write the Excel file. ' . $e->getMessage();
        exit;
    }

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'report.xlsx';
    $size = filesize($tmp);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) $size);
    header('Cache-Control: max-age=0');
    header('X-Content-Type-Options: nosniff');

    readfile($tmp);
    @unlink($tmp);
    exit;
}

/**
 * Parse an uploaded RPCPPE workbook and return item updates keyed by property number.
 * Skips category-header rows (col A set, col B empty) and TOTAL rows.
 *
 * @return array{updates: array<int, array{property_number:string,quantity_physical_count:?int,remarks:?string,unit_value:?float,quantity:?int}>, errors: string[]}
 */
function rpcppe_parse_workbook(Spreadsheet $spreadsheet): array
{
    $map = rpcppe_cell_map();
    $dataStart = (int) $map['data_start_row'];
    $updates = [];
    $errors = [];

    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $highest = (int) $sheet->getHighestRow();
        for ($r = $dataStart; $r <= $highest; $r++) {
            $a = trim((string) $sheet->getCell('A' . $r)->getValue());
            $b = trim((string) $sheet->getCell('B' . $r)->getValue());
            $c = trim((string) $sheet->getCell('C' . $r)->getCalculatedValue());
            $eRaw = $sheet->getCell('E' . $r)->getValue();
            $fRaw = $sheet->getCell('F' . $r)->getCalculatedValue();
            $gRaw = $sheet->getCell('G' . $r)->getCalculatedValue();
            $j = trim((string) $sheet->getCell('J' . $r)->getValue());

            // Category header: title in A, blank description, often a SUM formula in E.
            if ($a !== '' && $b === '' && (is_string($eRaw) && str_starts_with($eRaw, '='))) {
                continue;
            }
            if ($a !== '' && $b === '' && $c === '') {
                continue;
            }
            // TOTAL row
            if (strcasecmp($c, 'TOTAL') === 0 || strcasecmp($c, 'Total') === 0) {
                continue;
            }
            // Signature / footer noise
            if ($a !== '' && $b === '' && $c === '' && preg_match('/^(Prepared by|Approved by|Date:)/i', $a)) {
                break;
            }
            if ($b === '' || $c === '' || strcasecmp($c, 'PROPERTY NUMBER') === 0) {
                continue;
            }
            if (!preg_match('/[A-Za-z0-9]/', $c)) {
                continue;
            }

            $phys = null;
            if ($gRaw !== null && $gRaw !== '') {
                if (!is_numeric($gRaw)) {
                    $errors[] = "Row {$r} ({$c}): on-hand quantity is not numeric.";
                    continue;
                }
                $phys = (int) $gRaw;
            }

            $card = null;
            if ($fRaw !== null && $fRaw !== '' && is_numeric($fRaw)) {
                $card = (int) $fRaw;
            }

            $uv = null;
            $eCalc = $sheet->getCell('E' . $r)->getCalculatedValue();
            if ($eCalc !== null && $eCalc !== '' && is_numeric($eCalc)) {
                $uv = (float) $eCalc;
            }

            $updates[] = [
                'property_number' => $c,
                'quantity_physical_count' => $phys,
                'quantity' => $card,
                'unit_value' => $uv,
                'remarks' => $j !== '' ? $j : null,
            ];
        }
    }

    return ['updates' => $updates, 'errors' => $errors];
}

/**
 * Apply parsed RPCPPE item rows back onto property_items (match by property_number).
 *
 * @param array<int, array{property_number:string,quantity_physical_count:?int,remarks:?string,unit_value:?float,quantity:?int}> $updates
 * @return array{updated:int,skipped:int,missing:string[]}
 */
function rpcppe_apply_updates(PDO $conn, array $updates, bool $updateCardQty = false, bool $updateUnitValue = false): array
{
    $updated = 0;
    $skipped = 0;
    $missing = [];

    $find = $conn->prepare('SELECT id FROM property_items WHERE property_number = ? LIMIT 1');
    $sql = 'UPDATE property_items SET quantity_physical_count = ?';
    if ($updateCardQty) {
        $sql .= ', quantity = ?';
    }
    if ($updateUnitValue) {
        $sql .= ', unit_value = ?';
    }
    $sql .= ', remarks = COALESCE(?, remarks) WHERE id = ?';
    $upd = $conn->prepare($sql);

    foreach ($updates as $u) {
        $pn = trim((string) ($u['property_number'] ?? ''));
        if ($pn === '') {
            $skipped++;
            continue;
        }
        $find->execute([$pn]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $missing[] = $pn;
            $skipped++;
            continue;
        }

        $params = [$u['quantity_physical_count']];
        if ($updateCardQty) {
            $params[] = $u['quantity'];
        }
        if ($updateUnitValue) {
            $params[] = $u['unit_value'];
        }
        $params[] = $u['remarks'];
        $params[] = (int) $row['id'];
        $upd->execute($params);
        $updated++;
    }

    return ['updated' => $updated, 'skipped' => $skipped, 'missing' => $missing];
}
