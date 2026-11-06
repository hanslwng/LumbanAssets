<?php
/**
 * Delete consumable Purchase Requests dated in September 2026
 * and their canvass / PO / AIR / issuance / stock-move rows.
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts/delete-september-consumable-prs.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';

$conn = getDBConnection();
consumableEnsureSchema($conn);

$prIds = $conn->query(
    "SELECT id FROM proc_pr
     WHERE is_consumable = 1
       AND pr_date >= '2026-09-01'
       AND pr_date < '2026-10-01'"
)->fetchAll(PDO::FETCH_COLUMN);
$prIds = array_map('intval', $prIds ?: []);
if (!$prIds) {
    echo "No September 2026 consumable PRs found.\n";
    exit(0);
}

$in = implode(',', $prIds);
echo 'September consumable PRs to delete: ' . count($prIds) . "\n";

$conn->beginTransaction();
try {
    $issIds = $conn->query(
        "SELECT id FROM issuance_records WHERE proc_pr_id IN ($in)"
    )->fetchAll(PDO::FETCH_COLUMN);
    $issIds = array_map('intval', $issIds ?: []);
    $issN = 0;
    $issLineN = 0;
    if ($issIds) {
        $iin = implode(',', $issIds);
        try {
            $conn->exec("DELETE FROM issuance_items WHERE issuance_id IN ($iin)");
        } catch (Throwable $e) {
            // table may not exist
        }
        $issLineN = (int) $conn->exec("DELETE FROM issuance_consumable_lines WHERE issuance_id IN ($iin)");
        $issN = (int) $conn->exec("DELETE FROM issuance_records WHERE id IN ($iin)");
    }

    $movesN = (int) $conn->exec("DELETE FROM consumable_stock_moves WHERE proc_pr_id IN ($in)");

    $poIds = $conn->query("SELECT id FROM proc_po WHERE pr_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
    $poIds = array_map('intval', $poIds ?: []);
    $airN = 0;
    $airLineN = 0;
    $poLineN = 0;
    $poN = 0;
    if ($poIds) {
        $pin = implode(',', $poIds);
        $airIds = [];
        try {
            $airIds = $conn->query("SELECT id FROM proc_air WHERE po_id IN ($pin)")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            $airIds = [];
        }
        $airIds = array_map('intval', $airIds ?: []);
        if ($airIds) {
            $ain = implode(',', $airIds);
            $airLineN = (int) $conn->exec("DELETE FROM proc_air_line WHERE air_id IN ($ain)");
            $airN = (int) $conn->exec("DELETE FROM proc_air WHERE id IN ($ain)");
        }
        $poLineN = (int) $conn->exec("DELETE FROM proc_po_line WHERE po_id IN ($pin)");
        $poN = (int) $conn->exec("DELETE FROM proc_po WHERE id IN ($pin)");
    }

    $qIds = $conn->query("SELECT id FROM proc_quotation WHERE pr_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
    $qIds = array_map('intval', $qIds ?: []);
    $qlN = 0;
    $qN = 0;
    if ($qIds) {
        $qin = implode(',', $qIds);
        $qlN = (int) $conn->exec("DELETE FROM proc_quotation_line WHERE quotation_id IN ($qin)");
        $qN = (int) $conn->exec("DELETE FROM proc_quotation WHERE id IN ($qin)");
    }

    $lineN = (int) $conn->exec("DELETE FROM proc_pr_line WHERE pr_id IN ($in)");
    $prN = (int) $conn->exec("DELETE FROM proc_pr WHERE id IN ($in) AND is_consumable = 1");

    try {
        $nIds = $conn->query(
            "SELECT id FROM notifications
             WHERE related_id IN ($in)
               AND related_type IN ('pr','purchase_request','po','proc_pr','proc_po')"
        )->fetchAll(PDO::FETCH_COLUMN);
        $nIds = array_map('intval', $nIds ?: []);
        if ($nIds) {
            $nin = implode(',', $nIds);
            $conn->exec("DELETE FROM notification_reads WHERE notification_id IN ($nin)");
            $conn->exec("DELETE FROM notifications WHERE id IN ($nin)");
        }
    } catch (Throwable $e) {
        // notifications optional
    }

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

    echo "Deleted PRs: {$prN}\n";
    echo "Deleted PR lines: {$lineN}\n";
    echo "Deleted quotations: {$qN} (lines {$qlN})\n";
    echo "Deleted POs: {$poN} (lines {$poLineN})\n";
    echo "Deleted AIRs: {$airN} (lines {$airLineN})\n";
    echo "Deleted issuances: {$issN} (lines {$issLineN})\n";
    echo "Deleted stock moves: {$movesN}\n";
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'Delete failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$left = (int) $conn->query(
    "SELECT COUNT(*) FROM proc_pr
     WHERE is_consumable = 1 AND pr_date >= '2026-09-01' AND pr_date < '2026-10-01'"
)->fetchColumn();
$byMonth = $conn->query(
    "SELECT DATE_FORMAT(pr_date,'%Y-%m') ym, COUNT(*) n
     FROM proc_pr WHERE is_consumable = 1 GROUP BY ym ORDER BY ym"
)->fetchAll(PDO::FETCH_ASSOC);
echo "September consumable PRs left: {$left}\n";
foreach ($byMonth as $r) {
    echo "  {$r['ym']}: {$r['n']}\n";
}
