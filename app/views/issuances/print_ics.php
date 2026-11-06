<?php
if (!isset($issuance) || !is_array($issuance)) {
    exit('Invalid print context');
}
$ipDate = static function ($d) {
    if ($d === null || $d === '') {
        return '';
    }
    $t = strtotime((string) $d);

    return $t ? date('F j, Y', $t) : htmlspecialchars((string) $d);
};
$q = (int) ($issuance['quantity'] ?? 1);
$u = htmlspecialchars($issuance['unit'] ?? 'pc');
$desc = htmlspecialchars($issuance['description'] ?? '');
$pn = htmlspecialchars($issuance['property_number'] ?? '');
$uv = number_format((float) ($issuance['unit_value'] ?? 0), 2);
$tot = number_format((float) ($issuance['amount'] ?? 0), 2);
$life = htmlspecialchars($issuance['estimated_useful_life_display'] ?? '');
$issue = $ipDate($issuance['issue_date'] ?? $issuance['issuance_date'] ?? '');
$entity = htmlspecialchars($issuance['entity_name'] ?? (defined('AGENCY_NAME') ? AGENCY_NAME : ''));
$fund = htmlspecialchars($issuance['fund_cluster'] ?? '');
$icsNo = htmlspecialchars($issuance['form_number'] ?? '');
$issued = htmlspecialchars($issuance['issued_to'] ?? '');
$off = htmlspecialchars($issuance['office_name'] ?? '');
$BODY_ROWS = 14;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICS <?php echo $icsNo; ?></title>
    <style>
        :root { --border: #111; --cell-pad: 5px 6px; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            margin: 0;
            padding: 16px;
            background: #f3f4f6;
            color: #111;
        }
        .sheet {
            max-width: 980px;
            margin: 0 auto;
            background: #fff;
            padding: 20px 24px 32px;
            border: 1px solid #ccc;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
        }
        .toolbar { margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap; }
        .toolbar button {
            padding: 8px 16px;
            border: 1px solid #333;
            background: #b91c1c;
            color: #fff;
            cursor: pointer;
            border-radius: 4px;
            font-size: 13px;
        }
        .toolbar button.secondary { background: #444; }
        h1 {
            text-align: center;
            font-size: 14pt;
            font-weight: 700;
            letter-spacing: .02em;
            margin: 0 0 16px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
            margin-bottom: 14px;
            font-size: 11pt;
        }
        .meta-row { display: flex; align-items: flex-end; gap: 8px; min-height: 1.6em; }
        .meta-row label { flex: 0 0 auto; white-space: nowrap; font-weight: 700; }
        .meta-row .line { flex: 1; border-bottom: 1px solid var(--border); min-height: 1.2em; }
        .meta-ics { grid-column: 2; text-align: right; justify-content: flex-end; }
        .g-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .g-table th, .g-table td {
            border: 1px solid var(--border);
            padding: var(--cell-pad);
            vertical-align: middle;
            word-wrap: break-word;
        }
        .g-table thead th {
            font-weight: 700;
            text-align: center;
            background: #fafafa;
            font-size: 9.5pt;
        }
        .g-table td[contenteditable="true"] { min-height: 1.4em; outline: none; font-size: 10pt; }
        .g-table td[contenteditable="true"]:focus { background: #fffef5; }
        .num { text-align: center; }
        .money { text-align: right; font-variant-numeric: tabular-nums; }
        .col-qty { width: 7%; }
        .col-unit { width: 7%; }
        .col-uc { width: 9%; }
        .col-tc { width: 9%; }
        .col-desc { width: 40%; }
        .col-inv { width: 14%; }
        .col-life { width: 14%; }
        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 28px;
        }
        .sign-box { border: 1px solid var(--border); padding: 12px 14px; min-height: 120px; }
        .sign-box h3 { margin: 0 0 12px; font-size: 10pt; font-weight: 700; }
        .sign-line { border-top: 1px solid var(--border); margin-top: 36px; padding-top: 4px; font-size: 9pt; text-align: center; color: #333; }
        .hint { font-size: 9pt; color: #555; margin-top: 12px; text-align: center; }
        @media (max-width: 720px) {
            .meta-grid { grid-template-columns: 1fr; }
            .meta-ics { grid-column: 1; text-align: left; }
            .sign-grid { grid-template-columns: 1fr; }
        }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; border: none; max-width: none; padding: 0; }
            .no-print { display: none !important; }
            .g-table td[contenteditable="true"]:focus { background: transparent; }
            @page { margin: 10mm; }
            .sign-grid {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                gap: 24px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .sign-box {
                flex: 1 1 0 !important;
                min-width: 0 !important;
            }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" class="secondary" onclick="window.close()">Close</button>
    </div>

    <h1>INVENTORY CUSTODIAN SLIP</h1>

    <div class="meta-grid">
        <div class="meta-row">
            <label>Entity Name :</label>
            <div class="line" contenteditable="true"><?php echo $entity; ?></div>
        </div>
        <div class="meta-row meta-ics">
            <label>ICS No. :</label>
            <div class="line" contenteditable="true"><?php echo $icsNo; ?></div>
        </div>
        <div class="meta-row">
            <label>Fund Cluster :</label>
            <div class="line" contenteditable="true"><?php echo $fund; ?></div>
        </div>
        <div class="meta-row meta-ics">
            <label>Date :</label>
            <div class="line" contenteditable="true"><?php echo $issue; ?></div>
        </div>
    </div>

    <table class="g-table">
        <thead>
            <tr>
                <th class="col-qty" rowspan="2">Quantity</th>
                <th class="col-unit" rowspan="2">Unit</th>
                <th colspan="2">Amount</th>
                <th class="col-desc" rowspan="2">Description</th>
                <th class="col-inv" rowspan="2">Inventory Item No.</th>
                <th class="col-life" rowspan="2">Estimated Useful Life</th>
            </tr>
            <tr>
                <th class="col-uc">Unit Cost</th>
                <th class="col-tc">Total Cost</th>
            </tr>
        </thead>
        <tbody>
        <?php for ($r = 0; $r < $BODY_ROWS; $r++): ?>
            <tr>
                <td class="num" contenteditable="true"><?php echo $r === 0 ? $q : ''; ?></td>
                <td class="num" contenteditable="true"><?php echo $r === 0 ? $u : ''; ?></td>
                <td class="money" contenteditable="true"><?php echo $r === 0 ? $uv : ''; ?></td>
                <td class="money" contenteditable="true"><?php echo $r === 0 ? $tot : ''; ?></td>
                <td contenteditable="true"><?php echo $r === 0 ? $desc : ''; ?></td>
                <td contenteditable="true" style="font-family: Consolas, monospace; font-size: 10pt;"><?php echo $r === 0 ? $pn : ''; ?></td>
                <td contenteditable="true"><?php echo $r === 0 ? $life : ''; ?></td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <div class="sign-grid">
        <div class="sign-box">
            <h3>Received from:</h3>
            <div class="sign-line" contenteditable="true">Signature Over Printed Name</div>
            <div class="sign-line" contenteditable="true" style="margin-top:16px;">Position/Office — <?php echo $off; ?></div>
            <div class="sign-line" contenteditable="true" style="margin-top:16px;">Date</div>
        </div>
        <div class="sign-box">
            <h3>Received by:</h3>
            <div class="sign-line" contenteditable="true"><?php echo $issued; ?></div>
            <div class="sign-line" contenteditable="true" style="margin-top:16px;">Position/Office</div>
            <div class="sign-line" contenteditable="true" style="margin-top:16px;">Date</div>
        </div>
    </div>

    <p class="hint no-print">Cells are editable before printing. Unit and total cost follow the encoded property item.</p>
</div>
</body>
</html>
