<?php
/**
 * Outbound mail. SMTP credentials come from environment variables only.
 */

require_once __DIR__ . '/env.php';

function lgueMailConfigured(): bool
{
    $driver = strtolower(lgueEnv('MAIL_DRIVER', 'smtp'));
    if ($driver === 'log') {
        return true;
    }

    return lgueEnv('MAIL_HOST') !== '' && lgueEnv('MAIL_USERNAME') !== '' && lgueEnv('MAIL_PASSWORD') !== '';
}

/**
 * @param array<int, array{path:string,cid:string,name?:string}> $embeds
 */
function lgueSendMail(string $to, string $subject, string $html, string $text = '', array $embeds = []): array
{
    lgueLoadEnvFile();
    $driver = strtolower(lgueEnv('MAIL_DRIVER', 'smtp'));

    if ($driver === 'log') {
        $dir = dirname(__DIR__) . '/storage/mail-outbox';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $safe = preg_replace('/[^a-z0-9._-]+/i', '_', $to) ?: 'mail';
        $file = $dir . '/' . date('Ymd-His') . '-' . $safe . '.html';
        $ok = @file_put_contents($file, $html) !== false;

        return $ok
            ? ['success' => true, 'message' => 'Verification email queued']
            : ['success' => false, 'message' => 'Could not write local mail outbox'];
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return ['success' => false, 'message' => 'Mailer library is not installed'];
    }
    require_once $autoload;

    if (!lgueMailConfigured()) {
        return ['success' => false, 'message' => 'Email is not configured. Ask an administrator to set MAIL_* in the server environment.'];
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = lgueEnv('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = lgueEnv('MAIL_USERNAME');
        $mail->Password = preg_replace('/\s+/', '', lgueEnv('MAIL_PASSWORD'));
        $enc = strtolower(lgueEnv('MAIL_ENCRYPTION', 'tls'));
        $mail->SMTPSecure = $enc === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) lgueEnv('MAIL_PORT', $enc === 'ssl' ? '465' : '587');
        $mail->CharSet = 'UTF-8';

        $from = lgueEnv('MAIL_FROM_ADDRESS', lgueEnv('MAIL_USERNAME'));
        $fromName = lgueEnv('MAIL_FROM_NAME', 'Lumban Property Inventory System');
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text !== '' ? $text : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

        foreach ($embeds as $embed) {
            if (!empty($embed['path']) && is_file($embed['path'])) {
                $mail->addEmbeddedImage(
                    $embed['path'],
                    $embed['cid'],
                    $embed['name'] ?? basename($embed['path'])
                );
            }
        }

        $mail->send();

        return ['success' => true, 'message' => 'Verification email sent'];
    } catch (Throwable $e) {
        error_log('Mail send failed: ' . $e->getMessage());

        return ['success' => false, 'message' => 'Could not send the verification email. Try again later.'];
    }
}
