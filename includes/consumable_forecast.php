<?php
/**
 * Consumables forecasting.
 * Department target: monthly consumable Purchase Request spending (₱).
 * Item-level outlook still uses released consumable issuances when present.
 */

require_once __DIR__ . '/consumable_schema.php';
require_once __DIR__ . '/consumable_stock.php';
require_once __DIR__ . '/consumable_pr_forecast.php';
require_once __DIR__ . '/consumable_forecast_ml.php';

function consumableForecastOls(array $y): ?array
{
    $n = count($y);
    if ($n < 3) {
        return null;
    }
    $sumX = 0.0;
    $sumY = 0.0;
    $sumXY = 0.0;
    $sumXX = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $x = (float) ($i + 1);
        $yi = (float) $y[$i];
        $sumX += $x;
        $sumY += $yi;
        $sumXY += $x * $yi;
        $sumXX += $x * $x;
    }
    $den = ($n * $sumXX) - ($sumX * $sumX);
    if (abs($den) < 1e-12) {
        $avg = $sumY / $n;
        $next = max(0.0, $avg);

        return [
            'slope' => 0.0,
            'intercept' => $avg,
            'next' => (int) round($next),
            'next_raw' => $next,
            'rmse' => 0.0,
            'mape' => 0.0,
            'r2' => 1.0,
            'n' => $n,
        ];
    }
    $slope = (($n * $sumXY) - ($sumX * $sumY)) / $den;
    $intercept = ($sumY - ($slope * $sumX)) / $n;
    $nextX = (float) ($n + 1);
    $pred = $intercept + ($slope * $nextX);
    if ($pred < 0) {
        $pred = 0.0;
    }

    $sse = 0.0;
    $sst = 0.0;
    $apeSum = 0.0;
    $apeN = 0;
    $meanY = $sumY / $n;
    for ($i = 0; $i < $n; $i++) {
        $yi = (float) $y[$i];
        $fit = $intercept + ($slope * (float) ($i + 1));
        $err = $yi - $fit;
        $sse += $err * $err;
        $sst += ($yi - $meanY) * ($yi - $meanY);
        if (abs($yi) >= 0.5) {
            $apeSum += abs($err) / abs($yi);
            $apeN++;
        }
    }
    $rmse = sqrt($sse / max(1, $n - 2));
    $r2 = $sst > 1e-12 ? max(0.0, 1.0 - ($sse / $sst)) : 0.0;
    $mape = $apeN > 0 ? ($apeSum / $apeN) : 1.0;

    // Soft-cap wild extrapolations from short/noisy series (max 2.5× peak history).
    $peak = 0.0;
    foreach ($y as $v) {
        $peak = max($peak, (float) $v);
    }
    $cap = max($peak * 2.5, $meanY * 2.5, 1.0);
    if ($pred > $cap) {
        $pred = $cap;
    }

    return [
        'slope' => $slope,
        'intercept' => $intercept,
        'next' => (int) round($pred),
        'next_raw' => $pred,
        'rmse' => $rmse,
        'mape' => $mape,
        'r2' => $r2,
        'n' => $n,
    ];
}

/**
 * Drop the incomplete current calendar month from a YYYY-MM keyed map
 * so partial in-month releases do not bias next-month forecasts.
 *
 * @param array<string,mixed> $byMonth
 * @return array<string,mixed>
 */
function consumableForecastDropIncompleteMonth(array $byMonth): array
{
    if (!$byMonth) {
        return $byMonth;
    }
    $curYm = (new DateTimeImmutable('today'))->format('Y-m');
    if (isset($byMonth[$curYm])) {
        unset($byMonth[$curYm]);
    }

    return $byMonth;
}

/**
 * True when series has enough real activity for a reliable OLS fit.
 *
 * @param list<float|int> $series
 */
function consumableForecastHasEnoughActivity(array $series, int $minPositive = 3): bool
{
    if (count($series) < $minPositive) {
        return false;
    }
    $positive = 0;
    foreach ($series as $v) {
        if ((float) $v > 0) {
            $positive++;
        }
    }

    return $positive >= $minPositive;
}

/**
 * Inclusive integer range around an OLS point forecast.
 *
 * @return array{low:int,high:int,point:int}
 */
function consumableForecastRange(?array $ols, float $minSpread = 1.0): array
{
    if ($ols === null) {
        return ['low' => 0, 'high' => 0, 'point' => 0];
    }
    $point = (float) ($ols['next_raw'] ?? $ols['next'] ?? 0);
    $rmse = (float) ($ols['rmse'] ?? 0);
    $pad = max($minSpread, $rmse, abs($point) * 0.08);
    $low = (int) max(0, floor($point - $pad));
    $high = (int) max($low, ceil($point + $pad));
    if ($high === $low) {
        $high = $low + (int) max(1, round($minSpread));
    }

    return [
        'low' => $low,
        'high' => $high,
        'point' => (int) round($point),
    ];
}

/**
 * Predict next N months from OLS and return sum range.
 *
 * @return array{low:int,high:int,point:int}
 */
function consumableForecastHorizonSum(?array $ols, int $months = 3): array
{
    if ($ols === null || $months < 1) {
        return ['low' => 0, 'high' => 0, 'point' => 0];
    }
    $n = (int) ($ols['n'] ?? 0);
    $slope = (float) ($ols['slope'] ?? 0);
    $intercept = (float) ($ols['intercept'] ?? 0);
    $sum = 0.0;
    for ($k = 1; $k <= $months; $k++) {
        $pred = $intercept + ($slope * (float) ($n + $k));
        if ($pred < 0) {
            $pred = 0.0;
        }
        $sum += $pred;
    }
    $rmse = (float) ($ols['rmse'] ?? 0);
    $pad = max(1.0, $rmse * sqrt($months), abs($sum) * 0.08);
    $low = (int) max(0, floor($sum - $pad));
    $high = (int) max($low, ceil($sum + $pad));
    if ($high === $low) {
        $high = $low + 1;
    }

    return [
        'low' => $low,
        'high' => $high,
        'point' => (int) round($sum),
    ];
}

function consumableForecastTrendLabel(float $slope, float $avgAbs = 0.0): string
{
    $scale = $avgAbs > 0 ? abs($slope) / $avgAbs : abs($slope);
    if ($scale < 0.05 && abs($slope) < 0.5) {
        return 'Stable';
    }
    if ($slope > 0) {
        return 'Increasing';
    }
    if ($slope < 0) {
        return 'Decreasing';
    }

    return 'Stable';
}

/**
 * Short budget action line from model signals (varies by trend/driver/urgency).
 *
 * @param array<string,mixed> $dept
 */
function consumableForecastDeptAdvice(array $dept): string
{
    if (($dept['status'] ?? '') === 'Insufficient historical data') {
        return 'Collect more release history first.';
    }
    $trend = (string) ($dept['trend_label'] ?? 'Stable');
    $strength = (string) ($dept['trend_strength'] ?? 'mild');
    $confidence = (string) ($dept['confidence'] ?? 'medium');
    $accel = (float) ($dept['acceleration_pct'] ?? 0);
    $freqTrend = (string) ($dept['frequency_trend'] ?? 'Stable');
    $qprTrend = (string) ($dept['qty_per_request_trend'] ?? 'Stable');
    $avgQty = (float) ($dept['avg_qty_per_month'] ?? 0);
    $budgetPoint = (float) ($dept['forecast_budget_next_month'] ?? ($dept['forecast_budget_range']['point'] ?? 0));

    $driver = '';
    if ($qprTrend === 'Increasing' && $freqTrend !== 'Increasing') {
        $driver = 'basket';
    } elseif ($freqTrend === 'Increasing' && $qprTrend !== 'Increasing') {
        $driver = 'frequency';
    } elseif ($freqTrend === 'Increasing' && $qprTrend === 'Increasing') {
        $driver = 'both';
    } elseif ($qprTrend === 'Decreasing' || $freqTrend === 'Decreasing') {
        $driver = 'easing';
    }

    $volume = $avgQty >= 150 ? 'high' : ($avgQty >= 60 ? 'mid' : 'low');
    $spend = $budgetPoint >= 35000 ? 'heavy' : ($budgetPoint >= 15000 ? 'mid' : 'light');

    if ($trend === 'Increasing') {
        if ($confidence === 'low') {
            return 'Trend looks up but history is thin — raise allocation only after verifying the latest releases.';
        }
        if ($strength === 'strong' || $accel >= 25) {
            if ($driver === 'frequency') {
                return $spend === 'heavy'
                    ? 'Urgent: request trips are rising fast — pre-position stock and lift the monthly ceiling.'
                    : 'Urgent: they are requesting more often — restock early and raise the monthly allocation.';
            }
            if ($driver === 'basket') {
                return $accel >= 30
                    ? 'Urgent: quantity per request is accelerating — boost bulk pack sizes now.'
                    : ($volume === 'high'
                        ? 'Strong rise in basket size on a high-volume office — boost bulk lines first.'
                        : 'Strong rise in quantity per request — increase bulk pack sizes for next month.');
            }
            if ($driver === 'both') {
                return 'Urgent: both request frequency and basket size are up — increase allocation and reorder lead time.';
            }

            return $accel >= 30
                ? 'Urgent: demand is accelerating — increase allocation now and restock ahead of the curve.'
                : 'Strong upward trend — increase allocation soon and restock ahead of demand.';
        }
        if ($strength === 'moderate') {
            if ($driver === 'frequency') {
                return $accel >= 15
                    ? 'Moderate rise in request frequency with recent heat — add a clear buffer to next allocation.'
                    : 'Moderate rise in request frequency — add a small buffer to the next allocation.';
            }
            if ($driver === 'basket') {
                return $spend === 'heavy'
                    ? 'Moderate rise in basket size on a larger budget — increase pack sizes and watch peso impact.'
                    : ($accel >= 15
                        ? 'Moderate rise in quantity per request — increase pack sizes this cycle.'
                        : 'Moderate rise in quantity per request — slightly increase pack sizes, keep trip count steady.');
            }

            return $spend === 'heavy'
                ? 'Moderate upward trend on a larger budget — nudge next-month allocation up a notch.'
                : 'Slightly increase next-month allocation to match the moderate rise.';
        }

        if ($driver === 'frequency') {
            return $accel >= 15
                ? 'Mild but recent trip count is warming — hold budget, watch frequency closely.'
                : 'Mild uptick in request frequency — hold budget, watch trip count next month.';
        }
        if ($driver === 'basket') {
            if ($accel >= 18) {
                return $spend === 'heavy'
                    ? 'Basket size is creeping up on a larger spend — prepare a modest pack-size bump.'
                    : 'Basket size is creeping up faster lately — prepare a modest pack-size bump.';
            }
            if ($accel >= 10) {
                return $volume === 'high'
                    ? 'Mild basket-size rise on a busy office — hold allocation, watch top movers.'
                    : 'Mild uptick in basket size — hold allocation, watch quantity-per-request.';
            }

            return 'Slight basket-size drift — no budget change yet; monitor next releases.';
        }

        return $accel >= 12
            ? 'Soft upward signal with recent warmth — hold current budget and re-check after the next release cycle.'
            : 'Soft upward signal — hold current budget and re-check after the next release cycle.';
    }

    if ($trend === 'Decreasing') {
        if ($confidence === 'low') {
            return 'Demand looks softer, but data is noisy — do not cut hard yet.';
        }
        if ($strength === 'strong' || $accel <= -25) {
            if ($driver === 'easing') {
                return $spend === 'heavy'
                    ? 'Clear slowdown on a large spend — trim the monthly ceiling and pause non-essential bulk buys.'
                    : 'Clear slowdown — trim allocation gradually and avoid overstock on slow lines.';
            }

            return 'Strong downward trend — reduce next-month allocation and keep only essential safety stock.';
        }
        if ($strength === 'moderate') {
            return $driver === 'easing'
                ? 'Moderate easing — review which lines can be reduced without risking stockouts.'
                : 'Moderate decline — shave the next allocation lightly and monitor.';
        }

        return 'Mild decline — keep stock ready; no major cut yet.';
    }

    if ($accel >= 20) {
        return $driver === 'frequency'
            ? 'Long-term stable, but recent months show more request trips — buffer a little.'
            : 'Long-term stable, but recent months rose — keep a small buffer for next month.';
    }
    if ($accel <= -20) {
        return 'Long-term stable, but recent months fell — buy cautiously this cycle.';
    }
    if ($spend === 'heavy' && $volume === 'high') {
        return 'Steady high-volume office — maintain current allocation and keep fast movers topped up.';
    }
    if ($spend === 'light') {
        return 'Steady light usage — maintain current allocation; no change needed.';
    }

    return 'Maintain current allocation and monitor monthly.';
}

/**
 * Classify trend intensity from OLS slope vs average level.
 */
function consumableForecastTrendStrength(float $slope, float $avgAbs): string
{
    // Relative to volume — avoid absolute slope shortcuts that mark every busy office "strong".
    $scale = $avgAbs > 0 ? abs($slope) / $avgAbs : abs($slope);
    if ($scale >= 0.20) {
        return 'strong';
    }
    if ($scale >= 0.09) {
        return 'moderate';
    }

    return 'mild';
}

/**
 * Confidence from sample size + relative forecast error + fit quality.
 */
function consumableForecastConfidence(int $months, float $rmse, float $avgAbs, ?float $mape = null, ?float $r2 = null): string
{
    $relErr = $avgAbs > 0 ? ($rmse / $avgAbs) : $rmse;
    $mape = $mape ?? $relErr;
    $r2 = $r2 ?? 0.0;

    if ($months >= 6 && $relErr <= 0.25 && $mape <= 0.28 && $r2 >= 0.55) {
        return 'high';
    }
    if ($months >= 4 && $relErr <= 0.45 && $mape <= 0.45) {
        return 'medium';
    }

    return 'low';
}

/**
 * Recent vs earlier quantity acceleration (%).
 *
 * @param list<float> $series
 */
function consumableForecastAccelerationPct(array $series): float
{
    $n = count($series);
    if ($n < 4) {
        return 0.0;
    }
    $recentN = min(2, (int) floor($n / 2));
    $recent = array_slice($series, -$recentN);
    $earlier = array_slice($series, 0, max(1, $n - $recentN));
    $rAvg = array_sum($recent) / max(1, count($recent));
    $eAvg = array_sum($earlier) / max(1, count($earlier));
    if ($eAvg <= 0.0001) {
        return $rAvg > 0 ? 100.0 : 0.0;
    }

    return (($rAvg - $eAvg) / $eAvg) * 100.0;
}

/**
 * Short actionable recommendation only (details stay in the briefing).
 *
 * @param array<string,mixed> $dept
 */
function consumableForecastDeptRecommendation(array $dept): string
{
    if (($dept['status'] ?? '') === 'Insufficient historical data') {
        $months = (int) ($dept['months_observed'] ?? 0);

        return $months > 0
            ? 'Keep recording completed consumable Purchase Requests until at least 3 months of history exist, then re-check this forecast.'
            : 'No completed consumable Purchase Requests yet for this office. Record PRs for 3+ months to unlock a next-month recommendation.';
    }

    $action = consumableForecastDeptAdvice($dept);
    $reqTxt = consumableForecastFmtRange($dept['forecast_requests_range'] ?? ['low' => 0, 'high' => 0, 'point' => 0]);
    $qtyTxt = consumableForecastFmtRange($dept['forecast_qty_range'] ?? ['low' => 0, 'high' => 0, 'point' => 0]);
    $budgetLow = (float) ($dept['forecast_budget_range']['low'] ?? 0);
    $budgetHigh = (float) ($dept['forecast_budget_range']['high'] ?? 0);
    $budgetTxt = $budgetLow === $budgetHigh
        ? '₱' . number_format($budgetLow, 0)
        : '₱' . number_format($budgetLow, 0) . '–₱' . number_format($budgetHigh, 0);

    $freqTrend = (string) ($dept['frequency_trend'] ?? 'Stable');
    $qprTrend = (string) ($dept['qty_per_request_trend'] ?? 'Stable');
    $focus = '';
    if ($qprTrend === 'Increasing' && $freqTrend !== 'Increasing') {
        $focus = ' Focus on larger pack sizes, not more trips.';
    } elseif ($freqTrend === 'Increasing' && $qprTrend !== 'Increasing') {
        $focus = ' Focus on covering more frequent small draws.';
    } elseif ($freqTrend === 'Increasing' && $qprTrend === 'Increasing') {
        $focus = ' Cover both more trips and larger baskets.';
    } elseif ($freqTrend === 'Decreasing' || $qprTrend === 'Decreasing') {
        $focus = ' Prefer leaner buys on slowing lines.';
    }

    $monthLbl = consumableForecastMonthLabel((string) ($dept['forecast_month'] ?? ''));
    $status = (string) ($dept['budget_status'] ?? '');
    $statusBit = $status !== '' ? " Status: {$status}." : '';

    return "{$action}{$focus} Predicted consumables PR spending for {$monthLbl} is about {$budgetTxt}.{$statusBit}";
}

/**
 * @param array{low:int,high:int,point:int} $range
 */
function consumableForecastFmtRange(array $range, string $prefix = ''): string
{
    $low = (int) ($range['low'] ?? 0);
    $high = (int) ($range['high'] ?? 0);
    if ($low === $high) {
        return $prefix . number_format($low);
    }

    return $prefix . number_format($low) . '–' . number_format($high);
}

/**
 * Narrative briefing matching the planning brief format.
 *
 * @param array<string,mixed> $dept
 */
function consumableForecastDeptBriefing(array $dept): string
{
    if (($dept['status'] ?? '') === 'Insufficient historical data') {
        return consumableForecastDeptRecommendation($dept);
    }
    $forecastYm = (string) ($dept['forecast_month'] ?? '');
    $monthLbl = $forecastYm !== '' ? consumableForecastMonthLabel($forecastYm) : 'next month';
    $point = (float) ($dept['forecast_budget_next_month'] ?? ($dept['forecast_budget_range']['point'] ?? 0));
    $budgetTxt = '₱' . number_format($point, 0);
    $base = (float) ($dept['monthly_budget'] ?? 0);
    $baseTxt = $base > 0 ? '₱' . number_format($base, 0) : '—';
    $diff = (float) ($dept['budget_diff'] ?? ($point - $base));
    $diffTxt = ($diff >= 0 ? '+' : '−') . '₱' . number_format(abs($diff), 0);
    $status = (string) ($dept['budget_status'] ?? 'Within Budget');
    $method = trim((string) ($dept['forecast_method'] ?? ''));
    if ($method === '') {
        $method = 'Adaptive forecast';
    }
    $fromLbl = consumableForecastMonthLabel((string) ($dept['train_from'] ?? '2026-01'));
    $toLbl = consumableForecastMonthLabel((string) ($dept['train_to'] ?? '2026-08'));

    return "Based on the department’s historical consumables Purchase Requests, the estimated consumables budget requirement for {$monthLbl} is {$budgetTxt}.\n"
        . "Forecast method: {$method}. Historical basis: monthly consumables Purchase Request spending ({$fromLbl}–{$toLbl} completed months).\n"
        . "Allotted monthly budget (average actual spend): {$baseTxt}. Difference: {$diffTxt}. Status: {$status}.";
}

/**
 * Fill contiguous months from first to last observed (zeros for gaps).
 *
 * @param array<string,float> $byMonth
 * @return list<array{month:string,demand:float}>
 */
function consumableForecastFillSeries(array $byMonth): array
{
    if (!$byMonth) {
        return [];
    }
    ksort($byMonth);
    $keys = array_keys($byMonth);
    $start = DateTimeImmutable::createFromFormat('Y-m-d', $keys[0] . '-01');
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $keys[count($keys) - 1] . '-01');
    if (!$start || !$end) {
        return [];
    }
    $series = [];
    $cur = $start;
    while ($cur <= $end) {
        $ym = $cur->format('Y-m');
        $series[] = ['month' => $ym, 'demand' => (float) ($byMonth[$ym] ?? 0)];
        $cur = $cur->modify('+1 month');
    }

    return $series;
}

/**
 * Monthly requested qty by item label from completed consumable PRs.
 *
 * @return array<string, array{name:string,unit:string,months:array<string,float>,request_count:int}>
 */
function consumableForecastPrItemMonthly(PDO $conn, ?int $officeId = null): array
{
    consumableEnsureSchema($conn);
    $labelSql = consumablePrLineLabelSql('l');
    $window = consumableForecastPrWindow(null, $conn);
    $sql = "SELECT {$labelSql} AS item_label,
                   COALESCE(NULLIF(TRIM(l.unit), ''), 'pcs') AS unit,
                   DATE_FORMAT(p.pr_date, '%Y-%m') AS ym,
                   COALESCE(SUM(l.quantity), 0) AS qty,
                   COUNT(*) AS line_count
            FROM proc_pr_line l
            INNER JOIN proc_pr p ON p.id = l.pr_id
            WHERE p.is_consumable = 1
              AND p.status IN ('completed', 'po_issued')
              AND DATE_FORMAT(p.pr_date, '%Y-%m') >= ?
              AND DATE_FORMAT(p.pr_date, '%Y-%m') <= ?";
    $params = [$window['train_start'], $window['train_end']];
    if ($officeId !== null && $officeId > 0) {
        $sql .= ' AND p.office_id = ?';
        $params[] = $officeId;
    }
    $sql .= " GROUP BY {$labelSql}, COALESCE(NULLIF(TRIM(l.unit), ''), 'pcs'), DATE_FORMAT(p.pr_date, '%Y-%m')
              ORDER BY item_label ASC, ym ASC";
    $st = $conn->prepare($sql);
    $st->execute($params);

    $byItem = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string) ($row['item_label'] ?? ''));
        if ($name === '' || consumableArticleIsFixedLabel($name)) {
            continue;
        }
        $key = consumableNormalizeNameKey($name);
        if ($key === '' || $key === 'consumable' || $key === 'consumables') {
            continue;
        }
        if (!isset($byItem[$key])) {
            $byItem[$key] = [
                'name' => $name,
                'unit' => (string) ($row['unit'] ?? 'pcs'),
                'months' => [],
                'request_count' => 0,
            ];
        }
        $ym = (string) $row['ym'];
        $byItem[$key]['months'][$ym] = (float) ($byItem[$key]['months'][$ym] ?? 0) + (float) $row['qty'];
        $byItem[$key]['request_count'] += (int) $row['line_count'];
    }

    return $byItem;
}

/**
 * Top requested consumable items for one office (from completed PRs).
 *
 * @return list<array{name:string,request_count:int,total_qty:float,last_requested:?string}>
 */
function consumableForecastDeptTopItems(PDO $conn, int $officeId, int $limit = 3): array
{
    if ($officeId <= 0) {
        return [];
    }
    $labelSql = consumablePrLineLabelSql('l');
    $st = $conn->prepare(
        "SELECT {$labelSql} AS item_label,
                COUNT(*) AS request_count,
                COALESCE(SUM(l.quantity), 0) AS total_qty,
                MAX(p.pr_date) AS last_requested
         FROM proc_pr_line l
         INNER JOIN proc_pr p ON p.id = l.pr_id
         WHERE p.office_id = ?
           AND p.is_consumable = 1
           AND p.status IN ('completed', 'po_issued', 'quoting', 'submitted')
           AND p.status <> 'draft'
         GROUP BY {$labelSql}
         ORDER BY request_count DESC, total_qty DESC
         LIMIT " . (int) $limit
    );
    $st->execute([$officeId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string) ($row['item_label'] ?? ''));
        if ($name === '' || consumableArticleIsFixedLabel($name)) {
            continue;
        }
        $out[] = [
            'name' => $name,
            'request_count' => (int) $row['request_count'],
            'total_qty' => (float) $row['total_qty'],
            'last_requested' => $row['last_requested'] !== null ? (string) $row['last_requested'] : null,
        ];
    }

    return $out;
}

/**
 * @param ?int $officeScope When set, department rows are limited to this office (staff portal).
 * @return array{items:list<array<string,mixed>>,departments:list<array<string,mixed>>,summary:array<string,int>,meta:array<string,mixed>}
 */
function consumableForecastBuild(PDO $conn, ?int $officeScope = null): array
{
    consumableEnsureSchema($conn);

    $deptOut = consumableForecastBuildDepartments($conn, $officeScope);

    return [
        'items' => [],
        'departments' => $deptOut,
        'summary' => [
            'tracked' => 0,
            'reorder' => 0,
            'sufficient' => 0,
            'insufficient' => 0,
        ],
        'meta' => array_merge(consumableForecastPrWindow(null, $conn), [
            'engine' => 'adaptive_holdout_selection',
            'demand_source' => 'completed_consumable_purchase_requests',
            'target' => 'monthly_consumables_pr_spending',
            'forecast_method' => 'Adaptive (OLS / MA3 / blend via holdout)',
            'min_months' => 3,
            'min_positive_months' => 3,
            'excludes_incomplete_current_month' => true,
            'office_scoped' => $officeScope !== null,
            'generated_at' => date('c'),
        ]),
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function consumableForecastBuildDepartments(PDO $conn, ?int $officeScope = null): array
{
    consumableEnsureSchema($conn);
    $window = consumableForecastPrWindow(null, $conn);

    $sql = "SELECT p.office_id, o.name AS office_name, o.code AS office_code,
                   DATE_FORMAT(p.pr_date, '%Y-%m') AS ym,
                   COUNT(DISTINCT p.id) AS request_count,
                   COALESCE(SUM(l.quantity), 0) AS qty,
                   COALESCE(SUM(l.est_line_total), 0) AS peso
            FROM proc_pr p
            INNER JOIN proc_pr_line l ON l.pr_id = p.id
            INNER JOIN offices o ON o.id = p.office_id
            WHERE p.is_consumable = 1
              AND p.status IN ('completed', 'po_issued')";
    $params = [];
    if ($officeScope !== null) {
        $sql .= ' AND p.office_id = ?';
        $params[] = $officeScope;
    }
    $sql .= ' GROUP BY p.office_id, o.name, o.code, DATE_FORMAT(p.pr_date, \'%Y-%m\')
              ORDER BY o.name ASC, ym ASC';
    $st = $params ? $conn->prepare($sql) : $conn->query($sql);
    if ($params) {
        $st->execute($params);
    }
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $byOffice = [];
    $currentByOffice = [];
    foreach ($rows as $row) {
        $oid = (int) $row['office_id'];
        $ym = (string) $row['ym'];
        if (!isset($byOffice[$oid])) {
            $byOffice[$oid] = [
                'office_id' => $oid,
                'office_name' => (string) $row['office_name'],
                'office_code' => (string) ($row['office_code'] ?? ''),
                'months' => [],
            ];
        }
        $pack = [
            'requests' => (int) $row['request_count'],
            'qty' => (float) $row['qty'],
            'peso' => (float) $row['peso'],
        ];
        if (($window['current_ym'] ?? '') !== '' && $ym === $window['current_ym']) {
            $currentByOffice[$oid] = $pack;
            continue;
        }
        $byOffice[$oid]['months'][$ym] = $pack;
    }

    if ($officeScope !== null && $officeScope > 0 && !isset($byOffice[$officeScope])) {
        $nm = $conn->prepare('SELECT id, name, code FROM offices WHERE id = ?');
        $nm->execute([$officeScope]);
        $o = $nm->fetch(PDO::FETCH_ASSOC) ?: [];
        $byOffice[$officeScope] = [
            'office_id' => $officeScope,
            'office_name' => (string) ($o['name'] ?? 'Your department'),
            'office_code' => (string) ($o['code'] ?? ''),
            'months' => [],
        ];
    } elseif ($officeScope === null) {
        $allOff = $conn->query('SELECT id, name, code FROM offices ORDER BY name ASC');
        foreach ($allOff->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $oid = (int) $o['id'];
            if (!isset($byOffice[$oid])) {
                $byOffice[$oid] = [
                    'office_id' => $oid,
                    'office_name' => (string) $o['name'],
                    'office_code' => (string) ($o['code'] ?? ''),
                    'months' => [],
                ];
            } elseif (($byOffice[$oid]['office_code'] ?? '') === '') {
                $byOffice[$oid]['office_code'] = (string) ($o['code'] ?? '');
            }
        }
    }

    $out = [];
    $trainStart = DateTimeImmutable::createFromFormat('Y-m-d', $window['train_start'] . '-01');
    $trainEnd = DateTimeImmutable::createFromFormat('Y-m-d', $window['train_end'] . '-01');
    $forecastDt = DateTimeImmutable::createFromFormat('Y-m-d', $window['forecast_ym'] . '-01');

    foreach ($byOffice as $oid => $pack) {
        $months = $pack['months'];
        $reqSeries = [];
        $qtySeries = [];
        $pesoSeries = [];
        $qtyPerReqSeries = [];
        $history = [];

        if ($trainStart && $trainEnd) {
            $cur = $trainStart;
            while ($cur <= $trainEnd) {
                $ym = $cur->format('Y-m');
                $m = $months[$ym] ?? ['requests' => 0, 'qty' => 0.0, 'peso' => 0.0];
                $req = (float) $m['requests'];
                $qty = (float) $m['qty'];
                $peso = (float) $m['peso'];
                $qpr = $req > 0 ? ($qty / $req) : 0.0;
                $reqSeries[] = $req;
                $qtySeries[] = $qty;
                $pesoSeries[] = $peso;
                $qtyPerReqSeries[] = $qpr;
                $history[] = [
                    'month' => $ym,
                    'requests' => (int) $req,
                    'qty' => $qty,
                    'peso' => $peso,
                    'qty_per_request' => round($qpr, 2),
                    'incomplete' => false,
                    'anomaly' => false,
                ];
                $cur = $cur->modify('+1 month');
            }
        }

        $currentMonth = null;
        if (!empty($window['has_current_actuals']) && ($window['current_ym'] ?? '') !== '') {
            $curPack = $currentByOffice[$oid] ?? ['requests' => 0, 'qty' => 0.0, 'peso' => 0.0];
            $currentMonth = [
                'month' => $window['current_ym'],
                'requests' => (int) ($curPack['requests'] ?? 0),
                'qty' => (float) ($curPack['qty'] ?? 0),
                'peso' => (float) ($curPack['peso'] ?? 0),
                'incomplete' => true,
                'through' => $window['current_through'],
                'label' => 'Ongoing',
            ];
        }

        $monthLabels = array_column($history, 'month');
        $fitReq = consumableForecastHasEnoughActivity($reqSeries, 3)
            ? consumableForecastFitBest($reqSeries, $monthLabels) : null;
        $fitQty = consumableForecastHasEnoughActivity($qtySeries, 3)
            ? consumableForecastFitBest($qtySeries, $monthLabels) : null;
        $fitPeso = consumableForecastHasEnoughActivity($pesoSeries, 3)
            ? consumableForecastFitBest($pesoSeries, $monthLabels) : null;
        // Keep OLS aliases for trend slope (FitBest always includes slope from OLS on cleaned series).
        $olsReq = $fitReq;
        $olsQty = $fitQty;
        $olsPeso = $fitPeso;

        // Mark spike months on history (from peso analysis).
        if ($fitPeso && !empty($fitPeso['anomalies'])) {
            foreach ($fitPeso['anomalies'] as $an) {
                $idx = (int) ($an['index'] ?? -1);
                if ($idx >= 0 && isset($history[$idx])) {
                    $history[$idx]['anomaly'] = true;
                    $history[$idx]['anomaly_type'] = (string) ($an['type'] ?? 'spike');
                }
            }
        }

        // QPR trend must ignore months with zero requests (those are not zero basket size).
        $qprFit = [];
        foreach ($history as $h) {
            if ((int) ($h['requests'] ?? 0) > 0) {
                $qprFit[] = (float) ($h['qty_per_request'] ?? 0);
            }
        }
        $olsQpr = consumableForecastHasEnoughActivity($qprFit, 3) ? consumableForecastOls($qprFit) : null;

        $avgReq = $reqSeries ? array_sum($reqSeries) / count($reqSeries) : 0.0;
        $avgQty = $qtySeries ? array_sum($qtySeries) / count($qtySeries) : 0.0;
        $totalReq = array_sum($reqSeries);
        $totalQty = array_sum($qtySeries);
        $avgPerReq = $totalReq > 0 ? $totalQty / $totalReq : 0.0;
        $qprAvgForTrend = $qprFit ? (array_sum($qprFit) / count($qprFit)) : $avgPerReq;

        $freqTrend = $olsReq
            ? consumableForecastTrendLabel((float) $olsReq['slope'], abs($avgReq))
            : null;
        $qprTrend = $olsQpr
            ? consumableForecastTrendLabel((float) $olsQpr['slope'], abs($qprAvgForTrend))
            : null;
        $qtyTrend = $olsQty
            ? consumableForecastTrendLabel((float) $olsQty['slope'], abs($avgQty))
            : null;

        $pesoTrend = $olsPeso
            ? consumableForecastTrendLabel((float) $olsPeso['slope'], abs(array_sum($pesoSeries) / max(1, count($pesoSeries))))
            : null;

        $monthlyBudget = consumableDeptAllottedBudgetFromSpend($pesoSeries);
        $nTrain = count($pesoSeries);
        $forecastX = $nTrain + 1;
        if ($trainEnd && $forecastDt) {
            $forecastX = $nTrain + (
                ((int) $forecastDt->format('Y') - (int) $trainEnd->format('Y')) * 12
                + ((int) $forecastDt->format('n') - (int) $trainEnd->format('n'))
            );
        }

        $methodLabel = $fitPeso['model_label'] ?? 'Adaptive forecast';
        $baseFields = [
            'office_id' => $oid,
            'office_name' => $pack['office_name'],
            'office_code' => (string) ($pack['office_code'] ?? ''),
            'monthly_budget' => $monthlyBudget,
            'budget_basis' => 'avg_monthly_spend',
            'forecast_month' => $window['forecast_ym'],
            'train_from' => $window['train_start'],
            'train_to' => $window['train_end'],
            'forecast_method' => $methodLabel,
            'current_month' => $currentMonth,
        ];

        if ($olsPeso === null) {
            $row = array_merge($baseFields, [
                'status' => 'Insufficient historical data',
                'avg_requests_per_month' => round($avgReq, 2),
                'avg_qty_per_month' => round($avgQty, 2),
                'avg_qty_per_request' => round($avgPerReq, 2),
                'avg_peso_per_month' => round($pesoSeries ? array_sum($pesoSeries) / count($pesoSeries) : 0.0, 2),
                'frequency_trend' => $freqTrend,
                'qty_per_request_trend' => $qprTrend,
                'trend_label' => null,
                'trend_strength' => null,
                'confidence' => 'low',
                'months_observed' => count($pesoSeries),
                'acceleration_pct' => round(consumableForecastAccelerationPct($pesoSeries), 1),
                'qty_slope' => null,
                'qty_rmse' => null,
                'forecast_requests_next_month' => null,
                'forecast_qty_next_month' => null,
                'forecast_budget_next_month' => null,
                'forecast_requests_range' => null,
                'forecast_qty_range' => null,
                'forecast_budget_range' => null,
                'forecast_requests_next_quarter' => null,
                'forecast_qty_next_quarter' => null,
                'budget_diff' => null,
                'budget_status' => null,
                'recommendation' => '',
                'recommendation_action' => null,
                'history' => $history,
                'demand_answers' => [
                    'requesting_more_frequently' => null,
                    'qty_per_request_increasing' => null,
                    'overall_demand' => null,
                ],
            ]);
            $row['recommendation'] = consumableForecastDeptRecommendation($row);
            $row['recommendation_action'] = consumableForecastDeptAdvice($row);
            $row['briefing'] = consumableForecastDeptBriefing($row);
            $row['top_items'] = consumableForecastDeptTopItems($conn, (int) $oid);
            $out[] = $row;
            continue;
        }

        $avgPeso = $pesoSeries ? array_sum($pesoSeries) / count($pesoSeries) : 0.0;
        $trend = $pesoTrend ?: ($qtyTrend ?: 'Stable');
        $strength = consumableForecastTrendStrength((float) $olsPeso['slope'], abs($avgPeso));
        $confidence = consumableForecastConfidenceFromFit($olsPeso);
        $accel = consumableForecastAccelerationPct($pesoSeries);
        $reqRange = $olsReq ? consumableForecastRange($olsReq, 1.0) : ['low' => 0, 'high' => 0, 'point' => 0];
        $qtyRange = $olsQty ? consumableForecastRange($olsQty, 1.0) : ['low' => 0, 'high' => 0, 'point' => 0];

        $budgetPoint = consumableForecastPredictFromFit($olsPeso, max(1, $forecastX), $pesoSeries);
        $olsOct = $olsPeso;
        $olsOct['next_raw'] = $budgetPoint;
        $olsOct['next'] = (int) round($budgetPoint);
        $budgetRange = consumableForecastRange($olsOct, 100.0);
        // Widen range when holdout error is high.
        if (isset($olsPeso['holdout_mape']) && $olsPeso['holdout_mape'] !== null) {
            $hm = (float) $olsPeso['holdout_mape'];
            $pad = max(100.0, abs($budgetPoint) * max(0.12, $hm));
            $budgetRange['low'] = (int) max(0, floor($budgetPoint - $pad));
            $budgetRange['high'] = (int) max($budgetRange['low'], ceil($budgetPoint + $pad));
            $budgetRange['point'] = (int) round($budgetPoint);
        }
        $budgetDiff = $budgetPoint - $monthlyBudget;
        $budgetStatus = ($monthlyBudget > 0 && $budgetPoint > $monthlyBudget + 0.5)
            ? 'Above Normal Budget'
            : 'Within Budget';

        $reqQ = $olsReq ? consumableForecastHorizonSumFromFit($olsReq, 3, $reqSeries) : ['low' => 0, 'high' => 0, 'point' => 0];
        $qtyQ = $olsQty ? consumableForecastHorizonSumFromFit($olsQty, 3, $qtySeries) : ['low' => 0, 'high' => 0, 'point' => 0];

        // Point forecasts for next month should follow selected models (not raw OLS next).
        if ($olsReq) {
            $reqPt = consumableForecastPredictFromFit($olsReq, max(1, $forecastX), $reqSeries);
            $reqRange = consumableForecastRange(array_merge($olsReq, ['next_raw' => $reqPt, 'next' => (int) round($reqPt)]), 1.0);
        }
        if ($olsQty) {
            $qtyPt = consumableForecastPredictFromFit($olsQty, max(1, $forecastX), $qtySeries);
            $qtyRange = consumableForecastRange(array_merge($olsQty, ['next_raw' => $qtyPt, 'next' => (int) round($qtyPt)]), 1.0);
        }

        $row = array_merge($baseFields, [
            'status' => 'ok',
            'avg_requests_per_month' => round($avgReq, 2),
            'avg_qty_per_month' => round($avgQty, 2),
            'avg_qty_per_request' => round($avgPerReq, 2),
            'avg_peso_per_month' => round($avgPeso, 2),
            'frequency_trend' => $freqTrend,
            'qty_per_request_trend' => $qprTrend,
            'trend_label' => $trend,
            'trend_strength' => $strength,
            'confidence' => $confidence,
            'months_observed' => (int) ($olsPeso['n'] ?? count($pesoSeries)),
            'acceleration_pct' => round($accel, 1),
            'qty_slope' => round((float) $olsPeso['slope'], 3),
            'qty_rmse' => round((float) ($olsPeso['rmse'] ?? 0), 3),
            'qty_mape' => round((float) ($olsPeso['mape'] ?? 0) * 100, 1),
            'qty_r2' => round((float) ($olsPeso['r2'] ?? 0), 3),
            'holdout_mape_pct' => $olsPeso['holdout_mape_pct'] ?? null,
            'data_quality' => (string) ($olsPeso['data_quality'] ?? 'weak'),
            'forecast_requests_next_month' => $reqRange['point'],
            'forecast_qty_next_month' => $qtyRange['point'],
            'forecast_budget_next_month' => round($budgetPoint, 2),
            'forecast_requests_range' => $reqRange,
            'forecast_qty_range' => $qtyRange,
            'forecast_budget_range' => $budgetRange,
            'forecast_requests_next_quarter' => $reqQ,
            'forecast_qty_next_quarter' => $qtyQ,
            'budget_diff' => round($budgetDiff, 2),
            'budget_status' => $budgetStatus,
            'recommendation' => '',
            'recommendation_action' => '',
            'history' => $history,
            'analysis' => consumableForecastAnalysisPayload($olsPeso),
            'demand_answers' => [
                'requesting_more_frequently' => $freqTrend === 'Increasing',
                'qty_per_request_increasing' => $qprTrend === 'Increasing',
                'overall_demand' => $trend,
            ],
        ]);
        $row['recommendation'] = consumableForecastDeptRecommendation($row);
        $row['recommendation_action'] = consumableForecastDeptAdvice($row);
        $row['briefing'] = consumableForecastDeptBriefing($row);
        $row['top_items'] = consumableForecastDeptTopItems($conn, (int) $oid);
        $out[] = $row;
    }

    usort($out, static function ($a, $b) {
        $rank = static function ($t) {
            if ($t === 'Increasing') {
                return 0;
            }
            if ($t === 'Stable') {
                return 1;
            }
            if ($t === 'Decreasing') {
                return 2;
            }

            return 3;
        };
        $ra = $rank($a['trend_label'] ?? '');
        $rb = $rank($b['trend_label'] ?? '');
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return strcasecmp((string) $a['office_name'], (string) $b['office_name']);
    });

    return $out;
}

function consumableForecastDeptBudget(PDO $conn, int $officeId): float
{
    $st = $conn->prepare(
        "SELECT icl.consumable_item_id AS item_id,
                DATE_FORMAT(i.issue_date, '%Y-%m') AS ym,
                SUM(icl.quantity) AS qty
         FROM issuance_consumable_lines icl
         INNER JOIN issuance_records i ON i.id = icl.issuance_id
         WHERE i.is_consumable = 1
           AND i.office_id = ?
           AND COALESCE(i.status, 'Active') <> 'Cancelled'
         GROUP BY icl.consumable_item_id, DATE_FORMAT(i.issue_date, '%Y-%m')"
    );
    $st->execute([$officeId]);
    $byItem = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $iid = (int) $row['item_id'];
        $byItem[$iid][(string) $row['ym']] = (float) $row['qty'];
    }
    $budget = 0.0;
    foreach ($byItem as $itemId => $months) {
        $months = consumableForecastDropIncompleteMonth($months);
        $series = consumableForecastFillSeries($months);
        $ys = array_map(static fn ($p) => (float) $p['demand'], $series);
        $ols = consumableForecastHasEnoughActivity($ys, 3) ? consumableForecastOls($ys) : null;
        if ($ols === null) {
            continue;
        }
        $price = consumableRecentUnitCost($conn, (int) $itemId);
        $budget += ((float) $ols['next_raw']) * $price;
    }

    return round($budget, 2);
}
