<?php
/**
 * Copy current PR item/qty/unit onto PO and AIR lines so canvass, PO, and AIR match.
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts/sync-po-air-to-pr-lines.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$conn = getDBConnection();
$conn->beginTransaction();
try {
    $nPo = $conn->exec(
        'UPDATE proc_po_line pol
         INNER JOIN proc_pr_line prl ON prl.id = pol.pr_line_id
         SET pol.description = prl.description,
             pol.unit = prl.unit,
             pol.quantity = prl.quantity,
             pol.line_no = prl.line_no,
             pol.amount = ROUND(pol.unit_cost * prl.quantity, 2)'
    );

    $insMissing = $conn->exec(
        'INSERT INTO proc_po_line (po_id, line_no, pr_line_id, quantity, unit, description, unit_cost, amount)
         SELECT po.id, prl.line_no, prl.id, prl.quantity, prl.unit, prl.description,
                ROUND(COALESCE(NULLIF(ql.unit_price, 0), prl.est_unit_cost, 0), 2),
                ROUND(prl.quantity * COALESCE(NULLIF(ql.unit_price, 0), prl.est_unit_cost, 0), 2)
         FROM proc_pr_line prl
         INNER JOIN proc_pr p ON p.id = prl.pr_id
         INNER JOIN proc_po po ON po.pr_id = p.id
         LEFT JOIN proc_po_line pol ON pol.po_id = po.id AND pol.pr_line_id = prl.id
         LEFT JOIN proc_quotation_line ql ON ql.quotation_id = po.quotation_id AND ql.pr_line_id = prl.id
         WHERE pol.id IS NULL'
    );

    $conn->exec(
        'UPDATE proc_po po
         SET po.total_amount = (
            SELECT COALESCE(SUM(pol.amount), 0) FROM proc_po_line pol WHERE pol.po_id = po.id
         )'
    );

    $conn->exec(
        'UPDATE proc_quotation_line ql
         INNER JOIN proc_pr_line prl ON prl.id = ql.pr_line_id
         SET ql.line_total = ROUND(ql.unit_price * prl.quantity, 2)'
    );
    $conn->exec(
        'UPDATE proc_quotation q
         SET q.total_amount = (
            SELECT COALESCE(SUM(ql.line_total), 0) FROM proc_quotation_line ql WHERE ql.quotation_id = q.id
         )'
    );

    $conn->exec(
        'DELETE al FROM proc_air_line al
         INNER JOIN proc_air a ON a.id = al.air_id
         INNER JOIN proc_po po ON po.id = a.po_id
         INNER JOIN proc_pr p ON p.id = po.pr_id
         WHERE p.is_consumable = 1'
    );
    $nAir = $conn->exec(
        'INSERT INTO proc_air_line (air_id, line_no, quantity, unit, description)
         SELECT a.id, pol.line_no, pol.quantity, pol.unit, pol.description
         FROM proc_air a
         INNER JOIN proc_po po ON po.id = a.po_id
         INNER JOIN proc_pr p ON p.id = po.pr_id
         INNER JOIN proc_po_line pol ON pol.po_id = po.id
         WHERE p.is_consumable = 1'
    );

    $conn->commit();
    echo "Updated PO lines: {$nPo}\n";
    echo "Inserted missing PO lines: {$insMissing}\n";
    echo "Rebuilt AIR lines: {$nAir}\n";
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
