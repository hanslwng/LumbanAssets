<?php
/**
 * Wipe all consumable procurement/issuance demo data and replace from ML Excel export JSON.
 *
 * Pipeline:
 *   python scripts/export-ml-pr-dataset.py
 *   C:\xampp\php\php.exe scripts/replace-consumable-from-ml-dataset.php
 *
 * Optional flags:
 *   --skip-canvass   do not create quotations/PO/AIR
 *   --skip-issue     do not restock+issue into Issuance
 */

@set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';
require_once dirname(__DIR__) . '/includes/consumable_stock.php';
require_once dirname(__DIR__) . '/includes/consumable_issuance.php';
require_once dirname(__DIR__) . '/includes/issuance_helpers.php';

$skipCanvass = in_array('--skip-canvass', $argv ?? [], true);
$skipIssue = in_array('--skip-issue', $argv ?? [], true);

$jsonPath = dirname(__DIR__) . '/storage/imports/ml_pr_jan_sep_2026.json';
if (!is_file($jsonPath)) {
    fwrite(STDERR, "Missing $jsonPath — run: python scripts/export-ml-pr-dataset.py\n");
    exit(1);
}

$payload = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($payload) || empty($payload['purchase_requests'])) {
    fwrite(STDERR, "Invalid JSON payload\n");
    exit(1);
}

$conn = getDBConnection();
consumableEnsureSchema($conn);
issuanceEnsureItemsTable($conn);

$officeByCode = [];
foreach ($conn->query('SELECT id, code, name, head_name, head_position FROM offices') as $row) {
    $officeByCode[strtoupper(trim((string) $row['code']))] = $row;
}

$adminId = (int) ($conn->query(
    "SELECT id FROM users WHERE role IN ('admin','Admin') ORDER BY id ASC LIMIT 1"
)->fetchColumn() ?: 0);

$suppliers = [
    ['name' => 'Lumban Office & School Supply', 'address' => 'National Highway, Lumban, Laguna'],
    ['name' => 'Laguna Trading Center', 'address' => 'Brgy. Primera Parang, Santa Cruz, Laguna'],
    ['name' => 'Pagsanjan Commercial Hub', 'address' => 'Maharlika Highway, Pagsanjan, Laguna'],
    ['name' => 'Calamba Paper & Office Depot', 'address' => 'Crossing, Calamba City, Laguna'],
    ['name' => 'Bay Retail Supplies', 'address' => 'Municipal Road, Bay, Laguna'],
    ['name' => 'Sta. Cruz General Merchandise', 'address' => 'Poblacion, Santa Cruz, Laguna'],
];

function wipeConsumableUniverse(PDO $conn): array
{
    $stats = [];
    $conn->beginTransaction();
    try {
        $prIds = $conn->query('SELECT id FROM proc_pr WHERE is_consumable = 1')->fetchAll(PDO::FETCH_COLUMN);
        $prIds = array_map('intval', $prIds ?: []);
        $stats['old_prs'] = count($prIds);

        // Issuance consumable lines + headers
        $issIds = $conn->query(
            'SELECT id FROM issuance_records WHERE COALESCE(is_consumable,0)=1'
        )->fetchAll(PDO::FETCH_COLUMN);
        $issIds = array_map('intval', $issIds ?: []);
        if ($issIds) {
            $in = implode(',', $issIds);
            $stats['iss_lines'] = $conn->exec("DELETE FROM issuance_consumable_lines WHERE issuance_id IN ($in)");
            $stats['issuances'] = $conn->exec("DELETE FROM issuance_records WHERE id IN ($in)");
        } else {
            $stats['iss_lines'] = 0;
            $stats['issuances'] = 0;
        }

        // Stock moves / stock / items (consumable catalog rebuilds on issue)
        $stats['stock_moves'] = $conn->exec('DELETE FROM consumable_stock_moves');
        $stats['stock'] = $conn->exec('DELETE FROM consumable_stock');
        $stats['items'] = $conn->exec('DELETE FROM consumable_items');

        if ($prIds) {
            $in = implode(',', $prIds);
            // AIR via PO
            $poIds = $conn->query("SELECT id FROM proc_po WHERE pr_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
            $poIds = array_map('intval', $poIds ?: []);
            if ($poIds) {
                $pin = implode(',', $poIds);
                try {
                    $airIds = $conn->query("SELECT id FROM proc_air WHERE po_id IN ($pin)")->fetchAll(PDO::FETCH_COLUMN);
                    $airIds = array_map('intval', $airIds ?: []);
                    if ($airIds) {
                        $ain = implode(',', $airIds);
                        $conn->exec("DELETE FROM proc_air_line WHERE air_id IN ($ain)");
                        $stats['air'] = $conn->exec("DELETE FROM proc_air WHERE id IN ($ain)");
                    } else {
                        $stats['air'] = 0;
                    }
                } catch (Throwable $e) {
                    $stats['air'] = 0;
                }
                $conn->exec("DELETE FROM proc_po_line WHERE po_id IN ($pin)");
                $stats['po'] = $conn->exec("DELETE FROM proc_po WHERE id IN ($pin)");
            } else {
                $stats['air'] = 0;
                $stats['po'] = 0;
            }

            $qIds = $conn->query("SELECT id FROM proc_quotation WHERE pr_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
            $qIds = array_map('intval', $qIds ?: []);
            if ($qIds) {
                $qin = implode(',', $qIds);
                $conn->exec("DELETE FROM proc_quotation_line WHERE quotation_id IN ($qin)");
                $stats['quotes'] = $conn->exec("DELETE FROM proc_quotation WHERE id IN ($qin)");
            } else {
                $stats['quotes'] = 0;
            }

            $stats['pr_lines'] = $conn->exec("DELETE FROM proc_pr_line WHERE pr_id IN ($in)");
            $stats['prs'] = $conn->exec("DELETE FROM proc_pr WHERE id IN ($in)");
        } else {
            $stats['air'] = 0;
            $stats['po'] = 0;
            $stats['quotes'] = 0;
            $stats['pr_lines'] = 0;
            $stats['prs'] = 0;
        }

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    return $stats;
}

echo "=== 1) Wipe consumable universe ===\n";
$wiped = wipeConsumableUniverse($conn);
foreach ($wiped as $k => $v) {
    echo "  $k: $v\n";
}

echo "\n=== 2) Import PRs from JSON ===\n";
$insPr = $conn->prepare(
    'INSERT INTO proc_pr (
        pr_number, office_id, pr_section, is_consumable, sai_number, pr_date, purpose,
        requested_by, requested_by_title, requested_signed_date,
        approved_by, approved_by_title, approved_signed_date,
        budget_received_note, status, created_by_user_id, created_at, updated_at,
        canvass_reviewed_by, canvass_reviewed_at
     ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$insLine = $conn->prepare(
    'INSERT INTO proc_pr_line
     (pr_id, line_no, quantity, unit, description, est_unit_cost, est_line_total, article, item_group)
     VALUES (?,?,?,?,?,?,?,?,?)'
);

$imported = 0;
$lineCount = 0;
$fail = 0;
$errors = [];
$prIdByNumber = [];

$conn->beginTransaction();
try {
    foreach ($payload['purchase_requests'] as $pr) {
        $code = strtoupper(trim((string) ($pr['office_code'] ?? '')));
        $office = $officeByCode[$code] ?? null;
        if (!$office) {
            $fail++;
            if (count($errors) < 20) {
                $errors[] = ($pr['pr_number'] ?? '?') . ": office code $code not found";
            }
            continue;
        }
        $prNo = (string) $pr['pr_number'];
        // Avoid clash with any leftover non-consumable PR numbers
        $chk = $conn->prepare('SELECT id FROM proc_pr WHERE pr_number = ? LIMIT 1');
        $chk->execute([$prNo]);
        if ($chk->fetchColumn()) {
            $prNo = 'C-' . $prNo;
        }

        $prDate = (string) $pr['pr_date'];
        $reqName = trim((string) ($office['head_name'] ?? '')) ?: 'Office Head';
        $reqTitle = trim((string) ($office['head_position'] ?? '')) ?: 'Department Head';
        $createdAt = $prDate . ' 09:00:00';
        $reviewedAt = date('Y-m-d H:i:s', strtotime($prDate . ' +1 day 10:00:00'));

        $insPr->execute([
            $prNo,
            (int) $office['id'],
            'Consumable',
            1,
            null,
            $prDate,
            (string) ($pr['purpose'] ?? 'For office use'),
            $reqName,
            $reqTitle,
            $prDate,
            'BELEN B. RAGA',
            'Municipal Mayor',
            $prDate,
            isset($pr['monthly_ceiling']) ? ('Monthly ceiling ₱' . number_format((float) $pr['monthly_ceiling'], 0)) : null,
            'completed',
            $adminId > 0 ? $adminId : null,
            $createdAt,
            $createdAt,
            'GSO Admin',
            $reviewedAt,
        ]);
        $prId = (int) $conn->lastInsertId();
        $prIdByNumber[$prNo] = $prId;

        $ln = 1;
        foreach ($pr['lines'] as $line) {
            $insLine->execute([
                $prId,
                $ln++,
                (float) $line['qty'],
                (string) ($line['unit'] ?: 'pc'),
                (string) $line['name'],
                (float) $line['unit_cost'],
                (float) $line['line_total'],
                'Consumable',
                'Consumable',
            ]);
            $lineCount++;
        }
        $imported++;
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    throw $e;
}

echo "Imported PRs: $imported\n";
echo "Imported lines: $lineCount\n";
echo "Failed: $fail\n";
if ($errors) {
    echo "Sample errors:\n";
    foreach ($errors as $e) {
        echo " - $e\n";
    }
}

// Persist budgets into a small PHP cache file consumed by forecast helper if present.
$budgetMap = $payload['budgets_by_office_code'] ?? [];
if (is_array($budgetMap) && $budgetMap) {
    $budgetFile = dirname(__DIR__) . '/storage/imports/ml_dept_budgets.php';
    $export = var_export($budgetMap, true);
    file_put_contents(
        $budgetFile,
        "<?php\n/** Auto-generated from ML dataset Base Max Monthly Budget */\nreturn $export;\n"
    );
    echo "Budget map written: storage/imports/ml_dept_budgets.php\n";
}

if (!$skipCanvass) {
    echo "\n=== 3) Backfill 3-supplier canvass + PO + AIR ===\n";
    require_once dirname(__DIR__) . '/scripts/lib_consumable_canvass_backfill.php';
    $cStats = consumableBackfillCanvassForAll($conn, $suppliers);
    echo "Canvass OK: {$cStats['ok']} fail={$cStats['fail']}\n";
}

if (!$skipIssue) {
    echo "\n=== 4) Restock + release into Issuance ===\n";
    $prs = $conn->query(
        "SELECT p.id, p.pr_number, p.pr_date, o.name AS office_name
         FROM proc_pr p
         INNER JOIN offices o ON o.id = p.office_id
         WHERE p.is_consumable = 1 AND p.status = 'completed'
           AND NOT EXISTS (
               SELECT 1 FROM issuance_records i
               WHERE i.proc_pr_id = p.id AND COALESCE(i.is_consumable,0)=1
           )
         ORDER BY p.pr_date ASC, p.id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $ok = 0;
    $failI = 0;
    $lines = 0;
    foreach ($prs as $i => $pr) {
        $prId = (int) $pr['id'];
        $issueDate = substr((string) $pr['pr_date'], 0, 10);
        try {
            $conn->beginTransaction();
            consumableRestockFromCompletedPr($conn, $prId);
            $result = consumableIssueFromPr(
                $conn,
                $prId,
                'Custodian — ' . $pr['office_name'],
                'Department Staff',
                $issueDate,
                'Released consumables for ' . $pr['office_name'],
                ''
            );
            $conn->commit();
            $ok++;
            $lines += (int) ($result['attached'] ?? 0);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $failI++;
            if ($failI <= 10) {
                echo '  issue fail ' . $pr['pr_number'] . ': ' . $e->getMessage() . "\n";
            }
        }
        if (($i + 1) % 100 === 0) {
            echo '  progress ' . ($i + 1) . '/' . count($prs) . "\n";
        }
    }
    echo "Issued OK: $ok fail=$failI lines=$lines\n";
}

echo "\n=== DONE ===\n";
echo 'Consumable PRs: ' . $conn->query('SELECT COUNT(*) FROM proc_pr WHERE is_consumable=1')->fetchColumn() . "\n";
echo 'PR lines: ' . $conn->query(
    'SELECT COUNT(*) FROM proc_pr_line l INNER JOIN proc_pr p ON p.id=l.pr_id WHERE p.is_consumable=1'
)->fetchColumn() . "\n";
echo 'Quotations: ' . $conn->query(
    'SELECT COUNT(*) FROM proc_quotation q INNER JOIN proc_pr p ON p.id=q.pr_id WHERE p.is_consumable=1'
)->fetchColumn() . "\n";
echo 'Issuances: ' . $conn->query(
    'SELECT COUNT(*) FROM issuance_records WHERE COALESCE(is_consumable,0)=1'
)->fetchColumn() . "\n";

$sample = $conn->query(
    "SELECT p.pr_number, o.name office, p.pr_date,
            (SELECT COUNT(*) FROM proc_pr_line l WHERE l.pr_id=p.id) lines,
            (SELECT GROUP_CONCAT(l.description ORDER BY l.line_no SEPARATOR ', ')
             FROM proc_pr_line l WHERE l.pr_id=p.id) items
     FROM proc_pr p
     INNER JOIN offices o ON o.id=p.office_id
     WHERE p.is_consumable=1
     ORDER BY p.id ASC LIMIT 3"
);
echo "Sample PRs:\n";
foreach ($sample as $r) {
    echo ' - ' . $r['pr_number'] . ' | ' . $r['office'] . ' | ' . $r['pr_date']
        . ' | ' . $r['lines'] . ' lines | ' . mb_substr((string) $r['items'], 0, 90) . "\n";
}
