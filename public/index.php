<?php
/**
 * Application Entry Point
 * 
 * All requests are routed through this file
 */

// Load configuration
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Load models
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/Office.php';
require_once APP_PATH . '/models/Item.php';
require_once APP_PATH . '/models/Issuance.php';
require_once APP_PATH . '/models/ReturnModel.php';

// Load controllers
require_once APP_PATH . '/controllers/AuthController.php';
require_once APP_PATH . '/controllers/DashboardController.php';
require_once APP_PATH . '/controllers/ItemController.php';
require_once APP_PATH . '/controllers/IssuanceController.php';
require_once APP_PATH . '/controllers/ReturnController.php';
require_once APP_PATH . '/controllers/ReportController.php';
require_once APP_PATH . '/controllers/ForecastController.php';

// Load routes
require_once BASE_PATH . '/routes/web.php';
