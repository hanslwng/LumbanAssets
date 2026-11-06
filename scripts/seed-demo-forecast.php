<?php
/**
 * CLI: rebuild live (or demo) demand history and forecast cache.
 * Usage: C:\xampp\php\php.exe scripts/seed-demo-forecast.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from the command line.\n");
    exit(1);
}

set_time_limit(180);
require_once dirname(__DIR__) . '/bootstrap.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once dirname(__DIR__) . '/includes/demo_forecast.php';

$conn = getDBConnection();
$mode = settingsForecastMode($conn);
$t0 = microtime(true);
$n = $mode === 'demo'
    ? demoForecastRebuildHistory($conn)
    : liveForecastRebuildHistory($conn);
$data = demoForecastGetOrBuild($conn, true);
$elapsed = round(microtime(true) - $t0, 2);

$sc = $data['status_counts'] ?? [];
echo "Mode: {$mode}\n";
echo "Demand rows: {$n}\n";
echo "Series: " . ($data['series_count'] ?? 0) . "\n";
echo "As of: " . ($data['as_of'] ?? 'n/a') . "\n";
echo "History: {$data['history_from']}–{$data['history_to']} ({$data['history_rows']} rows)\n";
echo "Forecast: {$data['forecast_from']}–{$data['forecast_to']}\n";
echo "Mean MAE: " . ($data['mean_model_mae'] ?? 'n/a') . "\n";
echo "Statuses: " . json_encode($sc) . "\n";
echo "Elapsed: {$elapsed}s\n";
if (!empty($data['recommendations'][0])) {
    $r = $data['recommendations'][0];
    echo "Top row: {$r['office_name']} / {$r['item_category']} → {$r['status']} order {$r['recommended_order_qty']}\n";
}
