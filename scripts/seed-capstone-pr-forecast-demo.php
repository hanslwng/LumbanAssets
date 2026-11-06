<?php
/**
 * Insert simulated consumable Purchase Requests for capstone PR-spending forecast.
 *
 * Tagged: SIMULATED DEMO DATA — FOR CAPSTONE TESTING
 * Dates: 2026-01-01 through 2026-09-14 only.
 * Does not modify existing PRs, users, inventory, or offices (adds Organic Trading Post if missing).
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts\seed-capstone-pr-forecast-demo.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';
require_once dirname(__DIR__) . '/includes/consumable_pr_forecast.php';
require_once __DIR__ . '/delete-capstone-pr-forecast-demo.php';

const CAPSTONE_PR_PREFIX = 'DEMO-PR-2026-';

$CATALOG = [
    'office' => [
        ['A4 Bond Paper', 'ream', 250],
        ['Long Bond Paper', 'ream', 280],
        ['Short Bond Paper', 'ream', 270],
        ['Ballpen Black', 'box', 180],
        ['Ballpen Blue', 'box', 180],
        ['Pencil', 'box', 150],
        ['Permanent Marker', 'box', 220],
        ['Whiteboard Marker', 'box', 250],
        ['Correction Tape', 'pc', 35],
        ['Long Folder', 'pc', 15],
        ['Short Folder', 'pc', 12],
        ['Plastic Envelope', 'pc', 10],
        ['Mailing Envelope', 'pack', 120],
        ['Paper Fastener', 'box', 45],
        ['Staple Wire', 'box', 35],
        ['Binder Clips', 'box', 80],
        ['Rubber Bands', 'box', 60],
        ['Glue', 'btl', 35],
    ],
    'print' => [
        ['A4 Bond Paper', 'ream', 250],
        ['Long Bond Paper', 'ream', 280],
        ['Printer Ink Black', 'cartridge', 650],
        ['Printer Ink Colored', 'cartridge', 900],
        ['Toner Cartridge', 'pc', 2500],
        ['Staple Wire', 'box', 35],
        ['Correction Tape', 'pc', 35],
        ['Long Folder', 'pc', 15],
    ],
    'janitor' => [
        ['Zonrox, Original', 'gal', 250],
        ['Zonrox, Violet', 'gal', 400],
        ['Liquid Sosa', 'L', 350],
        ['Lysol', 'btl', 800],
        ['Floor Cleaner', 'btl', 150],
        ['Toilet Bowl Cleaner', 'btl', 110],
        ['Bathroom/Tile Cleaner', 'btl', 120],
        ['Liquid Hand Soap', 'btl', 100],
        ['Dishwashing Liquid', 'btl', 90],
        ['Laundry Detergent', 'kg', 120],
        ['Glass Cleaner', 'btl', 120],
        ['Disinfectant', 'btl', 180],
        ['Air Freshener', 'can', 100],
        ['Toilet Tissue', 'roll', 25],
        ['Paper Towels', 'roll', 60],
        ['Garbage Bags', 'pack', 150],
        ['Cleaning Cloth', 'pack', 100],
        ['Scrub Sponge', 'pack', 70],
        ['Scouring Pad', 'pack', 60],
        ['Rubber Gloves', 'pair', 60],
        ['Toilet Deodorizer', 'pc', 50],
        ['Albatros', 'pc', 110],
        ['Plunger', 'pc', 110],
    ],
];

$PURPOSES = [
    'For office use',
    'For daily office operations',
    'For printing and documentation',
    'For cleaning and sanitation',
    'For maintenance of office cleanliness',
    'For administrative activities',
    'For official municipal activities',
    'For office supplies replenishment',
    'For documentation requirements',
    'For public service operations',
];

function capstonePrRng(int $seed): int
{
    $x = $seed & 0x7fffffff;
    $x = ($x * 1103515245 + 12345) & 0x7fffffff;

    return $x;
}

function capstonePrPick(array $pool, int $seed): mixed
{
    if (!$pool) {
        return null;
    }
    $i = capstonePrRng($seed) % count($pool);

    return $pool[$i];
}

/**
 * @return list<array{name:string,unit:string,cost:float,qty:int,total:float}>
 */
function capstonePrBuildLines(array $pool, float $target, int $nItems, int $seed): array
{
    $nItems = max(1, min($nItems, count($pool)));
    $picked = [];
    $used = [];
    $s = $seed;
    $guard = 0;
    while (count($picked) < $nItems && $guard < 80) {
        $guard++;
        $s = capstonePrRng($s + $guard * 17);
        $idx = $s % count($pool);
        if (isset($used[$idx])) {
            continue;
        }
        $used[$idx] = true;
        $picked[] = $pool[$idx];
    }
    if (!$picked) {
        $picked[] = $pool[0];
    }

    $lines = [];
    $remaining = max(50.0, $target);
    $left = count($picked);
    foreach ($picked as $i => $item) {
        $cost = (float) $item[2];
        $left--;
        if ($cost <= 0) {
            continue;
        }
        if ($left === 0) {
            $qty = max(1, (int) round($remaining / $cost));
        } else {
            $share = $remaining / (float) ($left + 1);
            $jitter = 0.55 + ((capstonePrRng($seed + $i * 91) % 90) / 100.0);
            $qty = max(1, (int) round(($share * $jitter) / $cost));
            $maxQty = max(1, (int) floor($remaining / $cost));
            $qty = min($qty, $maxQty);
        }
        $total = round($qty * $cost, 2);
        $remaining = max(0.0, $remaining - $total);
        $lines[] = [
            'name' => (string) $item[0],
            'unit' => (string) $item[1],
            'cost' => $cost,
            'qty' => $qty,
            'total' => $total,
        ];
    }

    return $lines;
}

$conn = getDBConnection();
consumableEnsureSchema($conn);

$otpExists = (int) $conn->query(
    "SELECT COUNT(*) FROM offices WHERE code = 'OTP' OR name LIKE '%Trading Post%'"
)->fetchColumn();
if ($otpExists === 0) {
    $conn->prepare(
        'INSERT INTO offices (name, code, head_name, head_position, is_active)
         VALUES (?,?,?,?,1)'
    )->execute([
        'Organic Trading Post',
        'OTP',
        'CATHERINE H. RANOCO',
        'Organic Trading Post Head',
    ]);
    echo "Added office Organic Trading Post (OTP).\n";
}

$offices = $conn->query(
    'SELECT id, name, code, head_name, head_position FROM offices WHERE COALESCE(is_active, 1) = 1 ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);
if (count($offices) < 1) {
    fwrite(STDERR, "No active offices found.\n");
    exit(1);
}

$adminId = (int) ($conn->query(
    "SELECT id FROM users WHERE is_active = 1 AND (role = 'admin' OR username = 'admin') ORDER BY id LIMIT 1"
)->fetchColumn() ?: 0);

$existingReal = (int) $conn->query(
    "SELECT COUNT(*) FROM proc_pr WHERE pr_number = 'PR-2026-000023'"
)->fetchColumn();

capstonePrForecastDemoDelete($conn, true);

$uniqueItems = [];
foreach ($CATALOG as $kit) {
    foreach ($kit as $row) {
        $uniqueItems[$row[0]] = $row;
    }
}
foreach ($uniqueItems as $row) {
    $item = consumableEnsureItem($conn, 'Consumable', $row[0], $row[1]);
    $conn->prepare('UPDATE consumable_items SET aliases = ? WHERE id = ? AND (aliases IS NULL OR aliases = \'\')')
        ->execute([LGUE_CAPSTONE_PR_DEMO_TAG, (int) $item['id']]);
}

$janitorCodes = ['HLTH', 'RHU', 'GSO', 'MDRRMO', 'MARKET', 'ENRO', 'SWM', 'LWS'];
$mixedCodes = ['MAYOR', 'OM', 'ADMIN', 'SWDO', 'MSWDO', 'AGRI', 'DA', 'OTP'];
$smallCodes = ['DILG', 'PDAO', 'SENIOR', 'COA'];

$kitFor = static function (string $code, int $kind) use ($CATALOG, $janitorCodes, $mixedCodes): array {
    if ($kind === 0) {
        return $CATALOG['office'];
    }
    if ($kind === 1) {
        return $CATALOG['print'];
    }
    if (in_array($code, $janitorCodes, true) || in_array($code, $mixedCodes, true)) {
        return $CATALOG['janitor'];
    }

    return $kind === 2 ? $CATALOG['janitor'] : $CATALOG['office'];
};

$dayPools = [
    1 => [3, 8, 14, 19, 25, 29],
    2 => [4, 9, 15, 21, 27],
    3 => [3, 7, 13, 18, 24, 28],
    4 => [2, 8, 13, 16, 22, 28],
    5 => [5, 9, 14, 20, 26, 29],
    6 => [3, 10, 16, 23, 27],
    7 => [2, 8, 15, 21, 28, 31],
    8 => [4, 11, 18, 24, 28],
    9 => [2, 5, 9, 12, 14],
];

$insPr = $conn->prepare(
    'INSERT INTO proc_pr (
        pr_number, office_id, pr_section, is_consumable, sai_number, pr_date, purpose,
        requested_by, requested_by_title, requested_signed_date,
        approved_by, approved_by_title, approved_signed_date,
        budget_received_note, status, created_by_user_id, created_at, updated_at
     ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$insLine = $conn->prepare(
    'INSERT INTO proc_pr_line (pr_id,line_no,quantity,unit,description,est_unit_cost,est_line_total,article,item_group)
     VALUES (?,?,?,?,?,?,?,?,?)'
);

$prSeq = 1;
$prCount = 0;
$lineCount = 0;
$dateMin = null;
$dateMax = null;
$officesUsed = [];

$conn->beginTransaction();
try {
    foreach ($offices as $off) {
        $oid = (int) $off['id'];
        $code = strtoupper(trim((string) $off['code']));
        $budget = consumableDeptMonthlyBudget($code, (string) $off['name']);
        if ($budget <= 0) {
            continue;
        }
        $reqName = trim((string) ($off['head_name'] ?? ''));
        $reqTitle = trim((string) ($off['head_position'] ?? ''));
        if ($reqName === '') {
            $reqName = 'RHONALIZA A. ALVIAR';
            $reqTitle = 'GSO - Designate';
        }
        if ($reqTitle === '') {
            $reqTitle = 'Office Head';
        }

        $high = $budget >= 80000;
        $low = $budget <= 15000;

        for ($month = 1; $month <= 9; $month++) {
            $seedBase = $oid * 10007 + $month * 9176 + 42;
            $nPrs = $high ? (3 + (capstonePrRng($seedBase) % 4)) : ($low ? (2 + (capstonePrRng($seedBase) % 2)) : (2 + (capstonePrRng($seedBase) % 4)));
            if ($month === 9) {
                $nPrs = $high ? (2 + (capstonePrRng($seedBase + 3) % 2)) : (1 + (capstonePrRng($seedBase + 5) % 2));
            } else {
                $wobble = capstonePrRng($seedBase + 777) % 5;
                if ($wobble === 0 && $nPrs > 2) {
                    $nPrs--;
                } elseif ($wobble >= 3) {
                    $nPrs++;
                }
            }
            $days = $dayPools[$month];
            $nPrs = min($nPrs, count($days));
            $pickedDays = $days;
            usort($pickedDays, static function ($a, $b) use ($seedBase) {
                return (capstonePrRng($seedBase + (int) $a * 13) <=> capstonePrRng($seedBase + (int) $b * 13));
            });
            $pickedDays = array_slice($pickedDays, 0, $nPrs);
            if ($code === 'MAYOR' && $month === 4 && !in_array(13, $pickedDays, true)) {
                $pickedDays[count($pickedDays) - 1] = 13;
            }
            sort($pickedDays);

            $wave = sin(($month + $oid) * 0.87);
            $jitter = ((capstonePrRng($seedBase + 9) % 41) - 20) / 100.0;
            $trend = ($month - 4.5) * 0.015;
            $factor = 0.93 + $jitter + ($wave * 0.07) + $trend;
            $factor = max(0.74, min(1.22, $factor));
            $monthTarget = $budget * $factor;
            if ($month === 9) {
                $monthTarget = $budget * (0.38 + ((capstonePrRng($seedBase + 11) % 15) / 100.0));
            }

            $weights = [];
            $wsum = 0.0;
            for ($p = 0; $p < $nPrs; $p++) {
                $w = 0.55 + ((capstonePrRng($seedBase + 30 + $p) % 90) / 100.0);
                $weights[] = $w;
                $wsum += $w;
            }

            $mayorSample = ($code === 'MAYOR' && $month === 4 && in_array(13, $pickedDays, true));

            foreach ($pickedDays as $pi => $day) {
                $prDate = sprintf('2026-%02d-%02d', $month, $day);
                $prTarget = $monthTarget * ($weights[$pi] / $wsum);
                $kind = capstonePrRng($seedBase + $pi * 7) % 3;
                $nItems = 1 + (capstonePrRng($seedBase + $pi * 19 + $day) % 6);
                if ($nItems === 1 && (capstonePrRng($seedBase + $pi + 4) % 4) !== 0) {
                    $nItems = 2 + (capstonePrRng($seedBase + $pi + 8) % 4);
                }

                if ($mayorSample && (int) $day === 13) {
                    $lines = [
                        ['name' => 'Liquid Sosa', 'unit' => 'L', 'cost' => 350.0, 'qty' => 1, 'total' => 350.0],
                        ['name' => 'Zonrox, Original', 'unit' => 'gal', 'cost' => 250.0, 'qty' => 1, 'total' => 250.0],
                        ['name' => 'Plunger', 'unit' => 'pc', 'cost' => 110.0, 'qty' => 1, 'total' => 110.0],
                        ['name' => 'Zonrox, Violet', 'unit' => 'gal', 'cost' => 400.0, 'qty' => 1, 'total' => 400.0],
                        ['name' => 'Lysol', 'unit' => 'btl', 'cost' => 800.0, 'qty' => 1, 'total' => 800.0],
                        ['name' => 'Albatros', 'unit' => 'pc', 'cost' => 110.0, 'qty' => 10, 'total' => 1100.0],
                    ];
                    $purposeLead = 'For cleaning and sanitation';
                } else {
                    $pool = $kitFor($code, $kind);
                    $lines = capstonePrBuildLines($pool, $prTarget, $nItems, $seedBase + $pi * 101 + $day);
                    $purposeLead = $PURPOSES[($kind + $pi + $month) % count($PURPOSES)];
                }

                $prNo = sprintf('%s%06d', CAPSTONE_PR_PREFIX, $prSeq++);
                $purpose = $purposeLead . '. ' . LGUE_CAPSTONE_PR_DEMO_TAG;
                $createdAt = $prDate . ' 08:30:00';

                $insPr->execute([
                    $prNo,
                    $oid,
                    'Consumable',
                    1,
                    null,
                    $prDate,
                    $purpose,
                    $reqName,
                    $reqTitle,
                    $prDate,
                    'BELEN B. RAGA',
                    'Municipal Mayor',
                    $prDate,
                    LGUE_CAPSTONE_PR_DEMO_TAG,
                    'completed',
                    $adminId > 0 ? $adminId : null,
                    $createdAt,
                    $createdAt,
                ]);
                $prId = (int) $conn->lastInsertId();
                $lineNo = 1;
                foreach ($lines as $ln) {
                    $qty = (float) $ln['qty'];
                    $cost = (float) $ln['cost'];
                    $total = round($qty * $cost, 2);
                    $insLine->execute([
                        $prId,
                        $lineNo++,
                        $qty,
                        $ln['unit'],
                        $ln['name'],
                        $cost,
                        $total,
                        'Consumable',
                        'Consumable',
                    ]);
                    $lineCount++;
                }
                $prCount++;
                $officesUsed[$oid] = true;
                $dateMin = $dateMin === null || $prDate < $dateMin ? $prDate : $dateMin;
                $dateMax = $dateMax === null || $prDate > $dateMax ? $prDate : $dateMax;
            }
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$realStill = (int) $conn->query(
    "SELECT COUNT(*) FROM proc_pr WHERE pr_number = 'PR-2026-000023'"
)->fetchColumn();

$badMath = (int) $conn->query(
    "SELECT COUNT(*) FROM proc_pr_line l
     INNER JOIN proc_pr p ON p.id = l.pr_id
     WHERE p.pr_number LIKE 'DEMO-PR-%'
       AND ABS(l.est_line_total - (l.quantity * l.est_unit_cost)) > 0.02"
)->fetchColumn();

$future = (int) $conn->query(
    "SELECT COUNT(*) FROM proc_pr WHERE pr_number LIKE 'DEMO-PR-%' AND pr_date > '2026-09-14'"
)->fetchColumn();

$sepLate = (int) $conn->query(
    "SELECT COUNT(*) FROM proc_pr WHERE pr_number LIKE 'DEMO-PR-%' AND pr_date BETWEEN '2026-09-15' AND '2026-09-30'"
)->fetchColumn();

echo "Inserted {$prCount} PRs and {$lineCount} lines.\n";
echo "Date range: {$dateMin} to {$dateMax}\n";
echo "Departments: " . count($officesUsed) . "\n";
echo "Catalog items: " . count($uniqueItems) . "\n";
echo "Line math mismatches: {$badMath}\n";
echo "Records after 2026-09-14: {$future}\n";
echo "Sep 15–30 records: {$sepLate}\n";
echo "Existing PR-2026-000023 still present: " . ($realStill ? 'yes' : 'NO') . " (was {$existingReal})\n";
echo "Done.\n";
