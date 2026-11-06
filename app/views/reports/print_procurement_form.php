<?php
/**
 * @var string $formType pr|rfq|po|air
 * @var array<string, mixed>|null $payload
 */
if (!isset($formType, $payload)) {
    exit('Invalid print');
}

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$underlineName = defined('PROC_PRINT_UNDERLINE_NAME') ? (string) constant('PROC_PRINT_UNDERLINE_NAME') : (defined('AGENCY_NAME') ? AGENCY_NAME : 'Municipality / LGU');
if (function_exists('settingsGet') && function_exists('getDBConnection')) {
    try {
        $saved = settingsGet(getDBConnection(), 'print_underline_name', $underlineName);
        if ($saved) {
            $underlineName = $saved;
        }
    } catch (Throwable $e) {
        // Keep config default.
    }
}
$poLegalTitle = defined('PROC_PRINT_PO_HEADER_TITLE') ? (string) constant('PROC_PRINT_PO_HEADER_TITLE') : $underlineName;
$rfqRep = defined('PROC_PRINT_RFQ_REPUBLIC_LINE') ? (string) constant('PROC_PRINT_RFQ_REPUBLIC_LINE') : 'Republic of the Philippines';
$rfqProv = defined('PROC_PRINT_RFQ_PROVINCE_LINE') ? (string) constant('PROC_PRINT_RFQ_PROVINCE_LINE') : 'Province of Laguna';
$rfqMun = defined('PROC_PRINT_RFQ_MUN_LINE') ? (string) constant('PROC_PRINT_RFQ_MUN_LINE') : 'MUNICIPALITY OF LUMBAN';
$rfqTel = defined('PROC_PRINT_RFQ_TEL') ? (string) constant('PROC_PRINT_RFQ_TEL') : '';

/** @phpstan-ignore-next-line */
if (!function_exists('procurement_chunk999_caps')) {
    /** Converts 1–999 to English words (government style, all caps wording). */
    function procurement_chunk999_caps(int $n): string {
        static $below20 = [
            1 => 'ONE', 2 => 'TWO', 3 => 'THREE', 4 => 'FOUR', 5 => 'FIVE', 6 => 'SIX',
            7 => 'SEVEN', 8 => 'EIGHT', 9 => 'NINE', 10 => 'TEN', 11 => 'ELEVEN', 12 => 'TWELVE',
            13 => 'THIRTEEN', 14 => 'FOURTEEN', 15 => 'FIFTEEN', 16 => 'SIXTEEN', 17 => 'SEVENTEEN',
            18 => 'EIGHTEEN', 19 => 'NINETEEN',
        ];
        static $tens = [
            2 => 'TWENTY', 3 => 'THIRTY', 4 => 'FORTY', 5 => 'FIFTY', 6 => 'SIXTY', 7 => 'SEVENTY', 8 => 'EIGHTY', 9 => 'NINETY',
        ];

        if ($n < 1 || $n > 999) {
            return '';
        }

        $hundreds = intdiv($n, 100);
        $rest = $n % 100;
        $parts = [];
        if ($hundreds > 0) {
            $parts[] = ($below20[$hundreds] ?? '') . ' HUNDRED';
        }
        if ($rest === 0) {
            return trim(implode(' ', $parts));
        }
        if ($rest < 20) {
            $parts[] = $below20[$rest] ?? '';

            return trim(implode(' ', $parts));
        }
        $tx = intdiv($rest, 10);
        $units = $rest % 10;
        $tenWord = $tens[$tx] ?? '';
        if ($units > 0) {
            $parts[] = $tenWord . ' ' . $below20[$units];
        } else {
            $parts[] = $tenWord;
        }

        return trim(implode(' ', $parts));
    }
}

/** @phpstan-ignore-next-line */
if (!function_exists('procurement_pesos_words_caps')) {
    /** Whole pesos only (matches typical LGU PO “amount in words” line). */
    function procurement_pesos_words_caps(float $amt): string {
        $whole = (int) round(max(0, $amt));

        return procurement_int_money_caps($whole);
    }

    /** @phpstan-ignore-next-line */
    function procurement_int_money_caps(int $whole): string {
        $scales = ['', 'THOUSAND', 'MILLION', 'BILLION'];
        $n = abs($whole);
        if ($n === 0) {
            return 'ZERO PESOS';
        }

        $fragments = [];

        $i = 0;
        while ($n > 0 && $i < count($scales)) {
            $chunk = $n % 1000;
            $n = intdiv($n, 1000);
            if ($chunk > 0) {
                $chunkWords = procurement_chunk999_caps($chunk);
                $suffix = $scales[$i] ?? '';
                $frag = trim($chunkWords . ($suffix !== '' ? ' ' . $suffix : ''));
                array_unshift($fragments, $frag);
            }
            ++$i;
        }

        return trim(implode(' ', $fragments)) . ' PESOS';
    }
}

$pfmt = static function ($n): string {
    return number_format((float) $n, 2, '.', ',');
};

$qtyShow = static function ($q): string {
    $n = (float) $q;
    if (!is_finite($n)) {
        return '';
    }
    if (abs($n - round($n)) < 0.0000001) {
        return (string) (int) round($n);
    }

    return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
};

$dLong = static function (?string $ymd): string {
    if ($ymd === null || $ymd === '') {
        return '';
    }
    $ts = strtotime($ymd);

    return $ts ? date('F j, Y', $ts) : $ymd;
};

$chk = static function ($on): string {
    return $on ? '☑' : '☐';
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Procurement — <?php echo $h(strtoupper($formType)); ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 10px auto;
            max-width: 900px;
            color: #000;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table { border-collapse: collapse; }
        .no-print button { cursor: pointer; }
        @media print {
            body { margin: 0 auto; max-width: 100%; }
            .no-print { display: none !important; }
        }
        .sheet-sans {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.3;
            border: 1px solid #000;
            padding: 12px;
        }
        .sheet-serif {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.35;
            border: 1px solid #000;
            padding: 14px;
        }
        .tac { text-align: center; }
        .tal { text-align: left; }
        .tar { text-align: right; }
        .vat { vertical-align: top; }
        .vam { vertical-align: middle; }
        .bw { border: 1px solid #000; }
        .bff th, .bff td { border: 1px solid #000; }
        .th-b { font-weight: bold; }
        .up { text-transform: uppercase; }

        /* PR */
        .pr-stamp-br { display: flex; justify-content: flex-end; margin-bottom: 8px; }
        .pr-stamp {
            border: 1px dashed #333;
            min-width: 200px;
            min-height: 70px;
            font-size: 8pt;
            padding: 6px;
            text-align: center;
        }
        .pr-title {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.06em;
            margin: 4px 0 8px;
        }
        .ul-name {
            display: inline-block;
            border-bottom: 1px solid #000;
            font-weight: bold;
            padding: 0 32px 1px;
        }
        .ag-sub-it { font-style: italic; font-size: 10pt; margin: 4px 0 10px; }
        .dash-cell { font-size: 10pt; text-align: right; padding: 3px 4px !important; }
        .dash-cell-dash { font-size: 10pt; text-align: right; padding: 3px 4px !important; color: #333; }

        /* RFQ seal */
        .rfq-seal {
            margin: 0 auto 10px;
            width: 88px;
            height: 88px;
            border: 1px dashed #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 7pt;
            text-align: center;
            padding: 4px;
        }
        .rfq-deem { margin: 6px auto 14px; max-width: 95%; font-size: 10pt; line-height: 1.38; text-align: center; font-style: italic; }
        .rfq-laws { margin: 6px 0; font-size: 9pt; line-height: 1.42; padding-left: 18px; }
        .small { font-size: 9.5pt; }

        /* PO */
        .po-dashed { margin: 14px 0 8px; border: none; border-top: 1px dashed #000; }
        .muted-foot { margin-top: 10px; font-size: 9.5pt; text-align: center; font-style: italic; line-height: 1.38; }

        /* AIR header */
        .air-hdr {
            position: relative;
            margin-bottom: 10px;
        }
        .air-num-box {
            position: absolute;
            right: 0;
            top: 0;
            font-size: 10pt;
            text-align: right;
        }
        .air-two {
            width: 100%;
            margin-top: 14px;
        }
        .air-two td { width: 50%; border: 1px solid #000; padding: 8px 10px; vertical-align: top; }
        .nf { text-align: center; font-size: 10pt; font-style: italic; padding: 4px 6px !important; }
        .rcv-block { margin-top: 14px; border-top: 1px solid #000; padding-top: 10px; text-align: left; }
        .rcv-block .rcv-names { margin-top: 10px; text-align: center; }
        .rcv-n { font-weight: bold; text-transform: uppercase; margin-top: 8px; }
    </style>
</head>
<body>
<p class="no-print tac"><button type="button" onclick="window.print()">Print</button> <button type="button" onclick="window.close()">Close</button></p>

<?php if ($formType === 'pr' && isset($payload['pr'])): ?>
<?php $pr = $payload['pr'];
    $prReqName = trim((string) ($pr['requested_by'] ?? ''));
    $prReqTitle = trim((string) ($pr['requested_by_title'] ?? ''));
    $prAppName = trim((string) ($pr['approved_by'] ?? ''));
    $prAppTitle = trim((string) ($pr['approved_by_title'] ?? ''));
    if ($prReqTitle === '') {
        $prReqTitle = trim((string) ($pr['office_name'] ?? ''));
    }
    if ($prAppName === '') {
        $prAppName = defined('RPCPPE_APPROVER_NAME') ? (string) RPCPPE_APPROVER_NAME : 'BELEN B. RAGA';
    }
    if ($prAppTitle === '') {
        $prAppTitle = defined('RPCPPE_APPROVER_DESIGNATION') ? (string) RPCPPE_APPROVER_DESIGNATION : 'Municipal Mayor';
    }
    $lines = $payload['lines'];
    $gt = 0.0;
    foreach ($lines as $ln) {
        $gt += (float) $ln['est_line_total'];
    }
    $padRows = max(0, 12 - count($lines));

    $prLongDate = $dLong((string) ($pr['pr_date'] ?? ''));
    ?>
    <div class="sheet-sans">
        <div class="pr-stamp-br">
            <div class="pr-stamp tac">
                RECEIVED<br><strong>(OFFICE OF THE BUDGET)</strong><br><span style="opacity:.75">stamp date / receiver — wet sign only</span>
            </div>
        </div>
        <div class="pr-title tac">PURCHASE REQUEST</div>
        <div class="tac"><span class="ul-name"><?php echo $h($underlineName); ?></span></div>
        <div class="tac ag-sub-it">Agency / Procuring Entity</div>

        <table style="width:100%;margin-bottom:10px" class="bff small">
            <tr>
                <td class="tal vam" colspan="4" style="width:62%">
                    <strong>Department:</strong><br>
                    <?php echo $h((string) ($pr['office_name'] ?? '')); ?>
                </td>
                <td class="tar vat" colspan="2" style="vertical-align:top">
                    <div><strong>PR No.:</strong> <?php echo $h((string) ($pr['pr_number'] ?? '')); ?></div>
                    <div style="margin-top:6px"><strong>Date:</strong> <?php echo $h($prLongDate); ?></div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="vam tal"><strong>Section:</strong> <?php echo $h((string) ($pr['pr_section'] ?? '')); ?></td>
                <td colspan="2" class="vam tal"><strong>SAI No.:</strong> <?php echo $h((string) ($pr['sai_number'] ?? '')); ?></td>
                <td colspan="2" class="tal vam"><strong>Date:</strong>&nbsp;</td>
            </tr>
        </table>

        <?php if (!empty($pr['budget_received_note'])): ?>
            <div class="small" style="margin-bottom:10px;"><strong>Note:</strong> <?php echo $h((string) $pr['budget_received_note']); ?></div>
        <?php endif; ?>

        <table class="bff full" style="width:100%;table-layout:fixed">
            <thead>
            <tr>
                <th style="width:9%" class="tac vam">Item<br>No.</th>
                <th style="width:11%" class="tac vam">Quantity</th>
                <th style="width:9%" class="tac vam">Unit</th>
                <th class="tac vam">Item Description</th>
                <th style="width:12%" class="tar vam">Estimated<br>Unit Cost</th>
                <th style="width:12%" class="tar vam">Estimated<br>Total Cost</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $ln): ?>
                <tr>
                    <td class="tac"><?php echo $h($ln['line_no']); ?></td>
                    <td class="tac"><?php echo $h($qtyShow($ln['quantity'])); ?></td>
                    <td class="tac"><?php echo $h($ln['unit']); ?></td>
                    <td class="tal"><?php echo $h($ln['description']); ?></td>
                    <td class="tar"><?php echo $h($pfmt($ln['est_unit_cost'])); ?></td>
                    <td class="tar"><?php echo $h($pfmt($ln['est_line_total'])); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $padRows; ++$i): ?>
                <tr>
                    <td class="tac">&nbsp;</td>
                    <td class="tac">&nbsp;</td>
                    <td class="tac">&nbsp;</td>
                    <td>&nbsp;</td>
                    <td class="dash-cell-dash">―</td>
                    <td class="dash-cell-dash">―</td>
                </tr>
            <?php endfor; ?>
            </tbody>
            <tfoot>
            <tr class="th-b">
                <td colspan="5" class="tar vam">TOTAL</td>
                <td class="tar"><?php echo $h($pfmt($gt)); ?></td>
            </tr>
            </tfoot>
        </table>

        <div style="margin-top:12px" class="bff">
            <div class="tal" style="padding:8px;"><strong>Purpose:</strong> <?php echo nl2br($h((string) ($pr['purpose'] ?? ''))); ?></div>
        </div>

        <table style="width:100%;margin-top:20px;border:none" cellpadding="6">
            <tr>
                <td class="vat" style="width:50%;border:none;">
                    <div class="th-b" style="margin-bottom:14px;">Requested by:</div>
                    <div class="small tac" style="border-bottom:1px solid #000;min-height:28px;"></div>
                    <div class="small" style="margin-top:14px;"><strong>Printed Name:</strong></div>
                    <div class="rcv-n"><?php echo $h(mb_strtoupper($prReqName, 'UTF-8')); ?></div>
                    <div class="tac"><?php echo $h($prReqTitle); ?></div>
                    <div class="small" style="margin-top:14px;"><strong>Date Signed:</strong> <?php echo $h((string) $dLong(isset($pr['requested_signed_date']) ? (string) $pr['requested_signed_date'] : null)); ?></div>
                </td>
                <td class="vat" style="width:50%;border:none;">
                    <div class="th-b" style="margin-bottom:14px;">Approved by:</div>
                    <div class="small tac" style="border-bottom:1px solid #000;min-height:28px;"></div>
                    <div class="small" style="margin-top:14px;"><strong>Printed Name:</strong></div>
                    <div class="rcv-n"><?php echo $h(mb_strtoupper($prAppName, 'UTF-8')); ?></div>
                    <div class="tac"><?php echo $h($prAppTitle); ?></div>
                    <div class="small" style="margin-top:14px;"><strong>Date Signed:</strong> <?php echo $h((string) $dLong(isset($pr['approved_signed_date']) ? (string) $pr['approved_signed_date'] : null)); ?></div>
                </td>
            </tr>
        </table>
    </div>

<?php elseif ($formType === 'rfq' && isset($payload['quotation'])): ?>
<?php
    $q = $payload['quotation'];
    $lines = $payload['lines'];
    $budget = isset($q['pr_estimate_total']) ? (float) $q['pr_estimate_total'] : 0;

    $qDate = '';
    if (!empty($q['quotation_date'])) {
        $qDate = $dLong((string) $q['quotation_date']);
    }

    $gt = (float) $q['total_amount'];
    $padRfq = max(0, 12 - count($lines));
    ?>
    <div class="sheet-sans tac">
        <div class="rfq-seal">Official Seal<br/><span style="font-size:7px;opacity:.7">Digital print — overlay wet seal if required</span></div>
        <div class="small"><?php echo $h($rfqRep); ?></div>
        <div class="small"><?php echo $h($rfqProv); ?></div>
        <div class="th-b" style="margin:8px 0 2px;font-size:12pt;letter-spacing:0.06em"><?php echo $h($rfqMun); ?></div>
        <?php if ($rfqTel !== ''): ?>
            <div class="small"><?php echo $h($rfqTel); ?></div>
        <?php endif; ?>
        <div class="pr-title tac" style="margin-top:14px;margin-bottom:8px;">REQUEST FOR QUOTATION OF PRICES</div>

        <table class="bff full tac" style="width:100%;margin-top:12px;text-align:left">
            <tr>
                <td class="tal vam" colspan="6" style="width:48%;padding:0">
                    <table style="width:100%;border:none">
                        <tr><td colspan="2" class="bff small tar" style="border:none;width:140px;"><strong>PR Ref. No.:</strong></td><td class="bff small tal"><?php echo $h((string) $q['pr_number']); ?></td></tr>
                        <tr><td colspan="3" class="th-b tac up" style="border:none;font-size:9pt;padding-top:6px;">Company name</td></tr>
                        <tr><td colspan="3" class="tal small" style="border:none;"><strong><?php echo $h((string) $q['supplier_name']); ?></strong></td></tr>
                        <tr><td colspan="2" class="small tar" style="border:none;"><strong>Address</strong></td><td class="tal small bw" style="min-height:20px"><?php echo $h((string) ($q['supplier_address'] ?? '')); ?></td></tr>
                        <tr><td colspan="2" class="small tar" style="border:none;"><strong>Contact No.</strong></td><td class="tal small bw" style="min-height:22px">&nbsp;</td></tr>
                    </table>
                </td>
                <td class="tal vam" colspan="6" style="width:52%;padding:0">
                    <table style="width:100%;border:none">
                        <?php /* right column blanks */ ?>
                        <tr><td class="bff small tar" style="border:none;width:42%"><strong>Date:</strong></td><td class="tal bw small"><?php echo $h($qDate ?: '________________'); ?></td></tr>
                        <tr><td class="bff small tar" style="border:none"><strong>Quotation No.:</strong></td><td class="tal bw small"><?php echo $h((string) ($q['quotation_ref'] ?? '')); ?></td></tr>
                        <tr><td class="bff small tar" style="border:none"><strong>Business Permit No.</strong></td><td class="tal bw small">&nbsp;</td></tr>
                        <tr><td class="bff small tar" style="border:none"><strong>TIN No.</strong></td><td class="tal bw small">&nbsp;</td></tr>
                        <tr><td class="bff small tar" style="border:none"><strong>PhilGEPS Reg. No.</strong></td><td class="tal bw small">&nbsp;</td></tr>
                        <tr><td class="bff small tar" style="border:none;padding-top:4px;line-height:1.2;"><strong>Vatable/Non-Vatable<br>(Please indicate)</strong></td><td class="tal bw small">&nbsp;</td></tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="12" class="tal" style="padding:10px 8px">
                    <div class="small th-b tac" style="margin-bottom:6px;">Approved Budget / Contract particulars</div>
                    <table class="bff full" style="font-size:9.8pt;margin-bottom:8px;width:100%">
                        <tr>
                            <td class="tal" colspan="12" style="padding:6px;">1. Approved Budget for the Contract: ₱ <?php echo $h($budget > 0 ? $pfmt($budget) : ''); ?></td>
                        </tr>
                        <tr>
                            <td class="tal" colspan="12" style="padding:6px;">2. Delivery Period: _________________</td>
                        </tr>
                        <tr>
                            <td class="tal" colspan="12" style="padding:6px;">3. Price Validity: _________________</td>
                        </tr>
                        <tr>
                            <td class="tal" colspan="12" style="padding:6px;">4. Warranty Period: _________________</td>
                        </tr>
                    </table>

                    <div class="tal th-b" style="margin:8px 0 6px;font-size:10pt;">General Conditions</div>
                    <ol class="rfq-laws tac" style="list-style-position:outside;padding-left:20px;text-align:left">
                        <li>All bids higher than ABC shall automatically be disqualified.</li>
                        <li>All prices should be denominated in Philippine Peso and quotations shall be inclusive of VAT.</li>
                        <li>The penalty for late deliveries is one tenth (1/10) of one (1) percent of the cost of the unperformed portion for everyday of delay.</li>
                        <li>Bidder must submit the following documents:
                            <ol type="a" style="margin:4px 0 0;padding-left:20px;line-height:1.35;font-size:9.3pt;text-align:left">
                                <li>Valid Mayor&apos;s Permit</li>
                                <li>PhilGEPS Registration</li>
                                <li>DTI Registration</li>
                                <li>BIR Registration</li>
                                <li>Notarized Omnibus Sworn Statements (if applicable)</li>
                            </ol>
                        </li>
                    </ol>
                </td>
            </tr>
            <tr><td colspan="12" class="rfq-deem">
                Please quote your <strong>LOWEST PRICE</strong> on the item/s listed below, subject to the General Conditions of the Revised IRR of RA 9184 stating the shortest time of delivery and submit your quotation duly signed by your representative.
            </td></tr>
        </table>

        <table class="bff full" style="width:100%;margin-top:4px">
            <thead>
            <tr class="small th-b tac">
                <th style="width:8%" class="tac">QTY.</th>
                <th style="width:9%">UNIT</th>
                <th>ITEM DESCRIPTION</th>
                <th style="width:13%">UNIT PRICE</th>
                <th style="width:13%">TOTAL AMOUNT</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $ln): ?>
                <tr>
                    <td class="tac"><?php echo $h($qtyShow($ln['quantity'])); ?></td>
                    <td class="tac"><?php echo $h($ln['unit']); ?></td>
                    <td class="tal"><?php echo $h($ln['description']); ?></td>
                    <td class="tar"><?php echo $h($pfmt((float) $ln['unit_price'])); ?></td>
                    <td class="tar"><?php echo $h($pfmt((float) $ln['line_total'])); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $padRfq; ++$i): ?>
                <tr><?php for ($c = 0; $c < 5; ++$c): ?><td>&nbsp;</td><?php endfor; ?></tr><?php endfor; ?>
            <tr>
                <td></td><td></td><td class="nf" colspan="3" style="text-align:center">*********nothing follows*********</td>
            </tr>
            </tbody>
            <tfoot>
            <tr class="th-b tac">
                <td colspan="4" class="tar">Total</td>
                <td class="tar"><?php echo $h($pfmt($gt)); ?></td>
            </tr>
            </tfoot>
        </table>

        <table style="width:100%;margin-top:14px;border:none;text-align:left" class="small">
            <tr>
                <td class="vat" colspan="8" style="width:54%;padding:8px;">
                    Period of Delivery: _________________________________________________
                </td>
                <td class="vat tal" colspan="8" style="padding:8px;">
                    Signature over Printed Name&nbsp;&nbsp;(Supplier / Dealer)<br>
                    Date: ___________<br><br><br>
                    Canvasser: ___________________________
                </td>
            </tr>
        </table>

        <p class="muted-foot">The right is reserved to reject any quotation to waive any defect therein to accept each quotation as maybe considered most advantageous to the government.</p>
    </div>

<?php elseif ($formType === 'po' && isset($payload['po'])): ?>
<?php
    $po = $payload['po'];
    $lines = $payload['lines'];
    $poLong = $dLong((string) ($po['po_date'] ?? ''));
    $prRef = trim((string) ($po['pr_reference_display'] ?? ''));
    if ($prRef === '') {
        $prRef = (string) ($po['pr_number'] ?? '');
    }
    $penalty = trim((string) ($po['penalty_clause'] ?? ''));
    if ($penalty === '') {
        $penalty = 'In case of failure to make the full delivery within specified as above, a penalty of one tenth (1/10) of one percent for every day of delay shall be imposed.';
    }
    $purposeLine = mb_strtoupper((string) ($po['purpose'] ?? ''), 'UTF-8');
    $grand = (float) $po['total_amount'];

    $wordsLine = procurement_pesos_words_caps($grand);
    $padPo = max(0, 10 - count($lines));
    $mayor = trim((string) ($po['signed_by_display'] ?? ''));
    ?>
    <div class="sheet-serif">
        <div class="pr-title tac">PURCHASE ORDER</div>
        <div class="tac"><span class="ul-name"><?php echo $h($poLegalTitle); ?></span></div>
        <div class="tac small" style="margin:6px 0 12px;"><strong>LGU</strong></div>

        <table style="width:100%;margin-bottom:10px" class="small">
            <tr>
                <td class="tal vam" colspan="10" style="width:54%">
                    <strong>Supplier:</strong> <?php echo $h(mb_strtoupper((string) $po['supplier_name'], 'UTF-8')); ?><br>
                    <strong>Address:</strong> <?php echo $h(mb_strtoupper((string) ($po['supplier_address'] ?? ''), 'UTF-8')); ?>
                </td>
                <td class="tar vam" colspan="10" style="width:46%;font-size:10pt;line-height:1.45;">
                    <div><strong>PO No.:</strong> <?php echo $h((string) $po['po_number']); ?></div>
                    <div><strong>Date:</strong> <?php echo $h($poLong); ?></div>
                    <div><strong>Mode of Procurement:</strong> <?php echo $h((string) ($po['mode_of_procurement'] ?? 'SVP')); ?></div>
                    <div><strong>PR No.:</strong> <?php echo $h($prRef); ?></div>
                </td>
            </tr>
        </table>

        <p class="small">Gentlemen/Madam:<br/>
            Please furnish this office the following articles subject to the terms and conditions contained herein:</p>

        <table class="bff full small" style="width:100%;margin:10px 0">
            <tr>
                <td class="tal vam" style="width:50%;padding:6px;">
                    <strong>Place of Delivery:</strong><br><?php echo $h((string) ($po['place_of_delivery'] ?? '')); ?>
                    <div style="margin-top:14px;"><strong>W/Holding Tax &amp; VAT Charges:</strong> _________________</div>
                </td>
                <td class="tal vam" style="width:50%;padding:6px;">
                    <strong>Delivery Term:</strong><br><?php echo $h((string) ($po['delivery_term'] ?? '')); ?>
                    <div style="margin-top:14px;"><strong>Payment Term:</strong><br><?php echo $h((string) ($po['payment_term'] ?? '')); ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="bff full" style="width:100%;font-size:10pt;table-layout:fixed">
            <thead>
            <tr class="th-b tac">
                <th style="width:9%">Item No.</th>
                <th style="width:11%">Unit</th>
                <th style="width:10%" class="tac">Quantity</th>
                <th>Item Description</th>
                <th style="width:13%">Unit Cost</th>
                <th style="width:13%">Amount</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $ln): ?>
                <tr>
                    <td class="tac"><?php echo $h($ln['line_no']); ?></td>
                    <td class="tac"><?php echo $h($ln['unit']); ?></td>
                    <td class="tac"><?php echo $h($qtyShow($ln['quantity'])); ?></td>
                    <td class="tal"><?php echo $h(mb_strtoupper((string) $ln['description'], 'UTF-8')); ?></td>
                    <td class="tar"><?php echo $h($pfmt((float) $ln['unit_cost'])); ?></td>
                    <td class="tar"><?php echo $h($pfmt((float) $ln['amount'])); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($p = 0; $p < $padPo; ++$p): ?>
                <tr><?php for ($c = 0; $c < 6; ++$c): ?><td>&nbsp;</td><?php endfor; ?></tr><?php endfor; ?>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td class="nf tal" style="padding:10px;text-align:center !important;font-style:italic;font-size:10pt;">
                    *********nothing follows*********
                </td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <?php for ($tail = 0; $tail < 4; ++$tail): ?>
                <tr><?php for ($c = 0; $c < 6; ++$c): ?><td style="padding:6px">&nbsp;</td><?php endfor; ?></tr><?php endfor; ?>
            </tbody>
            <tfoot>
            <tr class="th-b tac">
                <td colspan="5" class="tar">TOTAL</td>
                <td class="tar"><?php echo $h($pfmt($grand)); ?></td>
            </tr>
            </tfoot>
        </table>

        <table class="bff full small" style="width:100%;margin-top:10px;font-size:10pt;">
            <tr>
                <td colspan="6" style="padding:8px;line-height:1.45">
                    <strong>(Total Amount in Words)</strong> <?php echo $h($wordsLine); ?>
                </td>
            </tr>
            <tr>
                <td colspan="6" style="padding:8px;line-height:1.45">
                    <strong>PURPOSE:</strong> <?php echo $purposeLine !== '' ? $h($purposeLine) : '_______________________________'; ?>
                </td>
            </tr>
        </table>

        <div class="po-dashed"></div>

        <p class="small" style="margin:10px 0;text-align:left;">
            <?php echo nl2br($h($penalty)); ?>
        </p>

        <table style="width:100%;margin-top:24px;font-size:10pt" cellpadding="10">
            <tr>
                <td class="vat tac" style="width:52%;vertical-align:top;border:none;">
                    Conforme: (Supplier)<br><br>
                    Signature over Printed Name&nbsp;&nbsp;_______________________________<br>
                    Date:&nbsp;&nbsp;_______________
                </td>
                <td class="vat tac" style="width:48%;vertical-align:top;border:none;">
                    Very truly yours,<br><br><br>
                    <?php echo $mayor !== '' ? '<strong>' . $h(mb_strtoupper($mayor, 'UTF-8')) . '</strong><br/>Municipal Mayor' : '<span style="opacity:.75">_________________________<br/>Municipal Mayor</span>'; ?>
                </td>
            </tr>
        </table>
    </div>

<?php elseif ($formType === 'air' && isset($payload['air'])): ?>
<?php
    $a = $payload['air'];
    $lines = $payload['lines'];
    $linkedPoNum = isset($a['linked_po_number']) ? (string) $a['linked_po_number'] : (string) ($a['po_reference_display'] ?? '');
    $linkedPoDateDisp = isset($a['linked_po_date']) ? $dLong((string) $a['linked_po_date']) : '';
    $siLine = isset($a['si_dr_notes']) ? (string) $a['si_dr_notes'] : 'see attached';
    $reqOffice = isset($a['requisitioning_office_display']) ? (string) $a['requisitioning_office_display'] : '';

    $accTot = isset($a['acceptance_complete']) && (int) $a['acceptance_complete'] !== 0;
    $accPart = !$accTot;
    $chkInspPhrase = isset($a['inspection_ok']) && (int) $a['inspection_ok'] !== 0;

    $padAir = max(0, 16 - count($lines));

    $accName = mb_strtoupper((string) ($a['acceptance_personnel'] ?? ''), 'UTF-8');
    $accTitlePr = isset($a['acceptance_title']) ? (string) $a['acceptance_title'] : '';
    $inspName = mb_strtoupper((string) ($a['inspection_personnel'] ?? ''), 'UTF-8');
    $inspTitlePr = isset($a['inspection_title']) ? (string) $a['inspection_title'] : '';
    $recvName = mb_strtoupper((string) ($a['received_by'] ?? ''), 'UTF-8');
    $recvTitlePr = isset($a['received_title']) ? (string) $a['received_title'] : '';

    $dIns = isset($a['date_inspected']) ? trim((string) $a['date_inspected']) : '';
    $dSig = isset($a['date_signed']) ? trim((string) $a['date_signed']) : '';
?>

    <div class="sheet-sans">
        <div class="air-hdr tac" style="min-height:36px;margin-bottom:4px;">
            <div class="air-num-box">
                <strong>AIR No.</strong><br><?php echo $h((string) $a['air_number']); ?>
            </div>
            <div class="pr-title" style="margin-top:2px;margin-bottom:4px;font-size:12pt;line-height:1.2;">ACCEPTANCE AND<br>INSPECTION REPORT</div>
        </div>
        <div class="tac"><span class="ul-name"><?php echo $h($underlineName); ?></span></div>
        <div class="tac ag-sub-it" style="margin-bottom:10px;"><em>Agency / Procuring Entity</em></div>

        <table class="bff full small" style="width:100%;margin-bottom:8px;">
            <tr>
                <td colspan="4" style="padding:6px;line-height:1.35">
                    <strong>Supplier / Claimant:</strong>
                    <?php echo $h(mb_strtoupper((string) ($a['supplier_or_claimant'] ?? ''), 'UTF-8')); ?>
                </td>
            </tr>
            <tr>
                <td style="padding:6px;width:26%;vertical-align:top;"><strong>PO/JO No.:</strong><br><?php echo $linkedPoNum !== '' ? $h($linkedPoNum) : ''; ?></td>
                <td style="padding:6px;width:26%;vertical-align:top;"><strong>Date:</strong><br><?php echo $linkedPoDateDisp !== '' ? $h($linkedPoDateDisp) : ''; ?></td>
                <td style="padding:6px;width:24%;vertical-align:top;"><strong>SI/DI/OR No.:</strong><br><?php echo $h($siLine); ?></td>
                <td style="padding:6px;width:24%;vertical-align:top;"><strong>Date:</strong><br><?php echo $siLine !== '' ? $h($siLine) : ''; ?></td>
            </tr>
            <tr>
                <td colspan="4" style="padding:6px;line-height:1.35">
                    <strong>Requisitioning Office:</strong>
                    <?php echo $reqOffice !== '' ? $h(mb_strtoupper($reqOffice, 'UTF-8')) : ''; ?>
                </td>
            </tr>
        </table>

        <table class="bff full" style="width:100%;font-size:10pt;table-layout:fixed">
            <thead>
            <tr class="up th-b tac">
                <th style="width:11%">ITEM NO.</th>
                <th style="width:13%">UNIT</th>
                <th>DESCRIPTION</th>
                <th style="width:12%" class="tac">QUANTITY</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $ln): ?>
                <tr>
                    <td class="tac"><?php echo $h($ln['line_no']); ?>.</td>
                    <td class="tac"><?php echo $h($ln['unit']); ?></td>
                    <td class="tal"><?php echo $h(mb_strtoupper((string) ($ln['description'] ?? ''), 'UTF-8')); ?></td>
                    <td class="tac"><?php echo $h($qtyShow($ln['quantity'])); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($pai = 0; $pai < $padAir; ++$pai): ?>
                <tr><?php for ($c = 0; $c < 4; ++$c): ?><td style="min-height:20px">&nbsp;</td><?php endfor; ?></tr><?php endfor; ?>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td class="nf" style="padding:8px">*********nothing follows*********</td>
                <td>&nbsp;</td>
            </tr>
            </tbody>
        </table>

        <table class="air-two small" cellpadding="10" cellspacing="0" style="border-collapse:collapse;width:100%;margin-top:0">
            <tr>
                <td>
                    <div class="th-b up tac" style="margin-bottom:10px;">ACCEPTANCE</div>
                    <div style="margin-bottom:8px;line-height:1.45"><strong>Date Inspected:</strong> <?php echo $dIns !== '' ? $h($dIns) : '_______________________________'; ?></div>
                    <div style="margin:10px 0;line-height:1.85">
                        <?php echo $chk($accTot); ?> <strong>Complete</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <?php echo $chk($accPart); ?> <strong>Partial</strong>
                    </div>
                    <div style="margin-top:18px;" class="tac">
                        <div class="rcv-n"><?php echo $accName !== '' ? $h($accName) : '________________________________________'; ?></div>
                        <?php echo $accTitlePr !== '' ? '<div style="margin-top:4px">'.$h($accTitlePr).'</div>' : ''; ?>
                        <div style="margin-top:18px;line-height:1.45"><strong>Date Signed:</strong> <?php echo $dSig !== '' ? $h($dSig) : '_______________________________'; ?></div>
                    </div>
                </td>
                <td>
                    <div class="th-b up tac" style="margin-bottom:10px;">INSPECTION</div>
                    <div style="margin-bottom:8px;line-height:1.45"><strong>Date Inspected:</strong> _______________________________</div>
                    <div style="margin:10px 0;line-height:1.45">
                        <?php echo $chk($chkInspPhrase); ?>
                        Inspected, Verified and found OK as to quantity and specifications
                    </div>
                    <div style="margin-top:42px;line-height:1.55" class="tac">
                        <div class="rcv-n"><?php echo $inspName !== '' ? $h($inspName) : '________________________________________'; ?></div>
                        <?php echo $inspTitlePr !== '' ? '<div style="margin-top:4px">'.$h($inspTitlePr).'</div>' : '<div style="margin-top:4px">&nbsp;</div>'; ?>
                        <div style="margin-top:18px;line-height:1.45"><strong>Date Signed:</strong> _______________________________</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="rcv-block">
            <div class="tal th-b small">RECEIVED BY:</div>
            <div class="rcv-names">
                <?php echo $recvName !== '' ? '<div class="rcv-n">'.$h($recvName).'</div>' : '<div class="rcv-n">________________________________________</div>'; ?>
                <?php echo $recvTitlePr !== '' ? '<div>'.$h($recvTitlePr).'</div>' : ''; ?>
            </div>
        </div>
    </div>

<?php endif; ?>

<p class="small no-print muted-foot">Generated preview — seals, handwritten dates, budget stamps on hardcopy remain authoritative.</p>
</body>
</html>
