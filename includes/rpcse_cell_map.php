<?php
/**
 * Shared RPCSE (Semi-Expendable Physical Count) cell map.
 * Matches the client's official Annex A Excel template.
 * Used by the template filler, tests, and the README mapping note.
 */

if (!defined('RPCSE_TEMPLATE_FILENAME')) {
    define('RPCSE_TEMPLATE_FILENAME', 'rpcse-template.xlsx');
}

if (!defined('RPCSE_MASTER_SHEET')) {
    define('RPCSE_MASTER_SHEET', 'Sheet');
}

/** Excel paperSize 14 = Folio 8.5×13 (PH "legal"), not US Legal (5). */
const RPCSE_PAPER_SIZE = 14;

function rpcse_template_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'templates' . DIRECTORY_SEPARATOR . 'rpcse';
}

function rpcse_template_path(): string
{
    return rpcse_template_dir() . DIRECTORY_SEPARATOR . RPCSE_TEMPLATE_FILENAME;
}

function rpcse_preview_path(): string
{
    return rpcse_template_dir() . DIRECTORY_SEPARATOR . 'rpcse-preview.xlsx';
}

function rpcse_archive_dir(): string
{
    return rpcse_template_dir() . DIRECTORY_SEPARATOR . 'archive';
}

/**
 * Canonical cell map for the client's RPCSE workbook (1-based rows).
 */
function rpcse_cell_map(): array
{
    return [
        'master_sheet' => RPCSE_MASTER_SHEET,
        'header' => [
            'republic' => 'A1',
            'province' => 'A2',
            'municipality' => 'A3',
            'annex' => 'J4',
            'title' => 'A5',
            'type_label' => 'A6',
            'type_caption' => 'A7',
            'as_at' => 'A8',
            'fund_cluster_label' => 'A10',
            'fund_cluster_value' => 'B10',
            'for_which' => 'A11',
            'officer_name' => 'B11',
            'officer_designation' => 'C11',
            'entity_name' => 'E11',
            'is_accountable' => 'H11',
            'cap_officer' => 'B12',
            'cap_designation' => 'C12',
            'cap_entity' => 'E12',
            'accountability_on' => 'A13',
            'assumption_date' => 'B13',
            'cap_assumption' => 'B14',
        ],
        'merges' => [
            'A1:J1', 'A2:J2', 'A3:J3',
            'A5:J5', 'A6:J6', 'A7:J7', 'A8:J8',
            'C11:D11', 'E11:G11',
            'C12:D12', 'E12:G12',
            'A16:A17', 'B16:B17', 'C16:C17', 'D16:D17', 'E16:E17',
            'H16:I16', 'J16:J17',
        ],
        'table_header_row' => 16,
        'table_subheader_row' => 17,
        'data_start_row' => 18,
        'prototype_row' => 18,
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
            'A' => 19.9,
            'B' => 44.9,
            'C' => 28.3,
            'D' => 11.9,
            'E' => 14.4,
            'F' => 12.7,
            'G' => 11.43,
            'H' => 14.0,
            'I' => 14.4,
            'J' => 27.3,
        ],
        'row_heights' => [
            16 => 42.0,
            17 => 14.25,
        ],
        'headers_row16' => [
            'A' => 'Article / Item',
            'B' => 'Description',
            'C' => 'Semi-expendable Property No.',
            'D' => 'Unit of Measure',
            'E' => 'Unit Value',
            'F' => 'Balance Per Card',
            'G' => 'On Hand Per Count',
            'H' => 'Shortage/Overage',
            'J' => 'Remarks',
        ],
        'headers_row17' => [
            'F' => '(Quantity)',
            'G' => '(Quantity)',
            'H' => 'Quantity',
            'I' => 'Value',
        ],
        'static' => [
            'republic' => 'REPUBLIC OF THE PHILIPPINES',
            'province' => 'PROVINCE OF LAGUNA',
            'municipality' => 'MUNICIPALITY OF LUMBAN',
            'annex' => 'Annex A',
            'title' => 'REPORT ON THE PHYSICAL COUNT OF SEMI-EXPENDABLE PROPERTY',
            'type_label' => 'Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment',
            'type_caption' => '(Type of Semi-expandable Property)',
            'fund_cluster' => 'Fund Cluster:',
            'for_which' => 'For which',
            'is_accountable' => 'is accountable, having assumed such',
            'cap_officer' => '(Name of Accountable Officer)',
            'cap_designation' => '(Official Designation)',
            'cap_entity' => '(Entity Name)',
            'accountability_on' => '          accountability on',
            'cap_assumption' => '(Date of Assumption)',
            'prepared_by' => 'Prepared by:',
            'certified_by' => 'Certified Correct by:',
            'approved_by' => 'Approved by:',
            'sig_prepared' => 'Signature over Printed Name of Inventory Committee Chair and Members',
            'sig_certified' => 'Signature over Printed Name of Accountable Officer',
            'sig_approved' => 'Signature over Printed Name of Head of Agency/Entity or Authorized Representative',
            'date_label' => 'Date:',
        ],
        'number_format' => '#,##0.00',
        'signature_spacer_rows' => 2,
        'print_title_rows' => '',
    ];
}
