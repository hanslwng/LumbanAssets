<?php
/**
 * Normalize issuance + property item data for PAR / ICS print views.
 * Works with issuance_records + property_items (SPA / api flow).
 * Falls back to a minimal SELECT if optional columns are missing (older DBs).
 */
require_once __DIR__ . '/issuance_helpers.php';

/**
 * Build the shared print payload array used by HTML/Excel PAR views.
 *
 * @param array<string,mixed> $row
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function issuanceBuildPrintPayload(PDO $conn, array $row, array $items, int $prId): array
{
    $first = $items[0] ?? null;

    $qty = $first ? (float) ($first['quantity'] ?? 1) : (isset($row['item_quantity']) ? (float) $row['item_quantity'] : 1);
    if ($qty < 1) {
        $qty = 1;
    }
    $uv = $first ? (float) ($first['unit_value'] ?? 0) : (isset($row['unit_value']) ? (float) $row['unit_value'] : 0.0);
    $amount = $first
        ? (float) ($first['amount'] ?? ($uv * (float) ($first['quantity'] ?? $qty)))
        : ($uv * $qty);
    $issueRaw = $row['issue_date'] ?? date('Y-m-d');
    $acqRaw = $first['acquisition_date'] ?? ($row['acquisition_date'] ?? null);

    $desc = $first['description'] ?? trim((string) ($row['description'] ?? ''));
    if ($desc === '') {
        $art = trim((string) ($row['article'] ?? ''));
        $desc = $art !== '' ? $art : '—';
    }

    $uom = $first['unit'] ?? trim((string) ($row['unit_of_measure'] ?? ''));
    if ($uom === '') {
        $uom = 'pc';
    }

    $life = $row['estimated_useful_life'] ?? null;
    $lifeDisplay = ($life !== null && $life !== '' && (int) $life > 0) ? (string) (int) $life . ' yr' : '';

    if (!function_exists('settingsGet')) {
        require_once __DIR__ . '/settings_helpers.php';
    }

    $received = trim((string) ($row['received_by'] ?? ''));
    if ($received === '') {
        $received = trim((string) ($row['issued_to'] ?? ''));
    }

    $po = issuancePrPoMeta($conn, $prId);
    $section = '';
    if ($prId > 0) {
        try {
            $secSt = $conn->prepare('SELECT pr_section FROM proc_pr WHERE id = ?');
            $secSt->execute([$prId]);
            $section = prInventoryNormalizeGroup((string) ($secSt->fetchColumn() ?: ''));
        } catch (PDOException $e) {
            $section = '';
        }
    }
    if ($section === '' && !empty($first['item_group'])) {
        $section = (string) $first['item_group'];
    }
    if ($section === '') {
        $section = (string) ($row['item_group'] ?? '');
    }
    if ($section === '' && !empty($row['is_consumable'])) {
        $section = 'Consumable';
    }

    $acqForHeader = $acqRaw;
    if (empty($acqForHeader) && !empty($po['po_date'])) {
        $acqForHeader = $po['po_date'];
    }

    return [
        'form_type' => 'PAR',
        'office_id' => (int) ($row['office_id'] ?? 0),
        'property_item_id' => (int) ($row['property_item_id'] ?? 0),
        'is_consumable' => !empty($row['is_consumable']) ? 1 : 0,
        'form_number' => (string) ($row['form_number'] ?? ''),
        'issue_date' => (string) $issueRaw,
        'issuance_date' => (string) $issueRaw,
        'entity_name' => defined('AGENCY_NAME') ? AGENCY_NAME : '',
        'fund_cluster' => '',
        'ris_number' => null,
        'quantity' => $qty,
        'unit' => $uom,
        'description' => $desc,
        'property_number' => (string) ($first['property_number'] ?? $row['property_number'] ?? ''),
        'acquisition_date' => $acqForHeader,
        'unit_value' => $uv,
        'amount' => $amount,
        'issued_to' => (string) ($row['issued_to'] ?? ''),
        'position' => (string) ($row['position'] ?? ''),
        'office_name' => (string) ($row['office_name'] ?? ''),
        'remarks' => (string) ($row['remarks'] ?? ''),
        'estimated_useful_life_display' => $lifeDisplay,
        'purpose' => '',
        'received_by' => $received,
        'section' => $section,
        'po_number' => (string) ($po['po_number'] ?? ''),
        'po_date' => $po['po_date'] ?? '',
        'items' => $items,
        'administered_by' => '',
        'administered_title' => '',
        'issued_by' => (string) settingsGet($conn, 'gso_officer', defined('RPCPPE_ACCOUNTABLE_OFFICER') ? RPCPPE_ACCOUNTABLE_OFFICER : ''),
        'issued_by_title' => (string) settingsGet($conn, 'gso_designation', defined('RPCPPE_ACCOUNTABLE_DESIGNATION') ? RPCPPE_ACCOUNTABLE_DESIGNATION : ''),
        'approved_by' => (string) settingsGet($conn, 'mayor_name', defined('RPCPPE_APPROVER_NAME') ? RPCPPE_APPROVER_NAME : ''),
        'approved_by_title' => (string) settingsGet($conn, 'mayor_designation', defined('RPCPPE_APPROVER_DESIGNATION') ? RPCPPE_APPROVER_DESIGNATION : ''),
    ];
}

function loadIssuancePrintForView(PDO $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    issuanceEnsureItemsTable($conn);

    // Consumable PARs have no property_item_id — load header first, then branch.
    $header = null;
    try {
        $hs = $conn->prepare(
            'SELECT i.*, o.name AS office_name
             FROM issuance_records i
             LEFT JOIN offices o ON i.office_id = o.id
             WHERE i.id = ?'
        );
        $hs->execute([$id]);
        $header = $hs->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
    if (!$header) {
        return null;
    }

    if (!empty($header['is_consumable'])) {
        require_once __DIR__ . '/consumable_issuance.php';
        $cLines = consumableLoadIssuanceLines($conn, $id);
        $items = [];
        foreach ($cLines as $line) {
            $qty = (float) ($line['quantity'] ?? 0);
            if ($qty <= 0) {
                $qty = 1;
            }
            $uv = (float) ($line['unit_cost'] ?? 0);
            $unit = trim((string) ($line['unit'] ?? ''));
            if ($unit === '') {
                $unit = 'pc';
            }
            $items[] = [
                'quantity' => $qty,
                'unit' => $unit,
                'description' => (string) ($line['item_name'] ?? ''),
                'property_number' => '',
                'acquisition_date' => $header['issue_date'] ?? null,
                'unit_value' => $uv,
                'amount' => $uv * $qty,
                'remarks' => '',
                'item_group' => 'Consumable',
            ];
        }
        $prId = (int) ($header['proc_pr_id'] ?? 0);
        $header['description'] = $items
            ? implode('; ', array_column($items, 'description'))
            : 'Consumable release';
        $header['property_number'] = '';
        $header['article'] = null;
        $header['unit_of_measure'] = null;
        $header['estimated_useful_life'] = null;
        $header['item_group'] = 'Consumable';
        $header['unit_value'] = $items[0]['unit_value'] ?? 0;
        $header['item_quantity'] = $items[0]['quantity'] ?? 1;
        $header['acquisition_date'] = $header['issue_date'] ?? null;

        return issuanceBuildPrintPayload($conn, $header, $items, $prId);
    }

    $sqlFull = 'SELECT i.*, p.property_number, p.description, p.article, p.unit_of_measure,
                   p.unit_value, p.quantity AS item_quantity, p.acquisition_date,
                   p.estimated_useful_life, p.item_group, o.name AS office_name
            FROM issuance_records i
            INNER JOIN property_items p ON i.property_item_id = p.id
            LEFT JOIN offices o ON i.office_id = o.id
            WHERE i.id = ?';

    $sqlMinimal = 'SELECT i.*, p.property_number, p.description, p.unit_value,
                   p.quantity AS item_quantity, p.acquisition_date, o.name AS office_name
            FROM issuance_records i
            INNER JOIN property_items p ON i.property_item_id = p.id
            LEFT JOIN offices o ON i.office_id = o.id
            WHERE i.id = ?';

    $row = null;
    try {
        $stmt = $conn->prepare($sqlFull);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Unknown column') !== false || strpos($msg, '42S22') !== false) {
            try {
                $stmt = $conn->prepare($sqlMinimal);
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $row['article'] = null;
                    $row['unit_of_measure'] = null;
                    $row['estimated_useful_life'] = null;
                    $row['item_group'] = null;
                }
            } catch (PDOException $e2) {
                return null;
            }
        } else {
            return null;
        }
    }

    if (!$row) {
        // Fallback: header exists but property row missing — still show what we can.
        $row = $header;
        $row['property_number'] = $row['property_number'] ?? '';
        $row['description'] = $row['description'] ?? '';
        $row['article'] = null;
        $row['unit_of_measure'] = null;
        $row['estimated_useful_life'] = null;
        $row['item_group'] = null;
        $row['unit_value'] = 0;
        $row['item_quantity'] = 1;
        $row['acquisition_date'] = $row['issue_date'] ?? null;
    }

    $prId = (int) ($row['proc_pr_id'] ?? 0);
    if ($prId > 0 && class_exists('PermissionHelper') && PermissionHelper::can('create')) {
        try {
            issuanceAttachRemainingLines($conn, $id, $prId, [
                'issued_to' => (string) ($row['issued_to'] ?? ''),
                'form_number' => (string) ($row['form_number'] ?? ''),
            ]);
        } catch (Throwable $e) {
            // Viewing still works with whatever is already on the PAR.
        }
    }

    $items = issuanceLoadItems($conn, $id);

    return issuanceBuildPrintPayload($conn, $row, $items, $prId);
}
