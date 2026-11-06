<?php
/**
 * Make consumable PR spending look like municipal office supplies.
 * Lowers quantities (never unit prices), then scales each office-month
 * toward the capstone monthly budget map. Syncs quotes, PO, AIR, issuance, stock.
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts/rescale-consumable-spend.php
 */

@set_time_limit(0);
ini_set('memory_limit', '512M');

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_pr_forecast.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';

function lgueSpendMonthFactor(int $officeId, string $ym): float
{
    $parts = explode('-', $ym);
    $month = (int) ($parts[1] ?? 1);
    $seed = ($officeId * 10007) + ($month * 9176) + 42;
    $jitter = ($seed % 41) / 100.0; // 0.00–0.40

    return 0.78 + $jitter; // 0.78–1.18
}

function lgueSpendQtyCap(string $desc, string $unit, float $unitCost, float $budget, string $code): int
{
    $d = strtolower($desc);
    $u = strtolower(trim($unit));
    $ops = in_array($code, ['GSO', 'HLTH', 'RHU', 'MDRRMO', 'LWS', 'ENRO', 'SWM', 'MARKET'], true);
    $health = in_array($code, ['HLTH', 'RHU'], true);
    $gso = $code === 'GSO';

    $cap = 40;
    if ($unitCost >= 1000.0 || str_contains($d, 'toner')) {
        $cap = $gso ? 6 : 3;
    } elseif ($unitCost >= 600.0 || str_contains($d, 'printer ink') || str_contains($d, 'usb')) {
        $cap = $ops ? 6 : 4;
    } elseif (str_contains($d, 'first-aid') || str_contains($d, 'blueprint')) {
        $cap = $ops ? 4 : 2;
    } elseif (str_contains($d, 'floor wax') || str_contains($d, 'extension cord')) {
        $cap = $gso ? 10 : 6;
    } elseif ($u === 'ream' || str_contains($d, 'bond paper') || str_contains($d, ' forms')) {
        $cap = $ops ? 10 : 6;
    } elseif (str_contains($d, 'alcohol') || str_contains($d, 'mask') || str_contains($d, 'glove')) {
        $cap = $health ? 36 : ($ops ? 18 : 12);
    } elseif (in_array($u, ['can', 'gal', 'kg', 'set'], true)) {
        $cap = $ops ? 12 : 8;
    } elseif (in_array($u, ['bottle', 'btl'], true)) {
        $cap = $health ? 24 : 12;
    } elseif (in_array($u, ['pack', 'box', 'roll'], true)) {
        $cap = $ops ? 16 : 10;
    } elseif ($u === 'pc' && $unitCost >= 100.0) {
        $cap = $ops ? 16 : 10;
    } elseif ($u === 'pc') {
        $cap = 48;
    }

    if ($unitCost > 0 && $budget > 0) {
        $pesoCap = (int) floor(($budget * 0.16) / $unitCost);
        $cap = min($cap, max(1, $pesoCap));
    }

    return max(1, $cap);
}

function lgueSpendLinePeso(array $row): float
{
    return round(max(1, (int) $row['qty']) * (float) $row['cost'], 2);
}

function lgueSpendMonthTotal(array $lines): float
{
    $sum = 0.0;
    foreach ($lines as $row) {
        if (!empty($row['drop'])) {
            continue;
        }
        $sum += lgueSpendLinePeso($row);
    }

    return $sum;
}

$conn = getDBConnection();
consumableEnsureSchema($conn);

$map = consumableDeptBudgetByCode();

$st = $conn->query(
    "SELECT l.id, l.pr_id, l.line_no, l.quantity, l.unit, l.description, l.est_unit_cost, l.est_line_total,
            p.office_id, DATE_FORMAT(p.pr_date, '%Y-%m') AS ym, p.pr_date,
            o.code, o.name AS office_name
     FROM proc_pr_line l
     INNER JOIN proc_pr p ON p.id = l.pr_id
     INNER JOIN offices o ON o.id = p.office_id
     WHERE p.is_consumable = 1
     ORDER BY p.office_id, ym, p.id, l.line_no, l.id"
);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    fwrite(STDERR, "No consumable PR lines found.\n");
    exit(1);
}

$work = [];
$groupKeys = [];
foreach ($rows as $r) {
    $code = strtoupper(trim((string) $r['code']));
    $budget = (float) ($map[$code] ?? 0);
    if ($budget <= 0) {
        $budget = consumableDeptMonthlyBudget($code, (string) $r['office_name']);
    }
    $cost = (float) $r['est_unit_cost'];
    if ($cost <= 0) {
        $cost = 1.0;
    }
    $oldQty = max(1, (int) round((float) $r['quantity']));
    $cap = lgueSpendQtyCap((string) $r['description'], (string) $r['unit'], $cost, $budget, $code);
    $qty = min($oldQty, $cap);
    $ym = (string) $r['ym'];
    $oid = (int) $r['office_id'];
    $gkey = $oid . '|' . $ym;
    $work[] = [
        'id' => (int) $r['id'],
        'pr_id' => (int) $r['pr_id'],
        'line_no' => (int) $r['line_no'],
        'desc' => (string) $r['description'],
        'unit' => (string) $r['unit'],
        'cost' => $cost,
        'old_qty' => $oldQty,
        'qty' => $qty,
        'budget' => $budget,
        'code' => $code,
        'office_id' => $oid,
        'ym' => $ym,
        'drop' => false,
    ];
    $groupKeys[$gkey] = true;
}

foreach (array_keys($groupKeys) as $gkey) {
    [$oidStr, $ym] = explode('|', $gkey, 2);
    $oid = (int) $oidStr;
    $idxs = [];
    $budget = 0.0;
    foreach ($work as $i => $row) {
        if ((int) $row['office_id'] === $oid && $row['ym'] === $ym) {
            $idxs[] = $i;
            $budget = (float) $row['budget'];
        }
    }
    if ($budget <= 0 || !$idxs) {
        continue;
    }

    $factor = lgueSpendMonthFactor($oid, $ym);
    $isSep = str_ends_with($ym, '-09');
    $target = $budget * $factor;
    if ($isSep) {
        $target = $budget * (0.34 + ((($oid * 13) % 12) / 100.0)); // ~34–45% (incomplete month)
    }

    // Scale down if still over target.
    $total = 0.0;
    foreach ($idxs as $i) {
        $total += lgueSpendLinePeso($work[$i]);
    }
    if ($total > $target + 0.5 && $total > 0) {
        $scale = $target / $total;
        foreach ($idxs as $i) {
            $q = (int) $work[$i]['qty'];
            $work[$i]['qty'] = max(1, (int) round($q * $scale));
        }
    }

    // Nibble largest remaining qtys.
    $monthRows = static function (array $work, array $idxs): array {
        $out = [];
        foreach ($idxs as $i) {
            $out[] = $work[$i];
        }

        return $out;
    };

    $guard = 0;
    while (lgueSpendMonthTotal($monthRows($work, $idxs)) > $target + 1 && $guard < 8000) {
        $guard++;
        $best = null;
        $bestPeso = -1.0;
        foreach ($idxs as $i) {
            if ($work[$i]['drop'] || (int) $work[$i]['qty'] <= 1) {
                continue;
            }
            $peso = lgueSpendLinePeso($work[$i]);
            if ($peso > $bestPeso) {
                $bestPeso = $peso;
                $best = $i;
            }
        }
        if ($best === null) {
            break;
        }
        $work[$best]['qty'] = (int) $work[$best]['qty'] - 1;
    }

    // Last resort: drop extra qty=1 lines (keep ≥1 line per PR, ≥2 lines in the month).
    $prCounts = [];
    foreach ($idxs as $i) {
        if ($work[$i]['drop']) {
            continue;
        }
        $pid = (int) $work[$i]['pr_id'];
        $prCounts[$pid] = ($prCounts[$pid] ?? 0) + 1;
    }
    $alive = 0;
    foreach ($idxs as $i) {
        if (!$work[$i]['drop']) {
            $alive++;
        }
    }
    $guard = 0;
    while (lgueSpendMonthTotal($monthRows($work, $idxs)) > $target + 1 && $guard < 4000) {
        $guard++;
        $best = null;
        $bestPeso = -1.0;
        foreach ($idxs as $i) {
            if ($work[$i]['drop']) {
                continue;
            }
            $pid = (int) $work[$i]['pr_id'];
            if (($prCounts[$pid] ?? 0) <= 1) {
                continue;
            }
            if ($alive <= 2) {
                continue;
            }
            $peso = lgueSpendLinePeso($work[$i]);
            if ($peso > $bestPeso) {
                $bestPeso = $peso;
                $best = $i;
            }
        }
        if ($best === null) {
            break;
        }
        $pid = (int) $work[$best]['pr_id'];
        $work[$best]['drop'] = true;
        $prCounts[$pid]--;
        $alive--;
    }
}

$updateN = 0;
$dropN = 0;
$sameN = 0;
foreach ($work as $row) {
    if ($row['drop']) {
        $dropN++;
        continue;
    }
    if ((int) $row['qty'] !== (int) $row['old_qty']) {
        $updateN++;
    } else {
        $sameN++;
    }
}

echo "Planned: update qty on {$updateN} lines, drop {$dropN} surplus lines, leave {$sameN} unchanged.\n";

$updLine = $conn->prepare(
    'UPDATE proc_pr_line SET quantity = ?, est_line_total = ? WHERE id = ?'
);
$updIss = $conn->prepare(
    'UPDATE issuance_consumable_lines SET quantity = ? WHERE proc_pr_line_id = ?'
);
$updPo = $conn->prepare(
    'UPDATE proc_po_line SET quantity = ?, amount = ROUND(unit_cost * ?, 2), description = (SELECT description FROM proc_pr_line WHERE id = ?), unit = (SELECT unit FROM proc_pr_line WHERE id = ?) WHERE pr_line_id = ?'
);
$updQl = $conn->prepare(
    'UPDATE proc_quotation_line SET line_total = ROUND(unit_price * ?, 2) WHERE pr_line_id = ?'
);
$updMoves = $conn->prepare(
    "UPDATE consumable_stock_moves SET quantity = ? WHERE proc_pr_line_id = ? AND move_type IN ('restock','release')"
);

$delQl = $conn->prepare('DELETE FROM proc_quotation_line WHERE pr_line_id = ?');
$delPo = $conn->prepare('DELETE FROM proc_po_line WHERE pr_line_id = ?');
$delIss = $conn->prepare('DELETE FROM issuance_consumable_lines WHERE proc_pr_line_id = ?');
$delMoves = $conn->prepare('DELETE FROM consumable_stock_moves WHERE proc_pr_line_id = ?');
$selPoLine = $conn->prepare(
    'SELECT pol.po_id, pol.line_no FROM proc_po_line pol WHERE pol.pr_line_id = ?'
);
$delAirMatch = $conn->prepare(
    'DELETE al FROM proc_air_line al
     INNER JOIN proc_air a ON a.id = al.air_id
     WHERE a.po_id = ? AND al.line_no = ?'
);
$delPrLine = $conn->prepare('DELETE FROM proc_pr_line WHERE id = ?');

$conn->beginTransaction();
try {
    foreach ($work as $row) {
        $id = (int) $row['id'];
        if ($row['drop']) {
            $selPoLine->execute([$id]);
            foreach ($selPoLine->fetchAll(PDO::FETCH_ASSOC) as $pol) {
                $delAirMatch->execute([(int) $pol['po_id'], (int) $pol['line_no']]);
            }
            $delQl->execute([$id]);
            $delPo->execute([$id]);
            $delIss->execute([$id]);
            $delMoves->execute([$id]);
            $delPrLine->execute([$id]);
            continue;
        }

        $qty = (int) $row['qty'];
        $total = round($qty * (float) $row['cost'], 2);
        $updLine->execute([$qty, $total, $id]);
        $updIss->execute([$qty, $id]);
        $updPo->execute([$qty, $qty, $id, $id, $id]);
        $updQl->execute([$qty, $id]);
        $updMoves->execute([$qty, $id]);
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
        "UPDATE proc_air_line al
         INNER JOIN proc_air a ON a.id = al.air_id
         INNER JOIN proc_po po ON po.id = a.po_id
         INNER JOIN proc_po_line pol ON pol.po_id = po.id AND pol.line_no = al.line_no
         INNER JOIN proc_pr p ON p.id = po.pr_id
         SET al.quantity = pol.quantity
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
    fwrite(STDERR, 'Rescale failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Applied.\n\n";

echo "Office avg monthly spend (Jan–Aug) vs map\n";
$out = $conn->query(
    "SELECT o.code, o.name,
            ROUND(SUM(l.est_line_total)/8, 0) AS avg_mo
     FROM proc_pr p
     INNER JOIN proc_pr_line l ON l.pr_id = p.id
     INNER JOIN offices o ON o.id = p.office_id
     WHERE p.is_consumable = 1
       AND p.status IN ('completed','po_issued')
       AND p.pr_date >= '2026-01-01' AND p.pr_date < '2026-09-01'
     GROUP BY o.id
     ORDER BY avg_mo DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$lgu = 0.0;
foreach ($out as $r) {
    $code = strtoupper((string) $r['code']);
    $budget = (float) ($map[$code] ?? 0);
    $avg = (float) $r['avg_mo'];
    $lgu += $avg;
    echo sprintf(
        "  %-8s  avg ₱%-10s  map ₱%-8s  %s\n",
        $code,
        number_format($avg),
        number_format($budget),
        $r['name']
    );
}

$tot = $conn->query(
    "SELECT ROUND(SUM(l.est_line_total)/8,0) avg_mo,
            MAX(l.quantity) max_qty,
            ROUND(MAX(l.est_line_total),0) max_line
     FROM proc_pr p
     INNER JOIN proc_pr_line l ON l.pr_id = p.id
     WHERE p.is_consumable = 1
       AND p.status IN ('completed','po_issued')
       AND p.pr_date >= '2026-01-01' AND p.pr_date < '2026-09-01'"
)->fetch(PDO::FETCH_ASSOC);

echo "\nLGU-wide avg/month ₱" . number_format((float) $tot['avg_mo']) . " (was ~₱3,220,165)\n";
echo 'Max remaining qty ' . $tot['max_qty'] . ' · max line ₱' . number_format((float) $tot['max_line']) . "\n";

$toner = $conn->query(
    "SELECT ROUND(MAX(l.quantity),0) max_qty, ROUND(MAX(l.est_line_total),0) max_line
     FROM proc_pr_line l
     INNER JOIN proc_pr p ON p.id = l.pr_id
     WHERE p.is_consumable = 1 AND LOWER(l.description) LIKE '%toner%'"
)->fetch(PDO::FETCH_ASSOC);
echo 'Toner max qty ' . $toner['max_qty'] . ' · max line ₱' . number_format((float) $toner['max_line']) . "\n";
