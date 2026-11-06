<?php
/**
 * Remove only capstone-tagged simulated consumable PRs (never real records).
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts\delete-capstone-pr-forecast-demo.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_pr_forecast.php';

function capstonePrForecastDemoDelete(PDO $conn, bool $quiet = false): array
{
    $tag = LGUE_CAPSTONE_PR_DEMO_TAG;
    $like = '%' . $tag . '%';
    $out = ['prs' => 0, 'lines' => 0];

    $ids = $conn->prepare(
        "SELECT id FROM proc_pr
         WHERE purpose LIKE ?
            OR budget_received_note LIKE ?
            OR pr_number LIKE 'DEMO-PR-%'"
    );
    $ids->execute([$like, $like]);
    $prIds = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
    if (!$prIds) {
        if (!$quiet) {
            echo "No tagged demo PRs to delete.\n";
        }

        return $out;
    }

    $in = implode(',', array_fill(0, count($prIds), '?'));
    $delLines = $conn->prepare("DELETE FROM proc_pr_line WHERE pr_id IN ($in)");
    $delLines->execute($prIds);
    $out['lines'] = $delLines->rowCount();

    $delPr = $conn->prepare("DELETE FROM proc_pr WHERE id IN ($in)");
    $delPr->execute($prIds);
    $out['prs'] = $delPr->rowCount();

    if (!$quiet) {
        echo "Deleted {$out['prs']} demo PRs and {$out['lines']} lines.\n";
    }

    return $out;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $conn = getDBConnection();
    capstonePrForecastDemoDelete($conn, false);
}
