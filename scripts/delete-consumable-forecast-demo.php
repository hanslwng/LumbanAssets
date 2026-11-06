<?php
/**
 * Delete DEMO consumable forecast sample data only (DEMO-CF tagged).
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts\delete-consumable-forecast-demo.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';

function consumableForecastDemoDelete(PDO $conn, bool $quiet = false): void
{
    consumableEnsureSchema($conn);

    $deleted = [
        'lines' => 0,
        'issuances' => 0,
        'moves' => 0,
        'stock' => 0,
        'items' => 0,
    ];

    try {
        $conn->beginTransaction();

        // Lines linked to DEMO issuances
        $st = $conn->prepare(
            "SELECT icl.id
             FROM issuance_consumable_lines icl
             INNER JOIN issuance_records i ON i.id = icl.issuance_id
             WHERE i.form_number LIKE 'DEMO-CF-%'
                OR i.remarks LIKE 'DEMO-CF%'"
        );
        $st->execute();
        $lineIds = $st->fetchAll(PDO::FETCH_COLUMN);
        if ($lineIds) {
            $in = implode(',', array_fill(0, count($lineIds), '?'));
            $del = $conn->prepare("DELETE FROM issuance_consumable_lines WHERE id IN ($in)");
            $del->execute(array_map('intval', $lineIds));
            $deleted['lines'] = $del->rowCount();
        }

        $delIss = $conn->prepare(
            "DELETE FROM issuance_records
             WHERE form_number LIKE 'DEMO-CF-%' OR remarks LIKE 'DEMO-CF%'"
        );
        $delIss->execute();
        $deleted['issuances'] = $delIss->rowCount();

        $delMoves = $conn->prepare("DELETE FROM consumable_stock_moves WHERE note LIKE 'DEMO-CF%'");
        $delMoves->execute();
        $deleted['moves'] = $delMoves->rowCount();

        $itemIds = $conn->query(
            "SELECT id FROM consumable_items
             WHERE name_key LIKE 'demo-cf-%' OR name LIKE 'DEMO-CF%' OR aliases = 'DEMO-CF'"
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($itemIds) {
            $in = implode(',', array_fill(0, count($itemIds), '?'));
            $ids = array_map('intval', $itemIds);
            $delStock = $conn->prepare("DELETE FROM consumable_stock WHERE item_id IN ($in)");
            $delStock->execute($ids);
            $deleted['stock'] = $delStock->rowCount();
            // Any leftover lines/moves for these items
            $conn->prepare("DELETE FROM issuance_consumable_lines WHERE consumable_item_id IN ($in)")->execute($ids);
            $conn->prepare("DELETE FROM consumable_stock_moves WHERE item_id IN ($in)")->execute($ids);
            $delItems = $conn->prepare("DELETE FROM consumable_items WHERE id IN ($in)");
            $delItems->execute($ids);
            $deleted['items'] = $delItems->rowCount();
        }

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    if (!$quiet) {
        echo "DEMO-CF forecast sample deleted.\n";
        echo '  issuance lines: ' . $deleted['lines'] . "\n";
        echo '  issuances: ' . $deleted['issuances'] . "\n";
        echo '  stock moves: ' . $deleted['moves'] . "\n";
        echo '  stock rows: ' . $deleted['stock'] . "\n";
        echo '  items: ' . $deleted['items'] . "\n";
    }
}

// Allow require-from-seed without executing main.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    try {
        $conn = getDBConnection();
        consumableForecastDemoDelete($conn, false);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Delete failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
