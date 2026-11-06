<?php
/**
 * Shared helpers: seed / remove Admin Dashboard EXAMPLE data.
 * Marker string: [DASHBOARD_DEMO]
 */

declare(strict_types=1);

const DASHBOARD_DEMO_TAG = '[DASHBOARD_DEMO]';

/**
 * Delete previously seeded dashboard demo rows (safe for real data).
 *
 * @return array{items:int,prs:int,issuances:int}
 */
function dashboardDemoRemove(PDO $db): array
{
    $db->exec('SET FOREIGN_KEY_CHECKS=0');

    // Issuances linked to demo property items
    $db->exec(
        "DELETE i FROM issuance_records i
         INNER JOIN property_items p ON p.id = i.property_item_id
         WHERE p.property_number LIKE 'DEMO-%'
            OR p.remarks LIKE '%" . DASHBOARD_DEMO_TAG . "%'
            OR i.form_number LIKE 'DEMO-%'
            OR i.remarks LIKE '%" . DASHBOARD_DEMO_TAG . "%'"
    );

    $db->exec(
        "DELETE h FROM inventory_history h
         INNER JOIN property_items p ON p.id = h.property_item_id
         WHERE p.property_number LIKE 'DEMO-%'
            OR p.remarks LIKE '%" . DASHBOARD_DEMO_TAG . "%'"
    );

    $db->exec(
        "DELETE r FROM return_records r
         INNER JOIN property_items p ON p.id = r.property_item_id
         WHERE p.property_number LIKE 'DEMO-%'
            OR p.remarks LIKE '%" . DASHBOARD_DEMO_TAG . "%'"
    );

    $db->exec(
        "DELETE d FROM disposal_records d
         INNER JOIN property_items p ON p.id = d.property_item_id
         WHERE p.property_number LIKE 'DEMO-%'
            OR p.remarks LIKE '%" . DASHBOARD_DEMO_TAG . "%'"
    );

    $db->exec(
        "DELETE u FROM unserviceable_records u
         INNER JOIN property_items p ON p.id = u.property_item_id
         WHERE p.property_number LIKE 'DEMO-%'
            OR p.remarks LIKE '%" . DASHBOARD_DEMO_TAG . "%'"
    );

    $items = (int) $db->exec(
        "DELETE FROM property_items
         WHERE property_number LIKE 'DEMO-%'
            OR remarks LIKE '%" . DASHBOARD_DEMO_TAG . "%'"
    );

    // Procurement demo PRs (cascade lines via FK when enabled; delete lines first anyway)
    $db->exec(
        "DELETE l FROM proc_pr_line l
         INNER JOIN proc_pr p ON p.id = l.pr_id
         WHERE p.pr_number LIKE 'DEMO-PR-%'
            OR p.purpose LIKE '%" . DASHBOARD_DEMO_TAG . "%'"
    );

    // Child procurement docs if any
    try {
        $db->exec(
            "DELETE airl FROM proc_air_line airl
             INNER JOIN proc_air air ON air.id = airl.air_id
             INNER JOIN proc_po po ON po.id = air.po_id
             INNER JOIN proc_pr p ON p.id = po.pr_id
             WHERE p.pr_number LIKE 'DEMO-PR-%'"
        );
        $db->exec(
            "DELETE air FROM proc_air air
             INNER JOIN proc_po po ON po.id = air.po_id
             INNER JOIN proc_pr p ON p.id = po.pr_id
             WHERE p.pr_number LIKE 'DEMO-PR-%'"
        );
        $db->exec(
            "DELETE pol FROM proc_po_line pol
             INNER JOIN proc_po po ON po.id = pol.po_id
             INNER JOIN proc_pr p ON p.id = po.pr_id
             WHERE p.pr_number LIKE 'DEMO-PR-%'"
        );
        $db->exec(
            "DELETE po FROM proc_po po
             INNER JOIN proc_pr p ON p.id = po.pr_id
             WHERE p.pr_number LIKE 'DEMO-PR-%'"
        );
        $db->exec(
            "DELETE ql FROM proc_quotation_line ql
             INNER JOIN proc_quotation q ON q.id = ql.quotation_id
             INNER JOIN proc_pr p ON p.id = q.pr_id
             WHERE p.pr_number LIKE 'DEMO-PR-%'"
        );
        $db->exec(
            "DELETE q FROM proc_quotation q
             INNER JOIN proc_pr p ON p.id = q.pr_id
             WHERE p.pr_number LIKE 'DEMO-PR-%'"
        );
    } catch (Throwable $e) {
        // Optional tables / empty
    }

    $prs = (int) $db->exec(
        "DELETE FROM proc_pr
         WHERE pr_number LIKE 'DEMO-PR-%'
            OR purpose LIKE '%" . DASHBOARD_DEMO_TAG . "%'"
    );

    $db->exec('SET FOREIGN_KEY_CHECKS=1');

    return ['items' => max(0, $items), 'prs' => max(0, $prs), 'issuances' => 0];
}

/**
 * Insert example inventory + PR frequency data for charts.
 *
 * @return array{items:int,issuances:int,prs:int,pr_lines:int}
 */
function dashboardDemoSeed(PDO $db): array
{
    // Idempotent: clear previous demo first
    dashboardDemoRemove($db);

    $offices = $db->query(
        'SELECT id, name, code FROM offices WHERE is_active = 1 ORDER BY id ASC LIMIT 8'
    )->fetchAll(PDO::FETCH_ASSOC);

    if (count($offices) < 3) {
        throw new RuntimeException('Need at least 3 active offices to seed demo analytics.');
    }

    $tag = DASHBOARD_DEMO_TAG;
    $now = new DateTimeImmutable('now');

    // Recurring items departments "always" request (frequency charts)
    $catalog = [
        ['article' => 'Bond paper A4', 'desc' => 'Bond paper A4 80gsm (ream)', 'unit' => 'ream', 'cost' => 280, 'cat' => 'SE', 'group' => 'Office Supplies'],
        ['article' => 'Ballpen black', 'desc' => 'Ballpen black 0.5mm', 'unit' => 'box', 'cost' => 120, 'cat' => 'SE', 'group' => 'Office Supplies'],
        ['article' => 'Ink cartridge', 'desc' => 'Printer ink cartridge black', 'unit' => 'pc', 'cost' => 1850, 'cat' => 'SE', 'group' => 'IT Supplies'],
        ['article' => 'USB flash drive', 'desc' => 'USB flash drive 32GB', 'unit' => 'pc', 'cost' => 450, 'cat' => 'SE', 'group' => 'IT Supplies'],
        ['article' => 'Office chair', 'desc' => 'Ergonomic office chair mesh back', 'unit' => 'pc', 'cost' => 6500, 'cat' => 'SE', 'group' => 'Furniture'],
        ['article' => 'Laptop computer', 'desc' => 'Laptop computer i5 16GB', 'unit' => 'unit', 'cost' => 52000, 'cat' => 'PPE', 'group' => 'IT Equipment'],
        ['article' => 'Desktop computer', 'desc' => 'Desktop computer complete set', 'unit' => 'unit', 'cost' => 48000, 'cat' => 'PPE', 'group' => 'IT Equipment'],
        ['article' => 'Air conditioning unit', 'desc' => 'Window-type aircon 1.5HP', 'unit' => 'unit', 'cost' => 28500, 'cat' => 'PPE', 'group' => 'Appliances'],
        ['article' => 'Filing cabinet', 'desc' => 'Steel filing cabinet 4-drawer', 'unit' => 'pc', 'cost' => 9800, 'cat' => 'SE', 'group' => 'Furniture'],
        ['article' => 'Extension cord', 'desc' => 'Heavy duty extension cord 5m', 'unit' => 'pc', 'cost' => 650, 'cat' => 'SE', 'group' => 'Electrical'],
    ];

    // Which offices repeatedly ask for which catalog indexes (creates clear "always ask" patterns)
    $officePatterns = [
        // office index in $offices => list of [catalogIndex, timesOnSeparatePRs]
        0 => [[0, 5], [1, 4], [2, 3], [4, 2]],          // Mayor-like: paper, pens, ink, chairs
        1 => [[5, 4], [2, 3], [3, 3], [9, 2]],          // Accounting: laptops, ink, USB, cords
        2 => [[6, 3], [7, 2], [8, 3], [0, 2]],          // Engineering: desktops, AC, cabinets, paper
        3 => [[0, 4], [1, 5], [3, 2]],                   // Budget: paper & pens heavy
        4 => [[5, 2], [6, 2], [2, 4], [4, 3]],           // Health-ish pattern
        5 => [[7, 3], [8, 2], [9, 4], [0, 2]],
        6 => [[1, 3], [0, 3], [3, 4], [4, 2]],
        7 => [[5, 3], [2, 2], [8, 2], [1, 2]],
    ];

    $insItem = $db->prepare(
        'INSERT INTO property_items
         (property_number, article, description, unit_of_measure, category, item_group, sort_order,
          unit_value, quantity, office_id, status, condition_status, acquisition_date, acquisition_mode,
          remarks, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );

    $insHist = $db->prepare(
        'INSERT INTO inventory_history (property_item_id, action, old_value, new_value, changed_by, changed_at)
         VALUES (?,?,?,?,?,?)'
    );

    $insIss = $db->prepare(
        'INSERT INTO issuance_records
         (form_type, form_number, property_item_id, office_id, issued_to, position, issue_date,
          status, remarks, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );

    $itemCount = 0;
    $issuanceCount = 0;
    $demoItemIds = []; // for issuances / recent lists

    $seq = 1;
    foreach ($offices as $oi => $office) {
        // 2–3 inventory items per office (mix SE/PPE)
        $picks = array_slice($catalog, ($oi * 2) % count($catalog), 3);
        if (count($picks) < 2) {
            $picks = array_slice($catalog, 0, 3);
        }
        foreach ($picks as $pi => $c) {
            $pn = sprintf('DEMO-%s-%04d', $c['cat'], $seq++);
            $acq = $now->modify('-' . (20 + $oi * 3 + $pi) . ' days')->format('Y-m-d');
            $created = $now->modify('-' . (18 + $oi * 2 + $pi) . ' days')->format('Y-m-d H:i:s');
            $status = ($pi === 0 && $oi < 3) ? 'Issued' : 'Active';
            $insItem->execute([
                $pn,
                $c['article'],
                $c['desc'],
                $c['unit'],
                $c['cat'],
                $c['group'],
                $pi,
                $c['cost'],
                1,
                (int) $office['id'],
                $status,
                'Good',
                $acq,
                'Purchase',
                $tag . ' Example inventory for dashboard analytics.',
                $created,
                $created,
            ]);
            $id = (int) $db->lastInsertId();
            $demoItemIds[] = ['id' => $id, 'office_id' => (int) $office['id'], 'cat' => $c['cat'], 'status' => $status];
            $itemCount++;

            $insHist->execute([
                $id,
                'Created',
                null,
                'Active',
                'dashboard-demo',
                $created,
            ]);
        }
    }

    // Issuances for Issued items + a couple Active→show recent txns
    $issN = 1;
    foreach ($demoItemIds as $row) {
        if ($row['status'] !== 'Issued' && $issN > 4) {
            continue;
        }
        if ($row['status'] !== 'Issued' && $issN > 2) {
            continue;
        }
        $formType = $row['cat'] === 'PPE' ? 'PAR' : 'ICS';
        $issueDate = $now->modify('-' . (5 + $issN) . ' days')->format('Y-m-d');
        $created = $now->modify('-' . (5 + $issN) . ' days')->format('Y-m-d H:i:s');
        $insIss->execute([
            $formType,
            sprintf('DEMO-%s-%03d', $formType, $issN),
            $row['id'],
            $row['office_id'],
            'Demo Custodian ' . $issN,
            'Staff',
            $issueDate,
            'Active',
            $tag . ' Example issuance.',
            $created,
            $created,
        ]);
        if ($row['status'] === 'Issued') {
            $insHist->execute([
                $row['id'],
                'Status Changed',
                'Active',
                'Issued',
                'dashboard-demo',
                $created,
            ]);
        }
        $issuanceCount++;
        $issN++;
        if ($issN > 6) {
            break;
        }
    }

    $insPr = $db->prepare(
        'INSERT INTO proc_pr
         (pr_number, office_id, pr_section, pr_date, purpose, requested_by, status, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $insLine = $db->prepare(
        'INSERT INTO proc_pr_line
         (pr_id, line_no, quantity, unit, description, est_unit_cost, est_line_total, article, item_group)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );

    $prCount = 0;
    $lineCount = 0;
    $prSeq = 1;
    $statuses = ['submitted', 'quoting', 'po_issued', 'completed', 'submitted', 'quoting', 'completed'];

    foreach ($officePatterns as $oi => $pattern) {
        if (!isset($offices[$oi])) {
            continue;
        }
        $office = $offices[$oi];
        // Max PR count needed for this office = max frequency
        $maxTimes = 1;
        foreach ($pattern as $p) {
            $maxTimes = max($maxTimes, (int) $p[1]);
        }

        for ($t = 0; $t < $maxTimes; $t++) {
            $prDate = $now->modify('-' . (3 + $oi + $t * 7) . ' days')->format('Y-m-d');
            $created = $now->modify('-' . (3 + $oi + $t * 7) . ' days')->format('Y-m-d H:i:s');
            $status = $statuses[($prCount + $t) % count($statuses)];
            $prNo = sprintf('DEMO-PR-%04d', $prSeq++);

            $insPr->execute([
                $prNo,
                (int) $office['id'],
                'Admin Section',
                $prDate,
                $tag . ' Example PR for ' . $office['name'] . ' analytics preview.',
                'Demo Requester',
                $status,
                $created,
                $created,
            ]);
            $prId = (int) $db->lastInsertId();
            $prCount++;

            $lineNo = 1;
            foreach ($pattern as [$catIdx, $times]) {
                if ($t >= $times) {
                    continue;
                }
                $c = $catalog[$catIdx];
                $qty = 1 + ($t % 3);
                $lineTotal = $qty * $c['cost'];
                $insLine->execute([
                    $prId,
                    $lineNo++,
                    $qty,
                    $c['unit'],
                    $c['desc'],
                    $c['cost'],
                    $lineTotal,
                    $c['article'],
                    $c['group'],
                ]);
                $lineCount++;
            }
        }
    }

    return [
        'items' => $itemCount,
        'issuances' => $issuanceCount,
        'prs' => $prCount,
        'pr_lines' => $lineCount,
    ];
}
