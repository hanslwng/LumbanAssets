<?php
/**
 * Consumable stock ledger: Current = Starting + Restocks − Released.
 */

require_once __DIR__ . '/consumable_schema.php';

function consumableStockGet(PDO $conn, int $itemId): float
{
    consumableEnsureSchema($conn);
    $st = $conn->prepare('SELECT current_stock FROM consumable_stock WHERE item_id = ?');
    $st->execute([$itemId]);
    $v = $st->fetchColumn();
    if ($v === false) {
        $conn->prepare('INSERT IGNORE INTO consumable_stock (item_id, current_stock) VALUES (?, 0)')->execute([$itemId]);

        return 0.0;
    }

    return (float) $v;
}

function consumableStockApplyMove(
    PDO $conn,
    int $itemId,
    string $moveType,
    float $quantity,
    array $meta = []
): float {
    consumableEnsureSchema($conn);
    if ($itemId <= 0 || $quantity == 0.0) {
        return consumableStockGet($conn, $itemId);
    }
    $qty = abs($quantity);
    $signed = ($moveType === 'release') ? -$qty : $qty;
    if ($moveType === 'adjust') {
        $signed = $quantity;
        $qty = abs($quantity);
    }

    $conn->prepare('INSERT IGNORE INTO consumable_stock (item_id, current_stock) VALUES (?, 0)')->execute([$itemId]);
    $conn->prepare('UPDATE consumable_stock SET current_stock = current_stock + ? WHERE item_id = ?')
        ->execute([$signed, $itemId]);

    $conn->prepare(
        'INSERT INTO consumable_stock_moves
         (item_id, move_type, quantity, unit_cost, office_id, proc_pr_id, proc_pr_line_id, issuance_id, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $itemId,
        $moveType === 'adjust' ? 'adjust' : $moveType,
        $qty,
        isset($meta['unit_cost']) ? (float) $meta['unit_cost'] : null,
        isset($meta['office_id']) ? (int) $meta['office_id'] : null,
        isset($meta['proc_pr_id']) ? (int) $meta['proc_pr_id'] : null,
        isset($meta['proc_pr_line_id']) ? (int) $meta['proc_pr_line_id'] : null,
        isset($meta['issuance_id']) ? (int) $meta['issuance_id'] : null,
        isset($meta['note']) ? mb_substr((string) $meta['note'], 0, 255) : null,
    ]);

    return consumableStockGet($conn, $itemId);
}

/**
 * When a consumable PR completes (AIR filed), restock GSO ledger from PO/PR quantities.
 * Idempotent per PR line (skips lines that already have a restock move).
 */
function consumableRestockFromCompletedPr(PDO $conn, int $prId): int
{
    consumableEnsureSchema($conn);
    if ($prId <= 0 || !consumablePrIsConsumable($conn, $prId)) {
        return 0;
    }

    $prSt = $conn->prepare('SELECT office_id FROM proc_pr WHERE id = ?');
    $prSt->execute([$prId]);
    $officeId = (int) ($prSt->fetchColumn() ?: 0);

    $sql = 'SELECT l.id AS line_id, l.quantity, l.unit, l.description, l.article, l.est_unit_cost,
                   pol.unit_cost AS po_unit_cost, pol.quantity AS po_qty
            FROM proc_pr_line l
            LEFT JOIN proc_po po ON po.id = (
                SELECT po2.id FROM proc_po po2 WHERE po2.pr_id = l.pr_id ORDER BY po2.id DESC LIMIT 1
            )
            LEFT JOIN proc_po_line pol ON pol.po_id = po.id AND pol.pr_line_id = l.id
            WHERE l.pr_id = ?
            ORDER BY l.line_no ASC, l.id ASC';
    $st = $conn->prepare($sql);
    $st->execute([$prId]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);
    $n = 0;
    foreach ($lines as $line) {
        $lineId = (int) ($line['line_id'] ?? 0);
        if ($lineId <= 0) {
            continue;
        }
        $chk = $conn->prepare(
            "SELECT id FROM consumable_stock_moves
             WHERE proc_pr_line_id = ? AND move_type = 'restock' LIMIT 1"
        );
        $chk->execute([$lineId]);
        if ($chk->fetchColumn()) {
            continue;
        }
        $item = consumableEnsureItem(
            $conn,
            (string) ($line['article'] ?? ''),
            (string) ($line['description'] ?? ''),
            (string) ($line['unit'] ?? '')
        );
        $qty = (float) ($line['po_qty'] ?? 0);
        if ($qty <= 0) {
            $qty = (float) ($line['quantity'] ?? 0);
        }
        if ($qty <= 0) {
            continue;
        }
        $unitCost = (float) ($line['po_unit_cost'] ?? 0);
        if ($unitCost <= 0) {
            $unitCost = (float) ($line['est_unit_cost'] ?? 0);
        }
        consumableStockApplyMove($conn, (int) $item['id'], 'restock', $qty, [
            'unit_cost' => $unitCost,
            'office_id' => $officeId,
            'proc_pr_id' => $prId,
            'proc_pr_line_id' => $lineId,
            'note' => 'AIR restock',
        ]);
        $n++;
    }

    return $n;
}

function consumableRecentUnitCost(PDO $conn, int $itemId): float
{
    consumableEnsureSchema($conn);
    $st = $conn->prepare(
        "SELECT unit_cost FROM consumable_stock_moves
         WHERE item_id = ? AND unit_cost IS NOT NULL AND unit_cost > 0
         ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$itemId]);
    $v = $st->fetchColumn();
    if ($v !== false) {
        return (float) $v;
    }
    $st2 = $conn->prepare(
        'SELECT unit_cost FROM issuance_consumable_lines
         WHERE consumable_item_id = ? AND unit_cost IS NOT NULL AND unit_cost > 0
         ORDER BY id DESC LIMIT 1'
    );
    $st2->execute([$itemId]);

    return (float) ($st2->fetchColumn() ?: 0);
}

/**
 * @return list<array<string,mixed>>
 */
function consumableLinesAlreadyIssuedMap(PDO $conn, int $prId): array
{
    consumableEnsureSchema($conn);
    $st = $conn->prepare(
        'SELECT icl.proc_pr_line_id
         FROM issuance_consumable_lines icl
         INNER JOIN issuance_records i ON i.id = icl.issuance_id
         WHERE i.proc_pr_id = ? AND icl.proc_pr_line_id IS NOT NULL'
    );
    $st->execute([$prId]);
    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $lid) {
        $map[(int) $lid] = true;
    }

    return $map;
}
