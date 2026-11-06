<?php
/**
 * Cell map for the official Lumban PAR Excel
 * (storage/templates/par/par-template.xlsx).
 */
function par_cell_map(): array
{
    return [
        'sheet' => '(1)',
        'department' => 'C7',
        'par_no' => 'H7',
        'par_date' => 'K7',
        'section' => 'C8',
        'po_no' => 'H8',
        'po_date' => 'K8',
        'item_first_row' => 11,
        'item_last_row' => 20,
        'total_formula' => 'J21',
        'received_name' => 'A25',
        'administered_name' => 'H25',
        'administered_title' => 'H26',
        'issued_name' => 'A31',
        'issued_title' => 'A32',
        'approved_name' => 'H31',
        'approved_title' => 'H32',
    ];
}
