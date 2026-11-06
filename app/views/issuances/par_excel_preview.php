<?php
/**
 * Excel-style in-browser view of the filled official PAR workbook.
 *
 * @var array $issuance
 * @var array $preview
 * @var int $issuanceId
 */
if (!isset($preview) || !is_array($preview)) {
    exit('Invalid preview context');
}
$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$id = (int) ($issuanceId ?? ($_GET['id'] ?? 0));
$formNo = (string) ($issuance['form_number'] ?? 'PAR');
$xlsxHref = 'print-issuance.php?id=' . $id . '&format=xlsx';
$canEdit = !empty($canEdit);
$cols = $preview['cols'] ?? [];
$letters = $preview['letters'] ?? [];
$rows = $preview['rows'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View <?php echo $h($formNo); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Calibri, Arial, sans-serif;
            background: #217636;
            color: #111;
        }
        .bar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            padding: 10px 14px;
            background: #185c2b;
            color: #fff;
        }
        .bar strong { margin-right: 6px; }
        .bar button, .bar a {
            border: 0;
            border-radius: 4px;
            padding: 8px 14px;
            font: 600 13px Arial, sans-serif;
            cursor: pointer;
            background: #fff;
            color: #185c2b;
            text-decoration: none;
        }
        .bar button.ghost, .bar a.ghost { background: #0f3d1c; color: #fff; }
        .bar button.save { background: #facc15; color: #1a1a1a; }
        .bar .status { font-size: 12px; margin-left: 6px; }
        .bar .file {
            font-size: 12px;
            opacity: .92;
            margin-left: 4px;
        }
        .wrap {
            padding: 16px;
            overflow: auto;
            min-height: calc(100vh - 52px);
        }
        .book {
            background: #fff;
            box-shadow: 0 2px 14px rgba(0,0,0,.28);
            display: inline-block;
            min-width: 100%;
        }
        table.xl {
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12px;
        }
        table.xl th.colh, table.xl td.rnh {
            background: #e7e6e6;
            color: #444;
            font: 600 11px Arial, sans-serif;
            text-align: center;
            border: 1px solid #b1b1b1;
            padding: 2px 4px;
        }
        table.xl td.cell {
            border: 1px solid #d0d0d0;
            padding: 3px 6px;
            vertical-align: middle;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
        }
        table.xl td.cell[contenteditable="true"] { outline: none; cursor: text; }
        table.xl td.cell[contenteditable="true"]:focus { background: #fff8d5; box-shadow: inset 0 0 0 1px #c4a000; }
        table.xl td.center { text-align: center; }
        table.xl td.right { text-align: right; font-variant-numeric: tabular-nums; }
        table.xl td.bold { font-weight: 700; }
        table.xl td.title {
            font-size: 16px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .tabs {
            display: flex;
            background: #f3f3f3;
            border-top: 1px solid #c6c6c6;
            padding: 4px 8px 0;
        }
        .tabs span {
            background: #fff;
            border: 1px solid #c6c6c6;
            border-bottom: 0;
            padding: 5px 14px;
            font: 600 12px Arial, sans-serif;
            color: #217636;
        }
        @media print {
            body { background: #fff; }
            .bar, .tabs { display: none !important; }
            .wrap { padding: 0; }
            .book { box-shadow: none; }
            @page { size: landscape; margin: 8mm; }
        }
    </style>
</head>
<body>
<div class="bar no-print">
    <strong>View PAR</strong>
    <span class="file"><?php echo $h($formNo); ?> · official Excel<?php echo $canEdit ? ' · click any cell to edit' : ''; ?></span>
    <?php if ($canEdit): ?>
    <button type="button" class="save" id="par-save" onclick="saveParExcel()">Save</button>
    <?php endif; ?>
    <button type="button" onclick="window.print()">Print</button>
    <a href="<?php echo $h($xlsxHref); ?>">Download Excel</a>
    <a class="ghost" href="#" onclick="window.close(); return false;">Close</a>
    <span class="status" id="par-status"></span>
</div>
<div class="wrap">
    <div class="book">
        <table class="xl">
            <colgroup>
                <col style="width:36px">
                <?php foreach ($cols as $w): ?>
                    <col style="width:<?php echo max(28, (int) round($w * 8.2)); ?>px">
                <?php endforeach; ?>
            </colgroup>
            <thead>
                <tr>
                    <th class="colh"></th>
                    <?php foreach ($letters as $letter): ?>
                        <th class="colh"><?php echo $h($letter); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr style="height:<?php echo max(16, (int) round(($row['h'] ?? 18) * 1.25)); ?>px">
                    <td class="rnh"><?php echo (int) $row['n']; ?></td>
                    <?php foreach ($row['cells'] as $cell):
                        $cls = 'cell';
                        if (!empty($cell['center'])) {
                            $cls .= ' center';
                        }
                        if (!empty($cell['right'])) {
                            $cls .= ' right';
                        }
                        if (!empty($cell['bold'])) {
                            $cls .= ' bold';
                        }
                        if (!empty($cell['title'])) {
                            $cls .= ' title';
                        }
                        $cs = (int) ($cell['cs'] ?? 1);
                        $rs = (int) ($cell['rs'] ?? 1);
                        ?>
                        <td class="<?php echo $cls; ?>"
                            data-addr="<?php echo $h((string) ($cell['addr'] ?? '')); ?>"
                            data-raw="<?php echo $h((string) ($cell['raw'] ?? '')); ?>"
                            data-display="<?php echo $h((string) ($cell['t'] ?? '')); ?>"
                            <?php echo $canEdit ? ' contenteditable="true" spellcheck="false"' : ''; ?>
                            <?php echo $cs > 1 ? ' colspan="' . $cs . '"' : ''; ?>
                            <?php echo $rs > 1 ? ' rowspan="' . $rs . '"' : ''; ?>
                        ><?php echo $h($cell['t'] ?? ''); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="tabs"><span><?php echo $h((string) ($preview['title'] ?? 'PAR')); ?></span></div>
    </div>
</div>
<?php if ($canEdit): ?>
<script>
function saveParExcel() {
    var status = document.getElementById('par-status');
    var btn = document.getElementById('par-save');
    var cells = {};
    document.querySelectorAll('td.cell[data-addr]').forEach(function (td) {
        var addr = (td.getAttribute('data-addr') || '').trim();
        if (!addr) return;
        var text = (td.innerText || '').replace(/\r\n/g, '\n').trim();
        var original = (td.getAttribute('data-display') || '').trim();
        var raw = (td.getAttribute('data-raw') || '').trim();
        cells[addr] = (text === original && raw !== '') ? raw : text;
    });
    if (btn) btn.disabled = true;
    if (status) status.textContent = 'Saving…';
    fetch('print-issuance.php?id=<?php echo (int) $id; ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ cells: cells })
    }).then(function (r) { return r.json(); }).then(function (res) {
        if (btn) btn.disabled = false;
        if (status) status.textContent = res.success ? 'Saved' : (res.message || 'Save failed');
        if (res.success) {
            document.querySelectorAll('td.cell[data-addr]').forEach(function (td) {
                td.setAttribute('data-display', (td.innerText || '').trim());
            });
        }
    }).catch(function () {
        if (btn) btn.disabled = false;
        if (status) status.textContent = 'Save failed';
    });
}
document.querySelectorAll('td.cell[contenteditable="true"]').forEach(function (td) {
    td.addEventListener('input', function () {
        var status = document.getElementById('par-status');
        if (status) status.textContent = 'Unsaved changes';
    });
});
</script>
<?php endif; ?>
</body>
</html>
