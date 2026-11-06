<?php
/**
 * Sync the RPCSE GSO tab into property_items (SE, office GSO) in Excel order.
 * Usage: C:\xampp\php\php.exe scripts/import-gso-rpcse.php [path-to.xlsx]
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from command line.\n");
    exit(1);
}

set_time_limit(180);
require_once dirname(__DIR__) . '/bootstrap.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once dirname(__DIR__) . '/includes/items_import_helpers.php';

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "PhpSpreadsheet is not installed. Run composer install.\n");
    exit(1);
}
require_once $autoload;

$candidates = array_slice($argv, 1);
if (!$candidates) {
    $candidates = [
        'D:\\Downloads\\RPCSP-semi-expendable-2025.xlsx',
        'd:\\Downloads\\RPCSP-semi-expendable-2025.xlsx',
        dirname(__DIR__) . '/storage/demo/RPCSP-semi-expendable-2025.xlsx',
    ];
}

$path = null;
foreach ($candidates as $c) {
    if (is_file($c)) {
        $path = $c;
        break;
    }
}
if ($path === null) {
    fwrite(STDERR, "GSO workbook not found. Pass the .xlsx path as the first argument.\n");
    exit(1);
}

$conn = getDBConnection();
$officeSt = $conn->query("SELECT id FROM offices WHERE code = 'GSO' OR name LIKE '%General Services%' ORDER BY (code = 'GSO') DESC LIMIT 1");
$officeId = (int) $officeSt->fetchColumn();
if ($officeId <= 0) {
    fwrite(STDERR, "GSO office row is missing.\n");
    exit(1);
}

$reader = PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($path);
$sheet = $spreadsheet->getSheetByName('GSO');
if ($sheet === null) {
    fwrite(STDERR, "Workbook has no GSO sheet.\n");
    exit(1);
}

$highestRow = (int) $sheet->getHighestRow();
$currentGroup = '';
$parsed = [];
$sortByGroup = [];

for ($r = 1; $r <= $highestRow; $r++) {
    $a = trim((string) $sheet->getCell('A' . $r)->getValue());
    $b = trim((string) $sheet->getCell('B' . $r)->getValue());
    $c = trim((string) $sheet->getCell('C' . $r)->getValue());
    $d = trim((string) $sheet->getCell('D' . $r)->getValue());
    $eVal = $sheet->getCell('E' . $r)->getValue();
    $fVal = $sheet->getCell('F' . $r)->getValue();
    $gVal = $sheet->getCell('G' . $r)->getValue();
    $jRemarks = trim((string) $sheet->getCell('J' . $r)->getValue());

    if ($a === '' && $b === '' && $c === '' && ($eVal === null || $eVal === '')) {
        continue;
    }
    if (str_contains(strtoupper($a . $b), 'ARTICLE') && str_contains(strtoupper($a . $b), 'DESCRIPTION')) {
        continue;
    }
    if (str_contains(strtoupper($a . $b), 'NOTHING FOLLOWS') || preg_match('/^\s*TOTAL\b/i', $a) === 1) {
        continue;
    }
    if (importItemsIsSignatureOrFooterRow($a, $b, $c, $jRemarks)) {
        continue;
    }
    if ($a !== '' && $c === '' && $d === '' && $b === '' && $eVal !== null && $eVal !== '') {
        $currentGroup = $a;
        continue;
    }
    if ($b === '' && $c === '') {
        continue;
    }
    if ($currentGroup === '') {
        continue;
    }

    $unitValue = is_numeric($eVal) ? (float) $eVal : (float) preg_replace('/[^0-9.\-]/', '', (string) $eVal);
    $qtyCard = is_numeric($fVal) ? (int) $fVal : 1;
    $qtyPhys = ($gVal !== null && $gVal !== '' && is_numeric($gVal)) ? (int) $gVal : null;
    if ($qtyCard < 1) {
        $qtyCard = 1;
    }

    $group = $currentGroup;
    if (!isset($sortByGroup[$group])) {
        $sortByGroup[$group] = 0;
    } else {
        $sortByGroup[$group]++;
    }

    $parsed[] = [
        'row' => $r,
        'article' => importItemsNormalizeArticle($a !== '' ? $a : null),
        'description' => $b !== '' ? $b : '—',
        'property_number' => $c,
        'uom' => $d !== '' ? $d : 'pc',
        'unit_value' => $unitValue,
        'quantity' => $qtyCard,
        'quantity_physical_count' => $qtyPhys,
        'item_group' => $group,
        'sort_order' => $sortByGroup[$group],
        'remarks' => $jRemarks !== '' ? $jRemarks : null,
    ];
}

if (!$parsed) {
    fwrite(STDERR, "No GSO item rows found in the workbook.\n");
    exit(1);
}

$existingSt = $conn->prepare(
    "SELECT id, property_number, article, description, unit_of_measure, unit_value, quantity,
            quantity_physical_count, item_group, sort_order, remarks, status
     FROM property_items
     WHERE office_id = ? AND category = 'SE'"
);
$existingSt->execute([$officeId]);
$existing = $existingSt->fetchAll(PDO::FETCH_ASSOC);
$byId = [];
$byTail = [];
foreach ($existing as $row) {
    $byId[(int) $row['id']] = $row;
    $tail = importItemsPropertyNumberTail((string) $row['property_number']);
    if ($tail !== '') {
        $byTail[$tail][] = (int) $row['id'];
    }
}

$usedIds = [];
$updated = 0;
$created = 0;
$deleted = 0;

$upd = $conn->prepare(
    "UPDATE property_items
     SET article = ?, description = ?, unit_of_measure = ?, unit_value = ?, quantity = ?,
         quantity_physical_count = ?, item_group = ?, sort_order = ?, remarks = ?
     WHERE id = ? AND office_id = ? AND category = 'SE'"
);

$conn->beginTransaction();
try {
    foreach ($parsed as $item) {
        $matchId = null;
        $tail = importItemsPropertyNumberTail($item['property_number']);
        if ($tail !== '' && !empty($byTail[$tail])) {
            foreach ($byTail[$tail] as $candidateId) {
                if (!isset($usedIds[$candidateId])) {
                    $matchId = $candidateId;
                    break;
                }
            }
        }

        if ($matchId) {
            $upd->execute([
                $item['article'],
                $item['description'],
                $item['uom'],
                $item['unit_value'],
                $item['quantity'],
                $item['quantity_physical_count'],
                $item['item_group'],
                $item['sort_order'],
                $item['remarks'],
                $matchId,
                $officeId,
            ]);
            $usedIds[$matchId] = true;
            $updated++;
            continue;
        }

        $res = importItemsInsertRow(
            $conn,
            'SE',
            $officeId,
            $item['article'],
            $item['description'],
            $item['uom'],
            $item['unit_value'],
            $item['quantity'],
            $item['quantity_physical_count'],
            $item['item_group'],
            $item['property_number'],
            $item['remarks']
        );
        if (($res['status'] ?? '') === 'created') {
            $newId = (int) ($res['id'] ?? $conn->lastInsertId());
            if ($newId > 0) {
                $usedIds[$newId] = true;
            }
            $created++;
        }
    }

    foreach ($existing as $row) {
        $id = (int) $row['id'];
        if (isset($usedIds[$id])) {
            continue;
        }
        $pn = (string) $row['property_number'];
        $desc = (string) $row['description'];
        $isJunk = importItemsIsSignatureOrFooterRow('', $desc, $pn, (string) ($row['remarks'] ?? ''))
            || preg_match('/^\d{4}-\d{2}-/', $pn) !== 1;
        if (!$isJunk) {
            echo "KEEP extra GSO row id={$id} pn={$pn} desc={$desc}\n";
            continue;
        }

        $linked = 0;
        foreach (['issuance_records', 'return_records', 'disposal_records'] as $tbl) {
            try {
                $st = $conn->prepare("SELECT COUNT(*) FROM {$tbl} WHERE property_item_id = ?");
                $st->execute([$id]);
                $linked += (int) $st->fetchColumn();
            } catch (PDOException $e) {
                // Table may not exist.
            }
        }
        if ($linked > 0) {
            echo "SKIP delete id={$id} (linked transaction)\n";
            continue;
        }

        $conn->prepare('DELETE FROM unserviceable_records WHERE property_item_id = ?')->execute([$id]);
        try {
            $conn->prepare('DELETE FROM property_tags WHERE property_item_id = ?')->execute([$id]);
        } catch (PDOException $e) {
            // Table may not exist on older installs.
        }
        try {
            $conn->prepare('DELETE FROM inventory_history WHERE property_item_id = ?')->execute([$id]);
        } catch (PDOException $e) {
            // History is optional.
        }
        $conn->prepare("DELETE FROM property_items WHERE id = ? AND office_id = ? AND category = 'SE'")->execute([$id, $officeId]);
        $deleted++;
        echo "DELETED junk id={$id} pn={$pn}\n";
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "GSO sync from {$path}\n";
echo 'parsed=' . count($parsed) . " updated={$updated} created={$created} deleted={$deleted}\n";

$check = $conn->prepare(
    "SELECT item_group, sort_order, article, LEFT(description, 48) d, property_number
     FROM property_items
     WHERE office_id = ? AND category = 'SE'
     ORDER BY FIELD(item_group,
        'OFFICE FIXTURES & FURNITURES',
        'OFFICE EQUIPMENTS, TOOLS & APPLIANCES',
        'IT EQUIPMENTS'
     ), sort_order, id"
);
$check->execute([$officeId]);
foreach ($check->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo ($row['item_group'] ?? '') . ' | so=' . $row['sort_order'] . ' | art=' . ($row['article'] ?? '') . ' | ' . $row['d'] . "\n";
}
