<?php
/**
 * Fill the client's RPCSE Excel template with live SE inventory (one tab per office).
 * Never writes to rpcse-template.xlsx in place — only temporary copies.
 */

require_once __DIR__ . '/rpcse_cell_map.php';
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

function rpcse_ensure_storage_dirs(): void
{
    $dir = rpcse_template_dir();
    $archive = rpcse_archive_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!is_dir($archive)) {
        mkdir($archive, 0775, true);
    }
}

/**
 * Archive current template (if any) then write bytes as rpcse-template.xlsx.
 */
function rpcse_replace_template_bytes(string $bytes, string $sourceName = ''): array
{
    rpcse_ensure_storage_dirs();
    $path = rpcse_template_path();
    if (is_file($path)) {
        $stamp = date('Y-m-d-His');
        $dest = rpcse_archive_dir() . DIRECTORY_SEPARATOR . 'rpcse-template-' . $stamp . '.xlsx';
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

/**
 * Create the blank master workbook (Sheet tab) matching the confirmed cell map.
 * Used when the client's file is not yet in storage.
 */
function rpcse_build_blank_template(): Spreadsheet
{
    $map = rpcse_cell_map();
    $st = $map['static'];
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle($map['master_sheet']);

    foreach ($map['column_widths'] as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth((float) $w);
    }

    foreach ($map['merges'] as $range) {
        $sheet->mergeCells($range);
    }

    $center = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
    ];

    $sheet->setCellValue('A1', $st['republic']);
    $sheet->setCellValue('A2', $st['province']);
    $sheet->setCellValue('A3', $st['municipality']);
    $sheet->getStyle('A1:A3')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true],
    ] + $center);

    $sheet->setCellValue('J4', $st['annex']);
    $sheet->getStyle('J4')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);

    $sheet->setCellValue('A5', $st['title']);
    $sheet->getStyle('A5')->applyFromArray([
        'font' => ['name' => 'Arial Black', 'size' => 14],
    ] + $center);

    $sheet->setCellValue('A6', $st['type_label']);
    $sheet->getStyle('A6')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'italic' => true],
    ] + $center);

    $sheet->setCellValue('A7', $st['type_caption']);
    $sheet->getStyle('A7')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'italic' => true],
    ] + $center);

    $sheet->setCellValue('A8', 'As at ' . date('F j, Y'));
    $sheet->getStyle('A8')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true],
    ] + $center);

    $sheet->setCellValue('A10', $st['fund_cluster']);
    $sheet->getStyle('A10')->getFont()->setName('Arial')->setSize(10);

    $sheet->setCellValue('A11', $st['for_which']);
    $sheet->setCellValue('H11', $st['is_accountable']);
    $sheet->setCellValue('B12', $st['cap_officer']);
    $sheet->setCellValue('C12', $st['cap_designation']);
    $sheet->setCellValue('E12', $st['cap_entity']);
    $sheet->setCellValue('A13', $st['accountability_on']);
    $sheet->setCellValue('B14', $st['cap_assumption']);
    $sheet->getStyle('A11:H11')->getFont()->setName('Arial')->setSize(10);
    $sheet->getStyle('B12:E12')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 8, 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle('A13')->getFont()->setName('Arial')->setSize(10);
    $sheet->getStyle('B14')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 8, 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $h16 = $map['headers_row16'];
    foreach ($h16 as $col => $label) {
        $sheet->setCellValue($col . '16', $label);
    }
    $h17 = $map['headers_row17'];
    foreach ($h17 as $col => $label) {
        $sheet->setCellValue($col . '17', $label);
    }

    $sheet->getRowDimension(16)->setRowHeight((float) $map['row_heights'][16]);
    $sheet->getRowDimension(17)->setRowHeight((float) $map['row_heights'][17]);

    $headerStyle = [
        'font' => ['name' => 'Calibri', 'size' => 12, 'bold' => false],
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
    $sheet->getStyle('A16:J17')->applyFromArray($headerStyle);

    $proto = (int) $map['prototype_row'];
    $sheet->getStyle('A' . $proto . ':J' . $proto)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_HAIR,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ]);

    rpcse_apply_page_setup($sheet, $map);

    return $ss;
}

function rpcse_apply_page_setup(Worksheet $sheet, array $map): void
{
    $ps = $sheet->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $ps->setPaperSize(RPCSE_PAPER_SIZE);
    $ps->setFitToPage(true);
    $ps->setFitToWidth(1);
    $ps->setFitToHeight(0);
    // One header only: do not reprint Republic / officer / column titles on later pages.
    $ps->setRowsToRepeatAtTop(['', '']);
    if (isset($map['print_title_rows']) && is_string($map['print_title_rows']) && $map['print_title_rows'] !== '') {
        $parts = explode(':', $map['print_title_rows']);
        if (count($parts) === 2) {
            $ps->setRowsToRepeatAtTopByStartAndEnd((int) $parts[0], (int) $parts[1]);
        }
    }

    $m = $sheet->getPageMargins();
    $m->setTop(0.12);
    $m->setBottom(0.04);
    $m->setLeft(0);
    $m->setRight(0);
    $m->setHeader(0);
    $m->setFooter(0);
}

/**
 * Ensure rpcse-template.xlsx exists (generate blank master if the client file is not installed yet).
 */
function rpcse_ensure_template(): string
{
    rpcse_ensure_storage_dirs();
    $path = rpcse_template_path();
    if (is_file($path) && filesize($path) > 0) {
        return $path;
    }
    $ss = rpcse_build_blank_template();
    $writer = new Xlsx($ss);
    $writer->save($path);
    $ss->disconnectWorksheets();

    return $path;
}

/**
 * Group SE items by office, then by item_group (category header).
 *
 * @param array<int, array<string, mixed>> $items
 * @param array<int, array<string, mixed>> $offices keyed by id
 * @return array<int, array{sheet_title:string,office_id:int,office_name:string,officer_name:string,officer_designation:string,items:array}>
 */
function rpcse_buckets_by_office(array $items, array $offices, int $forceOfficeId = 0): array
{
    $byOffice = [];
    foreach ($items as $item) {
        $oid = (int) ($item['office_id'] ?? 0);
        if ($forceOfficeId > 0 && $oid !== $forceOfficeId) {
            continue;
        }
        if ($oid <= 0) {
            continue;
        }
        if (!isset($byOffice[$oid])) {
            $byOffice[$oid] = [];
        }
        $byOffice[$oid][] = $item;
    }

    if ($forceOfficeId > 0 && !isset($byOffice[$forceOfficeId])) {
        $byOffice[$forceOfficeId] = [];
    }

    // All-offices export: every department tab comes from live inventory, not leftover template rows.
    if ($forceOfficeId <= 0) {
        foreach ($offices as $oid => $info) {
            $oid = (int) $oid;
            if ($oid <= 0 || isset($byOffice[$oid])) {
                continue;
            }
            $byOffice[$oid] = [];
        }
    }

    $buckets = [];
    foreach ($byOffice as $oid => $officeItems) {
        $info = $offices[$oid] ?? [];
        $code = trim((string) ($info['code'] ?? ($officeItems[0]['office_code'] ?? '')));
        $name = trim((string) ($info['name'] ?? ($officeItems[0]['office_name'] ?? 'Office')));
        $title = $code !== '' ? $code : $name;
        $acc = physical_count_accountable_for_office($info + [
            'code' => $code,
            'office_name' => $name,
        ]);
        $buckets[] = [
            'sheet_title' => $title,
            'office_id' => $oid,
            'office_name' => $name,
            'officer_name' => $acc['name'],
            'officer_designation' => $acc['designation'],
            'items' => $officeItems,
        ];
    }

    usort($buckets, static function ($a, $b) {
        return strnatcasecmp($a['sheet_title'], $b['sheet_title']);
    });

    return $buckets;
}

/**
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array{title:string,items:array}>
 */
function rpcse_group_categories(array $items): array
{
    $groups = [];
    $order = [];
    foreach ($items as $item) {
        $g = trim((string) ($item['item_group'] ?? ''));
        if ($g === '') {
            $g = 'ITEMS';
        }
        $key = function_exists('mb_strtoupper') ? mb_strtoupper($g, 'UTF-8') : strtoupper($g);
        if (!isset($groups[$key])) {
            $groups[$key] = ['title' => $g, 'items' => []];
            $order[] = $key;
        }
        $groups[$key]['items'][] = $item;
    }
    foreach ($groups as &$group) {
        usort($group['items'], static function ($a, $b) {
            $oa = (int) ($a['sort_order'] ?? 0);
            $ob = (int) ($b['sort_order'] ?? 0);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });
    }
    unset($group);

    usort($order, static function ($ka, $kb) use ($groups) {
        $rank = static function (array $bucket): int {
            $min = PHP_INT_MAX;
            foreach ($bucket['items'] as $it) {
                $n = physical_count_article_lead_index($it['article'] ?? '');
                if ($n !== null && $n < $min) {
                    $min = $n;
                }
            }

            return $min;
        };
        $ra = $rank($groups[$ka]);
        $rb = $rank($groups[$kb]);
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return strnatcasecmp((string) $groups[$ka]['title'], (string) $groups[$kb]['title']);
    });

    $out = [];
    foreach ($order as $k) {
        $out[] = $groups[$k];
    }

    return $out;
}

function rpcse_unique_sheet_title(Spreadsheet $ss, string $wanted): string
{
    $base = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $wanted) ?? 'Office';
    $base = trim($base);
    if ($base === '') {
        $base = 'Office';
    }
    $base = function_exists('mb_substr') ? mb_substr($base, 0, 31, 'UTF-8') : substr($base, 0, 31);
    $title = $base;
    $n = 2;
    while ($ss->getSheetByName($title) !== null) {
        $suffix = '-' . $n;
        $max = 31 - strlen($suffix);
        $title = (function_exists('mb_substr') ? mb_substr($base, 0, $max, 'UTF-8') : substr($base, 0, $max)) . $suffix;
        $n++;
    }

    return $title;
}

function rpcse_hair_border(): array
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

function rpcse_ink_black(): array
{
    return ['rgb' => '000000'];
}

function rpcse_ink_red(): array
{
    return ['rgb' => 'FF0000'];
}

function rpcse_reset_row_style(Worksheet $sheet, int $row, bool $withGrid): void
{
    $style = [
        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'italic' => false, 'color' => rpcse_ink_black()],
        'fill' => ['fillType' => Fill::FILL_NONE],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_GENERAL,
            'vertical' => Alignment::VERTICAL_BOTTOM,
            'wrapText' => false,
        ],
    ];
    if ($withGrid) {
        $style['borders'] = rpcse_hair_border()['borders'];
    } else {
        $style['borders'] = [
            'allBorders' => [
                'borderStyle' => Border::BORDER_NONE,
                'color' => ['rgb' => '000000'],
            ],
        ];
    }
    $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray($style);
}

function rpcse_clone_row_style(Worksheet $sheet, int $from, int $to): void
{
    $sheet->duplicateStyle($sheet->getStyle('A' . $from . ':J' . $from), 'A' . $to . ':J' . $to);
    $h = $sheet->getRowDimension($from)->getRowHeight();
    if ($h > 0) {
        $sheet->getRowDimension($to)->setRowHeight($h);
    }
}

function rpcse_unmerge_from_row(Worksheet $sheet, int $startRow): void
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

function rpcse_merge(Worksheet $sheet, string $range): void
{
    foreach ($sheet->getMergeCells() as $existing) {
        if (strcasecmp((string) $existing, $range) === 0) {
            return;
        }
    }
    $sheet->mergeCells($range);
}

function rpcse_clear_from_row(Worksheet $sheet, int $startRow): void
{
    rpcse_unmerge_from_row($sheet, $startRow);
    $highest = (int) $sheet->getHighestRow();
    if ($highest < $startRow) {
        return;
    }
    for ($r = $startRow; $r <= $highest; $r++) {
        foreach (range('A', 'J') as $col) {
            $sheet->setCellValue($col . $r, null);
        }
        rpcse_reset_row_style($sheet, $r, false);
        $sheet->getRowDimension($r)->setRowHeight(17.25);
    }
}

function rpcse_write_header(Worksheet $sheet, array $map, array $meta, array $office): void
{
    $h = $map['header'];
    $st = $map['static'];

    $asAt = trim((string) ($meta['as_of'] ?? ''));
    if ($asAt !== '' && stripos($asAt, 'as at') !== 0 && stripos($asAt, 'as of') !== 0) {
        $asAt = 'As at ' . $asAt;
    }
    if ($asAt === '') {
        $asAt = 'As at ' . date('F j, Y');
    }
    $sheet->setCellValue($h['as_at'], $asAt);

    $type = trim((string) ($meta['type_label'] ?? ''));
    if ($type === '') {
        $type = $st['type_label'];
    }
    $sheet->setCellValue($h['type_label'], $type);

    $fund = trim((string) ($meta['fund_cluster'] ?? ''));
    $sheet->setCellValue($h['fund_cluster_value'], $fund);

    $officer = trim((string) ($office['officer_name'] ?? ''));
    if ($officer === '') {
        $officer = trim((string) ($meta['officer_name'] ?? ''));
    }
    $desig = trim((string) ($office['officer_designation'] ?? ''));
    if ($desig === '') {
        $desig = trim((string) ($meta['officer_designation'] ?? ''));
    }
    $entity = trim((string) ($office['office_name'] ?? ''));
    if ($entity === '') {
        $entity = trim((string) ($meta['lgu_name'] ?? ''));
    }

    $sheet->setCellValue($h['officer_name'], $officer);
    $sheet->setCellValue($h['officer_designation'], $desig);
    $sheet->setCellValue($h['entity_name'], $entity);

    $assumption = trim((string) ($meta['assumption_date'] ?? ''));
    if ($assumption === '') {
        $assumption = preg_replace('/^(As at|As of)\s+/i', '', $asAt) ?? $asAt;
    }
    $sheet->setCellValue($h['assumption_date'], $assumption);
}

function rpcse_write_signature(Worksheet $sheet, int $startRow, array $map, array $meta, array $office): void
{
    $st = $map['static'];
    $r = $startRow;

    $sheet->setCellValue('A' . $r, $st['prepared_by']);
    $sheet->setCellValue('C' . $r, $st['certified_by']);
    $sheet->setCellValue('F' . $r, $st['approved_by']);
    rpcse_merge($sheet, 'G' . $r . ':J' . $r);
    $sheet->getStyle('A' . $r . ':J' . $r)->applyFromArray([
        'font' => ['name' => 'Calibri', 'size' => 12, 'bold' => false, 'color' => rpcse_ink_black()],
        'alignment' => ['vertical' => Alignment::VERTICAL_BOTTOM],
    ]);
    $sheet->getRowDimension($r)->setRowHeight(18.75);

    $r += 2;
    $prepared = trim((string) ($meta['prepared_name'] ?? ''));
    if ($prepared === '') {
        $prepared = defined('RPCPPE_ACCOUNTABLE_OFFICER') ? (string) RPCPPE_ACCOUNTABLE_OFFICER : '';
    }
    $preparedDesig = trim((string) ($meta['prepared_designation'] ?? ''));
    if ($preparedDesig === '') {
        $preparedDesig = defined('RPCPPE_ACCOUNTABLE_DESIGNATION') ? (string) RPCPPE_ACCOUNTABLE_DESIGNATION : '';
    }
    $certified = trim((string) ($office['officer_name'] ?? ''));
    if ($certified === '') {
        $certified = trim((string) ($meta['officer_name'] ?? $prepared));
    }
    $certifiedDesig = trim((string) ($office['officer_designation'] ?? ''));
    if ($certifiedDesig === '') {
        $certifiedDesig = trim((string) ($meta['officer_designation'] ?? ''));
    }
    $approver = trim((string) ($meta['approver_name'] ?? ''));
    $approverDesig = trim((string) ($meta['approver_designation'] ?? ''));

    rpcse_merge($sheet, 'C' . $r . ':E' . $r);
    rpcse_merge($sheet, 'G' . $r . ':J' . $r);
    $sheet->setCellValue('B' . $r, $prepared);
    $sheet->setCellValue('C' . $r, $certified);
    $sheet->setCellValue('G' . $r, $approver);
    $sheet->getStyle('A' . $r . ':J' . $r)->applyFromArray([
        'font' => ['name' => 'Calibri', 'size' => 14, 'bold' => true, 'color' => rpcse_ink_black()],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension($r)->setRowHeight(18.75);

    $r++;
    rpcse_merge($sheet, 'C' . $r . ':E' . $r);
    rpcse_merge($sheet, 'G' . $r . ':J' . $r);
    $sheet->setCellValue('B' . $r, $preparedDesig);
    $sheet->setCellValue('C' . $r, $certifiedDesig);
    $sheet->setCellValue('G' . $r, $approverDesig);
    $sheet->getStyle('A' . $r . ':J' . $r)->applyFromArray([
        'font' => ['name' => 'Calibri', 'size' => 11, 'bold' => false, 'color' => rpcse_ink_black()],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    $r++;
    rpcse_merge($sheet, 'C' . $r . ':E' . $r);
    rpcse_merge($sheet, 'G' . $r . ':J' . $r);
    $sheet->setCellValue('B' . $r, $st['sig_prepared']);
    $sheet->setCellValue('G' . $r, $st['sig_approved']);
    $sheet->getStyle('B' . $r)->applyFromArray([
        'font' => ['name' => 'Calibri', 'size' => 8, 'bold' => false, 'color' => rpcse_ink_black()],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ]);
    $sheet->getStyle('G' . $r)->applyFromArray([
        'font' => ['name' => 'Calibri', 'size' => 10, 'bold' => false, 'color' => rpcse_ink_black()],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ]);
    $sheet->getRowDimension($r)->setRowHeight(27);

    $r++;
    $sheet->setCellValue('A' . $r, $st['date_label']);
    $sheet->setCellValue('F' . $r, $st['date_label']);
    $sheet->getStyle('A' . $r)->applyFromArray([
        'font' => ['name' => 'Calibri', 'size' => 12, 'bold' => false, 'color' => rpcse_ink_black()],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_BOTTOM],
    ]);
    $sheet->getStyle('F' . $r)->applyFromArray([
        'font' => ['name' => 'Calibri', 'size' => 12, 'bold' => false, 'color' => rpcse_ink_black()],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_BOTTOM],
    ]);
}

function rpcse_fill_office_sheet(Worksheet $sheet, array $office, array $meta): void
{
    $map = rpcse_cell_map();
    $start = (int) $map['data_start_row'];

    rpcse_apply_page_setup($sheet, $map);
    rpcse_write_header($sheet, $map, $meta, $office);
    rpcse_clear_from_row($sheet, $start);

    $row = $start;
    $categories = rpcse_group_categories($office['items'] ?? []);

    foreach ($categories as $cat) {
        $catRow = $row;
        $title = function_exists('mb_strtoupper')
            ? mb_strtoupper((string) $cat['title'], 'UTF-8')
            : strtoupper((string) $cat['title']);
        rpcse_reset_row_style($sheet, $row, true);
        $sheet->setCellValue('A' . $row, $title);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => rpcse_ink_red()],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;

        $firstItem = $row;
        $lastArticle = '';
        foreach ($cat['items'] as $item) {
            rpcse_reset_row_style($sheet, $row, true);

            $article = trim((string) ($item['article'] ?? ''));
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
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($map['number_format']);
            if ($qc !== 0) {
                $sheet->setCellValue('F' . $row, $qc);
            }
            $sheet->setCellValue('G' . $row, $qp);
            if ($qp !== $qc) {
                $sheet->setCellValue('H' . $row, '=G' . $row . '-F' . $row);
                $sheet->setCellValue('I' . $row, '=H' . $row . '*E' . $row);
                $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode($map['number_format']);
            }
            $sheet->setCellValue('J' . $row, $remarks);

            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'color' => rpcse_ink_black()],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_GENERAL, 'vertical' => Alignment::VERTICAL_BOTTOM],
            ]);
            $wrap = (function_exists('mb_strlen') ? mb_strlen($desc, 'UTF-8') : strlen($desc)) > 55;
            $sheet->getStyle('B' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 9, 'bold' => false, 'color' => rpcse_ink_black()],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_BOTTOM, 'wrapText' => $wrap],
            ]);
            $sheet->getStyle('C' . $row)->applyFromArray([
                'font' => ['name' => 'Calibri', 'size' => 11, 'bold' => false, 'color' => rpcse_ink_black()],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle('D' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'color' => rpcse_ink_black()],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_BOTTOM],
            ]);
            $sheet->getStyle('E' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'color' => rpcse_ink_black()],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_GENERAL, 'vertical' => Alignment::VERTICAL_BOTTOM],
            ]);
            $sheet->getStyle('F' . $row . ':G' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'color' => rpcse_ink_black()],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle('H' . $row . ':J' . $row)->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'bold' => false, 'color' => rpcse_ink_black()],
            ]);

            $descLen = function_exists('mb_strlen') ? mb_strlen($desc, 'UTF-8') : strlen($desc);
            if ($descLen > 80) {
                $sheet->getRowDimension($row)->setRowHeight(max(28, (int) ceil($descLen / 55) * 14));
            } else {
                $sheet->getRowDimension($row)->setRowHeight(17.25);
            }

            $row++;
        }

        $lastItem = $row - 1;
        if ($lastItem >= $firstItem) {
            $sheet->setCellValue('E' . $catRow, '=SUM(E' . $firstItem . ':E' . $lastItem . ')');
        } else {
            $sheet->setCellValue('E' . $catRow, 0);
        }
        $sheet->getStyle('E' . $catRow)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11, 'bold' => false, 'color' => rpcse_ink_red()],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('E' . $catRow)->getNumberFormat()->setFormatCode($map['number_format']);
    }

    $sheet->getRowDimension($row)->setRowHeight(7.5);
    $sigRow = $row + 1;
    rpcse_write_signature($sheet, $sigRow, $map, $meta, $office);
}

/**
 * @param array<int, array<string, mixed>> $items
 * @param array<string, mixed> $meta
 * @param array<int, array<string, mixed>> $offices keyed by id
 */
function rpcse_sheet_alias_map(): array
{
    return [
        'ACCT' => 'ACCOUNTING',
        'ACCOUNTING' => 'ACCOUNTING',
        'HLTH' => 'RHU',
        'HEALTH' => 'RHU',
        'RHU' => 'RHU',
        'SWDO' => 'MSWDO',
        'MSWDO' => 'MSWDO',
        'PLAN' => 'MPDC',
        'MPDC' => 'MPDC',
        'ASSO' => 'ASSESSOR',
        'ASSESSOR' => 'ASSESSOR',
        'AGRI' => 'DA',
        'DA' => 'DA',
        'MAYOR' => 'OM',
        'OM' => 'OM',
        'ADMIN' => 'ADMIN',
        'VMAY' => 'SB',
        'SB' => 'SB',
        'TRES' => 'TREASURY',
        'TREASURY' => 'TREASURY',
        'GSO' => 'GSO',
        'HRMO' => 'HRMO',
        'ENGR' => 'ENGR',
        'PESO' => 'PESO',
        'PDAO' => 'PDAO',
        'SWM' => 'SWM',
        'MCR' => 'MCR',
        'SENIOR' => 'SENIOR',
        'LICENSE' => 'LICENSE',
        'MARKET' => 'MARKET',
        'BUDGET' => 'BUDGET',
        'BUDG' => 'BUDGET',
        'LWS' => 'LWS',
        'DILG' => 'DILG',
        'COA' => 'COA',
        'MDRRMO' => 'MDRRMO',
        'DEPED' => 'DEPED',
        'ENRO' => 'ENRO',
    ];
}

function rpcse_norm_sheet_key(string $s): string
{
    $s = strtoupper(trim($s));
    $s = preg_replace('/[^A-Z0-9]+/', '', $s) ?? $s;

    return $s;
}

/**
 * Labels that can identify an office tab (code, name, and official-template aliases).
 *
 * @return array<int, string>
 */
function rpcse_office_sheet_labels(array $office): array
{
    $aliases = rpcse_sheet_alias_map();
    $code = strtoupper(trim((string) ($office['code'] ?? $office['sheet_title'] ?? '')));
    $name = trim((string) ($office['office_name'] ?? $office['name'] ?? ''));
    $canonical = $code !== '' && isset($aliases[$code]) ? $aliases[$code] : $code;
    $wanted = [];
    foreach ([$code, $canonical, $name] as $c) {
        $c = trim((string) $c);
        if ($c !== '') {
            $wanted[] = $c;
        }
    }
    if ($canonical !== '') {
        foreach ($aliases as $from => $to) {
            if (strcasecmp((string) $to, $canonical) === 0) {
                $wanted[] = (string) $from;
                $wanted[] = (string) $to;
            }
        }
    }

    $out = [];
    $seen = [];
    foreach ($wanted as $label) {
        $key = strtoupper($label);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $label;
    }

    return $out;
}

/**
 * Find the client's existing tab for an office. Never invent a parallel "GSO-2" tab.
 */
function rpcse_find_office_sheet(Spreadsheet $ss, array $office): ?Worksheet
{
    $wanted = rpcse_office_sheet_labels($office);

    foreach ($wanted as $label) {
        $hit = $ss->getSheetByName($label);
        if ($hit) {
            return $hit;
        }
        foreach ($ss->getAllSheets() as $sh) {
            if (strcasecmp($sh->getTitle(), $label) === 0) {
                return $sh;
            }
        }
    }

    $keys = [];
    foreach ($wanted as $label) {
        $k = rpcse_norm_sheet_key($label);
        if ($k !== '') {
            $keys[] = $k;
        }
    }
    foreach ($ss->getAllSheets() as $sh) {
        $t = rpcse_norm_sheet_key($sh->getTitle());
        if ($t !== '' && in_array($t, $keys, true)) {
            return $sh;
        }
    }

    return null;
}

/**
 * Drop leftover official-template tabs that were never filled from live inventory.
 *
 * @param array<int, Worksheet> $keep
 */
function rpcse_keep_only_sheets(Spreadsheet $ss, array $keep): void
{
    if ($keep === []) {
        return;
    }
    $keepIds = [];
    foreach ($keep as $sh) {
        if ($sh instanceof Worksheet) {
            $keepIds[spl_object_id($sh)] = true;
        }
    }
    $indexes = [];
    foreach ($ss->getAllSheets() as $i => $sh) {
        if (!isset($keepIds[spl_object_id($sh)])) {
            $indexes[] = $i;
        }
    }
    rsort($indexes, SORT_NUMERIC);
    foreach ($indexes as $i) {
        if ($ss->getSheetCount() <= 1) {
            break;
        }
        $ss->removeSheetByIndex($i);
    }
}

function rpcse_stamp_as_of_date(Worksheet $sheet, array $map, string $asAt): void
{
    $title = (string) $sheet->getCell($map['header']['title'])->getValue();
    if (stripos($title, 'SEMI-EXPENDABLE') === false && stripos($title, 'PHYSICAL COUNT') === false) {
        return;
    }
    $sheet->setCellValue($map['header']['as_at'], $asAt);
}

function rpcse_save_workbook(Spreadsheet $spreadsheet, string $path): void
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

function rpcse_save_into_template_file(Spreadsheet $spreadsheet): void
{
    rpcse_save_workbook($spreadsheet, rpcse_template_path());
}

function rpcse_save_preview_file(Spreadsheet $spreadsheet): void
{
    require_once __DIR__ . '/excel_export_lock.php';
    lgue_lock_export_spreadsheet($spreadsheet);
    rpcse_save_workbook($spreadsheet, rpcse_preview_path());
}

function rpcse_find_excel_exe(): ?string
{
    $pf = getenv('ProgramFiles') ?: 'C:\\Program Files';
    $pf86 = getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)';
    $candidates = [];
    foreach (['Office16', 'Office15', 'Office14'] as $ver) {
        $candidates[] = $pf . '\\Microsoft Office\\root\\' . $ver . '\\EXCEL.EXE';
        $candidates[] = $pf . '\\Microsoft Office\\' . $ver . '\\EXCEL.EXE';
        $candidates[] = $pf86 . '\\Microsoft Office\\root\\' . $ver . '\\EXCEL.EXE';
        $candidates[] = $pf86 . '\\Microsoft Office\\' . $ver . '\\EXCEL.EXE';
    }

    foreach ($candidates as $exe) {
        if (is_file($exe)) {
            return $exe;
        }
    }

    $keys = [
        'HKLM\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\App Paths\\excel.exe',
        'HKLM\\SOFTWARE\\WOW6432Node\\Microsoft\\Windows\\CurrentVersion\\App Paths\\excel.exe',
    ];
    foreach ($keys as $key) {
        $out = [];
        @exec('reg query ' . escapeshellarg($key) . ' /ve', $out);
        foreach ($out as $line) {
            if (!preg_match('/REG_(?:SZ|EXPAND_SZ)\s+(.+)$/i', $line, $m)) {
                continue;
            }
            $exe = trim($m[1], " \t\"'");
            $exe = str_ireplace('%ProgramFiles%', $pf, $exe);
            $exe = str_ireplace('%ProgramFiles(x86)%', $pf86, $exe);
            if (is_file($exe)) {
                return $exe;
            }
        }
    }

    return null;
}

function rpcse_ms_excel_url(string $path): string
{
    $real = realpath($path) ?: $path;
    $real = str_replace('\\', '/', $real);
    $parts = explode('/', $real);
    $encoded = [];
    foreach ($parts as $i => $part) {
        $encoded[] = $i === 0 ? $part : rawurlencode($part);
    }

    return 'ms-excel:ofe|u|file:///' . implode('/', $encoded);
}

/**
 * Open a .xlsx in Microsoft Excel on this Windows PC (XAMPP/localhost).
 * Uses EXCEL.EXE directly so Chrome/.xlsx file association cannot steal the file.
 *
 * @return array{ok:bool,message:string,method?:string,protocol_url?:string}
 */
function rpcse_open_in_excel(string $path): array
{
    $real = realpath($path);
    if ($real === false || !is_file($real)) {
        return ['ok' => false, 'message' => 'Filled Excel file was not found on disk'];
    }

    $protocol = rpcse_ms_excel_url($real);
    if (stripos(PHP_OS, 'WIN') !== 0) {
        return [
            'ok' => false,
            'message' => 'Microsoft Excel can only be launched from this Windows computer',
            'protocol_url' => $protocol,
        ];
    }

    $phpUser = (string) getenv('USERNAME');
    if (preg_match('/^(SYSTEM|LOCAL SERVICE|NETWORK SERVICE)$/i', $phpUser)) {
        return [
            'ok' => false,
            'message' => 'Allow this site to open Microsoft Excel if the browser asks',
            'protocol_url' => $protocol,
        ];
    }

    if (class_exists('COM')) {
        try {
            $excel = new COM('Excel.Application');
            $excel->Visible = true;
            $excel->UserControl = true;
            $excel->Workbooks->Open($real);

            return [
                'ok' => true,
                'message' => 'Opened in Microsoft Excel',
                'method' => 'com',
                'protocol_url' => $protocol,
            ];
        } catch (Throwable $e) {
            // Fall through to EXCEL.EXE.
        }
    }

    $excelExe = rpcse_find_excel_exe();
    if ($excelExe) {
        $cmd = 'cmd /c start "" ' . escapeshellarg($excelExe) . ' ' . escapeshellarg($real);
        pclose(popen($cmd, 'r'));

        return [
            'ok' => true,
            'message' => 'Opened in Microsoft Excel',
            'method' => 'excel-exe',
            'protocol_url' => $protocol,
        ];
    }

    return [
        'ok' => false,
        'message' => 'Microsoft Excel was not found on this PC. If the browser asks, allow it to open Excel.',
        'protocol_url' => $protocol,
    ];
}

/**
 * Fill the client's rpcse-template.xlsx in place: same tabs, same layout.
 * System as-of date and inventory are typed into the existing office sheets.
 */
function rpcse_fill_template(array $items, array $meta, array $offices, int $officeId = 0): Spreadsheet
{
    $templatePath = rpcse_ensure_template();
    if (!is_file($templatePath)) {
        throw new RuntimeException('RPCSE template is missing: ' . $templatePath);
    }

    $ss = IOFactory::load($templatePath);
    $map = rpcse_cell_map();
    $master = $ss->getSheetByName($map['master_sheet']);

    $asAt = trim((string) ($meta['as_of'] ?? ''));
    if ($asAt !== '' && stripos($asAt, 'as at') !== 0 && stripos($asAt, 'as of') !== 0) {
        $asAt = 'As at ' . $asAt;
    }
    if ($asAt === '') {
        $asAt = 'As at December 31, ' . date('Y');
        $meta['as_of'] = $asAt;
    }

    // Stamp the system date on every official form tab in this workbook.
    foreach ($ss->getAllSheets() as $sh) {
        rpcse_stamp_as_of_date($sh, $map, $asAt);
    }

    $keepSheet = null;
    $filled = [];
    $buckets = rpcse_buckets_by_office($items, $offices, $officeId);
    foreach ($buckets as $bucket) {
        $sheet = rpcse_find_office_sheet($ss, $bucket);
        if ($sheet === null && $master !== null) {
            $copy = $master->copy();
            $title = rpcse_unique_sheet_title($ss, (string) $bucket['sheet_title']);
            $copy->setTitle($title);
            $ss->addSheet($copy);
            $sheet = $copy;
        }
        if ($sheet === null) {
            continue;
        }
        rpcse_fill_office_sheet($sheet, $bucket, $meta);
        $filled[] = $sheet;
        if ($officeId > 0 && (int) $bucket['office_id'] === $officeId) {
            $keepSheet = $sheet;
        }
    }

    if ($officeId > 0 && isset($keepSheet) && $keepSheet instanceof Worksheet) {
        rpcse_show_only_sheet($ss, $keepSheet);
    } else {
        rpcse_keep_only_sheets($ss, $filled);
        $ss->setActiveSheetIndex(0);
    }

    return $ss;
}

/**
 * Hide every tab except the chosen office so Excel opens on that form only.
 */
function rpcse_show_only_sheet(Spreadsheet $ss, Worksheet $keep): void
{
    $keepIndex = 0;
    foreach ($ss->getAllSheets() as $i => $sh) {
        if ($sh === $keep) {
            $sh->setSheetState(Worksheet::SHEETSTATE_VISIBLE);
            $keepIndex = $i;
            continue;
        }
        $sh->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }
    $ss->setActiveSheetIndex($keepIndex);
}

function rpcse_load_offices_map(PDO $conn): array
{
    return physical_count_offices_map($conn);
}
