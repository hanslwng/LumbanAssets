<?php
/**
 * Shared unserviceable / repair-status helpers for APIs.
 */

const UNSERVICEABLE_REASON_CATEGORIES = [
    'Damaged',
    'Not Working',
    'Broken',
    'Missing Parts',
    'Worn Out',
    'Other',
];

function unserviceableEnsureSchema(PDO $conn): void {
    static $done = false;
    if ($done) {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['_uns_schema_ok'])) {
        $done = true;
        return;
    }
    try {
        $conn->query('SELECT repair_status FROM unserviceable_records LIMIT 1');
    } catch (PDOException $e) {
        try {
            $conn->exec(
                "ALTER TABLE unserviceable_records
                 ADD COLUMN repair_status ENUM('Pending','Serviceable','Defective') NOT NULL DEFAULT 'Pending'
                 AFTER disposal_status"
            );
        } catch (PDOException $e2) {
            // Best-effort on older installs / restricted DB users.
        }
    }

    // Must run outside any transaction: CREATE TABLE implicitly commits in MySQL.
    try {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS notifications (
                id INT(11) NOT NULL AUTO_INCREMENT,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT DEFAULT NULL,
                related_type VARCHAR(50) DEFAULT NULL,
                related_id INT(11) DEFAULT NULL,
                office_id INT(11) DEFAULT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_is_read (is_read),
                KEY idx_created_at (created_at),
                KEY idx_office_id (office_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (PDOException $e) {
        // Notifications are optional.
    }
    $done = true;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_uns_schema_ok'] = 1;
    }
}

function unserviceableGsoOfficeId(PDO $conn): ?int {
    static $cached = null;
    static $resolved = false;
    if ($resolved) {
        return $cached;
    }
    $resolved = true;
    try {
        $st = $conn->prepare(
            "SELECT id FROM offices WHERE code = 'GSO' OR name LIKE '%General Services%' ORDER BY (code = 'GSO') DESC LIMIT 1"
        );
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $cached = $row ? (int) $row['id'] : null;
    } catch (PDOException $e) {
        $cached = null;
    }

    return $cached;
}

/**
 * Best-effort notification insert. $officeId scopes who (besides admins) can see it.
 */
function unserviceableNotify(PDO $conn, string $type, string $title, string $message, int $relatedId, ?int $officeId): void {
    if (!function_exists('notificationsCreate')) {
        require_once __DIR__ . '/notifications_helpers.php';
    }
    notificationsCreate($conn, $type, $title, $message, 'unserviceable_record', $relatedId, $officeId, true);
}

/**
 * Drop "still works" reports so the item leaves the unserviceable list
 * and a later report does not stack a second row.
 */
function unserviceableRemoveWorkingReports(PDO $conn, int $propertyItemId, int $alsoRecordId = 0): void {
    if ($propertyItemId <= 0 && $alsoRecordId <= 0) {
        return;
    }
    if ($alsoRecordId > 0) {
        $st = $conn->prepare(
            "DELETE FROM unserviceable_records
             WHERE id = ?
                OR (property_item_id = ? AND repair_status = 'Serviceable')"
        );
        $st->execute([$alsoRecordId, $propertyItemId]);
        return;
    }
    if ($propertyItemId <= 0) {
        return;
    }
    $st = $conn->prepare(
        "DELETE FROM unserviceable_records
         WHERE property_item_id = ? AND repair_status = 'Serviceable'"
    );
    $st->execute([$propertyItemId]);
}

function unserviceableHasOpenReport(PDO $conn, int $propertyItemId): bool {
    if ($propertyItemId <= 0) {
        return false;
    }
    $st = $conn->prepare(
        "SELECT id FROM unserviceable_records
         WHERE property_item_id = ?
           AND COALESCE(repair_status, 'Pending') <> 'Serviceable'
           AND COALESCE(disposal_status, 'Pending') <> 'Disposed'
         ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$propertyItemId]);
    return (bool) $st->fetchColumn();
}

function unserviceableSafeRollback(PDO $conn): void {
    try {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
    } catch (PDOException $e) {
        // Ignore — MySQL may have already committed via DDL.
    }
}

function unserviceableReasonCategory(?string $reason): string {
    $reason = trim((string) $reason);
    if ($reason === '') {
        return '';
    }
    foreach (UNSERVICEABLE_REASON_CATEGORIES as $cat) {
        if ($cat === 'Other') {
            if (stripos($reason, 'Other') === 0) {
                return 'Other';
            }
            continue;
        }
        if (stripos($reason, $cat) === 0) {
            return $cat;
        }
    }

    return 'Other';
}

function unserviceableAttachDerived(array &$row): void {
    if (empty($row['repair_status'])) {
        $row['repair_status'] = 'Pending';
    }
    $row['reason_category'] = unserviceableReasonCategory($row['reason'] ?? '');
}

function unserviceableComposeReason(array $data): string {
    $category = trim((string) ($data['reason_category'] ?? ''));
    $reason = trim((string) ($data['reason'] ?? ''));
    $allowed = UNSERVICEABLE_REASON_CATEGORIES;

    if ($category !== '' && in_array($category, $allowed, true)) {
        if ($category === 'Other') {
            $extra = $reason !== '' ? $reason : trim((string) ($data['remarks'] ?? ''));
            return $extra !== '' ? ('Other: ' . $extra) : 'Other';
        }
        if ($reason !== '' && stripos($reason, $category) !== 0) {
            return $category . ' — ' . $reason;
        }

        return $category;
    }

    return $reason;
}
