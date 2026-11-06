<?php
/**
 * Load KEY=value pairs from a project-root .env into getenv()/$_ENV.
 * Does not overwrite variables already set in the environment.
 */
function lgueLoadEnvFile(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $file = $path ?? (dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
    if (!is_readable($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function lgueEnv(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }

    return (string) $v;
}
