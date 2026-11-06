<?php
/**
 * In-app notifications: schema, create, per-user read, prefs, optional email.
 */

require_once __DIR__ . '/settings_helpers.php';

function notificationsEnsureSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
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
        // ignore
    }
    try {
        $conn->query('SELECT office_id FROM notifications LIMIT 1');
    } catch (PDOException $e) {
        try {
            $conn->exec('ALTER TABLE notifications ADD COLUMN office_id INT(11) DEFAULT NULL, ADD KEY idx_office_id (office_id)');
        } catch (PDOException $e2) {
            // ignore
        }
    }
    try {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS notification_reads (
                user_id INT NOT NULL,
                notification_id INT NOT NULL,
                read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, notification_id),
                KEY idx_notification_id (notification_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (PDOException $e) {
        // ignore
    }
}

function notificationsCategory(string $type): string
{
    if (str_starts_with($type, 'unserviceable')) {
        return 'unserviceable';
    }
    if (str_starts_with($type, 'stock_') || str_starts_with($type, 'forecast')) {
        return 'stock';
    }
    if (
        str_starts_with($type, 'pr_')
        || str_starts_with($type, 'po_')
        || str_starts_with($type, 'proc_')
    ) {
        return 'pr';
    }

    return 'other';
}

function notificationsPrefAllows(array $prefs, string $type): bool
{
    $cat = notificationsCategory($type);
    if ($cat === 'pr' && empty($prefs['notify_pr'])) {
        return false;
    }
    if ($cat === 'unserviceable' && empty($prefs['notify_unserviceable'])) {
        return false;
    }
    if ($cat === 'stock' && empty($prefs['notify_stock'])) {
        return false;
    }

    return true;
}

function notificationsCreate(
    PDO $conn,
    string $type,
    string $title,
    string $message = '',
    ?string $relatedType = null,
    ?int $relatedId = null,
    ?int $officeId = null,
    bool $email = true
): void {
    notificationsEnsureSchema($conn);
    try {
        $stmt = $conn->prepare(
            'INSERT INTO notifications (type, title, message, related_type, related_id, office_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$type, $title, $message, $relatedType, $relatedId, $officeId]);
    } catch (PDOException $e) {
        return;
    }

    if ($email) {
        notificationsEmailRecipients($conn, $type, $title, $message, $officeId);
    }
}

function notificationsEmailRecipients(PDO $conn, string $type, string $title, string $message, ?int $officeId): void
{
    if (!function_exists('lgueMailConfigured')) {
        $mailer = dirname(__DIR__) . '/includes/mailer.php';
        if (is_file($mailer)) {
            require_once $mailer;
        }
    }
    if (!function_exists('lgueMailConfigured') || !lgueMailConfigured()) {
        return;
    }

    $actorId = (int) ($_SESSION['user_id'] ?? 0);
    try {
        $rows = $conn->query(
            'SELECT u.id, u.email, u.role, u.office_id
             FROM users u
             WHERE u.is_active = 1 AND u.email IS NOT NULL AND u.email <> \'\''
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return;
    }

    foreach ($rows as $u) {
        $uid = (int) ($u['id'] ?? 0);
        if ($uid <= 0 || $uid === $actorId) {
            continue;
        }
        $role = strtolower((string) ($u['role'] ?? ''));
        $isAdmin = $role === 'admin';
        $userOffice = (int) ($u['office_id'] ?? 0);
        if (!$isAdmin) {
            if ($officeId === null || $officeId <= 0 || $userOffice !== (int) $officeId) {
                continue;
            }
        }
        try {
            $prefs = settingsUserPrefs($conn, $uid);
        } catch (Throwable $e) {
            $prefs = ['notify_email' => true, 'notify_pr' => true, 'notify_unserviceable' => true, 'notify_stock' => true];
        }
        if (empty($prefs['notify_email']) || !notificationsPrefAllows($prefs, $type)) {
            continue;
        }
        $to = trim((string) ($u['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $html = '<p>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p><p>'
            . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>';
        try {
            lgueSendMail($to, '[LGU Inventory] ' . $title, $html, $title . "\n\n" . $message);
        } catch (Throwable $e) {
            // best-effort
        }
    }
}

function notificationsListForUser(PDO $conn): array
{
    notificationsEnsureSchema($conn);
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $isAdmin = class_exists('PermissionHelper') && PermissionHelper::isAdmin();
    $officeId = (int) ($_SESSION['user_office_id'] ?? 0);
    $prefs = [
        'notify_inapp' => true,
        'notify_pr' => true,
        'notify_unserviceable' => true,
        'notify_stock' => true,
    ];
    if ($uid > 0) {
        try {
            $prefs = settingsUserPrefs($conn, $uid);
        } catch (Throwable $e) {
            // defaults
        }
    }
    if (empty($prefs['notify_inapp'])) {
        return ['data' => [], 'unread_count' => 0, 'latest_id' => 0, 'muted' => true];
    }

    $sql = 'SELECT n.*, CASE WHEN r.notification_id IS NULL THEN 0 ELSE 1 END AS user_read
            FROM notifications n
            LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?';
    $params = [$uid];
    if (!$isAdmin) {
        $sql .= ' WHERE (n.office_id IS NULL OR n.office_id = ?)';
        $params[] = $officeId;
    }
    $sql .= ' ORDER BY n.created_at DESC, n.id DESC LIMIT 80';

    try {
        $st = $conn->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return ['data' => [], 'unread_count' => 0, 'latest_id' => 0];
    }

    $out = [];
    $unread = 0;
    $latest = 0;
    foreach ($rows as $row) {
        $type = (string) ($row['type'] ?? '');
        if (!$isAdmin && notificationsCategory($type) === 'stock') {
            continue;
        }
        if (!notificationsPrefAllows($prefs, $type)) {
            continue;
        }
        $read = ((int) ($row['user_read'] ?? 0) === 1) || ((int) ($row['is_read'] ?? 0) === 1);
        $row['is_read'] = $read ? 1 : 0;
        unset($row['user_read']);
        $out[] = $row;
        if (!$read) {
            $unread++;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id > $latest) {
            $latest = $id;
        }
        if (count($out) >= 50) {
            break;
        }
    }

    return ['data' => $out, 'unread_count' => $unread, 'latest_id' => $latest, 'muted' => false];
}

/**
 * Cheap pulse for live UI: notification ids + a fingerprint of PR statuses
 * the current user is allowed to see.
 *
 * @return array{latest_id:int, unread_count:int, proc_stamp:string}
 */
function notificationsHeadForUser(PDO $conn): array
{
    notificationsEnsureSchema($conn);
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $unscoped = class_exists('PermissionHelper') && PermissionHelper::isUnscopedAdmin();
    $officeId = class_exists('PermissionHelper') ? PermissionHelper::officeId() : (int) ($_SESSION['user_office_id'] ?? 0);

    $latest = 0;
    $unread = 0;
    try {
        $sql = 'SELECT COALESCE(MAX(n.id), 0) AS latest_id,
                       COALESCE(SUM(CASE WHEN r.notification_id IS NULL THEN 1 ELSE 0 END), 0) AS unread_count
                FROM notifications n
                LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?';
        $params = [$uid];
        if (!$unscoped) {
            $sql .= ' WHERE (n.office_id IS NULL OR n.office_id = ?)';
            $params[] = $officeId;
        }
        $st = $conn->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $latest = (int) ($row['latest_id'] ?? 0);
        $unread = (int) ($row['unread_count'] ?? 0);
    } catch (PDOException $e) {
        // keep zeros
    }

    $procStamp = '0';
    try {
        $agg = "CONCAT(
                    COALESCE(COUNT(*),0), ':',
                    COALESCE(MAX(id),0), ':',
                    COALESCE(MAX(UNIX_TIMESTAMP(updated_at)),0), ':',
                    COALESCE(SUM(status='submitted'),0), ':',
                    COALESCE(SUM(status='quoting'),0), ':',
                    COALESCE(SUM(status='po_issued'),0), ':',
                    COALESCE(SUM(status='completed'),0)
                )";
        if ($unscoped) {
            $procStamp = (string) ($conn->query("SELECT $agg FROM proc_pr")->fetchColumn() ?: '0');
        } elseif ($officeId > 0) {
            $st = $conn->prepare("SELECT $agg FROM proc_pr WHERE office_id = ?");
            $st->execute([$officeId]);
            $procStamp = (string) ($st->fetchColumn() ?: '0');
        }
    } catch (PDOException $e) {
        $procStamp = '0';
    }

    return [
        'latest_id' => $latest,
        'unread_count' => $unread,
        'proc_stamp' => $procStamp,
    ];
}

function notificationsMarkReadForUser(PDO $conn, int $id, bool $all): void
{
    notificationsEnsureSchema($conn);
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return;
    }
    $isAdmin = class_exists('PermissionHelper') && PermissionHelper::isAdmin();
    $officeId = (int) ($_SESSION['user_office_id'] ?? 0);

    if ($all) {
        $sql = 'SELECT n.id FROM notifications n
                LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?
                WHERE r.notification_id IS NULL';
        $params = [$uid];
        if (!$isAdmin) {
            $sql .= ' AND (n.office_id IS NULL OR n.office_id = ?)';
            $params[] = $officeId;
        }
        try {
            $st = $conn->prepare($sql);
            $st->execute($params);
            $ids = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            $ids = [];
        }
    } else {
        $ids = $id > 0 ? [$id] : [];
    }

    $ins = $conn->prepare(
        'INSERT IGNORE INTO notification_reads (user_id, notification_id) VALUES (?, ?)'
    );
    foreach ($ids as $nid) {
        try {
            $ins->execute([$uid, (int) $nid]);
        } catch (PDOException $e) {
            // ignore
        }
    }
}

function notificationsRaiseStockAlerts(PDO $conn, array $payload): void
{
    notificationsEnsureSchema($conn);
    $recs = $payload['recommendations'] ?? [];
    if (!is_array($recs) || $recs === []) {
        return;
    }
    $raised = 0;
    foreach ($recs as $r) {
        if ($raised >= 8 || !is_array($r)) {
            break;
        }
        $status = (string) ($r['status'] ?? '');
        if ($status !== 'Stockout risk' && $status !== 'Reorder now') {
            continue;
        }
        $officeId = isset($r['office_id']) ? (int) $r['office_id'] : 0;
        $cat = trim((string) ($r['item_category'] ?? 'Item group'));
        $qty = (int) ($r['recommended_order_qty'] ?? 0);
        $title = $status === 'Stockout risk' ? 'Stockout risk' : 'Reorder recommended';
        $message = $cat
            . ' · ' . trim((string) ($r['office_name'] ?? 'Office'))
            . ' · suggested order ' . $qty
            . '. Open Planning → Forecast.';
        try {
            $st = $conn->prepare(
                "SELECT id FROM notifications
                 WHERE type = 'stock_alert' AND office_id <=> ? AND title = ?
                   AND message LIKE ? AND created_at >= (NOW() - INTERVAL 1 DAY)
                 LIMIT 1"
            );
            $st->execute([$officeId > 0 ? $officeId : null, $title, $cat . '%']);
            if ($st->fetch()) {
                continue;
            }
        } catch (PDOException $e) {
            continue;
        }
        notificationsCreate(
            $conn,
            'stock_alert',
            $title,
            $message,
            'forecast',
            $officeId > 0 ? $officeId : null,
            $officeId > 0 ? $officeId : null,
            false
        );
        $raised++;
    }
}
