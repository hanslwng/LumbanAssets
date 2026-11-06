<?php
/**
 * Multi-item PAR helpers: one purchase request → one issuance,
 * then items split to SE / PPE by actual unit cost.
 */

require_once __DIR__ . '/pr_inventory_groups.php';

function issuanceEnsureProcPrColumn(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $conn->query('SELECT proc_pr_id FROM issuance_records LIMIT 1');
    } catch (PDOException $e) {
        try {
            $conn->exec('ALTER TABLE issuance_records ADD COLUMN proc_pr_id INT(11) DEFAULT NULL');
        } catch (PDOException $e2) {
            // Best-effort.
        }
    }
}

function issuanceEnsureItemsTable(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    issuanceEnsureProcPrColumn($conn);
    try {
        $conn->exec(
            'CREATE TABLE IF NOT EXISTS issuance_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                issuance_id INT NOT NULL,
                property_item_id INT NOT NULL,
                proc_pr_line_id INT DEFAULT NULL,
                quantity DECIMAL(12,4) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_issuance_property_item (property_item_id),
                KEY idx_issuance_id (issuance_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    } catch (PDOException $e) {
        return;
    }
    try {
        $conn->query('SELECT proc_pr_line_id FROM issuance_items LIMIT 1');
    } catch (PDOException $e) {
        try {
            $conn->exec('ALTER TABLE issuance_items ADD COLUMN proc_pr_line_id INT DEFAULT NULL');
        } catch (PDOException $e2) {
            // Best-effort.
        }
    }
    try {
        $conn->exec(
            'INSERT IGNORE INTO issuance_items (issuance_id, property_item_id, quantity)
             SELECT id, property_item_id, 1 FROM issuance_records
             WHERE property_item_id IS NOT NULL AND property_item_id > 0'
        );
    } catch (PDOException $e) {
        // Older schemas may not allow the backfill; list/view still work from the header row.
    }
}

function issuanceDepartmentOfficeId(): ?int
{
    if (!class_exists('PermissionHelper') || !PermissionHelper::isDepartmentScoped()) {
        return null;
    }
    $oid = (int) PermissionHelper::officeId();

    return $oid > 0 ? $oid : -1;
}

/** Whole numbers as int (1 not 1.0000); otherwise up to 4 decimals without trailing zeros. */
function issuanceFormatQty($q)
{
    $n = (float) $q;
    if (!is_finite($n)) {
        return 1;
    }
    if (abs($n - round($n)) < 0.0000001) {
        return (int) round($n);
    }

    return (float) rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
}

function issuanceParDescription(string $article, string $description): string
{
    $desc = trim($description);
    $art = trim((string) (preg_replace('/^\d+\s*(?:[\.\)]\s*|\s+)/', '', $article) ?? $article));
    if ($art === '' || strcasecmp($art, $desc) === 0) {
        return $desc !== '' ? $desc : ($art !== '' ? $art : '—');
    }
    if ($desc === '') {
        return $art;
    }

    return $art . ' — ' . $desc;
}

function issuancePrPoMeta(PDO $conn, int $prId): array
{
    if ($prId <= 0) {
        return ['po_number' => '', 'po_date' => null];
    }
    try {
        $st = $conn->prepare('SELECT po_number, po_date FROM proc_po WHERE pr_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$prId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'po_number' => (string) ($row['po_number'] ?? ''),
                'po_date' => $row['po_date'] ?? null,
            ];
        }
    } catch (PDOException $e) {
        // PO table may not exist on older installs.
    }

    return ['po_number' => '', 'po_date' => null];
}

function issuanceLineUnitCost(PDO $conn, array $line): float
{
    if (isset($line['po_unit_cost']) && $line['po_unit_cost'] !== null && $line['po_unit_cost'] !== '') {
        return max(0, (float) $line['po_unit_cost']);
    }
    $prId = (int) ($line['pr_id'] ?? 0);
    $lineId = (int) ($line['id'] ?? $line['line_id'] ?? 0);
    if ($prId > 0 && $lineId > 0) {
        try {
            $st = $conn->prepare(
                'SELECT pol.unit_cost
                 FROM proc_po po
                 INNER JOIN proc_po_line pol ON pol.po_id = po.id
                 WHERE po.pr_id = ? AND pol.pr_line_id = ?
                 ORDER BY po.id DESC
                 LIMIT 1'
            );
            $st->execute([$prId, $lineId]);
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null && $v !== '') {
                return max(0, (float) $v);
            }
        } catch (PDOException $e) {
            // Fall back to the PR estimate.
        }
    }

    return max(0, (float) ($line['est_unit_cost'] ?? 0));
}

function issuanceCreateItemFromPrLine(PDO $conn, array $line): int
{
    $prId = (int) ($line['pr_id'] ?? 0);
    if ($prId > 0 && function_exists('consumablePrIsConsumable') && consumablePrIsConsumable($conn, $prId)) {
        throw new RuntimeException('Consumable PR lines must not create PPE/SE property items');
    }
    $officeId = (int) ($line['office_id'] ?? 0);
    $description = trim((string) ($line['description'] ?? ''));
    $unitValue = issuanceLineUnitCost($conn, $line);
    $qty = (int) max(1, round((float) ($line['quantity'] ?? 1)));
    $uom = trim((string) ($line['unit'] ?? 'pc')) ?: 'pc';
    $group = prInventoryNormalizeGroup((string) ($line['item_group'] ?: $line['pr_section'] ?: 'OTHERS'));
    $typedArticle = trim((string) ($line['article'] ?? ''));
    if ($typedArticle === '') {
        $typedArticle = $description !== '' ? $description : 'Item';
    }
    $threshold = function_exists('settingsPpeThreshold') ? (float) settingsPpeThreshold($conn) : 50000.0;
    $category = $unitValue >= $threshold ? 'PPE' : 'SE';
    $place = prPlaceItemInArticleList($conn, $officeId, $category, $group, $typedArticle);
    $article = $place['article'];
    $sortOrder = (int) $place['sort_order'];

    $year = date('Y');
    $cnt = $conn->prepare('SELECT COUNT(*) FROM property_items WHERE category = ? AND YEAR(created_at) = ?');
    $cnt->execute([$category, $year]);
    $sequence = str_pad((string) (((int) $cnt->fetchColumn()) + 1), 6, '0', STR_PAD_LEFT);
    $propertyNumber = $year . '-' . $category . '-' . $sequence;

    $po = issuancePrPoMeta($conn, (int) ($line['pr_id'] ?? 0));
    $acq = !empty($po['po_date']) ? (string) $po['po_date'] : date('Y-m-d');
    $ins = $conn->prepare(
        'INSERT INTO property_items (property_number, article, description, unit_of_measure, category, item_group, sort_order, unit_value, quantity, quantity_physical_count, office_id, status, acquisition_date, remarks)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Active\', ?, ?)'
    );
    $ins->execute([
        $propertyNumber,
        $article,
        $description,
        $uom,
        $category,
        $group,
        $sortOrder,
        $unitValue,
        $qty,
        $qty,
        $officeId,
        $acq,
        'From PR ' . (string) ($line['pr_number'] ?? ''),
    ]);
    $itemId = (int) $conn->lastInsertId();
    if (!function_exists('auditLogWrite')) {
        require_once __DIR__ . '/audit_log.php';
    }
    auditLogWrite($conn, $itemId, 'Created', null, trim($propertyNumber . ' — ' . $description));

    return $itemId;
}

function issuanceFormNumberFromPr(string $prNumber): string
{
    $pr = trim($prNumber);
    if ($pr === '') {
        return '';
    }
    if (preg_match('/^PAR([\-\s].+)$/i', $pr, $m)) {
        return 'PAR' . $m[1];
    }
    if (preg_match('/^PR([\-\s].+)$/i', $pr, $m)) {
        return 'PAR' . $m[1];
    }

    return 'PAR-' . $pr;
}

function issuanceUniqueFormNumber(PDO $conn, string $formNumber): string
{
    $base = trim($formNumber);
    if ($base === '') {
        $base = 'PAR-' . date('Y') . '-000001';
    }
    $candidate = $base;
    $n = 2;
    while ($n < 100) {
        try {
            $st = $conn->prepare('SELECT id FROM issuance_records WHERE form_number = ? LIMIT 1');
            $st->execute([$candidate]);
            if (!$st->fetch()) {
                return $candidate;
            }
        } catch (PDOException $e) {
            return $candidate;
        }
        $candidate = $base . '-' . $n;
        $n++;
    }

    return $base . '-' . date('His');
}

function issuanceExistingForPr(PDO $conn, int $prId): ?array
{
    if ($prId <= 0) {
        return null;
    }
    try {
        $st = $conn->prepare('SELECT * FROM issuance_records WHERE proc_pr_id = ? ORDER BY id ASC LIMIT 1');
        $st->execute([$prId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Create inventory rows for leftover PR lines and attach them to the same PAR.
 *
 * @return int number of newly attached lines
 */
function issuanceAttachRemainingLines(PDO $conn, int $issuanceId, int $prId, array $meta = []): int
{
    if ($issuanceId <= 0 || $prId <= 0) {
        return 0;
    }
    if (function_exists('consumablePrIsConsumable') && consumablePrIsConsumable($conn, $prId)) {
        return 0;
    }
    issuanceEnsureItemsTable($conn);
    $sql = 'SELECT l.*, p.pr_number, p.office_id, p.pr_section, p.status
            FROM proc_pr_line l
            INNER JOIN proc_pr p ON p.id = l.pr_id
            WHERE l.pr_id = ?
            ORDER BY l.line_no ASC, l.id ASC';
    $st = $conn->prepare($sql);
    $st->execute([$prId]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);
    $attached = 0;
    $issuedTo = (string) ($meta['issued_to'] ?? '');
    $formNumber = (string) ($meta['form_number'] ?? '');
    foreach ($lines as $line) {
        $lineId = (int) ($line['id'] ?? 0);
        $itemId = (int) ($line['property_item_id'] ?? 0);
        if ($itemId <= 0) {
            $itemId = issuanceCreateItemFromPrLine($conn, $line);
            $conn->prepare('UPDATE proc_pr_line SET property_item_id = ? WHERE id = ?')->execute([$itemId, $lineId]);
        }
        $chk = $conn->prepare('SELECT id FROM issuance_items WHERE property_item_id = ? LIMIT 1');
        $chk->execute([$itemId]);
        if ($chk->fetchColumn()) {
            continue;
        }
        $qty = issuanceFormatQty($line['quantity'] ?? 1);
        if ((float) $qty < 1) {
            $qty = 1;
        }
        try {
            $conn->prepare(
                'INSERT INTO issuance_items (issuance_id, property_item_id, proc_pr_line_id, quantity) VALUES (?, ?, ?, ?)'
            )->execute([$issuanceId, $itemId, $lineId > 0 ? $lineId : null, $qty]);
        } catch (PDOException $e) {
            $conn->prepare(
                'INSERT INTO issuance_items (issuance_id, property_item_id, quantity) VALUES (?, ?, ?)'
            )->execute([$issuanceId, $itemId, $qty]);
        }
        $attached++;
        if (!function_exists('auditLogWrite')) {
            require_once __DIR__ . '/audit_log.php';
        }
        $statusSt = $conn->prepare('SELECT status FROM property_items WHERE id = ?');
        $statusSt->execute([$itemId]);
        $prev = (string) ($statusSt->fetchColumn() ?: 'Active');
        auditLogWrite($conn, $itemId, 'Issued', $prev, trim('Issued to ' . $issuedTo . ' (' . $formNumber . ')'));
    }
    if ($attached > 0) {
        $wb = __DIR__ . '/par_template_workbook.php';
        if (is_file($wb)) {
            require_once $wb;
        }
        if (function_exists('par_invalidate_filled')) {
            par_invalidate_filled($issuanceId);
        }
    }

    return $attached;
}

/**
 * @return list<array<string, mixed>>
 */
function issuanceLoadItems(PDO $conn, int $issuanceId): array
{
    issuanceEnsureItemsTable($conn);
    $rows = [];
    try {
        $st = $conn->prepare(
            'SELECT ii.quantity AS issue_qty, p.id AS property_item_id, p.property_number, p.description, p.article,
                    p.unit_of_measure, p.unit_value, p.quantity AS item_quantity, p.acquisition_date,
                    p.item_group, p.category, p.remarks AS item_remarks
             FROM issuance_items ii
             INNER JOIN property_items p ON p.id = ii.property_item_id
             WHERE ii.issuance_id = ?
             ORDER BY ii.id ASC'
        );
        $st->execute([$issuanceId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $rows = [];
    }
    if (!$rows) {
        $st = $conn->prepare(
            'SELECT 1 AS issue_qty, p.id AS property_item_id, p.property_number, p.description, p.article,
                    p.unit_of_measure, p.unit_value, p.quantity AS item_quantity, p.acquisition_date,
                    p.item_group, p.category, p.remarks AS item_remarks
             FROM issuance_records i
             INNER JOIN property_items p ON p.id = i.property_item_id
             WHERE i.id = ?'
        );
        $st->execute([$issuanceId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $out = [];
    foreach ($rows as $row) {
        $qty = (int) ($row['issue_qty'] ?? $row['item_quantity'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }
        $uv = (float) ($row['unit_value'] ?? 0);
        $uom = trim((string) ($row['unit_of_measure'] ?? ''));
        if ($uom === '') {
            $uom = 'pc';
        }
        $out[] = [
            'property_item_id' => (int) ($row['property_item_id'] ?? 0),
            'quantity' => $qty,
            'unit' => $uom,
            'description' => issuanceParDescription((string) ($row['article'] ?? ''), (string) ($row['description'] ?? '')),
            'property_number' => (string) ($row['property_number'] ?? ''),
            'acquisition_date' => $row['acquisition_date'] ?? null,
            'unit_value' => $uv,
            'amount' => $uv * $qty,
            'article' => (string) ($row['article'] ?? ''),
            'item_group' => (string) ($row['item_group'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'remarks' => '',
        ];
    }

    return $out;
}

/**
 * @param list<int> $issuanceIds
 * @return array<int, list<array<string, string>>>
 */
function issuanceListItemMap(PDO $conn, array $issuanceIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $issuanceIds))));
    if (!$ids) {
        return [];
    }
    issuanceEnsureItemsTable($conn);
    $in = implode(',', $ids);
    $map = [];
    try {
        $rows = $conn->query(
            "SELECT ii.issuance_id, p.property_number, p.description, p.article, p.category
             FROM issuance_items ii
             INNER JOIN property_items p ON p.id = ii.property_item_id
             WHERE ii.issuance_id IN ($in)
             ORDER BY ii.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
    foreach ($rows as $row) {
        $id = (int) $row['issuance_id'];
        $map[$id][] = [
            'property_number' => (string) ($row['property_number'] ?? ''),
            'description' => issuanceParDescription((string) ($row['article'] ?? ''), (string) ($row['description'] ?? '')),
            'category' => (string) ($row['category'] ?? ''),
        ];
    }

    return $map;
}
