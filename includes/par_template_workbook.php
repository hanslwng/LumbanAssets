<?php
/**
 * Fill the official PAR Excel template with one issuance.
 * Never overwrites the master file — only a temporary copy.
 */

require_once __DIR__ . '/par_cell_map.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function par_template_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'par';
}

function par_template_path(): string
{
    return par_template_dir() . DIRECTORY_SEPARATOR . 'par-template.xlsx';
}

function par_filled_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'issuance-par';
}

function par_filled_path(int $issuanceId): string
{
    return par_filled_dir() . DIRECTORY_SEPARATOR . 'par-' . $issuanceId . '.xlsx';
}

function par_edited_path(int $issuanceId): string
{
    return par_filled_dir() . DIRECTORY_SEPARATOR . 'par-' . $issuanceId . '.edited';
}

function par_invalidate_filled(int $issuanceId): void
{
    if ($issuanceId <= 0) {
        return;
    }
    @unlink(par_filled_path($issuanceId));
    @unlink(par_edited_path($issuanceId));
}

function par_mark_edited(int $issuanceId): void
{
    if ($issuanceId <= 0) {
        return;
    }
    par_ensure_filled_dir();
    file_put_contents(par_edited_path($issuanceId), (string) time());
}

function par_ensure_filled_dir(): void
{
    $dir = par_filled_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function par_save_workbook(int $issuanceId, Spreadsheet $ss): string
{
    par_ensure_filled_dir();
    $path = par_filled_path($issuanceId);
    $writer = new Xlsx($ss);
    $writer->save($path);

    return $path;
}

/**
 * Load the admin-edited workbook if it exists; otherwise fill from the issuance and save it.
 */
function par_load_or_fill_workbook(array $issuance, int $issuanceId): Spreadsheet
{
    $path = par_filled_path($issuanceId);
    $edited = par_edited_path($issuanceId);
    if ($issuanceId > 0 && is_file($path) && filesize($path) > 0 && is_file($edited)) {
        return IOFactory::load($path);
    }
    $ss = par_fill_template($issuance);
    if ($issuanceId > 0) {
        par_save_workbook($issuanceId, $ss);
    }

    return $ss;
}

/**
 * @param array<string, string> $cells address => value
 */
function par_apply_cell_edits(Spreadsheet $ss, array $cells): void
{
    $map = par_cell_map();
    $sheet = $ss->getSheetByName($map['sheet']);
    if (!$sheet) {
        $sheet = $ss->getActiveSheet();
    }
    foreach ($cells as $addr => $value) {
        $addr = strtoupper(trim((string) $addr));
        if (!preg_match('/^[A-K](?:[1-9]|[1-3][0-9])$/', $addr)) {
            continue;
        }
        $value = str_replace("\r\n", "\n", (string) $value);
        $value = trim($value);
        if ($value === '') {
            par_set_cell($sheet, $addr, null);
            continue;
        }
        if (str_starts_with($value, '=')) {
            par_set_cell($sheet, $addr, $value);
            continue;
        }
        $num = str_replace(',', '', $value);
        if (is_numeric($num)) {
            par_set_cell($sheet, $addr, (float) $num);
            continue;
        }
        par_set_cell($sheet, $addr, $value);
    }
}

function par_set_cell(Worksheet $sheet, string $addr, $value): void
{
    $sheet->setCellValue($addr, $value);
}

function par_clear_item_rows(Worksheet $sheet, array $map): void
{
    $first = (int) $map['item_first_row'];
    $last = (int) $map['item_last_row'];
    for ($r = $first; $r <= $last; $r++) {
        for ($c = 1; $c <= 11; $c++) {
            $addr = Coordinate::stringFromColumnIndex($c) . $r;
            par_set_cell($sheet, $addr, null);
        }
    }
}

/**
 * @param array<string, mixed> $issuance
 */
function par_fill_template(array $issuance): Spreadsheet
{
    $path = par_template_path();
    if (!is_file($path)) {
        throw new RuntimeException('PAR template is missing: ' . $path);
    }

    $ss = IOFactory::load($path);
    $map = par_cell_map();
    $sheet = $ss->getSheetByName($map['sheet']);
    if (!$sheet) {
        $sheet = $ss->getActiveSheet();
    }

    par_clear_item_rows($sheet, $map);

    $date = static function ($d) {
        if ($d === null || $d === '') {
            return null;
        }
        $t = strtotime((string) $d);

        return $t ? date('Y-m-d', $t) : (string) $d;
    };

    par_set_cell($sheet, $map['department'], (string) ($issuance['office_name'] ?? ''));
    par_set_cell($sheet, $map['par_no'], (string) ($issuance['form_number'] ?? ''));
    par_set_cell($sheet, $map['par_date'], $date($issuance['issue_date'] ?? $issuance['issuance_date'] ?? null));
    par_set_cell($sheet, $map['section'], (string) ($issuance['section'] ?? ''));
    par_set_cell($sheet, $map['po_no'], (string) ($issuance['po_number'] ?? ''));
    par_set_cell($sheet, $map['po_date'], $date($issuance['po_date'] ?? null));

    $items = $issuance['items'] ?? [];
    if (!is_array($items) || $items === []) {
        $items = [[
            'quantity' => $issuance['quantity'] ?? 1,
            'unit' => $issuance['unit'] ?? 'pc',
            'description' => $issuance['description'] ?? '',
            'property_number' => $issuance['property_number'] ?? '',
            'acquisition_date' => $issuance['acquisition_date'] ?? null,
            'amount' => $issuance['amount'] ?? 0,
            'remarks' => $issuance['remarks'] ?? '',
        ]];
    }

    $firstRow = (int) $map['item_first_row'];
    $lastRow = (int) $map['item_last_row'];
    $maxItems = max(0, $lastRow - $firstRow + 1);
    $written = 0;
    foreach (array_slice($items, 0, $maxItems) as $i => $item) {
        $row = $firstRow + $i;
        $qty = (int) ($item['quantity'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }
        par_set_cell($sheet, 'A' . $row, $i + 1);
        par_set_cell($sheet, 'B' . $row, $qty);
        par_set_cell($sheet, 'C' . $row, strtoupper((string) ($item['unit'] ?? 'pc')));
        par_set_cell($sheet, 'D' . $row, (string) ($item['description'] ?? ''));
        par_set_cell($sheet, 'H' . $row, (string) ($item['property_number'] ?? ''));
        par_set_cell($sheet, 'I' . $row, $date($item['acquisition_date'] ?? $issuance['acquisition_date'] ?? $issuance['po_date'] ?? null));
        par_set_cell($sheet, 'J' . $row, (float) ($item['amount'] ?? 0));
        $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $lineRemarks = trim((string) ($item['remarks'] ?? ''));
        if ($lineRemarks === '' && $i === 0) {
            $lineRemarks = trim((string) ($issuance['remarks'] ?? ''));
        }
        par_set_cell($sheet, 'K' . $row, $lineRemarks !== '' ? $lineRemarks : '');
        $written++;
    }

    $follow = $firstRow + $written;
    if ($written > 0 && $follow <= $lastRow) {
        par_set_cell($sheet, 'D' . $follow, '*********nothing follows*********');
    }

    $sheet->setCellValue($map['total_formula'], '=SUM(J11:J20)');
    $sheet->getStyle($map['total_formula'])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle('J11:J21')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

    $received = trim((string) ($issuance['received_by'] ?? ''));
    if ($received === '') {
        $received = trim((string) ($issuance['issued_to'] ?? ''));
        $pos = trim((string) ($issuance['position'] ?? ''));
        if ($pos !== '') {
            $received .= ($received !== '' ? ' — ' : '') . $pos;
        }
    }
    par_set_cell($sheet, $map['received_name'], $received);
    par_set_cell($sheet, $map['administered_name'], (string) ($issuance['administered_by'] ?? ''));
    par_set_cell($sheet, $map['administered_title'], (string) ($issuance['administered_title'] ?? ''));
    par_set_cell($sheet, $map['issued_name'], (string) ($issuance['issued_by'] ?? ''));
    par_set_cell($sheet, $map['issued_title'], (string) ($issuance['issued_by_title'] ?? ''));
    par_set_cell($sheet, $map['approved_name'], (string) ($issuance['approved_by'] ?? ''));
    par_set_cell($sheet, $map['approved_title'], (string) ($issuance['approved_by_title'] ?? ''));

    return $ss;
}

/**
 * Build an Excel-like grid (merges, widths, formatted values) for in-browser View.
 *
 * @return array{title:string,cols:array<int,float>,letters:array<int,string>,rows:array<int,array{h:float,cells:array<int,array<string,mixed>>}>}
 */
function par_excel_preview_model(Spreadsheet $ss): array
{
    $map = par_cell_map();
    $ws = $ss->getSheetByName($map['sheet']);
    if (!$ws) {
        $ws = $ss->getActiveSheet();
    }

    $maxR = max((int) $ws->getHighestRow(), 32);
    $maxC = min(Coordinate::columnIndexFromString((string) $ws->getHighestColumn()), 11);

    $covered = [];
    $merges = [];
    foreach ($ws->getMergeCells() as $range) {
        [$start, $end] = explode(':', $range);
        [$c1, $r1] = Coordinate::coordinateFromString($start);
        [$c2, $r2] = Coordinate::coordinateFromString($end);
        $c1i = Coordinate::columnIndexFromString((string) $c1);
        $c2i = Coordinate::columnIndexFromString((string) $c2);
        $r1i = (int) $r1;
        $r2i = (int) $r2;
        $merges[$c1i . ':' . $r1i] = [
            'cs' => max(1, $c2i - $c1i + 1),
            'rs' => max(1, $r2i - $r1i + 1),
        ];
        for ($r = $r1i; $r <= $r2i; $r++) {
            for ($c = $c1i; $c <= $c2i; $c++) {
                if ($c === $c1i && $r === $r1i) {
                    continue;
                }
                $covered[$c . ':' . $r] = true;
            }
        }
    }

    $letters = [];
    $cols = [];
    for ($c = 1; $c <= $maxC; $c++) {
        $letter = Coordinate::stringFromColumnIndex($c);
        $letters[] = $letter;
        $dim = $ws->getColumnDimension($letter);
        $w = method_exists($dim, 'getWidth') ? $dim->getWidth() : null;
        $cols[] = $w !== null && (float) $w > 0 ? (float) $w : 10.0;
    }

    $rows = [];
    for ($r = 1; $r <= $maxR; $r++) {
        $rd = $ws->getRowDimension($r);
        $rh = null;
        if (method_exists($ws, 'getRowHeight')) {
            $rh = $ws->getRowHeight($r);
        } elseif (is_object($rd) && isset($rd->height)) {
            $rh = $rd->height;
        }
        $cells = [];
        for ($c = 1; $c <= $maxC; $c++) {
            $key = $c . ':' . $r;
            if (!empty($covered[$key])) {
                continue;
            }
            $addr = Coordinate::stringFromColumnIndex($c) . $r;
            $cell = $ws->getCell($addr);
            $raw = $cell->getValue();
            $text = '';
            $isAmountCol = $c === 10 && $r >= 11 && $r <= 21;
            try {
                if (is_string($raw) && str_starts_with($raw, '=')) {
                    $calc = $cell->getCalculatedValue();
                    $text = is_numeric($calc) ? number_format((float) $calc, 2) : (string) $calc;
                } else {
                    $text = (string) $cell->getFormattedValue();
                    if ($isAmountCol && is_numeric($raw)) {
                        $text = number_format((float) $raw, 2);
                    } elseif ($isAmountCol && is_numeric(str_replace(',', '', $text))) {
                        $text = number_format((float) str_replace(',', '', $text), 2);
                    }
                }
            } catch (Throwable $e) {
                $text = is_scalar($raw) || $raw === null ? (string) $raw : '';
                if ($isAmountCol && is_numeric($text)) {
                    $text = number_format((float) $text, 2);
                }
            }
            $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
            $align = strtolower((string) $cell->getStyle()->getAlignment()->getHorizontal());
            $font = $cell->getStyle()->getFont();
            $m = $merges[$key] ?? ['cs' => 1, 'rs' => 1];
            $rawOut = is_scalar($raw) || $raw === null ? (string) $raw : '';
            $cells[] = [
                'addr' => $addr,
                't' => $text,
                'raw' => $rawOut,
                'cs' => $m['cs'],
                'rs' => $m['rs'],
                'center' => $align === 'center' || $r === 10 || in_array($c, [1, 2, 3], true) && $r >= 11 && $r <= 20,
                'right' => $align === 'right' || ($c === 10 && $r >= 11 && $r <= 21),
                'bold' => (bool) $font->getBold() || in_array($r, [1, 2, 3, 5, 10, 21], true),
                'title' => $r === 5,
            ];
        }
        $rows[] = [
            'n' => $r,
            'h' => $rh !== null && $rh > 0 ? (float) $rh : 18.0,
            'cells' => $cells,
        ];
    }

    return [
        'title' => $ws->getTitle(),
        'cols' => $cols,
        'letters' => $letters,
        'rows' => $rows,
    ];
}
