<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/app/models/User.php';
require_once dirname(__DIR__) . '/includes/settings_helpers.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/includes/password_reset.php';

header('Content-Type: application/json');
PermissionHelper::require('view');

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed']);
}

settingsEnsureSchema($conn);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = PermissionHelper::isAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function settingsReadJson(): array
{
    $raw = json_decode((string) file_get_contents('php://input'), true);

    return is_array($raw) ? $raw : $_POST;
}

function settingsCurrentUser(PDO $conn, int $userId): ?array
{
    $st = $conn->prepare(
        'SELECT u.id, u.username, u.full_name, u.email, u.role, u.office_id, u.is_active, o.name AS office_name, o.code AS office_code
         FROM users u
         LEFT JOIN offices o ON o.id = u.office_id
         WHERE u.id = ?'
    );
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function settingsPayload(PDO $conn, int $userId, bool $isAdmin): array
{
    $me = settingsCurrentUser($conn, $userId);
    $prefs = settingsUserPrefs($conn, $userId);
    $offices = [];
    try {
        $offices = (new User())->listOfficesWithAccountCounts();
    } catch (Exception $e) {
        $offices = [];
    }

    $out = [
        'me' => $me,
        'prefs' => $prefs,
        'ppe_threshold' => settingsPpeThreshold($conn),
        'mail_configured' => lgueMailConfigured(),
        'mail_driver' => lgueEnv('MAIL_DRIVER', 'smtp'),
        'is_admin' => $isAdmin,
        'offices' => $offices,
        'max_accounts_per_office' => User::MAX_ACCOUNTS_PER_DEPARTMENT,
    ];

    if ($isAdmin) {
        $out['system'] = [
            'lgu_name' => settingsGet($conn, 'lgu_name', ''),
            'gso_officer' => settingsGet($conn, 'gso_officer', ''),
            'gso_designation' => settingsGet($conn, 'gso_designation', ''),
            'mayor_name' => settingsGet($conn, 'mayor_name', ''),
            'mayor_designation' => settingsGet($conn, 'mayor_designation', ''),
            'print_underline_name' => settingsGet($conn, 'print_underline_name', ''),
            'ppe_threshold' => settingsGet($conn, 'ppe_threshold', '50000'),
            'forecast_mode' => settingsForecastMode($conn),
            'allow_items_import' => settingsAllowItemsImport($conn) ? '1' : '0',
        ];
    }

    return $out;
}

if ($method === 'GET') {
    jsonResponse(['success' => true, 'data' => settingsPayload($conn, $userId, $isAdmin)]);
}

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

$body = settingsReadJson();
$action = (string) ($body['action'] ?? '');

try {
    if ($action === 'profile') {
        $name = trim((string) ($body['full_name'] ?? ''));
        $username = trim((string) ($body['username'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $nameErr = lgueNameError($name, 'Full name');
        if ($nameErr !== null) {
            jsonResponse(['success' => false, 'message' => $nameErr]);
        }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,63}$/', $username)) {
            jsonResponse(['success' => false, 'message' => 'Username must start with a letter and use letters, numbers, or underscores (3–64).']);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'Enter a valid email address.']);
        }
        $dup = $conn->prepare('SELECT id FROM users WHERE (LOWER(email) = ? OR username = ?) AND id <> ? LIMIT 1');
        $dup->execute([$email, $username, $userId]);
        if ($dup->fetch()) {
            jsonResponse(['success' => false, 'message' => 'That email or username is already in use.']);
        }
        $conn->prepare('UPDATE users SET full_name = ?, username = ?, email = ? WHERE id = ?')
            ->execute([$name, $username, $email, $userId]);
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        if (!function_exists('activityLog')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        activityLog($conn, 'settings', 'Profile updated', [
            'entity_type' => 'user',
            'entity_id' => $userId,
            'entity_label' => $email,
            'new_value' => $name . ' · ' . $username,
        ]);
        jsonResponse(['success' => true, 'message' => 'Profile updated.', 'data' => settingsPayload($conn, $userId, $isAdmin)]);
    }

    if ($action === 'password') {
        $current = (string) ($body['current_password'] ?? '');
        $new = (string) ($body['new_password'] ?? '');
        $confirm = (string) ($body['confirm_password'] ?? '');
        if ($new !== $confirm) {
            jsonResponse(['success' => false, 'message' => 'New passwords do not match.']);
        }
        if (!lgueIsStrongPassword($new)) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.']);
        }
        $st = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $st->execute([$userId]);
        $hash = (string) ($st->fetchColumn() ?: '');
        if ($hash === '' || !password_verify($current, $hash)) {
            jsonResponse(['success' => false, 'message' => 'Current password is incorrect.']);
        }
        (new User())->updatePassword($userId, $new);
        if (!function_exists('activityLog')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        activityLog($conn, 'settings', 'Password changed', [
            'entity_type' => 'user',
            'entity_id' => $userId,
            'entity_label' => (string) ($_SESSION['user_email'] ?? ''),
        ]);
        jsonResponse(['success' => true, 'message' => 'Password changed.']);
    }

    if ($action === 'prefs') {
        if (array_key_exists('session_minutes', $body)) {
            $minsErr = lgueNumericFieldError($body['session_minutes'], 'Session minutes', false, 15.0, false);
            if ($minsErr !== null) {
                jsonResponse(['success' => false, 'message' => $minsErr]);
            }
        }
        if (array_key_exists('pr_printed_name', $body)) {
            $nErr = lgueNameError((string) $body['pr_printed_name'], 'Printed name', false);
            if ($nErr !== null) {
                jsonResponse(['success' => false, 'message' => $nErr]);
            }
        }
        if (array_key_exists('pr_designation', $body)) {
            $dErr = lgueOptionalTextError((string) $body['pr_designation'], 'Designation');
            if ($dErr !== null) {
                jsonResponse(['success' => false, 'message' => $dErr]);
            }
        }
        $prefs = settingsSavePrefs($conn, $userId, $body);
        if (!function_exists('activityLog')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        activityLog($conn, 'settings', 'Preferences saved', [
            'entity_type' => 'user',
            'entity_id' => $userId,
            'entity_label' => (string) ($_SESSION['user_email'] ?? ''),
        ]);
        jsonResponse(['success' => true, 'message' => 'Preferences saved.', 'data' => settingsPayload($conn, $userId, $isAdmin), 'prefs' => $prefs]);
    }

    if ($action === 'system') {
        if (!$isAdmin) {
            jsonResponse(['success' => false, 'message' => 'Only an administrator can change system settings.']);
        }
        $keys = ['lgu_name', 'gso_officer', 'gso_designation', 'mayor_name', 'mayor_designation', 'print_underline_name', 'forecast_mode', 'allow_items_import'];
        $nameKeys = ['gso_officer' => 'GSO officer', 'mayor_name' => 'Mayor name'];
        $textKeys = [
            'lgu_name' => 'LGU name',
            'gso_designation' => 'GSO designation',
            'mayor_designation' => 'Mayor designation',
            'print_underline_name' => 'Print underline name',
        ];
        foreach ($nameKeys as $k => $label) {
            if (array_key_exists($k, $body)) {
                $nErr = lgueNameError((string) $body[$k], $label, false);
                if ($nErr !== null) {
                    jsonResponse(['success' => false, 'message' => $nErr]);
                }
            }
        }
        foreach ($textKeys as $k => $label) {
            if (array_key_exists($k, $body)) {
                $tErr = lgueOptionalTextError((string) $body[$k], $label);
                if ($tErr !== null) {
                    jsonResponse(['success' => false, 'message' => $tErr]);
                }
            }
        }
        foreach ($keys as $k) {
            if (array_key_exists($k, $body)) {
                $val = $body[$k];
                if ($k === 'forecast_mode') {
                    $val = strtolower((string) $val) === 'live' ? 'live' : 'demo';
                }
                if ($k === 'allow_items_import') {
                    $val = (!empty($val) && $val !== '0') ? '1' : '0';
                }
                settingsSet($conn, $k, $val);
            }
        }
        if (array_key_exists('forecast_mode', $body)) {
            try {
                $conn->exec('DELETE FROM demo_forecast_cache WHERE id = 1');
            } catch (PDOException $e) {
                // Cache table may not exist yet.
            }
        }
        if (isset($body['ppe_threshold'])) {
            $thErr = lgueNumericFieldError($body['ppe_threshold'], 'PPE threshold', true, 1.0, false);
            if ($thErr !== null) {
                jsonResponse(['success' => false, 'message' => $thErr]);
            }
            $th = (float) $body['ppe_threshold'];
            if ($th < 1) {
                jsonResponse(['success' => false, 'message' => 'PPE threshold must be greater than 0.']);
            }
            settingsSet($conn, 'ppe_threshold', (string) (int) round($th));
        }
        if (!function_exists('activityLog')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        activityLog($conn, 'settings', 'System settings saved', [
            'entity_type' => 'system',
            'entity_label' => 'LGU settings',
        ]);
        jsonResponse(['success' => true, 'message' => 'System settings saved.', 'data' => settingsPayload($conn, $userId, $isAdmin)]);
    }

    if ($action === 'user_create') {
        if (!$isAdmin) {
            jsonResponse(['success' => false, 'message' => 'Only an administrator can create users.']);
        }
        $name = trim((string) ($body['full_name'] ?? ''));
        $username = trim((string) ($body['username'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $role = (string) ($body['role'] ?? 'User');
        $officeId = (int) ($body['office_id'] ?? 0);
        $nameErr = lgueNameError($name, 'Full name');
        if ($nameErr !== null) {
            jsonResponse(['success' => false, 'message' => $nameErr]);
        }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,63}$/', $username)) {
            jsonResponse(['success' => false, 'message' => 'Username must start with a letter and use letters, numbers, or underscores (3–64).']);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'Enter a valid email address.']);
        }
        if (!in_array($role, ['Admin', 'User', 'Viewer'], true)) {
            jsonResponse(['success' => false, 'message' => 'Invalid role.']);
        }
        if ($role !== 'Admin' && $officeId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Assign a department for User and Viewer accounts.']);
        }
        if (!lgueIsStrongPassword($password)) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.']);
        }
        $result = (new User())->createUser($name, $email, $username, $password, $role, $officeId > 0 ? $officeId : null);
        if (empty($result['success'])) {
            jsonResponse(['success' => false, 'message' => $result['message'] ?? 'Could not create user.']);
        }
        if (!function_exists('activityLog')) {
            require_once dirname(__DIR__) . '/includes/audit_log.php';
        }
        activityLog($conn, 'settings', 'User created', [
            'office_id' => $officeId > 0 ? $officeId : null,
            'entity_type' => 'user',
            'entity_id' => isset($result['user_id']) ? (int) $result['user_id'] : 0,
            'entity_label' => $email,
            'new_value' => $role . ' · ' . $username,
        ]);
        jsonResponse(['success' => true, 'message' => 'User account created.', 'data' => settingsPayload($conn, $userId, $isAdmin)]);
    }

    jsonResponse(['success' => false, 'message' => 'Unknown settings action.']);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Settings update failed.']);
}
