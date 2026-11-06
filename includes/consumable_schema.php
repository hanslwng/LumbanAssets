<?php
/**
 * Consumables classification + stock + issuance schema (additive only).
 * Does not alter PPE / Semi-Expendable property_items rows or workflows.
 */

function consumableEnsureSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $conn->query('SELECT is_consumable FROM proc_pr LIMIT 1');
    } catch (PDOException $e) {
        try {
            $conn->exec('ALTER TABLE proc_pr ADD COLUMN is_consumable TINYINT(1) NOT NULL DEFAULT 0');
        } catch (PDOException $e2) {
            // Best-effort.
        }
    }

    try {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS consumable_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                name_key VARCHAR(255) NOT NULL,
                unit VARCHAR(40) NULL,
                aliases TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_consumable_name_key (name_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (PDOException $e) {
        // ignore
    }

    try {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS consumable_stock (
                item_id INT NOT NULL PRIMARY KEY,
                current_stock DECIMAL(14,4) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (PDOException $e) {
        // ignore
    }

    try {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS consumable_stock_moves (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                move_type ENUM('starting','restock','release','adjust') NOT NULL,
                quantity DECIMAL(14,4) NOT NULL,
                unit_cost DECIMAL(14,4) NULL,
                office_id INT NULL,
                proc_pr_id INT NULL,
                proc_pr_line_id INT NULL,
                issuance_id INT NULL,
                note VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_csm_item (item_id),
                KEY idx_csm_created (created_at),
                KEY idx_csm_pr_line (proc_pr_line_id),
                KEY idx_csm_type (move_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (PDOException $e) {
        // ignore
    }

    try {
        $conn->query('SELECT is_consumable FROM issuance_records LIMIT 1');
    } catch (PDOException $e) {
        try {
            $conn->exec('ALTER TABLE issuance_records ADD COLUMN is_consumable TINYINT(1) NOT NULL DEFAULT 0');
        } catch (PDOException $e2) {
            // ignore
        }
    }

    // Consumable PAR headers may have no property_items row.
    try {
        $conn->exec('ALTER TABLE issuance_records MODIFY property_item_id INT NULL');
    } catch (PDOException $e) {
        // ignore (permission / already nullable)
    }

    try {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS issuance_consumable_lines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                issuance_id INT NOT NULL,
                consumable_item_id INT NOT NULL,
                proc_pr_line_id INT NULL,
                item_name VARCHAR(255) NOT NULL,
                quantity DECIMAL(14,4) NOT NULL DEFAULT 0,
                unit VARCHAR(40) NULL,
                unit_cost DECIMAL(14,4) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_icl_pr_line (proc_pr_line_id),
                KEY idx_icl_iss (issuance_id),
                KEY idx_icl_item (consumable_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (PDOException $e) {
        // ignore
    }
}

function consumableNormalizeNameKey(string $name): string
{
    $n = strtolower(trim(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $n = preg_replace('/^\d+\s*(?:[\.\)]\s*|\s+)/', '', $n) ?? $n;
    $n = preg_replace('/\s+/', ' ', $n) ?? $n;

    return mb_substr($n, 0, 255);
}

function consumableArticleIsFixedLabel(string $article): bool
{
    $a = strtolower(trim($article));

    return $a === '' || $a === 'consumable' || $a === 'consumables';
}

function consumableDisplayName(string $article, string $description): string
{
    $art = trim(preg_replace('/^\d+\s*(?:[\.\)]\s*|\s+)/', '', $article) ?? $article);
    $desc = trim($description);
    // Consumable PRs lock Article to "Consumable"; the real item name is the description.
    if (consumableArticleIsFixedLabel($art)) {
        return $desc !== '' ? $desc : 'Consumable';
    }
    if ($art !== '' && $desc !== '' && strcasecmp($art, $desc) !== 0) {
        return $art . ' — ' . $desc;
    }

    return $art !== '' ? $art : ($desc !== '' ? $desc : 'Consumable');
}

/**
 * SQL expression for the real PR line item name.
 * When Article is the fixed "Consumable" label, use Description instead.
 */
function consumablePrLineLabelSql(string $lineAlias = 'l'): string
{
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $lineAlias) ?: 'l';

    return "TRIM(COALESCE(
        CASE
            WHEN LOWER(TRIM(COALESCE({$a}.article,''))) IN ('consumable','consumables','')
                THEN NULLIF(TRIM({$a}.description),'')
            ELSE NULL
        END,
        NULLIF(TRIM({$a}.article), ''),
        NULLIF(TRIM({$a}.description), ''),
        'Unnamed item'
    ))";
}

/**
 * Find or create a consumable master row from a PR line article/description.
 */
function consumableEnsureItem(PDO $conn, string $article, string $description, ?string $unit = null): array
{
    consumableEnsureSchema($conn);
    $display = consumableDisplayName($article, $description);
    // Identity key: description when article is the fixed "Consumable" label.
    $keySource = consumableArticleIsFixedLabel($article)
        ? ($description !== '' ? $description : $display)
        : ($article !== '' ? $article : $description);
    $key = consumableNormalizeNameKey($keySource);
    if ($key === '') {
        $key = consumableNormalizeNameKey($display);
    }
    if ($key === '') {
        $key = 'unnamed-consumable';
        $display = 'Unnamed consumable';
    }

    $st = $conn->prepare('SELECT * FROM consumable_items WHERE name_key = ? LIMIT 1');
    $st->execute([$key]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if ($unit && empty($row['unit'])) {
            $conn->prepare('UPDATE consumable_items SET unit = ? WHERE id = ?')->execute([mb_substr($unit, 0, 40), (int) $row['id']]);
            $row['unit'] = $unit;
        }

        return $row;
    }

    $ins = $conn->prepare(
        'INSERT INTO consumable_items (name, name_key, unit, is_active) VALUES (?, ?, ?, 1)'
    );
    $ins->execute([$display, $key, $unit !== null && $unit !== '' ? mb_substr($unit, 0, 40) : null]);
    $id = (int) $conn->lastInsertId();
    $conn->prepare(
        'INSERT IGNORE INTO consumable_stock (item_id, current_stock) VALUES (?, 0)'
    )->execute([$id]);

    return [
        'id' => $id,
        'name' => $display,
        'name_key' => $key,
        'unit' => $unit,
        'is_active' => 1,
    ];
}

function consumablePrIsConsumable(PDO $conn, int $prId): bool
{
    if ($prId <= 0) {
        return false;
    }
    consumableEnsureSchema($conn);
    try {
        $st = $conn->prepare('SELECT is_consumable, pr_section FROM proc_pr WHERE id = ? LIMIT 1');
        $st->execute([$prId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        if ((int) ($row['is_consumable'] ?? 0) === 1) {
            return true;
        }

        return strcasecmp(trim((string) ($row['pr_section'] ?? '')), 'Consumable') === 0;
    } catch (PDOException $e) {
        return false;
    }
}
