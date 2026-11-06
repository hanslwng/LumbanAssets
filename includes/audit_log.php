<?php
/**
 * Who changed what — writes to inventory_history with the signed-in user.
 */

function auditLogActor(): string
{
    $name = trim((string) ($_SESSION['user_name'] ?? ''));

    return $name !== '' ? $name : 'System';
}

function auditLogEnsureSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS inventory_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_item_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            changed_by VARCHAR(255) DEFAULT NULL,
            changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_hist_item (property_item_id),
            INDEX idx_hist_at (changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    try {
        $conn->exec('DROP TRIGGER IF EXISTS trg_property_update');
    } catch (PDOException $e) {
        // Trigger may be missing or the DB user cannot drop it.
    }
    activityLogEnsureSchema($conn);
}

function activityLogEnsureSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS user_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            changed_by VARCHAR(255) DEFAULT NULL,
            office_id INT DEFAULT NULL,
            module VARCHAR(40) NOT NULL DEFAULT 'inventory',
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(40) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            entity_label VARCHAR(255) DEFAULT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ua_at (created_at),
            INDEX idx_ua_office (office_id),
            INDEX idx_ua_module (module),
            INDEX idx_ua_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    activityLogBackfillFromHistory($conn);
    activityLogRepairOfficeScope($conn);
}

/**
 * Point each row at the affected department, not the actor's office.
 */
function activityLogRepairOfficeScope(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $conn->exec(
            "UPDATE user_activity a
             INNER JOIN property_items p ON a.entity_type = 'item' AND a.entity_id = p.id
             SET a.office_id = p.office_id
             WHERE p.office_id IS NOT NULL AND (a.office_id IS NULL OR a.office_id <> p.office_id)"
        );
    } catch (PDOException $e) {
        // Best-effort.
    }
    try {
        $conn->exec(
            "UPDATE user_activity a
             INNER JOIN proc_pr pr ON a.entity_type = 'pr' AND a.entity_id = pr.id
             SET a.office_id = pr.office_id
             WHERE pr.office_id IS NOT NULL AND (a.office_id IS NULL OR a.office_id <> pr.office_id)"
        );
    } catch (PDOException $e) {
        // proc_pr may be missing.
    }
    try {
        $conn->exec(
            "UPDATE user_activity a
             INNER JOIN users u ON a.entity_type IN ('user', 'session') AND a.entity_id = u.id
             SET a.office_id = u.office_id
             WHERE u.office_id IS NOT NULL AND (a.office_id IS NULL OR a.office_id <> u.office_id)"
        );
    } catch (PDOException $e) {
        // Best-effort.
    }
    try {
        $conn->exec(
            "UPDATE user_activity a
             INNER JOIN users u ON u.id = a.user_id
             SET a.office_id = u.office_id
             WHERE a.office_id IS NULL AND u.office_id IS NOT NULL
               AND a.entity_type IN ('user', 'session')"
        );
    } catch (PDOException $e) {
        // Best-effort.
    }
}

function activityLogBackfillFromHistory(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $copied = (int) $conn->query("SELECT COUNT(*) FROM user_activity WHERE entity_type = 'item'")->fetchColumn();
        if ($copied > 0) {
            return;
        }
        $conn->exec(
            "INSERT INTO user_activity (user_id, changed_by, office_id, module, action, entity_type, entity_id, entity_label, old_value, new_value, created_at)
             SELECT NULL, h.changed_by, p.office_id,
                    CASE
                        WHEN h.action LIKE '%Issued%' OR h.action LIKE '%PAR%' THEN 'issuance'
                        WHEN h.action LIKE '%Return%' THEN 'returns'
                        WHEN h.action LIKE '%unserviceable%' OR h.action LIKE '%Inspection%' OR h.action LIKE '%Disposal%' THEN 'unserviceable'
                        ELSE 'inventory'
                    END,
                    h.action, 'item', h.property_item_id, p.property_number, h.old_value, h.new_value, h.changed_at
             FROM inventory_history h
             LEFT JOIN property_items p ON p.id = h.property_item_id"
        );
    } catch (PDOException $e) {
        // Best-effort copy of older item history.
    }
}

function activityLogActorOfficeId(): ?int
{
    $id = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : 0;

    return $id > 0 ? $id : null;
}

/**
 * Department that owns the record — never the actor's office when they differ.
 *
 * @return array{office_id: ?int, label: string}
 */
function activityLogResolveAffectedOffice(PDO $conn, string $entityType, int $entityId, string $label, ?int $provided): array
{
    $out = ['office_id' => $provided, 'label' => ''];
    try {
        if ($entityType === 'item' && $entityId > 0) {
            $st = $conn->prepare('SELECT property_number, office_id FROM property_items WHERE id = ? LIMIT 1');
            $st->execute([$entityId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($row['office_id'])) {
                $out['office_id'] = (int) $row['office_id'];
            }
            if (!empty($row['property_number'])) {
                $out['label'] = (string) $row['property_number'];
            }

            return $out;
        }
        if ($entityType === 'pr' && $entityId > 0) {
            $st = $conn->prepare('SELECT pr_number, office_id FROM proc_pr WHERE id = ? LIMIT 1');
            $st->execute([$entityId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($row['office_id'])) {
                $out['office_id'] = (int) $row['office_id'];
            }
            if (!empty($row['pr_number'])) {
                $out['label'] = (string) $row['pr_number'];
            }

            return $out;
        }
        if (in_array($entityType, ['user', 'session'], true) && $entityId > 0) {
            $st = $conn->prepare('SELECT email, office_id FROM users WHERE id = ? LIMIT 1');
            $st->execute([$entityId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($row['office_id'])) {
                $out['office_id'] = (int) $row['office_id'];
            }
            if ($label === '' && !empty($row['email'])) {
                $out['label'] = (string) $row['email'];
            }

            return $out;
        }
        if (in_array($entityType, ['user', 'session'], true) && $label !== '' && str_contains($label, '@')) {
            $st = $conn->prepare('SELECT office_id FROM users WHERE LOWER(email) = ? LIMIT 1');
            $st->execute([strtolower($label)]);
            $oid = (int) ($st->fetchColumn() ?: 0);
            if ($oid > 0) {
                $out['office_id'] = $oid;
            }

            return $out;
        }
        if ($entityType === 'issuance' && $entityId > 0) {
            $st = $conn->prepare('SELECT form_number, office_id FROM issuance_records WHERE id = ? LIMIT 1');
            $st->execute([$entityId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($row['office_id'])) {
                $out['office_id'] = (int) $row['office_id'];
            }
            if ($label === '' && !empty($row['form_number'])) {
                $out['label'] = (string) $row['form_number'];
            }

            return $out;
        }
        if ($entityType === 'office' && $entityId > 0) {
            $out['office_id'] = $entityId;

            return $out;
        }
    } catch (PDOException $e) {
        // Keep provided office.
    }
    if ($out['office_id'] === null) {
        $out['office_id'] = activityLogActorOfficeId();
    }

    return $out;
}

function activityLogClientIp(): ?string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        return null;
    }

    return function_exists('mb_substr') ? mb_substr($ip, 0, 45) : substr($ip, 0, 45);
}

function auditLogModuleForAction(string $action): string
{
    $a = strtolower($action);
    if (str_contains($a, 'issued') || str_contains($a, 'par ')) {
        return 'issuance';
    }
    if (str_contains($a, 'return') && !str_contains($a, 'returned to inventory')) {
        return 'returns';
    }
    if (
        str_contains($a, 'unserviceable')
        || str_contains($a, 'inspection')
        || str_contains($a, 'dispos')
        || str_contains($a, 'cannot repair')
        || str_contains($a, 'returned to inventory')
    ) {
        return 'unserviceable';
    }

    return 'inventory';
}

/**
 * @param array<string, mixed> $event
 */
function activityLogWrite(PDO $conn, array $event): void
{
    $action = trim((string) ($event['action'] ?? ''));
    if ($action === '') {
        return;
    }
    try {
        activityLogEnsureSchema($conn);
        $userId = array_key_exists('user_id', $event)
            ? ((int) $event['user_id'] > 0 ? (int) $event['user_id'] : null)
            : ((int) ($_SESSION['user_id'] ?? 0) > 0 ? (int) $_SESSION['user_id'] : null);
        $officeId = array_key_exists('office_id', $event)
            ? ($event['office_id'] !== null && $event['office_id'] !== '' ? (int) $event['office_id'] : null)
            : null;
        if ($officeId !== null && $officeId <= 0) {
            $officeId = null;
        }
        $entityType = trim((string) ($event['entity_type'] ?? ''));
        $entityId = isset($event['entity_id']) ? (int) $event['entity_id'] : 0;
        $label = trim((string) ($event['entity_label'] ?? ''));
        $resolved = activityLogResolveAffectedOffice($conn, $entityType, $entityId, $label, $officeId);
        $officeId = $resolved['office_id'];
        if ($label === '' && $resolved['label'] !== '') {
            $label = $resolved['label'];
        }
        $stmt = $conn->prepare(
            'INSERT INTO user_activity (user_id, changed_by, office_id, module, action, entity_type, entity_id, entity_label, old_value, new_value, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            isset($event['changed_by']) && (string) $event['changed_by'] !== ''
                ? (string) $event['changed_by']
                : auditLogActor(),
            $officeId,
            (string) ($event['module'] ?? 'inventory'),
            $action,
            $entityType !== '' ? $entityType : null,
            $entityId > 0 ? $entityId : null,
            $label !== '' ? $label : null,
            array_key_exists('old_value', $event) ? ($event['old_value'] === null ? null : (string) $event['old_value']) : null,
            array_key_exists('new_value', $event) ? ($event['new_value'] === null ? null : (string) $event['new_value']) : null,
            activityLogClientIp(),
        ]);
    } catch (PDOException $e) {
        // Never block the parent save.
    }
}

/**
 * @param array<string, mixed> $extra
 */
function activityLog(PDO $conn, string $module, string $action, array $extra = []): void
{
    $extra['module'] = $module;
    $extra['action'] = $action;
    activityLogWrite($conn, $extra);
}

/**
 * PAR-level activity (consumable issues have no property_items to hang history on).
 */
function activityLogParIssued(
    PDO $conn,
    int $prId,
    string $formNumber,
    string $issuedTo,
    int $count,
    ?int $officeId = null,
    bool $consumable = false,
    bool $updated = false
): void {
    if ($prId <= 0) {
        return;
    }
    $unit = $consumable ? 'consumable line' : 'item';
    if ($count !== 1) {
        $unit .= 's';
    }
    activityLog($conn, 'issuance', $updated ? 'PAR updated' : 'PAR issued', [
        'entity_type' => 'pr',
        'entity_id' => $prId,
        'entity_label' => $formNumber !== '' ? $formNumber : null,
        'office_id' => $officeId && $officeId > 0 ? $officeId : null,
        'new_value' => trim('Issued to ' . $issuedTo . ' · ' . $count . ' ' . $unit),
    ]);
}

function auditLogWrite(PDO $conn, int $itemId, string $action, ?string $oldValue = null, ?string $newValue = null, ?string $changedBy = null): void
{
    if ($itemId <= 0 || $action === '') {
        return;
    }
    try {
        auditLogEnsureSchema($conn);
        $stmt = $conn->prepare(
            'INSERT INTO inventory_history (property_item_id, action, old_value, new_value, changed_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $who = $changedBy !== null && $changedBy !== '' ? $changedBy : auditLogActor();
        $stmt->execute([
            $itemId,
            $action,
            $oldValue,
            $newValue,
            $who,
        ]);
        activityLogWrite($conn, [
            'module' => auditLogModuleForAction($action),
            'action' => $action,
            'entity_type' => 'item',
            'entity_id' => $itemId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => $who,
        ]);
    } catch (PDOException $e) {
        // Never block the parent save.
    }
}

function auditLogOfficeName(PDO $conn, $officeId): string
{
    $id = (int) $officeId;
    if ($id <= 0) {
        return (string) $officeId;
    }
    try {
        $st = $conn->prepare('SELECT name FROM offices WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $name = $st->fetchColumn();
        if ($name) {
            return (string) $name;
        }
    } catch (PDOException $e) {
        // Fall through.
    }

    return 'Office #' . $id;
}

/**
 * @param array<string, mixed> $before
 * @param array<string, mixed> $after
 */
function auditLogItemDiff(PDO $conn, int $itemId, array $before, array $after): void
{
    $map = [
        'property_number' => 'Property number changed',
        'status' => 'Status changed',
        'office_id' => 'Office transferred',
        'description' => 'Description changed',
        'article' => 'Article changed',
        'unit_value' => 'Unit value changed',
        'quantity' => 'Quantity changed',
        'quantity_physical_count' => 'Physical count changed',
        'condition_status' => 'Condition changed',
        'remarks' => 'Remarks changed',
        'acquisition_date' => 'Acquisition date changed',
        'category' => 'Category changed',
    ];
    foreach ($map as $field => $action) {
        $old = $before[$field] ?? null;
        $new = $after[$field] ?? null;
        if ((string) $old === (string) $new) {
            continue;
        }
        if ($field === 'office_id') {
            $old = auditLogOfficeName($conn, $old);
            $new = auditLogOfficeName($conn, $new);
        }
        auditLogWrite($conn, $itemId, $action, $old === null ? null : (string) $old, $new === null ? null : (string) $new);
    }
}
