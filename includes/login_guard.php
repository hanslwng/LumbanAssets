<?php

/**
 * Local login throttle: 5 failed attempts per email+IP in 15 minutes.
 * Files live under storage/ (already denied from the web).
 */

function loginGuardDir(): string
{
    $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'login-throttle';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    return $dir;
}

function loginGuardKey(string $email): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    return hash('sha256', strtolower(trim($email)) . '|' . $ip);
}

function loginGuardPath(string $email): string
{
    return loginGuardDir() . DIRECTORY_SEPARATOR . loginGuardKey($email) . '.json';
}

function loginGuardRead(string $email): array
{
    $path = loginGuardPath($email);
    if (!is_readable($path)) {
        return ['fails' => 0, 'first' => time()];
    }
    $raw = file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return ['fails' => 0, 'first' => time()];
    }
    $window = 15 * 60;
    $first = (int) ($data['first'] ?? time());
    if ((time() - $first) > $window) {
        return ['fails' => 0, 'first' => time()];
    }

    return [
        'fails' => (int) ($data['fails'] ?? 0),
        'first' => $first,
    ];
}

function loginGuardWrite(string $email, array $data): void
{
    file_put_contents(loginGuardPath($email), json_encode($data), LOCK_EX);
}

function loginGuardLocked(string $email): bool
{
    return loginGuardRead($email)['fails'] >= 5;
}

function loginGuardLockedMessage(): string
{
    return 'Too many failed sign-in attempts. Try again in 15 minutes.';
}

function loginGuardFail(string $email): void
{
    $row = loginGuardRead($email);
    $row['fails'] = ((int) ($row['fails'] ?? 0)) + 1;
    if (empty($row['first'])) {
        $row['first'] = time();
    }
    loginGuardWrite($email, $row);
}

function loginGuardClear(string $email): void
{
    $path = loginGuardPath($email);
    if (is_file($path)) {
        @unlink($path);
    }
}
