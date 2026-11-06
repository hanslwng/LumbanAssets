<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/audit_log.php';
require_once dirname(__DIR__) . '/includes/pr_inventory_groups.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';
require_once dirname(__DIR__) . '/includes/consumable_stock.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();
    consumableEnsureSchema($conn);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Database connection failed',
        'hint' => 'Import database/migrations/001_procurement_module.sql then retry',
    ]);
}

/**
 * @param array<string, mixed> $row
 * @return array{quantity: float, unit: string, description: string, est_unit_cost: float, est_line_total: float, article: ?string, item_group: ?string}
 */
function procParsePrLineRow(array $row, ?string $fallbackGroup): array
{
    $qty = floatval($row['quantity'] ?? 1);
    if ($qty <= 0) {
        $qty = 1;
    }
    $unit = sanitizeInput((string) ($row['unit'] ?? 'pc'));
    if ($unit === '') {
        $unit = 'pc';
    }
    $desc = sanitizeInput((string) ($row['description'] ?? ''));
    $estUnit = max(0, floatval($row['est_unit_cost'] ?? 0));
    $article = sanitizeInput((string) ($row['article'] ?? ''));
    $group = sanitizeInput((string) ($row['item_group'] ?? $fallbackGroup ?? ''));
    if ($group !== '') {
        $group = prInventoryNormalizeGroup($group);
    }

    return [
        'quantity' => $qty,
        'unit' => $unit,
        'description' => $desc,
        'est_unit_cost' => $estUnit,
        'est_line_total' => round($qty * $estUnit, 2),
        'article' => $article !== '' ? $article : null,
        'item_group' => $group !== '' ? $group : null,
    ];
}

/**
 * Reuse an existing inventory article in this office + section (Table → 3. Table).
 *
 * @param array{quantity: float, unit: string, description: string, est_unit_cost: float, est_line_total: float, article: ?string, item_group: ?string} $parsed
 * @return array{quantity: float, unit: string, description: string, est_unit_cost: float, est_line_total: float, article: ?string, item_group: ?string}
 */
function procAlignParsedArticle(PDO $conn, int $officeId, array $parsed): array
{
    $art = trim((string) ($parsed['article'] ?? ''));
    if ($art === '' || $officeId <= 0) {
        return $parsed;
    }
    $parsed['article'] = prResolveArticleLabel(
        $conn,
        $officeId,
        '',
        $parsed['item_group'] ?? null,
        $art
    );

    return $parsed;
}

function procurementEnsureCreatedByColumn(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $conn->query('SELECT created_by_user_id FROM proc_pr LIMIT 1');
    } catch (PDOException $e) {
        try {
            $conn->exec('ALTER TABLE proc_pr ADD COLUMN created_by_user_id INT NULL');
        } catch (PDOException $e2) {
            // Best-effort.
        }
    }
}

function procurementCurrentUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function procurementIsDraftLike(?string $status): bool
{
    $s = strtolower(trim((string) $status));

    return $s === 'draft' || $s === 'needs_revision';
}

/** Admin may see a draft only if they created it. Department drafts stay hidden until submit. */
function procurementAdminMaySeeDraft(array $pr): bool
{
    if (!PermissionHelper::isAdmin()) {
        return true;
    }
    if (strtolower((string) ($pr['status'] ?? '')) !== 'draft') {
        return true;
    }
    $uid = procurementCurrentUserId();
    $created = (int) ($pr['created_by_user_id'] ?? 0);

    return $uid > 0 && $created === $uid;
}

function procTableExists(PDO $conn): bool {
    try {
        $conn->query('SELECT 1 FROM proc_pr LIMIT 1');

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function procurementEnsureCanvassColumns(PDO $conn): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $conn->query('SELECT canvass_feedback, canvass_reviewed_by, canvass_reviewed_at FROM proc_pr LIMIT 1');
    } catch (PDOException $e) {
        try {
            $conn->exec('ALTER TABLE proc_pr ADD COLUMN canvass_feedback TEXT NULL');
        } catch (PDOException $e2) {
            // Column may already exist from a partial migration.
        }
        try {
            $conn->exec('ALTER TABLE proc_pr ADD COLUMN canvass_reviewed_by VARCHAR(255) NULL');
        } catch (PDOException $e2) {
        }
        try {
            $conn->exec('ALTER TABLE proc_pr ADD COLUMN canvass_reviewed_at DATETIME NULL');
        } catch (PDOException $e2) {
        }
    }
    try {
        $col = $conn->query("SHOW COLUMNS FROM proc_pr LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        $type = strtolower((string) ($col['Type'] ?? ''));
        if ($type !== '' && strpos($type, 'needs_revision') === false) {
            $conn->exec(
                "ALTER TABLE proc_pr MODIFY COLUMN status ENUM('draft','needs_revision','submitted','quoting','po_issued','completed') NOT NULL DEFAULT 'draft'"
            );
        }
        $conn->exec(
            "UPDATE proc_pr SET status='needs_revision' WHERE status='draft' AND canvass_feedback IS NOT NULL AND TRIM(canvass_feedback) <> ''"
        );
    } catch (PDOException $e) {
        // Best-effort; older installs without ALTER privilege keep the original enum.
    }
}

/**
 * Raise a dashboard notification. Admins always see it (Property Custodian
 * duties); when $officeId is set, staff of that department see it too, so
 * every department is notified about its own PR/PO/AIR activity.
 * Best-effort: never blocks or fails the procurement workflow.
 */
function procurementNotify(PDO $conn, string $type, string $title, string $message = '', ?int $relatedId = null, ?int $officeId = null): void {
    if (!function_exists('notificationsCreate')) {
        require_once dirname(__DIR__) . '/includes/notifications_helpers.php';
    }
    notificationsCreate($conn, $type, $title, $message, 'proc_pr', $relatedId, $officeId, true);
}

/**
 * @param array<string, mixed> $extra
 */
function procurementLog(PDO $conn, string $action, int $prId, array $extra = []): void
{
    $label = (string) ($extra['entity_label'] ?? '');
    $officeId = $extra['office_id'] ?? null;
    if ($prId > 0 && ($label === '' || $officeId === null)) {
        try {
            $st = $conn->prepare('SELECT pr_number, office_id FROM proc_pr WHERE id = ? LIMIT 1');
            $st->execute([$prId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            if ($label === '' && !empty($row['pr_number'])) {
                $label = (string) $row['pr_number'];
            }
            if ($officeId === null && isset($row['office_id'])) {
                $officeId = (int) $row['office_id'];
            }
        } catch (PDOException $e) {
            // Use what we have.
        }
    }
    $extra['entity_type'] = $extra['entity_type'] ?? 'pr';
    $extra['entity_id'] = $extra['entity_id'] ?? $prId;
    $extra['entity_label'] = $label !== '' ? $label : ('PR #' . $prId);
    $extra['office_id'] = $officeId;
    activityLog($conn, 'procurement', $action, $extra);
}

/**
 * Resolve the GSO (General Services Office) office_id so delivery
 * notifications always reach the Property Custodian's own office, even when
 * the requesting department is a different office. Best-effort: returns
 * null if it cannot be determined (notification simply won't be duplicated).
 */
function procurementGsoOfficeId(PDO $conn): ?int {
    static $cached = null;
    static $resolved = false;
    if ($resolved) {
        return $cached;
    }
    $resolved = true;
    try {
        $st = $conn->prepare("SELECT id FROM offices WHERE code = 'GSO' OR name LIKE '%General Services%' ORDER BY (code = 'GSO') DESC LIMIT 1");
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $cached = $row ? (int) $row['id'] : null;
    } catch (PDOException $e) {
        $cached = null;
    }

    return $cached;
}

/**
 * Admin-created PRs: requester is the GSO-Designate; approver is the municipal mayor.
 *
 * @return array{0: string, 1: ?string, 2: string, 3: ?string}
 */
function procurementApplyAdminPrSignatories(
    PDO $conn,
    int $officeId,
    string $requestedBy,
    ?string $requestedTitle,
    string $approvedBy,
    ?string $approvedTitle
): array {
    if (!PermissionHelper::isAdmin()) {
        return [$requestedBy, $requestedTitle, $approvedBy, $approvedTitle];
    }

    $gsoOfficer = defined('RPCPPE_ACCOUNTABLE_OFFICER') ? (string) RPCPPE_ACCOUNTABLE_OFFICER : 'RHONALIZA A. ALVIAR';
    if (function_exists('settingsGet')) {
        $saved = trim((string) settingsGet($conn, 'gso_officer', $gsoOfficer));
        if ($saved !== '') {
            $gsoOfficer = $saved;
        }
    }
    $gsoTitle = 'GSO - Designate';
    $mayorName = defined('RPCPPE_APPROVER_NAME') ? (string) RPCPPE_APPROVER_NAME : 'BELEN B. RAGA';
    $mayorTitle = defined('RPCPPE_APPROVER_DESIGNATION')
        ? (string) RPCPPE_APPROVER_DESIGNATION
        : 'Municipal Mayor';

    if ($requestedBy === '') {
        $requestedBy = $gsoOfficer;
    }
    $requestedTitle = $gsoTitle;
    if ($approvedBy === '') {
        $approvedBy = $mayorName;
    }
    if ($approvedTitle === null || $approvedTitle === '') {
        $approvedTitle = $mayorTitle;
    }

    return [$requestedBy, $requestedTitle, $approvedBy, $approvedTitle];
}

/**
 * Department PR: default requested name/designation from the logged-in user
 * and their office, plus mayor approve lines. Client values stay if provided.
 *
 * @return array{0: string, 1: string|null, 2: string, 3: string|null}
 */
function procurementApplyStaffPrDefaults(
    PDO $conn,
    string $requestedBy,
    ?string $requestedTitle,
    string $approvedBy,
    ?string $approvedTitle
): array {
    if (PermissionHelper::isAdmin()) {
        return [$requestedBy, $requestedTitle, $approvedBy, $approvedTitle];
    }

    $prefs = [];
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid > 0 && function_exists('settingsUserPrefs')) {
        $prefs = settingsUserPrefs($conn, $uid);
    }
    if ($requestedBy === '') {
        $prefName = trim((string) ($prefs['pr_printed_name'] ?? ''));
        $requestedBy = $prefName !== ''
            ? $prefName
            : trim((string) ($_SESSION['user_name'] ?? ''));
    }
    if ($requestedTitle === null || $requestedTitle === '') {
        $office = trim((string) ($_SESSION['user_office_name'] ?? ''));
        $requestedTitle = $office !== '' ? $office : trim((string) ($prefs['pr_designation'] ?? ''));
        if ($requestedTitle === '') {
            $requestedTitle = null;
        }
    }

    $mayorName = defined('RPCPPE_APPROVER_NAME') ? (string) RPCPPE_APPROVER_NAME : 'BELEN B. RAGA';
    $mayorTitle = defined('RPCPPE_APPROVER_DESIGNATION')
        ? (string) RPCPPE_APPROVER_DESIGNATION
        : 'Municipal Mayor';
    if (function_exists('settingsGet')) {
        $savedName = trim((string) settingsGet($conn, 'mayor_name', $mayorName));
        $savedTitle = trim((string) settingsGet($conn, 'mayor_designation', $mayorTitle));
        if ($savedName !== '') {
            $mayorName = $savedName;
        }
        if ($savedTitle !== '') {
            $mayorTitle = $savedTitle;
        }
    }
    if ($approvedBy === '') {
        $approvedBy = $mayorName;
    }
    if ($approvedTitle === null || $approvedTitle === '') {
        $approvedTitle = $mayorTitle;
    }

    return [$requestedBy, $requestedTitle, $approvedBy, $approvedTitle];
}

/**
 * Procurement scoping by user department.
 * Administrators see every office (department drafts stay hidden via list filters).
 *
 * @return array{scoped: bool, office_id?: int|null}
 */
function procurementUserDepartmentScope(): array {
    if (PermissionHelper::isAdmin()) {
        return ['scoped' => false];
    }
    $oid = PermissionHelper::officeId();

    return ['scoped' => true, 'office_id' => $oid];
}

/** When empty office for scoped user — block workflow with a consistent message */
function procurementRequireAssignedDepartment(): void {
    $s = procurementUserDepartmentScope();
    if (!$s['scoped']) {
        return;
    }
    if (($s['office_id'] ?? 0) <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Your account has no department on file. Ask an administrator to assign your department, or log out and register again.',
        ]);
    }
}

/**
 * @param int|null $recordOffice PR row office_id from database
 */
function procurementAssertDepartmentMatch(?int $recordOffice): void {
    $s = procurementUserDepartmentScope();
    if (!$s['scoped']) {
        return;
    }
    procurementRequireAssignedDepartment();
    $mine = (int) $s['office_id'];
    if ((int) $recordOffice !== $mine) {
        jsonResponse([
            'success' => false,
            'message' => 'You can only work on purchase requests for your assigned department.',
        ]);
    }
}

function procurementNormalizeNewPrOffice(int $requestedFromClient): int {
    $s = procurementUserDepartmentScope();
    if (!$s['scoped']) {
        return $requestedFromClient;
    }
    procurementRequireAssignedDepartment();
    $mine = (int) $s['office_id'];
    if ($requestedFromClient > 0 && $requestedFromClient !== $mine) {
        jsonResponse([
            'success' => false,
            'message' => 'Purchase requests must be created for your department only.',
        ]);
    }

    return $mine;
}

/**
 * Department (staff) users may only create/edit their own draft PR and submit it
 * for quoting — everything past that (quotations, PO, AIR) is admin/GSO-only.
 * Staff can still view those sections read-only via the normal GET/detail endpoint.
 */
function procurementAssertAdminOnlyAction(string $actionLabel): void {
    if (PermissionHelper::isAdmin()) {
        return;
    }
    jsonResponse([
        'success' => false,
        'message' => "Only an administrator can $actionLabel. Your department can create and submit purchase requests; quotations, purchase orders, and acceptance reports are handled by the GSO/Admin team.",
    ]);
}

function procurementEnsurePrAccess(PDO $conn, int $pr_id): void {
    $st = $conn->prepare('SELECT office_id FROM proc_pr WHERE id = ?');
    $st->execute([$pr_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Purchase Request not found']);
    }
    procurementAssertDepartmentMatch(isset($row['office_id']) ? (int) $row['office_id'] : null);
}

/** Accept YYYY-MM-DD or blank → NULL */
function procNormalizeDate(?string $v): ?string {
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

/** @param array<mixed>|null $j */
function procBodyJson(?array &$j): void {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        $j = [];

        return;
    }
    $dec = json_decode($raw, true);
    $j = is_array($dec) ? $dec : null;
}

/**
 * @return array<string, mixed>
 */
function procurementDetail(PDO $conn, int $prId): array {
    $st = $conn->prepare(
        'SELECT p.*, o.name AS office_name, o.code AS office_code FROM proc_pr p 
         JOIN offices o ON o.id = p.office_id WHERE p.id = ?',
    );
    $st->execute([$prId]);
    $pr = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pr) {
        jsonResponse(['success' => false, 'message' => 'Purchase Request not found']);
    }
    procurementAssertDepartmentMatch(isset($pr['office_id']) ? (int) $pr['office_id'] : null);
    if (!procurementAdminMaySeeDraft($pr)) {
        jsonResponse(['success' => false, 'message' => 'This draft is only visible to that department until they submit it.']);
    }

    $st = $conn->prepare(
        'SELECT * FROM proc_pr_line WHERE pr_id = ? ORDER BY line_no ASC',
    );
    $st->execute([$prId]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $conn->prepare(
        'SELECT * FROM proc_quotation WHERE pr_id = ? ORDER BY slot ASC',
    );
    $st->execute([$prId]);
    $quotes = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($quotes as &$q) {
        $ql = $conn->prepare(
            'SELECT ql.* FROM proc_quotation_line ql WHERE ql.quotation_id = ? ORDER BY ql.id ASC',
        );
        $ql->execute([(int) $q['id']]);
        $q['lines'] = $ql->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($q);

    $po = null;
    $st = $conn->prepare(
        'SELECT * FROM proc_po WHERE pr_id = ? ORDER BY id DESC LIMIT 1',
    );
    $st->execute([$prId]);
    $po = $st->fetch(PDO::FETCH_ASSOC);
    $po_lines = [];
    $air = null;
    $air_lines = [];

    if ($po) {
        $pl = $conn->prepare(
            'SELECT pol.id, pol.po_id, pol.pr_line_id,
                    COALESCE(prl.line_no, pol.line_no) AS line_no,
                    COALESCE(prl.quantity, pol.quantity) AS quantity,
                    COALESCE(prl.unit, pol.unit) AS unit,
                    COALESCE(prl.description, pol.description) AS description,
                    pol.unit_cost,
                    ROUND(pol.unit_cost * COALESCE(prl.quantity, pol.quantity), 2) AS amount
             FROM proc_po_line pol
             LEFT JOIN proc_pr_line prl ON prl.id = pol.pr_line_id
             WHERE pol.po_id = ?
             ORDER BY COALESCE(prl.line_no, pol.line_no) ASC, pol.id ASC',
        );
        $pl->execute([(int) $po['id']]);
        $po_lines = $pl->fetchAll(PDO::FETCH_ASSOC);

        $ar = $conn->prepare(
            'SELECT * FROM proc_air WHERE po_id = ? LIMIT 1',
        );
        $ar->execute([(int) $po['id']]);
        $air = $ar->fetch(PDO::FETCH_ASSOC);
        if ($air) {
            $al = $conn->prepare(
                'SELECT COALESCE(prl.line_no, pol.line_no, al.line_no) AS line_no,
                        COALESCE(prl.quantity, pol.quantity, al.quantity) AS quantity,
                        COALESCE(prl.unit, pol.unit, al.unit) AS unit,
                        COALESCE(prl.description, pol.description, al.description) AS description
                 FROM proc_air_line al
                 INNER JOIN proc_air a2 ON a2.id = al.air_id
                 LEFT JOIN proc_po_line pol ON pol.po_id = a2.po_id AND pol.line_no = al.line_no
                 LEFT JOIN proc_pr_line prl ON prl.id = pol.pr_line_id
                 WHERE al.air_id = ?
                 ORDER BY COALESCE(prl.line_no, pol.line_no, al.line_no) ASC, al.id ASC',
            );
            $al->execute([(int) $air['id']]);
            $air_lines = $al->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $linkedIssuance = null;
    try {
        $li = $conn->prepare(
            'SELECT i.id, i.form_type, i.form_number, i.issue_date, i.issued_to, p.property_number, p.description
             FROM issuance_records i
             LEFT JOIN property_items p ON p.id = i.property_item_id
             WHERE i.proc_pr_id = ? ORDER BY i.id DESC LIMIT 1',
        );
        $li->execute([$prId]);
        $linkedIssuance = $li->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        // proc_pr_id column not present yet (older install) — treat as no link.
        $linkedIssuance = null;
    }

    return [
        'purchase_request' => $pr,
        'lines' => $lines,
        'quotations' => $quotes,
        'purchase_order' => $po ? array_merge($po, ['lines' => $po_lines]) : null,
        'acceptance_report' => $air ? array_merge($air, ['lines' => $air_lines]) : null,
        'linked_issuance' => $linkedIssuance,
    ];
}

switch ($method) {
    case 'GET':
        PermissionHelper::require('view');
        prEnsureLineInventoryColumns($conn);
        if (isset($_GET['sections'])) {
            jsonResponse(['success' => true, 'data' => prInventoryGroupOptions($conn)]);
        }
        if (!procTableExists($conn)) {
            jsonResponse([
                'success' => false,
                'message' => 'Procurement tables not installed',
                'hint' => 'Import database/migrations/001_procurement_module.sql',
            ]);
        }
        procurementEnsureCanvassColumns($conn);
        procurementEnsureCreatedByColumn($conn);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id > 0) {
            jsonResponse(['success' => true, 'data' => procurementDetail($conn, $id)]);
        }

        // Department pulse: most frequently requested PR line items (office-scoped for staff)
        if (isset($_GET['frequent_items'])) {
            $scope = procurementUserDepartmentScope();
            $officeId = 0;
            if ($scope['scoped']) {
                procurementRequireAssignedDepartment();
                $officeId = (int) $scope['office_id'];
            } else {
                $officeId = isset($_GET['office_id']) ? (int) $_GET['office_id'] : 0;
            }
            if ($officeId <= 0) {
                jsonResponse(['success' => true, 'data' => []]);
            }
            $labelSql = consumablePrLineLabelSql('l');
            $st = $conn->prepare(
                "SELECT {$labelSql} AS item_label,
                        COUNT(*) AS request_count,
                        COALESCE(SUM(l.quantity), 0) AS total_qty,
                        MAX(p.pr_date) AS last_requested
                 FROM proc_pr_line l
                 INNER JOIN proc_pr p ON p.id = l.pr_id
                 WHERE p.office_id = ?
                   AND p.status NOT IN ('draft','needs_revision')
                 GROUP BY {$labelSql}
                 ORDER BY request_count DESC, total_qty DESC
                 LIMIT 5"
            );
            $st->execute([$officeId]);
            jsonResponse(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        $scope = procurementUserDepartmentScope();
        $sql =
            'SELECT p.id, p.pr_number, p.pr_date, p.status, p.purpose, p.canvass_feedback, p.updated_at, p.is_consumable, o.name AS office_name,
               (SELECT SUM(est_line_total) FROM proc_pr_line l WHERE l.pr_id = p.id) AS estimate_total,
               (SELECT COUNT(*) FROM proc_quotation q WHERE q.pr_id = p.id) AS quotation_count,
               EXISTS(SELECT 1 FROM proc_po po WHERE po.pr_id = p.id LIMIT 1) AS has_po
             FROM proc_pr p
             JOIN offices o ON o.id = p.office_id';
        $params = [];
        $where = [];
        if ($scope['scoped']) {
            procurementRequireAssignedDepartment();
            $where[] = 'p.office_id = ?';
            $params[] = (int) $scope['office_id'];
        } else {
            // Admins may narrow the list to a specific office.
            $filterOfficeId = isset($_GET['office_id']) ? (int) $_GET['office_id'] : 0;
            if ($filterOfficeId > 0) {
                $where[] = 'p.office_id = ?';
                $params[] = $filterOfficeId;
            }
        }
        $filterStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
        if ($filterStatus === 'needs_fix') {
            $filterStatus = 'needs_revision';
        }
        if (!$scope['scoped']) {
            $adminId = procurementCurrentUserId();
            if ($filterStatus === 'draft') {
                $where[] = "p.status = 'draft' AND p.created_by_user_id = ?";
                $params[] = $adminId;
            } elseif ($filterStatus !== '') {
                $where[] = 'p.status = ?';
                $params[] = $filterStatus;
            } else {
                $where[] = '(p.status <> \'draft\' OR p.created_by_user_id = ?)';
                $params[] = $adminId;
            }
        } elseif ($filterStatus !== '') {
            $where[] = 'p.status = ?';
            $params[] = $filterStatus;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where) . ' ';
        }
        $sql .= ' ORDER BY p.updated_at DESC, p.id DESC LIMIT 200';
        $st = $conn->prepare($sql);
        $st->execute($params);
        jsonResponse([
            'success' => true,
            'data' => $st->fetchAll(PDO::FETCH_ASSOC),
        ]);

        break;

    case 'POST':
        PermissionHelper::require('create');
        if (!procTableExists($conn)) {
            jsonResponse([
                'success' => false,
                'message' => 'Procurement tables not installed',
                'hint' => 'Import database/migrations/001_procurement_module.sql',
            ]);
        }
        prEnsureLineInventoryColumns($conn);
        procurementEnsureCanvassColumns($conn);
        procurementEnsureCreatedByColumn($conn);
        procBodyJson($body);
        if (!is_array($body)) {
            jsonResponse(['success' => false, 'message' => 'Invalid JSON body']);
        }
        $action = isset($body['action']) ? trim((string) $body['action']) : '';

        if ($action === 'create_pr') {
            procCreatePR($conn, $body);
        }

        if ($action === 'add_quotation') {
            procurementAssertAdminOnlyAction('add or edit quotations');
            procAddQuotation($conn, $body);
        }

        if ($action === 'delete_quotation') {
            PermissionHelper::require('delete');
            procurementAssertAdminOnlyAction('remove quotations');
            procDeleteQuotation($conn, $body);
        }

        if ($action === 'create_po') {
            procurementAssertAdminOnlyAction('issue a purchase order');
            procCreatePo($conn, $body);
        }

        if ($action === 'create_air') {
            procurementAssertAdminOnlyAction('file an acceptance & inspection report');
            procCreateAir($conn, $body);
        }

        if ($action === 'submit_pr') {
            PermissionHelper::require('edit');
            procSubmitPr($conn, $body);
        }

        if ($action === 'approve_pr') {
            PermissionHelper::require('edit');
            procurementAssertAdminOnlyAction('approve canvass submissions');
            procApprovePr($conn, $body);
        }

        if ($action === 'reject_pr') {
            PermissionHelper::require('edit');
            procurementAssertAdminOnlyAction('reject canvass submissions');
            procRejectPr($conn, $body);
        }

        jsonResponse(['success' => false, 'message' => 'Unknown action']);
        break;

    case 'PUT':
        PermissionHelper::require('edit');
        prEnsureLineInventoryColumns($conn);
        procurementEnsureCreatedByColumn($conn);
        procBodyJson($body);
        if (!is_array($body)) {
            jsonResponse(['success' => false, 'message' => 'Invalid JSON body']);
        }
        procUpdatePrDraft($conn, $body);
        break;

    case 'DELETE':
        PermissionHelper::require('edit');
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid PR id']);
        }
        procDeletePrDraft($conn, $id);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

/**
 * @param array<string, mixed> $body
 */
function procCreatePR(PDO $conn, array $body): void {
    $requestedOffice = isset($body['office_id']) ? (int) $body['office_id'] : 0;
    $office_id = procurementNormalizeNewPrOffice($requestedOffice);
    $pr_date = sanitizeInput((string) ($body['pr_date'] ?? date('Y-m-d')));
    if ($office_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Select a requisitioning office']);
    }

    $pr_number_raw = isset($body['pr_number']) ? trim((string) $body['pr_number']) : '';
    $purpose = sanitizeInput((string) ($body['purpose'] ?? ''));
    $requested_by = sanitizeInput((string) ($body['requested_by'] ?? ''));
    $approved_by = sanitizeInput((string) ($body['approved_by'] ?? ''));
    $budget_note = sanitizeInput((string) ($body['budget_received_note'] ?? ''));
    $is_consumable = !empty($body['is_consumable']) ? 1 : 0;
    $pr_section_raw = sanitizeInput((string) ($body['pr_section'] ?? ''));
    if ($is_consumable) {
        $pr_section = 'Consumable';
    } else {
        $pr_section = $pr_section_raw !== '' ? mb_substr(prInventoryNormalizeGroup($pr_section_raw), 0, 120) : null;
        if ($pr_section !== null && strcasecmp($pr_section, 'Consumable') === 0) {
            jsonResponse(['success' => false, 'message' => 'Use the consumable Yes option for consumable requests.']);
        }
    }
    if ($pr_section === null || $pr_section === '') {
        jsonResponse(['success' => false, 'message' => 'Select a section (inventory category) for this request.']);
    }
    $sai_raw = sanitizeInput((string) ($body['sai_number'] ?? ''));
    $sai_number = $sai_raw !== '' ? mb_substr($sai_raw, 0, 80) : null;
    $req_title_raw = sanitizeInput((string) ($body['requested_by_title'] ?? ''));
    $requested_by_title = $req_title_raw !== '' ? mb_substr($req_title_raw, 0, 160) : null;
    $app_title_raw = sanitizeInput((string) ($body['approved_by_title'] ?? ''));
    $approved_by_title = $app_title_raw !== '' ? mb_substr($app_title_raw, 0, 160) : null;
    $requested_signed_date = procNormalizeDate((string) ($body['requested_signed_date'] ?? ''));
    $approved_signed_date = procNormalizeDate((string) ($body['approved_signed_date'] ?? ''));

    [$requested_by, $requested_by_title, $approved_by, $approved_by_title] = procurementApplyAdminPrSignatories(
        $conn,
        $office_id,
        $requested_by,
        $requested_by_title,
        $approved_by,
        $approved_by_title
    );
    [$requested_by, $requested_by_title, $approved_by, $approved_by_title] = procurementApplyStaffPrDefaults(
        $conn,
        $requested_by,
        $requested_by_title,
        $approved_by,
        $approved_by_title
    );

    $lines_in = isset($body['lines']) && is_array($body['lines']) ? $body['lines'] : [];
    $prErr = lguePrPayloadError($purpose, $lines_in);
    if ($prErr !== null) {
        jsonResponse(['success' => false, 'message' => $prErr]);
    }
    $nameErr = lgueNameError($requested_by, 'Requested by', false);
    if ($nameErr !== null) {
        jsonResponse(['success' => false, 'message' => $nameErr]);
    }
    $nameErr = lgueNameError($approved_by, 'Approved by', false);
    if ($nameErr !== null) {
        jsonResponse(['success' => false, 'message' => $nameErr]);
    }
    $titleErr = lgueOptionalTextError($requested_by_title ?? '', 'Requested designation');
    if ($titleErr !== null) {
        jsonResponse(['success' => false, 'message' => $titleErr]);
    }
    $titleErr = lgueOptionalTextError($approved_by_title ?? '', 'Approved designation');
    if ($titleErr !== null) {
        jsonResponse(['success' => false, 'message' => $titleErr]);
    }

    if ($pr_number_raw !== '') {
        $chk = $conn->prepare(
            'SELECT id FROM proc_pr WHERE pr_number = ?',
        );
        $chk->execute([$pr_number_raw]);
        if ($chk->fetch()) {
            jsonResponse(['success' => false, 'message' => 'PR number already exists']);
        }
    }

    try {
        $conn->beginTransaction();

        $pr_number_placeholder = '__NEW__';

        $st = $conn->prepare(
            'INSERT INTO proc_pr (pr_number, office_id, pr_section, is_consumable, sai_number, pr_date, purpose,
             requested_by, requested_by_title, requested_signed_date,
             approved_by, approved_by_title, approved_signed_date,
             budget_received_note, status, created_by_user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'draft\',?)',
        );
        $st->execute([
            $pr_number_raw !== '' ? $pr_number_raw : $pr_number_placeholder,
            $office_id,
            $pr_section,
            $is_consumable,
            $sai_number,
            $pr_date,
            $purpose !== '' ? $purpose : null,
            $requested_by !== '' ? $requested_by : null,
            $requested_by_title,
            $requested_signed_date,
            $approved_by !== '' ? $approved_by : null,
            $approved_by_title,
            $approved_signed_date,
            $budget_note !== '' ? $budget_note : null,
            procurementCurrentUserId() ?: null,
        ]);

        $pr_id = (int) $conn->lastInsertId();

        if ($pr_number_raw === '') {
            $final = sprintf(
                'PR-%s-%06d',
                date('Y', strtotime($pr_date)),
                $pr_id,
            );
            $conn->prepare('UPDATE proc_pr SET pr_number=? WHERE id=?')->execute([
                $final,
                $pr_id,
            ]);
        }

        $lineNo = 1;
        $insLine = $conn->prepare(
            'INSERT INTO proc_pr_line (pr_id,line_no,quantity,unit,description,est_unit_cost,est_line_total,article,item_group) VALUES (?,?,?,?,?,?,?,?,?)',
        );
        foreach ($lines_in as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($is_consumable) {
                $row['article'] = 'Consumable';
                $row['item_group'] = 'Consumable';
            }
            $parsed = $is_consumable
                ? procParsePrLineRow($row, 'Consumable')
                : procAlignParsedArticle($conn, $office_id, procParsePrLineRow($row, $pr_section));
            if ($is_consumable) {
                $parsed['article'] = 'Consumable';
                $parsed['item_group'] = 'Consumable';
            }
            if ($parsed['description'] === '') {
                $conn->rollBack();
                jsonResponse(['success' => false, 'message' => $is_consumable
                    ? 'Each consumable line needs an item name in the description'
                    : 'Each line needs a description']);
            }
            $insLine->execute([
                $pr_id,
                $lineNo,
                $parsed['quantity'],
                $parsed['unit'],
                $parsed['description'],
                $parsed['est_unit_cost'],
                $parsed['est_line_total'],
                $parsed['article'],
                $parsed['item_group'],
            ]);
            ++$lineNo;
        }

        $conn->commit();
        $createdLabel = $pr_number_raw !== ''
            ? $pr_number_raw
            : sprintf('PR-%s-%06d', date('Y', strtotime($pr_date)), $pr_id);
        procurementLog($conn, 'PR created', $pr_id, [
            'office_id' => $office_id,
            'entity_label' => $createdLabel,
            'new_value' => 'draft',
        ]);
        jsonResponse([
            'success' => true,
            'message' => 'Purchase Request created',
            'id' => $pr_id,
        ]);
    } catch (PDOException $e) {
        $conn->rollBack();
        jsonResponse([
            'success' => false,
            'message' => 'Could not save PR',
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * @param array<string, mixed> $body
 */
function procUpdatePrDraft(PDO $conn, array $body): void {
    $pr_id = isset($body['pr_id']) ? (int) $body['pr_id'] : 0;
    if ($pr_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid pr_id']);
    }

    $st = $conn->prepare('SELECT status, purpose, requested_by, approved_by, requested_by_title, approved_by_title, office_id, created_by_user_id FROM proc_pr WHERE id=?');
    $st->execute([$pr_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $status = strtolower((string) ($row['status'] ?? ''));
    $isAdmin = PermissionHelper::isAdmin();
    $canEditDraft = procurementIsDraftLike($status) && ($status !== 'draft' || procurementAdminMaySeeDraft($row ?: []));
    $canEditSubmitted = $isAdmin && $status === 'submitted';
    if (!$row || (!$canEditDraft && !$canEditSubmitted)) {
        jsonResponse(['success' => false, 'message' => $isAdmin
            ? 'Only a waiting-approval request or your own draft can be edited'
            : 'Only draft or need-revision PRs can be edited']);
    }

    procurementEnsurePrAccess($conn, $pr_id);

    $lines_in = isset($body['lines']) && is_array($body['lines']) ? $body['lines'] : null;
    $purposeCheck = array_key_exists('purpose', $body)
        ? sanitizeInput((string) $body['purpose'])
        : (string) ($row['purpose'] ?? '');
    $linesCheck = $lines_in;
    if ($linesCheck === null) {
        $lst = $conn->prepare('SELECT description, quantity, unit, est_unit_cost FROM proc_pr_line WHERE pr_id=?');
        $lst->execute([$pr_id]);
        $linesCheck = $lst->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $prErr = lguePrPayloadError($purposeCheck, $linesCheck);
    if ($prErr !== null) {
        jsonResponse(['success' => false, 'message' => $prErr]);
    }
    $reqBy = array_key_exists('requested_by', $body) ? sanitizeInput((string) $body['requested_by']) : (string) ($row['requested_by'] ?? '');
    $appBy = array_key_exists('approved_by', $body) ? sanitizeInput((string) $body['approved_by']) : (string) ($row['approved_by'] ?? '');
    $nameErr = lgueNameError($reqBy, 'Requested by', false);
    if ($nameErr !== null) {
        jsonResponse(['success' => false, 'message' => $nameErr]);
    }
    $nameErr = lgueNameError($appBy, 'Approved by', false);
    if ($nameErr !== null) {
        jsonResponse(['success' => false, 'message' => $nameErr]);
    }

    try {
        $conn->beginTransaction();

        $hdrSets = [];
        $hdrVals = [];
        if (array_key_exists('purpose', $body)) {
            $hdrSets[] = 'purpose=?';
            $hdrVals[] = sanitizeInput((string) $body['purpose']);
        }
        if (array_key_exists('is_consumable', $body) || array_key_exists('pr_section', $body)) {
            $isCons = array_key_exists('is_consumable', $body)
                ? (!empty($body['is_consumable']) ? 1 : 0)
                : (consumablePrIsConsumable($conn, $pr_id) ? 1 : 0);
            $hdrSets[] = 'is_consumable=?';
            $hdrVals[] = $isCons;
            if ($isCons) {
                $hdrSets[] = 'pr_section=?';
                $hdrVals[] = 'Consumable';
            } elseif (array_key_exists('pr_section', $body)) {
                $hdrSets[] = 'pr_section=?';
                $v = sanitizeInput((string) $body['pr_section']);
                $norm = $v !== '' ? mb_substr(prInventoryNormalizeGroup($v), 0, 120) : null;
                if ($norm !== null && strcasecmp($norm, 'Consumable') === 0) {
                    $conn->rollBack();
                    jsonResponse(['success' => false, 'message' => 'Use the consumable Yes option for consumable requests.']);
                }
                $hdrVals[] = $norm;
            }
        }
        if (array_key_exists('sai_number', $body)) {
            $hdrSets[] = 'sai_number=?';
            $v = sanitizeInput((string) $body['sai_number']);
            $hdrVals[] = $v !== '' ? mb_substr($v, 0, 80) : null;
        }
        if (array_key_exists('requested_by', $body)) {
            $hdrSets[] = 'requested_by=?';
            $v = sanitizeInput((string) $body['requested_by']);
            $hdrVals[] = $v !== '' ? $v : null;
        }
        if (array_key_exists('requested_by_title', $body)) {
            $hdrSets[] = 'requested_by_title=?';
            $v = sanitizeInput((string) $body['requested_by_title']);
            $hdrVals[] = $v !== '' ? mb_substr($v, 0, 160) : null;
        }
        if (array_key_exists('requested_signed_date', $body)) {
            $hdrSets[] = 'requested_signed_date=?';
            $hdrVals[] = procNormalizeDate((string) $body['requested_signed_date']);
        }
        if (array_key_exists('approved_by', $body)) {
            $hdrSets[] = 'approved_by=?';
            $v = sanitizeInput((string) $body['approved_by']);
            $hdrVals[] = $v !== '' ? $v : null;
        }
        if (array_key_exists('approved_by_title', $body)) {
            $hdrSets[] = 'approved_by_title=?';
            $v = sanitizeInput((string) $body['approved_by_title']);
            $hdrVals[] = $v !== '' ? mb_substr($v, 0, 160) : null;
        }
        if (array_key_exists('approved_signed_date', $body)) {
            $hdrSets[] = 'approved_signed_date=?';
            $hdrVals[] = procNormalizeDate((string) $body['approved_signed_date']);
        }
        if (array_key_exists('budget_received_note', $body)) {
            $hdrSets[] = 'budget_received_note=?';
            $v = sanitizeInput((string) $body['budget_received_note']);
            $hdrVals[] = $v !== '' ? $v : null;
        }

        if ($hdrSets !== []) {
            $hdrVals[] = $pr_id;
            $conn->prepare(
                'UPDATE proc_pr SET ' . implode(', ', $hdrSets) . ' WHERE id=?',
            )->execute($hdrVals);
        }

        if ($lines_in !== null) {
            if (count($lines_in) === 0) {
                $conn->rollBack();
                jsonResponse(['success' => false, 'message' => 'At least one line required']);
            }
            $conn->prepare('DELETE FROM proc_pr_line WHERE pr_id=?')->execute([$pr_id]);
            $lineNo = 1;
            $fallbackGroup = null;
            if (array_key_exists('pr_section', $body)) {
                $fallbackGroup = sanitizeInput((string) $body['pr_section']);
                $fallbackGroup = $fallbackGroup !== '' ? prInventoryNormalizeGroup($fallbackGroup) : null;
            } else {
                $secSt = $conn->prepare('SELECT pr_section FROM proc_pr WHERE id=?');
                $secSt->execute([$pr_id]);
                $fallbackGroup = (string) ($secSt->fetchColumn() ?: '');
                $fallbackGroup = $fallbackGroup !== '' ? prInventoryNormalizeGroup($fallbackGroup) : null;
            }
            $insLine = $conn->prepare(
                'INSERT INTO proc_pr_line (pr_id,line_no,quantity,unit,description,est_unit_cost,est_line_total,article,item_group) VALUES (?,?,?,?,?,?,?,?,?)',
            );
            $prOfficeId = (int) ($row['office_id'] ?? 0);
            $isConsUpdate = !empty($body['is_consumable'])
                || consumablePrIsConsumable($conn, $pr_id)
                || strcasecmp((string) ($fallbackGroup ?? ''), 'Consumable') === 0;
            foreach ($lines_in as $lineRow) {
                if (!is_array($lineRow)) {
                    continue;
                }
                if ($isConsUpdate) {
                    $lineRow['article'] = 'Consumable';
                    $lineRow['item_group'] = 'Consumable';
                }
                $parsed = $isConsUpdate
                    ? procParsePrLineRow($lineRow, 'Consumable')
                    : procAlignParsedArticle($conn, $prOfficeId, procParsePrLineRow($lineRow, $fallbackGroup));
                if ($isConsUpdate) {
                    $parsed['article'] = 'Consumable';
                    $parsed['item_group'] = 'Consumable';
                }
                if ($parsed['description'] === '') {
                    $conn->rollBack();
                    jsonResponse(['success' => false, 'message' => $isConsUpdate
                        ? 'Each consumable line needs an item name in the description'
                        : 'Each line needs a description']);
                }
                $insLine->execute([
                    $pr_id,
                    $lineNo,
                    $parsed['quantity'],
                    $parsed['unit'],
                    $parsed['description'],
                    $parsed['est_unit_cost'],
                    $parsed['est_line_total'],
                    $parsed['article'],
                    $parsed['item_group'],
                ]);
                ++$lineNo;
            }
        }

        $conn->commit();
        procurementLog($conn, $canEditSubmitted ? 'PR revised by admin' : 'PR updated', $pr_id);
        jsonResponse([
            'success' => true,
            'message' => $canEditSubmitted
                ? 'Request updated. It is still waiting for canvass approval.'
                : 'PR updated',
        ]);
    } catch (PDOException $e) {
        $conn->rollBack();
        jsonResponse(['success' => false, 'message' => 'Update failed', 'error' => $e->getMessage()]);
    }
}

/**
 * @param array<string, mixed> $body
 */
function procSubmitPr(PDO $conn, array $body): void {
    $pr_id = isset($body['pr_id']) ? (int) $body['pr_id'] : 0;
    if ($pr_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid pr_id']);
    }

    procurementEnsurePrAccess($conn, $pr_id);

    $chk = $conn->prepare("SELECT purpose, status FROM proc_pr WHERE id=? AND status IN ('draft','needs_revision')");
    $chk->execute([$pr_id]);
    $prRow = $chk->fetch(PDO::FETCH_ASSOC) ?: [];
    $purpose = (string) ($prRow['purpose'] ?? '');
    $oldStatus = strtolower((string) ($prRow['status'] ?? 'draft'));
    $lst = $conn->prepare('SELECT description, quantity, unit, est_unit_cost FROM proc_pr_line WHERE pr_id=?');
    $lst->execute([$pr_id]);
    $prErr = lguePrPayloadError($purpose, $lst->fetchAll(PDO::FETCH_ASSOC) ?: []);
    if ($prErr !== null) {
        jsonResponse(['success' => false, 'message' => $prErr]);
    }

    $st = $conn->prepare("UPDATE proc_pr SET status='submitted', canvass_feedback=NULL WHERE id=? AND status IN ('draft','needs_revision')");
    $st->execute([$pr_id]);
    if ($st->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'PR is not a draft or need-revision request']);
    }

    $info = $conn->prepare('SELECT p.pr_number, p.office_id, o.name AS office_name FROM proc_pr p JOIN offices o ON o.id = p.office_id WHERE p.id = ?');
    $info->execute([$pr_id]);
    $row = $info->fetch(PDO::FETCH_ASSOC) ?: [];
    procurementNotify(
        $conn,
        'pr_submitted',
        'Purchase Request submitted for canvass approval',
        sprintf(
            'PR %s (%s) was submitted for canvass. Department canvass submissions require admin approval.',
            $row['pr_number'] ?? $pr_id,
            $row['office_name'] ?? 'Unknown office'
        ),
        $pr_id,
        isset($row['office_id']) ? (int) $row['office_id'] : null
    );
    procurementLog($conn, 'PR submitted', $pr_id, [
        'office_id' => isset($row['office_id']) ? (int) $row['office_id'] : null,
        'entity_label' => (string) ($row['pr_number'] ?? ''),
        'old_value' => $oldStatus !== '' ? $oldStatus : 'draft',
        'new_value' => 'submitted',
    ]);

    jsonResponse(['success' => true, 'message' => 'PR submitted for admin canvass approval']);
}

function procApprovePr(PDO $conn, array $body): void {
    $pr_id = isset($body['pr_id']) ? (int) $body['pr_id'] : 0;
    if ($pr_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid pr_id']);
    }

    procurementEnsurePrAccess($conn, $pr_id);
    procurementEnsureCanvassColumns($conn);

    $reviewer = sanitizeInput((string) ($_SESSION['user_name'] ?? 'Admin'));
    $st = $conn->prepare(
        "UPDATE proc_pr SET status='quoting', canvass_reviewed_by=?, canvass_reviewed_at=NOW() WHERE id=? AND status='submitted'"
    );
    $st->execute([$reviewer, $pr_id]);
    if ($st->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'PR is not awaiting canvass approval']);
    }

    $info = $conn->prepare('SELECT p.pr_number, p.office_id, o.name AS office_name FROM proc_pr p JOIN offices o ON o.id = p.office_id WHERE p.id = ?');
    $info->execute([$pr_id]);
    $row = $info->fetch(PDO::FETCH_ASSOC) ?: [];
    procurementNotify(
        $conn,
        'pr_canvass_approved',
        'Canvass approved',
        sprintf(
            'PR %s was approved for canvass. GSO can now record quotations.',
            $row['pr_number'] ?? $pr_id
        ),
        $pr_id,
        isset($row['office_id']) ? (int) $row['office_id'] : null
    );
    procurementLog($conn, 'Canvass approved', $pr_id, [
        'office_id' => isset($row['office_id']) ? (int) $row['office_id'] : null,
        'entity_label' => (string) ($row['pr_number'] ?? ''),
        'old_value' => 'submitted',
        'new_value' => 'quoting',
    ]);

    jsonResponse(['success' => true, 'message' => 'Canvass approved. Quotations can now be recorded.']);
}

function procRejectPr(PDO $conn, array $body): void {
    $pr_id = isset($body['pr_id']) ? (int) $body['pr_id'] : 0;
    $feedback = trim((string) ($body['feedback'] ?? $body['canvass_feedback'] ?? ''));
    if ($pr_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid pr_id']);
    }
    $fbErr = lgueRequiredTextError($feedback, 'Feedback', 12, 2);
    if ($fbErr !== null) {
        jsonResponse(['success' => false, 'message' => $fbErr]);
    }

    procurementEnsurePrAccess($conn, $pr_id);
    procurementEnsureCanvassColumns($conn);

    $reviewer = sanitizeInput((string) ($_SESSION['user_name'] ?? 'Admin'));
    $params = [sanitizeInput($feedback), $reviewer, $pr_id];
    $st = $conn->prepare(
        "UPDATE proc_pr SET status='needs_revision', canvass_feedback=?, canvass_reviewed_by=?, canvass_reviewed_at=NOW() WHERE id=? AND status='submitted'"
    );
    try {
        $st->execute($params);
    } catch (PDOException $e) {
        $st = $conn->prepare(
            "UPDATE proc_pr SET status='draft', canvass_feedback=?, canvass_reviewed_by=?, canvass_reviewed_at=NOW() WHERE id=? AND status='submitted'"
        );
        $st->execute($params);
    }
    if ($st->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'PR is not awaiting canvass approval']);
    }

    $info = $conn->prepare('SELECT p.pr_number, p.office_id, o.name AS office_name FROM proc_pr p JOIN offices o ON o.id = p.office_id WHERE p.id = ?');
    $info->execute([$pr_id]);
    $row = $info->fetch(PDO::FETCH_ASSOC) ?: [];
    $preview = function_exists('mb_substr') ? mb_substr($feedback, 0, 300) : substr($feedback, 0, 300);
    procurementNotify(
        $conn,
        'pr_canvass_rejected',
        'Canvass returned for revision',
        sprintf(
            'PR %s was returned for revision. Address the admin feedback and resubmit. Feedback: %s',
            $row['pr_number'] ?? $pr_id,
            $preview
        ),
        $pr_id,
        isset($row['office_id']) ? (int) $row['office_id'] : null
    );
    procurementLog($conn, 'Canvass rejected', $pr_id, [
        'office_id' => isset($row['office_id']) ? (int) $row['office_id'] : null,
        'entity_label' => (string) ($row['pr_number'] ?? ''),
        'old_value' => 'submitted',
        'new_value' => 'needs_revision',
    ]);

    jsonResponse(['success' => true, 'message' => 'Returned for revision. The department must address the feedback and resubmit.']);
}

/**
 * @param array<string, mixed> $body
 */
function procDeletePrDraft(PDO $conn, int $id): void {
    procurementEnsurePrAccess($conn, $id);
    procurementEnsureCreatedByColumn($conn);
    $info = $conn->prepare('SELECT pr_number, office_id, status, created_by_user_id FROM proc_pr WHERE id=?');
    $info->execute([$id]);
    $row = $info->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($row && !procurementAdminMaySeeDraft($row)) {
        jsonResponse(['success' => false, 'message' => 'This draft is only visible to that department until they submit it.']);
    }
    $st = $conn->prepare("DELETE FROM proc_pr WHERE id=? AND status IN ('draft','needs_revision')");
    $st->execute([$id]);
    if ($st->rowCount() === 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Cannot delete — only draft PRs with no reliance can be deleted',
        ]);
    }
    procurementLog($conn, 'Draft PR deleted', $id, [
        'office_id' => isset($row['office_id']) ? (int) $row['office_id'] : null,
        'entity_label' => (string) ($row['pr_number'] ?? ''),
        'old_value' => 'draft',
    ]);
    jsonResponse(['success' => true, 'message' => 'Draft PR deleted']);
}

/**
 * @param array<string, mixed> $body
 */
function procAddQuotation(PDO $conn, array $body): void {
    $pr_id = isset($body['pr_id']) ? (int) $body['pr_id'] : 0;
    $slot = isset($body['slot']) ? (int) $body['slot'] : 1;
    if ($pr_id <= 0 || $slot < 1 || $slot > 3) {
        jsonResponse(['success' => false, 'message' => 'Invalid pr_id or slot (use 1–3)']);
    }

    procurementEnsurePrAccess($conn, $pr_id);

    $st = $conn->prepare('SELECT status FROM proc_pr WHERE id=?');
    $st->execute([$pr_id]);
    $pr = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pr || ($pr['status'] ?? '') !== 'quoting') {
        jsonResponse([
            'success' => false,
            'message' => 'PR must be approved for canvass before quotations can be recorded',
        ]);
    }

    $supplier = sanitizeInput((string) ($body['supplier_name'] ?? ''));
    $supErr = lgueRequiredTextError($supplier, 'Supplier name', 3);
    if ($supErr !== null) {
        jsonResponse(['success' => false, 'message' => $supErr]);
    }
    $addrErr = lgueOptionalTextError(sanitizeInput((string) ($body['supplier_address'] ?? '')), 'Supplier address');
    if ($addrErr !== null) {
        jsonResponse(['success' => false, 'message' => $addrErr]);
    }
    $addr = sanitizeInput((string) ($body['supplier_address'] ?? ''));
    $quot_ref = sanitizeInput((string) ($body['quotation_ref'] ?? ''));
    $qDate = sanitizeInput((string) ($body['quotation_date'] ?? date('Y-m-d')));

    $prLinesSt = $conn->prepare(
        'SELECT id, quantity FROM proc_pr_line WHERE pr_id = ? ORDER BY line_no ASC',
    );
    $prLinesSt->execute([$pr_id]);
    $prLines = $prLinesSt->fetchAll(PDO::FETCH_ASSOC);
    if (!$prLines) {
        jsonResponse(['success' => false, 'message' => 'PR has no line items']);
    }

    $prLineIds = [];
    foreach ($prLines as $row) {
        $prLineIds[(int) $row['id']] = (float) $row['quantity'];
    }

    $prices = isset($body['prices']) && is_array($body['prices']) ? $body['prices'] : [];
    if (count($prices) !== count($prLineIds)) {
        jsonResponse([
            'success' => false,
            'message' => 'Provide one unit price per PR line (' . count($prLineIds) . ' rows)',
        ]);
    }

    $priceMap = [];
    foreach ($prices as $p) {
        if (!is_array($p)) {
            jsonResponse(['success' => false, 'message' => 'Invalid prices payload']);
        }
        $lid = isset($p['pr_line_id']) ? (int) $p['pr_line_id'] : 0;
        $upRaw = $p['unit_price'] ?? '';
        $upErr = lgueNumericFieldError($upRaw, 'Unit price', true, 0.0, false);
        if ($upErr !== null) {
            jsonResponse(['success' => false, 'message' => $upErr]);
        }
        $up = round((float) $upRaw, 2);
        if (!isset($prLineIds[$lid])) {
            jsonResponse(['success' => false, 'message' => 'Unknown pr_line_id']);
        }
        $priceMap[$lid] = $up;
    }

    if (count($priceMap) !== count($prLineIds)) {
        jsonResponse([
            'success' => false,
            'message' => 'Prices must reference every PR line exactly once',
        ]);
    }

    try {
        $conn->beginTransaction();

        $conn->prepare(
            'DELETE FROM proc_quotation_line WHERE quotation_id IN (SELECT id FROM proc_quotation WHERE pr_id=? AND slot=?)',
        )->execute([$pr_id, $slot]);
        $conn->prepare('DELETE FROM proc_quotation WHERE pr_id=? AND slot=?')->execute([
            $pr_id,
            $slot,
        ]);

        $qi = $conn->prepare(
            'INSERT INTO proc_quotation (pr_id, slot, supplier_name, supplier_address, quotation_ref, quotation_date, total_amount) VALUES (?,?,?,?,?,?,?)',
        );
        $qi->execute([
            $pr_id,
            $slot,
            $supplier,
            $addr ?: null,
            $quot_ref ?: null,
            $qDate,
            0,
        ]);
        $qid = (int) $conn->lastInsertId();

        $insQl = $conn->prepare(
            'INSERT INTO proc_quotation_line (quotation_id, pr_line_id, unit_price, line_total) VALUES (?,?,?,?)',
        );

        $sum = 0.0;
        foreach ($prLines as $pl) {
            $lid = (int) $pl['id'];
            $qty = (float) $pl['quantity'];
            $unit = $priceMap[$lid];
            $lt = round($qty * $unit, 2);
            $sum += $lt;
            $insQl->execute([$qid, $lid, $unit, $lt]);
        }

        $sum = round($sum, 2);
        $conn->prepare(
            'UPDATE proc_quotation SET total_amount=? WHERE id=?',
        )->execute([$sum, $qid]);

        $conn->commit();
        procurementLog($conn, 'Quotation saved', $pr_id, [
            'new_value' => $supplier . ' · ' . number_format($sum, 2),
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Supplier quotation saved',
            'quotation_id' => $qid,
            'total_amount' => $sum,
        ]);
    } catch (PDOException $e) {
        $conn->rollBack();
        jsonResponse(['success' => false, 'message' => 'Save failed', 'error' => $e->getMessage()]);
    }
}

/**
 * @param array<string, mixed> $body
 */
function procDeleteQuotation(PDO $conn, array $body): void {
    $qid = isset($body['quotation_id']) ? (int) $body['quotation_id'] : 0;
    if ($qid <= 0) {
        jsonResponse(['success' => false, 'message' => 'quotation_id required']);
    }

    $pst = $conn->prepare('SELECT pr_id FROM proc_quotation WHERE id = ?');
    $pst->execute([$qid]);
    $prow = $pst->fetch(PDO::FETCH_ASSOC);
    if (!$prow) {
        jsonResponse(['success' => false, 'message' => 'Quotation not found']);
    }
    procurementEnsurePrAccess($conn, (int) $prow['pr_id']);

    $st = $conn->prepare(
        'SELECT id FROM proc_po WHERE quotation_id = ? LIMIT 1',
    );
    $st->execute([$qid]);
    if ($st->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Cannot delete quotation linked to a PO']);
    }

    $conn->prepare('DELETE FROM proc_quotation_line WHERE quotation_id=?')->execute([$qid]);
    $del = $conn->prepare(
        'DELETE FROM proc_quotation WHERE id=?',
    );
    $del->execute([$qid]);

    if ($del->rowCount() > 0) {
        procurementLog($conn, 'Quotation deleted', (int) $prow['pr_id']);
    }

    jsonResponse([
        'success' => $del->rowCount() > 0,
        'message' => $del->rowCount() > 0 ? 'Quotation deleted' : 'Quotation not found',
    ]);
}

/**
 * @param array<string, mixed> $body
 */
function procCreatePo(PDO $conn, array $body): void {
    $pr_id = isset($body['pr_id']) ? (int) $body['pr_id'] : 0;
    $quote_id = isset($body['quotation_id']) ? (int) $body['quotation_id'] : 0;
    if ($pr_id <= 0 || $quote_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'pr_id and quotation_id required']);
    }

    $st = $conn->prepare(
        'SELECT * FROM proc_quotation WHERE id=? AND pr_id=?',
    );
    $st->execute([$quote_id, $pr_id]);
    $qt = $st->fetch(PDO::FETCH_ASSOC);
    if (!$qt) {
        jsonResponse(['success' => false, 'message' => 'Quotation does not belong to PR']);
    }

    $chk = $conn->prepare(
        'SELECT id FROM proc_po WHERE pr_id=?',
    );
    $chk->execute([$pr_id]);
    if ($chk->fetch()) {
        jsonResponse(['success' => false, 'message' => 'PO already exists for this PR']);
    }

    $prSt = $conn->prepare('SELECT * FROM proc_pr WHERE id=?');
    $prSt->execute([$pr_id]);
    $pr_row = $prSt->fetch(PDO::FETCH_ASSOC);
    if (!$pr_row) {
        jsonResponse(['success' => false, 'message' => 'PR not found']);
    }
    if (($pr_row['status'] ?? '') !== 'quoting') {
        jsonResponse(['success' => false, 'message' => 'PR must be approved for canvass before a purchase order can be issued']);
    }

    procurementEnsurePrAccess($conn, $pr_id);

    $po_date = sanitizeInput((string) ($body['po_date'] ?? date('Y-m-d')));
    $mode = sanitizeInput((string) ($body['mode_of_procurement'] ?? 'SVP'));
    $place = sanitizeInput((string) ($body['place_of_delivery'] ?? AGENCY_NAME));
    $delivery = sanitizeInput((string) ($body['delivery_term'] ?? ''));
    $payment = sanitizeInput((string) ($body['payment_term'] ?? ''));
    $penalty = isset($body['penalty_clause']) ? trim((string) $body['penalty_clause']) : null;
    $signed = sanitizeInput((string) ($body['signed_by_display'] ?? ''));

    $po_number_manual = isset($body['po_number']) ? trim((string) $body['po_number']) : '';

    try {
        $conn->beginTransaction();

        $placeholder = '__NEW__';

        $insPo = $conn->prepare(
            'INSERT INTO proc_po (pr_id, quotation_id, po_number, po_date, supplier_name, supplier_address,
             mode_of_procurement, place_of_delivery, delivery_term, payment_term, total_amount,
             purpose, penalty_clause, pr_reference_display, signed_by_display) VALUES 
             (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        );
        $insPo->execute([
            $pr_id,
            $quote_id,
            $placeholder,
            $po_date,
            (string) $qt['supplier_name'],
            $qt['supplier_address'] ?: null,
            $mode ?: 'SVP',
            $place,
            $delivery ?: null,
            $payment ?: null,
            round((float) $qt['total_amount'], 2),
            $pr_row['purpose'] ?: null,
            $penalty,
            $pr_row['pr_number'] ?: null,
            $signed ?: null,
        ]);

        $po_row_id = (int) $conn->lastInsertId();

        if ($po_number_manual !== '') {
            $dup = $conn->prepare('SELECT id FROM proc_po WHERE po_number=? AND id<>?');
            $dup->execute([$po_number_manual, $po_row_id]);
            if ($dup->fetch()) {
                $conn->rollBack();

                jsonResponse(['success' => false, 'message' => 'PO number exists']);
            }
            $finalPoNum = $po_number_manual;
        } else {
            $finalPoNum = sprintf(
                'PO-%s-%06d',
                date('Y', strtotime($po_date)),
                $po_row_id,
            );
        }

        $conn->prepare('UPDATE proc_po SET po_number=? WHERE id=?')->execute([
            $finalPoNum,
            $po_row_id,
        ]);

        $plines = $conn->prepare(
            'SELECT prl.id AS pr_line_id, prl.line_no, prl.quantity, prl.unit, prl.description, prl.est_unit_cost,
                    ql.unit_price
             FROM proc_pr_line prl
             LEFT JOIN proc_quotation_line ql ON ql.pr_line_id = prl.id AND ql.quotation_id = ?
             WHERE prl.pr_id = ?
             ORDER BY prl.line_no ASC, prl.id ASC',
        );
        $plines->execute([$quote_id, $pr_id]);
        $rows = $plines->fetchAll(PDO::FETCH_ASSOC);
        $insPoLine = $conn->prepare(
            'INSERT INTO proc_po_line (po_id,line_no,pr_line_id,quantity,unit,description,unit_cost,amount) VALUES (?,?,?,?,?,?,?,?)',
        );
        $poSum = 0.0;
        $ln = 1;
        foreach ($rows as $r) {
            $qty = (float) $r['quantity'];
            $unitCost = (float) ($r['unit_price'] ?? 0);
            if ($unitCost <= 0) {
                $unitCost = (float) ($r['est_unit_cost'] ?? 0);
            }
            $unitCost = round($unitCost, 2);
            $amount = round($qty * $unitCost, 2);
            $poSum += $amount;
            $insPoLine->execute([
                $po_row_id,
                (int) ($r['line_no'] ?: $ln),
                (int) $r['pr_line_id'],
                $qty,
                $r['unit'],
                $r['description'],
                $unitCost,
                $amount,
            ]);
            ++$ln;
        }
        $conn->prepare('UPDATE proc_po SET total_amount=? WHERE id=?')->execute([
            round($poSum, 2),
            $po_row_id,
        ]);

        $conn->prepare(
            "UPDATE proc_pr SET status='po_issued' WHERE id=?",
        )->execute([$pr_id]);

        $conn->commit();

        $officeId = isset($pr_row['office_id']) ? (int) $pr_row['office_id'] : null;
        procurementNotify(
            $conn,
            'po_issued',
            'Purchase Order issued',
            sprintf(
                'PO %s was issued for PR %s.',
                $finalPoNum,
                (string) ($pr_row['pr_number'] ?? $pr_id)
            ),
            $pr_id,
            $officeId
        );

        procurementLog($conn, 'PO issued', $pr_id, [
            'office_id' => $officeId,
            'entity_label' => (string) ($pr_row['pr_number'] ?? ''),
            'new_value' => $finalPoNum,
        ]);
        jsonResponse([
            'success' => true,
            'message' => 'Purchase Order created',
            'po_id' => $po_row_id,
            'po_number' => $finalPoNum,
        ]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        jsonResponse(['success' => false, 'message' => 'PO failed', 'error' => $e->getMessage()]);
    }
}

/**
 * @param array<string, mixed> $body
 */
function procCreateAir(PDO $conn, array $body): void {
    $po_id = isset($body['po_id']) ? (int) $body['po_id'] : 0;
    if ($po_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'po_id required']);
    }

    $st = $conn->prepare(
        'SELECT po.*, pr.pr_number, pr.office_id FROM proc_po po JOIN proc_pr pr ON pr.id = po.pr_id WHERE po.id=?',
    );
    $st->execute([$po_id]);
    $po = $st->fetch(PDO::FETCH_ASSOC);
    if (!$po) {
        jsonResponse(['success' => false, 'message' => 'PO not found']);
    }

    procurementEnsurePrAccess($conn, (int) $po['pr_id']);

    $ch = $conn->prepare(
        'SELECT id FROM proc_air WHERE po_id=?',
    );
    $ch->execute([$po_id]);
    if ($ch->fetch()) {
        jsonResponse(['success' => false, 'message' => 'AIR already exists for this PO']);
    }

    $air_date = sanitizeInput((string) ($body['air_date'] ?? date('Y-m-d')));
    $si_dr = sanitizeInput((string) ($body['si_dr_notes'] ?? 'see attached'));
    $req_off = sanitizeInput((string) ($body['requisitioning_office_display'] ?? ''));
    $acc_ok = isset($body['acceptance_complete']) ? (int) (bool) $body['acceptance_complete'] : 1;
    $ins_ok = isset($body['inspection_ok']) ? (int) (bool) $body['inspection_ok'] : 1;

    $acc_p = sanitizeInput((string) ($body['acceptance_personnel'] ?? ''));
    $acc_t = sanitizeInput((string) ($body['acceptance_title'] ?? ''));
    $insp_p = sanitizeInput((string) ($body['inspection_personnel'] ?? ''));
    $insp_t = sanitizeInput((string) ($body['inspection_title'] ?? ''));
    $rcv = sanitizeInput((string) ($body['received_by'] ?? ''));
    $rcv_t = sanitizeInput((string) ($body['received_title'] ?? ''));
    $d_ins = sanitizeInput((string) ($body['date_inspected'] ?? ''));
    $d_sig = sanitizeInput((string) ($body['date_signed'] ?? ''));

    $officeName = '';
    if ($req_off === '') {
        $of = $conn->prepare(
            'SELECT o.name FROM proc_pr p JOIN offices o ON o.id=p.office_id WHERE p.id=?',
        );
        $of->execute([(int) $po['pr_id']]);
        $rw = $of->fetch(PDO::FETCH_ASSOC);
        $officeName = $rw['name'] ?? '';
    }

    try {
        $conn->beginTransaction();

        $ph = '__AIR__';
        $ins = $conn->prepare(
            'INSERT INTO proc_air (po_id, air_number, air_date, supplier_or_claimant, po_reference_display, si_dr_notes,
             requisitioning_office_display, acceptance_complete, inspection_ok,
             acceptance_personnel, acceptance_title, inspection_personnel, inspection_title,
             received_by, received_title, date_inspected, date_signed) VALUES
             (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        );
        $ins->execute([
            $po_id,
            $ph,
            $air_date,
            (string) $po['supplier_name'],
            $po['po_number'],
            $si_dr ?: 'see attached',
            $req_off ?: $officeName,
            $acc_ok ? 1 : 0,
            $ins_ok ? 1 : 0,
            $acc_p ?: null,
            $acc_t ?: null,
            $insp_p ?: null,
            $insp_t ?: null,
            $rcv ?: null,
            $rcv_t ?: null,
            $d_ins ?: null,
            $d_sig ?: null,
        ]);
        $air_id = (int) $conn->lastInsertId();
        $airNum = sprintf(
            'AIR-%s-%06d',
            date('Y', strtotime($air_date)),
            $air_id,
        );
        $conn->prepare('UPDATE proc_air SET air_number=? WHERE id=?')->execute([
            $airNum,
            $air_id,
        ]);

        $pol = $conn->prepare(
            'SELECT COALESCE(prl.line_no, pol.line_no) AS line_no,
                    COALESCE(prl.quantity, pol.quantity) AS quantity,
                    COALESCE(prl.unit, pol.unit) AS unit,
                    COALESCE(prl.description, pol.description) AS description
             FROM proc_po_line pol
             LEFT JOIN proc_pr_line prl ON prl.id = pol.pr_line_id
             WHERE pol.po_id=?
             ORDER BY COALESCE(prl.line_no, pol.line_no) ASC, pol.id ASC',
        );
        $pol->execute([$po_id]);
        $plrows = $pol->fetchAll(PDO::FETCH_ASSOC);
        $ial = $conn->prepare(
            'INSERT INTO proc_air_line (air_id, line_no, quantity, unit, description) VALUES (?,?,?,?,?)',
        );
        foreach ($plrows as $r) {
            $ial->execute([
                $air_id,
                (int) $r['line_no'],
                (float) $r['quantity'],
                $r['unit'],
                $r['description'],
            ]);
        }

        $conn->prepare(
            "UPDATE proc_pr SET status='completed' WHERE id=?",
        )->execute([(int) $po['pr_id']]);

        $conn->commit();

        try {
            consumableRestockFromCompletedPr($conn, (int) $po['pr_id']);
        } catch (Throwable $e) {
            // Ledger restock must not roll back a completed AIR.
        }

        $requestingOfficeId = isset($po['office_id']) ? (int) $po['office_id'] : null;
        $requestingOfficeName = $officeName;
        if ($requestingOfficeName === '' && $req_off !== '') {
            $requestingOfficeName = $req_off;
        }

        procurementNotify(
            $conn,
            'po_completed',
            'Purchase Order completed',
            sprintf(
                'PO %s for PR %s has been completed (AIR %s filed) and the item(s) have already been delivered to the GSO office. Property Custodian: please receive and record the item(s) in inventory.',
                (string) $po['po_number'],
                (string) $po['pr_number'],
                $airNum
            ),
            (int) $po['pr_id'],
            $requestingOfficeId
        );

        // GSO is the receiving/custodian office for every delivery, so it must
        // always be notified even when the requesting department is a
        // different office (Property Custodian receiving duties).
        $gsoOfficeId = procurementGsoOfficeId($conn);
        if ($gsoOfficeId !== null && $gsoOfficeId !== $requestingOfficeId) {
            procurementNotify(
                $conn,
                'po_delivered_gso',
                'Items delivered to GSO office',
                sprintf(
                    'PR %s (%s) is complete — PO %s items (AIR %s) have been delivered and received at the GSO office, ready for issuance.',
                    (string) $po['pr_number'],
                    $requestingOfficeName ?: 'Unknown office',
                    (string) $po['po_number'],
                    $airNum
                ),
                (int) $po['pr_id'],
                $gsoOfficeId
            );
        }

        procurementLog($conn, 'PR completed', (int) ($po['pr_id'] ?? 0), [
            'office_id' => isset($po['office_id']) ? (int) $po['office_id'] : null,
            'entity_label' => (string) ($po['pr_number'] ?? ''),
            'new_value' => $airNum,
        ]);
        jsonResponse([
            'success' => true,
            'message' => 'Acceptance / Inspection Report created',
            'air_id' => $air_id,
            'air_number' => $airNum,
        ]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        jsonResponse([
            'success' => false,
            'message' => 'AIR save failed',
            'error' => $e->getMessage(),
        ]);
    }
}
