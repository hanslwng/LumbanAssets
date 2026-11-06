<?php
/**
 * Shared RPCPPE (Report on the Physical Count of PPE) cell map & style constants.
 * Used by the Excel writer and the re-import parser — keep coordinates in one place.
 *
 * Spec source: official Lumban GSO workbook
 * `PPE-update-to-OFFICIAL FORMAT TEMPALTE.xlsx` (storage/templates/rpcppe/rpcppe-template.xlsx).
 */

if (!defined('RPCPPE_COLS')) {
    define('RPCPPE_COLS', 'J');
}

if (!defined('RPCPPE_TEMPLATE_FILENAME')) {
    define('RPCPPE_TEMPLATE_FILENAME', 'rpcppe-template.xlsx');
}

/** Excel paperSize 14 = Folio 8.5×13 (PH "legal"), not US Legal (5). */
const RPCPPE_PAPER_SIZE = 14;

function rpcppe_template_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'templates' . DIRECTORY_SEPARATOR . 'rpcppe';
}

function rpcppe_template_path(): string
{
    return rpcppe_template_dir() . DIRECTORY_SEPARATOR . RPCPPE_TEMPLATE_FILENAME;
}

function rpcppe_preview_path(): string
{
    return rpcppe_template_dir() . DIRECTORY_SEPARATOR . 'rpcppe-preview.xlsx';
}

function rpcppe_archive_dir(): string
{
    return rpcppe_template_dir() . DIRECTORY_SEPARATOR . 'archive';
}

/**
 * Inventory tabs printed by the system. FORM / IIRUP / unused SEF office tab stay hidden.
 *
 * @return array<int, string>
 */
function rpcppe_data_tabs(): array
{
    return [
        'LAND FINAL',
        'SUPPLIES AND EQUIPMENT FINAL',
        'SEF SCHOOL BUILDING',
        'LAND TITLE',
    ];
}

function ppe_excel_tab_from_item(array $item): string
{
    $g = strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($item['item_group'] ?? '')) ?? ''));
    if (str_starts_with($g, 'LAND FINAL')) {
        return 'LAND FINAL';
    }
    if (str_starts_with($g, 'SUPPLIES AND EQUIPMENT')) {
        return 'SUPPLIES AND EQUIPMENT FINAL';
    }
    if (str_starts_with($g, 'SEF') || str_contains($g, ' - SEF') || str_contains($g, 'SEF ·')) {
        return 'SEF SCHOOL BUILDING';
    }
    if ($g === 'LAND TITLE' || str_starts_with($g, 'LAND TITLE')) {
        return 'LAND TITLE';
    }
    $landSections = [
        'ROAD NETWORKS' => true,
        'SCHOOL BUILDINGS' => true,
        'FLOOD CONTROL SYSTEM' => true,
        'BUILDINGS' => true,
        'OTHER LAND IMPROVEMENTS' => true,
        'OTHER INFRASTRUCTURE ASSETS' => true,
        'OTHER STRUCTURES' => true,
        'HOSPITALS AND HEALTH CENTERS' => true,
        'MARKETS' => true,
    ];
    if (isset($landSections[$g])) {
        return 'LAND FINAL';
    }

    return 'SUPPLIES AND EQUIPMENT FINAL';
}

/** Accounting number format from the reference workbook. */
const RPCPPE_NUM_FMT = '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)';

/**
 * Header / table / body layout (1-based Excel rows).
 */
function rpcppe_cell_map(): array
{
    return [
        'title_row' => 1,
        'type_row' => 3,
        'type_caption_row' => 4,
        'as_of_row' => 5,
        'accountable_row' => 7,
        'accountable_caption_row' => 8,
        'assumption_row' => 9,
        'assumption_caption_row' => 10,
        'header' => [
            'title' => 'A1',
            'type_label' => 'A3',
            'type_caption' => 'A4',
            'as_of' => 'A5',
            'officer' => 'B7',
            'designation' => 'C7',
            'lgu' => 'E7',
            'assumption' => 'B9',
        ],
        'table_header_row' => 12,
        'table_subheader_row' => 13,
        'data_start_row' => 14,
        'cols' => [
            'article' => 'A',
            'description' => 'B',
            'property_number' => 'C',
            'uom' => 'D',
            'unit_value' => 'E',
            'qty_card' => 'F',
            'qty_phys' => 'G',
            'var_qty' => 'H',
            'var_val' => 'I',
            'remarks' => 'J',
        ],
        'column_widths' => [
            'A' => 27.71,
            'B' => 53.43,
            'C' => 27.43,
            'D' => 10.57,
            'E' => 16.0,
            'F' => 10.29,
            'G' => 11.43,
            'H' => 10.0,
            'I' => 14.29,
            'J' => 18.29,
        ],
        // Exact header labels from the reference FORM sheet (multi-space padding preserved).
        'headers_row12' => [
            'A' => 'ARTICLE',
            'B' => 'DESCRIPTION',
            'C' => 'PROPERTY NUMBER',
            'D' => 'UNIT OF                     MEASURE',
            'E' => 'UNIT VALUE',
            'F' => 'BALANCE                    PER CARD',
            'G' => 'ON HAND               PER COUNT',
            'H' => 'SHORTAGE/OVERAGE',
            'J' => 'REMARKS',
        ],
        'headers_row13' => [
            'F' => '(Quantity)',
            'G' => '(Quantity)',
            'H' => 'Quantity',
            'I' => 'Value',
        ],
        'static' => [
            'title_ppe' => 'REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT',
            'type_label_ppe' => 'Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment',
            'header_designation_ppe' => 'GSO - Designate',
            'prepared_designation_ppe' => 'GSO - Designate / PRO III',
            'title_se' => 'REPORT ON THE PHYSICAL COUNT OF SEMI-EXPENDABLE PROPERTY',
            'type_caption_ppe' => '(Type of Property, Plant and Equipment)',
            'type_caption_se' => '(Type of Semi-expandable Property)',
            'for_which' => 'For which',
            'is_accountable' => 'is accountable, having assumed such',
            'cap_officer' => '(Name of Accountable Officer)',
            'cap_designation' => '(Official Designation)',
            'cap_lgu' => '(LGU)',
            'accountability_on' => '          accountability on',
            'cap_assumption' => '(Date of Assumption)',
            'prepared_by' => 'Prepared by:',
            'approved_by' => 'Approved by:',
            'sig_left' => 'Signature over Printed Name of Inventory Committee Chair and Members',
            'sig_right' => 'Signature over Printed Name of Head of Agency/Entity or Authorized Representative',
            'date_label' => 'Date:',
            'total_label' => 'TOTAL',
        ],
    ];
}

/**
 * Default report meta (overridable via query params / config constants).
 */
function rpcppe_default_meta(string $category): array
{
    $lgu = defined('RPCPPE_LGU_NAME')
        ? (string) RPCPPE_LGU_NAME
        : (defined('PROC_PRINT_PO_HEADER_TITLE') ? (string) PROC_PRINT_PO_HEADER_TITLE : 'Municipal Government of Lumban, Laguna');
    $officer = defined('RPCPPE_ACCOUNTABLE_OFFICER') ? (string) RPCPPE_ACCOUNTABLE_OFFICER : '';
    $officerDesig = defined('RPCPPE_ACCOUNTABLE_DESIGNATION') ? (string) RPCPPE_ACCOUNTABLE_DESIGNATION : 'GSO - Designate';
    $approver = defined('RPCPPE_APPROVER_NAME') ? (string) RPCPPE_APPROVER_NAME : '';
    $approverDesig = defined('RPCPPE_APPROVER_DESIGNATION') ? (string) RPCPPE_APPROVER_DESIGNATION : 'Municipal Mayor';
    if (function_exists('settingsGet') && function_exists('getDBConnection')) {
        try {
            $db = getDBConnection();
            $lgu = settingsGet($db, 'lgu_name', $lgu) ?: $lgu;
            $officer = settingsGet($db, 'gso_officer', $officer) ?: $officer;
            $officerDesig = settingsGet($db, 'gso_designation', $officerDesig) ?: $officerDesig;
            $approver = settingsGet($db, 'mayor_name', $approver) ?: $approver;
            $approverDesig = settingsGet($db, 'mayor_designation', $approverDesig) ?: $approverDesig;
        } catch (Throwable $e) {
            // Keep config defaults.
        }
    }
    $assumption = defined('RPCPPE_ASSUMPTION_DATE') ? (string) RPCPPE_ASSUMPTION_DATE : '';

    $map = rpcppe_cell_map();
    $isPpe = strtoupper($category) === 'PPE';

    return [
        'category' => $isPpe ? 'PPE' : 'SE',
        'title' => $isPpe ? $map['static']['title_ppe'] : $map['static']['title_se'],
        'type_label' => $isPpe
            ? $map['static']['type_label_ppe']
            : 'Semi-Expendable Property',
        'type_caption' => $isPpe ? $map['static']['type_caption_ppe'] : $map['static']['type_caption_se'],
        'as_of' => 'December 31, ' . date('Y'),
        'officer_name' => $officer,
        'officer_designation' => $isPpe ? $map['static']['header_designation_ppe'] : $officerDesig,
        'lgu_name' => $lgu,
        'assumption_date' => $assumption !== '' ? $assumption : ('December 31, ' . date('Y')),
        'approver_name' => $approver,
        'approver_designation' => $approverDesig,
        'prepared_name' => $officer,
        'prepared_designation' => $isPpe ? $map['static']['prepared_designation_ppe'] : $officerDesig,
        'sheet_title' => $isPpe ? 'RPCPPE' : 'RPCSE',
    ];
}
