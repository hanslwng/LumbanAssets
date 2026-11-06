<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/consumable_forecast.php';

@set_time_limit(120);

header('Content-Type: application/json');

PermissionHelper::require('view');

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
if ($method === 'POST') {
    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($raw) && !empty($raw['action'])) {
        $action = (string) $raw['action'];
    }
}

try {
    consumableEnsureSchema($conn);

    $officeScope = null;
    if (!PermissionHelper::isAdmin() && PermissionHelper::isDepartmentScoped()) {
        $oid = (int) PermissionHelper::officeId();
        if ($oid <= 0) {
            jsonResponse(['success' => true, 'data' => [
                'items' => [],
                'departments' => [],
                'summary' => ['tracked' => 0, 'reorder' => 0, 'sufficient' => 0, 'insufficient' => 0],
                'meta' => ['office_scoped' => true, 'error' => 'no_office'],
            ]]);
        }
        $officeScope = $oid;
    }

    // Rebuild is a no-op cache refresh for this engine (computed live).
    if ($action === 'rebuild') {
        PermissionHelper::require('edit');
    }

    $data = consumableForecastBuild($conn, $officeScope);
    jsonResponse([
        'success' => true,
        'message' => $action === 'rebuild' ? 'Consumables forecast refreshed.' : null,
        'data' => $data,
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Forecast failed: ' . $e->getMessage()]);
}
