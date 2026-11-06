<?php
/**
 * Shared helpers for bulk-importing Semi-Expendable / PPE property items,
 * used by api/items-import.php (and covered by scripts/test-items-import.php).
 */

require_once __DIR__ . '/demo_acquisition_year.php';
require_once __DIR__ . '/unserviceable_helpers.php';

/**
 * Physical-count remarks that mean the item should appear under Unserviceable.
 */
function importItemsUnserviceableFromRemarks(?string $remarks): ?array
{
    $raw = trim((string) $remarks);
    if ($raw === '') {
        return null;
    }
    $r = strtolower($raw);
    if (preg_match('/\bbroken\b/', $r)) {
        return ['reason' => 'Broken', 'repair_status' => 'Defective'];
    }
    if (preg_match('/\b(for repair|under repair|for fixing)\b/', $r)) {
        return ['reason' => 'Not Working', 'repair_status' => 'Pending'];
    }
    if (preg_match('/\b(defective|not working|unserviceable)\b/', $r)) {
        return ['reason' => 'Not Working', 'repair_status' => 'Defective'];
    }

    return null;
}

/**
 * Mark an imported item Unserviceable and add a record if one is not already open.
 */
function importItemsEnsureUnserviceable(PDO $conn, int $itemId, ?string $remarks): bool
{
    $meta = importItemsUnserviceableFromRemarks($remarks);
    if ($meta === null || $itemId <= 0) {
        return false;
    }

    $st = $conn->prepare('SELECT id, status FROM property_items WHERE id = ? LIMIT 1');
    $st->execute([$itemId]);
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item || ($item['status'] ?? '') === 'Disposed') {
        return false;
    }

    $exists = $conn->prepare(
        'SELECT id, repair_status FROM unserviceable_records WHERE property_item_id = ? ORDER BY id DESC LIMIT 1'
    );
    $exists->execute([$itemId]);
    $latest = $exists->fetch(PDO::FETCH_ASSOC);
    if ($latest && ($latest['repair_status'] ?? '') !== 'Serviceable') {
        if (($item['status'] ?? '') !== 'Unserviceable') {
            $fix = $conn->prepare("UPDATE property_items SET status = 'Unserviceable', condition_status = 'Damaged' WHERE id = ?");
            $fix->execute([$itemId]);
        }

        return false;
    }

    unserviceableEnsureSchema($conn);

    $ins = $conn->prepare(
        "INSERT INTO unserviceable_records (property_item_id, marked_date, reason, marked_by, remarks, disposal_status, repair_status)
         VALUES (?, CURDATE(), ?, ?, ?, 'Pending', ?)"
    );
    $ins->execute([
        $itemId,
        $meta['reason'],
        'Physical count (Excel)',
        trim((string) $remarks),
        $meta['repair_status'],
    ]);

    $upd = $conn->prepare("UPDATE property_items SET status = 'Unserviceable', condition_status = 'Damaged' WHERE id = ?");
    $upd->execute([$itemId]);

    return true;
}

function importItemsBackfillUnserviceableFromRemarks(PDO $conn): int
{
    unserviceableEnsureSchema($conn);
    $st = $conn->query(
        "SELECT id, remarks FROM property_items
         WHERE remarks IS NOT NULL AND TRIM(remarks) <> ''
           AND status <> 'Disposed'"
    );
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (importItemsEnsureUnserviceable($conn, (int) $row['id'], $row['remarks'])) {
            $n++;
        }
    }

    return $n;
}

/**
 * Preload offices for name/code matching (case-insensitive, tolerant of partial matches
 * since sheet names are truncated to 31 chars by Excel).
 */
function importItemsIsJunkSheet(string $sheetName): bool
{
    $n = strtolower(trim($sheetName));
    if ($n === '' || preg_match('/^sheet(\s*\(\d+\))?$/', $n) === 1 || $n === 'sheet1') {
        return true;
    }

    return false;
}

/**
 * Signature / certification / date lines at the bottom of RPCSE office tabs.
 * These used to be imported as fake items (officer names as property numbers).
 */
function importItemsIsSignatureOrFooterRow(string $article, string $description, string $propertyNumber, string $remarks = ''): bool
{
    $blob = strtoupper(trim($article . ' ' . $description . ' ' . $propertyNumber . ' ' . $remarks));
    $needles = [
        'PREPARED BY',
        'CERTIFIED CORRECT',
        'APPROVED BY',
        'SIGNATURE OVER PRINTED NAME',
        'INVENTORY COMMITTEE',
        'HEAD OF AGENCY',
        'AUTHORIZED REPRESENTATIVE',
        'NOTHING FOLLOWS',
    ];
    foreach ($needles as $n) {
        if (str_contains($blob, $n)) {
            return true;
        }
    }
    if (preg_match('/^\s*DATE\s*:?\s*$/i', $article) === 1) {
        return true;
    }
    $pn = trim($propertyNumber);
    if ($pn !== '' && preg_match('/^\d{4}-/', $pn) !== 1 && preg_match('/\d/', $pn) !== 1) {
        return true;
    }

    return false;
}

/**
 * Excel often writes "1 Table" instead of "1. Table". Normalize so the
 * article column sorts and displays as 1. / 2. / 3.
 */
function importItemsNormalizeArticle(?string $article): ?string
{
    $a = trim((string) $article);
    if ($a === '') {
        return null;
    }
    if (preg_match('/^(\d+)\s*[\.\)]\s*(.+)$/u', $a, $m) === 1) {
        return $m[1] . '. ' . trim($m[2]);
    }
    if (preg_match('/^(\d+)\s+(.+)$/u', $a, $m) === 1) {
        return $m[1] . '. ' . trim($m[2]);
    }

    return $a;
}

/**
 * Compare RPCSE property numbers after a remapped unknown year (0000- → 2023-).
 */
function importItemsPropertyNumberTail(string $propertyNumber): string
{
    $pn = strtolower(trim($propertyNumber));
    if (preg_match('/^\d{4}-(.+)$/', $pn, $m) === 1) {
        return $m[1];
    }

    return $pn;
}

/**
 * Map RPCSE department tab names to existing office codes when names differ.
 *
 * @return array<string, string> lowercase sheet title => office code
 */
function importItemsSheetOfficeAliases(): array
{
    return [
        'om' => 'MAYOR',
        'sb' => 'VMAY',
        'accounting' => 'ACCT',
        'budget' => 'BUDG',
        'treasury' => 'TRES',
        'engr' => 'ENGR',
        'rhu' => 'HLTH',
        'mswdo' => 'SWDO',
        'mpdc' => 'PLAN',
        'gso' => 'GSO',
        'hrmo' => 'HRMO',
        'assessor' => 'ASSO',
        'da' => 'AGRI',
        'swm' => 'ENRO',
        'mdrrmo' => 'MDRRMO',
        'trading post' => 'AGRI',
    ];
}

function importItemsOfficeDisplayName(string $sheetName): string
{
    $names = [
        'SWM' => 'Solid Waste Management',
        'PDAO' => 'Office of the Person With Disability Affairs',
        'SENIOR' => 'Office of the Senior Citizen Affairs',
        'MCR' => 'Municipal Civil Registrar',
        'LICENSE' => 'License Office',
        'MARKET' => 'Lumban Public Market',
        'PESO' => 'Public Employment Services Office',
        'LWS' => 'Lumban Waterworks System',
        'DILG' => 'Department of the Interior and Local Government',
        'TRADING POST' => 'Organic Trading Post',
        'COA' => 'Commission on Audit',
        'ADMIN' => 'Administrative Office',
        'DEPED' => 'Department of Education',
    ];
    $key = strtoupper(trim($sheetName));

    return $names[$key] ?? trim($sheetName);
}

function importItemsEnsureOffice(PDO $conn, string $sheetName): ?int
{
    $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', $sheetName) ?? '');
    $code = substr($code !== '' ? $code : 'OFF', 0, 12);
    $name = importItemsOfficeDisplayName($sheetName);

    $st = $conn->prepare('SELECT id FROM offices WHERE code = ? OR name = ? LIMIT 1');
    $st->execute([$code, $name]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int) $row['id'];
    }

    $ins = $conn->prepare('INSERT INTO offices (name, code) VALUES (?, ?)');
    try {
        $ins->execute([$name, $code]);
        return (int) $conn->lastInsertId();
    } catch (PDOException $e) {
        $st->execute([$code, $name]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }
}

function importItemsResolveOffice(PDO $conn, string $sheetName, int $fallbackOfficeId): ?int
{
    static $offices = null;
    if ($offices === null) {
        $offices = [];
        $st = $conn->query('SELECT id, name, code FROM offices');
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $offices[] = $o;
        }
    }

    if (importItemsIsJunkSheet($sheetName)) {
        return null;
    }

    $needle = strtolower(trim($sheetName));
    if ($needle === '') {
        return $fallbackOfficeId > 0 ? $fallbackOfficeId : null;
    }

    $aliases = importItemsSheetOfficeAliases();
    if (isset($aliases[$needle])) {
        $aliasCode = strtolower($aliases[$needle]);
        foreach ($offices as $o) {
            if (strtolower(trim((string) ($o['code'] ?? ''))) === $aliasCode) {
                return (int) $o['id'];
            }
        }
    }

    foreach ($offices as $o) {
        if (strtolower(trim((string) $o['name'])) === $needle || strtolower(trim((string) ($o['code'] ?? ''))) === $needle) {
            return (int) $o['id'];
        }
    }
    foreach ($offices as $o) {
        $n = strtolower(trim((string) $o['name']));
        $c = strtolower(trim((string) ($o['code'] ?? '')));
        if ($n !== '' && (str_starts_with($n, $needle) || str_starts_with($needle, $n) || str_contains($n, $needle))) {
            return (int) $o['id'];
        }
        if ($c !== '' && (str_starts_with($c, $needle) || str_starts_with($needle, $c) || str_contains($needle, $c) || str_contains($c, $needle))) {
            return (int) $o['id'];
        }
    }

    $createdId = importItemsEnsureOffice($conn, $sheetName);
    if ($createdId) {
        $offices = null;
        return $createdId;
    }

    return $fallbackOfficeId > 0 ? $fallbackOfficeId : null;
}

function importItemsNextPropertyNumber(PDO $conn, string $category): string
{
    $year = date('Y');
    $cst = $conn->prepare('SELECT COUNT(*) AS count FROM property_items WHERE category = ? AND YEAR(created_at) = ?');
    $cst->execute([$category, $year]);
    $seq = str_pad(((int) $cst->fetch(PDO::FETCH_ASSOC)['count']) + 1, 6, '0', STR_PAD_LEFT);

    return "$year-$category-$seq";
}

function importItemsPropertyNumberExists(PDO $conn, string $propertyNumber): bool
{
    if ($propertyNumber === '') {
        return false;
    }
    $st = $conn->prepare('SELECT id FROM property_items WHERE property_number = ? LIMIT 1');
    $st->execute([$propertyNumber]);

    return (bool) $st->fetch(PDO::FETCH_ASSOC);
}

function importItemsInsertRow(PDO $conn, string $category, int $officeId, ?string $article, string $description, string $uom, float $unitValue, int $qty, ?int $qtyPhysical, ?string $itemGroup, ?string $propertyNumber, ?string $remarks): array
{
    $propertyNumber = $propertyNumber !== null ? trim($propertyNumber) : '';
    $article = importItemsNormalizeArticle($article);
    $acquisitionDate = null;
    if (demoPropertyNumberHasUnknownYear($propertyNumber)) {
        $assigned = demoAssignUnknownAcquisitionYearUnique(
            $propertyNumber,
            static fn (string $candidate) => importItemsPropertyNumberExists($conn, $candidate)
        );
        $propertyNumber = $assigned['property_number'];
        $acquisitionDate = $assigned['acquisition_date'];
    } elseif (preg_match('/^(19\d{2}|20[0-3]\d)-/', $propertyNumber, $ym)) {
        $acquisitionDate = $ym[1] . '-01-01';
    }
    if ($propertyNumber !== '' && importItemsPropertyNumberExists($conn, $propertyNumber)) {
        $find = $conn->prepare('SELECT id FROM property_items WHERE property_number = ? LIMIT 1');
        $find->execute([$propertyNumber]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            importItemsEnsureUnserviceable($conn, (int) $existing['id'], $remarks);
        }

        return ['status' => 'skipped'];
    }
    if ($propertyNumber === '') {
        $propertyNumber = importItemsNextPropertyNumber($conn, $category);
    }

    $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM property_items WHERE office_id = ? AND category = ? AND (item_group <=> ?)");
    $stmt->execute([$officeId, $category, $itemGroup !== '' ? $itemGroup : null]);
    $sortOrder = intval($stmt->fetch(PDO::FETCH_ASSOC)['n']);

    $ins = $conn->prepare(
        'INSERT INTO property_items (property_number, article, description, unit_of_measure, category, item_group, sort_order, unit_value, quantity, quantity_physical_count, office_id, acquisition_date, remarks)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $propertyNumber,
        ($article !== null && $article !== '') ? $article : null,
        $description !== '' ? $description : '—',
        $uom !== '' ? $uom : 'pc',
        $category,
        ($itemGroup !== null && $itemGroup !== '') ? $itemGroup : null,
        $sortOrder,
        $unitValue,
        max(1, $qty),
        $qtyPhysical,
        $officeId,
        $acquisitionDate,
        $remarks,
    ]);

    $newId = (int) $conn->lastInsertId();
    if ($newId > 0) {
        importItemsEnsureUnserviceable($conn, $newId, $remarks);
    }

    return ['status' => 'created'];
}

function importItemsWorkbookLooksLikeRpcppe(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): bool
{
    $blob = '';
    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        $blob .= '|' . strtoupper($sheet->getTitle());
    }
    $score = 0;
    if (str_contains($blob, 'LAND FINAL') || str_contains($blob, 'LAND TITLE')) {
        $score++;
    }
    if (str_contains($blob, 'SUPPLIES AND EQUIPMENT')) {
        $score++;
    }
    if (str_contains($blob, 'SEF')) {
        $score++;
    }
    if (str_contains($blob, 'IIRUP')) {
        $score++;
    }

    return $score >= 2;
}

/**
 * Official RPCPPE tabs that are not the 2025 asset register.
 */
function importItemsRpcppeSkipReason(string $sheetName): ?string
{
    $n = strtoupper(trim($sheetName));
    if ($n === 'FORM' || str_starts_with($n, 'FORM ')) {
        return 'empty RPCPPE template';
    }
    if ($n === 'IIRUP' || str_contains($n, 'IIRUP')) {
        return 'IIRUP unserviceable vehicles (no property numbers)';
    }
    // 2024 copy of 10 SEF office-equipment lines already on SEF SCHOOL BUILDING (2025).
    if ($n === 'SEF OFFICE EQUIPMENT') {
        return '2024 SEF office equipment duplicate of the 2025 SEF tab';
    }

    return null;
}

function importItemsResolvePpeAccountableOffice(PDO $conn): ?int
{
    $st = $conn->query(
        "SELECT id FROM offices WHERE code = 'GSO' OR name LIKE '%General Services%' ORDER BY (code = 'GSO') DESC LIMIT 1"
    );
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int) $row['id'];
    }

    return importItemsEnsureOffice($conn, 'GSO');
}

function importItemsCellRaw(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $coord)
{
    $cell = $sheet->getCell($coord);
    $v = $cell->getValue();
    if (is_string($v) && str_starts_with($v, '=')) {
        try {
            return $cell->getCalculatedValue();
        } catch (Throwable $e) {
            return $v;
        }
    }

    return $v;
}

function importItemsIsFooterOrTotal(string $a, string $b): bool
{
    $blob = strtoupper($a . ' ' . $b);
    if (str_contains($blob, 'NOTHING FOLLOWS')) {
        return true;
    }
    if (preg_match('/\bTOTAL\b/', strtoupper($a)) === 1) {
        return true;
    }
    if (preg_match('/^(PREPARED BY|CERTIFIED|APPROVED|DATE:|RHONALIZA)/i', $a) === 1) {
        return true;
    }

    return false;
}

/**
 * Official RPCPPE register: tabs are asset groups, not departments.
 * Accountable office is GSO. Skip FORM, IIRUP, and the 2024 SEF equipment duplicate.
 *
 * @return array{created:int,skipped:int,errors:list<string>}
 */
function importItemsFromRpcppeXlsx(PDO $conn, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, bool $isAdmin = true): array
{
    $created = 0;
    $skipped = 0;
    $errors = [];

    if (!$isAdmin) {
        return [
            'created' => 0,
            'skipped' => 0,
            'errors' => ['The municipal RPCPPE workbook can only be imported by an administrator'],
        ];
    }

    $officeId = importItemsResolvePpeAccountableOffice($conn);
    if (!$officeId) {
        return ['created' => 0, 'skipped' => 0, 'errors' => ['Could not resolve General Services Office for RPCPPE']];
    }

    $notes = [];
    $notes[] = 'RPCPPE tabs are asset groups, not departments. Items were assigned to General Services Office (accountable officer). FORM, IIRUP, and the 2024 SEF Office Equipment duplicate were skipped.';

    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $sheetName = $sheet->getTitle();
        $skip = importItemsRpcppeSkipReason($sheetName);
        if ($skip !== null) {
            $notes[] = "Sheet \"$sheetName\": skipped ($skip)";
            continue;
        }
        if (importItemsIsJunkSheet($sheetName)) {
            continue;
        }

        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $headerRow = 0;
        for ($r = 1; $r <= min($highestRow, 25); $r++) {
            $rowText = '';
            foreach ($sheet->getRowIterator($r, $r)->current()->getCellIterator('A', $highestCol) as $cell) {
                $rowText .= ' ' . strtoupper((string) $cell->getValue());
            }
            if (
                str_contains($rowText, 'ARTICLE')
                && str_contains($rowText, 'PROPERTY')
                && (str_contains($rowText, 'UNIT VALUE') || str_contains($rowText, 'UNIT  VALUE'))
            ) {
                $headerRow = $r;
                break;
            }
        }
        if ($headerRow === 0) {
            $errors[] = "Sheet \"$sheetName\": no RPCPPE header row, skipped";
            continue;
        }

        $sefPrefix = str_contains(strtoupper($sheetName), 'SEF') ? 'SEF · ' : '';
        $currentGroup = trim($sheetName);
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $a = trim((string) importItemsCellRaw($sheet, 'A' . $r));
            $b = trim((string) importItemsCellRaw($sheet, 'B' . $r));
            $c = trim((string) importItemsCellRaw($sheet, 'C' . $r));
            $d = trim((string) importItemsCellRaw($sheet, 'D' . $r));
            $eVal = importItemsCellRaw($sheet, 'E' . $r);
            $fVal = importItemsCellRaw($sheet, 'F' . $r);
            $gVal = importItemsCellRaw($sheet, 'G' . $r);
            $jRemarks = trim((string) importItemsCellRaw($sheet, 'J' . $r));

            if ($a === '' && $b === '' && $c === '' && ($eVal === null || $eVal === '')) {
                continue;
            }
            if (importItemsIsFooterOrTotal($a, $b)) {
                continue;
            }

            if ($a !== '' && $c === '' && $d === '' && $b === '' && $eVal !== null && $eVal !== '') {
                $currentGroup = $a;
                continue;
            }
            if ($b === '' && $c === '') {
                continue;
            }

            $unitValue = is_numeric($eVal) ? (float) $eVal : (float) preg_replace('/[^0-9.\-]/', '', (string) $eVal);
            $qtyCard = is_numeric($fVal) ? (int) $fVal : 1;
            $qtyPhys = ($gVal !== null && $gVal !== '' && is_numeric($gVal)) ? (int) $gVal : null;
            if ($qtyCard < 1) {
                $qtyCard = 1;
            }

            $itemGroup = $sefPrefix . $currentGroup;
            $article = $a !== '' ? $a : $currentGroup;

            try {
                $res = importItemsInsertRow(
                    $conn,
                    'PPE',
                    $officeId,
                    $article,
                    $b !== '' ? $b : $article,
                    $d,
                    $unitValue,
                    $qtyCard,
                    $qtyPhys,
                    $itemGroup,
                    $c,
                    $jRemarks
                );
                if ($res['status'] === 'created') {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (PDOException $e) {
                $errors[] = "Sheet \"$sheetName\" row $r: " . $e->getMessage();
            }
        }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors, 'notes' => $notes];
}

/**
 * Import the official COA layout: one worksheet per office, section header rows
 * (Article col filled, Property No/UOM blank, Unit Value = subtotal) followed by
 * item rows: Article | Description | Property No | UOM | Unit Value | Qty per card |
 * Qty per count | Shortage qty | Shortage value | Remarks.
 *
 * @param bool $isAdmin whether the current user may target any office (via sheet-name matching)
 * @param int $sessionOfficeId office id to force for non-admins
 * @param int $defaultOfficeId fallback office id when a sheet name cannot be matched (admins only)
 */
function importItemsFromXlsx(PDO $conn, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $category, bool $isAdmin = true, int $sessionOfficeId = 0, int $defaultOfficeId = 0): array
{
    if (importItemsWorkbookLooksLikeRpcppe($spreadsheet)) {
        return importItemsFromRpcppeXlsx($conn, $spreadsheet, $isAdmin);
    }

    $created = 0;
    $skipped = 0;
    $errors = [];

    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $sheetName = $sheet->getTitle();
        if (importItemsIsJunkSheet($sheetName)) {
            continue;
        }
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();

        // Locate the header row (contains "PROPERTY" and ("NUMBER" or "NO")).
        $headerRow = 0;
        for ($r = 1; $r <= min($highestRow, 25); $r++) {
            $rowText = '';
            foreach ($sheet->getRowIterator($r, $r)->current()->getCellIterator('A', $highestCol) as $cell) {
                $rowText .= ' ' . strtoupper((string) $cell->getValue());
            }
            if (
                str_contains($rowText, 'ARTICLE')
                && str_contains($rowText, 'PROPERTY')
                && (str_contains($rowText, 'UNIT VALUE') || str_contains($rowText, 'UNIT  VALUE'))
            ) {
                $headerRow = $r;
                break;
            }
        }
        if ($headerRow === 0) {
            continue; // Not a data sheet (e.g. cover/annex sheet) — skip silently.
        }

        $officeId = $isAdmin
            ? importItemsResolveOffice($conn, $sheetName, $defaultOfficeId)
            : $sessionOfficeId;
        if (!$officeId || $officeId <= 0) {
            $errors[] = "Sheet \"$sheetName\": could not match an office, skipped";
            continue;
        }

        $currentGroup = '';
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $a = trim((string) $sheet->getCell('A' . $r)->getValue());
            $b = trim((string) $sheet->getCell('B' . $r)->getValue());
            $c = trim((string) $sheet->getCell('C' . $r)->getValue());
            $d = trim((string) $sheet->getCell('D' . $r)->getValue());
            $eVal = $sheet->getCell('E' . $r)->getValue();
            $fVal = $sheet->getCell('F' . $r)->getValue();
            $gVal = $sheet->getCell('G' . $r)->getValue();
            $jRemarks = trim((string) $sheet->getCell('J' . $r)->getValue());

            if ($a === '' && $b === '' && $c === '' && $eVal === null) {
                continue; // fully blank row
            }
            if (str_contains(strtoupper($a . $b), 'NOTHING FOLLOWS') || str_contains(strtoupper($a), 'TOTAL')) {
                continue;
            }
            if (importItemsIsSignatureOrFooterRow($a, $b, $c, $jRemarks)) {
                continue;
            }

            // Section header row: Article/title filled, Property No + UOM blank, Unit Value holds subtotal.
            if ($a !== '' && $c === '' && $d === '' && $b === '' && $eVal !== null && $eVal !== '') {
                $currentGroup = $a;
                continue;
            }

            // Item row needs at least a description or property number.
            if ($b === '' && $c === '') {
                continue;
            }

            $unitValue = is_numeric($eVal) ? (float) $eVal : (float) preg_replace('/[^0-9.\-]/', '', (string) $eVal);
            $qtyCard = is_numeric($fVal) ? (int) $fVal : 1;
            $qtyPhys = ($gVal !== null && $gVal !== '' && is_numeric($gVal)) ? (int) $gVal : null;
            if ($qtyCard < 1) {
                $qtyCard = 1;
            }

            try {
                $res = importItemsInsertRow(
                    $conn,
                    $category,
                    $officeId,
                    $a !== '' ? $a : null,
                    $b,
                    $d,
                    $unitValue,
                    $qtyCard,
                    $qtyPhys,
                    $currentGroup,
                    $c,
                    $jRemarks
                );
                if ($res['status'] === 'created') {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (PDOException $e) {
                $errors[] = "Sheet \"$sheetName\" row $r: " . $e->getMessage();
            }
        }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
}

/**
 * Backward-compatible importer for the flat CSV template previously produced by
 * api/items-export.php (Property Number, Article, Description, Report Section, ...).
 */
function importItemsFromCsv(PDO $conn, string $tmpPath, string $category, bool $isAdmin = true, int $sessionOfficeId = 0, int $defaultOfficeId = 0): array
{
    $created = 0;
    $skipped = 0;
    $errors = [];
    $handle = fopen($tmpPath, 'r');
    if (!$handle) {
        return ['created' => 0, 'skipped' => 0, 'errors' => ['Could not read uploaded file']];
    }

    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return ['created' => 0, 'skipped' => 0, 'errors' => ['File is empty or not a valid CSV']];
    }
    $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);

    $colMap = [
        'property number' => 'property_number', 'article' => 'article', 'description' => 'description',
        'report section' => 'item_group', 'unit of measure' => 'unit_of_measure', 'unit value' => 'unit_value',
        'qty (property card)' => 'quantity', 'qty (physical)' => 'quantity_physical_count',
        'office' => 'office_name', 'remarks' => 'remarks',
    ];
    $idx = [];
    foreach ($header as $i => $h) {
        if (isset($colMap[$h])) {
            $idx[$colMap[$h]] = $i;
        }
    }

    $rowNum = 1;

    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $get = static function (string $field) use ($row, $idx) {
            return isset($idx[$field]) ? ($row[$idx[$field]] ?? '') : '';
        };

        $officeNameCell = trim((string) $get('office_name'));
        $officeId = $isAdmin
            ? importItemsResolveOffice($conn, $officeNameCell, $defaultOfficeId)
            : $sessionOfficeId;
        if (!$officeId) {
            $errors[] = "Row $rowNum: could not resolve office";
            continue;
        }

        $description = trim((string) $get('description'));
        $article = trim((string) $get('article'));
        if ($description === '' && $article === '') {
            $errors[] = "Row $rowNum: article/description both empty, skipped";
            continue;
        }

        $unitValueRaw = trim((string) $get('unit_value'));
        $unitValue = $unitValueRaw === '' ? 0 : (float) preg_replace('/[^0-9.\-]/', '', $unitValueRaw);
        $qtyRaw = trim((string) $get('quantity'));
        $qty = $qtyRaw === '' ? 1 : (int) $qtyRaw;
        $qtyPhysRaw = trim((string) $get('quantity_physical_count'));
        $qtyPhys = $qtyPhysRaw === '' ? null : (int) $qtyPhysRaw;

        try {
            $res = importItemsInsertRow(
                $conn, $category, $officeId, $article, $description,
                trim((string) $get('unit_of_measure')), $unitValue, $qty, $qtyPhys,
                trim((string) $get('item_group')), trim((string) $get('property_number')),
                trim((string) $get('remarks'))
            );
            if ($res['status'] === 'created') {
                $created++;
            } else {
                $skipped++;
            }
        } catch (PDOException $e) {
            $errors[] = "Row $rowNum: " . $e->getMessage();
        }
    }
    fclose($handle);

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
}
