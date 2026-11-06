<?php
/**
 * Registration email verification: 6-digit code, 5-minute expiry.
 * The user row is created only after the code is confirmed.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/password_reset.php';

if (!class_exists('User')) {
    require_once dirname(__DIR__) . '/app/models/User.php';
}

function registrationEnsureSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS registration_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            username VARCHAR(64) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            office_id INT NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_regc_email (email),
            INDEX idx_regc_user (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS registration_daily (
            email VARCHAR(255) NOT NULL,
            attempt_date DATE NOT NULL,
            verify_attempts INT NOT NULL DEFAULT 0,
            request_attempts INT NOT NULL DEFAULT 0,
            PRIMARY KEY (email, attempt_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function registrationDailyRow(PDO $conn, string $email): array
{
    $date = passwordResetNow()->format('Y-m-d');
    $conn->prepare(
        'INSERT INTO registration_daily (email, attempt_date, verify_attempts, request_attempts)
         VALUES (?, ?, 0, 0)
         ON DUPLICATE KEY UPDATE email = email'
    )->execute([$email, $date]);
    $st = $conn->prepare(
        'SELECT verify_attempts, request_attempts FROM registration_daily WHERE email = ? AND attempt_date = ?'
    );
    $st->execute([$email, $date]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'verify_attempts' => (int) ($row['verify_attempts'] ?? 0),
        'request_attempts' => (int) ($row['request_attempts'] ?? 0),
    ];
}

function registrationBump(PDO $conn, string $email, string $column): int
{
    if ($column !== 'verify_attempts' && $column !== 'request_attempts') {
        return 0;
    }
    $date = passwordResetNow()->format('Y-m-d');
    $conn->prepare(
        "INSERT INTO registration_daily (email, attempt_date, {$column})
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE {$column} = {$column} + 1"
    )->execute([$email, $date]);
    $st = $conn->prepare("SELECT {$column} FROM registration_daily WHERE email = ? AND attempt_date = ?");
    $st->execute([$email, $date]);

    return (int) $st->fetchColumn();
}

function registrationValidateInput(array $in): array
{
    $name = trim((string) ($in['name'] ?? ''));
    $email = passwordResetNormalizeEmail((string) ($in['email'] ?? ''));
    $username = trim((string) ($in['username'] ?? ''));
    $password = (string) ($in['password'] ?? '');
    $confirm = (string) ($in['confirm_password'] ?? '');
    $officeId = (int) ($in['office_id'] ?? 0);
    $errors = [];

    $nameLen = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($nameLen === 0) {
        $errors[] = 'Full name is required';
    } elseif ($nameLen > 120) {
        $errors[] = 'Full name must be at most 120 characters';
    } elseif (!preg_match('/\p{L}/u', $name)) {
        $errors[] = 'Full name must contain at least one letter';
    } elseif (!lgueIsPersonOrOfficeName($name)) {
        $errors[] = 'Full name must be a proper name (letters only), not random text';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    } else {
        $local = strstr($email, '@', true);
        if ($local === false || $local === '' || !preg_match('/[a-zA-Z\p{L}]/u', $local)) {
            $errors[] = 'Email cannot be numbers-only before @ — include letters.';
        }
    }

    if ($username === '') {
        $errors[] = 'Username is required';
    } elseif (strlen($username) > 64 || strlen($username) < 3) {
        $errors[] = 'Username must be 3–64 characters';
    } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,63}$/', $username)) {
        $errors[] = 'Username must start with a letter and use only letters, numbers, and underscores';
    }

    if ($password === '' || !lgueIsStrongPassword($password)) {
        $errors[] = 'Password must be at least 8 characters and include 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match';
    }
    if ($officeId <= 0) {
        $errors[] = 'Please select your department / office';
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'name' => $name,
        'email' => $email,
        'username' => $username,
        'password' => $password,
        'office_id' => $officeId,
    ];
}

function registrationEmailHtml(string $code, string $name): string
{
    $safeName = htmlspecialchars($name !== '' ? $name : 'User', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration Verification Code</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ee;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;color:#1c1917;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f1ee;padding:28px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #ece8e4;">
          <tr>
            <td style="background:#7a1515;padding:22px 28px;text-align:center;">
              <img src="cid:lumban-logo" width="72" height="72" alt="Municipality of Lumban" style="display:block;margin:0 auto 10px;border-radius:50%;border:2px solid #f3d27a;object-fit:cover;">
              <div style="color:#fff;font-size:13px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;">Municipality of Lumban</div>
              <div style="color:#f3d27a;font-size:15px;font-weight:700;margin-top:4px;">Property Inventory System</div>
            </td>
          </tr>
          <tr>
            <td style="padding:32px 28px 12px;">
              <p style="margin:0 0 12px;font-size:16px;">Dear {$safeName},</p>
              <p style="margin:0 0 20px;font-size:14px;line-height:1.55;color:#44403c;">
                Use this code to finish creating your department account.
                The account is created only after this code is entered. Valid for <strong>5 minutes only</strong>.
              </p>
              <div style="background:#faf5f5;border:1px dashed #b22222;border-radius:12px;padding:18px;text-align:center;margin:0 0 20px;">
                <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#7a1515;font-weight:700;margin-bottom:8px;">Registration code</div>
                <div style="font-size:32px;letter-spacing:.28em;font-weight:800;color:#7a1515;font-family:Consolas,'Courier New',monospace;">{$safeCode}</div>
                <div style="margin-top:10px;font-size:13px;color:#9a3412;font-weight:600;">Expires in 5 minutes</div>
              </div>
              <p style="margin:0;font-size:13px;line-height:1.55;color:#44403c;">
                <strong>Security notice:</strong> Do not share this code. If you did not start a registration, ignore this email.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 28px 28px;border-top:1px solid #ece8e4;">
              <p style="margin:0;font-size:11px;color:#78716c;line-height:1.5;text-align:center;">
                General Services Office · Municipality of Lumban, Laguna<br>
                Official internal system · &copy; {$year}
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function registrationStart(PDO $conn, array $in): array
{
    registrationEnsureSchema($conn);
    $v = registrationValidateInput($in);
    if (!$v['ok']) {
        return ['success' => false, 'message' => implode('. ', $v['errors'])];
    }

    if (!lgueMailConfigured()) {
        return ['success' => false, 'message' => 'Email is not configured. Ask an administrator to set mail settings before new accounts can be registered.'];
    }

    $daily = registrationDailyRow($conn, $v['email']);
    if (($daily['verify_attempts'] ?? 0) >= 5 || ($daily['request_attempts'] ?? 0) >= 5) {
        return ['success' => false, 'message' => passwordResetLockedMessage(), 'locked' => true];
    }

    $chkOffice = $conn->prepare('SELECT id, code, name FROM offices WHERE id = ?');
    $chkOffice->execute([$v['office_id']]);
    $officeRow = $chkOffice->fetch(PDO::FETCH_ASSOC);
    if (!$officeRow) {
        return ['success' => false, 'message' => 'Invalid department selected'];
    }
    if (User::isGsoOffice($officeRow)) {
        return ['success' => false, 'message' => 'GSO accounts are created by an administrator in Settings. Choose another department.'];
    }

    $userModel = new User();
    if ($userModel->officeAtAccountLimit($v['office_id'])) {
        return ['success' => false, 'message' => 'This department has reached the maximum limit of 3 user accounts.'];
    }

    $taken = $conn->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
    $taken->execute([$v['email']]);
    if ($taken->fetch()) {
        return ['success' => false, 'message' => 'This email is already registered. Sign in or use Forgot password.'];
    }
    $takenU = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $takenU->execute([$v['username']]);
    if ($takenU->fetch()) {
        return ['success' => false, 'message' => 'Username already exists'];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $now = passwordResetNow();
    $expires = $now->modify('+5 minutes');
    $conn->prepare('UPDATE registration_codes SET used_at = ? WHERE email = ? AND used_at IS NULL')
        ->execute([$now->format('Y-m-d H:i:s'), $v['email']]);

    $conn->prepare(
        'INSERT INTO registration_codes (email, username, full_name, password_hash, office_id, code_hash, expires_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $v['email'],
        $v['username'],
        $v['name'],
        password_hash($v['password'], PASSWORD_DEFAULT),
        $v['office_id'],
        password_hash($code, PASSWORD_DEFAULT),
        $expires->format('Y-m-d H:i:s'),
        $now->format('Y-m-d H:i:s'),
    ]);

    registrationBump($conn, $v['email'], 'request_attempts');

    $html = registrationEmailHtml($code, $v['name']);
    $text = "Your Lumban Property Inventory registration code is {$code}. It is valid for 5 minutes. Your account is created only after you enter this code.";
    $logo = dirname(__DIR__) . '/public/assets/logo-lumban.jpg';
    $sent = lgueSendMail(
        $v['email'],
        'Registration Verification Code – Lumban System',
        $html,
        $text,
        [['path' => $logo, 'cid' => 'lumban-logo', 'name' => 'lumban-logo.jpg']]
    );
    if (!$sent['success']) {
        return ['success' => false, 'message' => $sent['message']];
    }

    $_SESSION['reg_verify_email'] = $v['email'];
    $_SESSION['reg_verify_expires'] = $expires->getTimestamp();

    return [
        'success' => true,
        'message' => 'A 6-digit code was sent to your email. It expires in 5 minutes. The account is created only after you enter the code.',
        'expires_in' => 300,
        'email' => $v['email'],
    ];
}

function registrationVerify(PDO $conn, string $email, string $code): array
{
    registrationEnsureSchema($conn);
    $email = passwordResetNormalizeEmail($email);
    $code = preg_replace('/\D/', '', $code) ?? '';
    $daily = registrationDailyRow($conn, $email);
    if (($daily['verify_attempts'] ?? 0) >= 5) {
        return ['success' => false, 'message' => passwordResetLockedMessage(), 'locked' => true];
    }
    $verifyN = registrationBump($conn, $email, 'verify_attempts');
    $left = max(0, 5 - $verifyN);

    if (strlen($code) !== 6) {
        return ['success' => false, 'message' => 'Enter the 6-digit verification code.', 'remaining' => $left];
    }

    $st = $conn->prepare(
        'SELECT * FROM registration_codes WHERE email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $now = passwordResetNow();
    if (!$row) {
        return ['success' => false, 'message' => 'Invalid or expired verification code.', 'remaining' => $left];
    }
    $expires = new DateTimeImmutable((string) $row['expires_at'], passwordResetTimezone());
    if ($now > $expires) {
        return ['success' => false, 'message' => 'This code has expired. Request a new code.', 'expired' => true, 'remaining' => $left];
    }
    if (!password_verify($code, (string) $row['code_hash'])) {
        $msg = $left === 0
            ? passwordResetLockedMessage()
            : 'Incorrect verification code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' remaining today.';

        return ['success' => false, 'message' => $msg, 'remaining' => $left, 'locked' => $left === 0];
    }

    $officeId = (int) $row['office_id'];
    $offSt = $conn->prepare('SELECT id, code, name FROM offices WHERE id = ?');
    $offSt->execute([$officeId]);
    $officeRow = $offSt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (User::isGsoOffice($officeRow)) {
        return ['success' => false, 'message' => 'GSO accounts are created by an administrator in Settings. Choose another department.'];
    }

    $userModel = new User();
    $created = $userModel->insertVerifiedStaff(
        (string) $row['full_name'],
        (string) $row['email'],
        (string) $row['username'],
        (string) $row['password_hash'],
        $officeId
    );
    if (!$created['success']) {
        return $created;
    }

    $conn->prepare('UPDATE registration_codes SET used_at = ? WHERE id = ?')
        ->execute([$now->format('Y-m-d H:i:s'), (int) $row['id']]);
    unset($_SESSION['reg_verify_email'], $_SESSION['reg_verify_expires']);

    try {
        if (!function_exists('activityLog')) {
            require_once __DIR__ . '/audit_log.php';
        }
        activityLog($conn, 'auth', 'Registered', [
            'user_id' => isset($created['user_id']) ? (int) $created['user_id'] : null,
            'changed_by' => (string) $row['full_name'],
            'office_id' => (int) $row['office_id'],
            'entity_type' => 'user',
            'entity_id' => isset($created['user_id']) ? (int) $created['user_id'] : 0,
            'entity_label' => (string) $row['email'],
            'new_value' => 'Department user · ' . (string) $row['username'],
        ]);
    } catch (Throwable $e) {
        // Account is already created.
    }

    return [
        'success' => true,
        'message' => 'Email verified. Your account is ready. You can now sign in.',
    ];
}
