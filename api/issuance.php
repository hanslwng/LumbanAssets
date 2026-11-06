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
require_once dirname(__DIR__) . '/includes/pr_inventory_groups.php';
require_once dirname(__DIR__) . '/includes/issuance_helpers.php';
require_once dirname(__DIR__) . '/includes/consumable_schema.php';
require_once dirname(__DIR__) . '/includes/consumable_stock.php';
require_once dirname(__DIR__) . '/includes/consumable_issuance.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();
    consumableEnsureSchema($conn);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Database connection failed',
        'hint' => 'Please run scripts/setup-database.php to initialize the database'
    ]);
}

switch ($method) {
    case 'GET':
        PermissionHelper::require('view');
        getIssuances($conn);
        break;
    case 'POST':
        PermissionHelper::require('create');
        createIssuance($conn);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

function getIssuances($conn) {
    issuanceEnsureItemsTable($conn);
    consumableEnsureSchema($conn);
    $form_type = isset($_GET['form_type']) ? sanitizeInput($_GET['form_type']) : '';
    $office_id = isset($_GET['office_id']) ? intval($_GET['office_id']) : 0;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $categoryFilter = isset($_GET['category']) ? strtolower(trim((string) $_GET['category'])) : 'all';
    if (!in_array($categoryFilter, ['all', 'consumable', 'non_consumable', 'non-consumable'], true)) {
        $categoryFilter = 'all';
    }
    if ($categoryFilter === 'non-consumable') {
        $categoryFilter = 'non_consumable';
    }
    
    if (isset($_GET['pending_pr'])) {
        getPendingPrIssueLines($conn);
        return;
    }

    $scopeOffice = issuanceDepartmentOfficeId();
    if ($scopeOffice === -1) {
        jsonResponse(['success' => true, 'data' => $id > 0 ? null : []]);
    }
    if ($scopeOffice !== null) {
        $office_id = $scopeOffice;
    }

    if ($id > 0) {
        $sql = "SELECT i.*, p.property_number, p.description, p.category, o.name as office_name 
                FROM issuance_records i
                LEFT JOIN property_items p ON i.property_item_id = p.id
                LEFT JOIN offices o ON i.office_id = o.id
                WHERE i.id = ?";
        $params = [$id];
        if ($office_id > 0) {
            $sql .= ' AND i.office_id = ?';
            $params[] = $office_id;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $issuance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($issuance) {
            if (!empty($issuance['is_consumable'])) {
                $cLines = consumableLoadIssuanceLines($conn, (int) $issuance['id']);
                $issuance['items'] = array_map(static function ($l) {
                    return [
                        'property_number' => '—',
                        'description' => $l['item_name'],
                        'article' => $l['item_name'],
                        'quantity' => $l['quantity'],
                        'unit_value' => $l['unit_cost'],
                        'category' => 'Consumable',
                    ];
                }, $cLines);
                $issuance['item_count'] = count($cLines);
                $issuance['description'] = implode('; ', array_column($cLines, 'item_name'));
                $issuance['property_number'] = '—';
                $issuance['category_label'] = 'Consumable';
            } else {
                $items = issuanceLoadItems($conn, (int) $issuance['id']);
                $issuance['items'] = $items;
                $issuance['item_count'] = count($items);
                $issuance['category_label'] = 'Non-consumable';
            }
            jsonResponse(['success' => true, 'data' => $issuance]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Issuance not found']);
        }
    } else {
        $sql = "SELECT i.*, p.property_number, p.description, p.category, o.name as office_name 
                FROM issuance_records i
                LEFT JOIN property_items p ON i.property_item_id = p.id
                LEFT JOIN offices o ON i.office_id = o.id
                WHERE 1=1";
        
        $params = [];
        
        if ($form_type) {
            $sql .= " AND i.form_type = ?";
            $params[] = $form_type;
        }

        if ($categoryFilter === 'consumable') {
            $sql .= ' AND COALESCE(i.is_consumable, 0) = 1';
        } elseif ($categoryFilter === 'non_consumable') {
            $sql .= ' AND COALESCE(i.is_consumable, 0) = 0';
        }
        
        if ($office_id > 0) {
            $sql .= " AND i.office_id = ?";
            $params[] = $office_id;
        }
        
        if ($search) {
            $sql .= " AND (i.issued_to LIKE ? OR i.form_number LIKE ? OR p.description LIKE ?
                     OR o.name LIKE ?
                     OR EXISTS (
                        SELECT 1 FROM issuance_items ii
                        INNER JOIN property_items p2 ON p2.id = ii.property_item_id
                        WHERE ii.issuance_id = i.id
                          AND (p2.description LIKE ? OR p2.property_number LIKE ? OR p2.article LIKE ?)
                     )
                     OR EXISTS (
                        SELECT 1 FROM issuance_consumable_lines icl
                        WHERE icl.issuance_id = i.id AND icl.item_name LIKE ?
                     ))";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY i.id DESC, i.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $issuances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = issuanceListItemMap($conn, array_map(static function ($row) {
            return (int) ($row['id'] ?? 0);
        }, $issuances));
        foreach ($issuances as &$row) {
            $issId = (int) ($row['id'] ?? 0);
            $items = $map[$issId] ?? [];
            $cLines = [];
            if (!empty($row['is_consumable']) || !$items) {
                try {
                    $cLines = consumableLoadIssuanceLines($conn, $issId);
                } catch (Throwable $e) {
                    $cLines = [];
                }
            }
            $isCons = !empty($row['is_consumable']) || $cLines;
            if ($isCons && $cLines) {
                $row['is_consumable'] = 1;
                $row['item_count'] = count($cLines);
                $row['property_number'] = '—';
                $names = array_filter(array_map(static function ($l) {
                    return trim((string) ($l['item_name'] ?? ''));
                }, $cLines));
                $row['description'] = $names ? implode('; ', $names) : 'Consumable release';
                $row['items'] = $cLines;
                $row['category_label'] = 'Consumable';
            } else {
                $row['is_consumable'] = 0;
                $row['item_count'] = $items ? count($items) : ((int) ($row['property_item_id'] ?? 0) > 0 ? 1 : 0);
                if ($items) {
                    $row['property_number'] = implode(', ', array_filter(array_column($items, 'property_number'))) ?: ($row['property_number'] ?? '—');
                    $row['description'] = implode('; ', array_filter(array_column($items, 'description'))) ?: ($row['description'] ?? '—');
                } else {
                    $row['property_number'] = $row['property_number'] ?: '—';
                    $row['description'] = $row['description'] ?: '—';
                }
                $row['items'] = $items;
                $row['category_label'] = 'Non-consumable';
            }
        }
        unset($row);
        
        jsonResponse(['success' => true, 'data' => $issuances]);
    }
}

function getPendingPrIssueLines($conn) {
    prEnsureLineInventoryColumns($conn);
    issuanceEnsureItemsTable($conn);
    $scopeOffice = issuanceDepartmentOfficeId();
    if ($scopeOffice === -1) {
        jsonResponse(['success' => true, 'data' => []]);
    }
    $sql = "SELECT l.id AS line_id, l.pr_id, l.line_no, l.quantity, l.unit, l.description,
                   l.est_unit_cost, l.est_line_total, l.article, l.item_group, l.property_item_id,
                   p.pr_number, p.office_id, p.pr_section, p.purpose, p.status, p.pr_date, p.updated_at,
                   COALESCE(p.is_consumable, 0) AS is_consumable,
                   o.name AS office_name,
                   pol.unit_cost AS po_unit_cost
            FROM proc_pr_line l
            INNER JOIN proc_pr p ON p.id = l.pr_id
            INNER JOIN offices o ON o.id = p.office_id
            LEFT JOIN proc_po po ON po.id = (
                SELECT po2.id FROM proc_po po2 WHERE po2.pr_id = p.id ORDER BY po2.id DESC LIMIT 1
            )
            LEFT JOIN proc_po_line pol ON pol.po_id = po.id AND pol.pr_line_id = l.id
            WHERE p.status = 'completed'";
    $params = [];
    if ($scopeOffice !== null) {
        $sql .= ' AND p.office_id = ?';
        $params[] = $scopeOffice;
    }
    $sql .= ' ORDER BY p.updated_at DESC, l.line_no ASC, l.id ASC';
    try {
        $stmt = $params ? $conn->prepare($sql) : $conn->query($sql);
        if ($params) {
            $stmt->execute($params);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        try {
            $sql = "SELECT l.id AS line_id, l.pr_id, l.line_no, l.quantity, l.unit, l.description,
                           l.est_unit_cost, l.est_line_total, l.article, l.item_group, l.property_item_id,
                           p.pr_number, p.office_id, p.pr_section, p.purpose, p.status, p.pr_date, p.updated_at,
                           COALESCE(p.is_consumable, 0) AS is_consumable,
                           o.name AS office_name,
                           NULL AS po_unit_cost
                    FROM proc_pr_line l
                    INNER JOIN proc_pr p ON p.id = l.pr_id
                    INNER JOIN offices o ON o.id = p.office_id
                    WHERE p.status = 'completed'";
            $params = [];
            if ($scopeOffice !== null) {
                $sql .= ' AND p.office_id = ?';
                $params[] = $scopeOffice;
            }
            $sql .= ' ORDER BY p.updated_at DESC, l.line_no ASC, l.id ASC';
            $stmt = $params ? $conn->prepare($sql) : $conn->query($sql);
            if ($params) {
                $stmt->execute($params);
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            jsonResponse(['success' => false, 'message' => 'Could not load completed purchase requests']);
            return;
        }
    }
    $threshold = function_exists('settingsPpeThreshold') ? (float) settingsPpeThreshold($conn) : 50000.0;
    $grouped = [];
    $consIssuedCache = [];
    foreach ($rows as $row) {
        $prId = (int) $row['pr_id'];
        $isCons = !empty($row['is_consumable']) || strcasecmp(trim((string) ($row['pr_section'] ?? '')), 'Consumable') === 0;
        if ($isCons && !isset($consIssuedCache[$prId])) {
            $consIssuedCache[$prId] = consumableLinesAlreadyIssuedMap($conn, $prId);
        }
        $unit = issuanceLineUnitCost($conn, $row);
        $already = $isCons
            ? !empty($consIssuedCache[$prId][(int) $row['line_id']])
            : !empty($row['property_item_id']);
        $line = [
            'line_id' => (int) $row['line_id'],
            'line_no' => (int) $row['line_no'],
            'quantity' => issuanceFormatQty($row['quantity']),
            'unit' => $row['unit'],
            'description' => $row['description'],
            'article' => trim((string) ($row['article'] ?? '')),
            'item_group' => $isCons
                ? 'Consumable'
                : prInventoryNormalizeGroup((string) ($row['item_group'] ?: $row['pr_section'] ?: 'OTHERS')),
            'est_unit_cost' => $unit,
            'category' => $isCons ? 'Consumable' : ($unit >= $threshold ? 'PPE' : 'SE'),
            'already_issued' => $already,
            'is_consumable' => $isCons ? 1 : 0,
        ];
        if (!isset($grouped[$prId])) {
            $existing = issuanceExistingForPr($conn, $prId);
            $suggested = $existing
                ? (string) ($existing['form_number'] ?? '')
                : issuanceUniqueFormNumber($conn, issuanceFormNumberFromPr((string) ($row['pr_number'] ?? '')));
            $grouped[$prId] = [
                'pr_id' => $prId,
                'pr_number' => $row['pr_number'],
                'office_id' => (int) $row['office_id'],
                'office_name' => $row['office_name'],
                'pr_section' => $isCons ? 'Consumable' : prInventoryNormalizeGroup((string) ($row['pr_section'] ?: $line['item_group'])),
                'purpose' => $row['purpose'],
                'pr_date' => substr((string) ($row['pr_date'] ?? ''), 0, 10),
                'completed_date' => substr((string) ($row['updated_at'] ?? $row['pr_date'] ?? ''), 0, 10),
                'is_consumable' => $isCons ? 1 : 0,
                'existing_issuance_id' => $existing ? (int) $existing['id'] : 0,
                'existing_form_number' => $existing ? (string) ($existing['form_number'] ?? '') : '',
                'suggested_form_number' => $suggested,
                'existing_issued_to' => $existing ? (string) ($existing['issued_to'] ?? '') : '',
                'existing_position' => $existing ? (string) ($existing['position'] ?? '') : '',
                'lines' => [],
                'pending_count' => 0,
                'total_count' => 0,
            ];
        }
        $grouped[$prId]['lines'][] = $line;
        $grouped[$prId]['total_count']++;
        if (!$line['already_issued']) {
            $grouped[$prId]['pending_count']++;
        }
    }
    $out = [];
    foreach ($grouped as $pr) {
        if ($pr['pending_count'] <= 0) {
            continue;
        }
        if (!empty($pr['is_consumable'])) {
            $pr['category'] = 'Consumable';
        } else {
            $cats = array_unique(array_column($pr['lines'], 'category'));
            $pr['category'] = count($cats) === 1 ? $cats[0] : 'MIXED';
        }
        $out[] = $pr;
    }
    usort($out, static function ($a, $b) {
        $d = strcmp((string) ($b['completed_date'] ?? ''), (string) ($a['completed_date'] ?? ''));
        if ($d !== 0) {
            return $d;
        }
        return ((int) ($b['pr_id'] ?? 0)) - ((int) ($a['pr_id'] ?? 0));
    });
    jsonResponse(['success' => true, 'data' => $out]);
}

function createIssuance($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = [];
    }

    $pr_line_id = intval($data['proc_pr_line_id'] ?? $data['pr_line_id'] ?? 0);
    $issued_to = sanitizeInput($data['issued_to'] ?? '');
    $position = sanitizeInput($data['position'] ?? '');
    $issue_date = sanitizeInput($data['issue_date'] ?? date('Y-m-d'));
    $form_number = sanitizeInput($data['form_number'] ?? '');
    $remarks = sanitizeInput($data['remarks'] ?? '');
    $proc_pr_id = isset($data['proc_pr_id']) ? intval($data['proc_pr_id']) : 0;

    prEnsureLineInventoryColumns($conn);
    issuanceEnsureItemsTable($conn);
    if (!function_exists('auditLogEnsureSchema')) {
        require_once dirname(__DIR__) . '/includes/audit_log.php';
    }
    auditLogEnsureSchema($conn);

    if ($proc_pr_id <= 0 && $pr_line_id > 0) {
        $find = $conn->prepare('SELECT pr_id FROM proc_pr_line WHERE id = ?');
        $find->execute([$pr_line_id]);
        $proc_pr_id = (int) $find->fetchColumn();
    }
    if ($proc_pr_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Select a completed purchase request']);
    }

    $scopeOffice = issuanceDepartmentOfficeId();
    if ($scopeOffice === -1) {
        jsonResponse(['success' => false, 'message' => 'Your account has no department on file.']);
    }

    $posErr = lgueOptionalTextError($position, 'Position');
    if ($posErr !== null) {
        jsonResponse(['success' => false, 'message' => $posErr]);
    }
    $remErr = lgueOptionalTextError($remarks, 'Remarks');
    if ($remErr !== null) {
        jsonResponse(['success' => false, 'message' => $remErr]);
    }

    // Consumable PRs: release stock + tagged issuance lines — never create property_items.
    if (consumablePrIsConsumable($conn, $proc_pr_id)) {
        $nameErr = lgueNameError($issued_to, 'Issued to');
        if ($nameErr !== null) {
            jsonResponse(['success' => false, 'message' => $nameErr]);
        }
        try {
            $conn->beginTransaction();
            $lock = $conn->prepare('SELECT id FROM proc_pr WHERE id = ? FOR UPDATE');
            $lock->execute([$proc_pr_id]);
            if (!$lock->fetch()) {
                $conn->rollBack();
                jsonResponse(['success' => false, 'message' => 'Purchase request not found']);
            }
            $prSt = $conn->prepare('SELECT office_id, status FROM proc_pr WHERE id = ?');
            $prSt->execute([$proc_pr_id]);
            $pr = $prSt->fetch(PDO::FETCH_ASSOC);
            if (!$pr || (string) ($pr['status'] ?? '') !== 'completed') {
                $conn->rollBack();
                jsonResponse(['success' => false, 'message' => 'Purchase request must be completed']);
            }
            if ($scopeOffice !== null && (int) $pr['office_id'] !== $scopeOffice) {
                $conn->rollBack();
                jsonResponse(['success' => false, 'message' => 'Not allowed for this department']);
            }
            $result = consumableIssueFromPr(
                $conn,
                $proc_pr_id,
                $issued_to,
                $position,
                $issue_date,
                $remarks,
                $form_number
            );
            $conn->commit();
            if (empty($result['already']) && function_exists('activityLogParIssued')) {
                activityLogParIssued(
                    $conn,
                    $proc_pr_id,
                    (string) ($result['form_number'] ?? $form_number),
                    $issued_to,
                    (int) ($result['attached'] ?? 0),
                    isset($pr['office_id']) ? (int) $pr['office_id'] : null,
                    true,
                    false
                );
            }
            jsonResponse([
                'success' => true,
                'already_issued' => !empty($result['already']),
                'message' => !empty($result['already'])
                    ? ('This consumable purchase is already on ' . $result['form_number'])
                    : ('Released ' . (int) $result['attached'] . ' consumable line(s)'),
                'id' => (int) $result['issuance_id'],
                'form_type' => 'PAR',
                'item_count' => (int) $result['attached'],
                'is_consumable' => 1,
            ]);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            jsonResponse(['success' => false, 'message' => $e->getMessage() ?: 'Failed to issue consumables']);
        }
    }

    $form_type = 'PAR';
    $attached = 0;
    $issuance_id = 0;
    $wasExisting = false;

    try {
        $conn->beginTransaction();
        $lock = $conn->prepare('SELECT id FROM proc_pr WHERE id = ? FOR UPDATE');
        $lock->execute([$proc_pr_id]);
        if (!$lock->fetch()) {
            $conn->rollBack();
            jsonResponse(['success' => false, 'message' => 'Purchase request not found']);
        }
        $prSt = $conn->prepare(
            'SELECT p.*, o.name AS office_name
             FROM proc_pr p
             INNER JOIN offices o ON o.id = p.office_id
             WHERE p.id = ?'
        );
        $prSt->execute([$proc_pr_id]);
        $pr = $prSt->fetch(PDO::FETCH_ASSOC);
        if (!$pr) {
            $conn->rollBack();
            jsonResponse(['success' => false, 'message' => 'Purchase request not found']);
        }
        if (($pr['status'] ?? '') !== 'completed') {
            $conn->rollBack();
            jsonResponse(['success' => false, 'message' => 'Only completed purchase requests can be issued']);
        }
        if ($scopeOffice !== null && (int) $pr['office_id'] !== $scopeOffice) {
            $conn->rollBack();
            jsonResponse(['success' => false, 'message' => 'You can only issue purchase requests for your department']);
        }

        $pendingSt = $conn->prepare(
            'SELECT COUNT(*) FROM proc_pr_line WHERE pr_id = ? AND (property_item_id IS NULL OR property_item_id = 0)'
        );
        $pendingSt->execute([$proc_pr_id]);
        $pendingCount = (int) $pendingSt->fetchColumn();
        $existing = issuanceExistingForPr($conn, $proc_pr_id);
        $wasExisting = (bool) $existing;

        if ($pendingCount <= 0 && $existing) {
            $issuance_id = (int) $existing['id'];
            $conn->commit();
            $itemCount = count(issuanceLoadItems($conn, $issuance_id));
            jsonResponse([
                'success' => true,
                'already_issued' => true,
                'message' => 'This purchase is already on PAR ' . (string) ($existing['form_number'] ?? ''),
                'id' => $issuance_id,
                'form_type' => $form_type,
                'item_count' => $itemCount,
            ]);
        }
        if ($pendingCount <= 0) {
            $conn->rollBack();
            jsonResponse(['success' => false, 'message' => 'No remaining items on this purchase request']);
        }

        if ($existing) {
            $issued_to = (string) ($existing['issued_to'] ?? $issued_to);
            $form_number = (string) ($existing['form_number'] ?? $form_number);
            $position = (string) ($existing['position'] ?? $position);
            $issue_date = (string) ($existing['issue_date'] ?? $issue_date);
            if ($remarks === '') {
                $remarks = (string) ($existing['remarks'] ?? '');
            }
            $issuance_id = (int) $existing['id'];
        } else {
            $nameErr = lgueNameError($issued_to, 'Issued to');
            if ($nameErr !== null) {
                $conn->rollBack();
                jsonResponse(['success' => false, 'message' => $nameErr]);
            }
            if (trim($form_number) === '') {
                $form_number = issuanceFormNumberFromPr((string) ($pr['pr_number'] ?? ''));
            }
            $form_number = issuanceUniqueFormNumber($conn, $form_number);
            $formErr = lgueFormRefError($form_number);
            if ($formErr !== null) {
                $conn->rollBack();
                jsonResponse(['success' => false, 'message' => $formErr]);
            }

            $office_id = (int) $pr['office_id'];
            $firstLine = $conn->prepare(
                'SELECT l.*, p.pr_number, p.office_id, p.pr_section
                 FROM proc_pr_line l
                 INNER JOIN proc_pr p ON p.id = l.pr_id
                 WHERE l.pr_id = ? AND (l.property_item_id IS NULL OR l.property_item_id = 0)
                 ORDER BY l.line_no ASC, l.id ASC
                 LIMIT 1'
            );
            $firstLine->execute([$proc_pr_id]);
            $line = $firstLine->fetch(PDO::FETCH_ASSOC);
            if (!$line) {
                $conn->rollBack();
                jsonResponse(['success' => false, 'message' => 'No remaining items on this purchase request']);
            }
            $firstItemId = issuanceCreateItemFromPrLine($conn, $line);
            $conn->prepare('UPDATE proc_pr_line SET property_item_id = ? WHERE id = ?')
                ->execute([$firstItemId, (int) $line['id']]);

            $ins = $conn->prepare(
                'INSERT INTO issuance_records (form_type, form_number, property_item_id, office_id, issued_to, position, issue_date, remarks, proc_pr_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$ins->execute([$form_type, $form_number, $firstItemId, $office_id, $issued_to, $position, $issue_date, $remarks, $proc_pr_id])) {
                $conn->rollBack();
                jsonResponse(['success' => false, 'message' => 'Failed to create issuance']);
            }
            $issuance_id = (int) $conn->lastInsertId();
        }

        $attached = issuanceAttachRemainingLines($conn, $issuance_id, $proc_pr_id, [
            'issued_to' => $issued_to,
            'form_number' => $form_number,
        ]);
        $conn->commit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $existingAfter = issuanceExistingForPr($conn, $proc_pr_id);
        if ($existingAfter) {
            $issuance_id = (int) $existingAfter['id'];
            $itemCount = count(issuanceLoadItems($conn, $issuance_id));
            jsonResponse([
                'success' => true,
                'already_issued' => true,
                'message' => 'This purchase is already on PAR ' . (string) ($existingAfter['form_number'] ?? ''),
                'id' => $issuance_id,
                'form_type' => 'PAR',
                'item_count' => $itemCount,
            ]);
        }
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate') !== false || stripos($msg, '1062') !== false) {
            jsonResponse(['success' => false, 'message' => 'That PAR number is already used']);
        }
        jsonResponse(['success' => false, 'message' => 'Failed to create issuance']);
    }

    $itemCount = count(issuanceLoadItems($conn, $issuance_id));
    $added = $wasExisting ? $attached : max($itemCount, $attached);
    if (function_exists('activityLogParIssued')) {
        activityLogParIssued(
            $conn,
            $proc_pr_id,
            $form_number,
            $issued_to,
            (int) ($wasExisting ? $attached : $itemCount),
            isset($pr['office_id']) ? (int) $pr['office_id'] : null,
            false,
            $wasExisting
        );
    }
    jsonResponse([
        'success' => true,
        'already_issued' => false,
        'message' => $wasExisting
            ? ('Added remaining items to the same PAR (' . $added . ')')
            : ('Issued ' . $itemCount . ' item' . ($itemCount === 1 ? '' : 's') . ' on one PAR'),
        'id' => $issuance_id,
        'form_type' => $form_type,
        'item_count' => $itemCount,
    ]);
}
?>
