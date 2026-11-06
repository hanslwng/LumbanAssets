<?php
/**
 * Consumable issuance: release stock without creating PPE/SE property_items.
 */

require_once __DIR__ . '/consumable_schema.php';
require_once __DIR__ . '/consumable_stock.php';
require_once __DIR__ . '/issuance_helpers.php';

/**
 * Issue all remaining lines on a consumable PR onto one issuance_records header.
 *
 * @return array{issuance_id:int,attached:int,already:bool,form_number:string}
 */
function consumableIssueFromPr(
    PDO $conn,
    int $prId,
    string $issuedTo,
    string $position,
    string $issueDate,
    string $remarks,
    string $formNumber = ''
): array {
    consumableEnsureSchema($conn);
    issuanceEnsureItemsTable($conn);

    if (!consumablePrIsConsumable($conn, $prId)) {
        throw new InvalidArgumentException('Not a consumable purchase request');
    }

    $prSt = $conn->prepare('SELECT * FROM proc_pr WHERE id = ? LIMIT 1');
    $prSt->execute([$prId]);
    $pr = $prSt->fetch(PDO::FETCH_ASSOC);
    if (!$pr || (string) ($pr['status'] ?? '') !== 'completed') {
        throw new RuntimeException('Purchase request must be completed before issuance');
    }

    $existing = issuanceExistingForPr($conn, $prId);
    $issuedMap = consumableLinesAlreadyIssuedMap($conn, $prId);

    $linesSt = $conn->prepare(
        'SELECT l.*, pol.unit_cost AS po_unit_cost
         FROM proc_pr_line l
         LEFT JOIN proc_po po ON po.id = (
             SELECT po2.id FROM proc_po po2 WHERE po2.pr_id = l.pr_id ORDER BY po2.id DESC LIMIT 1
         )
         LEFT JOIN proc_po_line pol ON pol.po_id = po.id AND pol.pr_line_id = l.id
         WHERE l.pr_id = ?
         ORDER BY l.line_no ASC, l.id ASC'
    );
    $linesSt->execute([$prId]);
    $lines = $linesSt->fetchAll(PDO::FETCH_ASSOC);
    $pending = [];
    foreach ($lines as $line) {
        $lid = (int) ($line['id'] ?? 0);
        if ($lid > 0 && empty($issuedMap[$lid])) {
            $pending[] = $line;
        }
    }
    if (!$pending) {
        if ($existing) {
            return [
                'issuance_id' => (int) $existing['id'],
                'attached' => 0,
                'already' => true,
                'form_number' => (string) ($existing['form_number'] ?? ''),
            ];
        }
        throw new RuntimeException('No remaining consumable lines to issue');
    }

    if ($existing) {
        $issuanceId = (int) $existing['id'];
        $formNumber = (string) ($existing['form_number'] ?? $formNumber);
        $issuedTo = (string) ($existing['issued_to'] ?? $issuedTo);
    } else {
        if (trim($formNumber) === '') {
            $formNumber = issuanceFormNumberFromPr((string) ($pr['pr_number'] ?? ''));
        }
        $formNumber = issuanceUniqueFormNumber($conn, $formNumber);
        $ins = $conn->prepare(
            'INSERT INTO issuance_records
             (form_type, form_number, property_item_id, office_id, issued_to, position, issue_date, remarks, proc_pr_id, is_consumable)
             VALUES (\'PAR\', ?, NULL, ?, ?, ?, ?, ?, ?, 1)'
        );
        $ins->execute([
            $formNumber,
            (int) $pr['office_id'],
            $issuedTo,
            $position,
            $issueDate,
            $remarks,
            $prId,
        ]);
        $issuanceId = (int) $conn->lastInsertId();
    }

    $attached = 0;
    foreach ($pending as $line) {
        $lineId = (int) $line['id'];
        $item = consumableEnsureItem(
            $conn,
            (string) ($line['article'] ?? ''),
            (string) ($line['description'] ?? ''),
            (string) ($line['unit'] ?? '')
        );
        $qty = (float) ($line['quantity'] ?? 0);
        if ($qty <= 0) {
            $qty = 1;
        }
        $unitCost = (float) ($line['po_unit_cost'] ?? 0);
        if ($unitCost <= 0) {
            $unitCost = (float) ($line['est_unit_cost'] ?? 0);
        }
        $name = consumableDisplayName((string) ($line['article'] ?? ''), (string) ($line['description'] ?? ''));

        $conn->prepare(
            'INSERT INTO issuance_consumable_lines
             (issuance_id, consumable_item_id, proc_pr_line_id, item_name, quantity, unit, unit_cost)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $issuanceId,
            (int) $item['id'],
            $lineId,
            $name,
            $qty,
            $line['unit'] ?? null,
            $unitCost > 0 ? $unitCost : null,
        ]);

        consumableStockApplyMove($conn, (int) $item['id'], 'release', $qty, [
            'unit_cost' => $unitCost,
            'office_id' => (int) $pr['office_id'],
            'proc_pr_id' => $prId,
            'proc_pr_line_id' => $lineId,
            'issuance_id' => $issuanceId,
            'note' => 'Released to department',
        ]);
        $attached++;
    }

    return [
        'issuance_id' => $issuanceId,
        'attached' => $attached,
        'already' => false,
        'form_number' => $formNumber,
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function consumableLoadIssuanceLines(PDO $conn, int $issuanceId): array
{
    consumableEnsureSchema($conn);
    $st = $conn->prepare(
        'SELECT id, consumable_item_id, proc_pr_line_id, item_name, quantity, unit, unit_cost
         FROM issuance_consumable_lines
         WHERE issuance_id = ?
         ORDER BY id ASC'
    );
    $st->execute([$issuanceId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
