<?php
/**
 * Seed DEMO consumable forecast data for Health Office only (DEMO-CF tagged).
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts\seed-health-forecast-demo.php
 * Cleanup (all DEMO-CF, including this):
 *   C:\xampp\php\php.exe scripts\delete-consumable-forecast-demo.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';
require_once dirname(__DIR__) . '/includes/consumable_stock.php';

const DEMO_CF_TAG = 'DEMO-CF';
const DEMO_CF_NOTE = 'DEMO-CF Health Office forecast sample — safe to delete';

$conn = getDBConnection();
consumableEnsureSchema($conn);

$st = $conn->prepare(
    "SELECT id, name, code FROM offices
     WHERE UPPER(COALESCE(code,'')) = 'HLTH'
        OR name LIKE '%Health%'
     ORDER BY CASE WHEN UPPER(COALESCE(code,'')) = 'HLTH' THEN 0 ELSE 1 END, id ASC
     LIMIT 1"
);
$st->execute();
$office = $st->fetch(PDO::FETCH_ASSOC);
if (!$office) {
    fwrite(STDERR, "Health Office not found. Create office code HLTH first.\n");
    exit(1);
}

$oid = (int) $office['id'];
$officeName = (string) $office['name'];
echo "Seeding forecast sample for: {$officeName} (id={$oid}, code={$office['code']})\n";

// Remove only Health Office DEMO issuances/lines first (keep other demo items if any).
$conn->beginTransaction();
try {
    $issIds = $conn->prepare(
        "SELECT id FROM issuance_records
         WHERE office_id = ?
           AND (form_number LIKE 'DEMO-CF-HLTH-%' OR (remarks LIKE 'DEMO-CF Health%' AND office_id = ?))"
    );
    $issIds->execute([$oid, $oid]);
    $ids = array_map('intval', $issIds->fetchAll(PDO::FETCH_COLUMN));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $conn->prepare("DELETE FROM issuance_consumable_lines WHERE issuance_id IN ($in)")->execute($ids);
        $conn->prepare("DELETE FROM consumable_stock_moves WHERE issuance_id IN ($in)")->execute($ids);
        $conn->prepare("DELETE FROM issuance_records WHERE id IN ($in)")->execute($ids);
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'Cleanup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$catalog = [
    [
        'name' => 'DEMO-CF Alcohol 70% 500ml',
        'key' => 'demo-cf-hlth-alcohol-70',
        'unit' => 'bot',
        'unit_cost' => 65.00,
        'starting' => 40,
        'monthly' => [14, 16, 18, 20, 22, 24],
    ],
    [
        'name' => 'DEMO-CF Mask Surgical (box)',
        'key' => 'demo-cf-hlth-mask-surgical',
        'unit' => 'box',
        'unit_cost' => 120.00,
        'starting' => 35,
        'monthly' => [12, 14, 13, 15, 16, 18],
    ],
    [
        'name' => 'DEMO-CF Cotton Balls (pack)',
        'key' => 'demo-cf-hlth-cotton-balls',
        'unit' => 'pack',
        'unit_cost' => 45.00,
        'starting' => 50,
        'monthly' => [10, 11, 12, 13, 14, 15],
    ],
    [
        'name' => 'DEMO-CF Disposable Gloves (box)',
        'key' => 'demo-cf-hlth-gloves',
        'unit' => 'box',
        'unit_cost' => 180.00,
        'starting' => 28,
        'monthly' => [8, 9, 10, 11, 12, 14],
    ],
    [
        'name' => 'DEMO-CF Examination Table Paper (roll)',
        'key' => 'demo-cf-hlth-table-paper',
        'unit' => 'roll',
        'unit_cost' => 95.00,
        'starting' => 20,
        'monthly' => [5, 6, 6, 7, 8, 9],
    ],
    [
        'name' => 'DEMO-CF Hand Soap 500ml',
        'key' => 'demo-cf-hlth-hand-soap',
        'unit' => 'bot',
        'unit_cost' => 55.00,
        'starting' => 30,
        'monthly' => [9, 10, 11, 12, 13, 14],
    ],
    [
        'name' => 'DEMO-CF Bond Paper A4 (ream)',
        'key' => 'demo-cf-hlth-bond-a4',
        'unit' => 'ream',
        'unit_cost' => 220.00,
        'starting' => 25,
        'monthly' => [6, 7, 8, 9, 10, 11],
    ],
    [
        'name' => 'DEMO-CF Ballpen Black (box)',
        'key' => 'demo-cf-hlth-ballpen',
        'unit' => 'box',
        'unit_cost' => 85.00,
        'starting' => 40,
        'monthly' => [5, 5, 6, 6, 7, 7],
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
        $find = $conn->prepare('SELECT id FROM consumable_items WHERE name_key = ?');
        $find->execute([$c['key']]);
        $itemId = (int) $find->fetchColumn();
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

        $need = array_sum($c['monthly']) + 30;
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
    foreach ($catalog as $c) {
        $itemId = $itemIds[$c['key']];
        foreach ($c['monthly'] as $mi => $baseQty) {
            if (!isset($monthStarts[$mi])) {
                continue;
            }
            $qty = max(1, (int) $baseQty);
            $day = min(26, 4 + ($mi * 3));
            $issueDate = $monthStarts[$mi]->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $form = sprintf('DEMO-CF-HLTH-%04d', $formSeq++);

            $issIns->execute([
                $form,
                $oid,
                'Health Office Custodian',
                'Midwife / Staff',
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

    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

require_once dirname(__DIR__) . '/includes/consumable_forecast.php';
$preview = consumableForecastBuild($conn, $oid);
$dept = $preview['departments'][0] ?? null;

echo "Done.\n";
echo "  Items: " . count($catalog) . "\n";
echo "  Issuances: DEMO-CF-HLTH-####\n";
if ($dept) {
    echo "  Forecast for {$dept['office_name']}:\n";
    echo "    next_month_qty=" . ($dept['next_month_qty'] ?? '—') . "\n";
    echo "    est_budget=" . ($dept['est_budget'] ?? '—') . "\n";
    echo "    trend=" . ($dept['trend'] ?? '—') . "\n";
    echo "    note=" . ($dept['recommendation'] ?? $dept['note'] ?? '—') . "\n";
}
echo "Log in as Health Office staff → Forecast to view.\n";
echo "Delete later: C:\\xampp\\php\\php.exe scripts\\delete-consumable-forecast-demo.php\n";
