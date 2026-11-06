<?php
/**
 * COA physical count report — printable HTML matching official RPCSE / RPCPPE forms.
 * Cells are contenteditable for final edits before Print.
 *
 * @var string $mainTitle
 * @var string $typeSubtitle
 * @var string $typeLabel
 * @var string $entity
 * @var string $asAtDefault
 * @var string $category SE|PPE
 * @var bool   $isPpe
 * @var array  $meta
 * @var array  $segments
 * @var float  $grandBookTotal
 */
if (!isset($mainTitle, $typeSubtitle, $segments, $grandBookTotal)) {
    exit('Invalid report context');
}
$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$isPpe = !empty($isPpe) || (isset($category) && strtoupper((string) $category) === 'PPE');
$meta = isset($meta) && is_array($meta) ? $meta : [];
$typeLabel = isset($typeLabel) ? (string) $typeLabel : (string) ($meta['type_label'] ?? '');
if ($typeLabel === '') {
    $typeLabel = $isPpe
        ? 'Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment'
        : 'Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment';
}
$officerName = (string) ($meta['officer_name'] ?? '');
$officerDesig = (string) ($meta['officer_designation'] ?? ($isPpe ? 'GSO - DESIGNATE' : ''));
$lguName = (string) ($meta['lgu_name'] ?? ($entity ?? 'Municipal Government of Lumban, Laguna'));
$assumptionDate = (string) ($meta['assumption_date'] ?? '');
$asOf = (string) ($meta['as_of'] ?? ($asAtDefault ?? date('F j, Y')));
$approverName = (string) ($meta['approver_name'] ?? '');
$approverDesig = (string) ($meta['approver_designation'] ?? 'Municipal Mayor');
$preparedName = (string) ($meta['prepared_name'] ?? $officerName);
$preparedDesig = (string) ($meta['prepared_designation'] ?? $officerDesig);
$certifiedName = (string) ($meta['certified_name'] ?? '');
$certifiedDesig = (string) ($meta['certified_designation'] ?? '');
$propColLabel = $isPpe ? 'PROPERTY NUMBER' : 'Semi-expendable Property No.';
$asOfPrefix = $isPpe ? 'As of' : 'As at';
$entityCaption = $isPpe ? '(LGU)' : '(Entity Name)';
$annexLabel = $isPpe ? 'Annex 49' : 'Annex A';
$xlsxUrl = 'print-physical-count-report.php?category=' . rawurlencode($isPpe ? 'PPE' : 'SE') . '&format=xlsx';
if (!empty($_GET['status'])) {
    $xlsxUrl .= '&status=' . rawurlencode((string) $_GET['status']);
}
if (!empty($_GET['search'])) {
    $xlsxUrl .= '&search=' . rawurlencode((string) $_GET['search']);
}
if (!empty($_GET['office_id'])) {
    $xlsxUrl .= '&office_id=' . rawurlencode((string) $_GET['office_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $h($isPpe ? 'RPCPPE' : 'RPCSE'); ?> — Physical Count Report</title>
    <style>
        :root {
            --ink: #000;
            --bd: #000;
            --muted: #222;
            --paper: #fff;
            --screen-bg: #c5c9d0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px 12px 40px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: var(--ink);
            background: var(--screen-bg);
            line-height: 1.25;
        }
        .sheet {
            max-width: 1280px;
            margin: 0 auto;
            background: var(--paper);
            padding: 14px 18px 22px;
            border: 1px solid #888;
            box-shadow: 0 4px 18px rgba(0,0,0,.12);
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
            align-items: center;
        }
        .toolbar button, .toolbar a.btn {
            padding: 8px 16px;
            border: 1px solid #1e293b;
            background: #1e3a5f;
            color: #fff;
            cursor: pointer;
            border-radius: 4px;
            font-size: 12.5px;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .toolbar button.secondary, .toolbar a.btn.secondary { background: #475569; }
        .toolbar .hint {
            font-size: 11px;
            color: #334155;
            margin-left: 4px;
        }
        .annex {
            text-align: right;
            font-size: 9pt;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .gov-head {
            text-align: center;
            margin: 0 0 6px;
            line-height: 1.35;
        }
        .gov-head .republic { font-size: 9.5pt; font-weight: 700; letter-spacing: 0.04em; }
        .gov-head .province { font-size: 9.5pt; font-weight: 700; }
        .gov-head .muni { font-size: 10pt; font-weight: 800; text-transform: uppercase; }
        .doc-title {
            text-align: center;
            font-family: "Arial Black", Arial, sans-serif;
            font-weight: 900;
            font-size: 13pt;
            letter-spacing: 0.01em;
            margin: 8px 0 6px;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .type-line {
            text-align: center;
            font-size: 11pt;
            font-weight: 700;
            font-style: italic;
            margin: 0 0 2px;
        }
        .type-caption {
            text-align: center;
            font-size: 9.5pt;
            font-style: italic;
            margin: 0 0 6px;
            color: var(--muted);
        }
        .as-of {
            text-align: center;
            font-size: 10pt;
            font-weight: 700;
            margin: 0 0 12px;
        }
        .meta-row {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 12px 28px;
            margin: 0 0 10px;
            font-size: 10pt;
        }
        .meta-cell { text-align: center; }
        .meta-cell .val {
            font-weight: 700;
            min-height: 1.2em;
            border-bottom: 1px solid #000;
            padding: 2px 6px;
            display: block;
        }
        .meta-cell .cap {
            font-size: 8.5pt;
            font-style: italic;
            margin-top: 2px;
            color: var(--muted);
        }
        .fund-row {
            margin: 0 0 10px;
            font-size: 10pt;
        }
        .fund-row .val {
            display: inline-block;
            min-width: 14em;
            border-bottom: 1px solid #000;
            padding: 0 4px;
            font-weight: 600;
        }
        .acct-block {
            margin: 0 0 14px;
            font-size: 10pt;
            line-height: 1.45;
        }
        .acct-block .uline {
            display: inline-block;
            min-width: 12em;
            border-bottom: 1px solid #000;
            padding: 0 4px;
            font-weight: 700;
            text-align: center;
        }
        .acct-block .cap-under {
            display: block;
            font-size: 8pt;
            font-style: italic;
            color: var(--muted);
            margin: 1px 0 4px 4.5em;
        }
        .acct-block .cap-under.inline {
            display: inline;
            margin: 0;
        }
        [contenteditable="true"] {
            outline: none;
        }
        [contenteditable="true"]:focus {
            background: #fff8c6;
        }
        .g-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8pt;
            margin-top: 4px;
        }
        .g-table th, .g-table td {
            border: 1px solid var(--bd);
            padding: 3px 4px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }
        .g-table thead th {
            font-weight: 700;
            text-align: center;
            background: #f3f3f3;
            vertical-align: middle;
            line-height: 1.15;
        }
        .g-table td.num, .g-table th.num { text-align: center; }
        .g-table td.money, .g-table th.money {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .c-art { width: 8%; }
        .c-desc { width: 22%; }
        .c-prop { width: 14%; }
        .c-uom { width: 6%; }
        .c-val { width: 8%; }
        .c-qc { width: 7%; }
        .c-qp { width: 7%; }
        .c-sq { width: 6%; }
        .c-sv { width: 7%; }
        .c-rem { width: 9%; }
        tr.pc-section-head td {
            background: #fff !important;
            font-weight: 700;
            font-size: 9pt;
            text-transform: uppercase;
            page-break-inside: avoid;
        }
        td.pc-section-title { text-align: left !important; }
        td.pc-section-subtotal { text-align: right !important; font-variant-numeric: tabular-nums; }
        tr.pc-grand-total td {
            background: #f7f7f7 !important;
            font-weight: 700;
            font-size: 9pt;
            font-style: italic;
            page-break-inside: avoid;
        }
        td.pc-grand-label { text-align: right !important; padding-right: 8px !important; }
        td.pc-grand-sub { text-align: right !important; font-variant-numeric: tabular-nums; }
        .sign-grid {
            display: grid;
            gap: 10px;
            margin-top: 22px;
            font-size: 9pt;
            page-break-inside: avoid;
        }
        .sign-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        .sign-grid.cols-2 { grid-template-columns: 1fr 1fr; }
        .sign-box {
            border: none;
            padding: 6px 8px;
            text-align: center;
            min-height: 120px;
        }
        .sign-box .lbl {
            text-align: left;
            font-weight: 700;
            margin-bottom: 28px;
            font-size: 9pt;
        }
        .sign-box .name {
            font-weight: 700;
            font-size: 10pt;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 70%;
            padding: 2px 8px 2px;
            margin-bottom: 2px;
        }
        .sign-box .desig {
            font-size: 8.5pt;
            margin-bottom: 10px;
        }
        .sign-box .sig-cap {
            font-size: 7.5pt;
            line-height: 1.25;
            color: var(--muted);
            margin-top: 8px;
        }
        .sign-box .date-line {
            text-align: left;
            margin-top: 14px;
            font-size: 9pt;
        }
        .sign-box .date-line .val {
            display: inline-block;
            min-width: 8em;
            border-bottom: 1px solid #000;
            padding: 0 4px;
        }
        .hint-below {
            font-size: 11px;
            color: #475569;
            margin-top: 14px;
            text-align: center;
        }
        @media (max-width: 900px) {
            .sign-grid.cols-3, .sign-grid.cols-2 { grid-template-columns: 1fr; }
            .meta-row { grid-template-columns: 1fr; }
            .g-table { font-size: 7.5pt; }
        }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; border: none; max-width: none; padding: 0; }
            .no-print { display: none !important; }
            [contenteditable="true"]:focus { background: transparent; }
            thead { display: table-header-group; }
            .g-table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
            @page { size: legal landscape; margin: 8mm 6mm; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print</button>
        <a class="btn secondary" href="<?php echo $h($xlsxUrl); ?>">Download Excel (.xlsx)</a>
        <button type="button" class="secondary" onclick="window.close()">Close</button>
        <span class="hint">Click any underlined field or table cell to edit before printing. Layout matches the official COA form.</span>
    </div>

    <div class="annex" contenteditable="true"><?php echo $h($annexLabel); ?></div>

    <?php if (!$isPpe): ?>
    <div class="gov-head">
        <div class="republic" contenteditable="true">REPUBLIC OF THE PHILIPPINES</div>
        <div class="province" contenteditable="true">PROVINCE OF LAGUNA</div>
        <div class="muni" contenteditable="true">MUNICIPALITY OF LUMBAN</div>
    </div>
    <?php endif; ?>

    <h1 class="doc-title" contenteditable="true"><?php echo $h($mainTitle); ?></h1>
    <p class="type-line" contenteditable="true"><?php echo $h($typeLabel); ?></p>
    <p class="type-caption" contenteditable="true"><?php echo $h($typeSubtitle); ?></p>
    <p class="as-of"><?php echo $h($asOfPrefix); ?> <span contenteditable="true"><?php echo $h($asOf); ?></span></p>

    <div class="meta-row">
        <div class="meta-cell">
            <span class="val" contenteditable="true"><?php echo $h($officerDesig !== '' ? $officerDesig : "\u{00a0}"); ?></span>
            <div class="cap">(Official Designation)</div>
        </div>
        <div class="meta-cell">
            <span class="val" contenteditable="true"><?php echo $h($lguName !== '' ? $lguName : "\u{00a0}"); ?></span>
            <div class="cap"><?php echo $h($entityCaption); ?></div>
        </div>
    </div>

    <?php if (!$isPpe): ?>
    <div class="fund-row">Fund Cluster: <span class="val" contenteditable="true">&nbsp;</span></div>
    <?php endif; ?>

    <div class="acct-block">
        For which <span class="uline" contenteditable="true"><?php echo $h($officerName !== '' ? $officerName : "\u{00a0}"); ?></span> is accountable, having assumed such<br>
        <span class="cap-under">(Name of Accountable Officer)</span>
        accountability on <span class="uline" contenteditable="true"><?php echo $h($assumptionDate !== '' ? $assumptionDate : $asOf); ?></span><br>
        <span class="cap-under" style="margin-left:8.5em">(Date of Assumption)</span>
    </div>

    <table class="g-table">
        <thead>
            <tr>
                <th rowspan="2" class="c-art"><?php echo $isPpe ? 'ARTICLE' : 'Article / Item'; ?></th>
                <th rowspan="2" class="c-desc">DESCRIPTION</th>
                <th rowspan="2" class="c-prop"><?php echo $h($propColLabel); ?></th>
                <th rowspan="2" class="c-uom">UNIT OF<br>MEASURE</th>
                <th rowspan="2" class="c-val money">UNIT VALUE</th>
                <th class="c-qc">BALANCE<br>PER CARD</th>
                <th class="c-qp">ON HAND<br>PER COUNT</th>
                <th colspan="2">SHORTAGE/OVERAGE</th>
                <th rowspan="2" class="c-rem">REMARKS</th>
            </tr>
            <tr>
                <th class="num">(Quantity)</th>
                <th class="num">(Quantity)</th>
                <th class="c-sq num">Quantity</th>
                <th class="c-sv money">Value</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($segments)): ?>
            <tr>
                <td colspan="10" style="text-align:center;padding:18px;font-style:italic" contenteditable="true">No items match the current filters.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($segments as $seg): ?>
            <?php if (!empty($seg['explicit']) && ($seg['title'] ?? '') !== ''): ?>
            <?php
                $secTitleUpper = function_exists('mb_strtoupper')
                    ? mb_strtoupper((string) $seg['title'], 'UTF-8')
                    : strtoupper((string) $seg['title']);
            ?>
            <tr class="pc-section-head">
                <td colspan="4" class="pc-section-title" contenteditable="true"><?php echo $h($secTitleUpper); ?></td>
                <td class="pc-section-subtotal money" contenteditable="true"><?php echo $h(number_format((float) $seg['book_subtotal'], 2, '.', ',')); ?></td>
                <td contenteditable="true">&nbsp;</td>
                <td contenteditable="true">&nbsp;</td>
                <td contenteditable="true">&nbsp;</td>
                <td contenteditable="true">&nbsp;</td>
                <td contenteditable="true">&nbsp;</td>
            </tr>
            <?php endif; ?>
            <?php foreach (physical_count_report_table_rows($seg['items'], !$isPpe, $isPpe ? 'PPE' : 'SE') as $r): ?>
            <tr>
                <td contenteditable="true"><?php echo $h($r['article']); ?></td>
                <td contenteditable="true"><?php echo $h($r['description']); ?></td>
                <td contenteditable="true" style="font-family:Consolas,'Courier New',monospace;font-size:7.5pt"><?php echo $h($r['property_number']); ?></td>
                <td class="num" contenteditable="true"><?php echo $h($r['uom']); ?></td>
                <td class="money" contenteditable="true"><?php echo $h($r['unit_value']); ?></td>
                <td class="num" contenteditable="true"><?php echo $h($r['qty_card']); ?></td>
                <td class="num" contenteditable="true"><?php echo $h($r['qty_phys']); ?></td>
                <td class="num" contenteditable="true"><?php echo $h($r['var_qty']); ?></td>
                <td class="money" contenteditable="true"><?php echo $h($r['var_val']); ?></td>
                <td contenteditable="true"><?php echo $h($r['remarks'] === '—' ? '' : $r['remarks']); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
            <tr class="pc-grand-total">
                <td colspan="4" class="pc-grand-label" contenteditable="true">TOTAL</td>
                <td class="pc-grand-sub money" contenteditable="true"><?php echo $h(number_format((float) $grandBookTotal, 2, '.', ',')); ?></td>
                <td contenteditable="true">&nbsp;</td>
                <td contenteditable="true">&nbsp;</td>
                <td contenteditable="true">&nbsp;</td>
                <td contenteditable="true">&nbsp;</td>
                <td contenteditable="true">&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <?php if ($isPpe): ?>
    <div class="sign-grid cols-2">
        <div class="sign-box">
            <div class="lbl">Prepared by:</div>
            <div class="name" contenteditable="true"><?php echo $h($preparedName !== '' ? $preparedName : "\u{00a0}"); ?></div>
            <div class="desig" contenteditable="true"><?php echo $h($preparedDesig !== '' ? $preparedDesig : "\u{00a0}"); ?></div>
            <div class="sig-cap" contenteditable="true">Signature over Printed Name of Inventory Committee Chair and Members</div>
            <div class="date-line">Date: <span class="val" contenteditable="true">&nbsp;</span></div>
        </div>
        <div class="sign-box">
            <div class="lbl">Approved by:</div>
            <div class="name" contenteditable="true"><?php echo $h($approverName !== '' ? $approverName : "\u{00a0}"); ?></div>
            <div class="desig" contenteditable="true"><?php echo $h($approverDesig !== '' ? $approverDesig : "\u{00a0}"); ?></div>
            <div class="sig-cap" contenteditable="true">Signature over Printed Name of Head of Agency/Entity or Authorized Representative</div>
            <div class="date-line">Date: <span class="val" contenteditable="true">&nbsp;</span></div>
        </div>
    </div>
    <?php else: ?>
    <div class="sign-grid cols-3">
        <div class="sign-box">
            <div class="lbl">Prepared by:</div>
            <div class="name" contenteditable="true"><?php echo $h($preparedName !== '' ? $preparedName : "\u{00a0}"); ?></div>
            <div class="desig" contenteditable="true"><?php echo $h($preparedDesig !== '' ? $preparedDesig : "\u{00a0}"); ?></div>
            <div class="sig-cap" contenteditable="true">Signature over Printed Name of Inventory Committee Chair and Members</div>
            <div class="date-line">Date: <span class="val" contenteditable="true">&nbsp;</span></div>
        </div>
        <div class="sign-box">
            <div class="lbl">Certified Correct by:</div>
            <div class="name" contenteditable="true"><?php echo $h($certifiedName !== '' ? $certifiedName : ($officerName !== '' ? $officerName : "\u{00a0}")); ?></div>
            <div class="desig" contenteditable="true"><?php echo $h($certifiedDesig !== '' ? $certifiedDesig : ($officerDesig !== '' ? $officerDesig : "\u{00a0}")); ?></div>
            <div class="sig-cap" contenteditable="true">Signature over Printed Name of Accountable Officer</div>
            <div class="date-line">Date: <span class="val" contenteditable="true">&nbsp;</span></div>
        </div>
        <div class="sign-box">
            <div class="lbl">Approved by:</div>
            <div class="name" contenteditable="true"><?php echo $h($approverName !== '' ? $approverName : "\u{00a0}"); ?></div>
            <div class="desig" contenteditable="true"><?php echo $h($approverDesig !== '' ? $approverDesig : "\u{00a0}"); ?></div>
            <div class="sig-cap" contenteditable="true">Signature over Printed Name of Head of Agency/Entity or Authorized Representative</div>
            <div class="date-line">Date: <span class="val" contenteditable="true">&nbsp;</span></div>
        </div>
    </div>
    <?php endif; ?>

    <p class="hint-below no-print">Category rows show section book totals. Edit any cell, then Print (landscape legal recommended). Optional: download Excel for offline editing / re-import.</p>
</div>
</body>
</html>
