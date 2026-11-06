<?php
/**
 * Demo-only: replace forgotten 0000 acquisition years with 2023–2026
 * so inventory charts have a usable time range for system demonstration.
 *
 * CLI: php scripts/remap-unknown-acquisition-years.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from the command line: php scripts/remap-unknown-acquisition-years.php\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/bootstrap.php';
require_once $root . '/includes/demo_acquisition_year.php';

$conn = getDBConnection();

$stmt = $conn->query(
    "SELECT id, property_number, office_id, acquisition_date
     FROM property_items
     WHERE property_number LIKE '0000%'
     ORDER BY id ASC"
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$existsStmt = $conn->prepare('SELECT id FROM property_items WHERE property_number = ? AND id <> ? LIMIT 1');
$updateStmt = $conn->prepare(
    'UPDATE property_items SET property_number = ?, acquisition_date = ? WHERE id = ?'
);

$updated = 0;
$skipped = 0;
$byYear = [2023 => 0, 2024 => 0, 2025 => 0, 2026 => 0];
$errors = [];

$conn->beginTransaction();
try {
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $assigned = demoAssignUnknownAcquisitionYearUnique(
            (string) $row['property_number'],
            static function (string $candidate) use ($existsStmt, $id) {
                $existsStmt->execute([$candidate, $id]);
                return (bool) $existsStmt->fetch(PDO::FETCH_ASSOC);
            }
        );

        if (!$assigned['changed']) {
            $skipped++;
            continue;
        }

        $existsStmt->execute([$assigned['property_number'], $id]);
        if ($existsStmt->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = "ID {$id}: {$row['property_number']} → {$assigned['property_number']} already exists";
            $skipped++;
            continue;
        }

        $updateStmt->execute([
            $assigned['property_number'],
            $assigned['acquisition_date'],
            $id,
        ]);
        $updated++;
        $year = (int) $assigned['year'];
        if (isset($byYear[$year])) {
            $byYear[$year]++;
        }
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    fwrite(STDERR, 'Update failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$remaining = (int) $conn->query(
    "SELECT COUNT(*) FROM property_items WHERE property_number LIKE '0000%'"
)->fetchColumn();

echo "Remapped unknown (0000) acquisition years for demonstration.\n";
echo "Candidates: " . count($rows) . "\n";
echo "Updated:    {$updated}\n";
echo "Skipped:    {$skipped}\n";
echo "Remaining 0000: {$remaining}\n";
echo "By year:\n";
foreach ($byYear as $year => $count) {
    echo "  {$year}: {$count}\n";
}
if ($errors) {
    echo "Issues:\n";
    foreach (array_slice($errors, 0, 20) as $err) {
        echo "  {$err}\n";
    }
    if (count($errors) > 20) {
        echo '  ... and ' . (count($errors) - 20) . " more\n";
    }
}
