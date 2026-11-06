<?php
/**
 * Shared canvass/PO/AIR backfill for completed consumable PRs missing quotations.
 *
 * @param list<array{name:string,address:string}> $suppliers
 * @return array{ok:int,fail:int,errors:list<string>}
 */
function consumableBackfillCanvassForAll(PDO $conn, array $suppliers): array
{
    $hasAir = false;
    try {
        $conn->query('SELECT 1 FROM proc_air LIMIT 1');
        $hasAir = true;
    } catch (Throwable $e) {
        $hasAir = false;
    }

    $prs = $conn->query(
        "SELECT p.id, p.pr_number, p.pr_date, p.purpose, p.office_id, o.name AS office_name
         FROM proc_pr p
         LEFT JOIN offices o ON o.id = p.office_id
         WHERE p.is_consumable = 1
           AND p.status = 'completed'
           AND NOT EXISTS (SELECT 1 FROM proc_quotation q WHERE q.pr_id = p.id)
         ORDER BY p.id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $insQ = $conn->prepare(
        'INSERT INTO proc_quotation
         (pr_id, slot, supplier_name, supplier_address, quotation_ref, quotation_date, total_amount)
         VALUES (?,?,?,?,?,?,?)'
    );
    $insQl = $conn->prepare(
        'INSERT INTO proc_quotation_line (quotation_id, pr_line_id, unit_price, line_total)
         VALUES (?,?,?,?)'
    );
    $insPo = $conn->prepare(
        'INSERT INTO proc_po
         (pr_id, quotation_id, po_number, po_date, supplier_name, supplier_address,
          mode_of_procurement, place_of_delivery, delivery_term, payment_term, total_amount,
          purpose, penalty_clause, pr_reference_display, signed_by_display)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insPoLine = $conn->prepare(
        'INSERT INTO proc_po_line
         (po_id, line_no, pr_line_id, quantity, unit, description, unit_cost, amount)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $updPr = $conn->prepare(
        'UPDATE proc_pr SET canvass_reviewed_by = ?, canvass_reviewed_at = ?, updated_at = NOW() WHERE id = ?'
    );

    $insAir = null;
    $insAirLine = null;
    if ($hasAir) {
        $insAir = $conn->prepare(
            'INSERT INTO proc_air
             (po_id, air_number, air_date, supplier_or_claimant, po_reference_display, si_dr_notes,
              requisitioning_office_display, acceptance_complete, inspection_ok,
              acceptance_personnel, acceptance_title, inspection_personnel, inspection_title,
              received_by, received_title, date_inspected, date_signed)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $insAirLine = $conn->prepare(
            'INSERT INTO proc_air_line (air_id, line_no, quantity, unit, description)
             VALUES (?,?,?,?,?)'
        );
    }

    $ok = 0;
    $fail = 0;
    $errors = [];
    $total = count($prs);
    $nSup = max(1, count($suppliers));

    foreach ($prs as $i => $pr) {
        $prId = (int) $pr['id'];
        $prDate = substr((string) ($pr['pr_date'] ?? date('Y-m-d')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $prDate)) {
            $prDate = date('Y-m-d');
        }

        $linesSt = $conn->prepare(
            'SELECT id, line_no, quantity, unit, description, est_unit_cost
             FROM proc_pr_line WHERE pr_id = ? ORDER BY line_no ASC'
        );
        $linesSt->execute([$prId]);
        $prLines = $linesSt->fetchAll(PDO::FETCH_ASSOC);
        if (!$prLines) {
            $fail++;
            continue;
        }

        $base = $prId % $nSup;
        $trio = [
            $suppliers[$base],
            $suppliers[($base + 2) % $nSup],
            $suppliers[($base + 4) % $nSup],
        ];
        $mults = [0.97, 1.04, 1.11];
        $seed = ($prId * 37) % 1000;

        try {
            $conn->beginTransaction();
            $quoteIds = [];
            $quoteTotals = [];
            $quoteUnitMaps = [];

            for ($slot = 1; $slot <= 3; $slot++) {
                $sup = $trio[$slot - 1];
                $qDate = date('Y-m-d', strtotime($prDate . ' +' . $slot . ' day'));
                $ref = sprintf('RFQ-%s-S%d-%04d', date('Ymd', strtotime($prDate)), $slot, ($prId + $slot) % 10000);
                $insQ->execute([$prId, $slot, $sup['name'], $sup['address'], $ref, $qDate, 0]);
                $qid = (int) $conn->lastInsertId();
                $quoteIds[$slot] = $qid;
                $sum = 0.0;
                $unitMap = [];
                foreach ($prLines as $li => $pl) {
                    $est = (float) ($pl['est_unit_cost'] ?? 0);
                    if ($est <= 0) {
                        $est = 25 + (($seed + $li * 13) % 120);
                    }
                    $jitter = 1 + (((($seed + $slot * 17 + $li * 7) % 9) - 4) * 0.005);
                    $unit = round(max(1, $est * $mults[$slot - 1] * $jitter), 2);
                    $qty = (float) ($pl['quantity'] ?? 1);
                    if ($qty <= 0) {
                        $qty = 1;
                    }
                    $lt = round($qty * $unit, 2);
                    $insQl->execute([$qid, (int) $pl['id'], $unit, $lt]);
                    $unitMap[(int) $pl['id']] = [$unit, $lt, $qty, (string) ($pl['unit'] ?? 'pc'), (string) ($pl['description'] ?? '')];
                    $sum += $lt;
                }
                $sum = round($sum, 2);
                $conn->prepare('UPDATE proc_quotation SET total_amount = ? WHERE id = ?')->execute([$sum, $qid]);
                $quoteTotals[$slot] = $sum;
                $quoteUnitMaps[$slot] = $unitMap;
            }

            $winSlot = 1;
            $winTotal = $quoteTotals[1];
            foreach ($quoteTotals as $slot => $tot) {
                if ($tot < $winTotal) {
                    $winTotal = $tot;
                    $winSlot = $slot;
                }
            }
            $winQid = $quoteIds[$winSlot];
            $winSup = $trio[$winSlot - 1];

            $poChk = $conn->prepare('SELECT id FROM proc_po WHERE pr_id = ? LIMIT 1');
            $poChk->execute([$prId]);
            $poId = (int) ($poChk->fetchColumn() ?: 0);
            if ($poId <= 0) {
                $poDate = date('Y-m-d', strtotime($prDate . ' +5 day'));
                $insPo->execute([
                    $prId,
                    $winQid,
                    '__NEW__',
                    $poDate,
                    $winSup['name'],
                    $winSup['address'],
                    'SVP',
                    'GSO Supply Room, LGU Lumban',
                    '7 calendar days',
                    '15 days upon completion of delivery',
                    $winTotal,
                    $pr['purpose'] ?: 'For office use',
                    null,
                    $pr['pr_number'] ?: null,
                    'Municipal Mayor',
                ]);
                $poId = (int) $conn->lastInsertId();
                $poNum = sprintf('PO-%s-%06d', date('Y', strtotime($poDate)), $poId);
                $conn->prepare('UPDATE proc_po SET po_number = ? WHERE id = ?')->execute([$poNum, $poId]);

                $ln = 1;
                foreach ($prLines as $pl) {
                    $lid = (int) $pl['id'];
                    $um = $quoteUnitMaps[$winSlot][$lid] ?? null;
                    if (!$um) {
                        continue;
                    }
                    $insPoLine->execute([
                        $poId,
                        $ln++,
                        $lid,
                        $um[2],
                        $um[3] !== '' ? $um[3] : 'pc',
                        $um[4] !== '' ? $um[4] : 'Consumable item',
                        $um[0],
                        $um[1],
                    ]);
                }
            }

            if ($hasAir && $poId > 0 && $insAir && $insAirLine) {
                $airChk = $conn->prepare('SELECT id FROM proc_air WHERE po_id = ? LIMIT 1');
                $airChk->execute([$poId]);
                if (!$airChk->fetchColumn()) {
                    $poRow = $conn->prepare('SELECT po_number, supplier_name, po_date FROM proc_po WHERE id = ?');
                    $poRow->execute([$poId]);
                    $poInfo = $poRow->fetch(PDO::FETCH_ASSOC) ?: [];
                    $airDate = date('Y-m-d', strtotime(($poInfo['po_date'] ?? $prDate) . ' +3 day'));
                    $insAir->execute([
                        $poId,
                        '__AIR__',
                        $airDate,
                        (string) ($poInfo['supplier_name'] ?? $winSup['name']),
                        (string) ($poInfo['po_number'] ?? ''),
                        'SI/DR on file',
                        (string) ($pr['office_name'] ?? 'Department'),
                        1,
                        1,
                        'Property Custodian',
                        'GSO',
                        'Inspection Officer',
                        'GSO',
                        'Department Custodian',
                        'End-user',
                        $airDate,
                        $airDate,
                    ]);
                    $airId = (int) $conn->lastInsertId();
                    $airNum = sprintf('AIR-%s-%06d', date('Y', strtotime($airDate)), $airId);
                    $conn->prepare('UPDATE proc_air SET air_number = ? WHERE id = ?')->execute([$airNum, $airId]);
                    $pol = $conn->prepare('SELECT line_no, quantity, unit, description FROM proc_po_line WHERE po_id = ? ORDER BY line_no');
                    $pol->execute([$poId]);
                    foreach ($pol->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $insAirLine->execute([
                            $airId,
                            (int) $r['line_no'],
                            (float) $r['quantity'],
                            (string) $r['unit'],
                            (string) $r['description'],
                        ]);
                    }
                }
            }

            $reviewedAt = date('Y-m-d H:i:s', strtotime($prDate . ' +1 day 09:30:00'));
            $updPr->execute(['GSO Admin', $reviewedAt, $prId]);
            $conn->commit();
            $ok++;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $fail++;
            if (count($errors) < 15) {
                $errors[] = ($pr['pr_number'] ?? $prId) . ': ' . $e->getMessage();
            }
        }

        if (($i + 1) % 100 === 0 || ($i + 1) === $total) {
            echo '  canvass progress ' . ($i + 1) . "/$total (ok=$ok fail=$fail)\n";
        }
    }

    return ['ok' => $ok, 'fail' => $fail, 'errors' => $errors];
}
