<?php
/**
 * Forgot-password codes: 6-digit, 5-minute expiry, 5 verify attempts / day.
 * Codes are stored hashed. Passwords are never stored in this table.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/mailer.php';

function lgueIsStrongPassword(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }

    return true;
}

function passwordResetTimezone(): DateTimeZone
{
    return new DateTimeZone('Asia/Manila');
}

function passwordResetNow(): DateTimeImmutable
{
    return new DateTimeImmutable('now', passwordResetTimezone());
}

function passwordResetNormalizeEmail(string $email): string
{
    return strtolower(trim($email));
}

function passwordResetEnsureSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS password_reset_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_prc_email (email),
            INDEX idx_prc_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS password_reset_daily (
            email VARCHAR(255) NOT NULL,
            attempt_date DATE NOT NULL,
            verify_attempts INT NOT NULL DEFAULT 0,
            request_attempts INT NOT NULL DEFAULT 0,
            PRIMARY KEY (email, attempt_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function passwordResetDailyRow(PDO $conn, string $email): array
{
    $date = passwordResetNow()->format('Y-m-d');
    $st = $conn->prepare(
        'INSERT INTO password_reset_daily (email, attempt_date, verify_attempts, request_attempts)
         VALUES (?, ?, 0, 0)
         ON DUPLICATE KEY UPDATE email = email'
    );
    $st->execute([$email, $date]);
    $st = $conn->prepare(
        'SELECT verify_attempts, request_attempts FROM password_reset_daily WHERE email = ? AND attempt_date = ?'
    );
    $st->execute([$email, $date]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'verify_attempts' => (int) ($row['verify_attempts'] ?? 0),
        'request_attempts' => (int) ($row['request_attempts'] ?? 0),
        'attempt_date' => $date,
    ];
}

function passwordResetBump(PDO $conn, string $email, string $column): int
{
    $allowed = ['verify_attempts' => true, 'request_attempts' => true];
    if (!isset($allowed[$column])) {
        return 0;
    }
    $date = passwordResetNow()->format('Y-m-d');
    $conn->prepare(
        "INSERT INTO password_reset_daily (email, attempt_date, {$column})
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE {$column} = {$column} + 1"
    )->execute([$email, $date]);
    $st = $conn->prepare("SELECT {$column} FROM password_reset_daily WHERE email = ? AND attempt_date = ?");
    $st->execute([$email, $date]);

    return (int) $st->fetchColumn();
}

function passwordResetLocked(array $daily): bool
{
    return ($daily['verify_attempts'] ?? 0) >= 5;
}

function passwordResetLockedMessage(): string
{
    return 'You have reached the maximum of 5 verification attempts for today. Try again tomorrow.';
}

function passwordResetSendCode(PDO $conn, string $email): array
{
    passwordResetEnsureSchema($conn);
    $email = passwordResetNormalizeEmail($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Enter a valid email address.'];
    }

    $daily = passwordResetDailyRow($conn, $email);
    if (passwordResetLocked($daily)) {
        return ['success' => false, 'message' => passwordResetLockedMessage(), 'locked' => true];
    }
    if (($daily['request_attempts'] ?? 0) >= 5) {
        return ['success' => false, 'message' => passwordResetLockedMessage(), 'locked' => true];
    }

    $find = $conn->prepare(
        'SELECT id, email, full_name, is_active FROM users WHERE LOWER(email) = ? LIMIT 1'
    );
    $find->execute([$email]);
    $user = $find->fetch(PDO::FETCH_ASSOC);
    $generic = 'If this email is registered, a 6-digit verification code was sent. It is valid for 5 minutes.';

    if (!$user || empty($user['is_active'])) {
        passwordResetBump($conn, $email, 'request_attempts');

        return ['success' => true, 'message' => $generic, 'expires_in' => 300];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $now = passwordResetNow();
    $expires = $now->modify('+5 minutes');

    $conn->prepare(
        'UPDATE password_reset_codes SET used_at = ? WHERE email = ? AND used_at IS NULL'
    )->execute([$now->format('Y-m-d H:i:s'), $email]);

    $conn->prepare(
        'INSERT INTO password_reset_codes (user_id, email, code_hash, expires_at, created_at)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        (int) $user['id'],
        $email,
        $hash,
        $expires->format('Y-m-d H:i:s'),
        $now->format('Y-m-d H:i:s'),
    ]);

    passwordResetBump($conn, $email, 'request_attempts');

    $html = passwordResetEmailHtml($code, (string) ($user['full_name'] ?? ''));
    $text = "Your Lumban Property Inventory verification code is {$code}. It is valid for 5 minutes only. Do not share this code with anyone.";
    $logo = dirname(__DIR__) . '/public/assets/logo-lumban.jpg';
    $sent = lgueSendMail(
        $email,
        'Password Reset Verification Code – Lumban System',
        $html,
        $text,
        [['path' => $logo, 'cid' => 'lumban-logo', 'name' => 'lumban-logo.jpg']]
    );

    if (!$sent['success']) {
        return ['success' => false, 'message' => $sent['message']];
    }

    $_SESSION['pw_reset_email'] = $email;
    $_SESSION['pw_reset_expires'] = $expires->getTimestamp();
    unset($_SESSION['pw_reset_user_id'], $_SESSION['pw_reset_until']);

    return ['success' => true, 'message' => $generic, 'expires_in' => 300];
}

function passwordResetVerifyCode(PDO $conn, string $email, string $code): array
{
    passwordResetEnsureSchema($conn);
    $email = passwordResetNormalizeEmail($email);
    $code = preg_replace('/\D/', '', $code) ?? '';
    $daily = passwordResetDailyRow($conn, $email);
    if (passwordResetLocked($daily)) {
        return ['success' => false, 'message' => passwordResetLockedMessage(), 'locked' => true];
    }

    $verifyN = passwordResetBump($conn, $email, 'verify_attempts');
    $left = max(0, 5 - $verifyN);

    if (strlen($code) !== 6) {
        return ['success' => false, 'message' => 'Enter the 6-digit verification code.', 'remaining' => $left];
    }

    $st = $conn->prepare(
        'SELECT id, user_id, code_hash, expires_at FROM password_reset_codes
         WHERE email = ? AND used_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $now = passwordResetNow();

    if (!$row) {
        $msg = $left === 0 ? passwordResetLockedMessage() : 'Invalid or expired verification code.';

        return ['success' => false, 'message' => $msg, 'remaining' => $left, 'locked' => $left === 0];
    }

    $expires = new DateTimeImmutable((string) $row['expires_at'], passwordResetTimezone());
    if ($now > $expires) {
        return [
            'success' => false,
            'message' => 'This code has expired. Request a new code.',
            'expired' => true,
            'remaining' => $left,
        ];
    }

    if (!password_verify($code, (string) $row['code_hash'])) {
        $msg = $left === 0
            ? passwordResetLockedMessage()
            : 'Incorrect verification code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' remaining today.';

        return ['success' => false, 'message' => $msg, 'remaining' => $left, 'locked' => $left === 0];
    }

    $conn->prepare('UPDATE password_reset_codes SET used_at = ? WHERE id = ?')
        ->execute([$now->format('Y-m-d H:i:s'), (int) $row['id']]);

    $_SESSION['pw_reset_email'] = $email;
    $_SESSION['pw_reset_user_id'] = (int) $row['user_id'];
    $_SESSION['pw_reset_until'] = time() + 600;

    return ['success' => true, 'message' => 'Code verified. Create your new password.', 'remaining' => $left];
}

function passwordResetChangePassword(PDO $conn, string $password, string $confirm): array
{
    $userId = (int) ($_SESSION['pw_reset_user_id'] ?? 0);
    $until = (int) ($_SESSION['pw_reset_until'] ?? 0);
    if ($userId <= 0 || $until < time()) {
        return ['success' => false, 'message' => 'Your reset session expired. Request a new verification code.'];
    }
    if ($password !== $confirm) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    if (!lgueIsStrongPassword($password)) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters and include 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.'];
    }

    $ok = (new User())->updatePassword($userId, $password);
    if (!$ok) {
        return ['success' => false, 'message' => 'Could not update the password. Try again.'];
    }

    $email = passwordResetNormalizeEmail((string) ($_SESSION['pw_reset_email'] ?? ''));
    if ($email !== '') {
        $conn->prepare('UPDATE password_reset_codes SET used_at = COALESCE(used_at, NOW()) WHERE email = ? AND used_at IS NULL')
            ->execute([$email]);
    }

    unset($_SESSION['pw_reset_email'], $_SESSION['pw_reset_user_id'], $_SESSION['pw_reset_until'], $_SESSION['pw_reset_expires']);

    try {
        if (!function_exists('activityLog')) {
            require_once __DIR__ . '/audit_log.php';
        }
        $who = '';
        $officeId = null;
        $st = $conn->prepare('SELECT full_name, office_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $urow = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $who = (string) ($urow['full_name'] ?? '');
        $officeId = isset($urow['office_id']) ? (int) $urow['office_id'] : null;
        activityLog($conn, 'auth', 'Password reset', [
            'user_id' => $userId,
            'changed_by' => $who !== '' ? $who : 'User',
            'office_id' => $officeId,
            'entity_type' => 'user',
            'entity_id' => $userId,
            'entity_label' => $email,
        ]);
    } catch (Throwable $e) {
        // Password is already changed.
    }

    return ['success' => true, 'message' => 'Your password has been changed. You can now sign in with the new password.'];
}

function passwordResetEmailHtml(string $code, string $name): string
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
  <title>Password Reset Verification Code</title>
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
                We received a request to reset the password for your Lumban Property Inventory account.
                Use the verification code below to continue. This code is valid for <strong>5 minutes only</strong>.
              </p>
              <div style="background:#faf5f5;border:1px dashed #b22222;border-radius:12px;padding:18px;text-align:center;margin:0 0 20px;">
                <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#7a1515;font-weight:700;margin-bottom:8px;">Verification code</div>
                <div style="font-size:32px;letter-spacing:.28em;font-weight:800;color:#7a1515;font-family:Consolas,'Courier New',monospace;">{$safeCode}</div>
                <div style="margin-top:10px;font-size:13px;color:#9a3412;font-weight:600;">Expires in 5 minutes</div>
              </div>
              <p style="margin:0 0 8px;font-size:13px;line-height:1.55;color:#44403c;">
                <strong>Security notice:</strong> Do not share this verification code with anyone.
                Lumban GSO staff will never ask you for this code by phone, chat, or email.
              </p>
              <p style="margin:0;font-size:13px;line-height:1.55;color:#57534e;">
                If you did not request a password reset, you can ignore this message. Your current password will stay unchanged.
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
