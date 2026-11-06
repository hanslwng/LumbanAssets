<?php

/**
 * Property Tag (GSO) — persist editable label fields separately from live inventory.
 * Property Number and Acquisition Cost always come from property_items.
 */

function propertyTagEnsureSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS property_tags (
                property_item_id INT(11) NOT NULL,
                office_division VARCHAR(180) NOT NULL DEFAULT '',
                person_accountable VARCHAR(180) NOT NULL DEFAULT '',
                acquisition_date VARCHAR(40) NOT NULL DEFAULT '',
                description TEXT NULL,
                model_number VARCHAR(180) NOT NULL DEFAULT '',
                serial_number VARCHAR(180) NOT NULL DEFAULT '',
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (property_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (PDOException $e) {
        // Best-effort on older installs.
    }
    $done = true;
}

function propertyTagEmpty(): array
{
    return [
        'office_division' => '',
        'person_accountable' => '',
        'acquisition_date' => '',
        'description' => '',
        'model_number' => '',
        'serial_number' => '',
    ];
}

function propertyTagNormalize(array $row): array
{
    $blank = propertyTagEmpty();
    return [
        'office_division' => trim((string) ($row['office_division'] ?? $blank['office_division'])),
        'person_accountable' => trim((string) ($row['person_accountable'] ?? $blank['person_accountable'])),
        'acquisition_date' => trim((string) ($row['acquisition_date'] ?? $blank['acquisition_date'])),
        'description' => trim((string) ($row['description'] ?? $blank['description'])),
        'model_number' => trim((string) ($row['model_number'] ?? $blank['model_number'])),
        'serial_number' => trim((string) ($row['serial_number'] ?? $blank['serial_number'])),
    ];
}

function propertyTagGet(PDO $conn, int $itemId): array
{
    propertyTagEnsureSchema($conn);
    if ($itemId <= 0) {
        return propertyTagEmpty();
    }
    try {
        $st = $conn->prepare(
            'SELECT office_division, person_accountable, acquisition_date, description, model_number, serial_number
             FROM property_tags WHERE property_item_id = ?'
        );
        $st->execute([$itemId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? propertyTagNormalize($row) : propertyTagEmpty();
    } catch (PDOException $e) {
        return propertyTagEmpty();
    }
}

function propertyTagSave(PDO $conn, int $itemId, array $fields): bool
{
    propertyTagEnsureSchema($conn);
    if ($itemId <= 0) {
        return false;
    }
    $n = propertyTagNormalize($fields);
    try {
        $st = $conn->prepare(
            'INSERT INTO property_tags (
                property_item_id, office_division, person_accountable, acquisition_date,
                description, model_number, serial_number
             ) VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                office_division = VALUES(office_division),
                person_accountable = VALUES(person_accountable),
                acquisition_date = VALUES(acquisition_date),
                description = VALUES(description),
                model_number = VALUES(model_number),
                serial_number = VALUES(serial_number)'
        );
        return $st->execute([
            $itemId,
            $n['office_division'],
            $n['person_accountable'],
            $n['acquisition_date'],
            $n['description'],
            $n['model_number'],
            $n['serial_number'],
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function propertyTagFormatCost($value): string
{
    $n = is_numeric($value) ? (float) $value : 0.0;

    return '₱' . number_format($n, 2, '.', ',');
}

function propertyTagLoadPrintRows(PDO $conn, array $ids): array
{
    propertyTagEnsureSchema($conn);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
        return $id > 0;
    })));
    if (!$ids) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT p.id, p.property_number, p.unit_value, p.office_id, p.category,
                   t.office_division, t.person_accountable, t.acquisition_date,
                   t.description, t.model_number, t.serial_number
            FROM property_items p
            LEFT JOIN property_tags t ON t.property_item_id = p.id
            WHERE p.id IN ({$place})";
    $st = $conn->prepare($sql);
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $id = (int) $row['id'];
        $byId[$id] = [
            'id' => $id,
            'property_number' => (string) ($row['property_number'] ?? ''),
            'unit_value' => $row['unit_value'],
            'category' => (string) ($row['category'] ?? ''),
            'office_id' => (int) ($row['office_id'] ?? 0),
            'tag' => propertyTagNormalize($row),
        ];
    }
    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }

    return $ordered;
}

function propertyTagRender(array $item, array $tag, string $logoUrl, string $mode = 'print'): string
{
    $h = static function ($s): string {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    };
    $pn = $h($item['property_number'] ?? '');
    $cost = $h(propertyTagFormatCost($item['unit_value'] ?? 0));
    $dateRaw = (string) ($tag['acquisition_date'] ?? '');
    $dateShow = $dateRaw;
    if ($mode !== 'edit' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateRaw, $dm)) {
        $dateShow = $dm[2] . '/' . $dm[3] . '/' . $dm[1];
    }
    $edit = $mode === 'edit';
    $lbl = static function (string $text) use ($h): string {
        return '<span class="ptag-lbl"><em>' . $h($text) . '</em></span>';
    };
    $val = static function (string $name, string $value) use ($h, $edit): string {
        if (!$edit) {
            return '<span class="ptag-val">' . $h($value) . '</span>';
        }
        if ($name === 'description') {
            return '<textarea class="ptag-inp" id="ptag-' . $h($name) . '" rows="2">' . $h($value) . '</textarea>';
        }
        $type = $name === 'acquisition_date' ? 'date' : 'text';

        return '<input class="ptag-inp" id="ptag-' . $h($name) . '" type="' . $type . '" value="' . $h($value) . '"/>';
    };

    return
        '<div class="ptag">' .
        '<div class="ptag-c ptag-title">PROPERTY TAG GSO</div>' .
        '<div class="ptag-c ptag-logo"><img src="' . $h($logoUrl) . '" alt="Bayan ng Lumban seal"/></div>' .
        '<div class="ptag-c ptag-pn">' . $lbl('Property Number:') . '<span class="ptag-val ptag-val-lock">' . $pn . '</span></div>' .
        '<div class="ptag-c ptag-od">' . $lbl('Office/Division:') . $val('office_division', $tag['office_division'] ?? '') . '</div>' .
        '<div class="ptag-c ptag-pa">' . $lbl('Person Accountable:') . $val('person_accountable', $tag['person_accountable'] ?? '') . '</div>' .
        '<div class="ptag-c ptag-ad">' . $lbl('Acquisition Date:') . $val('acquisition_date', $edit ? $dateRaw : $dateShow) . '</div>' .
        '<div class="ptag-c ptag-ac">' . $lbl('Acquisition Cost:') . '<span class="ptag-val ptag-val-lock">' . $cost . '</span></div>' .
        '<div class="ptag-c ptag-desc">' . $lbl('Description of Property:') . $val('description', $tag['description'] ?? '') . '</div>' .
        '<div class="ptag-c ptag-mod">' . $lbl('Model Number:') . $val('model_number', $tag['model_number'] ?? '') . '</div>' .
        '<div class="ptag-c ptag-ser">' . $lbl('Serial Number:') . $val('serial_number', $tag['serial_number'] ?? '') . '</div>' .
        '<div class="ptag-c ptag-sign-lbl">' . $lbl('Date of Inventory and Signature of Inventory Team:') . '</div>' .
        '<div class="ptag-c ptag-sign-l"></div>' .
        '<div class="ptag-c ptag-sign-r"></div>' .
        '<div class="ptag-c ptag-foot">TAMPERING OF THIS LABEL IS STRICTLY PROHIBITED</div>' .
        '</div>';
}
