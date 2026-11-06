<?php
/**
 * Seed DEMO consumable forecast data (tagged for safe deletion later).
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts\seed-consumable-forecast-demo.php
 * Cleanup:
 *   C:\xampp\php\php.exe scripts\delete-consumable-forecast-demo.php
 *
 * All demo rows are tagged with note/name_key/form_number prefix DEMO-CF- so cleanup
 * never touches real PPE/SE or real consumable data.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';
require_once dirname(__DIR__) . '/includes/consumable_stock.php';

const DEMO_CF_TAG = 'DEMO-CF';
const DEMO_CF_NOTE = 'DEMO-CF forecast sample — safe to delete';

$conn = getDBConnection();
consumableEnsureSchema($conn);

// Idempotent: wipe previous demo first.
require_once __DIR__ . '/delete-consumable-forecast-demo.php';
if (function_exists('consumableForecastDemoDelete')) {
    consumableForecastDemoDelete($conn, true);
}

$offices = $conn->query(
    'SELECT id, name, code FROM offices WHERE COALESCE(is_active, 1) = 1 ORDER BY name ASC LIMIT 5'
)->fetchAll(PDO::FETCH_ASSOC);
if (count($offices) < 1) {
    fwrite(STDERR, "No active offices found. Create offices first.\n");
    exit(1);
}

$catalog = [
    [
        'name' => 'DEMO-CF Bond Paper A4 (ream)',
        'key' => 'demo-cf-bond-paper-a4',
        'unit' => 'ream',
        'unit_cost' => 220.00,
        'starting' => 80,
        'monthly' => [18, 22, 28, 35, 42, 48],
    ],
    [
        'name' => 'DEMO-CF Ballpen Black (box)',
        'key' => 'demo-cf-ballpen-black',
        'unit' => 'box',
        'unit_cost' => 85.00,
        'starting' => 120,
        'monthly' => [20, 21, 19, 20, 22, 21],
    ],
    [
        'name' => 'DEMO-CF Toner HP 85A',
        'key' => 'demo-cf-toner-hp-85a',
        'unit' => 'pc',
        'unit_cost' => 1850.00,
        'starting' => 6,
        'monthly' => [2, 3, 4, 5, 6, 7],
    ],
    [
        'name' => 'DEMO-CF Folder Long (pack)',
        'key' => 'demo-cf-folder-long',
        'unit' => 'pack',
        'unit_cost' => 95.00,
        'starting' => 40,
        'monthly' => [30, 26, 22, 18, 14, 12],
    ],
    [
        'name' => 'DEMO-CF Dishwashing Liquid 1L',
        'key' => 'demo-cf-dishwash-1l',
        'unit' => 'bot',
        'unit_cost' => 75.00,
        'starting' => 15,
        'monthly' => [8, 10],
    ],
    [
        'name' => 'DEMO-CF Alcohol 70% 500ml',
        'key' => 'demo-cf-alcohol-70',
        'unit' => 'bot',
        'unit_cost' => 65.00,
        'starting' => 25,
        'monthly' => [12, 14, 15, 16, 18, 20],
    ],
    [
        'name' => 'DEMO-CF Mask Surgical (box)',
        'key' => 'demo-cf-mask-surgical',
        'unit' => 'box',
        'unit_cost' => 120.00,
        'starting' => 30,
        'monthly' => [10, 12, 11, 13, 14, 15],
    ],
    [
        'name' => 'DEMO-CF Tissue Paper (pack)',
        'key' => 'demo-cf-tissue-pack',
        'unit' => 'pack',
        'unit_cost' => 55.00,
        'starting' => 50,
        'monthly' => [25, 24, 26, 25, 27, 26],
    ],
    [
        'name' => 'DEMO-CF Garbage Bag XL (roll)',
        'key' => 'demo-cf-garbage-bag-xl',
        'unit' => 'roll',
        'unit_cost' => 140.00,
        'starting' => 18,
        'monthly' => [6, 8, 9, 11, 12, 14],
    ],
    [
        'name' => 'DEMO-CF Staple Wire #35',
        'key' => 'demo-cf-staple-35',
        'unit' => 'box',
        'unit_cost' => 45.00,
        'starting' => 60,
        'monthly' => [8, 7, 9, 8, 8, 7],
    ],
    [
        'name' => 'DEMO-CF Correction Tape',
        'key' => 'demo-cf-correction-tape',
        'unit' => 'pc',
        'unit_cost' => 35.00,
        'starting' => 40,
        'monthly' => [15, 14, 13, 12, 11, 10],
    ],
    [
        'name' => 'DEMO-CF Printer Ink Epson 003 Black',
        'key' => 'demo-cf-ink-epson-003',
        'unit' => 'bot',
        'unit_cost' => 280.00,
        'starting' => 8,
        'monthly' => [3, 4, 5, 6, 7, 8],
    ],
];

$monthsBack = 6;
$monthStarts = [];
$base = new DateTimeImmutable('first day of this month');
for ($i = $monthsBack; $i >= 1; $i--) {
    $monthStarts[] = $base->modify("-{$i} months");
}

$conn->beginTransaction();
try {
    $itemIds = [];
    foreach ($catalog as $c) {
        $ins = $conn->prepare(
            'INSERT INTO consumable_items (name, name_key, unit, aliases, is_active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE name = VALUES(name), unit = VALUES(unit), is_active = 1'
        );
        $ins->execute([$c['name'], $c['key'], $c['unit'], DEMO_CF_TAG]);
        $st = $conn->prepare('SELECT id FROM consumable_items WHERE name_key = ?');
        $st->execute([$c['key']]);
        $itemId = (int) $st->fetchColumn();
        $itemIds[$c['key']] = $itemId;

        $conn->prepare('INSERT IGNORE INTO consumable_stock (item_id, current_stock) VALUES (?, 0)')
            ->execute([$itemId]);
        $conn->prepare('UPDATE consumable_stock SET current_stock = ? WHERE item_id = ?')
            ->execute([(float) $c['starting'], $itemId]);

        $conn->prepare(
            'INSERT INTO consumable_stock_moves
             (item_id, move_type, quantity, unit_cost, office_id, note)
             VALUES (?, \'starting\', ?, ?, NULL, ?)'
        )->execute([$itemId, (float) $c['starting'], $c['unit_cost'], DEMO_CF_NOTE]);

        // Restock enough to cover demo releases so ledger stays sensible.
        $need = array_sum($c['monthly']) + 20;
        consumableStockApplyMove($conn, $itemId, 'restock', (float) $need, [
            'unit_cost' => $c['unit_cost'],
            'note' => DEMO_CF_NOTE . ' restock',
        ]);
    }

    $issIns = $conn->prepare(
        'INSERT INTO issuance_records
         (form_type, form_number, property_item_id, office_id, issued_to, position, issue_date, remarks, proc_pr_id, is_consumable, status)
         VALUES (\'PAR\', ?, NULL, ?, ?, ?, ?, ?, NULL, 1, \'Active\')'
    );
    $lineIns = $conn->prepare(
        'INSERT INTO issuance_consumable_lines
         (issuance_id, consumable_item_id, proc_pr_line_id, item_name, quantity, unit, unit_cost)
         VALUES (?, ?, NULL, ?, ?, ?, ?)'
    );

    $formSeq = 1;
    foreach ($offices as $oi => $office) {
        $oid = (int) $office['id'];
        $officeName = (string) $office['name'];
        // Vary intensity per office so trends differ.
        $officeScale = 0.7 + ($oi * 0.15);

        foreach ($catalog as $c) {
            $itemId = $itemIds[$c['key']];
            $series = $c['monthly'];
            foreach ($series as $mi => $baseQty) {
                if (!isset($monthStarts[$mi])) {
                    continue;
                }
                $qty = max(1, (int) round($baseQty * $officeScale * (0.85 + (($oi + $mi) % 3) * 0.08)));
                $day = min(26, 5 + (($oi + $mi) % 20));
                $issueDate = $monthStarts[$mi]->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                $form = sprintf('%s-%04d', DEMO_CF_TAG, $formSeq++);

                $issIns->execute([
                    $form,
                    $oid,
                    'Demo Custodian (' . $officeName . ')',
                    'Department Staff',
                    $issueDate,
                    DEMO_CF_NOTE,
                ]);
                $issId = (int) $conn->lastInsertId();
                $lineIns->execute([
                    $issId,
                    $itemId,
                    $c['name'],
                    $qty,
                    $c['unit'],
                    $c['unit_cost'],
                ]);
                consumableStockApplyMove($conn, $itemId, 'release', (float) $qty, [
                    'unit_cost' => $c['unit_cost'],
                    'office_id' => $oid,
                    'issuance_id' => $issId,
                    'note' => DEMO_CF_NOTE . ' release',
                ]);
            }
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

require_once dirname(__DIR__) . '/includes/consumable_forecast.php';
$preview = consumableForecastBuild($conn, null);
$reorder = 0;
$insuff = 0;
foreach ($preview['items'] as $row) {
    if (($row['status'] ?? '') === 'Reorder') {
        $reorder++;
    }
    if (($row['status'] ?? '') === 'Insufficient historical data') {
        $insuff++;
    }
}

echo "DEMO consumable forecast data seeded.\n";
echo "  Offices used: " . count($offices) . "\n";
echo "  Items: " . count($catalog) . " (name_key starts with demo-cf-)\n";
echo "  Issuance forms: prefix " . DEMO_CF_TAG . "-####\n";
echo "  Forecast preview: items=" . count($preview['items'])
    . " reorder={$reorder} insufficient={$insuff}"
    . " departments=" . count($preview['departments']) . "\n";
echo "Open Admin → Forecast to view. Delete later with:\n";
echo "  C:\\xampp\\php\\php.exe scripts\\delete-consumable-forecast-demo.php\n";
