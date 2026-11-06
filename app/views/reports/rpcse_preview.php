<?php
/**
 * Inspect the filled client RPCSE workbook before download.
 *
 * @var PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
 * @var string $downloadQuery
 */
if (!isset($spreadsheet) || !is_object($spreadsheet)) {
    exit('Invalid preview context');
}
$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$sheets = [];
foreach ($spreadsheet->getAllSheets() as $idx => $ws) {
    $title = $ws->getTitle();
    $highestRow = min((int) $ws->getHighestRow(), 450);
    $highestCol = $ws->getHighestColumn();
    $maxColIndex = min(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol), 10);
    $rows = [];
    for ($r = 1; $r <= max($highestRow, 17); $r++) {
        $cells = [];
        $has = false;
        for ($c = 1; $c <= $maxColIndex; $c++) {
            $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r;
            $cell = $ws->getCell($addr);
            try {
                $text = (string) $cell->getFormattedValue();
            } catch (Throwable $e) {
                $raw = $cell->getValue();
                $text = is_scalar($raw) || $raw === null ? (string) $raw : '';
            }
            $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
            if ($text !== '') {
                $has = true;
            }
            $cells[] = $text;
        }
        if ($has || $r <= 17) {
            $rows[] = ['n' => $r, 'cells' => $cells];
        }
    }
    $sheets[] = [
        'i' => $idx,
        'title' => $title,
        'rows' => $rows,
        'cols' => $maxColIndex,
    ];
}
$colLetters = [];
for ($c = 1; $c <= 10; $c++) {
    $colLetters[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
}
$downloadHref = 'print-physical-count-report.php?' . $downloadQuery;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspect RPCSE — rpcse-template.xlsx</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #217346;
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
            background: #1e3a5f;
            color: #fff;
        }
        .bar strong { margin-right: 8px; }
        .bar button, .bar a.btn {
            border: 0;
            border-radius: 5px;
            padding: 8px 14px;
            font: 600 13px Arial, sans-serif;
            cursor: pointer;
            background: #15803d;
            color: #fff;
            text-decoration: none;
        }
        .bar button.secondary, .bar a.secondary { background: #475569; }
        .bar .hint { font-size: 12px; opacity: .9; margin-left: 6px; }
        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
            background: #e8e8e8;
            border-bottom: 1px solid #9ca3af;
            padding: 0 8px 0 8px;
            overflow-x: auto;
        }
        .tabs button {
            border: 1px solid #9ca3af;
            border-bottom: 0;
            background: #d1d5db;
            padding: 8px 12px;
            margin: 6px 2px 0;
            cursor: pointer;
            font: 600 12px Arial, sans-serif;
            border-radius: 4px 4px 0 0;
            white-space: nowrap;
        }
        .tabs button.on {
            background: #fff;
            color: #217346;
        }
        .wrap {
            background: #c6c6c6;
            padding: 12px;
            min-height: calc(100vh - 90px);
        }
        .sheet {
            display: none;
            background: #fff;
            overflow: auto;
            max-height: calc(100vh - 130px);
            box-shadow: 0 2px 10px rgba(0,0,0,.15);
        }
        .sheet.on { display: block; }
        table {
            border-collapse: collapse;
            min-width: 100%;
            font-size: 11px;
        }
        th, td {
            border: 1px solid #c5c5c5;
            padding: 3px 6px;
            vertical-align: top;
            white-space: pre-wrap;
            max-width: 280px;
        }
        th {
            background: #f3f3f3;
            text-align: center;
            font-weight: 700;
            position: sticky;
            top: 0;
        }
        td.rn {
            background: #f3f3f3;
            text-align: center;
            color: #666;
            width: 36px;
            max-width: 36px;
        }
        tr.hdr td { font-weight: 700; text-align: center; }
    </style>
</head>
<body>
<div class="bar">
    <strong>Inspect filled RPCSE</strong>
    <a class="btn" href="<?php echo $h($downloadHref); ?>">Download Excel</a>
    <button type="button" class="secondary" onclick="window.close()">Close</button>
    <span class="hint">This is your client workbook after system dates and inventory were written in. Check each office tab, then download.</span>
</div>
<div class="tabs" role="tablist">
    <?php foreach ($sheets as $i => $s): ?>
        <button type="button" class="<?php echo $i === 0 ? 'on' : ''; ?>" data-tab="<?php echo (int) $s['i']; ?>"><?php echo $h($s['title']); ?></button>
    <?php endforeach; ?>
</div>
<div class="wrap">
    <?php foreach ($sheets as $i => $s): ?>
        <div class="sheet <?php echo $i === 0 ? 'on' : ''; ?>" id="sh-<?php echo (int) $s['i']; ?>">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <?php for ($c = 0; $c < $s['cols']; $c++): ?>
                            <th><?php echo $h($colLetters[$c] ?? ''); ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($s['rows'] as $row): ?>
                    <tr class="<?php echo ((int) $row['n'] <= 17) ? 'hdr' : ''; ?>">
                        <td class="rn"><?php echo (int) $row['n']; ?></td>
                        <?php foreach ($row['cells'] as $cell): ?>
                            <td><?php echo $h($cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.tabs button').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tabs button').forEach(function (b) { b.classList.remove('on'); });
        document.querySelectorAll('.sheet').forEach(function (s) { s.classList.remove('on'); });
        btn.classList.add('on');
        var el = document.getElementById('sh-' + btn.getAttribute('data-tab'));
        if (el) el.classList.add('on');
    });
});
</script>
</body>
</html>
