<?php
/**
 * Make consumable PR lines look like real municipal records:
 * merge duplicate items on the same PR, cap quantities, and stop GSO
 * from ordering floor wax on every request.
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts/normalize-consumable-pr-lines.php
 */

@set_time_limit(0);
ini_set('memory_limit', '512M');

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';

function lgueNormItemKey(string $desc, string $unit): string
{
    $d = strtolower(trim(preg_replace('/\s+/', ' ', $desc) ?? $desc));
    $u = strtolower(trim($unit));

    return $d . '|' . $u;
}

function lgueRealisticLineCap(string $desc, string $unit, float $cost, string $code): int
{
    $d = strtolower($desc);
    $u = strtolower(trim($unit));
    $health = in_array($code, ['HLTH', 'RHU'], true);
    $gso = $code === 'GSO';

    if (str_contains($d, 'floor wax')) {
        return $gso ? 3 : 2;
    }
    if (str_contains($d, 'mop')) {
        return 6;
    }
    if (str_contains($d, 'toner')) {
        return $gso ? 3 : 2;
    }
    if (str_contains($d, 'alcohol')) {
        return $health ? 12 : 6;
    }
    if (str_contains($d, 'mask')) {
        return $health ? 8 : 4;
    }
    if (str_contains($d, 'glove')) {
        return $health ? 8 : 6;
    }
    if (str_contains($d, 'blueprint') || str_contains($d, 'usb') || str_contains($d, 'first-aid')) {
        return 2;
    }
    if ($u === 'ream' || str_contains($d, 'bond paper') || str_contains($d, ' forms')) {
        return 5;
    }
    if (str_contains($d, 'seedling') || str_contains($d, 'chlorine')) {
        return 6;
    }
    if (str_contains($d, 'routing slip') || str_contains($d, 'notepad') || str_contains($d, 'highlighter') || str_contains($d, 'folder')) {
        return 12;
    }
    if ($cost >= 1000) {
        return 2;
    }
    if ($cost >= 500) {
        return 4;
    }
    if (in_array($u, ['can', 'gal', 'kg', 'set'], true)) {
        return 4;
    }
    if (in_array($u, ['bottle', 'btl'], true)) {
        return $health ? 10 : 6;
    }
    if (in_array($u, ['pack', 'box', 'roll'], true)) {
        return 8;
    }
    if ($u === 'pc' && $cost >= 100) {
        return 8;
    }
    if ($u === 'pc') {
        return 24;
    }

    return 10;
}

$conn = getDBConnection();
consumableEnsureSchema($conn);

$st = $conn->query(
    "SELECT l.id, l.pr_id, l.line_no, l.quantity, l.unit, l.description, l.est_unit_cost,
            p.pr_date, DATE_FORMAT(p.pr_date, '%Y-%m') AS ym, o.code
     FROM proc_pr_line l
     INNER JOIN proc_pr p ON p.id = l.pr_id
     INNER JOIN offices o ON o.id = p.office_id
     WHERE p.is_consumable = 1
     ORDER BY p.id, l.line_no, l.id"
);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    fwrite(STDERR, "No consumable PR lines.\n");
    exit(1);
}

/** @var array<int, list<array<string,mixed>>> $byPr */
$byPr = [];
foreach ($rows as $r) {
    $byPr[(int) $r['pr_id']][] = $r;
}

$keep = []; // id => ['qty'=>int,'cost'=>float]
$dropIds = [];
$mergedExtra = 0;

foreach ($byPr as $prId => $lines) {
    $groups = [];
    foreach ($lines as $r) {
        $key = lgueNormItemKey((string) $r['description'], (string) $r['unit']);
        $groups[$key][] = $r;
    }
    $code = strtoupper(trim((string) $lines[0]['code']));
    foreach ($groups as $group) {
        usort($group, static fn ($a, $b) => ((int) $a['id']) <=> ((int) $b['id']));
        $keeper = $group[0];
        $kid = (int) $keeper['id'];
        $qty = 0;
        foreach ($group as $g) {
            $qty += max(1, (int) round((float) $g['quantity']));
        }
        $cost = (float) $keeper['est_unit_cost'];
        if ($cost <= 0) {
            $cost = 1.0;
        }
        $qty = min($qty, lgueRealisticLineCap((string) $keeper['description'], (string) $keeper['unit'], $cost, $code));
        $keep[$kid] = [
            'qty' => max(1, $qty),
            'cost' => $cost,
            'pr_id' => $prId,
            'desc' => (string) $keeper['description'],
            'unit' => (string) $keeper['unit'],
            'code' => $code,
            'ym' => (string) $keeper['ym'],
            'pr_date' => (string) $keeper['pr_date'],
        ];
        for ($i = 1, $n = count($group); $i < $n; $i++) {
            $dropIds[(int) $group[$i]['id']] = true;
            $mergedExtra++;
        }
    }
}

// Thin items that currently appear on almost every PR for an office.
$thinRules = [
    ['needle' => 'floor wax', 'codes' => ['GSO'], 'keep_per_month' => 2],
    ['needle' => 'alcohol 70%', 'codes' => ['HLTH', 'RHU'], 'keep_per_month' => 3],
];

foreach ($thinRules as $rule) {
    $codes = $rule['codes'];
    $needle = $rule['needle'];
    $keepN = (int) $rule['keep_per_month'];
    $buckets = [];
    foreach ($keep as $id => $row) {
        if (!empty($dropIds[$id])) {
            continue;
        }
        if (!in_array($row['code'], $codes, true)) {
            continue;
        }
        if (!str_contains(strtolower($row['desc']), $needle)) {
            continue;
        }
        $buckets[$row['code'] . '|' . $row['ym']][] = $id;
    }
    foreach ($buckets as $ids) {
        usort($ids, static function ($a, $b) use ($keep) {
            $da = $keep[$a]['pr_date'] <=> $keep[$b]['pr_date'];

            return $da !== 0 ? $da : ($a <=> $b);
        });
        $ym = $keep[$ids[0]]['ym'] ?? '';
        $allow = str_ends_with($ym, '-09') ? max(1, $keepN - 1) : $keepN;
        $kept = 0;
        foreach ($ids as $id) {
            $prId = (int) $keep[$id]['pr_id'];
            $siblings = 0;
            foreach ($keep as $oid => $orow) {
                if (!empty($dropIds[$oid])) {
                    continue;
                }
                if ((int) $orow['pr_id'] === $prId && $oid !== $id) {
                    $siblings++;
                }
            }
            if ($kept < $allow) {
                $kept++;
                continue;
            }
            if ($siblings > 0) {
                $dropIds[$id] = true;
            }
        }
    }
}

$dropList = array_keys($dropIds);
$updateN = 0;
foreach ($keep as $id => $row) {
    if (!empty($dropIds[$id])) {
        continue;
    }
    $updateN++;
}

echo "Merge extras to drop: {$mergedExtra}\n";
echo "Thin/drop lines: " . count($dropList) . "\n";
echo "Keep/update lines: {$updateN}\n";

$updLine = $conn->prepare('UPDATE proc_pr_line SET quantity = ?, est_line_total = ?, line_no = ? WHERE id = ?');
$updIss = $conn->prepare('UPDATE issuance_consumable_lines SET quantity = ? WHERE proc_pr_line_id = ?');
$updPo = $conn->prepare('UPDATE proc_po_line SET quantity = ?, amount = ROUND(unit_cost * ?, 2), line_no = ?, description = ?, unit = ? WHERE pr_line_id = ?');
$updQl = $conn->prepare('UPDATE proc_quotation_line SET line_total = ROUND(unit_price * ?, 2) WHERE pr_line_id = ?');
$updMoves = $conn->prepare(
    "UPDATE consumable_stock_moves SET quantity = ? WHERE proc_pr_line_id = ? AND move_type IN ('restock','release')"
);

$delQl = $conn->prepare('DELETE FROM proc_quotation_line WHERE pr_line_id = ?');
$delPo = $conn->prepare('DELETE FROM proc_po_line WHERE pr_line_id = ?');
$delIss = $conn->prepare('DELETE FROM issuance_consumable_lines WHERE proc_pr_line_id = ?');
$delMoves = $conn->prepare('DELETE FROM consumable_stock_moves WHERE proc_pr_line_id = ?');
$selPoLine = $conn->prepare('SELECT po_id, line_no FROM proc_po_line WHERE pr_line_id = ?');
$delAirMatch = $conn->prepare(
    'DELETE al FROM proc_air_line al
     INNER JOIN proc_air a ON a.id = al.air_id
     WHERE a.po_id = ? AND al.line_no = ?'
);
$delPrLine = $conn->prepare('DELETE FROM proc_pr_line WHERE id = ?');

$conn->beginTransaction();
try {
    foreach ($dropList as $id) {
        $id = (int) $id;
        $selPoLine->execute([$id]);
        foreach ($selPoLine->fetchAll(PDO::FETCH_ASSOC) as $pol) {
            $delAirMatch->execute([(int) $pol['po_id'], (int) $pol['line_no']]);
        }
        $delQl->execute([$id]);
        $delPo->execute([$id]);
        $delIss->execute([$id]);
        $delMoves->execute([$id]);
        $delPrLine->execute([$id]);
    }

    $perPr = [];
    foreach ($keep as $id => $row) {
        if (!empty($dropIds[$id])) {
            continue;
        }
        $perPr[(int) $row['pr_id']][] = $id;
    }
    foreach ($perPr as $prId => $ids) {
        usort($ids, static fn ($a, $b) => $a <=> $b);
        $n = 1;
        foreach ($ids as $id) {
            $qty = (int) $keep[$id]['qty'];
            $total = round($qty * (float) $keep[$id]['cost'], 2);
            $updLine->execute([$qty, $total, $n, $id]);
            $updIss->execute([$qty, $id]);
            $updPo->execute([$qty, $qty, $n, $keep[$id]['desc'], $keep[$id]['unit'], $id]);
            $updQl->execute([$qty, $id]);
            $updMoves->execute([$qty, $id]);
            $n++;
        }
    }

    $conn->exec(
        "UPDATE proc_quotation q
         INNER JOIN proc_pr p ON p.id = q.pr_id
         SET q.total_amount = (
            SELECT COALESCE(SUM(ql.line_total), 0) FROM proc_quotation_line ql WHERE ql.quotation_id = q.id
         )
         WHERE p.is_consumable = 1"
    );
    $conn->exec(
        "UPDATE proc_po po
         INNER JOIN proc_pr p ON p.id = po.pr_id
         SET po.total_amount = (
            SELECT COALESCE(SUM(pol.amount), 0) FROM proc_po_line pol WHERE pol.po_id = po.id
         )
         WHERE p.is_consumable = 1"
    );

    $conn->exec(
        "DELETE al FROM proc_air_line al
         INNER JOIN proc_air a ON a.id = al.air_id
         INNER JOIN proc_po po ON po.id = a.po_id
         INNER JOIN proc_pr p ON p.id = po.pr_id
         WHERE p.is_consumable = 1"
    );
    $conn->exec(
        "INSERT INTO proc_air_line (air_id, line_no, quantity, unit, description)
         SELECT a.id, pol.line_no, pol.quantity, pol.unit, pol.description
         FROM proc_air a
         INNER JOIN proc_po po ON po.id = a.po_id
         INNER JOIN proc_pr p ON p.id = po.pr_id
         INNER JOIN proc_po_line pol ON pol.po_id = po.id
         WHERE p.is_consumable = 1"
    );

    $conn->exec(
        "INSERT INTO consumable_stock (item_id, current_stock)
         SELECT DISTINCT item_id, 0 FROM consumable_stock_moves
         ON DUPLICATE KEY UPDATE current_stock = current_stock"
    );
    $conn->exec(
        "UPDATE consumable_stock s
         SET current_stock = (
            SELECT COALESCE(SUM(
                CASE WHEN m.move_type = 'release' THEN -m.quantity ELSE m.quantity END
            ), 0)
            FROM consumable_stock_moves m
            WHERE m.item_id = s.item_id
         )"
    );

    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'Normalize failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Applied.\n\n";

echo "Sample GSO PR-2026-260173 after:\n";
$sample = $conn->query(
    "SELECT l.line_no, l.description, ROUND(l.quantity,0) qty, l.unit, ROUND(l.est_line_total,0) peso
     FROM proc_pr_line l
     INNER JOIN proc_pr p ON p.id = l.pr_id
     WHERE p.pr_number = 'PR-2026-260173'
     ORDER BY l.line_no"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($sample as $r) {
    echo "  {$r['line_no']}. {$r['description']} x{$r['qty']} {$r['unit']}  ₱{$r['peso']}\n";
}

$wax = $conn->query(
    "SELECT COUNT(DISTINCT p.id) prs, ROUND(SUM(l.quantity),0) cans, ROUND(SUM(l.est_line_total)/8,0) avg_mo
     FROM proc_pr_line l
     INNER JOIN proc_pr p ON p.id = l.pr_id
     INNER JOIN offices o ON o.id = p.office_id
     WHERE p.is_consumable = 1 AND o.code = 'GSO' AND LOWER(l.description) LIKE '%floor wax%'
       AND p.pr_date >= '2026-01-01' AND p.pr_date < '2026-09-01'"
)->fetch(PDO::FETCH_ASSOC);
echo "\nGSO floor wax Jan–Aug: {$wax['prs']} PRs, {$wax['cans']} cans, ₱" . number_format((float) $wax['avg_mo']) . "/month avg\n";

$dups = $conn->query(
    "SELECT COUNT(*) c FROM (
        SELECT p.id FROM proc_pr_line l
        INNER JOIN proc_pr p ON p.id = l.pr_id
        WHERE p.is_consumable = 1
        GROUP BY p.id, LOWER(TRIM(l.description)), LOWER(TRIM(l.unit))
        HAVING COUNT(*) > 1
     ) t"
)->fetchColumn();
echo "Remaining duplicate item groups: {$dups}\n";

$empty = $conn->query(
    "SELECT COUNT(*) FROM proc_pr p
     WHERE p.is_consumable = 1
       AND NOT EXISTS (SELECT 1 FROM proc_pr_line l WHERE l.pr_id = p.id)"
)->fetchColumn();
echo "Empty PRs: {$empty}\n";

$gso = $conn->query(
    "SELECT ROUND(SUM(l.est_line_total)/8,0) avg_mo
     FROM proc_pr p INNER JOIN proc_pr_line l ON l.pr_id=p.id INNER JOIN offices o ON o.id=p.office_id
     WHERE p.is_consumable=1 AND o.code='GSO' AND p.pr_date>='2026-01-01' AND p.pr_date<'2026-09-01'"
)->fetchColumn();
echo "GSO avg/month now ₱" . number_format((float) $gso) . "\n";
