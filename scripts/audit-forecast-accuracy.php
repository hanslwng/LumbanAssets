<?php
/**
 * Holdout accuracy checkup for consumable department PR-spending forecasts.
 *
 * Method: for each office with >= 4 completed months, train OLS on months 1..n-1,
 * predict month n, compare to actual. Also compare naive baselines.
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts\audit-forecast-accuracy.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_forecast.php';
require_once dirname(__DIR__) . '/includes/consumable_pr_forecast.php';

$conn = getDBConnection();
$built = consumableForecastBuild($conn, null);
$depts = $built['departments'] ?? [];
$items = $built['items'] ?? [];
$meta = $built['meta'] ?? [];

echo "=== FORECAST ACCURACY CHECKUP ===\n";
echo 'As of: ' . date('Y-m-d H:i') . "\n";
echo 'Forecast month: ' . ($meta['forecast_ym'] ?? '?') . "\n";
echo 'Current month (excluded from train): ' . ($meta['current_ym'] ?? '?') . "\n\n";

$conf = ['high' => 0, 'medium' => 0, 'low' => 0];
$ok = 0;
$insuff = 0;
$r2s = [];
$mapes = [];
foreach ($depts as $d) {
    if (($d['status'] ?? '') === 'Insufficient historical data') {
        $insuff++;
        continue;
    }
    $ok++;
    $c = strtolower((string) ($d['confidence'] ?? 'low'));
    if (!isset($conf[$c])) {
        $c = 'low';
    }
    $conf[$c]++;
    if (isset($d['qty_r2'])) {
        $r2s[] = (float) $d['qty_r2'];
    }
    if (isset($d['qty_mape'])) {
        $mapes[] = (float) $d['qty_mape'];
    }
}

echo "--- Live model snapshot (peso OLS fit quality) ---\n";
echo "Departments with forecast: $ok\n";
echo "Insufficient history: $insuff\n";
echo 'Confidence: high=' . $conf['high'] . ' medium=' . $conf['medium'] . ' low=' . $conf['low'] . "\n";
if ($r2s) {
    sort($r2s);
    $avgR2 = array_sum($r2s) / count($r2s);
    echo 'In-sample R² (peso): avg=' . round($avgR2, 3)
        . ' median=' . round($r2s[(int) floor(count($r2s) / 2)], 3)
        . ' min=' . round($r2s[0], 3)
        . ' max=' . round($r2s[count($r2s) - 1], 3) . "\n";
}
if ($mapes) {
    sort($mapes);
    $avgM = array_sum($mapes) / count($mapes);
    echo 'In-sample MAPE % (peso): avg=' . round($avgM, 1)
        . ' median=' . round($mapes[(int) floor(count($mapes) / 2)], 1)
        . "\n";
}
echo 'Tracked items: ' . count($items) . ' (reorder=' . (int) ($built['summary']['reorder'] ?? 0)
    . ', enough=' . (int) ($built['summary']['sufficient'] ?? 0)
    . ', thin history=' . (int) ($built['summary']['insufficient'] ?? 0) . ")\n\n";

/**
 * One-step-ahead holdout for a numeric series.
 *
 * @param list<float> $series
 * @return array{actual:float,ols:float,last:float,avg3:float}|null
 */
function holdoutOne(array $series): ?array
{
    $n = count($series);
    if ($n < 4) {
        return null;
    }
    $train = array_slice($series, 0, $n - 1);
    $actual = (float) $series[$n - 1];
    if (!consumableForecastHasEnoughActivity($train, 3)) {
        return null;
    }
    $ols = consumableForecastOls($train);
    if (!$ols) {
        return null;
    }
    $last = (float) $train[count($train) - 1];
    $tail = array_slice($train, -3);
    $avg3 = array_sum($tail) / max(1, count($tail));
    $adaptive = consumableForecastFitBest($train);
    $adaptivePred = $adaptive ? (float) $adaptive['next_raw'] : $avg3;

    return [
        'actual' => $actual,
        'ols' => (float) $ols['next_raw'],
        'adaptive' => $adaptivePred,
        'adaptive_model' => $adaptive['model'] ?? 'ma3',
        'last' => $last,
        'avg3' => $avg3,
        'r2' => (float) ($ols['r2'] ?? 0),
        'mape_fit' => (float) ($ols['mape'] ?? 0) * 100,
    ];
}

function errPct(float $pred, float $actual): ?float
{
    if (abs($actual) < 0.5) {
        return null;
    }

    return abs($pred - $actual) / abs($actual) * 100.0;
}

$pesoRows = [];
$reqRows = [];

foreach ($depts as $d) {
    $hist = $d['history'] ?? [];
    if (count($hist) < 4) {
        continue;
    }
    $pesos = [];
    $reqs = [];
    foreach ($hist as $h) {
        $pesos[] = (float) ($h['peso'] ?? 0);
        $reqs[] = (float) ($h['requests'] ?? 0);
    }
    $hp = holdoutOne($pesos);
    if ($hp) {
        $hp['office'] = (string) ($d['office_name'] ?? 'Office');
        $hp['months'] = count($pesos);
        $hp['confidence'] = (string) ($d['confidence'] ?? '');
        $hp['ape'] = errPct($hp['ols'], $hp['actual']);
        $hp['ape_adaptive'] = errPct($hp['adaptive'], $hp['actual']);
        $hp['ape_last'] = errPct($hp['last'], $hp['actual']);
        $hp['ape_avg3'] = errPct($hp['avg3'], $hp['actual']);
        $pesoRows[] = $hp;
    }
    $hr = holdoutOne($reqs);
    if ($hr) {
        $hr['office'] = (string) ($d['office_name'] ?? 'Office');
        $hr['ape'] = errPct($hr['ols'], $hr['actual']);
        $hr['ape_adaptive'] = errPct($hr['adaptive'], $hr['actual']);
        $hr['ape_last'] = errPct($hr['last'], $hr['actual']);
        $hr['ape_avg3'] = errPct($hr['avg3'], $hr['actual']);
        $reqRows[] = $hr;
    }
}

/**
 * @param list<array<string,mixed>> $rows
 * @param string $label
 */
function summarizeHoldout(array $rows, string $label): void
{
    echo "--- Holdout: predict last completed month ($label) ---\n";
    $n = count($rows);
    if ($n === 0) {
        echo "No offices with enough history for holdout.\n\n";
        return;
    }

    $maeOls = 0.0;
    $maeAdaptive = 0.0;
    $maeLast = 0.0;
    $maeAvg = 0.0;
    $apes = [];
    $apesAdaptive = [];
    $apesLast = [];
    $apesAvg = [];
    $within20 = 0;
    $within35 = 0;
    $within20A = 0;
    $within35A = 0;
    $beatLast = 0;
    $beatAvg = 0;
    $comparable = 0;

    foreach ($rows as $r) {
        $maeOls += abs($r['ols'] - $r['actual']);
        $maeAdaptive += abs(($r['adaptive'] ?? $r['ols']) - $r['actual']);
        $maeLast += abs($r['last'] - $r['actual']);
        $maeAvg += abs($r['avg3'] - $r['actual']);
        if ($r['ape'] !== null) {
            $apes[] = $r['ape'];
            if ($r['ape'] <= 20) {
                $within20++;
            }
            if ($r['ape'] <= 35) {
                $within35++;
            }
        }
        if (($r['ape_adaptive'] ?? null) !== null) {
            $apesAdaptive[] = $r['ape_adaptive'];
            if ($r['ape_adaptive'] <= 20) {
                $within20A++;
            }
            if ($r['ape_adaptive'] <= 35) {
                $within35A++;
            }
        }
        if ($r['ape_last'] !== null) {
            $apesLast[] = $r['ape_last'];
        }
        if ($r['ape_avg3'] !== null) {
            $apesAvg[] = $r['ape_avg3'];
        }
        if ($r['ape'] !== null && $r['ape_last'] !== null && $r['ape_avg3'] !== null) {
            $comparable++;
            if ($r['ape'] <= $r['ape_last']) {
                $beatLast++;
            }
            if ($r['ape'] <= $r['ape_avg3']) {
                $beatAvg++;
            }
        }
    }

    $avg = static function (array $a): float {
        return $a ? array_sum($a) / count($a) : 0.0;
    };
    $med = static function (array $a): float {
        if (!$a) {
            return 0.0;
        }
        sort($a);
        return $a[(int) floor(count($a) / 2)];
    };

    echo "Offices tested: $n\n";
    echo 'MAE — OLS: ' . round($maeOls / $n, 1)
        . ' | Adaptive: ' . round($maeAdaptive / $n, 1)
        . ' | last-month: ' . round($maeLast / $n, 1)
        . ' | 3-mo avg: ' . round($maeAvg / $n, 1) . "\n";
    echo 'MAPE — OLS: avg ' . round($avg($apes), 1) . '% median ' . round($med($apes), 1)
        . '% | Adaptive: avg ' . round($avg($apesAdaptive), 1) . '% median ' . round($med($apesAdaptive), 1)
        . '% | last-month: avg ' . round($avg($apesLast), 1)
        . '% | 3-mo avg: avg ' . round($avg($apesAvg), 1) . "%\n";
    echo 'OLS within ±20%: ' . ($apes ? round(100 * $within20 / count($apes)) : 0) . "% · Adaptive: "
        . ($apesAdaptive ? round(100 * $within20A / count($apesAdaptive)) : 0) . "%\n";
    echo 'OLS within ±35%: ' . ($apes ? round(100 * $within35 / count($apes)) : 0) . "% · Adaptive: "
        . ($apesAdaptive ? round(100 * $within35A / count($apesAdaptive)) : 0) . "%\n";
    echo "Beats last-month (OLS): $beatLast/$comparable · Beats 3-mo avg (OLS): $beatAvg/$comparable\n";

    usort($rows, static function ($a, $b) {
        $aa = $a['ape_adaptive'] ?? $a['ape'] ?? 9999;
        $bb = $b['ape_adaptive'] ?? $b['ape'] ?? 9999;

        return $bb <=> $aa;
    });
    echo "Worst 5 (adaptive % error):\n";
    foreach (array_slice($rows, 0, 5) as $r) {
        $err = $r['ape_adaptive'] ?? $r['ape'];
        echo '  - ' . $r['office']
            . ' actual=' . round($r['actual'], 1)
            . ' pred=' . round($r['adaptive'] ?? $r['ols'], 1)
            . ' model=' . ($r['adaptive_model'] ?? '?')
            . ' err=' . ($err !== null ? round($err, 1) . '%' : 'n/a')
            . "\n";
    }
    echo "Best 5 (adaptive):\n";
    foreach (array_slice(array_reverse($rows), 0, 5) as $r) {
        $err = $r['ape_adaptive'] ?? $r['ape'];
        if ($err === null) {
            continue;
        }
        echo '  - ' . $r['office']
            . ' actual=' . round($r['actual'], 1)
            . ' pred=' . round($r['adaptive'] ?? $r['ols'], 1)
            . ' model=' . ($r['adaptive_model'] ?? '?')
            . ' err=' . round($err, 1) . "%\n";
    }
    echo "\n";
}

summarizeHoldout($pesoRows, '₱ spending');
summarizeHoldout($reqRows, 'PR count');

// Sanity: demo-like regularity can inflate R²; warn if series look too linear.
$tooLinear = 0;
$modelCounts = [];
$qualityCounts = [];
foreach ($depts as $d) {
    if (($d['status'] ?? '') === 'Insufficient historical data') {
        continue;
    }
    if ((float) ($d['qty_r2'] ?? 0) >= 0.95 && (float) ($d['qty_mape'] ?? 100) <= 5) {
        $tooLinear++;
    }
    $m = (string) (($d['analysis']['model'] ?? 'unknown'));
    $modelCounts[$m] = ($modelCounts[$m] ?? 0) + 1;
    $q = (string) ($d['data_quality'] ?? 'unknown');
    $qualityCounts[$q] = ($qualityCounts[$q] ?? 0) + 1;
}

echo "--- Notes ---\n";
echo "Method: adaptive holdout selection (OLS / 3-mo avg / naive / blend); spikes soft-capped; incomplete current month dropped.\n";
echo 'Models chosen: ' . json_encode($modelCounts) . "\n";
echo 'Data quality: ' . json_encode($qualityCounts) . "\n";
echo "Holdout = train on all but last completed month, score that held-out month (true next-month test).\n";
echo "Near-perfect fit offices (R²≥0.95 & MAPE≤5%): $tooLinear — often means smooth/demo-like series, not real-world noise.\n";
echo "Verdict guide: MAPE ≤20% good for ops planning; 20–35% usable with ranges; >35% treat as directional only.\n";
