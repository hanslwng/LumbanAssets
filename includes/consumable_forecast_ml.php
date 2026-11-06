<?php
/**
 * Multi-model consumable forecast helpers.
 * Picks OLS / moving-average / naive / blend via one-step holdout,
 * soft-handles spikes, and returns analysis metadata for the UI.
 */

/**
 * Soft-cap extreme spikes so one unusual month does not dominate the fit.
 *
 * @param list<float|int> $y
 * @return array{series_fit:list<float>,anomalies:list<array<string,mixed>>,spike_count:int}
 */
function consumableForecastDetectAnomalies(array $y, array $monthLabels = []): array
{
    $n = count($y);
    $vals = array_map(static fn ($v) => (float) $v, $y);
    $anomalies = [];
    if ($n < 4) {
        return ['series_fit' => $vals, 'anomalies' => [], 'spike_count' => 0];
    }

    $sorted = $vals;
    sort($sorted);
    $median = $sorted[(int) floor(($n - 1) / 2)];
    $mean = array_sum($vals) / $n;
    $var = 0.0;
    foreach ($vals as $v) {
        $var += ($v - $mean) * ($v - $mean);
    }
    $std = sqrt($var / max(1, $n - 1));
    $hi = max($median * 3.0, $mean + (2.5 * $std), $median + 1.0);
    if ($hi < 1.0) {
        $hi = 1.0;
    }

    $fit = [];
    foreach ($vals as $i => $v) {
        $label = $monthLabels[$i] ?? ('m' . ($i + 1));
        if ($v > $hi && $v > 0) {
            $anomalies[] = [
                'index' => $i,
                'month' => $label,
                'value' => round($v, 2),
                'cap' => round($hi, 2),
                'type' => 'spike',
            ];
            $fit[] = $hi;
        } else {
            $fit[] = $v;
        }
    }

    return [
        'series_fit' => $fit,
        'anomalies' => $anomalies,
        'spike_count' => count($anomalies),
    ];
}

/**
 * @param list<float> $y
 */
function consumableForecastMovingAvg(array $y, int $window = 3): float
{
    if (!$y) {
        return 0.0;
    }
    $tail = array_slice($y, -$window);

    return array_sum($tail) / max(1, count($tail));
}

/**
 * Absolute percent error; null when actual ~ 0.
 */
function consumableForecastApe(?float $pred, float $actual): ?float
{
    if ($pred === null) {
        return null;
    }
    if (abs($actual) < 0.5) {
        return abs($pred - $actual) < 0.5 ? 0.0 : null;
    }

    return abs($pred - $actual) / abs($actual);
}

/**
 * One-step holdout scores for candidate models.
 *
 * @param list<float> $y cleaned series
 * @return array{best:string,scores:array<string,float>,predictions:array<string,float>,actual:float}|null
 */
function consumableForecastHoldoutSelect(array $y): ?array
{
    $n = count($y);
    if ($n < 4) {
        return null;
    }
    $train = array_slice($y, 0, $n - 1);
    $actual = (float) $y[$n - 1];
    if (!consumableForecastHasEnoughActivity($train, 3)) {
        return null;
    }

    $ols = consumableForecastOls($train);
    $olsPred = $ols ? (float) $ols['next_raw'] : null;
    $ma3 = consumableForecastMovingAvg($train, 3);
    $med3 = consumableForecastMedian(array_slice($train, -3));
    $naive = (float) $train[count($train) - 1];
    $blend = ($olsPred !== null) ? (0.30 * $olsPred + 0.40 * $ma3 + 0.30 * $med3) : ((0.55 * $ma3) + (0.45 * $med3));
    $meanTrain = array_sum($train) / max(1, count($train));

    $preds = [
        'ols' => $olsPred,
        'ma3' => $ma3,
        'med3' => $med3,
        'naive' => $naive,
        'blend' => $blend,
    ];
    $scores = [];
    $best = 'med3';
    $bestErr = INF;
    foreach ($preds as $name => $p) {
        if ($p === null) {
            continue;
        }
        $ape = consumableForecastApe((float) $p, $actual);
        if ($ape === null) {
            $ape = abs((float) $p - $actual);
        }
        // Mild penalty: naive/OLS overfit spikes on short noisy / high-peso series.
        if ($name === 'naive') {
            $ape *= ($meanTrain >= 5000) ? 1.22 : 1.08;
        }
        if ($name === 'ols') {
            $ape *= 1.06;
        }
        $scores[$name] = $ape;
        if ($ape < $bestErr) {
            $bestErr = $ape;
            $best = $name;
        }
    }

    return [
        'best' => $best,
        'scores' => $scores,
        'predictions' => array_map(static fn ($v) => $v === null ? null : round((float) $v, 2), $preds),
        'actual' => $actual,
    ];
}

/**
 * @param list<float> $vals
 */
function consumableForecastMedian(array $vals): float
{
    if (!$vals) {
        return 0.0;
    }
    $s = array_values($vals);
    sort($s);
    $n = count($s);
    $mid = (int) floor(($n - 1) / 2);
    if ($n % 2 === 1) {
        return (float) $s[$mid];
    }

    return ((float) $s[$mid] + (float) $s[$mid + 1]) / 2.0;
}

/**
 * Fit the best forecasting model for a monthly series.
 *
 * @param list<float|int> $y
 * @param list<string> $monthLabels
 * @return array<string,mixed>|null  OLS-compatible shape + analysis fields
 */
function consumableForecastFitBest(array $y, array $monthLabels = []): ?array
{
    if (!consumableForecastHasEnoughActivity($y, 3)) {
        return null;
    }

    $detected = consumableForecastDetectAnomalies($y, $monthLabels);
    $clean = $detected['series_fit'];
    $n = count($clean);

    $ols = consumableForecastOls($clean);
    if ($ols === null) {
        return null;
    }

    $ma3 = consumableForecastMovingAvg($clean, 3);
    $med3 = consumableForecastMedian(array_slice($clean, -3));
    $naive = (float) $clean[$n - 1];
    $blend = 0.30 * (float) $ols['next_raw'] + 0.40 * $ma3 + 0.30 * $med3;

    $holdout = consumableForecastHoldoutSelect($clean);
    $model = 'med3';
    if ($holdout) {
        $model = (string) $holdout['best'];
    } else {
        $r2 = (float) ($ols['r2'] ?? 0);
        $model = ($r2 >= 0.45) ? 'ols' : 'blend';
    }

    // Safety: if OLS in-sample R² is very weak, never use pure OLS.
    if ($model === 'ols' && (float) ($ols['r2'] ?? 0) < 0.15 && $n >= 5) {
        $model = 'blend';
    }

    $nextRaw = match ($model) {
        'ols' => (float) $ols['next_raw'],
        'naive' => $naive,
        'blend' => $blend,
        'ma3' => $ma3,
        default => $med3,
    };
    if ($nextRaw < 0) {
        $nextRaw = 0.0;
    }

    // Shrink wild swings toward a robust recent level (protects against spike/dip overfitting).
    $anchor = $med3 > 0 ? $med3 : $ma3;
    if ($anchor > 0 && abs($nextRaw - $anchor) / $anchor > 0.55) {
        $nextRaw = (0.40 * $nextRaw) + (0.60 * $anchor);
    }
    // Absolute cap vs history peak.
    $peak = 0.0;
    foreach ($clean as $v) {
        $peak = max($peak, (float) $v);
    }
    if ($peak > 0 && $nextRaw > $peak * 1.75) {
        $nextRaw = $peak * 1.75;
    }

    $holdoutMape = null;
    if ($holdout && isset($holdout['scores'][$model])) {
        $holdoutMape = (float) $holdout['scores'][$model];
    } elseif ($holdout && $holdout['scores']) {
        $holdoutMape = min($holdout['scores']);
    }

    $quality = consumableForecastDataQuality(
        $n,
        (float) ($ols['r2'] ?? 0),
        (float) ($ols['mape'] ?? 1),
        $holdoutMape,
        (int) $detected['spike_count']
    );

    $modelLabels = [
        'ols' => 'Linear regression',
        'ma3' => '3-month average',
        'med3' => '3-month median',
        'naive' => 'Last-month level',
        'blend' => 'Trend + average blend',
    ];

    return [
        'slope' => (float) $ols['slope'],
        'intercept' => (float) $ols['intercept'],
        'next' => (int) round($nextRaw),
        'next_raw' => $nextRaw,
        'rmse' => (float) ($ols['rmse'] ?? 0),
        'mape' => (float) ($ols['mape'] ?? 0),
        'r2' => (float) ($ols['r2'] ?? 0),
        'n' => $n,
        'model' => $model,
        'model_label' => $modelLabels[$model] ?? $model,
        'holdout_mape' => $holdoutMape,
        'holdout_mape_pct' => $holdoutMape !== null ? round($holdoutMape * 100, 1) : null,
        'holdout' => $holdout,
        'anomalies' => $detected['anomalies'],
        'spike_count' => (int) $detected['spike_count'],
        'data_quality' => $quality,
        'candidates' => [
            'ols' => round((float) $ols['next_raw'], 2),
            'ma3' => round($ma3, 2),
            'med3' => round($med3, 2),
            'naive' => round($naive, 2),
            'blend' => round($blend, 2),
        ],
    ];
}

function consumableForecastDataQuality(
    int $months,
    float $r2,
    float $inSampleMape,
    ?float $holdoutMape,
    int $spikes
): string {
    $hm = $holdoutMape ?? $inSampleMape;
    if ($months >= 6 && $hm <= 0.22 && $spikes <= 1 && $r2 >= 0.25) {
        return 'good';
    }
    if ($months >= 4 && $hm <= 0.40 && $spikes <= 2) {
        return 'fair';
    }

    return 'weak';
}

/**
 * Confidence using holdout error when available (more honest than in-sample fit).
 */
function consumableForecastConfidenceFromFit(array $fit): string
{
    $months = (int) ($fit['n'] ?? 0);
    $holdout = $fit['holdout_mape'] ?? null;
    $r2 = (float) ($fit['r2'] ?? 0);
    $mape = (float) ($fit['mape'] ?? 1);
    $rmse = (float) ($fit['rmse'] ?? 0);
    $avgAbs = abs((float) ($fit['next_raw'] ?? 0));
    if ($avgAbs < 1 && isset($fit['intercept'])) {
        $avgAbs = abs((float) $fit['intercept']);
    }

    if ($holdout !== null) {
        if ($months >= 6 && $holdout <= 0.20 && ($fit['spike_count'] ?? 0) <= 1) {
            return 'high';
        }
        if ($months >= 4 && $holdout <= 0.35) {
            return 'medium';
        }

        return 'low';
    }

    return consumableForecastConfidence($months, $rmse, $avgAbs, $mape, $r2);
}

/**
 * Project a fitted model to forecast month index x (1-based like OLS x).
 *
 * @param array<string,mixed> $fit
 * @param list<float|int> $y original series (for capping)
 */
function consumableForecastPredictFromFit(array $fit, int $forecastX, array $y = []): float
{
    $n = (int) ($fit['n'] ?? 0);
    $model = (string) ($fit['model'] ?? 'ma3');
    $baseNext = (float) ($fit['next_raw'] ?? 0);
    $targetX = max(1, $forecastX);
    $stepsAhead = max(0, $targetX - ($n + 1));

    if ($model === 'ols') {
        return consumableForecastOlsPredictAt($fit, $targetX, $y);
    }

    $slope = (float) ($fit['slope'] ?? 0);
    $damp = match ($model) {
        'blend' => 0.35,
        'naive' => 0.0,
        default => 0.15, // mild drift for MA
    };
    $pred = $baseNext + ($slope * $damp * $stepsAhead);
    if ($pred < 0) {
        $pred = 0.0;
    }
    if ($y) {
        $peak = 0.0;
        $sum = 0.0;
        foreach ($y as $v) {
            $peak = max($peak, (float) $v);
            $sum += (float) $v;
        }
        $meanY = $sum / max(1, count($y));
        $cap = max($peak * 2.5, $meanY * 2.5, 1.0);
        if ($pred > $cap) {
            $pred = $cap;
        }
    }

    return $pred;
}

/**
 * Horizon sum using the selected model.
 *
 * @param array<string,mixed>|null $fit
 * @return array{low:int,high:int,point:int}
 */
function consumableForecastHorizonSumFromFit(?array $fit, int $months = 3, array $y = []): array
{
    if ($fit === null || $months < 1) {
        return ['low' => 0, 'high' => 0, 'point' => 0];
    }
    $n = (int) ($fit['n'] ?? 0);
    $sum = 0.0;
    for ($k = 1; $k <= $months; $k++) {
        $sum += consumableForecastPredictFromFit($fit, $n + $k, $y);
    }
    $rmse = (float) ($fit['rmse'] ?? 0);
    $holdout = (float) ($fit['holdout_mape'] ?? 0.25);
    $pad = max(1.0, $rmse * sqrt($months), abs($sum) * max(0.08, $holdout));
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

/**
 * Build analysis payload for API/UI.
 *
 * @param array<string,mixed> $fit
 * @return array<string,mixed>
 */
function consumableForecastAnalysisPayload(array $fit): array
{
    return [
        'model' => (string) ($fit['model'] ?? 'ma3'),
        'model_label' => (string) ($fit['model_label'] ?? '3-month average'),
        'holdout_mape_pct' => $fit['holdout_mape_pct'] ?? null,
        'data_quality' => (string) ($fit['data_quality'] ?? 'weak'),
        'spike_count' => (int) ($fit['spike_count'] ?? 0),
        'anomalies' => $fit['anomalies'] ?? [],
        'candidates' => $fit['candidates'] ?? [],
        'in_sample_r2' => round((float) ($fit['r2'] ?? 0), 3),
        'in_sample_mape_pct' => round((float) ($fit['mape'] ?? 0) * 100, 1),
    ];
}
