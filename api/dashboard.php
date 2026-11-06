<?php
// Suppress errors to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once '../app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/unserviceable_helpers.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';

header('Content-Type: application/json');

// Dashboard is view-only, require view permission
PermissionHelper::require('view');

try {
    $conn = getDBConnection();
    unserviceableEnsureSchema($conn);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Database connection failed',
        'hint' => 'Please run scripts/setup-database.php to initialize the database'
    ]);
}

// Get dashboard statistics
$stats = [];

$itemCounts = $conn->query(
    "SELECT
        COUNT(*) AS total_items,
        SUM(category = 'SE') AS se_count,
        SUM(category = 'PPE') AS ppe_count,
        SUM(status = 'Active') AS active_count,
        SUM(status = 'Issued') AS issued_count,
        SUM(status = 'Unserviceable') AS unserviceable_count,
        SUM(status = 'Disposed') AS disposed_count,
        COALESCE(SUM(CASE WHEN UPPER(TRIM(CAST(unit_value AS CHAR))) = 'DONATION' THEN 0 ELSE unit_value END * quantity), 0) AS total_value
     FROM property_items"
)->fetch(PDO::FETCH_ASSOC) ?: [];
$stats['total_items'] = (int) ($itemCounts['total_items'] ?? 0);
$stats['se_count'] = (int) ($itemCounts['se_count'] ?? 0);
$stats['ppe_count'] = (int) ($itemCounts['ppe_count'] ?? 0);
$stats['active_count'] = (int) ($itemCounts['active_count'] ?? 0);
$stats['issued_count'] = (int) ($itemCounts['issued_count'] ?? 0);
$stats['unserviceable_count'] = (int) ($itemCounts['unserviceable_count'] ?? 0);
$stats['disposed_count'] = (int) ($itemCounts['disposed_count'] ?? 0);
$stats['total_value'] = $itemCounts['total_value'] ?? 0;

$stats['issuance_count'] = (int) $conn->query('SELECT COUNT(*) FROM issuance_records')->fetchColumn();
$stats['return_count'] = (int) $conn->query('SELECT COUNT(*) FROM return_records')->fetchColumn();

// Procurement KPI counts (drafts excluded for “submitted work”)
$stats['pr_count'] = 0;
$stats['pr_open_count'] = 0;
try {
    $prKpi = $conn->query(
        "SELECT
            COUNT(*) AS pr_count,
            SUM(status IN ('submitted','quoting','po_issued')) AS pr_open_count
         FROM proc_pr
         WHERE status NOT IN ('draft','needs_revision')"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['pr_count'] = (int) ($prKpi['pr_count'] ?? 0);
    $stats['pr_open_count'] = (int) ($prKpi['pr_open_count'] ?? 0);
} catch (Exception $e) {
    // Procurement optional
}

// Inventory count by office (for analytics bar)
$inventory_by_office = [];
try {
    $inventory_by_office = $conn->query(
        "SELECT o.id AS office_id, o.name AS office_name, o.code AS office_code,
                COUNT(p.id) AS item_count,
                COALESCE(SUM(CASE WHEN UPPER(TRIM(CAST(p.unit_value AS CHAR))) = 'DONATION' THEN 0 ELSE p.unit_value END * p.quantity), 0) AS total_value
         FROM offices o
         LEFT JOIN property_items p ON p.office_id = o.id
         WHERE o.is_active = 1
         GROUP BY o.id, o.name, o.code
         HAVING item_count > 0
         ORDER BY item_count DESC, o.name ASC
         LIMIT 12"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inventory_by_office = [];
}

// PR status mix (doughnut)
$pr_status_mix = [];
try {
    $pr_status_mix = $conn->query(
        "SELECT status, COUNT(*) AS total
         FROM proc_pr
         WHERE status NOT IN ('draft','needs_revision')
         GROUP BY status
         ORDER BY FIELD(status,'submitted','quoting','po_issued','completed')"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pr_status_mix = [];
}

/**
 * Normalize PR line item label: for consumable PRs Article is often just "Consumable",
 * so prefer Description (the real item name).
 */
$prItemLabelSql = consumablePrLineLabelSql('l');

// Top requested items across all departments (non-draft PRs)
$top_requested_items = [];
$dept_item_frequency = [];
$dept_frequency_offices = [];
try {
    $conn->query('SELECT 1 FROM proc_pr_line LIMIT 1');

    $sql = "SELECT {$prItemLabelSql} AS item_label,
                COUNT(*) AS request_count,
                COALESCE(SUM(l.quantity), 0) AS total_qty,
                COUNT(DISTINCT p.office_id) AS office_count,
                MAX(p.pr_date) AS last_requested
            FROM proc_pr_line l
            INNER JOIN proc_pr p ON p.id = l.pr_id
            WHERE p.status NOT IN ('draft','needs_revision')
            GROUP BY {$prItemLabelSql}
            ORDER BY request_count DESC, total_qty DESC
            LIMIT 12";
    $top_requested_items = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // Per-department: each office’s most frequent PR line items
    $sql = "SELECT o.id AS office_id, o.name AS office_name, o.code AS office_code,
                {$prItemLabelSql} AS item_label,
                COUNT(*) AS request_count,
                COALESCE(SUM(l.quantity), 0) AS total_qty,
                MAX(p.pr_date) AS last_requested
            FROM proc_pr_line l
            INNER JOIN proc_pr p ON p.id = l.pr_id
            INNER JOIN offices o ON o.id = p.office_id
            WHERE p.status NOT IN ('draft','needs_revision')
            GROUP BY o.id, o.name, o.code, {$prItemLabelSql}
            ORDER BY o.name ASC, request_count DESC, total_qty DESC";
    $rawDept = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $byOffice = [];
    foreach ($rawDept as $row) {
        $oid = (int) $row['office_id'];
        if (!isset($byOffice[$oid])) {
            $byOffice[$oid] = [
                'office_id' => $oid,
                'office_name' => $row['office_name'],
                'office_code' => $row['office_code'],
                'items' => [],
            ];
        }
        // Keep top 8 items per department for charts / lists
        if (count($byOffice[$oid]['items']) < 8) {
            $byOffice[$oid]['items'][] = [
                'item_label' => $row['item_label'],
                'request_count' => (int) $row['request_count'],
                'total_qty' => (float) $row['total_qty'],
                'last_requested' => $row['last_requested'],
            ];
        }
    }
    $dept_item_frequency = array_values($byOffice);

    // Dropdown: every active office (even with zero PR data yet)
    $dept_frequency_offices = $conn->query(
        "SELECT id AS office_id, name AS office_name, code AS office_code
         FROM offices
         WHERE is_active = 1
         ORDER BY name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top_requested_items = [];
    $dept_item_frequency = [];
    $dept_frequency_offices = [];
}

// Fallback: still show all offices in the dropdown if PR analytics failed
if (!$dept_frequency_offices) {
    try {
        $dept_frequency_offices = $conn->query(
            "SELECT id AS office_id, name AS office_name, code AS office_code
             FROM offices
             WHERE is_active = 1
             ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $dept_frequency_offices = [];
    }
}

// Recent items encoded (latest 5 property items)
$sql = "SELECT p.id, p.property_number, p.article, p.description, p.category, p.created_at,
               o.name AS office_name
        FROM property_items p
        LEFT JOIN offices o ON p.office_id = o.id
        ORDER BY p.created_at DESC, p.id DESC
        LIMIT 5";
$recent_items = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Recent transactions (latest 5 PAR/ICS). Item text may live on issuance_items
// when property_item_id on the header row is null.
$sql = "SELECT i.id, i.form_type, i.form_number, i.issued_to, i.issue_date, i.created_at,
               o.name AS office_name,
               COALESCE(
                   NULLIF(TRIM(p_direct.description), ''),
                   (
                       SELECT NULLIF(TRIM(p2.description), '')
                       FROM issuance_items ii
                       INNER JOIN property_items p2 ON p2.id = ii.property_item_id
                       WHERE ii.issuance_id = i.id
                       ORDER BY ii.id ASC
                       LIMIT 1
                   ),
                   (
                       SELECT CONCAT(
                           COALESCE(NULLIF(TRIM(icl.item_name), ''), 'Consumable'),
                           IF((SELECT COUNT(*) FROM issuance_consumable_lines x WHERE x.issuance_id = i.id) > 1,
                              CONCAT(' (+', (SELECT COUNT(*) FROM issuance_consumable_lines x WHERE x.issuance_id = i.id) - 1, ' more)'),
                              '')
                       )
                       FROM issuance_consumable_lines icl
                       WHERE icl.issuance_id = i.id
                       ORDER BY icl.id ASC
                       LIMIT 1
                   ),
                   CASE WHEN COALESCE(i.is_consumable, 0) = 1 THEN 'Consumable issuance' ELSE NULL END,
                   i.form_type
               ) AS description,
               COALESCE(
                   NULLIF(TRIM(p_direct.property_number), ''),
                   (
                       SELECT NULLIF(TRIM(p3.property_number), '')
                       FROM issuance_items ii3
                       INNER JOIN property_items p3 ON p3.id = ii3.property_item_id
                       WHERE ii3.issuance_id = i.id
                       ORDER BY ii3.id ASC
                       LIMIT 1
                   )
               ) AS property_number
        FROM issuance_records i
        LEFT JOIN property_items p_direct ON p_direct.id = i.property_item_id
        LEFT JOIN offices o ON o.id = i.office_id
        ORDER BY i.created_at DESC, i.id DESC
        LIMIT 5";
try {
    $recent_transactions = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $sql = "SELECT i.id, i.form_type, i.form_number, i.issued_to, i.issue_date, i.created_at,
                   o.name AS office_name,
                   COALESCE(
                       NULLIF(TRIM(p_direct.description), ''),
                       (
                           SELECT NULLIF(TRIM(p2.description), '')
                           FROM issuance_items ii
                           INNER JOIN property_items p2 ON p2.id = ii.property_item_id
                           WHERE ii.issuance_id = i.id
                           ORDER BY ii.id ASC
                           LIMIT 1
                       ),
                       i.form_type
                   ) AS description,
                   COALESCE(
                       NULLIF(TRIM(p_direct.property_number), ''),
                       (
                           SELECT NULLIF(TRIM(p3.property_number), '')
                           FROM issuance_items ii3
                           INNER JOIN property_items p3 ON p3.id = ii3.property_item_id
                           WHERE ii3.issuance_id = i.id
                           ORDER BY ii3.id ASC
                           LIMIT 1
                       )
                   ) AS property_number
            FROM issuance_records i
            LEFT JOIN property_items p_direct ON p_direct.id = i.property_item_id
            LEFT JOIN offices o ON o.id = i.office_id
            ORDER BY i.created_at DESC, i.id DESC
            LIMIT 5";
    $recent_transactions = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Items needing attention (damaged or unserviceable)
$sql = "SELECT p.*, o.name as office_name 
        FROM property_items p 
        LEFT JOIN offices o ON p.office_id = o.id 
        WHERE (p.condition_status = 'Damaged' OR p.status = 'Unserviceable')
          AND p.status <> 'Disposed'
        ORDER BY p.updated_at DESC 
        LIMIT 10";
$result = $conn->query($sql);
$attention_items = $result->fetchAll(PDO::FETCH_ASSOC);

// Unserviceable items still pending disposal action ("Needs Attention" — GSO Fixer follow-up)
$needs_attention = [];
try {
    $sql = "SELECT u.id, u.reason, u.remarks, u.marked_date, u.disposal_status, u.repair_status,
                p.property_number, p.description, p.category, o.name AS office_name
            FROM unserviceable_records u
            LEFT JOIN property_items p ON u.property_item_id = p.id
            LEFT JOIN offices o ON p.office_id = o.id
            WHERE COALESCE(u.repair_status, 'Pending') = 'Pending'
              AND COALESCE(u.disposal_status, 'Pending') <> 'Disposed'
              AND COALESCE(p.status, '') <> 'Disposed'
            ORDER BY u.marked_date DESC, u.created_at DESC
            LIMIT 15";
    $result = $conn->query($sql);
    $needs_attention = $result->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $needs_attention = [];
}

// Procurement requests grouped by office (admin visibility into what offices have submitted a PR)
$procurement_by_office = [];
$procurement_recent = [];
try {
    $conn->query('SELECT 1 FROM proc_pr LIMIT 1');

    $sql = "SELECT o.id AS office_id, o.name AS office_name,
                COUNT(p.id) AS total_pr,
                0 AS draft_count,
                SUM(p.status = 'submitted') AS submitted_count,
                SUM(p.status = 'quoting') AS quoting_count,
                SUM(p.status = 'po_issued') AS po_issued_count,
                SUM(p.status = 'completed') AS completed_count,
                MAX(p.pr_date) AS last_pr_date
            FROM proc_pr p
            JOIN offices o ON o.id = p.office_id
            WHERE p.status NOT IN ('draft','needs_revision')
            GROUP BY o.id, o.name
            ORDER BY total_pr DESC, o.name ASC";
    $result = $conn->query($sql);
    $procurement_by_office = $result->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT p.id, p.pr_number, p.pr_date, p.status, o.name AS office_name
            FROM proc_pr p
            JOIN offices o ON o.id = p.office_id
            WHERE p.status NOT IN ('draft','needs_revision')
            ORDER BY p.created_at DESC
            LIMIT 8";
    $result = $conn->query($sql);
    $procurement_recent = $result->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Procurement tables not installed yet; leave lists empty.
    $procurement_by_office = [];
    $procurement_recent = [];
}

// ── Analytics trends (combined multi-line chart: items, issuances, returns, procurement PRs) ──
function dashboardBuildPeriods(string $granularity, int $count): array {
    $periods = [];
    for ($i = $count - 1; $i >= 0; $i--) {
        if ($granularity === 'yearly') {
            $periods[] = date('Y', strtotime("-$i years"));
        } else {
            $periods[] = date('Y-m', strtotime("-$i months"));
        }
    }
    return $periods;
}

function dashboardCountsByPeriod(PDO $conn, string $table, string $dateCol, string $granularity, int $count): array {
    $fmt = $granularity === 'yearly' ? '%Y' : '%Y-%m';
    $since = $granularity === 'yearly' ? "-" . ($count - 1) . " years" : "-" . ($count - 1) . " months";
    $sinceDate = date('Y-m-01 00:00:00', strtotime($since));
    $out = [];
    try {
        $sql = "SELECT DATE_FORMAT($dateCol, '$fmt') AS period, COUNT(*) AS total
                FROM $table
                WHERE $dateCol >= ?
                GROUP BY period";
        $st = $conn->prepare($sql);
        $st->execute([$sinceDate]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['period']] = (int) $row['total'];
        }
    } catch (Exception $e) {
        // Table/column may not exist yet; return empty counts.
    }
    return $out;
}

function dashboardBuildTrend(PDO $conn, string $granularity, int $count): array {
    $periods = dashboardBuildPeriods($granularity, $count);

    $items = dashboardCountsByPeriod($conn, 'property_items', 'created_at', $granularity, $count);
    $issuances = dashboardCountsByPeriod($conn, 'issuance_records', 'created_at', $granularity, $count);
    $returns = dashboardCountsByPeriod($conn, 'return_records', 'created_at', $granularity, $count);
    $procurement = [];
    try {
        $conn->query('SELECT 1 FROM proc_pr LIMIT 1');
        $procurement = dashboardCountsByPeriod($conn, 'proc_pr', 'created_at', $granularity, $count);
    } catch (Exception $e) {
        $procurement = [];
    }

    return [
        'labels' => $periods,
        'items_encoded' => array_map(fn ($p) => $items[$p] ?? 0, $periods),
        'issuances' => array_map(fn ($p) => $issuances[$p] ?? 0, $periods),
        'returns' => array_map(fn ($p) => $returns[$p] ?? 0, $periods),
        'procurement_prs' => array_map(fn ($p) => $procurement[$p] ?? 0, $periods),
    ];
}

$trends = [
    'monthly' => dashboardBuildTrend($conn, 'monthly', 12),
    'yearly' => dashboardBuildTrend($conn, 'yearly', 5),
];

$response = [
    'success' => true,
    'stats' => $stats,
    'recent_items' => $recent_items,
    'recent_transactions' => $recent_transactions,
    'attention_items' => $attention_items,
    'procurement_by_office' => $procurement_by_office,
    'procurement_recent' => $procurement_recent,
    'needs_attention' => $needs_attention,
    'trends' => $trends,
    'inventory_by_office' => $inventory_by_office,
    'pr_status_mix' => $pr_status_mix,
    'top_requested_items' => $top_requested_items,
    'dept_item_frequency' => $dept_item_frequency,
    'dept_frequency_offices' => $dept_frequency_offices,
];

jsonResponse($response);
?>
