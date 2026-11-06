<?php
if (!isset($issuance) || !is_array($issuance)) {
    exit('Invalid print context');
}
$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$ipDate = static function ($d) {
    if ($d === null || $d === '') {
        return '';
    }
    $t = strtotime((string) $d);

    return $t ? date('Y-m-d', $t) : $d;
};
$items = (isset($issuance['items']) && is_array($issuance['items']) && $issuance['items'])
    ? $issuance['items']
    : [[
        'quantity' => $issuance['quantity'] ?? 1,
        'unit' => $issuance['unit'] ?? 'pc',
        'description' => $issuance['description'] ?? '',
        'property_number' => $issuance['property_number'] ?? '',
        'acquisition_date' => $issuance['acquisition_date'] ?? null,
        'amount' => $issuance['amount'] ?? 0,
        'remarks' => $issuance['remarks'] ?? '',
    ]];
$acq = $ipDate($issuance['acquisition_date'] ?? null);
$totalAmt = 0.0;
foreach ($items as $it) {
    $totalAmt += (float) ($it['amount'] ?? 0);
}
$amt = number_format($totalAmt > 0 ? $totalAmt : (float) ($issuance['amount'] ?? 0), 2);
$issue = $ipDate($issuance['issue_date'] ?? $issuance['issuance_date'] ?? '');
$parNo = (string) ($issuance['form_number'] ?? '');
$off = (string) ($issuance['office_name'] ?? '');
$section = (string) ($issuance['section'] ?? '');
$poNo = (string) ($issuance['po_number'] ?? '');
$poDate = $ipDate($issuance['po_date'] ?? null);
$received = (string) ($issuance['received_by'] ?? $issuance['issued_to'] ?? '');
$position = (string) ($issuance['position'] ?? '');
$issuedBy = (string) ($issuance['issued_by'] ?? '');
$issuedByTitle = (string) ($issuance['issued_by_title'] ?? '');
$approvedBy = (string) ($issuance['approved_by'] ?? '');
$approvedByTitle = (string) ($issuance['approved_by_title'] ?? '');
$xlsxHref = 'print-issuance.php?id=' . (int) ($_GET['id'] ?? 0) . '&format=xlsx';
$logo = function_exists('lgue_url') ? lgue_url('public/assets/logo-lumban.jpg') : '../public/assets/logo-lumban.jpg';
$BODY_ROWS = 10;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAR <?php echo $h($parNo); ?></title>
    <style>
        :root { --border: #111; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            margin: 0;
            padding: 16px;
            background: #e8e8e8;
            color: #111;
        }
        .sheet {
            max-width: 980px;
            margin: 0 auto;
            background: #fff;
            padding: 18px 22px 28px;
            border: 1px solid #bbb;
        }
        .toolbar { margin-bottom: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
        .toolbar a, .toolbar button {
            padding: 8px 16px;
            border: 1px solid #333;
            background: #b91c1c;
            color: #fff;
            cursor: pointer;
            border-radius: 4px;
            font-size: 13px;
            text-decoration: none;
            font-family: Arial, sans-serif;
        }
        .toolbar a.secondary, .toolbar button.secondary { background: #444; }
        .letterhead { text-align: center; position: relative; min-height: 72px; }
        .letterhead img {
            position: absolute;
            left: 8px;
            top: 0;
            width: 64px;
            height: 64px;
            object-fit: contain;
        }
        .letterhead .l1, .letterhead .l2 { font-size: 12pt; line-height: 1.25; }
        .letterhead .l3 { font-size: 13pt; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        h1 {
            text-align: center;
            font-size: 14pt;
            font-weight: 700;
            margin: 14px 0 16px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .meta td { padding: 3px 4px; vertical-align: bottom; }
        .meta label { font-weight: 700; white-space: nowrap; }
        .meta .line {
            border-bottom: 1px solid #111;
            min-height: 1.2em;
            min-width: 120px;
            outline: none;
        }
        .g-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .g-table th, .g-table td {
            border: 1px solid #111;
            padding: 5px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .g-table thead th {
            font-weight: 700;
            text-align: center;
            font-size: 10pt;
        }
        .g-table td[contenteditable="true"] { outline: none; min-height: 1.3em; }
        .num { text-align: center; }
        .money { text-align: right; font-variant-numeric: tabular-nums; }
        .col-item { width: 7%; }
        .col-qty { width: 8%; }
        .col-unit { width: 7%; }
        .col-desc { width: 32%; }
        .col-prop { width: 16%; }
        .col-date { width: 12%; }
        .col-amt { width: 10%; }
        .col-rem { width: 8%; }
        tr.total-row td { font-weight: 700; }
        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px 40px;
            margin-top: 28px;
        }
        .sign-box h3 { margin: 0 0 22px; font-size: 11pt; font-weight: 700; }
        .sign-name {
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid #111;
            min-height: 1.4em;
            outline: none;
        }
        .sign-sub { text-align: center; font-size: 10pt; margin-top: 4px; outline: none; }
        .hint { font-size: 9pt; color: #555; margin-top: 14px; text-align: center; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: none; max-width: none; padding: 0; }
            .no-print { display: none !important; }
            @page { margin: 10mm; size: landscape; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print</button>
        <a class="secondary" href="<?php echo $h($xlsxHref); ?>">Download official Excel</a>
        <button type="button" class="secondary" onclick="window.close()">Close</button>
    </div>

    <div class="letterhead">
        <img src="<?php echo $h($logo); ?>" alt="Municipality of Lumban">
        <div class="l1" contenteditable="true">Republic of the Philippines</div>
        <div class="l2" contenteditable="true">Province of Laguna</div>
        <div class="l3" contenteditable="true">Municipality of Lumban</div>
    </div>

    <h1>Property Acknowledgement Receipt</h1>

    <table class="meta">
        <tr>
            <td style="width:12%"><label>Department:</label></td>
            <td style="width:30%"><div class="line" contenteditable="true"><?php echo $h($off); ?></div></td>
            <td style="width:10%"><label>PAR No. :</label></td>
            <td style="width:24%"><div class="line" contenteditable="true"><?php echo $h($parNo); ?></div></td>
            <td style="width:8%"><label>Date:</label></td>
            <td><div class="line" contenteditable="true"><?php echo $h($issue); ?></div></td>
        </tr>
        <tr>
            <td><label>Section:</label></td>
            <td><div class="line" contenteditable="true"><?php echo $h($section); ?></div></td>
            <td><label>PO No. :</label></td>
            <td><div class="line" contenteditable="true"><?php echo $h($poNo); ?></div></td>
            <td><label>Date:</label></td>
            <td><div class="line" contenteditable="true"><?php echo $h($poDate); ?></div></td>
        </tr>
    </table>

    <table class="g-table">
        <thead>
            <tr>
                <th class="col-item">Item No.</th>
                <th class="col-qty">Quantity</th>
                <th class="col-unit">Unit</th>
                <th class="col-desc">Item Description</th>
                <th class="col-prop">Property No.</th>
                <th class="col-date">Date of Purchased</th>
                <th class="col-amt">Total Amount</th>
                <th class="col-rem">Remarks</th>
            </tr>
        </thead>
        <tbody>
        <?php for ($r = 0; $r < $BODY_ROWS; $r++):
            $it = $items[$r] ?? null;
            $isFollow = $it === null && $r === count($items);
            ?>
            <tr>
                <td class="num" contenteditable="true"><?php echo $it ? (string) ($r + 1) : ''; ?></td>
                <td class="num" contenteditable="true"><?php echo $it ? (int) ($it['quantity'] ?? 1) : ''; ?></td>
                <td class="num" contenteditable="true"><?php echo $it ? $h(strtoupper((string) ($it['unit'] ?? 'pc'))) : ''; ?></td>
                <td contenteditable="true"><?php
                    if ($it) {
                        echo $h((string) ($it['description'] ?? ''));
                    } elseif ($isFollow) {
                        echo '*********nothing follows*********';
                    }
                ?></td>
                <td contenteditable="true" style="font-family: Consolas, monospace; font-size: 9.5pt;"><?php echo $it ? $h((string) ($it['property_number'] ?? '')) : ''; ?></td>
                <td contenteditable="true"><?php echo $it ? $h($ipDate($it['acquisition_date'] ?? $acq)) : ''; ?></td>
                <td class="money" contenteditable="true"><?php echo $it ? number_format((float) ($it['amount'] ?? 0), 2) : ''; ?></td>
                <td contenteditable="true"><?php echo $it ? $h((string) ($it['remarks'] ?? ($r === 0 ? ($issuance['remarks'] ?? '') : ''))) : ''; ?></td>
            </tr>
        <?php endfor; ?>
            <tr class="total-row">
                <td colspan="6" style="text-align:right;">TOTAL</td>
                <td class="money" contenteditable="true"><?php echo $amt; ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="sign-grid">
        <div class="sign-box">
            <h3>Received by:</h3>
            <div class="sign-name" contenteditable="true"><?php echo $h($received); ?></div>
            <div class="sign-sub" contenteditable="true"><?php echo $h($position !== '' ? $position : 'Signature over Printed Name / Date'); ?></div>
        </div>
        <div class="sign-box">
            <h3>Administered by:</h3>
            <div class="sign-name" contenteditable="true">&nbsp;</div>
            <div class="sign-sub" contenteditable="true">Signature over Printed Name / Position</div>
        </div>
        <div class="sign-box">
            <h3>Issued by:</h3>
            <div class="sign-name" contenteditable="true"><?php echo $h($issuedBy); ?></div>
            <div class="sign-sub" contenteditable="true"><?php echo $h($issuedByTitle); ?></div>
        </div>
        <div class="sign-box">
            <h3>Approved by:</h3>
            <div class="sign-name" contenteditable="true"><?php echo $h($approvedBy); ?></div>
            <div class="sign-sub" contenteditable="true"><?php echo $h($approvedByTitle); ?></div>
        </div>
    </div>

    <p class="hint no-print">This is the official Lumban PAR form. Cells are editable before printing. Use Download official Excel for the same file GSO already uses.</p>
</div>
</body>
</html>
