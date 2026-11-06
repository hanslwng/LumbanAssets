<?php
/**
 * Demand history + forecasting.
 * Live mode uses acquisition dates, property-number years, issuances, and returns.
 * Demo mode can still generate a synthetic 2023–2026 series for comparison.
 */

function demoForecastEnsureSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS demo_demand_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_id INT NOT NULL,
            office_name VARCHAR(255) NOT NULL,
            item_category VARCHAR(120) NOT NULL,
            asset_class ENUM('SE','PPE') NOT NULL DEFAULT 'SE',
            demand_month DATE NOT NULL,
            qty_issued INT NOT NULL DEFAULT 0,
            qty_returned INT NOT NULL DEFAULT 0,
            net_demand INT NOT NULL DEFAULT 0,
            on_hand_snapshot INT NOT NULL DEFAULT 0,
            is_demo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_demo_demand (office_id, item_category, demand_month),
            INDEX idx_demo_ym (demand_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS demo_forecast_cache (
            id INT PRIMARY KEY,
            payload LONGTEXT NOT NULL,
            built_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function demoForecastNormalizeCategory(?string $itemGroup, ?string $article, string $assetClass): string
{
    $blob = strtoupper(trim((string) $itemGroup . ' ' . (string) $article));
    if (str_contains($blob, 'IT EQUIP') || str_contains($blob, 'ICT') || str_contains($blob, 'COMPUTER')) {
        return 'IT Equipment';
    }
    if (str_contains($blob, 'FURNIT') || str_contains($blob, 'FIXTURE')) {
        return 'Furniture & Fixtures';
    }
    if (str_contains($blob, 'COMMUN') || str_contains($blob, 'RADIO')) {
        return 'Communication Equipment';
    }
    if (str_contains($blob, 'AGRI') || str_contains($blob, 'FOREST') || str_contains($blob, 'FISHER')) {
        return 'Agricultural Equipment';
    }
    if (str_contains($blob, 'MEDICAL') || str_contains($blob, 'DENTAL') || str_contains($blob, 'LABORATORY')) {
        return 'Medical Equipment';
    }
    if (str_contains($blob, 'ROAD') || str_contains($blob, 'FLOOD') || str_contains($blob, 'INFRA') || str_contains($blob, 'STREET LIGHT')) {
        return 'Infrastructure';
    }
    if (str_contains($blob, 'BUILDING') || str_contains($blob, 'STRUCTURE')) {
        return 'Buildings & Structures';
    }
    if (str_contains($blob, 'LAND') || str_contains($blob, 'TITLE')) {
        return 'Land';
    }
    if (str_contains($blob, 'VEHICLE') || str_contains($blob, 'MOTOR')) {
        return 'Vehicles';
    }
    if (str_contains($blob, 'WATER') || str_contains($blob, 'TRANSFORM') || str_contains($blob, 'ELECTRIC')) {
        return 'Utilities & Plant';
    }
    if (str_contains($blob, 'DISASTER') || str_contains($blob, 'RESCUE')) {
        return 'Disaster Response Equipment';
    }
    if ($assetClass === 'PPE') {
        return 'Other PPE';
    }

    return 'Office Equipment & Tools';
}

function demoForecastMonthList(string $fromYm, string $toYm): array
{
    $out = [];
    $d = new DateTimeImmutable($fromYm . '-01');
    $end = new DateTimeImmutable($toYm . '-01');
    while ($d <= $end) {
        $out[] = $d->format('Y-m-01');
        $d = $d->modify('+1 month');
    }

    return $out;
}

function demoForecastSeasonal(int $month): float
{
    $map = [1 => 1.08, 2 => 0.88, 3 => 0.96, 4 => 1.12, 5 => 1.05, 6 => 0.92, 7 => 0.90, 8 => 0.94, 9 => 1.10, 10 => 1.18, 11 => 1.14, 12 => 1.32];

    return $map[$month] ?? 1.0;
}

function demoForecastBaseRate(string $category, string $assetClass): float
{
    $rates = [
        'IT Equipment' => 0.09,
        'Furniture & Fixtures' => 0.045,
        'Office Equipment & Tools' => 0.07,
        'Communication Equipment' => 0.06,
        'Agricultural Equipment' => 0.04,
        'Medical Equipment' => 0.055,
        'Infrastructure' => 0.012,
        'Buildings & Structures' => 0.01,
        'Land' => 0.004,
        'Vehicles' => 0.02,
        'Utilities & Plant' => 0.015,
        'Disaster Response Equipment' => 0.05,
        'Other PPE' => 0.03,
    ];
    $r = $rates[$category] ?? ($assetClass === 'PPE' ? 0.025 : 0.06);

    return $r;
}

/**
 * @return array<int, array{office_id:int,office_name:string,item_category:string,asset_class:string,stock:int}>
 */
function demoForecastSeriesKeys(PDO $conn): array
{
    $offices = $conn->query('SELECT id, name FROM offices ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    if (!$offices) {
        return [];
    }

    $keys = [];
    $hasItems = false;
    try {
        $st = $conn->query(
            "SELECT office_id, category, item_group, article, COUNT(*) AS n
             FROM property_items
             WHERE status NOT IN ('Disposed')
             GROUP BY office_id, category, item_group, article"
        );
        $agg = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $hasItems = true;
            $oid = (int) $row['office_id'];
            $cls = ((string) $row['category'] === 'PPE') ? 'PPE' : 'SE';
            $cat = demoForecastNormalizeCategory($row['item_group'] ?? '', $row['article'] ?? '', $cls);
            $k = $oid . '|' . $cat;
            if (!isset($agg[$k])) {
                $agg[$k] = [
                    'office_id' => $oid,
                    'item_category' => $cat,
                    'asset_class' => $cls,
                    'stock' => 0,
                ];
            }
            $agg[$k]['stock'] += (int) $row['n'];
            if ($cls === 'PPE') {
                $agg[$k]['asset_class'] = 'PPE';
            }
        }
        $officeNames = [];
        foreach ($offices as $o) {
            $officeNames[(int) $o['id']] = (string) $o['name'];
        }
        foreach ($agg as $row) {
            $row['office_name'] = $officeNames[$row['office_id']] ?? ('Office #' . $row['office_id']);
            $keys[] = $row;
        }
    } catch (PDOException $e) {
        $hasItems = false;
    }

    if ($hasItems && $keys) {
        return $keys;
    }

    $fallbackCats = [
        ['IT Equipment', 'SE'],
        ['Furniture & Fixtures', 'SE'],
        ['Office Equipment & Tools', 'SE'],
        ['Other PPE', 'PPE'],
    ];
    foreach ($offices as $o) {
        $oid = (int) $o['id'];
        foreach ($fallbackCats as $i => $pair) {
            $h = (int) sprintf('%u', crc32($oid . '|' . $pair[0]));
            $keys[] = [
                'office_id' => $oid,
                'office_name' => (string) $o['name'],
                'item_category' => $pair[0],
                'asset_class' => $pair[1],
                'stock' => 6 + ($h % 18) + $i,
            ];
        }
    }

    return $keys;
}

function demoForecastRebuildHistory(PDO $conn): int
{
    demoForecastEnsureSchema($conn);
    $months = demoForecastMonthList('2023-01', '2026-08');
    $keys = demoForecastSeriesKeys($conn);
    $ins = $conn->prepare(
        'INSERT INTO demo_demand_history
            (office_id, office_name, item_category, asset_class, demand_month, qty_issued, qty_returned, net_demand, on_hand_snapshot, is_demo)
         VALUES (?,?,?,?,?,?,?,?,?,1)'
    );
    $n = 0;
    try {
        $conn->beginTransaction();
        $conn->exec('DELETE FROM demo_demand_history');
        foreach ($keys as $k) {
            $rate = demoForecastBaseRate($k['item_category'], $k['asset_class']);
            $stock = max(1, (int) $k['stock']);
            foreach ($months as $t => $ym) {
                $dt = new DateTimeImmutable($ym);
                $month = (int) $dt->format('n');
                $year = (int) $dt->format('Y');
                $trend = 1.0 + 0.045 * ($year - 2023) + 0.004 * ($month / 12);
                $h = (int) sprintf('%u', crc32($k['office_id'] . '|' . $k['item_category'] . '|' . $ym));
                $noise = 0.85 + ($h % 31) / 100; // 0.85–1.15
                $issued = (int) round($stock * $rate * demoForecastSeasonal($month) * $trend * $noise);
                $issued = max(0, $issued);
                $returned = (int) round($issued * (0.05 + ($h % 7) / 100));
                $net = max(0, $issued - $returned);
                $ins->execute([
                    $k['office_id'],
                    $k['office_name'],
                    $k['item_category'],
                    $k['asset_class'],
                    $ym,
                    $issued,
                    $returned,
                    $net,
                    $stock,
                ]);
                $n++;
            }
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    return $n;
}

/**
 * 3-month moving average of the last observations, used for the next horizon months.
 *
 * @param list<int> $series
 * @return list<float>
 */
function demoForecastMovingAverage(array $series, int $horizon = 3, int $window = 3): array
{
    $vals = array_values($series);
    $out = [];
    $cur = $vals;
    for ($h = 0; $h < $horizon; $h++) {
        $slice = array_slice($cur, -$window);
        if (!$slice) {
            $out[] = 0.0;
            $cur[] = 0;
            continue;
        }
        $pred = array_sum($slice) / count($slice);
        $out[] = round($pred, 2);
        $cur[] = $pred;
    }

    return $out;
}

/**
 * Lightweight gradient boosting (stumps) — demo stand-in for XGBoost on monthly demand.
 * Features: t, month, lag1, lag3, ma3.
 *
 * @param list<int> $series
 * @return array{forecast: list<float>, mae: float}
 */
function demoForecastGradientBoosting(array $series, int $horizon = 3): array
{
    $n = count($series);
    if ($n < 8) {
        $f = demoForecastMovingAverage($series, $horizon);
        return ['forecast' => $f, 'mae' => 0.0];
    }

    $rows = [];
    for ($i = 3; $i < $n; $i++) {
        $lag1 = $series[$i - 1];
        $lag3 = $series[$i - 3];
        $ma3 = ($series[$i - 1] + $series[$i - 2] + $series[$i - 3]) / 3;
        $month = (($i % 12) + 1);
        $rows[] = [
            'x' => [$i, $month, $lag1, $lag3, $ma3],
            'y' => (float) $series[$i],
        ];
    }
    $m = count($rows);
    $pred = array_fill(0, $m, array_sum(array_column($rows, 'y')) / max(1, $m));
    $trees = 16;
    $lr = 0.2;
    $stumps = [];
    for ($r = 0; $r < $trees; $r++) {
        $resid = [];
        for ($i = 0; $i < $m; $i++) {
            $resid[$i] = $rows[$i]['y'] - $pred[$i];
        }
        $best = null;
        for ($f = 0; $f < 5; $f++) {
            $vals = array_map(static fn ($row) => $row['x'][$f], $rows);
            $uniq = array_values(array_unique($vals));
            sort($uniq);
            $step = max(1, (int) floor(count($uniq) / 4));
            for ($s = 0; $s < count($uniq); $s += $step) {
                $thr = $uniq[$s];
                $left = [];
                $right = [];
                for ($i = 0; $i < $m; $i++) {
                    if ($rows[$i]['x'][$f] <= $thr) {
                        $left[] = $resid[$i];
                    } else {
                        $right[] = $resid[$i];
                    }
                }
                if (!$left || !$right) {
                    continue;
                }
                $ml = array_sum($left) / count($left);
                $mr = array_sum($right) / count($right);
                $sse = 0.0;
                foreach ($left as $v) {
                    $sse += ($v - $ml) ** 2;
                }
                foreach ($right as $v) {
                    $sse += ($v - $mr) ** 2;
                }
                if ($best === null || $sse < $best['sse']) {
                    $best = ['f' => $f, 'thr' => $thr, 'ml' => $ml, 'mr' => $mr, 'sse' => $sse];
                }
            }
        }
        if ($best === null) {
            break;
        }
        $stumps[] = $best;
        for ($i = 0; $i < $m; $i++) {
            $add = ($rows[$i]['x'][$best['f']] <= $best['thr']) ? $best['ml'] : $best['mr'];
            $pred[$i] += $lr * $add;
        }
    }

    $mae = 0.0;
    $hold = max(1, (int) floor($m * 0.2));
    for ($i = $m - $hold; $i < $m; $i++) {
        $mae += abs($rows[$i]['y'] - $pred[$i]);
    }
    $mae = $mae / $hold;

    $ext = $series;
    $forecast = [];
    for ($h = 0; $h < $horizon; $h++) {
        $i = count($ext);
        $lag1 = $ext[$i - 1];
        $lag3 = $ext[$i - 3] ?? $ext[0];
        $ma3 = ($ext[$i - 1] + ($ext[$i - 2] ?? $ext[$i - 1]) + ($ext[$i - 3] ?? $ext[$i - 1])) / 3;
        $month = ((($i) % 12) + 1);
        $x = [$i, $month, $lag1, $lag3, $ma3];
        $yhat = $pred[$m - 1];
        foreach ($stumps as $st) {
            $add = ($x[$st['f']] <= $st['thr']) ? $st['ml'] : $st['mr'];
            $yhat += $lr * $add;
        }
        $yhat = max(0, round($yhat, 2));
        $forecast[] = $yhat;
        $ext[] = (int) round($yhat);
    }

    return ['forecast' => $forecast, 'mae' => round($mae, 2)];
}

function demoForecastIncomingByOffice(PDO $conn): array
{
    $out = [];
    try {
        $sql = "SELECT p.office_id, COALESCE(SUM(l.quantity),0) AS qty
                FROM proc_pr p
                JOIN proc_pr_line l ON l.pr_id = p.id
                WHERE p.status IN ('quoting','po_issued')
                GROUP BY p.office_id";
        foreach ($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['office_id']] = (float) $row['qty'];
        }
    } catch (PDOException $e) {
        // Procurement tables may be missing on older installs.
    }

    return $out;
}

function demoForecastOnHand(PDO $conn): array
{
    $out = [];
    try {
        $sql = "SELECT office_id, category, item_group, article, COUNT(*) AS n
                FROM property_items
                WHERE status IN ('Active','Issued')
                GROUP BY office_id, category, item_group, article";
        foreach ($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cls = ((string) $row['category'] === 'PPE') ? 'PPE' : 'SE';
            $cat = demoForecastNormalizeCategory($row['item_group'] ?? '', $row['article'] ?? '', $cls);
            $k = ((int) $row['office_id']) . '|' . $cat;
            $out[$k] = ($out[$k] ?? 0) + (int) $row['n'];
        }
    } catch (PDOException $e) {
        return [];
    }

    return $out;
}

function demoForecastStatus(float $onHand, float $incoming, float $predMonth, float $safety, float $pred3, float $recQty): string
{
    $avail = $onHand + $incoming;
    $reorderPoint = $predMonth + $safety;
    if ($recQty <= 0 && $predMonth <= 0 && $onHand > 8) {
        return 'Overstock';
    }
    if ($recQty <= 0 && $predMonth > 0 && $avail > 6 * $predMonth) {
        return 'Overstock';
    }
    if ($avail < $predMonth) {
        return 'Stockout risk';
    }
    if ($recQty > 0 && $avail <= $reorderPoint) {
        return 'Reorder now';
    }
    if ($recQty > 0) {
        return 'Watch';
    }
    if ($predMonth > 0 && $avail <= $reorderPoint * 1.5) {
        return 'Watch';
    }

    return 'Sufficient';
}

function demoForecastBuildRecommendations(PDO $conn): array
{
    demoForecastEnsureSchema($conn);
    $count = (int) $conn->query('SELECT COUNT(*) FROM demo_demand_history')->fetchColumn();
    if ($count < 10) {
        demoForecastRebuildHistory($conn);
    }

    $rows = $conn->query(
        'SELECT office_id, office_name, item_category, asset_class, demand_month, net_demand, on_hand_snapshot
         FROM demo_demand_history
         ORDER BY office_id, item_category, demand_month'
    )->fetchAll(PDO::FETCH_ASSOC);

    $series = [];
    foreach ($rows as $r) {
        $k = $r['office_id'] . '|' . $r['item_category'];
        if (!isset($series[$k])) {
            $series[$k] = [
                'office_id' => (int) $r['office_id'],
                'office_name' => $r['office_name'],
                'item_category' => $r['item_category'],
                'asset_class' => $r['asset_class'],
                'stock' => (int) $r['on_hand_snapshot'],
                'y' => [],
                'ym' => [],
            ];
        }
        $series[$k]['y'][] = (int) $r['net_demand'];
        $series[$k]['ym'][] = substr((string) $r['demand_month'], 0, 7);
        $series[$k]['stock'] = (int) $r['on_hand_snapshot'];
    }

    $onHandMap = demoForecastOnHand($conn);
    $incomingMap = demoForecastIncomingByOffice($conn);
    $horizonMonths = ['2026-09', '2026-10', '2026-11'];
    $recommendations = [];
    $chartActual = [];
    $chartMa = [];
    $chartGb = [];

    $nationalByMonth = [];
    foreach ($rows as $r) {
        $ym = substr((string) $r['demand_month'], 0, 7);
        $nationalByMonth[$ym] = ($nationalByMonth[$ym] ?? 0) + (int) $r['net_demand'];
    }
    ksort($nationalByMonth);
    $natSeries = array_values($nationalByMonth);
    $natMa = demoForecastMovingAverage($natSeries, 3);
    $natGb = demoForecastGradientBoosting($natSeries, 3);
    foreach ($nationalByMonth as $ym => $v) {
        $chartActual[] = ['month' => $ym, 'demand' => $v];
    }
    foreach ($horizonMonths as $i => $ym) {
        $chartMa[] = ['month' => $ym, 'demand' => $natMa[$i] ?? 0];
        $chartGb[] = ['month' => $ym, 'demand' => $natGb['forecast'][$i] ?? 0];
    }

    $maeList = [];
    foreach ($series as $k => $s) {
        $ma = demoForecastMovingAverage($s['y'], 3);
        $gb = demoForecastGradientBoosting($s['y'], 3);
        $maeList[] = $gb['mae'];
        $predMa3 = array_sum($ma);
        $predGb3 = array_sum($gb['forecast']);
        $predMonth = (float) ($gb['forecast'][0] ?? 0);
        $recent = array_slice($s['y'], -3);
        $avg3 = $recent ? array_sum($recent) / count($recent) : 0;
        $safety = max(1, (int) round($avg3 * 1.25));
        $physical = $onHandMap[$k] ?? $s['stock'];
        $incoming = $incomingMap[$s['office_id']] ?? 0;
        // Incoming POs are office-level; category grain uses 0 unless mapped later.
        $incomingCat = 0;
        $hBal = (int) sprintf('%u', crc32($s['office_id'] . '|' . $s['item_category'] . '|bal'));
        if ($s['asset_class'] === 'SE') {
            // Tagged SE count is not consumable stock. Demo working balance = 0.3–9.2 months of next-month demand so all statuses appear.
            $monthsCover = 0.3 + ($hBal % 90) / 10;
            $onHand = (int) max(0, round($predMonth * $monthsCover));
            $onHandNote = 'Demo working balance (Excel tagged count is not monthly stock)';
        } else {
            $onHand = (int) $physical;
            $onHandNote = 'Physical count snapshot';
        }
        $recQty = max(0, (int) round($predGb3 + $safety - $onHand - $incomingCat));
        $status = demoForecastStatus((float) $onHand, (float) $incomingCat, $predMonth, (float) $safety, $predGb3, (float) $recQty);
        $recommendations[] = [
            'office_id' => $s['office_id'],
            'office_name' => $s['office_name'],
            'item_category' => $s['item_category'],
            'asset_class' => $s['asset_class'],
            'physical_count' => (int) $physical,
            'on_hand' => (int) $onHand,
            'on_hand_note' => $onHandNote,
            'incoming' => $incomingCat,
            'avg_last_3' => round($avg3, 2),
            'predicted_month' => round($predMonth, 2),
            'predicted_3m' => round($predGb3, 2),
            'predicted_3m_ma' => round($predMa3, 2),
            'safety_stock' => $safety,
            'recommended_order_qty' => $recQty,
            'status' => $status,
            'model_mae' => $gb['mae'],
            'forecast_months' => $horizonMonths,
            'forecast_gb' => $gb['forecast'],
            'forecast_ma' => $ma,
        ];
    }

    usort($recommendations, static function ($a, $b) {
        $rank = ['Stockout risk' => 0, 'Reorder now' => 1, 'Watch' => 2, 'Sufficient' => 3, 'Overstock' => 4];
        $ra = $rank[$a['status']] ?? 9;
        $rb = $rank[$b['status']] ?? 9;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return $b['recommended_order_qty'] <=> $a['recommended_order_qty'];
    });

    $counts = ['Reorder now' => 0, 'Stockout risk' => 0, 'Watch' => 0, 'Sufficient' => 0, 'Overstock' => 0];
    foreach ($recommendations as $r) {
        if (isset($counts[$r['status']])) {
            $counts[$r['status']]++;
        }
    }

    return [
        'demo' => true,
        'disclaimer' => 'Demonstration dataset. Monthly demand for 2023–2026 is generated for evaluation (rising trend + seasonality). Semi-expendable on-hand used in the order formula is a demo working balance; the Excel tagged-item count is shown as a note. PPE on-hand is the physical-count snapshot. After go-live, replace demand with issuance and procurement history.',
        'history_from' => '2023-01',
        'history_to' => '2026-08',
        'forecast_from' => '2026-09',
        'forecast_to' => '2026-11',
        'formula' => 'Recommended Order Qty = Predicted Demand (3 months) + Safety Stock − Current Inventory − Incoming Orders',
        'models' => [
            'baseline' => '3-month moving average',
            'ml' => 'Gradient boosting (stump ensemble; XGBoost-class model for demo)',
        ],
        'status_counts' => $counts,
        'mean_model_mae' => $maeList ? round(array_sum($maeList) / count($maeList), 2) : 0,
        'series_count' => count($recommendations),
        'history_rows' => (int) $conn->query('SELECT COUNT(*) FROM demo_demand_history')->fetchColumn(),
        'chart' => [
            'actual' => $chartActual,
            'forecast_ma' => $chartMa,
            'forecast_gb' => $chartGb,
        ],
        'recommendations' => $recommendations,
        'incoming_po_by_office' => $incomingMap,
    ];
}

function demoForecastReadCache(PDO $conn): ?array
{
    try {
        $row = $conn->query('SELECT payload FROM demo_forecast_cache WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
    if (!$row || empty($row['payload'])) {
        return null;
    }
    $data = json_decode((string) $row['payload'], true);

    return is_array($data) ? $data : null;
}

function demoForecastWriteCache(PDO $conn, array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $st = $conn->prepare(
        'INSERT INTO demo_forecast_cache (id, payload, built_at) VALUES (1, ?, NOW())
         ON DUPLICATE KEY UPDATE payload = VALUES(payload), built_at = VALUES(built_at)'
    );
    $st->execute([$json]);
}

function forecastClipText(string $s, int $n): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($s) > $n ? (mb_substr($s, 0, $n - 1) . '…') : $s;
    }

    return strlen($s) > $n ? (substr($s, 0, $n - 1) . '...') : $s;
}

/**
 * Live inventory rows that make up each office / category forecast group.
 * Demand stays at category grain; this is the item list the admin can open.
 *
 * @return array<string, list<array<string,mixed>>>
 */
function demoForecastItemsByGroup(PDO $conn): array
{
    try {
        $st = $conn->query(
            "SELECT office_id, category, item_group, article, description,
                    property_number, quantity, status
             FROM property_items
             WHERE status IN ('Active','Issued')
             ORDER BY article, property_number"
        );
    } catch (PDOException $e) {
        return [];
    }

    $grouped = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $oid = (int) $row['office_id'];
        $cls = ((string) $row['category'] === 'PPE') ? 'PPE' : 'SE';
        $cat = demoForecastNormalizeCategory($row['item_group'] ?? '', $row['article'] ?? '', $cls);
        $gk = $oid . '|' . $cat;
        $article = trim((string) ($row['article'] ?? ''));
        $desc = trim((string) ($row['description'] ?? ''));
        if ($article === '') {
            $article = $desc !== '' ? forecastClipText($desc, 80) : 'Unnamed item';
        }
        $ak = strtolower($article) . '|' . strtolower(substr($desc, 0, 72));
        if (!isset($grouped[$gk][$ak])) {
            $grouped[$gk][$ak] = [
                'article' => $article,
                'description' => forecastClipText($desc, 160),
                'qty' => 0,
                'lines' => 0,
                'property_numbers' => [],
                'status' => (string) $row['status'],
            ];
        }
        $qty = (int) ($row['quantity'] ?? 0);
        $grouped[$gk][$ak]['qty'] += $qty > 0 ? $qty : 1;
        $grouped[$gk][$ak]['lines']++;
        $pn = trim((string) ($row['property_number'] ?? ''));
        if ($pn !== '' && count($grouped[$gk][$ak]['property_numbers']) < 4) {
            $grouped[$gk][$ak]['property_numbers'][] = $pn;
        }
        if ((string) $row['status'] === 'Issued') {
            $grouped[$gk][$ak]['status'] = 'Issued';
        }
    }

    $out = [];
    foreach ($grouped as $gk => $items) {
        $list = array_values($items);
        usort($list, static function ($a, $b) {
            if ($a['qty'] !== $b['qty']) {
                return $b['qty'] <=> $a['qty'];
            }

            return strcasecmp((string) $a['article'], (string) $b['article']);
        });
        $out[$gk] = array_slice($list, 0, 50);
    }

    return $out;
}

/**
 * Attach current inventory items to cached or freshly built recommendations.
 *
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function demoForecastAttachItems(PDO $conn, array $data): array
{
    $map = demoForecastItemsByGroup($conn);
    $recs = $data['recommendations'] ?? [];
    if (!is_array($recs)) {
        return $data;
    }
    foreach ($recs as $i => $r) {
        if (!is_array($r)) {
            continue;
        }
        $k = ((int) ($r['office_id'] ?? 0)) . '|' . (string) ($r['item_category'] ?? '');
        $items = $map[$k] ?? [];
        $count = 0;
        $qty = 0;
        foreach ($items as $it) {
            $count += (int) ($it['lines'] ?? 1);
            $qty += (int) ($it['qty'] ?? 0);
        }
        $recs[$i]['items'] = $items;
        $recs[$i]['item_count'] = $count;
        $recs[$i]['item_kinds'] = count($items);
        $recs[$i]['item_qty'] = $qty;
        $recs[$i]['item_names'] = array_values(array_unique(array_column($items, 'article')));
        $recs[$i]['item_names'] = array_slice($recs[$i]['item_names'], 0, 4);
    }
    $data['recommendations'] = $recs;

    $byPair = [];
    foreach ($recs as $r) {
        $byPair[((string) ($r['office_name'] ?? '')) . '|' . ((string) ($r['item_category'] ?? ''))] = $r;
    }
    $cards = $data['smart']['cards'] ?? null;
    if (is_array($cards)) {
        foreach ($cards as $ci => $card) {
            $hits = $card['items'] ?? [];
            if (!is_array($hits)) {
                continue;
            }
            foreach ($hits as $hi => $hit) {
                $src = $byPair[((string) ($hit['office_name'] ?? '')) . '|' . ((string) ($hit['item_category'] ?? ''))] ?? null;
                if (!$src) {
                    continue;
                }
                $cards[$ci]['items'][$hi]['item_names'] = $src['item_names'] ?? [];
                $cards[$ci]['items'][$hi]['item_count'] = $src['item_count'] ?? 0;
                $cards[$ci]['items'][$hi]['item_kinds'] = $src['item_kinds'] ?? 0;
            }
        }
        $data['smart']['cards'] = $cards;
    }

    return $data;
}

function demoForecastGetOrBuild(PDO $conn, bool $forceRebuild = false): array
{
    demoForecastEnsureSchema($conn);
    $mode = function_exists('settingsForecastMode') ? settingsForecastMode($conn) : 'live';
    if (!$forceRebuild) {
        $cached = demoForecastReadCache($conn);
        if ($cached && forecastCacheMatchesMode($cached, $mode)) {
            return demoForecastAttachItems($conn, $cached);
        }
    }
    $payload = $mode === 'demo'
        ? demoForecastBuildRecommendations($conn)
        : liveForecastBuildRecommendations($conn);
    demoForecastWriteCache($conn, $payload);

    return demoForecastAttachItems($conn, $payload);
}

function forecastCacheMatchesMode(array $cached, string $mode): bool
{
    $cachedMode = (string) ($cached['mode'] ?? (($cached['demo'] ?? true) ? 'demo' : 'live'));

    return $cachedMode === $mode;
}

function forecastParseItemMonth(?string $acquisitionDate, ?string $propertyNumber): ?string
{
    $acq = trim((string) $acquisitionDate);
    if ($acq !== '' && $acq > '1900-01-01' && preg_match('/^(\d{4})-(\d{2})/', $acq, $m)) {
        return $m[1] . '-' . $m[2] . '-01';
    }
    $pn = trim((string) $propertyNumber);
    if (preg_match('/^(?:19|20)\d{2}/', $pn, $m)) {
        return $m[0] . '-01-01';
    }

    return null;
}

function forecastIsYearOnlyDate(?string $acquisitionDate, ?string $propertyNumber): bool
{
    $acq = trim((string) $acquisitionDate);
    if ($acq !== '' && preg_match('/^\d{4}-01-01$/', $acq)) {
        return true;
    }
    if ($acq === '' || $acq <= '1900-01-01') {
        return preg_match('/^(?:19|20)\d{2}/', (string) $propertyNumber) === 1;
    }

    return false;
}

/**
 * Year-only Excel / property-number dates have no real month.
 * Place the item in one month of that year, never after the as-of month.
 */
function forecastSpreadYearOnlyMonth(string $yearMonth, string $seed, string $fromYm, string $asOfYm): string
{
    $year = (int) substr($yearMonth, 0, 4);
    $start = new DateTimeImmutable(max($fromYm, sprintf('%04d-01-01', $year)));
    $end = new DateTimeImmutable(min($asOfYm, sprintf('%04d-12-01', $year)));
    if ($end < $start) {
        return $asOfYm;
    }
    $months = [];
    $d = $start;
    while ($d <= $end) {
        $months[] = $d->format('Y-m-01');
        $d = $d->modify('+1 month');
    }
    $h = (int) sprintf('%u', crc32($seed));

    return $months[$h % count($months)];
}

function liveForecastLatestRawDate(PDO $conn): ?string
{
    $latest = null;
    try {
        $acq = $conn->query(
            "SELECT MAX(acquisition_date) FROM property_items
             WHERE acquisition_date IS NOT NULL AND acquisition_date > '1900-01-01'"
        )->fetchColumn();
        if ($acq) {
            $latest = (string) $acq;
        }
    } catch (PDOException $e) {
        // ignore
    }
    try {
        $iss = $conn->query('SELECT MAX(issue_date) FROM issuance_records')->fetchColumn();
        if ($iss && ($latest === null || (string) $iss > $latest)) {
            $latest = (string) $iss;
        }
    } catch (PDOException $e) {
        // ignore
    }

    return $latest;
}

function liveForecastAsOfMonth(PDO $conn): string
{
    $max = null;
    try {
        $st = $conn->query('SELECT property_number, acquisition_date FROM property_items');
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ym = forecastParseItemMonth($row['acquisition_date'] ?? null, $row['property_number'] ?? null);
            if ($ym && ($max === null || $ym > $max)) {
                $max = $ym;
            }
        }
    } catch (PDOException $e) {
        $max = null;
    }
    $raw = liveForecastLatestRawDate($conn);
    if ($raw) {
        $ym = substr($raw, 0, 7) . '-01';
        if ($max === null || $ym > $max) {
            $max = $ym;
        }
    }
    if ($max === null) {
        return (new DateTimeImmutable('today'))->modify('first day of this month')->format('Y-m-01');
    }
    $asOf = new DateTimeImmutable($max);
    $thisMonth = (new DateTimeImmutable('today'))->modify('first day of this month');
    if ($asOf > $thisMonth) {
        $asOf = $thisMonth;
    }

    return $asOf->format('Y-m-01');
}

function liveForecastHistoryFrom(string $asOfYm): string
{
    $asOf = new DateTimeImmutable($asOfYm);
    $from = new DateTimeImmutable('2023-01-01');
    if ($from > $asOf) {
        $from = $asOf->modify('-11 months');
    }

    return $from->format('Y-m-01');
}

function liveForecastHorizonMonths(string $asOfYm, int $horizon = 3): array
{
    $d = (new DateTimeImmutable($asOfYm))->modify('first day of next month');
    $out = [];
    for ($i = 0; $i < $horizon; $i++) {
        $out[] = $d->format('Y-m');
        $d = $d->modify('+1 month');
    }

    return $out;
}

function liveForecastIncomingByCategory(PDO $conn): array
{
    $out = [];
    try {
        $sql = "SELECT p.office_id, l.description, l.quantity
                FROM proc_pr p
                JOIN proc_pr_line l ON l.pr_id = p.id
                WHERE p.status IN ('quoting','po_issued')";
        foreach ($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cat = demoForecastNormalizeCategory('', (string) ($row['description'] ?? ''), 'SE');
            $k = ((int) $row['office_id']) . '|' . $cat;
            $out[$k] = ($out[$k] ?? 0) + (float) ($row['quantity'] ?? 0);
        }
    } catch (PDOException $e) {
        return [];
    }

    return $out;
}

function liveForecastOnHand(PDO $conn): array
{
    $out = [];
    try {
        $sql = "SELECT office_id, category, item_group, article, COALESCE(SUM(quantity), 0) AS n
                FROM property_items
                WHERE status IN ('Active','Issued')
                GROUP BY office_id, category, item_group, article";
        foreach ($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cls = ((string) $row['category'] === 'PPE') ? 'PPE' : 'SE';
            $cat = demoForecastNormalizeCategory($row['item_group'] ?? '', $row['article'] ?? '', $cls);
            $k = ((int) $row['office_id']) . '|' . $cat;
            $out[$k] = ($out[$k] ?? 0) + (int) $row['n'];
        }
    } catch (PDOException $e) {
        return [];
    }

    return $out;
}

function liveForecastRebuildHistory(PDO $conn): int
{
    demoForecastEnsureSchema($conn);
    $asOf = liveForecastAsOfMonth($conn);
    $from = liveForecastHistoryFrom($asOf);
    $months = demoForecastMonthList(substr($from, 0, 7), substr($asOf, 0, 7));
    $keys = demoForecastSeriesKeys($conn);
    $officeNames = [];
    foreach ($keys as $k) {
        $officeNames[(int) $k['office_id']] = (string) $k['office_name'];
    }

    $agg = [];
    $st = $conn->query(
        'SELECT office_id, category, item_group, article, property_number, acquisition_date, quantity
         FROM property_items'
    );
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ym = forecastParseItemMonth($row['acquisition_date'] ?? null, $row['property_number'] ?? null);
        if ($ym === null) {
            continue;
        }
        if (forecastIsYearOnlyDate($row['acquisition_date'] ?? null, $row['property_number'] ?? null)) {
            $ym = forecastSpreadYearOnlyMonth(
                $ym,
                (string) ($row['property_number'] ?? '') . '|' . (string) ($row['office_id'] ?? ''),
                $from,
                $asOf
            );
        }
        if ($ym < $from || $ym > $asOf) {
            continue;
        }
        $cls = ((string) $row['category'] === 'PPE') ? 'PPE' : 'SE';
        $cat = demoForecastNormalizeCategory($row['item_group'] ?? '', $row['article'] ?? '', $cls);
        $oid = (int) $row['office_id'];
        $k = $oid . '|' . $cat;
        if (!isset($agg[$k])) {
            $agg[$k] = [
                'office_id' => $oid,
                'office_name' => $officeNames[$oid] ?? ('Office #' . $oid),
                'item_category' => $cat,
                'asset_class' => $cls,
                'months' => [],
            ];
        }
        if ($cls === 'PPE') {
            $agg[$k]['asset_class'] = 'PPE';
        }
        $qty = max(1, (int) ($row['quantity'] ?? 1));
        if (!isset($agg[$k]['months'][$ym])) {
            $agg[$k]['months'][$ym] = ['issued' => 0, 'returned' => 0];
        }
        $agg[$k]['months'][$ym]['issued'] += $qty;
    }

    try {
        $iss = $conn->query(
            "SELECT i.office_id, i.issue_date, p.category, p.item_group, p.article, p.quantity
             FROM issuance_records i
             JOIN property_items p ON p.id = i.property_item_id"
        );
        foreach ($iss->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $raw = (string) ($row['issue_date'] ?? '');
            if ($raw <= '1900-01-01') {
                continue;
            }
            $ym = substr($raw, 0, 7) . '-01';
            if ($ym < $from || $ym > $asOf) {
                continue;
            }
            $cls = ((string) $row['category'] === 'PPE') ? 'PPE' : 'SE';
            $cat = demoForecastNormalizeCategory($row['item_group'] ?? '', $row['article'] ?? '', $cls);
            $oid = (int) $row['office_id'];
            $k = $oid . '|' . $cat;
            if (!isset($agg[$k])) {
                $agg[$k] = [
                    'office_id' => $oid,
                    'office_name' => $officeNames[$oid] ?? ('Office #' . $oid),
                    'item_category' => $cat,
                    'asset_class' => $cls,
                    'months' => [],
                ];
            }
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            if (!isset($agg[$k]['months'][$ym])) {
                $agg[$k]['months'][$ym] = ['issued' => 0, 'returned' => 0];
            }
            $agg[$k]['months'][$ym]['issued'] += $qty;
        }
    } catch (PDOException $e) {
        // Issuance table may be empty or missing on older installs.
    }

    try {
        $ret = $conn->query(
            "SELECT p.office_id, r.return_date, p.category, p.item_group, p.article, p.quantity
             FROM return_records r
             JOIN property_items p ON p.id = r.property_item_id"
        );
        foreach ($ret->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $raw = (string) ($row['return_date'] ?? '');
            if ($raw <= '1900-01-01') {
                continue;
            }
            $ym = substr($raw, 0, 7) . '-01';
            if ($ym < $from || $ym > $asOf) {
                continue;
            }
            $cls = ((string) $row['category'] === 'PPE') ? 'PPE' : 'SE';
            $cat = demoForecastNormalizeCategory($row['item_group'] ?? '', $row['article'] ?? '', $cls);
            $oid = (int) $row['office_id'];
            $k = $oid . '|' . $cat;
            if (!isset($agg[$k])) {
                continue;
            }
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            if (!isset($agg[$k]['months'][$ym])) {
                $agg[$k]['months'][$ym] = ['issued' => 0, 'returned' => 0];
            }
            $agg[$k]['months'][$ym]['returned'] += $qty;
        }
    } catch (PDOException $e) {
        // optional
    }

    foreach ($keys as $krow) {
        $k = $krow['office_id'] . '|' . $krow['item_category'];
        if (!isset($agg[$k])) {
            $agg[$k] = [
                'office_id' => (int) $krow['office_id'],
                'office_name' => (string) $krow['office_name'],
                'item_category' => (string) $krow['item_category'],
                'asset_class' => (string) $krow['asset_class'],
                'months' => [],
            ];
        }
    }

    $onHand = liveForecastOnHand($conn);
    $ins = $conn->prepare(
        'INSERT INTO demo_demand_history
            (office_id, office_name, item_category, asset_class, demand_month, qty_issued, qty_returned, net_demand, on_hand_snapshot, is_demo)
         VALUES (?,?,?,?,?,?,?,?,?,0)'
    );
    $n = 0;
    try {
        $conn->beginTransaction();
        $conn->exec('DELETE FROM demo_demand_history');
        foreach ($agg as $k => $s) {
            $stock = (int) ($onHand[$k] ?? 0);
            foreach ($months as $ym) {
                $issued = (int) ($s['months'][$ym]['issued'] ?? 0);
                $returned = (int) ($s['months'][$ym]['returned'] ?? 0);
                $net = max(0, $issued - $returned);
                $ins->execute([
                    $s['office_id'],
                    $s['office_name'],
                    $s['item_category'],
                    $s['asset_class'],
                    $ym,
                    $issued,
                    $returned,
                    $net,
                    $stock,
                ]);
                $n++;
            }
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    return $n;
}

function forecastMonthLabel(?string $ym): string
{
    $s = substr((string) $ym, 0, 7);
    if (!preg_match('/^\d{4}-\d{2}$/', $s)) {
        return trim((string) $ym);
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $s . '-01');

    return $dt ? $dt->format('F Y') : $s;
}

function forecastRowLabel(array $r): string
{
    $cat = trim((string) ($r['item_category'] ?? 'This category'));
    $off = trim((string) ($r['office_name'] ?? 'this office'));

    return $cat . ' at ' . $off;
}

function forecastRowAdvice(array $r): string
{
    $name = forecastRowLabel($r);
    $on = (int) ($r['on_hand'] ?? 0);
    $pred = rtrim(rtrim(number_format((float) ($r['predicted_3m'] ?? 0), 1, '.', ''), '0'), '.') ?: '0';
    $qty = (int) ($r['recommended_order_qty'] ?? 0);
    $status = (string) ($r['status'] ?? '');
    if ($status === 'Stockout risk') {
        return $name . ' may run out. On hand is ' . $on . ' versus about ' . $pred . ' needed in the next 3 months. Order ' . $qty . ' now.';
    }
    if ($status === 'Reorder now') {
        return 'Reorder ' . $name . ' now. Buy ' . $qty . ' units so stock covers the next 3 months.';
    }
    if ($status === 'Watch') {
        return 'Watch ' . $name . '. On hand is ' . $on . '; predicted need is about ' . $pred . '. Prepare a PR if demand continues.';
    }
    if ($status === 'Overstock') {
        return $name . ' looks overstocked. On hand is ' . $on . ' against about ' . $pred . ' predicted demand. Do not buy more.';
    }

    return $name . ' has enough stock (' . $on . ' on hand) for the next 3 months. No order needed.';
}

/**
 * @param list<array<string,mixed>> $recommendations
 * @param array<string,mixed> $meta
 * @return array<string,mixed>
 */
function forecastBuildSmart(array $recommendations, array $meta): array
{
    $from = forecastMonthLabel($meta['forecast_from'] ?? '');
    $to = forecastMonthLabel($meta['forecast_to'] ?? '');
    $asOf = (string) ($meta['as_of'] ?? '');
    $window = trim($from . ($to && $to !== $from ? ' to ' . $to : ''));

    $risk = [];
    $reorder = [];
    $watch = [];
    $over = [];
    $orders = [];
    foreach ($recommendations as $r) {
        $st = (string) ($r['status'] ?? '');
        if ($st === 'Stockout risk') {
            $risk[] = $r;
        } elseif ($st === 'Reorder now') {
            $reorder[] = $r;
        } elseif ($st === 'Watch') {
            $watch[] = $r;
        } elseif ($st === 'Overstock') {
            $over[] = $r;
        }
        if ((int) ($r['recommended_order_qty'] ?? 0) > 0) {
            $orders[] = $r;
        }
    }
    usort($orders, static fn ($a, $b) => ((int) $b['recommended_order_qty']) <=> ((int) $a['recommended_order_qty']));
    usort($over, static fn ($a, $b) => ((int) $b['on_hand']) <=> ((int) $a['on_hand']));

    $take = static function (array $rows, int $n) {
        return array_map(static function ($r) {
            $on = (int) ($r['on_hand'] ?? 0);
            $pred = (float) ($r['predicted_3m'] ?? 0);

            return [
                'office_name' => $r['office_name'] ?? '',
                'item_category' => $r['item_category'] ?? '',
                'label' => forecastRowLabel($r),
                'status' => $r['status'] ?? '',
                'on_hand' => $on,
                'recommended_order_qty' => (int) ($r['recommended_order_qty'] ?? 0),
                'predicted_3m' => $pred,
                'surplus' => max(0, (int) round($on - $pred)),
                'gap' => max(0, (int) round($pred - $on)),
                'advice' => $r['advice'] ?? forecastRowAdvice($r),
            ];
        }, array_slice($rows, 0, $n));
    };

    $buy = array_merge($risk, $reorder);
    $priority = array_merge($risk, $reorder, $watch);

    if ($buy) {
        $whenBody = count($buy) === 1
            ? 'Replenish before the next forecast month. One group needs a purchase now.'
            : 'Replenish these groups before they fall short in ' . ($window ?: 'the next 3 months') . '. ' . count($buy) . ' groups need a purchase now.';
    } else {
        $whenBody = 'No group needs a purchase this cycle. Current stock covers predicted demand'
            . ($window ? ' through ' . $to : '') . '. Raise a PR only if a department has a real request.';
    }

    if ($orders) {
        $top = $orders[0];
        $howBody = 'Order quantity is predicted 3-month demand + safety stock − on-hand − incoming PRs. '
            . 'Largest suggested buy: ' . (int) $top['recommended_order_qty'] . ' units of ' . forecastRowLabel($top) . '.';
    } else {
        $howBody = 'Recommended order quantity is 0 for every office and category. Do not open a PR from this forecast unless a department submits an actual need.';
    }

    if ($risk) {
        $runBody = count($risk) . ' group' . (count($risk) === 1 ? '' : 's') . ' may run out if nothing is purchased. Treat these first.';
    } else {
        $runBody = 'Nothing is at stockout risk for ' . ($window ?: 'the next 3 months') . '. On-hand still covers predicted demand.';
    }

    if ($over) {
        $runOver = count($over) . ' group' . (count($over) === 1 ? '' : 's') . ' look overstocked — high on-hand versus low expected demand. Hold new purchases there.';
    } else {
        $runOver = 'No group is flagged as overstocked.';
    }

    if ($priority) {
        $prioBody = 'Work top to bottom: stockout risk, then reorder now, then watch. These need purchasing or a closer look first.';
    } elseif ($over) {
        $prioBody = 'No emergency buys. Focus on holding purchases for overstocked groups and reviewing whether transfers can use surplus instead of buying new.';
    } else {
        $prioBody = 'Stock looks balanced. Use this page after the next encoding cycle; no immediate purchasing action is required.';
    }

    $urgentN = count($risk) + count($reorder);
    $orderTotal = 0;
    foreach ($orders as $r) {
        $orderTotal += (int) ($r['recommended_order_qty'] ?? 0);
    }
    $overSurplus = 0;
    foreach ($over as $r) {
        $overSurplus += max(0, (int) ($r['on_hand'] ?? 0) - (int) round((float) ($r['predicted_3m'] ?? 0)));
    }
    if ($urgentN > 0) {
        $headline = $urgentN . ' group' . ($urgentN === 1 ? '' : 's') . ' need purchasing action before ' . ($from ?: 'the next month') . '.';
    } elseif ($over) {
        $headline = 'No stockouts expected. Priority is to avoid buying more for ' . count($over) . ' overstocked groups.';
    } else {
        $headline = 'Inventory looks sufficient for the next 3 months. No urgent purchase list.';
    }

    return [
        'headline' => $headline,
        'intro' => 'Based on predicted demand and current inventory levels, the system provides smart recommendations to help prevent stockouts, reduce excess inventory, and improve purchasing decisions.',
        'as_of' => $asOf,
        'window' => $window,
        'cards' => [
            [
                'key' => 'when',
                'title' => 'When to Reorder',
                'blurb' => 'Identifies the best time to replenish items before they run out.',
                'body' => $whenBody,
                'tone' => $buy ? 'action' : 'ok',
                'filter' => $buy ? 'action' : '',
                'highlight' => ['value' => count($buy), 'label' => $buy ? 'buy now' : 'none to buy'],
                'items' => $take($buy ?: [], 5),
            ],
            [
                'key' => 'how',
                'title' => 'How Much to Order',
                'blurb' => 'Recommends the ideal quantity based on predicted demand and current stock levels.',
                'body' => $howBody,
                'tone' => $orders ? 'action' : 'ok',
                'filter' => $orders ? 'action' : '',
                'highlight' => ['value' => $orderTotal, 'label' => $orders ? 'units to order' : 'units — no buy'],
                'items' => $take($orders, 5),
            ],
            [
                'key' => 'runout',
                'title' => 'What May Run Out Soon',
                'blurb' => 'Highlights products that are at high risk of becoming out of stock.',
                'body' => $runBody,
                'tone' => $risk ? 'risk' : 'ok',
                'filter' => 'Stockout risk',
                'highlight' => ['value' => count($risk), 'label' => $risk ? 'at stockout risk' : 'at risk — none'],
                'items' => $take($risk, 5),
            ],
            [
                'key' => 'over',
                'title' => 'What May Become Overstocked',
                'blurb' => 'Identifies products with excess inventory or low expected demand.',
                'body' => $runOver,
                'tone' => $over ? 'over' : 'ok',
                'filter' => 'Overstock',
                'highlight' => ['value' => count($over), 'label' => $over ? 'overstocked groups' : 'overstocked — none'],
                'sub' => $over ? ['value' => $overSurplus, 'label' => 'extra units vs 3-month need'] : null,
                'items' => $take($over, 6),
            ],
            [
                'key' => 'priority',
                'title' => 'What to Prioritize',
                'blurb' => 'Helps staff focus on the items that require immediate purchasing or inventory action.',
                'body' => $prioBody,
                'tone' => $priority ? 'action' : ($over ? 'over' : 'ok'),
                'filter' => $risk ? 'Stockout risk' : ($reorder ? 'Reorder now' : ($over ? 'Overstock' : '')),
                'highlight' => [
                    'value' => $urgentN ?: count($over),
                    'label' => $urgentN ? 'need action first' : ($over ? 'hold these first' : 'priority items'),
                ],
                'items' => $take($priority ?: $over, 6),
            ],
        ],
    ];
}

function liveForecastBuildRecommendations(PDO $conn): array
{
    demoForecastEnsureSchema($conn);
    $count = (int) $conn->query('SELECT COUNT(*) FROM demo_demand_history WHERE is_demo = 0')->fetchColumn();
    if ($count < 1) {
        liveForecastRebuildHistory($conn);
    }

    $asOf = liveForecastAsOfMonth($conn);
    $from = liveForecastHistoryFrom($asOf);
    $horizonMonths = liveForecastHorizonMonths($asOf, 3);
    $asOfRaw = liveForecastLatestRawDate($conn) ?: substr($asOf, 0, 7);

    $rows = $conn->query(
        'SELECT office_id, office_name, item_category, asset_class, demand_month, net_demand, on_hand_snapshot
         FROM demo_demand_history
         WHERE is_demo = 0
         ORDER BY office_id, item_category, demand_month'
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        liveForecastRebuildHistory($conn);
        $rows = $conn->query(
            'SELECT office_id, office_name, item_category, asset_class, demand_month, net_demand, on_hand_snapshot
             FROM demo_demand_history
             WHERE is_demo = 0
             ORDER BY office_id, item_category, demand_month'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    $series = [];
    foreach ($rows as $r) {
        $k = $r['office_id'] . '|' . $r['item_category'];
        if (!isset($series[$k])) {
            $series[$k] = [
                'office_id' => (int) $r['office_id'],
                'office_name' => $r['office_name'],
                'item_category' => $r['item_category'],
                'asset_class' => $r['asset_class'],
                'stock' => (int) $r['on_hand_snapshot'],
                'y' => [],
                'ym' => [],
            ];
        }
        $series[$k]['y'][] = (int) $r['net_demand'];
        $series[$k]['ym'][] = substr((string) $r['demand_month'], 0, 7);
        $series[$k]['stock'] = (int) $r['on_hand_snapshot'];
    }

    $onHandMap = liveForecastOnHand($conn);
    $incomingMap = demoForecastIncomingByOffice($conn);
    $incomingCatMap = liveForecastIncomingByCategory($conn);
    $recommendations = [];
    $chartActual = [];
    $chartMa = [];
    $chartGb = [];

    $nationalByMonth = [];
    foreach ($rows as $r) {
        $ym = substr((string) $r['demand_month'], 0, 7);
        $nationalByMonth[$ym] = ($nationalByMonth[$ym] ?? 0) + (int) $r['net_demand'];
    }
    ksort($nationalByMonth);
    $natSeries = array_values($nationalByMonth);
    $natMa = demoForecastMovingAverage($natSeries, 3);
    $natGb = demoForecastGradientBoosting($natSeries, 3);
    foreach ($nationalByMonth as $ym => $v) {
        $chartActual[] = ['month' => $ym, 'demand' => $v];
    }
    foreach ($horizonMonths as $i => $ym) {
        $chartMa[] = ['month' => $ym, 'demand' => $natMa[$i] ?? 0];
        $chartGb[] = ['month' => $ym, 'demand' => $natGb['forecast'][$i] ?? 0];
    }

    $maeList = [];
    foreach ($series as $k => $s) {
        $ma = demoForecastMovingAverage($s['y'], 3);
        $gb = demoForecastGradientBoosting($s['y'], 3);
        $maeList[] = $gb['mae'];
        $predMa3 = array_sum($ma);
        $predGb3 = array_sum($gb['forecast']);
        $predMonth = (float) ($gb['forecast'][0] ?? 0);
        $recent = array_slice($s['y'], -3);
        $avg3 = $recent ? array_sum($recent) / count($recent) : 0;
        $safety = max(1, (int) round($avg3 * 1.25));
        $onHand = (int) ($onHandMap[$k] ?? $s['stock']);
        $incomingCat = (float) ($incomingCatMap[$k] ?? 0);
        $recQty = max(0, (int) round($predGb3 + $safety - $onHand - $incomingCat));
        $status = demoForecastStatus((float) $onHand, $incomingCat, $predMonth, (float) $safety, $predGb3, (float) $recQty);
        $row = [
            'office_id' => $s['office_id'],
            'office_name' => $s['office_name'],
            'item_category' => $s['item_category'],
            'asset_class' => $s['asset_class'],
            'physical_count' => $onHand,
            'on_hand' => $onHand,
            'on_hand_note' => 'Current Active / Issued quantity',
            'incoming' => $incomingCat,
            'avg_last_3' => round($avg3, 2),
            'predicted_month' => round($predMonth, 2),
            'predicted_3m' => round($predGb3, 2),
            'predicted_3m_ma' => round($predMa3, 2),
            'safety_stock' => $safety,
            'recommended_order_qty' => $recQty,
            'status' => $status,
            'model_mae' => $gb['mae'],
            'forecast_months' => $horizonMonths,
            'forecast_gb' => $gb['forecast'],
            'forecast_ma' => $ma,
        ];
        $row['advice'] = forecastRowAdvice($row);
        $recommendations[] = $row;
    }

    usort($recommendations, static function ($a, $b) {
        $rank = ['Stockout risk' => 0, 'Reorder now' => 1, 'Watch' => 2, 'Sufficient' => 3, 'Overstock' => 4];
        $ra = $rank[$a['status']] ?? 9;
        $rb = $rank[$b['status']] ?? 9;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return $b['recommended_order_qty'] <=> $a['recommended_order_qty'];
    });

    $counts = ['Reorder now' => 0, 'Stockout risk' => 0, 'Watch' => 0, 'Sufficient' => 0, 'Overstock' => 0];
    foreach ($recommendations as $r) {
        if (isset($counts[$r['status']])) {
            $counts[$r['status']]++;
        }
    }

    $histRows = (int) $conn->query('SELECT COUNT(*) FROM demo_demand_history WHERE is_demo = 0')->fetchColumn();

    $payloadMeta = [
        'as_of' => $asOfRaw,
        'forecast_from' => $horizonMonths[0] ?? '',
        'forecast_to' => $horizonMonths[count($horizonMonths) - 1] ?? '',
    ];

    return [
        'demo' => false,
        'mode' => 'live',
        'as_of' => $asOfRaw,
        'disclaimer' => 'Live forecast from this inventory: exact acquisition dates when the day is known; year-only records are placed in a month of that year up to the latest dated record. Issuances and returns are included. The next 3 months are model projections for planning — not approved purchase orders.',
        'history_from' => substr($from, 0, 7),
        'history_to' => substr($asOf, 0, 7),
        'forecast_from' => $horizonMonths[0] ?? '',
        'forecast_to' => $horizonMonths[count($horizonMonths) - 1] ?? '',
        'formula' => 'Recommended Order Qty = Predicted Demand (3 months) + Safety Stock − Current Inventory − Incoming Orders',
        'models' => [
            'baseline' => '3-month moving average',
            'ml' => 'Gradient boosting (stump ensemble)',
        ],
        'status_counts' => $counts,
        'mean_model_mae' => $maeList ? round(array_sum($maeList) / count($maeList), 2) : 0,
        'series_count' => count($recommendations),
        'history_rows' => $histRows,
        'chart' => [
            'actual' => $chartActual,
            'forecast_ma' => $chartMa,
            'forecast_gb' => $chartGb,
        ],
        'recommendations' => $recommendations,
        'incoming_po_by_office' => $incomingMap,
        'smart' => forecastBuildSmart($recommendations, $payloadMeta),
    ];
}
