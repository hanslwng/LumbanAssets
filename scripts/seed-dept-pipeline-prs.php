<?php
/**
 * Seed realistic in-progress Purchase Requests per department:
 * draft, submitted (Waiting), needs_revision, quoting.
 *
 * Does not touch completed / PO-issued forecast history.
 * Tagged so it can be re-run safely.
 *
 * Marker: [PIPELINE_DEMO]
 * PR numbers: PIPE-2026-######
 *
 * CLI:
 *   C:\xampp\php\php.exe scripts\seed-dept-pipeline-prs.php
 * Remove:
 *   C:\xampp\php\php.exe scripts\seed-dept-pipeline-prs.php --remove
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/bootstrap.php';
require_once $projectRoot . '/includes/consumable_schema.php';

const PIPELINE_DEMO_TAG = '[PIPELINE_DEMO]';
const PIPELINE_PR_PREFIX = 'PIPE-2026-';

$isCli = (PHP_SAPI === 'cli');
$remove = $isCli && in_array('--remove', $argv ?? [], true);

if (!$isCli) {
    header('Content-Type: text/plain; charset=UTF-8');
}

function pipelineDemoRemove(PDO $conn): array
{
    $conn->exec('SET FOREIGN_KEY_CHECKS=0');
    $deleted = ['quotes' => 0, 'lines' => 0, 'prs' => 0];
    try {
        $deleted['quotes'] = (int) $conn->exec(
            "DELETE q FROM proc_quotation q
             INNER JOIN proc_pr p ON p.id = q.pr_id
             WHERE p.pr_number LIKE '" . PIPELINE_PR_PREFIX . "%'
                OR p.purpose LIKE '%" . PIPELINE_DEMO_TAG . "%'"
        );
    } catch (Throwable $e) {
        // optional
    }
    $deleted['lines'] = (int) $conn->exec(
        "DELETE l FROM proc_pr_line l
         INNER JOIN proc_pr p ON p.id = l.pr_id
         WHERE p.pr_number LIKE '" . PIPELINE_PR_PREFIX . "%'
            OR p.purpose LIKE '%" . PIPELINE_DEMO_TAG . "%'"
    );
    $deleted['prs'] = (int) $conn->exec(
        "DELETE FROM proc_pr
         WHERE pr_number LIKE '" . PIPELINE_PR_PREFIX . "%'
            OR purpose LIKE '%" . PIPELINE_DEMO_TAG . "%'"
    );
    $conn->exec('SET FOREIGN_KEY_CHECKS=1');

    return $deleted;
}

function pipelineDemoEnsureStatusEnum(PDO $conn): void
{
    try {
        $col = $conn->query("SHOW COLUMNS FROM proc_pr LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        $type = strtolower((string) ($col['Type'] ?? ''));
        if ($type !== '' && strpos($type, 'needs_revision') === false) {
            $conn->exec(
                "ALTER TABLE proc_pr MODIFY COLUMN status ENUM('draft','needs_revision','submitted','quoting','po_issued','completed') NOT NULL DEFAULT 'draft'"
            );
        }
    } catch (Throwable $e) {
        // best-effort
    }
}

/**
 * @return list<array{0:string,1:string,2:float,3:float}>
 */
function pipelineItemsForCode(string $code): array
{
    $c = strtoupper($code);
    $office = [
        ['A4 Bond Paper 80gsm', 'ream', 4, 265],
        ['Ballpen black 0.5mm', 'box', 3, 185],
        ['Correction tape', 'pc', 12, 38],
        ['Long folder', 'pc', 40, 16],
        ['Staple wire no. 35', 'box', 4, 42],
    ];
    $print = [
        ['A4 Bond Paper 80gsm', 'ream', 6, 265],
        ['Printer ink black', 'cartridge', 2, 680],
        ['Printer ink colored', 'cartridge', 1, 920],
        ['Toner cartridge', 'pc', 1, 2450],
    ];
    $clinic = [
        ['Ethyl alcohol 70%', 'gal', 4, 280],
        ['Disposable gloves', 'box', 6, 195],
        ['Cotton balls', 'pack', 8, 45],
        ['Face mask 3-ply', 'box', 5, 160],
        ['Hydrogen peroxide', 'btl', 6, 55],
    ];
    $clean = [
        ['Zonrox original', 'gal', 3, 255],
        ['Liquid hand soap', 'btl', 8, 95],
        ['Garbage bags large', 'pack', 6, 145],
        ['Toilet tissue', 'pack', 10, 85],
        ['Dishwashing liquid', 'btl', 6, 88],
    ];
    $field = [
        ['Alkaline battery AA', 'pack', 8, 120],
        ['Whiteboard marker', 'box', 2, 240],
        ['Logbook 300 pages', 'pc', 6, 95],
        ['Extension cord 5m', 'pc', 2, 450],
    ];

    if (in_array($c, ['HLTH', 'RHU'], true)) {
        return array_merge($clinic, array_slice($office, 0, 2));
    }
    if (in_array($c, ['GSO', 'ADMIN'], true)) {
        return array_merge($print, $office);
    }
    if (in_array($c, ['MDRRMO', 'ENRO', 'SWM', 'ENGR', 'LWS', 'AGRI', 'DA'], true)) {
        return array_merge($field, $clean);
    }
    if (in_array($c, ['SWDO', 'MSWDO', 'MARKET', 'SENIOR'], true)) {
        return array_merge($clean, array_slice($office, 0, 3));
    }

    return $office;
}

function pipelinePickLines(array $catalog, int $n, int $seed): array
{
    $n = max(2, min($n, count($catalog)));
    $out = [];
    $used = [];
    $i = 0;
    while (count($out) < $n && $i < 20) {
        $idx = abs($seed + $i * 17) % count($catalog);
        $i++;
        if (isset($used[$idx])) {
            continue;
        }
        $used[$idx] = true;
        $row = $catalog[$idx];
        $qty = (float) $row[2] + (abs($seed + $idx) % 3);
        $cost = (float) $row[3];
        $out[] = [
            'description' => $row[0],
            'unit' => $row[1],
            'qty' => $qty,
            'cost' => $cost,
            'total' => round($qty * $cost, 2),
        ];
    }

    return $out;
}

try {
    $conn = getDBConnection();
    consumableEnsureSchema($conn);
    pipelineDemoEnsureStatusEnum($conn);

    if ($remove) {
        $d = pipelineDemoRemove($conn);
        $msg = 'Removed pipeline demo PRs: ' . $d['prs'] . ' (lines ' . $d['lines'] . ').';
        echo $msg . ($isCli ? PHP_EOL : '');
        exit(0);
    }

    $removed = pipelineDemoRemove($conn);

    $offices = $conn->query(
        'SELECT id, name, code, head_name, head_position FROM offices WHERE COALESCE(is_active, 1) = 1 ORDER BY name'
    )->fetchAll(PDO::FETCH_ASSOC);
    if (!$offices) {
        throw new RuntimeException('No active offices found.');
    }

    $users = $conn->query(
        'SELECT id, office_id, role FROM users WHERE COALESCE(is_active, 1) = 1 ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $userByOffice = [];
    $adminId = 0;
    foreach ($users as $u) {
        $oid = (int) ($u['office_id'] ?? 0);
        $role = strtolower((string) ($u['role'] ?? ''));
        if ($adminId === 0 && ($role === 'admin' || $role === 'administrator')) {
            $adminId = (int) $u['id'];
        }
        if ($oid > 0 && !isset($userByOffice[$oid])) {
            $userByOffice[$oid] = (int) $u['id'];
        }
    }

    $suppliers = [
        ['name' => 'Lumban Commercial Center', 'addr' => 'National Highway, Lumban, Laguna'],
        ['name' => 'Laguna Office Depot', 'addr' => 'Pagsanjan-Lumban Rd, Pagsanjan, Laguna'],
        ['name' => 'Calamba Paper & Supplies', 'addr' => 'Calamba City, Laguna'],
    ];

    $feedbacks = [
        'Please specify brand and yield for printer ink (OEM vs compatible) and restudy the quantity against last month’s issuance.',
        'Unit cost of alcohol is high versus the last awarded PO. Indicate gallon vs bottle packaging and attach a clearer purpose for RHU / office use.',
        'Purpose is too generic. State the activity (3rd quarter replenishment / program) and complete the SAI number before resubmitting for canvass.',
        'Split mixed janitorial and office items into separate requests, or group them under one article with complete descriptions per row.',
        'Estimated amounts look padded. Align quantities with actual on-hand and the next 30-day need, then resubmit for canvass approval.',
    ];

    $insPr = $conn->prepare(
        'INSERT INTO proc_pr (
            pr_number, office_id, pr_section, is_consumable, sai_number, pr_date, purpose,
            requested_by, requested_by_title, requested_signed_date,
            approved_by, approved_by_title, approved_signed_date,
            budget_received_note, status, canvass_feedback, canvass_reviewed_by, canvass_reviewed_at,
            created_by_user_id, created_at, updated_at
         ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insLine = $conn->prepare(
        'INSERT INTO proc_pr_line (pr_id, line_no, quantity, unit, description, est_unit_cost, est_line_total, article, item_group)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $insQ = $conn->prepare(
        'INSERT INTO proc_quotation (pr_id, slot, supplier_name, supplier_address, quotation_ref, quotation_date, total_amount)
         VALUES (?,?,?,?,?,?,?)'
    );
    $insQl = $conn->prepare(
        'INSERT INTO proc_quotation_line (quotation_id, pr_line_id, unit_price, line_total) VALUES (?,?,?,?)'
    );

    $seq = 1;
    $counts = ['draft' => 0, 'submitted' => 0, 'needs_revision' => 0, 'quoting' => 0];
    $lineCount = 0;
    $quoteCount = 0;
    $today = new DateTimeImmutable('2026-09-15');

    $conn->beginTransaction();

    foreach ($offices as $idx => $off) {
        $oid = (int) $off['id'];
        $code = strtoupper(trim((string) ($off['code'] ?? '')));
        $name = (string) $off['name'];
        $reqName = trim((string) ($off['head_name'] ?? ''));
        $reqTitle = trim((string) ($off['head_position'] ?? ''));
        if ($reqName === '') {
            $reqName = 'Office Head';
        }
        if ($reqTitle === '') {
            $reqTitle = 'Department Head';
        }
        $creator = $userByOffice[$oid] ?? ($adminId ?: null);
        $catalog = pipelineItemsForCode($code);
        $large = in_array($code, ['GSO', 'HLTH', 'RHU', 'MAYOR', 'MDRRMO', 'SWDO', 'MSWDO', 'ENGR'], true);

        $jobs = [
            ['draft', 1],
            ['submitted', 1],
            ['quoting', $large ? 2 : 1],
        ];
        if ($idx % 2 === 0 || $large) {
            $jobs[] = ['needs_revision', 1];
        }
        if ($large) {
            $jobs[] = ['draft', 1];
            $jobs[] = ['submitted', 1];
        }

        $jobN = 0;
        foreach ($jobs as $job) {
            [$status, $copies] = $job;
            for ($c = 0; $c < $copies; $c++) {
                $jobN++;
                $seed = $oid * 97 + $jobN * 13 + $c;
                $daysAgo = 2 + (($seed + $idx) % 12);
                $prDate = $today->modify('-' . $daysAgo . ' days')->format('Y-m-d');
                $created = $prDate . ' 09:' . str_pad((string) (10 + ($seed % 40)), 2, '0', STR_PAD_LEFT) . ':00';
                $nLines = $large ? (3 + ($seed % 2)) : 2;
                $lines = pipelinePickLines($catalog, $nLines, $seed);
                if (!$lines) {
                    continue;
                }

                $prNo = sprintf('%s%04d', PIPELINE_PR_PREFIX, $seq++);
                $isCons = 1;
                $section = 'Consumable';
                $purposeCore = match ($status) {
                    'draft' => 'Replenishment of common office consumables for daily operations of ' . $name . ' for the remainder of September 2026.',
                    'submitted' => 'Request for consumable supplies needed by ' . $name . ' to support ongoing frontline and administrative work this quarter.',
                    'needs_revision' => 'Canvass submission for consumable supplies of ' . $name . ' returned by GSO for correction before resubmit.',
                    default => 'Approved for canvass: consumable supplies of ' . $name . ' for September 2026 operations.',
                };
                $purpose = $purposeCore . ' ' . PIPELINE_DEMO_TAG;
                $sai = $status === 'draft' ? '' : sprintf('SAI-2026-%s-%03d', $code !== '' ? $code : 'OFF', ($oid + $jobN) % 900);

                $fb = null;
                $reviewedBy = null;
                $reviewedAt = null;
                if ($status === 'needs_revision') {
                    $fb = $feedbacks[$seed % count($feedbacks)];
                    $reviewedBy = 'Property Custodian / Admin';
                    $reviewedAt = $today->modify('-' . max(1, $daysAgo - 1) . ' days')->format('Y-m-d 14:20:00');
                } elseif ($status === 'quoting') {
                    $reviewedBy = 'Property Custodian / Admin';
                    $reviewedAt = $today->modify('-' . max(1, $daysAgo - 1) . ' days')->format('Y-m-d 11:05:00');
                }

                $insPr->execute([
                    $prNo,
                    $oid,
                    $section,
                    $isCons,
                    $sai !== '' ? $sai : null,
                    $prDate,
                    $purpose,
                    $reqName,
                    $reqTitle,
                    $prDate,
                    'BELEN B. RAGA',
                    'Municipal Mayor',
                    $status === 'draft' ? null : $prDate,
                    $status === 'draft' ? null : 'Charged to MOOE – office supplies, FY 2026.',
                    $status,
                    $fb,
                    $reviewedBy,
                    $reviewedAt,
                    $creator,
                    $created,
                    $created,
                ]);
                $prId = (int) $conn->lastInsertId();
                $counts[$status] = ($counts[$status] ?? 0) + 1;

                $prLineIds = [];
                $estTotal = 0.0;
                foreach ($lines as $li => $ln) {
                    $insLine->execute([
                        $prId,
                        $li + 1,
                        $ln['qty'],
                        $ln['unit'],
                        $ln['description'],
                        $ln['cost'],
                        $ln['total'],
                        'Consumable',
                        'Office Supplies',
                    ]);
                    $prLineIds[] = [
                        'id' => (int) $conn->lastInsertId(),
                        'qty' => $ln['qty'],
                        'cost' => $ln['cost'],
                    ];
                    $estTotal += $ln['total'];
                    $lineCount++;
                }

                if ($status === 'quoting') {
                    $nQuotes = ($large && $c === 0) ? 3 : (1 + ($seed % 2));
                    $nQuotes = max(1, min(3, $nQuotes));
                    $qDate = $today->modify('-' . max(0, $daysAgo - 2) . ' days')->format('Y-m-d');
                    for ($slot = 1; $slot <= $nQuotes; $slot++) {
                        $sup = $suppliers[($slot - 1) % count($suppliers)];
                        $factor = 0.92 + ($slot * 0.04) + (($seed % 5) * 0.005);
                        $qTotal = 0.0;
                        $linePrices = [];
                        foreach ($prLineIds as $pl) {
                            $up = round($pl['cost'] * $factor, 2);
                            $lt = round($pl['qty'] * $up, 2);
                            $linePrices[] = ['id' => $pl['id'], 'up' => $up, 'lt' => $lt];
                            $qTotal += $lt;
                        }
                        $insQ->execute([
                            $prId,
                            $slot,
                            $sup['name'],
                            $sup['addr'],
                            sprintf('RFQ-%s-%d', substr($prNo, -4), $slot),
                            $qDate,
                            round($qTotal, 2),
                        ]);
                        $qId = (int) $conn->lastInsertId();
                        foreach ($linePrices as $lp) {
                            $insQl->execute([$qId, $lp['id'], $lp['up'], $lp['lt']]);
                        }
                        $quoteCount++;
                    }
                }
            }
        }
    }

    $conn->commit();

    $msg = 'Pipeline demo seeded for ' . count($offices) . ' offices. '
        . 'Drafts ' . $counts['draft']
        . ', Waiting ' . $counts['submitted']
        . ', Need revision ' . $counts['needs_revision']
        . ', Quoting ' . $counts['quoting']
        . ' (lines ' . $lineCount . ', quotations ' . $quoteCount . '). '
        . 'Replaced previous tagged rows (' . $removed['prs'] . '). '
        . 'Hard-refresh staff / procurement. Remove: php scripts/seed-dept-pipeline-prs.php --remove';
    echo $msg . ($isCli ? PHP_EOL : '');
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $err = 'Error: ' . $e->getMessage();
    if ($isCli) {
        fwrite(STDERR, $err . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo $err;
}
