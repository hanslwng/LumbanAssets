<?php
/**
 * @return array<string, mixed>|null
 */
function procurement_print_load_pr(PDO $conn, int $prId): ?array {
    $st = $conn->prepare(
        'SELECT p.*, o.name AS office_name FROM proc_pr p JOIN offices o ON o.id = p.office_id WHERE p.id=?',
    );
    $st->execute([$prId]);
    $pr = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pr) {
        return null;
    }
    $st = $conn->prepare(
        'SELECT * FROM proc_pr_line WHERE pr_id=? ORDER BY line_no ASC',
    );
    $st->execute([$prId]);

    return ['pr' => $pr, 'lines' => $st->fetchAll(PDO::FETCH_ASSOC)];
}

/**
 * @return array<string, mixed>|null
 */
function procurement_print_load_quotation(PDO $conn, int $qid): ?array {
    $st = $conn->prepare(
        'SELECT q.*, p.pr_number, p.pr_date, p.office_id,
            COALESCE((SELECT SUM(l.est_line_total) FROM proc_pr_line l WHERE l.pr_id = p.id), 0) AS pr_estimate_total
         FROM proc_quotation q JOIN proc_pr p ON p.id = q.pr_id WHERE q.id=?',
    );
    $st->execute([$qid]);
    $qt = $st->fetch(PDO::FETCH_ASSOC);
    if (!$qt) {
        return null;
    }
    $sql = 'SELECT prl.line_no, prl.quantity, prl.unit, prl.description, ql.unit_price, ql.line_total
            FROM proc_quotation_line ql
            JOIN proc_pr_line prl ON prl.id = ql.pr_line_id
            WHERE ql.quotation_id = ?
            ORDER BY prl.line_no ASC';
    $st = $conn->prepare($sql);
    $st->execute([$qid]);

    return ['quotation' => $qt, 'lines' => $st->fetchAll(PDO::FETCH_ASSOC)];
}

/**
 * @return array<string, mixed>|null
 */
function procurement_print_load_po(PDO $conn, int $poId): ?array {
    $st = $conn->prepare(
        'SELECT po.*, pr.pr_number, pr.office_id FROM proc_po po JOIN proc_pr pr ON pr.id = po.pr_id WHERE po.id=?',
    );
    $st->execute([$poId]);
    $po = $st->fetch(PDO::FETCH_ASSOC);
    if (!$po) {
        return null;
    }
    $st = $conn->prepare(
        'SELECT pol.id, COALESCE(prl.line_no, pol.line_no) AS line_no,
                COALESCE(prl.quantity, pol.quantity) AS quantity,
                COALESCE(prl.unit, pol.unit) AS unit,
                COALESCE(prl.description, pol.description) AS description,
                pol.unit_cost,
                ROUND(pol.unit_cost * COALESCE(prl.quantity, pol.quantity), 2) AS amount,
                pol.pr_line_id
         FROM proc_po_line pol
         LEFT JOIN proc_pr_line prl ON prl.id = pol.pr_line_id
         WHERE pol.po_id=?
         ORDER BY COALESCE(prl.line_no, pol.line_no) ASC, pol.id ASC',
    );
    $st->execute([$poId]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);
    $sum = 0.0;
    foreach ($lines as $ln) {
        $sum += (float) ($ln['amount'] ?? 0);
    }
    $po['total_amount'] = round($sum, 2);

    return ['po' => $po, 'lines' => $lines];
}

/**
 * @return array<string, mixed>|null
 */
function procurement_print_load_air(PDO $conn, int $airId): ?array {
    $st = $conn->prepare(
        'SELECT a.*, po.po_number AS linked_po_number, po.po_date AS linked_po_date, pr.office_id
         FROM proc_air a
         JOIN proc_po po ON po.id = a.po_id
         JOIN proc_pr pr ON pr.id = po.pr_id
         WHERE a.id=?',
    );
    $st->execute([$airId]);
    $air = $st->fetch(PDO::FETCH_ASSOC);
    if (!$air) {
        return null;
    }
    $st = $conn->prepare(
        'SELECT COALESCE(prl.line_no, pol.line_no, al.line_no) AS line_no,
                COALESCE(prl.quantity, pol.quantity, al.quantity) AS quantity,
                COALESCE(prl.unit, pol.unit, al.unit) AS unit,
                COALESCE(prl.description, pol.description, al.description) AS description
         FROM proc_air_line al
         INNER JOIN proc_air a ON a.id = al.air_id
         LEFT JOIN proc_po_line pol ON pol.po_id = a.po_id AND pol.line_no = al.line_no
         LEFT JOIN proc_pr_line prl ON prl.id = pol.pr_line_id
         WHERE al.air_id=?
         ORDER BY COALESCE(prl.line_no, pol.line_no, al.line_no) ASC, al.id ASC',
    );
    $st->execute([$airId]);

    return ['air' => $air, 'lines' => $st->fetchAll(PDO::FETCH_ASSOC)];
}
