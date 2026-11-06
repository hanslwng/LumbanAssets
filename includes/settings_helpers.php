<?php
/**
 * User preferences + LGU system settings (admin).
 */

require_once __DIR__ . '/env.php';

function settingsEnsureSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT DEFAULT NULL,
            setting_type VARCHAR(50) DEFAULT 'string',
            description TEXT DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS user_settings (
            user_id INT NOT NULL PRIMARY KEY,
            notify_inapp TINYINT(1) NOT NULL DEFAULT 1,
            notify_email TINYINT(1) NOT NULL DEFAULT 1,
            notify_pr TINYINT(1) NOT NULL DEFAULT 1,
            notify_unserviceable TINYINT(1) NOT NULL DEFAULT 1,
            notify_stock TINYINT(1) NOT NULL DEFAULT 1,
            pr_printed_name VARCHAR(255) DEFAULT NULL,
            pr_designation VARCHAR(255) DEFAULT NULL,
            session_minutes INT NOT NULL DEFAULT 60,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    settingsSeedDefaults($conn);
}

function settingsSeedDefaults(PDO $conn): void
{
    $defaults = [
        'lgu_name' => defined('RPCPPE_LGU_NAME') ? RPCPPE_LGU_NAME : 'Municipal Government of Lumban, Laguna',
        'gso_officer' => defined('RPCPPE_ACCOUNTABLE_OFFICER') ? RPCPPE_ACCOUNTABLE_OFFICER : '',
        'gso_designation' => defined('RPCPPE_ACCOUNTABLE_DESIGNATION') ? RPCPPE_ACCOUNTABLE_DESIGNATION : 'Supply Officer II/GSO-Designate',
        'mayor_name' => defined('RPCPPE_APPROVER_NAME') ? RPCPPE_APPROVER_NAME : '',
        'mayor_designation' => defined('RPCPPE_APPROVER_DESIGNATION') ? RPCPPE_APPROVER_DESIGNATION : 'Municipal Mayor',
        'print_underline_name' => defined('PROC_PRINT_UNDERLINE_NAME') ? PROC_PRINT_UNDERLINE_NAME : 'Municipality of Lumban',
        'ppe_threshold' => defined('PPE_THRESHOLD') ? (string) PPE_THRESHOLD : '50000',
        'forecast_mode' => 'live',
        'allow_items_import' => '1',
    ];
    $find = $conn->prepare('SELECT id FROM system_settings WHERE setting_key = ? LIMIT 1');
    $ins = $conn->prepare(
        'INSERT INTO system_settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)'
    );
    foreach ($defaults as $k => $v) {
        $find->execute([$k]);
        if ($find->fetch()) {
            continue;
        }
        try {
            $ins->execute([$k, $v, $k === 'ppe_threshold' || $k === 'allow_items_import' ? 'number' : 'string']);
        } catch (PDOException $e) {
            // Duplicate key or older schema — skip.
        }
    }
}

function settingsGet(PDO $conn, string $key, ?string $default = null): ?string
{
    settingsEnsureSchema($conn);
    $st = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $st->execute([$key]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $default;
    }

    return $row['setting_value'] !== null ? (string) $row['setting_value'] : $default;
}

function settingsSet(PDO $conn, string $key, $value): void
{
    settingsEnsureSchema($conn);
    $val = $value === null ? null : (string) $value;
    $up = $conn->prepare('UPDATE system_settings SET setting_value = ? WHERE setting_key = ?');
    $up->execute([$val, $key]);
    if ($up->rowCount() === 0) {
        $chk = $conn->prepare('SELECT id FROM system_settings WHERE setting_key = ? LIMIT 1');
        $chk->execute([$key]);
        if (!$chk->fetch()) {
            $conn->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)')->execute([$key, $val]);
        }
    }
}

function settingsPpeThreshold(?PDO $conn = null): float
{
    $fallback = defined('PPE_THRESHOLD') ? (float) PPE_THRESHOLD : 50000.0;
    if (!$conn) {
        try {
            $conn = getDBConnection();
        } catch (Throwable $e) {
            return $fallback;
        }
    }
    try {
        $v = settingsGet($conn, 'ppe_threshold', (string) $fallback);
        $n = (float) $v;

        return $n > 0 ? $n : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function settingsAllowItemsImport(PDO $conn): bool
{
    return settingsGet($conn, 'allow_items_import', '1') !== '0';
}

function settingsForecastMode(PDO $conn): string
{
    $m = strtolower((string) settingsGet($conn, 'forecast_mode', 'live'));

    return $m === 'live' ? 'live' : 'demo';
}

function settingsUserPrefs(PDO $conn, int $userId): array
{
    settingsEnsureSchema($conn);
    $st = $conn->prepare('SELECT * FROM user_settings WHERE user_id = ? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $conn->prepare('INSERT INTO user_settings (user_id) VALUES (?)')->execute([$userId]);
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    return [
        'notify_inapp' => (int) ($row['notify_inapp'] ?? 1) === 1,
        'notify_email' => (int) ($row['notify_email'] ?? 1) === 1,
        'notify_pr' => (int) ($row['notify_pr'] ?? 1) === 1,
        'notify_unserviceable' => (int) ($row['notify_unserviceable'] ?? 1) === 1,
        'notify_stock' => (int) ($row['notify_stock'] ?? 1) === 1,
        'pr_printed_name' => (string) ($row['pr_printed_name'] ?? ''),
        'pr_designation' => (string) ($row['pr_designation'] ?? ''),
        'session_minutes' => max(15, min(240, (int) ($row['session_minutes'] ?? 60))),
    ];
}

function settingsUserSessionMinutes(?PDO $conn, int $userId): int
{
    if ($userId <= 0) {
        return 60;
    }
    try {
        if (!$conn) {
            $conn = getDBConnection();
        }
        $p = settingsUserPrefs($conn, $userId);

        return (int) $p['session_minutes'];
    } catch (Throwable $e) {
        return 60;
    }
}

function settingsApplySessionTimeout(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $mins = settingsUserSessionMinutes(null, (int) $_SESSION['user_id']);
    if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity'] > $mins * 60)) {
        session_unset();
        session_destroy();
        if (function_exists('lgue_url')) {
            header('Location: ' . lgue_url('login'));
        } else {
            header('Location: /lgu_invent/login');
        }
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function settingsSavePrefs(PDO $conn, int $userId, array $in): array
{
    $cur = settingsUserPrefs($conn, $userId);
    $bool = static fn ($v, $fallback) => ($v === null ? $fallback : !empty($v) && $v !== '0' && $v !== false);
    $mins = isset($in['session_minutes']) ? (int) $in['session_minutes'] : $cur['session_minutes'];
    $mins = max(15, min(240, $mins));
    $st = $conn->prepare(
        'INSERT INTO user_settings
            (user_id, notify_inapp, notify_email, notify_pr, notify_unserviceable, notify_stock, pr_printed_name, pr_designation, session_minutes)
         VALUES (?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            notify_inapp = VALUES(notify_inapp),
            notify_email = VALUES(notify_email),
            notify_pr = VALUES(notify_pr),
            notify_unserviceable = VALUES(notify_unserviceable),
            notify_stock = VALUES(notify_stock),
            pr_printed_name = VALUES(pr_printed_name),
            pr_designation = VALUES(pr_designation),
            session_minutes = VALUES(session_minutes)'
    );
    $st->execute([
        $userId,
        $bool($in['notify_inapp'] ?? null, $cur['notify_inapp']) ? 1 : 0,
        $bool($in['notify_email'] ?? null, $cur['notify_email']) ? 1 : 0,
        $bool($in['notify_pr'] ?? null, $cur['notify_pr']) ? 1 : 0,
        $bool($in['notify_unserviceable'] ?? null, $cur['notify_unserviceable']) ? 1 : 0,
        $bool($in['notify_stock'] ?? null, $cur['notify_stock']) ? 1 : 0,
        trim((string) ($in['pr_printed_name'] ?? $cur['pr_printed_name'])),
        trim((string) ($in['pr_designation'] ?? $cur['pr_designation'])),
        $mins,
    ]);

    return settingsUserPrefs($conn, $userId);
}
