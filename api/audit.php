<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/audit_log.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    http_response_code(405);
    jsonResponse(['success' => false, 'message' => 'Audit trail is read-only']);
}

PermissionHelper::require('view');

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed']);
}

auditLogEnsureSchema($conn);

$itemId = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 120;
if ($limit < 1) {
    $limit = 120;
}
if ($limit > 300) {
    $limit = 300;
}

$sessionOffice = PermissionHelper::officeId();
$isGlobalAdmin = PermissionHelper::isUnscopedAdmin();
$requestedRaw = isset($_GET['office_id']) ? trim((string) $_GET['office_id']) : '';
$adminActorScope = $isGlobalAdmin && $requestedRaw === 'admin';
$allScope = $isGlobalAdmin && ($requestedRaw === '' || $requestedRaw === 'all');
$requestedOffice = ctype_digit($requestedRaw) ? (int) $requestedRaw : 0;

// Anyone assigned to a department — including an Administrator registered
// under that office — may only see that office. Client office_id is ignored.
$officeScope = 0;
if (!$isGlobalAdmin) {
    $officeScope = $sessionOffice > 0 ? $sessionOffice : -1;
} elseif ($itemId <= 0 && !$adminActorScope) {
    $officeScope = $requestedOffice;
}

if ($itemId > 0 && !$isGlobalAdmin) {
    $chk = $conn->prepare('SELECT office_id FROM property_items WHERE id = ? LIMIT 1');
    $chk->execute([$itemId]);
    $itemOffice = (int) ($chk->fetchColumn() ?: 0);
    if ($officeScope <= 0 || $itemOffice !== $officeScope) {
        jsonResponse(['success' => true, 'data' => [], 'office_id' => $officeScope > 0 ? $officeScope : 0]);
    }
}

$sql = "SELECT a.id, a.user_id, a.changed_by, a.office_id, a.module, a.action,
               a.entity_type, a.entity_id, a.entity_label, a.old_value, a.new_value,
               a.created_at AS changed_at,
               CASE WHEN a.entity_type = 'item' THEN a.entity_id ELSE 0 END AS property_item_id,
               COALESCE(NULLIF(a.entity_label, ''), p.property_number) AS property_number,
               p.description, p.article,
               COALESCE(o.name, o2.name, o3.name) AS office_name
        FROM user_activity a
        LEFT JOIN property_items p ON a.entity_type = 'item' AND p.id = a.entity_id
        LEFT JOIN proc_pr pr ON a.entity_type = 'pr' AND pr.id = a.entity_id
        LEFT JOIN users actor ON actor.id = a.user_id
        LEFT JOIN offices o ON o.id = a.office_id
        LEFT JOIN offices o2 ON o2.id = p.office_id
        LEFT JOIN offices o3 ON o3.id = pr.office_id";
$params = [];
$where = [];
if ($itemId > 0) {
    $where[] = "a.entity_type = 'item' AND a.entity_id = ?";
    $params[] = $itemId;
}

$belongsToOffice = '(
    (p.id IS NULL OR p.office_id = ?)
    AND (pr.id IS NULL OR pr.office_id = ?)
    AND COALESCE(p.office_id, pr.office_id, a.office_id, actor.office_id) = ?
)';

if ($itemId <= 0) {
    if ($adminActorScope) {
        // GSO admin work only — not department users’ sign-ins or account changes.
        $where[] = "a.module IN ('auth', 'settings', 'reports', 'offices')
            AND (a.entity_type IS NULL OR a.entity_type NOT IN ('item', 'pr', 'issuance'))
            AND p.id IS NULL AND pr.id IS NULL
            AND actor.id IS NOT NULL
            AND (actor.office_id IS NULL OR actor.office_id = 0)
            AND LOWER(actor.role) = 'admin'";
    } elseif ($allScope) {
        // All operational work. Hide department users’ personal sign-in / account edits.
        $where[] = "NOT (
            a.module = 'auth'
            AND actor.id IS NOT NULL
            AND actor.office_id IS NOT NULL
            AND actor.office_id <> 0
        )";
        $where[] = "NOT (
            a.module = 'settings'
            AND a.action IN ('Preferences saved', 'Profile updated', 'Password changed', 'Password reset')
            AND actor.id IS NOT NULL
            AND actor.office_id IS NOT NULL
            AND actor.office_id <> 0
        )";
    } elseif ($officeScope <= 0) {
        jsonResponse([
            'success' => true,
            'data' => [],
            'office_required' => $isGlobalAdmin,
            'office_id' => 0,
        ]);
    } else {
        $where[] = $belongsToOffice;
        $params[] = $officeScope;
        $params[] = $officeScope;
        $params[] = $officeScope;
        if ($isGlobalAdmin) {
            // Oversight of that office’s property work, not staff logins or personal account edits.
            $where[] = "a.module <> 'auth'
                AND NOT (a.module = 'settings' AND a.action IN ('Preferences saved', 'Profile updated', 'Password changed', 'Password reset'))";
        }
    }
} elseif (!$isGlobalAdmin) {
    $where[] = $belongsToOffice;
    $params[] = $officeScope;
    $params[] = $officeScope;
    $params[] = $officeScope;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where[] = '(a.changed_by LIKE ?
        OR a.module LIKE ?
        OR a.action LIKE ?
        OR a.entity_label LIKE ?
        OR a.old_value LIKE ?
        OR a.new_value LIKE ?
        OR COALESCE(p.property_number, \'\') LIKE ?
        OR COALESCE(p.description, \'\') LIKE ?
        OR COALESCE(p.article, \'\') LIKE ?
        OR COALESCE(o.name, o2.name, o3.name, \'\') LIKE ?)';
    for ($i = 0; $i < 10; $i++) {
        $params[] = $like;
    }
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . $limit;

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Could not load activity']);
}

jsonResponse([
    'success' => true,
    'data' => $rows,
    'office_id' => $officeScope > 0 ? $officeScope : 0,
    'scope' => $adminActorScope ? 'admin' : ($allScope ? 'all' : 'office'),
    'department_scoped' => !$isGlobalAdmin,
]);
