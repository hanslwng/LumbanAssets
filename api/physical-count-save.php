<?php
/**
 * Batch-save physical count report edits (SE / PPE) back to property_items.
 * POST JSON:
 * {
 *   "category": "SE"|"PPE",
 *   "default_office_id": 1,
 *   "rows": [
 *     {
 *       "id": 12|null,
 *       "article", "description", "property_number", "unit_of_measure",
 *       "unit_value", "quantity", "quantity_physical_count", "remarks", "item_group"
 *     }
 *   ]
 * }
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/audit_log.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

PermissionHelper::require('edit');

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    jsonResponse(['success' => false, 'message' => 'Invalid JSON body']);
}

$category = strtoupper(trim((string) ($data['category'] ?? 'SE')));
if (!in_array($category, ['SE', 'PPE'], true)) {
    jsonResponse(['success' => false, 'message' => 'category must be SE or PPE']);
}

$rows = $data['rows'] ?? null;
if (!is_array($rows)) {
    jsonResponse(['success' => false, 'message' => 'rows array is required']);
}

$defaultOfficeId = (int) ($data['default_office_id'] ?? 0);
if (!PermissionHelper::isAdmin()) {
    $defaultOfficeId = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : 0;
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed']);
}

$findById = $conn->prepare(
    'SELECT id, office_id, category, property_number, article FROM property_items WHERE id = ? LIMIT 1'
);
$findByPn = $conn->prepare(
    'SELECT id, office_id, category, property_number, article
     FROM property_items WHERE property_number = ? AND category = ? LIMIT 1'
);
$dupPn = $conn->prepare('SELECT id FROM property_items WHERE property_number = ? AND id <> ? LIMIT 1');
$upd = $conn->prepare(
    'UPDATE property_items SET
        property_number = ?, article = ?, description = ?, unit_of_measure = ?, item_group = ?,
        unit_value = ?, quantity = ?, quantity_physical_count = ?, remarks = ?
     WHERE id = ? AND category = ?'
);
$updKeepPn = $conn->prepare(
    'UPDATE property_items SET
        article = ?, description = ?, unit_of_measure = ?, item_group = ?,
        unit_value = ?, quantity = ?, quantity_physical_count = ?, remarks = ?
     WHERE id = ? AND category = ?'
);
$ins = $conn->prepare(
    'INSERT INTO property_items
        (property_number, article, description, unit_of_measure, category, item_group, sort_order,
         unit_value, quantity, quantity_physical_count, office_id, remarks, status, condition_status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Active\', \'Good\')'
);
$maxSort = $conn->prepare(
    'SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM property_items
     WHERE office_id = ? AND category = ? AND (item_group <=> ?)'
);

$updated = 0;
$created = 0;
$skipped = 0;
$errors = [];
$createdMap = [];

function pc_save_text($v): string
{
    return trim(strip_tags((string) $v));
}

function pc_save_nullable_text($v): ?string
{
    $t = pc_save_text($v);
    return $t === '' ? null : $t;
}

function pc_save_pn_is_unique_key(string $pn): bool
{
    $n = function_exists('lgueNormalizePropertyNumber') ? lgueNormalizePropertyNumber($pn) : trim($pn);

    return $n !== '' && strcasecmp($n, 'NONE') !== 0;
}

auditLogEnsureSchema($conn);

try {
    $conn->beginTransaction();
    $usedPn = [];

    foreach ($rows as $i => $row) {
        if (!is_array($row)) {
            $skipped++;
            continue;
        }

        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $article = pc_save_nullable_text($row['article'] ?? '');
        $description = pc_save_text($row['description'] ?? '');
        if ($description === '' || $description === '—') {
            $description = '—';
        }
        $propertyNumber = function_exists('lgueNormalizePropertyNumber')
            ? lgueNormalizePropertyNumber(pc_save_text($row['property_number'] ?? ''))
            : pc_save_text($row['property_number'] ?? '');
        if ($propertyNumber === '') {
            $propertyNumber = 'NONE';
        }
        if (function_exists('lguePropertyNumberError')) {
            $pnErr = lguePropertyNumberError($propertyNumber);
            if ($pnErr !== null) {
                $errors[] = 'Row ' . ($i + 1) . ': ' . $pnErr;
                continue;
            }
        }
        $uom = pc_save_text($row['unit_of_measure'] ?? 'pc');
        if ($uom === '') {
            $uom = 'pc';
        }
        $unitValueRaw = $row['unit_value'] ?? 0;
        $isDonationUv = lgueIsDonationUnitValue($unitValueRaw);
        $unitValue = lgueUnitValueNumeric($unitValueRaw);
        $unitValueDb = lgueUnitValueForStorage($unitValueRaw);
        if (!$isDonationUv && $unitValue < 0) {
            $errors[] = 'Row ' . ($i + 1) . ': unit value cannot be negative';
            continue;
        }
        $qtyCard = (int) ($row['quantity'] ?? 0);
        if ($qtyCard < 0) {
            $qtyCard = 0;
        }
        $qtyPhysRaw = $row['quantity_physical_count'] ?? null;
        $qtyPhys = null;
        if ($qtyPhysRaw !== null && $qtyPhysRaw !== '' && $qtyPhysRaw !== '—') {
            $qtyPhys = (int) $qtyPhysRaw;
        }
        $remarks = pc_save_text($row['remarks'] ?? '');
        if ($remarks === '—') {
            $remarks = '';
        }
        $itemGroup = pc_save_nullable_text($row['item_group'] ?? '');
        $numErr = $isDonationUv ? null : lgueNumericFieldError($row['unit_value'] ?? $unitValue, 'Unit value', true, 0.0, true);
        if ($numErr !== null) {
            $errors[] = 'Row ' . ($i + 1) . ': ' . $numErr;
            continue;
        }
        if (isset($row['quantity']) && $row['quantity'] !== '' && $row['quantity'] !== null) {
            $qErr = lgueNumericFieldError($row['quantity'], 'Quantity', false, 0.0, false);
            if ($qErr !== null) {
                $errors[] = 'Row ' . ($i + 1) . ': ' . $qErr;
                continue;
            }
        }
        if ($qtyPhysRaw !== null && $qtyPhysRaw !== '' && $qtyPhysRaw !== '—') {
            $pErr = lgueNumericFieldError($qtyPhysRaw, 'Physical count', false, 0.0, false);
            if ($pErr !== null) {
                $errors[] = 'Row ' . ($i + 1) . ': ' . $pErr;
                continue;
            }
        }
        $descCheck = $description === '—' ? (string) $article : $description;
        if ($descCheck !== '' && $descCheck !== '—' && !lgueIsReadableText((string) $descCheck, 3)) {
            $errors[] = 'Row ' . ($i + 1) . ': article or description must be readable official text.';
            continue;
        }
        $remErr = lgueOptionalTextError($remarks, 'Remarks');
        if ($remErr !== null) {
            $errors[] = 'Row ' . ($i + 1) . ': ' . $remErr;
            continue;
        }
        $officeId = (int) ($row['office_id'] ?? $defaultOfficeId);

        $existing = null;
        if ($id > 0) {
            $findById->execute([$id]);
            $existing = $findById->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$existing && pc_save_pn_is_unique_key($propertyNumber)) {
            $findByPn->execute([$propertyNumber, $category]);
            $existing = $findByPn->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $pnKey = strtoupper($propertyNumber);
        if (pc_save_pn_is_unique_key($propertyNumber)) {
            $ownerId = $existing ? (int) $existing['id'] : 0;
            if (isset($usedPn[$pnKey]) && (int) $usedPn[$pnKey] !== $ownerId) {
                $errors[] = 'Row ' . ($i + 1) . ': property number ' . $propertyNumber . ' is used more than once on this form';
                continue;
            }
            if ($existing) {
                $dupPn->execute([$propertyNumber, (int) $existing['id']]);
                if ($dupPn->fetch(PDO::FETCH_ASSOC)) {
                    $errors[] = 'Row ' . ($i + 1) . ': property number ' . $propertyNumber . ' is already used by another item';
                    continue;
                }
            } else {
                $dupPn->execute([$propertyNumber, 0]);
                if ($dupPn->fetch(PDO::FETCH_ASSOC)) {
                    $errors[] = 'Row ' . ($i + 1) . ': property number ' . $propertyNumber . ' is already used by another item';
                    continue;
                }
            }
            $usedPn[$pnKey] = $ownerId > 0 ? $ownerId : -($i + 1);
        }

        if ($existing) {
            if (!PermissionHelper::isAdmin() && (int) $existing['office_id'] !== $officeId && $officeId > 0) {
                // Non-admin confined to own office
                if ((int) $existing['office_id'] !== (int) ($_SESSION['user_office_id'] ?? 0)) {
                    $errors[] = 'Row ' . ($i + 1) . ': not allowed to edit this item';
                    continue;
                }
            }
            if (strtoupper((string) $existing['category']) !== $category) {
                $errors[] = 'Row ' . ($i + 1) . ': property number belongs to another category';
                continue;
            }
            if ($article === null || $article === '') {
                $keepArt = trim((string) ($existing['article'] ?? ''));
                $article = $keepArt !== '' ? $keepArt : null;
            }
            $qtySave = max(1, $qtyCard);
            try {
                $upd->execute([
                    $propertyNumber,
                    $article,
                    $description,
                    $uom,
                    $itemGroup,
                    $unitValueDb,
                    $qtySave,
                    $qtyPhys,
                    $remarks,
                    (int) $existing['id'],
                    $category,
                ]);
            } catch (PDOException $e) {
                // Unique property_number still on the table (multiple NONE, or a race).
                try {
                    $updKeepPn->execute([
                        $article,
                        $description,
                        $uom,
                        $itemGroup,
                        $unitValueDb,
                        $qtySave,
                        $qtyPhys,
                        $remarks,
                        (int) $existing['id'],
                        $category,
                    ]);
                    $errors[] = 'Row ' . ($i + 1) . ': other fields saved, but property number was not changed (already used, or NONE is not allowed more than once until the unique key is dropped)';
                } catch (PDOException $e2) {
                    $errors[] = 'Row ' . ($i + 1) . ': could not update this item';
                    continue;
                }
                $updated++;
                continue;
            }
            $oldPn = (string) ($existing['property_number'] ?? '');
            if ($oldPn !== $propertyNumber) {
                try {
                    auditLogWrite($conn, (int) $existing['id'], 'Property number changed', $oldPn, $propertyNumber);
                } catch (Throwable $e) {
                    // Item is saved; history is optional.
                }
            }
            $updated++;
            continue;
        }

        // Create new item
        if ($officeId <= 0) {
            $errors[] = 'Row ' . ($i + 1) . ': office is required to create a new item';
            continue;
        }
        if (!PermissionHelper::isAdmin()) {
            $officeId = (int) ($_SESSION['user_office_id'] ?? 0);
            if ($officeId <= 0) {
                $errors[] = 'Row ' . ($i + 1) . ': no office assigned to your account';
                continue;
            }
        }

        $maxSort->execute([$officeId, $category, $itemGroup]);
        $sortOrder = (int) ($maxSort->fetch(PDO::FETCH_ASSOC)['n'] ?? 0);

        try {
            $ins->execute([
                $propertyNumber,
                $article,
                $description,
                $uom,
                $category,
                $itemGroup,
                $sortOrder,
                $unitValueDb,
                max(1, $qtyCard),
                $qtyPhys,
                $officeId,
                $remarks,
            ]);
            $newId = (int) $conn->lastInsertId();
            $created++;
            if (pc_save_pn_is_unique_key($propertyNumber)) {
                $usedPn[$pnKey] = $newId;
            }
            $createdMap[] = [
                'id' => $newId,
                'property_number' => $propertyNumber,
                'row_index' => $i,
                'temp_key' => pc_save_text($row['temp_key'] ?? ''),
            ];
            try {
                auditLogWrite($conn, $newId, 'Created', null, trim($propertyNumber . ' — ' . $description));
            } catch (Throwable $e) {
                // Item is saved; history is optional.
            }
        } catch (PDOException $e) {
            $errors[] = 'Row ' . ($i + 1) . ': could not create item (duplicate property number?)';
        }
    }

    if ($conn->inTransaction()) {
        $conn->commit();
    }
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    jsonResponse(['success' => false, 'message' => 'Save failed']);
}

try {
    activityLog($conn, 'reports', 'Physical count saved', [
        'entity_type' => 'physical_count',
        'entity_label' => $category,
        'new_value' => $updated . ' updated, ' . $created . ' created',
    ]);
} catch (Throwable $e) {
    // Save already committed.
}

jsonResponse([
    'success' => empty($errors) || ($updated + $created) > 0,
    'message' => sprintf('Saved: %d updated, %d created.', $updated, $created)
        . ($errors ? (' ' . count($errors) . ' issue(s).') : ''),
    'updated' => $updated,
    'created' => $created,
    'skipped' => $skipped,
    'created_items' => $createdMap,
    'errors' => array_slice($errors, 0, 25),
]);
