<?php
/**
 * Application router — front controller routes.
 */

$root = dirname(__DIR__);
require_once $root . '/includes/session.php';
lgueStartSession();

require_once $root . '/bootstrap.php';

// Define VIEW_PATH constant
if (!defined('VIEW_PATH')) {
    define('VIEW_PATH', $root . '/app/views');
}

// Load models
require_once $root . '/app/models/User.php';

// Load controllers
require_once $root . '/app/controllers/AuthController.php';
require_once $root . '/app/helpers/PermissionHelper.php';

// Get the request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string and application base path prefix
$requestUri = strtok($requestUri, '?');
$basePath = defined('LGUE_WEB_BASE') ? rtrim((string) LGUE_WEB_BASE, '/') : '';
if ($basePath !== '') {
    if ($requestUri === $basePath || ($requestUri !== '' && str_starts_with($requestUri, $basePath . '/'))) {
        $requestUri = substr($requestUri, strlen($basePath));
        if ($requestUri === '') {
            $requestUri = '/';
        }
    }
}

// Remove trailing slash
$requestUri = rtrim($requestUri, '/');

// If empty, set to home
if (empty($requestUri)) {
    $requestUri = '/';
}

// Route handling
switch ($requestUri) {
    // Auth routes
    case '/login':
        $controller = new AuthController();
        if ($requestMethod === 'POST') {
            $controller->login();
        } else {
            $controller->showLogin();
        }
        break;

    case '/auth/login':
        $controller = new AuthController();
        $controller->login();
        break;

    case '/auth/register':
    case '/register':
        $controller = new AuthController();
        if ($requestMethod === 'POST') {
            $controller->register();
        } else {
            header('Location: ' . lgue_url('login'));
            exit;
        }
        break;

    case '/auth/register-start':
        $controller = new AuthController();
        $controller->registerStart();
        break;

    case '/auth/register-verify':
        $controller = new AuthController();
        $controller->registerVerify();
        break;

    case '/logout':
    case '/auth/logout':
        $controller = new AuthController();
        $controller->logout();
        break;

    case '/auth/forgot-request':
        $controller = new AuthController();
        $controller->forgotRequest();
        break;

    case '/auth/forgot-verify':
        $controller = new AuthController();
        $controller->forgotVerify();
        break;

    case '/auth/forgot-reset':
        $controller = new AuthController();
        $controller->forgotReset();
        break;

    // Dashboard (home) — admins only; departmental users use /staff
    case '/':
    case '/dashboard':
    case '/index.php':
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . lgue_url('login'));
            exit;
        }
        if (!PermissionHelper::isAdmin()) {
            header('Location: ' . lgue_url('staff'));
            exit;
        }
        require_once __DIR__ . '/index.php';
        break;

    case '/staff':
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . lgue_url('login'));
            exit;
        }
        if (PermissionHelper::isAdmin()) {
            header('Location: ' . lgue_url('/'));
            exit;
        }
        require_once __DIR__ . '/staff_portal.php';
        break;

    // 404 Not Found
    default:
        http_response_code(404);
        $homeHref = htmlspecialchars(lgue_url('/'), ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html>
<html>
<head>
    <title>404 - Not Found</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #B22222, #8B0000);
            color: white;
        }
        .container {
            text-align: center;
        }
        h1 {
            font-size: 72px;
            margin: 0;
        }
        p {
            font-size: 24px;
        }
        a {
            color: #FFD700;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <p>Page Not Found</p>
        <a href="' . $homeHref . '">Go to Dashboard</a>
    </div>
</body>
</html>';
        break;
}
