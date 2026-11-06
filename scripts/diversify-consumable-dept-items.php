<?php
/**
 * Diversify consumable PR line items so each department has a distinct catalog/top mix.
 * Updates proc_pr_line + matching issuance_consumable_lines.
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts/diversify-consumable-dept-items.php
 */

@set_time_limit(0);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';
require_once dirname(__DIR__) . '/includes/consumable_stock.php';

$conn = getDBConnection();
consumableEnsureSchema($conn);

/** @var array<string, list<array{0:string,1:string,2:float}>> name, unit, unit_cost */
$kits = [
    'HLTH' => [
        ['Alcohol 70%', 'bottle', 120], ['Face masks', 'box', 180], ['Disposable gloves', 'box', 220],
        ['Cotton balls', 'pack', 45], ['Betadine', 'bottle', 95], ['Gauze pads', 'box', 85],
        ['Medical tape', 'roll', 55], ['Disinfectant spray', 'bottle', 150], ['Hand soap', 'bottle', 65],
        ['Syringe disposal bags', 'pack', 90], ['BP logbook', 'pc', 95], ['Patient record forms', 'ream', 320],
        ['Thermometer probe covers', 'box', 140], ['Cotton buds', 'box', 40], ['Bond paper A4', 'ream', 280],
    ],
    'ENRO' => [
        ['Rubber gloves', 'pair', 55], ['Bleach', 'bottle', 85], ['Garbage bags, large', 'roll', 140],
        ['Broom', 'pc', 120], ['Dustpan', 'pc', 80], ['Detergent', 'kg', 95], ['Disinfectant', 'bottle', 150],
        ['Cleaning rag', 'pc', 25], ['Trash liners', 'roll', 110], ['Caution tape', 'roll', 75],
        ['Face masks', 'box', 180], ['Rake handle grip', 'pc', 60], ['Wheelbarrow liner', 'pc', 200],
        ['Street sweeping logbook', 'pc', 90], ['Permanent marker', 'pc', 35],
    ],
    'MDRRMO' => [
        ['Safety vests', 'pc', 150], ['Caution tape', 'roll', 75], ['Whistle', 'pc', 40],
        ['First-aid kit refill', 'set', 450], ['Emergency rope', 'roll', 180], ['Batteries AA', 'pack', 90],
        ['Batteries AAA', 'pack', 85], ['Raincoat disposable', 'pc', 120], ['Tarpaulin rope', 'roll', 160],
        ['Megaphone batteries', 'pack', 200], ['Incident logbook', 'pc', 95], ['Marker board pens', 'pc', 45],
        ['Glow sticks', 'pack', 130], ['ID lanyards', 'pc', 35], ['Bond paper A4', 'ream', 280],
    ],
    'GSO' => [
        ['Floor wax', 'can', 220], ['Mop head', 'pc', 90], ['Toilet bowl cleaner', 'bottle', 80],
        ['Air freshener', 'bottle', 95], ['Packing tape', 'roll', 45], ['Trash bags', 'roll', 130],
        ['Detergent', 'kg', 95], ['Hand soap', 'bottle', 65], ['Cleaning rag', 'pc', 25],
        ['Utility gloves', 'pair', 60], ['Extension cord', 'pc', 350], ['Carton boxes', 'pc', 70],
        ['Inventory logbook', 'pc', 95], ['Broom soft', 'pc', 110], ['Furniture polish', 'bottle', 95],
    ],
    'SWDO' => [
        ['Case folders', 'pc', 25], ['Client intake forms', 'ream', 300], ['Relief packing tape', 'roll', 45],
        ['ID jackets', 'pc', 40], ['Certificate paper', 'ream', 350], ['Manila paper', 'pc', 18],
        ['Long envelopes', 'pc', 8], ['Ballpen blue', 'pc', 12], ['Staples', 'box', 25],
        ['Logbook', 'pc', 85], ['Plastic bags medium', 'pack', 80], ['Bond paper A4', 'ream', 280],
        ['Folders', 'pc', 15], ['Correction tape', 'pc', 45], ['Notepads', 'pc', 35],
    ],
    'DEPED' => [
        ['Manila paper', 'pc', 18], ['Chalk', 'box', 55], ['Whiteboard marker', 'pc', 40],
        ['Construction paper', 'pack', 90], ['Class record notebook', 'pc', 70], ['Pencils', 'pc', 8],
        ['Bond paper A4', 'ream', 280], ['Ballpen blue', 'pc', 12], ['Glue', 'bottle', 35],
        ['Scissors', 'pc', 75], ['Paper clips', 'box', 20], ['Cartolina', 'pc', 15],
        ['Crayons', 'box', 65], ['Masking tape', 'roll', 40], ['Folders', 'pc', 15],
    ],
    'AGRI' => [
        ['Seedling bags', 'pack', 120], ['Garden twine', 'roll', 70], ['Fertilizer sample bags', 'pc', 40],
        ['Soil sample envelopes', 'pc', 15], ['Field logbook', 'pc', 90], ['Permanent marker', 'pc', 35],
        ['Measuring tape', 'pc', 150], ['Pruning shear oil', 'bottle', 95], ['Work gloves', 'pair', 80],
        ['Spray bottle', 'pc', 55], ['Bond paper A4', 'ream', 280], ['Folders', 'pc', 15],
        ['Notepads', 'pc', 35], ['Ballpen black', 'pc', 12], ['Masking tape', 'roll', 40],
    ],
    'ENGR' => [
        ['Blueprint paper', 'roll', 450], ['Technical pen refill', 'pc', 95], ['Scale ruler', 'pc', 180],
        ['Tracing paper', 'pad', 160], ['Site inspection forms', 'ream', 280], ['Hard hat sticker labels', 'pack', 60],
        ['Pencils', 'pc', 8], ['Correction tape', 'pc', 45], ['Bond paper A4', 'ream', 280],
        ['Legal-size paper', 'ream', 320], ['Folders', 'pc', 15], ['Highlighter', 'pc', 25],
        ['Masking tape', 'roll', 40], ['USB flash drive', 'pc', 450], ['Batteries AA', 'pack', 90],
    ],
    'LWS' => [
        ['Pipe thread seal tape', 'roll', 35], ['Chlorine test strips', 'pack', 180], ['Water meter logbook', 'pc', 95],
        ['Valve tag labels', 'pack', 70], ['Leak report forms', 'ream', 260], ['Utility knife blades', 'pack', 85],
        ['Face masks', 'box', 180], ['Rubber gloves', 'pair', 55], ['Permanent marker', 'pc', 35],
        ['Correction tape', 'pc', 45], ['Bond paper A4', 'ream', 280], ['Water sample bottles', 'pc', 40],
        ['Batteries AA', 'pack', 90], ['Cleaning rag', 'pc', 25], ['Manila paper', 'pc', 18],
    ],
    'MARKET' => [
        ['Stall receipt booklet', 'pc', 75], ['Weighing scale receipt roll', 'pc', 95], ['Market fee stickers', 'pack', 120],
        ['Garbage bags, large', 'roll', 140], ['Detergent', 'kg', 95], ['Broom market', 'pc', 120],
        ['Ballpen black', 'pc', 12], ['Carbon paper', 'box', 60], ['Printer toner', 'pc', 1850],
        ['Legal-size paper', 'ream', 320], ['Masking tape', 'roll', 40], ['Logbook', 'pc', 85],
        ['Face masks', 'box', 180], ['Disinfectant', 'bottle', 150], ['Notepads', 'pc', 35],
    ],
    'OTP' => [
        ['Sacks', 'pc', 35], ['Produce tags', 'pack', 70], ['Weighing receipt roll', 'pc', 90],
        ['Twine', 'roll', 55], ['Price cards', 'pack', 80], ['Plastic bags large', 'pack', 100],
        ['Permanent marker', 'pc', 35], ['Bond paper A4', 'ream', 280], ['Highlighter', 'pc', 25],
        ['Logbook', 'pc', 85], ['Ballpen blue', 'pc', 12], ['Folders', 'pc', 15],
        ['Masking tape', 'roll', 40], ['Calculator battery', 'pc', 45], ['Notepads', 'pc', 35],
    ],
    'PESO' => [
        ['Application forms', 'ream', 280], ['Job vacancy posters', 'pc', 25], ['ID lace', 'pc', 30],
        ['Employee referral pads', 'pc', 55], ['Job fair banners paper', 'pc', 40], ['Ballpen blue', 'pc', 12],
        ['Ballpen black', 'pc', 12], ['Paper clips', 'box', 20], ['Folders', 'pc', 15],
        ['Long envelopes', 'pc', 8], ['Staples', 'box', 25], ['Notepads', 'pc', 35],
        ['Bond paper A4', 'ream', 280], ['Correction tape', 'pc', 45], ['Logbook', 'pc', 85],
    ],
    'MCR' => [
        ['Civil registry forms', 'ream', 400], ['Security paper', 'ream', 550], ['Seal ink pad', 'pc', 120],
        ['Notarial sticker', 'pack', 80], ['Document ribbon', 'roll', 95], ['Long envelopes', 'pc', 8],
        ['Legal-size paper', 'ream', 320], ['Carbon paper', 'box', 60], ['Printer toner', 'pc', 1850],
        ['Logbook', 'pc', 85], ['Manila paper', 'pc', 18], ['Ballpen black', 'pc', 12],
        ['Folders', 'pc', 15], ['Correction tape', 'pc', 45], ['Bond paper A4', 'ream', 280],
    ],
    'ACCT' => [
        ['Voucher folders', 'pc', 30], ['Ledger sheets', 'ream', 380], ['Calculator paper roll', 'pc', 55],
        ['Journal logbook', 'pc', 95], ['Carbon paper', 'box', 60], ['Legal-size paper', 'ream', 320],
        ['Printer toner', 'pc', 1850], ['Staples', 'box', 25], ['Folders', 'pc', 15],
        ['Ballpen black', 'pc', 12], ['Highlighter', 'pc', 25], ['Bond paper A4', 'ream', 280],
        ['Paper clips', 'box', 20], ['Long envelopes', 'pc', 8], ['Correction tape', 'pc', 45],
    ],
    'BUDG' => [
        ['Budget worksheets', 'ream', 320], ['Appropriation index cards', 'pack', 140], ['Obligation pads', 'pc', 70],
        ['Allotment sticky flags', 'pack', 55], ['Printer toner', 'pc', 1850], ['Legal-size paper', 'ream', 320],
        ['Notepads', 'pc', 35], ['Logbook', 'pc', 85], ['Permanent marker', 'pc', 35],
        ['Folders', 'pc', 15], ['Highlighter', 'pc', 25], ['Bond paper A4', 'ream', 280],
        ['Staples', 'box', 25], ['Ballpen blue', 'pc', 12], ['Carbon paper', 'box', 60],
    ],
    'TRES' => [
        ['Official receipt booklet', 'pc', 95], ['Coin wrapper', 'pack', 70], ['Cash count sheet', 'ream', 260],
        ['Deposit slip pads', 'pc', 65], ['Carbon paper', 'box', 60], ['Printer toner', 'pc', 1850],
        ['Ballpen black', 'pc', 12], ['Masking tape', 'roll', 40], ['Notepads', 'pc', 35],
        ['Bond paper A4', 'ream', 280], ['Folders', 'pc', 15], ['Staples', 'box', 25],
        ['Long envelopes', 'pc', 8], ['Correction tape', 'pc', 45], ['Logbook', 'pc', 85],
    ],
    'ASSO' => [
        ['Tax declaration forms', 'ream', 420], ['Property index cards', 'pack', 150], ['Assessment stickers', 'pack', 110],
        ['Field appraisal pads', 'pc', 60], ['Printer toner', 'pc', 1850], ['Legal-size paper', 'ream', 320],
        ['Staples', 'box', 25], ['Ballpen black', 'pc', 12], ['Long envelopes', 'pc', 8],
        ['Folders', 'pc', 15], ['Bond paper A4', 'ream', 280], ['Correction tape', 'pc', 45],
        ['Highlighter', 'pc', 25], ['Logbook', 'pc', 85], ['Carbon paper', 'box', 60],
    ],
    'MAYOR' => [
        ['Invitation envelopes', 'pc', 12], ['Specialty card stock', 'pack', 220], ['Ribbon seal', 'pc', 45],
        ['Protocol logbook', 'pc', 95], ['Mayor letterhead paper', 'ream', 380], ['Printer toner', 'pc', 1850],
        ['Bond paper A4', 'ream', 280], ['Long envelopes', 'pc', 8], ['Ballpen blue', 'pc', 12],
        ['Folders', 'pc', 15], ['Pencils', 'pc', 8], ['Notepads', 'pc', 35],
        ['Correction tape', 'pc', 45], ['Highlighter', 'pc', 25], ['Staples', 'box', 25],
    ],
    'VMAY' => [
        ['Session agenda paper', 'ream', 300], ['Ordinance binder rings', 'box', 90], ['Committee report pads', 'pc', 75],
        ['Roll-call sheets', 'ream', 240], ['Printer toner', 'pc', 1850], ['Legal-size paper', 'ream', 320],
        ['Ballpen black', 'pc', 12], ['Bond paper A4', 'ream', 280], ['Pencils', 'pc', 8],
        ['Folders', 'pc', 15], ['Notepads', 'pc', 35], ['Long envelopes', 'pc', 8],
        ['Highlighter', 'pc', 25], ['Correction tape', 'pc', 45], ['Staples', 'box', 25],
    ],
    'ADMIN' => [
        ['Routing slip pads', 'pc', 55], ['Document tray labels', 'pack', 50], ['Courier pouch labels', 'pack', 80],
        ['Inter-office memo pads', 'pc', 60], ['Printer toner', 'pc', 1850], ['Permanent marker', 'pc', 35],
        ['Long envelopes', 'pc', 8], ['Ballpen black', 'pc', 12], ['Logbook', 'pc', 85],
        ['Bond paper A4', 'ream', 280], ['Folders', 'pc', 15], ['Staples', 'box', 25],
        ['Notepads', 'pc', 35], ['Correction tape', 'pc', 45], ['Paper clips', 'box', 20],
    ],
    'HRMO' => [
        ['201 file folders', 'pc', 35], ['Employee forms', 'ream', 300], ['ID printers ribbon', 'pc', 650],
        ['Leave application pads', 'pc', 55], ['Long envelopes', 'pc', 8], ['Staples', 'box', 25],
        ['Permanent marker', 'pc', 35], ['Carbon paper', 'box', 60], ['Ballpen blue', 'pc', 12],
        ['Bond paper A4', 'ream', 280], ['Notepads', 'pc', 35], ['Correction tape', 'pc', 45],
        ['Logbook', 'pc', 85], ['Paper clips', 'box', 20], ['Folders', 'pc', 15],
    ],
    'LICENSE' => [
        ['Business permit forms', 'ream', 380], ['Security sticker', 'pack', 120], ['Mayor permit jackets', 'pc', 40],
        ['Inspection checklist pads', 'pc', 55], ['License fee stickers', 'pack', 95], ['Correction tape', 'pc', 45],
        ['Notepads', 'pc', 35], ['Ballpen black', 'pc', 12], ['Staples', 'box', 25],
        ['Long envelopes', 'pc', 8], ['Bond paper A4', 'ream', 280], ['Folders', 'pc', 15],
        ['Logbook', 'pc', 85], ['Highlighter', 'pc', 25], ['Legal-size paper', 'ream', 320],
    ],
    'PLAN' => [
        ['Maps/printing paper', 'ream', 480], ['Technical pens', 'pc', 95], ['Tracing paper', 'pad', 160],
        ['Zoning application pads', 'pc', 70], ['Printer toner', 'pc', 1850], ['Legal-size paper', 'ream', 320],
        ['Bond paper A4', 'ream', 280], ['Notepads', 'pc', 35], ['Highlighter', 'pc', 25],
        ['Folders', 'pc', 15], ['Ballpen blue', 'pc', 12], ['Correction tape', 'pc', 45],
        ['Logbook', 'pc', 85], ['Staples', 'box', 25], ['Paper clips', 'box', 20],
    ],
    'PDAO' => [
        ['PWD ID jackets', 'pc', 45], ['Assistive device tags', 'pack', 70], ['PWD application forms', 'ream', 280],
        ['Accessibility checklist pads', 'pc', 55], ['Notepads', 'pc', 35], ['Carbon paper', 'box', 60],
        ['Paper clips', 'box', 20], ['Logbook', 'pc', 85], ['Legal-size paper', 'ream', 320],
        ['Bond paper A4', 'ream', 280], ['Ballpen blue', 'pc', 12], ['Folders', 'pc', 15],
        ['Long envelopes', 'pc', 8], ['Staples', 'box', 25], ['Correction tape', 'pc', 45],
    ],
    'SENIOR' => [
        ['OSCA ID jackets', 'pc', 45], ['Medicine index cards', 'pack', 90], ['Senior application forms', 'ream', 300],
        ['Birthday gift tags', 'pack', 65], ['Printer toner', 'pc', 1850], ['Legal-size paper', 'ream', 320],
        ['Paper clips', 'box', 20], ['Long envelopes', 'pc', 8], ['Folders', 'pc', 15],
        ['Bond paper A4', 'ream', 280], ['Ballpen black', 'pc', 12], ['Notepads', 'pc', 35],
        ['Logbook', 'pc', 85], ['Staples', 'box', 25], ['Correction tape', 'pc', 45],
    ],
    'COA' => [
        ['Audit working paper', 'ream', 360], ['Document binder clips', 'box', 55], ['Observation memo pads', 'pc', 70],
        ['Evidence envelope seals', 'pack', 95], ['Printer toner', 'pc', 1850], ['Long envelopes', 'pc', 8],
        ['Legal-size paper', 'ream', 320], ['Bond paper A4', 'ream', 280], ['Staples', 'box', 25],
        ['Folders', 'pc', 15], ['Highlighter', 'pc', 25], ['Ballpen black', 'pc', 12],
        ['Correction tape', 'pc', 45], ['Logbook', 'pc', 85], ['Paper clips', 'box', 20],
    ],
    'DILG' => [
        ['Barangay forms', 'ream', 300], ['Training attendance sheets', 'ream', 260], ['Barangay monitoring pads', 'pc', 60],
        ['Seal of good governance stickers', 'pack', 90], ['Carbon paper', 'box', 60], ['Notepads', 'pc', 35],
        ['Ballpen black', 'pc', 12], ['Long envelopes', 'pc', 8], ['Correction tape', 'pc', 45],
        ['Bond paper A4', 'ream', 280], ['Folders', 'pc', 15], ['Logbook', 'pc', 85],
        ['Staples', 'box', 25], ['Highlighter', 'pc', 25], ['Legal-size paper', 'ream', 320],
    ],
];

// Fallback generic but still slightly unique per unknown code
$fallback = [
    ['Bond paper A4', 'ream', 280], ['Ballpen blue', 'pc', 12], ['Folders', 'pc', 15],
    ['Notepads', 'pc', 35], ['Staples', 'box', 25], ['Correction tape', 'pc', 45],
    ['Logbook', 'pc', 85], ['Long envelopes', 'pc', 8], ['Highlighter', 'pc', 25],
    ['Paper clips', 'box', 20], ['Masking tape', 'roll', 40], ['Permanent marker', 'pc', 35],
];

$offices = $conn->query('SELECT id, code, name FROM offices ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
echo "Diversifying " . count($offices) . " offices (variable catalog sizes)...\n";

$updLine = $conn->prepare(
    'UPDATE proc_pr_line
     SET description = ?, unit = ?, est_unit_cost = ?, est_line_total = ?, article = ?, item_group = ?
     WHERE id = ?'
);
$updIss = $conn->prepare(
    'UPDATE issuance_consumable_lines
     SET item_name = ?, quantity = ?, unit = ?, unit_cost = ?, consumable_item_id = ?
     WHERE proc_pr_line_id = ?'
);

/** Shared extras so busy offices can exceed a fixed 15-SKU kit. */
$kitExtras = [
    ['Sticky notes', 'pad', 45], ['Binder clips', 'box', 55], ['Push pins', 'box', 30],
    ['Rubber bands', 'box', 25], ['Clear tape', 'roll', 35], ['File fasteners', 'box', 40],
    ['Index tabs', 'pack', 50], ['Expanding envelope', 'pc', 45], ['Stamp pad ink', 'bottle', 85],
    ['Whiteboard eraser', 'pc', 60], ['USB flash drive', 'pc', 450], ['AAA batteries', 'pack', 80],
];

$pickWeighted = static function (array $weights, int $seed): int {
    $sum = array_sum($weights);
    if ($sum <= 0) {
        return 0;
    }
    $r = ($seed % 10000) / 10000.0 * $sum;
    $acc = 0.0;
    foreach ($weights as $i => $w) {
        $acc += $w;
        if ($r <= $acc) {
            return (int) $i;
        }
    }

    return count($weights) - 1;
};

$conn->beginTransaction();
$updated = 0;
try {
    foreach ($offices as $off) {
        $code = strtoupper(trim((string) $off['code']));
        $kit = $kits[$code] ?? $fallback;
        $seed = (int) sprintf('%u', crc32($code . '|catalog-v3'));
        $extraN = 4 + ($seed % 7); // 4–10
        for ($e = 0; $e < $extraN; $e++) {
            $kit[] = $kitExtras[($seed + $e * 3) % count($kitExtras)];
        }
        $seen = [];
        $deduped = [];
        foreach ($kit as $rowKit) {
            $nk = strtolower($rowKit[0]);
            if (isset($seen[$nk])) {
                continue;
            }
            $seen[$nk] = true;
            $deduped[] = $rowKit;
        }
        $kit = $deduped;
        $kitN = count($kit);

        $lines = $conn->prepare(
            'SELECT l.id, l.quantity, l.est_unit_cost, l.est_line_total
             FROM proc_pr_line l
             INNER JOIN proc_pr p ON p.id = l.pr_id
             WHERE p.office_id = ? AND p.is_consumable = 1
             ORDER BY l.id ASC'
        );
        $lines->execute([(int) $off['id']]);
        $rows = $lines->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            echo "  WARN no lines: {$off['name']}\n";
            continue;
        }

        $lineN = count($rows);
        // Variable unique-item target — avoid every dept showing exactly 15.
        $useN = 8 + ($seed % 12); // 8–19
        if ($lineN >= 400) {
            $useN = min($kitN, $useN + 4);
        } elseif ($lineN <= 250) {
            $useN = max(7, $useN - 2);
        }
        $useN = max(7, min($kitN, $useN));
        $active = array_slice($kit, 0, $useN);
        $activeN = count($active);

        $weights = [];
        for ($i = 0; $i < $activeN; $i++) {
            $weights[$i] = 1.0 / pow($i + 1, 1.25);
        }

        foreach ($rows as $i => $row) {
            if ($i < $activeN) {
                $pick = $active[$i];
            } else {
                $h = (int) sprintf('%u', crc32((string) $row['id'] . '|' . $code . '|z'));
                $pick = $active[$pickWeighted($weights, $h)];
            }
            $name = $pick[0];
            $unit = $pick[1];
            $uc = (float) $pick[2];
            $qty = (float) ($row['quantity'] ?? 1);
            if ($qty <= 0) {
                $qty = 1;
            }
            if ($unit === 'ream' && $qty > 20) {
                $qty = 1 + ($i % 3);
            }
            if ($unit === 'pc' && $qty > 200) {
                $qty = 10 + ($i % 40);
            }
            $lt = round($qty * $uc, 2);
            $updLine->execute([$name, $unit, $uc, $lt, 'Consumable', 'Consumable', (int) $row['id']]);

            $item = consumableEnsureItem($conn, 'Consumable', $name, $unit);
            $updIss->execute([
                $name,
                $qty,
                $unit,
                $uc,
                (int) $item['id'],
                (int) $row['id'],
            ]);
            $conn->prepare('UPDATE proc_pr_line SET quantity = ? WHERE id = ?')
                ->execute([$qty, (int) $row['id']]);
            $updated++;
        }
        echo "  {$off['code']}: {$lineN} lines -> {$activeN} SKUs (pool {$kitN})\n";
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    throw $e;
}

echo "Updated lines: $updated\n\n";

// Verify top-1 diversity
$st = $conn->query(
    "SELECT o.code, o.name,
            (SELECT l.description FROM proc_pr_line l
             JOIN proc_pr p ON p.id=l.pr_id
             WHERE p.office_id=o.id AND p.is_consumable=1
             GROUP BY l.description ORDER BY COUNT(*) DESC LIMIT 1) top1,
            (SELECT COUNT(DISTINCT l2.description) FROM proc_pr_line l2
             JOIN proc_pr p2 ON p2.id=l2.pr_id
             WHERE p2.office_id=o.id AND p2.is_consumable=1) uniq
     FROM offices o
     ORDER BY o.name"
);
$topFreq = [];
echo "Top-1 after diversify:\n";
foreach ($st as $r) {
    $t = (string) $r['top1'];
    $topFreq[$t] = ($topFreq[$t] ?? 0) + 1;
    echo sprintf("  %-6s uniq=%2d top=%s\n", $r['code'], $r['uniq'], $t);
}
arsort($topFreq);
echo "\nMost common top-1 counts:\n";
foreach (array_slice($topFreq, 0, 8, true) as $item => $n) {
    echo "  $n x $item\n";
}
echo "Distinct top-1 labels: " . count($topFreq) . " / 27 offices\n";
