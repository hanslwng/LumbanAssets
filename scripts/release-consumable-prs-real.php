<?php
/**
 * Make capstone consumable PR data look real and release them into Issuance history.
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts\release-consumable-prs-real.php
 */

@set_time_limit(0);
ini_set('memory_limit', '512M');

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';
require_once dirname(__DIR__) . '/includes/consumable_stock.php';
require_once dirname(__DIR__) . '/includes/consumable_issuance.php';
require_once dirname(__DIR__) . '/includes/issuance_helpers.php';

$conn = getDBConnection();
consumableEnsureSchema($conn);
issuanceEnsureItemsTable($conn);

$demoTag = 'SIMULATED DEMO DATA — FOR CAPSTONE TESTING';

echo "=== 1) Strip DEMO labels from purchase requests ===\n";
$demoPrs = $conn->query(
    "SELECT id, pr_number, purpose FROM proc_pr
     WHERE is_consumable = 1
       AND (pr_number LIKE 'DEMO-PR-%' OR purpose LIKE " . $conn->quote('%' . $demoTag . '%') . ")
     ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$renamed = 0;
$purposeCleaned = 0;
foreach ($demoPrs as $pr) {
    $id = (int) $pr['id'];
    $num = (string) $pr['pr_number'];
    $purpose = (string) ($pr['purpose'] ?? '');
    $newNum = $num;
    if (stripos($num, 'DEMO-PR-') === 0) {
        $candidate = preg_replace('/^DEMO-PR-/i', 'PR-', $num);
        $chk = $conn->prepare('SELECT id FROM proc_pr WHERE pr_number = ? AND id <> ? LIMIT 1');
        $chk->execute([$candidate, $id]);
        if ($chk->fetchColumn()) {
            // Keep unique: PR-2026-C-###### from original trailing digits if possible
            if (preg_match('/(\d+)$/', $num, $m)) {
                $candidate = 'PR-2026-C-' . str_pad($m[1], 6, '0', STR_PAD_LEFT);
            } else {
                $candidate = 'PR-2026-C-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            }
            $n = 1;
            while (true) {
                $chk->execute([$candidate, $id]);
                if (!$chk->fetchColumn()) {
                    break;
                }
                $candidate = 'PR-2026-C-' . str_pad((string) ($id + $n), 6, '0', STR_PAD_LEFT);
                $n++;
                if ($n > 50) {
                    $candidate = 'PR-' . date('Y') . '-C' . $id . '-' . $n;
                    break;
                }
            }
        }
        $newNum = $candidate;
    }
    $newPurpose = trim(str_replace($demoTag, '', $purpose));
    $newPurpose = trim(preg_replace('/\s{2,}/', ' ', $newPurpose) ?? $newPurpose);
    $newPurpose = trim($newPurpose, " \t\n\r\0\x0B.");
    if ($newPurpose === '') {
        $newPurpose = 'For office use';
    }

    if ($newNum !== $num || $newPurpose !== $purpose) {
        $conn->prepare('UPDATE proc_pr SET pr_number = ?, purpose = ? WHERE id = ?')
            ->execute([$newNum, $newPurpose, $id]);
        if ($newNum !== $num) {
            $renamed++;
        }
        if ($newPurpose !== $purpose) {
            $purposeCleaned++;
        }
    }
}
echo "PR numbers renamed: $renamed\n";
echo "PR purposes cleaned: $purposeCleaned\n";
echo "DEMO-PR remaining: " . $conn->query("SELECT COUNT(*) FROM proc_pr WHERE pr_number LIKE 'DEMO-PR-%'")->fetchColumn() . "\n";

echo "\n=== 2) Strip DEMO-CF from consumable items / notes ===\n";
$items = $conn->query(
    "SELECT id, name, name_key FROM consumable_items
     WHERE name LIKE 'DEMO-CF%' OR name_key LIKE 'demo-cf%' OR aliases = 'DEMO-CF'"
)->fetchAll(PDO::FETCH_ASSOC);
$itemRenamed = 0;
foreach ($items as $it) {
    $name = trim(preg_replace('/^DEMO-CF\s+/i', '', (string) $it['name']) ?? '');
    if ($name === '') {
        continue;
    }
    $key = consumableNormalizeNameKey($name);
    $chk = $conn->prepare('SELECT id FROM consumable_items WHERE name_key = ? AND id <> ? LIMIT 1');
    $chk->execute([$key, (int) $it['id']]);
    if ($chk->fetchColumn()) {
        $conn->prepare('UPDATE consumable_items SET is_active = 0, aliases = NULL WHERE id = ?')
            ->execute([(int) $it['id']]);
    } else {
        $conn->prepare('UPDATE consumable_items SET name = ?, name_key = ?, aliases = NULL WHERE id = ?')
            ->execute([$name, $key, (int) $it['id']]);
        $itemRenamed++;
    }
}
echo "Items renamed: $itemRenamed\n";
try {
    $conn->exec(
        "UPDATE consumable_stock_moves
         SET note = TRIM(REPLACE(REPLACE(COALESCE(note,''), 'DEMO-CF forecast sample — safe to delete', ''), 'DEMO-CF', ''))
         WHERE note LIKE '%DEMO-CF%'"
    );
    $conn->exec(
        "UPDATE issuance_records
         SET form_number = REPLACE(form_number, 'DEMO-CF-', 'PAR-'),
             remarks = TRIM(REPLACE(REPLACE(COALESCE(remarks,''), 'DEMO-CF Health Office forecast sample — safe to delete', ''), 'DEMO-CF forecast sample — safe to delete', ''))
         WHERE form_number LIKE 'DEMO-CF%' OR remarks LIKE '%DEMO-CF%'"
    );
} catch (Throwable $e) {
    echo "Note cleanup warning: " . $e->getMessage() . "\n";
}

echo "\n=== 3) Restock + release completed consumable PRs into Issuance ===\n";
$prs = $conn->query(
    "SELECT p.id, p.pr_number, p.pr_date, p.office_id, o.name AS office_name
     FROM proc_pr p
     INNER JOIN offices o ON o.id = p.office_id
     WHERE p.is_consumable = 1
       AND p.status = 'completed'
       AND NOT EXISTS (
           SELECT 1 FROM issuance_records i
           WHERE i.proc_pr_id = p.id AND COALESCE(i.is_consumable, 0) = 1
       )
     ORDER BY p.pr_date ASC, p.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$total = count($prs);
echo "PRs to release: $total\n";

$ok = 0;
$fail = 0;
$lines = 0;
$errors = [];

foreach ($prs as $i => $pr) {
    $prId = (int) $pr['id'];
    $office = (string) ($pr['office_name'] ?? 'Department');
    $issueDate = substr((string) ($pr['pr_date'] ?? date('Y-m-d')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
        $issueDate = date('Y-m-d');
    }

    try {
        $conn->beginTransaction();
        consumableRestockFromCompletedPr($conn, $prId);
        $result = consumableIssueFromPr(
            $conn,
            $prId,
            'Custodian — ' . $office,
            'Department Staff',
            $issueDate,
            'Released consumables for ' . $office,
            ''
        );
        $conn->commit();
        $ok++;
        $lines += (int) ($result['attached'] ?? 0);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $fail++;
        if (count($errors) < 20) {
            $errors[] = 'PR ' . ($pr['pr_number'] ?? $prId) . ': ' . $e->getMessage();
        }
    }

    if (($i + 1) % 50 === 0 || ($i + 1) === $total) {
        echo "  progress " . ($i + 1) . "/$total (ok=$ok fail=$fail lines=$lines)\n";
    }
}

echo "\n=== DONE ===\n";
echo "Released OK: $ok\n";
echo "Failed: $fail\n";
echo "Consumable lines attached: $lines\n";
echo "Issuance consumable headers: " . $conn->query(
    "SELECT COUNT(*) FROM issuance_records WHERE COALESCE(is_consumable,0)=1"
)->fetchColumn() . "\n";
echo "Issuance consumable lines: " . $conn->query(
    "SELECT COUNT(*) FROM issuance_consumable_lines"
)->fetchColumn() . "\n";
echo "DEMO-PR remaining: " . $conn->query(
    "SELECT COUNT(*) FROM proc_pr WHERE pr_number LIKE 'DEMO-PR-%'"
)->fetchColumn() . "\n";
echo "SIMULATED tag remaining: " . $conn->query(
    "SELECT COUNT(*) FROM proc_pr WHERE purpose LIKE '%SIMULATED DEMO DATA%'"
)->fetchColumn() . "\n";

if ($errors) {
    echo "\nSample errors:\n";
    foreach ($errors as $e) {
        echo " - $e\n";
    }
}

echo "\nSample issuance rows:\n";
$sample = $conn->query(
    "SELECT i.form_number, i.issued_to, o.name office,
            (SELECT GROUP_CONCAT(icl.item_name ORDER BY icl.id SEPARATOR ', ')
             FROM issuance_consumable_lines icl WHERE icl.issuance_id = i.id) AS items
     FROM issuance_records i
     LEFT JOIN offices o ON o.id = i.office_id
     WHERE COALESCE(i.is_consumable,0)=1
     ORDER BY i.id DESC LIMIT 5"
);
foreach ($sample as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
