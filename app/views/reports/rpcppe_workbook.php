<?php
/**
 * PPE Physical Count Report — official RPCPPE layout (same as the Excel template).
 *
 * @var array  $segments
 * @var float  $grandBookTotal
 * @var array  $meta
 * @var string $category
 */
if (!isset($segments, $grandBookTotal)) {
    exit('Invalid report context');
}
$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$meta = isset($meta) && is_array($meta) ? $meta : [];
$canEdit = class_exists('PermissionHelper') && PermissionHelper::can('edit');
$canPrint = !class_exists('PermissionHelper') || PermissionHelper::can('print');

$typeLabel = (string) ($meta['type_label'] ?? 'Office Equipment, Furniture, Agricultural Equipment and Technical & Scientific Equipment');
$officerName = (string) ($meta['officer_name'] ?? '');
$officerDesig = (string) ($meta['officer_designation'] ?? '');
$lguName = (string) ($meta['lgu_name'] ?? 'Municipal Government of Lumban, Laguna');
$assumptionDate = (string) ($meta['assumption_date'] ?? '');
$asOf = (string) ($meta['as_of'] ?? date('F j, Y'));
$approverName = (string) ($meta['approver_name'] ?? '');
$approverDesig = (string) ($meta['approver_designation'] ?? 'Municipal Mayor');
$preparedName = (string) ($meta['prepared_name'] ?? (defined('RPCPPE_ACCOUNTABLE_OFFICER') ? RPCPPE_ACCOUNTABLE_OFFICER : $officerName));
$preparedDesig = (string) ($meta['prepared_designation'] ?? (defined('RPCPPE_ACCOUNTABLE_DESIGNATION') ? RPCPPE_ACCOUNTABLE_DESIGNATION : 'GSO - Designate / PRO III'));
$certifiedName = (string) ($meta['certified_name'] ?? $officerName);
$certifiedDesig = (string) ($meta['certified_designation'] ?? $officerDesig);

$defaultOfficeId = 0;
foreach ($segments as $seg) {
    foreach ($seg['items'] as $it) {
        if (!empty($it['office_id'])) {
            $defaultOfficeId = (int) $it['office_id'];
            break 2;
        }
    }
}
if ($defaultOfficeId <= 0 && !empty($_SESSION['user_office_id'])) {
    $defaultOfficeId = (int) $_SESSION['user_office_id'];
}

if (!isset($tabReports) || !is_array($tabReports) || $tabReports === []) {
    $tabReports = [[
        'tab' => (string) ($_GET['tab'] ?? 'RPCPPE'),
        'type_label' => $typeLabel,
        'segments' => $segments,
        'grand' => $grandBookTotal,
        'rows' => function_exists('physical_count_rpcse_boot_rows')
            ? physical_count_rpcse_boot_rows($segments, $defaultOfficeId)
            : [],
    ]];
}

$sheetCount = count($tabReports);
$xlsxQ = http_build_query(array_filter([
    'category' => 'PPE',
    'format' => 'xlsx',
    'status' => $_GET['status'] ?? null,
    'search' => $_GET['search'] ?? null,
    'tab' => $sheetCount === 1 ? ($tabReports[0]['tab'] ?? null) : null,
    'tabs' => $sheetCount > 1 ? implode(',', array_map(static function ($r) {
        return (string) ($r['tab'] ?? '');
    }, $tabReports)) : null,
]));
$apiSave = function_exists('lgue_url') ? lgue_url('api/physical-count-save.php') : '../api/physical-count-save.php';
$xlsxHref = 'print-physical-count-report.php?' . $xlsxQ;
$jsSheets = [];
foreach ($tabReports as $rep) {
    $jsSheets[] = [
        'tab' => (string) ($rep['tab'] ?? ''),
        'rows' => $rep['rows'] ?? [],
    ];
}

$bootRows = [];
if ($jsSheets !== [] && !empty($jsSheets[0]['rows'])) {
    $bootRows = $jsSheets[0]['rows'];
} else {
foreach ($segments as $seg) {
    $title = trim((string) ($seg['title'] ?? ''));
    $lastArticle = '';
    if (!empty($seg['explicit']) && $title !== '') {
        $bootRows[] = [
            'type' => 'section',
            'title' => function_exists('mb_strtoupper') ? mb_strtoupper($title, 'UTF-8') : strtoupper($title),
            'item_group' => $title,
        ];
        $lastArticle = '';
    }
    foreach ($seg['items'] as $item) {
        $qc = pc_book_qty($item);
        $rawPhys = $item['quantity_physical_count'] ?? null;
        $qp = ($rawPhys === null || $rawPhys === '') ? $qc : (int) $rawPhys;
        $uv = (float) ($item['unit_value'] ?? 0);
        $vq = $qp - $qc;
        $article = trim((string) ($item['article'] ?? ''));
        if ($article !== '' && $title !== '' && strcasecmp($article, $title) === 0) {
            $article = '';
        }
        $articleCell = '';
        if ($article !== '' && $article !== $lastArticle) {
            $articleCell = $article;
            $lastArticle = $article;
        }
        $bootRows[] = [
            'type' => 'item',
            'id' => (int) ($item['id'] ?? 0),
            'office_id' => (int) ($item['office_id'] ?? $defaultOfficeId),
            'article' => $articleCell,
            'description' => (string) ($item['description'] ?? ''),
            'property_number' => (string) ($item['property_number'] ?? ''),
            'unit_of_measure' => (string) (($item['unit_of_measure'] ?? '') !== '' ? $item['unit_of_measure'] : 'pc'),
            'unit_value' => $uv,
            'quantity' => $qc,
            'quantity_physical_count' => $qp,
            'remarks' => (string) ($item['remarks'] ?? ''),
            'item_group' => (string) ($item['item_group'] ?? $title),
            'var_qty' => $vq,
            'var_val' => $vq * $uv,
        ];
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPCPPE — Physical Count of PPE</title>
    <style>
        :root {
            --ink: #000;
            --bd: #000;
            --muted: #333;
            --paper: #fff;
            --screen: #b8bec8;
            --edit: #1d4ed8;
            --ok: #15803d;
            --warn: #b45309;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px 10px 48px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: var(--ink);
            background: var(--screen);
            line-height: 1.25;
        }
        body.mode-print .edit-only { display: none !important; }
        body.mode-edit .print-only { display: none !important; }
        body.mode-print .sheet { box-shadow: none; }
        body.mode-print [contenteditable="true"] { caret-color: transparent; }
        body.mode-print [contenteditable="true"]:focus { background: transparent !important; outline: none; }

        .toolbar {
            max-width: 1280px;
            margin: 0 auto 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            background: #1e293b;
            color: #fff;
            padding: 10px 12px;
            border-radius: 8px;
        }
        .toolbar .brand { font-weight: 700; font-size: 13px; margin-right: 8px; }
        .toolbar button, .toolbar a.btn {
            border: 0;
            border-radius: 5px;
            padding: 8px 12px;
            font: 600 12px/1.2 Arial, sans-serif;
            cursor: pointer;
            background: #334155;
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .toolbar button.primary { background: var(--edit); }
        .toolbar button.success { background: var(--ok); }
        .toolbar button.warn { background: var(--warn); }
        .toolbar button:disabled { opacity: .55; cursor: not-allowed; }
        .toolbar .status {
            margin-left: auto;
            font-size: 12px;
            opacity: .95;
            max-width: 42%;
            text-align: right;
        }
        .toolbar .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            background: #0f172a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .badge.edit { color: #93c5fd; }
        .badge.print { color: #86efac; }

        .sheet {
            max-width: 1280px;
            margin: 0 auto;
            background: var(--paper);
            padding: 16px 18px 28px;
            border: 1px solid #7a8290;
            box-shadow: 0 6px 22px rgba(0,0,0,.14);
            overflow: visible;
        }
        .sheet + .sheet { margin-top: 28px; }
        .sheet-banner {
            margin: 0 0 10px;
            padding: 6px 8px;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #1e3a8a;
            font-size: 11px;
            font-weight: 700;
        }
        .annex { text-align: right; font-size: 9pt; font-weight: 700; margin: 0 0 2px; }
        .gov-head { text-align: center; margin: 0 0 6px; line-height: 1.35; }
        .gov-head .republic, .gov-head .province { font-size: 9.5pt; font-weight: 700; }
        .gov-head .muni { font-size: 10.5pt; font-weight: 800; text-transform: uppercase; }
        .doc-title {
            text-align: center;
            font-family: "Arial Black", Arial, sans-serif;
            font-size: 14pt;
            font-weight: 400;
            text-transform: uppercase;
            margin: 8px 0 6px;
            line-height: 1.2;
        }
        .type-line {
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            font-weight: 700;
            font-style: italic;
            text-decoration: underline;
            margin: 0 0 2px;
        }
        .type-caption { text-align: center; font-size: 10pt; font-style: normal; margin: 0 0 6px; }
        .as-of { text-align: center; font-size: 10pt; font-weight: 700; margin: 0 0 12px; }

        .meta-row {
            display: grid;
            grid-template-columns: 1fr 1.35fr;
            gap: 12px 28px;
            margin: 0 0 10px;
        }
        .meta-cell { text-align: center; }
        .meta-cell .val {
            display: block;
            font-weight: 700;
            min-height: 1.25em;
            border-bottom: 1px solid #000;
            padding: 2px 6px;
        }
        .meta-cell .cap { font-size: 8.5pt; font-style: italic; margin-top: 2px; color: var(--muted); }
        .fund-row { margin: 0 0 10px; font-size: 10pt; }
        .fund-row .val {
            display: inline-block;
            min-width: 14em;
            border-bottom: 1px solid #000;
            padding: 0 4px;
            font-weight: 600;
        }
        .acct-block { margin: 0 0 14px; font-size: 10pt; line-height: 1.45; }
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

        [contenteditable="true"] { outline: none; }
        body.mode-edit [contenteditable="true"]:focus { background: #fff8c6; }
        body.mode-edit td[contenteditable="true"]:hover,
        body.mode-edit .val:hover,
        body.mode-edit .uline:hover { background: #f8fafc; }

        .g-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8pt;
            margin-top: 4px;
        }
        .g-table th, .g-table td {
            border: 1px solid var(--bd);
            padding: 4px 5px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }
        .g-table thead th {
            font-weight: 700;
            text-align: center;
            background: #f0f0f0;
            vertical-align: middle;
            white-space: normal;
            line-height: 1.15;
        }
        .g-table td.num { text-align: center; white-space: nowrap; }
        .g-table td.money { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
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
        .c-act { width: 36px; }

        tr.pc-section-head td {
            font-weight: 700;
            font-size: 9pt;
            text-transform: uppercase;
            background: #fff;
        }
        tr.pc-section-head td.pc-section-title { text-align: left; }
        tr.pc-section-head td.pc-section-subtotal { text-align: right; font-variant-numeric: tabular-nums; }
        tr.pc-item td.desc-cell { min-height: 1.4em; }
        tr.pc-grand-total td {
            font-weight: 700;
            font-style: italic;
            font-size: 9pt;
            background: #f5f5f5;
        }
        tr.pc-grand-total td.pc-grand-label { text-align: right; padding-right: 8px; font-weight: 700; font-style: italic; }
        tr.pc-grand-total td.pc-grand-sub { text-align: right; font-variant-numeric: tabular-nums; }

        body.mode-edit tr.pc-item:hover,
        body.mode-edit tr.pc-section-head:hover { outline: 1px solid #93c5fd; outline-offset: -1px; }
        .row-del {
            border: 0;
            background: transparent;
            color: #b91c1c;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            padding: 0;
            width: 100%;
        }
        body.mode-print .c-act, body.mode-print .row-del { display: none !important; }

        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 26px;
            page-break-inside: avoid;
        }
        .sign-box { text-align: center; min-height: 125px; padding: 4px 6px; }
        .sign-box .lbl { text-align: left; font-weight: 700; margin-bottom: 28px; font-size: 9pt; }
        .sign-box .name {
            display: inline-block;
            min-width: 72%;
            font-weight: 700;
            font-size: 10pt;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding: 2px 8px;
        }
        .sign-box .desig { font-size: 8.5pt; margin: 4px 0 8px; }
        .sign-box .sig-cap { font-size: 7.5pt; color: var(--muted); line-height: 1.25; }
        .sign-box .date-line { text-align: left; margin-top: 14px; font-size: 9pt; }
        .sign-box .date-line .val {
            display: inline-block;
            min-width: 8em;
            border-bottom: 1px solid #000;
            padding: 0 4px;
        }

        .hint {
            margin-top: 14px;
            text-align: center;
            font-size: 11px;
            color: #334155;
        }

        @media (max-width: 960px) {
            .sign-grid { grid-template-columns: 1fr; }
            .meta-row { grid-template-columns: 1fr; }
            .toolbar .status { max-width: 100%; text-align: left; margin-left: 0; }
        }
        @media print {
            body { background: #fff !important; padding: 0 !important; }
            .toolbar, .hint, .edit-only, .c-act, .row-del { display: none !important; }
            .sheet { box-shadow: none !important; border: none !important; max-width: none !important; padding: 0 !important; }
            .sheet + .sheet { margin-top: 0 !important; page-break-before: always; break-before: page; }
            .sheet-banner { display: none !important; }
            [contenteditable="true"]:focus { background: transparent !important; }
            thead { display: table-row-group; }
            tr { page-break-inside: avoid; }
            @page { size: legal landscape; margin: 8mm 6mm; }
        }
    </style>
</head>
<body class="<?php echo $canEdit ? 'mode-edit' : 'mode-print'; ?>">
<div class="toolbar no-print">
    <span class="brand">PPE Physical Count</span>
    <span class="badge <?php echo $canEdit ? 'edit' : 'print'; ?>" id="modeBadge"><?php echo $canEdit ? 'EDIT MODE' : 'PRINT / PREVIEW'; ?></span>
    <?php if ($canEdit): ?>
    <button type="button" class="primary" id="btnToggleMode">Switch to Print / Preview</button>
    <?php if ($sheetCount === 1): ?>
    <button type="button" class="edit-only" id="btnAddSection">+ Category section</button>
    <button type="button" class="edit-only" id="btnAddRow">+ Item row</button>
    <?php endif; ?>
    <button type="button" class="success edit-only" id="btnSave">Save to database</button>
    <?php endif; ?>
    <?php if ($canPrint): ?>
    <button type="button" id="btnPrint">Print / PDF</button>
    <?php endif; ?>
    <a class="btn" href="<?php echo $h($xlsxHref); ?>">Export Excel (official template)</a>
    <button type="button" onclick="window.close()">Close</button>
    <div class="status" id="statusMsg"><?php echo $sheetCount > 1
        ? $h($sheetCount . ' RPCPPE sheets — one official form per selected tab, stacked below.')
        : 'Official RPCPPE layout. Edit cells like Excel; later printed pages continue without repeating the header.'; ?></div>
</div>

<?php foreach ($tabReports as $sheetIndex => $rep):
    $tabName = (string) ($rep['tab'] ?? 'RPCPPE');
    $sid = preg_replace('/[^A-Za-z0-9_-]/', '-', $tabName) ?: ('tab-' . (int) $sheetIndex);
    $sheetType = trim((string) ($rep['type_label'] ?? $typeLabel));
?>
<div class="sheet rpcse-sheet" id="rpcseSheet-<?php echo $h($sid); ?>" data-tab="<?php echo $h($tabName); ?>">
    <?php if ($sheetCount > 1): ?>
    <div class="sheet-banner no-print">RPCPPE sheet <?php echo (int) $sheetIndex + 1; ?> of <?php echo (int) $sheetCount; ?> — <?php echo $h($tabName); ?></div>
    <?php endif; ?>
    <h1 class="doc-title" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>">REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT</h1>
    <p class="type-line" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($sheetType); ?></p>
    <p class="type-caption" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>">(Type of Property, Plant and Equipment)</p>
    <p class="as-of">As of <span id="fldAsOf" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($asOf); ?></span></p>

    <div class="acct-block">
        For which <span class="uline" id="fldOfficer" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($officerName !== '' ? $officerName : "\u{00a0}"); ?></span>
        <span class="uline" id="fldDesig" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($officerDesig !== '' ? $officerDesig : "\u{00a0}"); ?></span>
        <span class="uline" id="fldEntity" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($lguName !== '' ? $lguName : "\u{00a0}"); ?></span>
        is accountable, having assumed such<br>
        <span class="cap-under">(Name of Accountable Officer) &nbsp;&nbsp; (Official Designation) &nbsp;&nbsp; (LGU)</span>
        accountability on <span class="uline" id="fldAssumption" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($assumptionDate !== '' ? $assumptionDate : $asOf); ?></span><br>
        <span class="cap-under" style="margin-left:8.5em">(Date of Assumption)</span>
    </div>

    <table class="g-table" id="rpcseTable">
        <thead>
            <tr>
                <th rowspan="2" class="c-art">ARTICLE</th>
                <th rowspan="2" class="c-desc">DESCRIPTION</th>
                <th rowspan="2" class="c-prop">PROPERTY NUMBER</th>
                <th rowspan="2" class="c-uom">UNIT OF<br>MEASURE</th>
                <th rowspan="2" class="c-val">UNIT VALUE</th>
                <th class="c-qc">BALANCE PER<br>CARD</th>
                <th class="c-qp">ON HAND PER<br>COUNT</th>
                <th colspan="2">SHORTAGE/OVERAGE</th>
                <th rowspan="2" class="c-rem">REMARKS</th>
                <?php if ($canEdit): ?><th rowspan="2" class="c-act edit-only"></th><?php endif; ?>
            </tr>
            <tr>
                <th class="num">(Quantity)</th>
                <th class="num">(Quantity)</th>
                <th class="c-sq num">Quantity</th>
                <th class="c-sv">Value</th>
            </tr>
        </thead>
        <tbody class="rpcse-body" id="rpcseBody-<?php echo $h($sid); ?>">
            <!-- filled by JS -->
            <tr class="pc-grand-total" id="rpcseTotalRow-<?php echo $h($sid); ?>">
                <td></td>
                <td></td>
                <td class="pc-grand-label">TOTAL</td>
                <td></td>
                <td class="pc-grand-sub money" id="rpcseGrandTotal-<?php echo $h($sid); ?>">0.00</td>
                <td></td><td></td><td></td><td></td><td></td>
                <?php if ($canEdit): ?><td class="edit-only"></td><?php endif; ?>
            </tr>
        </tbody>
    </table>

    <div class="sign-grid">
        <div class="sign-box">
            <div class="lbl">Prepared by:</div>
            <div class="name" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($preparedName !== '' ? $preparedName : "\u{00a0}"); ?></div>
            <div class="desig" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($preparedDesig !== '' ? $preparedDesig : "\u{00a0}"); ?></div>
            <div class="sig-cap">Signature over Printed Name of Inventory Committee Chair and Members</div>
            <div class="date-line">Date: <span class="val" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>">&nbsp;</span></div>
        </div>
        <div class="sign-box">
            <div class="lbl">Approved by:</div>
            <div class="name" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($approverName !== '' ? $approverName : "\u{00a0}"); ?></div>
            <div class="desig" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>"><?php echo $h($approverDesig !== '' ? $approverDesig : "\u{00a0}"); ?></div>
            <div class="sig-cap">Signature over Printed Name of Head of Agency/Entity or Authorized Representative</div>
            <div class="date-line">Date: <span class="val" contenteditable="<?php echo $canEdit ? 'true' : 'false'; ?>">&nbsp;</span></div>
        </div>
    </div>

    <p class="hint edit-only no-print">Category rows hold section book totals (Σ unit value × balance per card). Shortage/Overage recalculates when quantities or unit value change. Save writes item rows to the GSO database.</p>
</div>
<?php endforeach; ?>

<script>
(function () {
    const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
    const DEFAULT_OFFICE_ID = <?php echo (int) $defaultOfficeId; ?>;
    const SAVE_URL = <?php echo json_encode($apiSave, JSON_UNESCAPED_SLASHES); ?>;
    const SHEETS = <?php echo json_encode($jsSheets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const statusEl = document.getElementById('statusMsg');
    const modeBadge = document.getElementById('modeBadge');
    const btnToggle = document.getElementById('btnToggleMode');
    const sheetStates = [];

    function money(n) {
        const x = Number(n) || 0;
        return x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function parseNum(s) {
        if (s == null) return 0;
        const t = String(s).replace(/,/g, '').replace(/[^\d.-]/g, '').trim();
        if (t === '' || t === '—') return 0;
        const n = parseFloat(t);
        return isNaN(n) ? 0 : n;
    }
    function isDonationUv(s) {
        return String(s == null ? '' : s).replace(/\u00a0/g, ' ').trim().toUpperCase() === 'DONATION';
    }
    function parseUv(s) {
        return isDonationUv(s) ? 0 : parseNum(s);
    }
    function parseIntSafe(s) {
        return Math.round(parseNum(s));
    }
    function textOf(el) {
        return (el && (el.innerText || el.textContent) || '').replace(/\u00a0/g, ' ').trim();
    }
    function setStatus(msg, kind) {
        statusEl.textContent = msg || '';
        statusEl.style.color = kind === 'error' ? '#fecaca' : (kind === 'ok' ? '#bbf7d0' : '#fff');
    }
    function editableAttr() {
        return document.body.classList.contains('mode-edit') && CAN_EDIT ? 'true' : 'false';
    }
    function syncEditableFlags() {
        const on = editableAttr();
        document.querySelectorAll('.rpcse-sheet [data-ce]').forEach(function (el) {
            el.setAttribute('contenteditable', on);
        });
        document.querySelectorAll('.rpcse-body [contenteditable]').forEach(function (el) {
            if (el.closest('.pc-grand-total')) return;
            el.setAttribute('contenteditable', on);
        });
    }

    function currentItemGroup(beforeRow, totalRow) {
        let group = '';
        let node = beforeRow ? beforeRow.previousElementSibling : (totalRow ? totalRow.previousElementSibling : null);
        while (node) {
            if (node.classList.contains('pc-section-head')) {
                group = textOf(node.querySelector('[data-field="section_title"]')) || '';
                break;
            }
            node = node.previousElementSibling;
        }
        return group;
    }

    function makeSectionRow(title, itemGroup) {
        const tr = document.createElement('tr');
        tr.className = 'pc-section-head';
        tr.dataset.rowType = 'section';
        tr.dataset.itemGroup = itemGroup || title || '';
        const act = CAN_EDIT
            ? '<td class="edit-only c-act"><button type="button" class="row-del" title="Remove section header">×</button></td>'
            : '';
        tr.innerHTML =
            '<td colspan="4" class="pc-section-title" data-field="section_title" data-ce contenteditable="' + editableAttr() + '">' + escapeHtml(title || 'NEW CATEGORY') + '</td>' +
            '<td class="pc-section-subtotal money" data-field="section_subtotal">0.00</td>' +
            '<td></td><td></td><td></td><td></td><td></td>' + act;
        return tr;
    }

    let tempSeq = 0;
    function makeItemRow(row) {
        row = row || {};
        const tr = document.createElement('tr');
        tr.className = 'pc-item';
        tr.dataset.rowType = 'item';
        tr.dataset.id = String(row.id || '');
        tr.dataset.tempKey = row.temp_key || ('tmp-' + (++tempSeq));
        tr.dataset.officeId = String(row.office_id || '');
        tr.dataset.itemGroup = row.item_group || '';
        tr.dataset.articleFull = row.article_full || row.article || '';
        const ce = editableAttr();
        const act = CAN_EDIT
            ? '<td class="edit-only c-act"><button type="button" class="row-del" title="Remove row">×</button></td>'
            : '';
        const uv = isDonationUv(row.unit_value) ? 'DONATION' : (row.unit_value != null ? money(row.unit_value) : '');
        const qc = row.quantity != null ? String(row.quantity) : '';
        const qp = row.quantity_physical_count != null ? String(row.quantity_physical_count) : '';
        const vq = row.var_qty != null ? String(row.var_qty) : '';
        const vv = row.var_val != null && row.var_val !== 0 ? money(row.var_val) : (row.var_qty === 0 ? '—' : '');
        tr.innerHTML =
            '<td data-field="article" contenteditable="' + ce + '">' + escapeHtml(row.article || '') + '</td>' +
            '<td class="desc-cell" data-field="description" contenteditable="' + ce + '">' + escapeHtml(row.description || '') + '</td>' +
            '<td data-field="property_number" contenteditable="' + ce + '" style="font-family:Consolas,\'Courier New\',monospace;font-size:7.5pt">' + escapeHtml(row.property_number || '') + '</td>' +
            '<td class="num" data-field="unit_of_measure" contenteditable="' + ce + '">' + escapeHtml(row.unit_of_measure || 'pc') + '</td>' +
            '<td class="money" data-field="unit_value" contenteditable="' + ce + '">' + escapeHtml(uv) + '</td>' +
            '<td class="num" data-field="quantity" contenteditable="' + ce + '">' + escapeHtml(qc) + '</td>' +
            '<td class="num" data-field="quantity_physical_count" contenteditable="' + ce + '">' + escapeHtml(qp) + '</td>' +
            '<td class="num" data-field="var_qty" contenteditable="' + ce + '">' + escapeHtml(vq === '0' ? '—' : vq) + '</td>' +
            '<td class="money" data-field="var_val" contenteditable="' + ce + '">' + escapeHtml(vv || (vq === '0' ? '—' : '')) + '</td>' +
            '<td data-field="remarks" contenteditable="' + ce + '">' + escapeHtml(row.remarks || '') + '</td>' +
            act;
        return tr;
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function field(tr, name) {
        return tr.querySelector('[data-field="' + name + '"]');
    }

    function recalcRow(tr) {
        if (!tr || tr.dataset.rowType !== 'item') return;
        const uvText = textOf(field(tr, 'unit_value'));
        const donation = isDonationUv(uvText);
        const uv = donation ? 0 : parseNum(uvText);
        const qc = parseIntSafe(textOf(field(tr, 'quantity')));
        const qp = parseIntSafe(textOf(field(tr, 'quantity_physical_count')));
        const vq = qp - qc;
        const vv = vq * uv;
        const vqEl = field(tr, 'var_qty');
        const vvEl = field(tr, 'var_val');
        if (vqEl) vqEl.textContent = vq === 0 ? '—' : String(vq);
        if (vvEl) vvEl.textContent = vq === 0 ? '—' : money(vv);
        if (!donation) field(tr, 'unit_value').textContent = money(uv);
    }

    function recalcSheet(state) {
        let grand = 0;
        let sectionSum = 0;
        let sectionCell = null;
        const rows = Array.from(state.bodyEl.querySelectorAll('tr'));
        rows.forEach(function (tr) {
            if (tr.classList.contains('pc-grand-total')) return;
            if (tr.dataset.rowType === 'section') {
                if (sectionCell) sectionCell.textContent = money(sectionSum);
                sectionSum = 0;
                sectionCell = field(tr, 'section_subtotal');
                return;
            }
            if (tr.dataset.rowType === 'item') {
                recalcRow(tr);
                const uv = parseUv(textOf(field(tr, 'unit_value')));
                const qc = parseIntSafe(textOf(field(tr, 'quantity')));
                const book = uv * qc;
                sectionSum += book;
                grand += book;
            }
        });
        if (sectionCell) sectionCell.textContent = money(sectionSum);
        if (state.grandEl) state.grandEl.textContent = money(grand);
    }

    function recalcAll() {
        sheetStates.forEach(recalcSheet);
    }

    function insertBeforeTotal(state, tr) {
        state.bodyEl.insertBefore(tr, state.totalRow);
    }

    function renderSheet(state, initialRows) {
        const rows = initialRows || [];
        rows.forEach(function (row) {
            if (row.type === 'section') {
                insertBeforeTotal(state, makeSectionRow(row.title, row.item_group));
            } else {
                insertBeforeTotal(state, makeItemRow(row));
            }
        });
        if (!rows.length) {
            insertBeforeTotal(state, makeSectionRow('OFFICE FIXTURES & FURNITURES', 'OFFICE FIXTURES & FURNITURES'));
            insertBeforeTotal(state, makeItemRow({
                article: '',
                description: '',
                property_number: '',
                unit_of_measure: 'pc',
                unit_value: 0,
                quantity: 1,
                quantity_physical_count: 1,
                remarks: '',
                item_group: 'OFFICE FIXTURES & FURNITURES',
                office_id: state.officeId
            }));
        }
        recalcSheet(state);
    }

    function collectRows() {
        const out = [];
        sheetStates.forEach(function (state) {
        let group = '';
        let lastArticle = '';
        state.bodyEl.querySelectorAll('tr').forEach(function (tr) {
            if (tr.dataset.rowType === 'section') {
                group = textOf(field(tr, 'section_title'));
                tr.dataset.itemGroup = group;
                lastArticle = '';
                return;
            }
            if (tr.dataset.rowType !== 'item') return;
            const desc = textOf(field(tr, 'description'));
            const pn = textOf(field(tr, 'property_number'));
            const articleRaw = textOf(field(tr, 'article'));
            if (!desc && !pn && !articleRaw) return;
            if (articleRaw) {
                lastArticle = articleRaw;
                tr.dataset.articleFull = articleRaw;
            }
            const article = articleRaw || tr.dataset.articleFull || lastArticle;
            if (article && !lastArticle) lastArticle = article;
            const g = tr.dataset.itemGroup || group || currentItemGroup(tr, state.totalRow);
            const rowId = tr.dataset.id ? parseInt(tr.dataset.id, 10) : 0;
            out.push({
                id: rowId > 0 ? rowId : null,
                temp_key: tr.dataset.tempKey || '',
                office_id: tr.dataset.officeId ? parseInt(tr.dataset.officeId, 10) : state.officeId,
                article: article,
                description: desc || '—',
                property_number: pn,
                unit_of_measure: textOf(field(tr, 'unit_of_measure')) || 'pc',
                unit_value: isDonationUv(textOf(field(tr, 'unit_value'))) ? 'DONATION' : parseNum(textOf(field(tr, 'unit_value'))),
                quantity: Math.max(1, parseIntSafe(textOf(field(tr, 'quantity')))),
                quantity_physical_count: parseIntSafe(textOf(field(tr, 'quantity_physical_count'))),
                remarks: textOf(field(tr, 'remarks')),
                item_group: g
            });
        });
        });
        return out;
    }

    async function saveToDb() {
        if (!CAN_EDIT) return;
        const rows = collectRows();
        if (!rows.length) {
            setStatus('Nothing to save — add at least one item row.', 'error');
            return;
        }
        if (!DEFAULT_OFFICE_ID && rows.some(function (r) { return !r.id && !r.office_id; })) {
            setStatus('Set an office on items in the inventory list first (needed for new rows).', 'error');
            return;
        }
        setStatus('Saving to database…');
        const btn = document.getElementById('btnSave');
        if (btn) btn.disabled = true;
        try {
            const res = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    category: 'PPE',
                    default_office_id: DEFAULT_OFFICE_ID,
                    rows: rows
                })
            });
            const rawText = await res.text();
            let data = null;
            try {
                data = JSON.parse(rawText);
            } catch (parseErr) {
                setStatus('Save failed (HTTP ' + res.status + '). Sign in again if your session expired.', 'error');
                return;
            }
            if (!data.success) {
                setStatus((data.message || 'Save failed') + (data.errors && data.errors[0] ? (' — ' + data.errors[0]) : ''), 'error');
                return;
            }
            if (data.created_items && data.created_items.length) {
                data.created_items.forEach(function (c) {
                    let tr = null;
                    sheetStates.forEach(function (state) {
                        if (tr) return;
                        if (c.temp_key) {
                            tr = state.bodyEl.querySelector('tr.pc-item[data-temp-key="' + CSS.escape(c.temp_key) + '"]');
                        }
                        if (!tr && c.property_number) {
                            state.bodyEl.querySelectorAll('tr.pc-item').forEach(function (candidate) {
                                if (tr) return;
                                if (textOf(field(candidate, 'property_number')) === c.property_number) tr = candidate;
                            });
                        }
                    });
                    if (!tr) return;
                    tr.dataset.id = String(c.id);
                    const pnEl = field(tr, 'property_number');
                    if (pnEl && !textOf(pnEl)) pnEl.textContent = c.property_number;
                });
            }
            setStatus(
                (data.message || 'Saved.') + (data.errors && data.errors[0] ? (' — ' + data.errors[0]) : ''),
                (data.errors && data.errors.length) ? 'error' : 'ok'
            );
        } catch (e) {
            setStatus('Save failed: ' + (e && e.message ? e.message : 'network error'), 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function setMode(edit) {
        if (!CAN_EDIT) return;
        document.body.classList.toggle('mode-edit', edit);
        document.body.classList.toggle('mode-print', !edit);
        modeBadge.textContent = edit ? 'EDIT MODE' : 'PRINT / PREVIEW';
        modeBadge.className = 'badge ' + (edit ? 'edit' : 'print');
        if (btnToggle) btnToggle.textContent = edit ? 'Switch to Print / Preview' : 'Switch to Edit Mode';
        syncEditableFlags();
        setStatus(edit
            ? 'Edit Mode — click cells to edit, add rows, then Save to database.'
            : 'Print / Preview — form matches the official RPCPPE layout. Use Print / PDF.');
    }

    document.querySelectorAll('.rpcse-body').forEach(function (bodyEl) {
        bodyEl.addEventListener('input', function (e) {
            const tr = e.target.closest('tr.pc-item');
            if (!tr) {
                if (e.target.closest('tr.pc-section-head')) recalcAll();
                return;
            }
            const f = e.target.getAttribute('data-field');
            if (['unit_value', 'quantity', 'quantity_physical_count'].indexOf(f) !== -1) {
                recalcRow(tr);
                recalcAll();
            }
        });
        bodyEl.addEventListener('blur', function (e) {
            const tr = e.target.closest('tr.pc-item');
            if (!tr) return;
            const f = e.target.getAttribute('data-field');
            if (f === 'unit_value') {
                if (!isDonationUv(textOf(e.target))) {
                    e.target.textContent = money(parseNum(textOf(e.target)));
                }
                recalcAll();
            }
        }, true);
        bodyEl.addEventListener('click', function (e) {
            const btn = e.target.closest('.row-del');
            if (!btn) return;
            const tr = btn.closest('tr');
            if (!tr || tr.classList.contains('pc-grand-total')) return;
            tr.remove();
            recalcAll();
        });
    });

    if (btnToggle) {
        btnToggle.addEventListener('click', function () {
            setMode(!document.body.classList.contains('mode-edit'));
        });
    }
    const btnAddRow = document.getElementById('btnAddRow');
    if (btnAddRow) {
        btnAddRow.addEventListener('click', function () {
            const state = sheetStates[0];
            if (!state) return;
            const group = currentItemGroup(null, state.totalRow);
            const tr = makeItemRow({
                article: '',
                description: '',
                property_number: '',
                unit_of_measure: 'pc',
                unit_value: 0,
                quantity: 1,
                quantity_physical_count: 1,
                remarks: '',
                item_group: group,
                office_id: state.officeId
            });
            insertBeforeTotal(state, tr);
            recalcAll();
            const desc = field(tr, 'description');
            if (desc) desc.focus();
        });
    }
    const btnAddSection = document.getElementById('btnAddSection');
    if (btnAddSection) {
        btnAddSection.addEventListener('click', function () {
            const state = sheetStates[0];
            if (!state) return;
            const title = window.prompt('Category / section title', 'NEW CATEGORY');
            if (title == null) return;
            const t = title.trim() || 'NEW CATEGORY';
            insertBeforeTotal(state, makeSectionRow(t.toUpperCase(), t));
            recalcAll();
        });
    }
    const btnSave = document.getElementById('btnSave');
    if (btnSave) btnSave.addEventListener('click', saveToDb);
    const btnPrint = document.getElementById('btnPrint');
    if (btnPrint) {
        btnPrint.addEventListener('click', function () {
            if (CAN_EDIT && document.body.classList.contains('mode-edit')) {
                setMode(false);
                setTimeout(function () { window.print(); }, 50);
            } else {
                window.print();
            }
        });
    }

    document.querySelectorAll('.rpcse-sheet .val, .rpcse-sheet .uline, .rpcse-sheet .type-line, .rpcse-sheet .doc-title, .rpcse-sheet .gov-head div, .rpcse-sheet .annex, .rpcse-sheet .type-caption, .rpcse-sheet .sign-box .name, .rpcse-sheet .sign-box .desig, .rpcse-sheet .as-of span').forEach(function (el) {
        el.setAttribute('data-ce', '1');
    });

    document.querySelectorAll('.rpcse-sheet').forEach(function (sheetEl, idx) {
        const tab = sheetEl.getAttribute('data-tab') || '';
        const pack = SHEETS[idx] || SHEETS.find(function (s) { return s.tab === tab; }) || { rows: [] };
        const state = {
            officeId: DEFAULT_OFFICE_ID,
            tab: tab,
            sheetEl: sheetEl,
            bodyEl: sheetEl.querySelector('.rpcse-body'),
            totalRow: sheetEl.querySelector('.pc-grand-total'),
            grandEl: sheetEl.querySelector('.pc-grand-sub')
        };
        if (!state.bodyEl || !state.totalRow) return;
        sheetStates.push(state);
        renderSheet(state, pack.rows || []);
    });
    if (!CAN_EDIT) setMode(false);
})();
</script>
</body>
</html>
