<?php
/**
 * Smoke-test consumables forecast schema + empty dataset.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_forecast.php';

$conn = getDBConnection();
consumableEnsureSchema($conn);
$data = consumableForecastBuild($conn, null);
echo "items=" . count($data['items']) . " depts=" . count($data['departments']) . PHP_EOL;
echo "summary=" . json_encode($data['summary']) . PHP_EOL;
echo "meta=" . json_encode($data['meta']) . PHP_EOL;

// OLS unit check
$ols = consumableForecastOls([10, 12, 14]);
echo "ols_next=" . ($ols['next'] ?? 'null') . " slope=" . ($ols['slope'] ?? '') . PHP_EOL;
$insuff = consumableForecastOls([1, 2]);
echo "insuff=" . ($insuff === null ? 'yes' : 'no') . PHP_EOL;

$cols = $conn->query("SHOW COLUMNS FROM proc_pr LIKE 'is_consumable'")->fetch();
echo "proc_pr.is_consumable=" . ($cols ? 'ok' : 'MISSING') . PHP_EOL;
$cols2 = $conn->query("SHOW COLUMNS FROM issuance_records LIKE 'is_consumable'")->fetch();
echo "issuance_records.is_consumable=" . ($cols2 ? 'ok' : 'MISSING') . PHP_EOL;
echo "PASS\n";
