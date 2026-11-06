<?php
/**
 * Physical count report — COA RPCSE / RPCPPE.
 * URLs: /lgu_invent/print/print-physical-count-report.php
 * Query: category=SE|PPE, optional status, office_id, search,
 *        as_of, officer, designation, assumption_date, type_label,
 *        format=html (SE/PPE in-browser official form) | open-excel (SE: fill client template and launch Excel.exe)
 *        | xlsx (SE/PPE download)
 */
ini_set('memory_limit', '512M');
set_time_limit(120);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';

PermissionHelper::require('print');

require_once dirname(__DIR__) . '/includes/physical_count_report_data.php';
require_once dirname(__DIR__) . '/includes/rpcppe_cell_map.php';

$category = isset($_GET['category']) ? strtoupper(trim((string) $_GET['category'])) : '';
if (!in_array($category, ['SE', 'PPE'], true)) {
    http_response_code(400);
    echo 'Specify category=SE or category=PPE';
    exit;
}

$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$office_id = isset($_GET['office_id']) ? (int) $_GET['office_id'] : 0;
$officeIds = physical_count_parse_office_ids($_GET);
$ppeTabs = physical_count_parse_ppe_tabs($_GET);
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'html';

if (!PermissionHelper::isAdmin() && $category === 'SE') {
    $office_id = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : -1;
    $officeIds = $office_id > 0 ? [$office_id] : [];
}

if ($category === 'PPE') {
    $office_id = 0;
    $officeIds = [];
}

$isAllOfficesExport = ($category === 'SE' && $office_id === 0 && $officeIds === []);
$isAllPpeTabsExport = ($category === 'PPE' && $ppeTabs === [] && in_array($format, ['xlsx', 'download', 'open-excel'], true));
// Full live inventory, not the on-screen status/search filter.
if ($isAllOfficesExport || $isAllPpeTabsExport) {
    $status = '';
    $search = '';
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

$items = ($office_id < 0 && $officeIds === [])
    ? []
    : load_physical_count_report_items($conn, $category, $status, $office_id, $search, $officeIds);

/**
 * @param string|null $value
 */
function pc_report_query_text(?string $value): string
{
    return trim(strip_tags((string) $value));
}

$meta = rpcppe_default_meta($category);
if (!empty($_GET['as_of'])) {
    $meta['as_of'] = pc_report_query_text((string) $_GET['as_of']);
}
if (!empty($_GET['officer'])) {
    $meta['officer_name'] = pc_report_query_text((string) $_GET['officer']);
}
if (!empty($_GET['designation'])) {
    $meta['officer_designation'] = pc_report_query_text((string) $_GET['designation']);
}
if (!empty($_GET['assumption_date'])) {
    $meta['assumption_date'] = pc_report_query_text((string) $_GET['assumption_date']);
}
if (!empty($_GET['type_label'])) {
    $meta['type_label'] = pc_report_query_text((string) $_GET['type_label']);
} else {
    $meta['type_label'] = 'Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment';
}
if (!empty($_GET['approver'])) {
    $meta['approver_name'] = pc_report_query_text((string) $_GET['approver']);
}
if (!empty($_GET['approver_designation'])) {
    $meta['approver_designation'] = pc_report_query_text((string) $_GET['approver_designation']);
}
if (!empty($_GET['lgu'])) {
    $meta['lgu_name'] = pc_report_query_text((string) $_GET['lgu']);
} elseif ((count($officeIds) === 1 || $office_id > 0) && !empty($items[0]['office_name'])) {
    $meta['lgu_name'] = trim((string) $items[0]['office_name']);
    if ($category === 'SE' && empty($meta['officer_designation'])) {
        $meta['officer_designation'] = 'Office Head';
    }
}
if (!empty($_GET['prepared'])) {
    $meta['prepared_name'] = pc_report_query_text((string) $_GET['prepared']);
}
if (!empty($_GET['prepared_designation'])) {
    $meta['prepared_designation'] = pc_report_query_text((string) $_GET['prepared_designation']);
}

if ($format === 'html' || $format === '' || $format === 'view') {
    if ($category === 'SE') {
        $officesMap = physical_count_offices_map($conn);
        $targets = $officeIds;
        if ($targets === [] && $office_id > 0) {
            $targets = [$office_id];
        }
        if ($targets === []) {
            $seen = [];
            foreach ($items as $it) {
                $oid = (int) ($it['office_id'] ?? 0);
                if ($oid > 0 && !isset($seen[$oid])) {
                    $seen[$oid] = true;
                    $targets[] = $oid;
                }
            }
        }
        $officeReports = [];
        foreach ($targets as $oid) {
            $oid = (int) $oid;
            $officeItems = [];
            foreach ($items as $it) {
                if ((int) ($it['office_id'] ?? 0) === $oid) {
                    $officeItems[] = $it;
                }
            }
            $info = $officesMap[$oid] ?? [];
            $officeName = trim((string) ($info['name'] ?? ($officeItems[0]['office_name'] ?? 'Office')));
            $acc = physical_count_accountable_for_office($info + [
                'code' => (string) ($info['code'] ?? ($officeItems[0]['office_code'] ?? '')),
                'office_name' => $officeName,
            ]);
            $officer = $acc['name'];
            $desig = $acc['designation'];
            if ($officer === '') {
                $officer = (string) ($meta['officer_name'] ?? '');
            }
            if ($desig === '') {
                $desig = (string) (($meta['officer_designation'] ?? '') !== '' ? $meta['officer_designation'] : 'Office Head');
            }
            $segs = physical_count_report_segments_rpcse($officeItems);
            $officeReports[] = [
                'office_id' => $oid,
                'office_name' => $officeName,
                'officer_name' => $officer,
                'officer_designation' => $desig,
                'segments' => $segs,
                'grand' => physical_count_grand_book_total($officeItems),
                'rows' => physical_count_rpcse_boot_rows($segs, $oid),
            ];
        }
        $segments = $officeReports[0]['segments'] ?? [];
        $grandBookTotal = physical_count_grand_book_total($items);
        require dirname(__DIR__) . '/app/views/reports/rpcse_workbook.php';
        exit;
    }
}

if ($format === 'html' && $category === 'PPE') {
    $targets = $ppeTabs !== [] ? $ppeTabs : rpcppe_data_tabs();
    $byTab = [];
    foreach ($items as $it) {
        $tab = ppe_excel_tab_from_item($it);
        if (!isset($byTab[$tab])) {
            $byTab[$tab] = [];
        }
        $byTab[$tab][] = $it;
    }
    $tabReports = [];
    foreach ($targets as $tab) {
        $tabItems = $byTab[$tab] ?? [];
        $segs = physical_count_report_segments_rpcppe($tabItems);
        $tabReports[] = [
            'tab' => $tab,
            'type_label' => (string) ($meta['type_label'] ?? ''),
            'segments' => $segs,
            'grand' => physical_count_grand_book_total($tabItems),
            'rows' => physical_count_rpcse_boot_rows($segs, 0),
        ];
    }
    $segments = $tabReports[0]['segments'] ?? [];
    $grandBookTotal = physical_count_grand_book_total($items);
    require dirname(__DIR__) . '/app/views/reports/rpcppe_workbook.php';
    exit;
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'PhpSpreadsheet is not installed. Run composer install in the project root.';
    exit;
}
require_once $autoload;
require_once dirname(__DIR__) . '/includes/rpcppe_workbook.php';

if ($category === 'SE') {
    require_once dirname(__DIR__) . '/includes/rpcse_template_workbook.php';
    $wantDownload = ($format === 'xlsx' || $format === 'download');
    $wantOpenExcel = !$wantDownload;

    try {
        $officesMap = rpcse_load_offices_map($conn);
        if ($officeIds !== []) {
            $officesMap = array_intersect_key($officesMap, array_flip($officeIds));
        }
        if (!empty($_GET['fund_cluster'])) {
            $meta['fund_cluster'] = pc_report_query_text((string) $_GET['fund_cluster']);
        }
        $fillOfficeId = count($officeIds) === 1 ? (int) $officeIds[0] : (($officeIds !== []) ? 0 : $office_id);
        $spreadsheet = rpcse_fill_template($items, $meta, $officesMap, $fillOfficeId);
        rpcse_save_preview_file($spreadsheet);
    } catch (Throwable $e) {
        http_response_code(500);
        if ($wantOpenExcel) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'message' => 'Could not generate RPCSE workbook from the client template. ' . $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Could not generate RPCSE workbook from the client template.' . "\n\n" . $e->getMessage();
        exit;
    }

    if ($wantOpenExcel) {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        $opened = rpcse_open_in_excel(rpcse_preview_path());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => !empty($opened['ok']),
            'message' => $opened['message'] ?? 'Could not open Microsoft Excel',
            'method' => $opened['method'] ?? null,
            'path' => realpath(rpcse_preview_path()) ?: rpcse_preview_path(),
            'protocol_url' => $opened['protocol_url'] ?? rpcse_ms_excel_url(rpcse_preview_path()),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (count($officeIds) > 1) {
        $officeLabel = count($officeIds) . '-offices';
    } elseif (($office_id > 0 || count($officeIds) === 1) && !empty($items[0]['office_code'])) {
        $officeLabel = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $items[0]['office_code']);
    } else {
        $officeLabel = 'all-offices';
    }
    rpcppe_stream_download($spreadsheet, 'RPCSE-' . $officeLabel . '.xlsx');
}

require_once dirname(__DIR__) . '/includes/rpcppe_template_workbook.php';
try {
    $spreadsheet = rpcppe_fill_template($items, $meta, $ppeTabs);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Could not generate RPCPPE workbook from the official template.' . "\n\n" . $e->getMessage();
    exit;
}

$ppeLabel = count($ppeTabs) === 1
    ? preg_replace('/[^A-Za-z0-9_-]/', '', $ppeTabs[0])
    : (count($ppeTabs) > 1 ? count($ppeTabs) . '-tabs' : 'all-tabs');
rpcppe_stream_download($spreadsheet, 'RPCPPE-' . $ppeLabel . '.xlsx');
