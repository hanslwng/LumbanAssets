<?php

/**
 * Session cookie flags. Call before session_start().
 * Secure is only set when the request is already HTTPS (localhost HTTP stays usable).
 */
function lgueConfigureSessionCookie(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $https,
    ]);
}

function lgueStartSession(): void
{
    lgueConfigureSessionCookie();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
