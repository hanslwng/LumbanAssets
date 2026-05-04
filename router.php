<?php
/**
 * Simple Router
 * Handles all application routes
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration
require_once 'config.php';

// Define VIEW_PATH constant
if (!defined('VIEW_PATH')) {
    define('VIEW_PATH', __DIR__ . '/app/views');
}

// Load models
require_once 'app/models/User.php';

// Load controllers
require_once 'app/controllers/AuthController.php';

// Get the request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string and base path
$requestUri = strtok($requestUri, '?');
$requestUri = str_replace('/lgu_invent', '', $requestUri);

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
            header('Location: /lgu_invent/login');
            exit;
        }
        break;
    
    case '/logout':
    case '/auth/logout':
        $controller = new AuthController();
        $controller->logout();
        break;
    
    // Dashboard (home)
    case '/':
    case '/dashboard':
    case '/index.php':
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Location: /lgu_invent/login');
            exit;
        }
        require_once 'index.php';
        break;
    
    // 404 Not Found
    default:
        http_response_code(404);
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
        <a href="/lgu_invent/">Go to Dashboard</a>
    </div>
</body>
</html>';
        break;
}
