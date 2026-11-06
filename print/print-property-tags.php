<?php
/**
 * Print Property Tags — A4/Letter 6-up or Long/Legal 8-up.
 * Query: layout=a4|long, ids=1,2,3
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/property_tag_helpers.php';

PermissionHelper::require('print');

$layout = isset($_GET['layout']) ? strtolower(trim((string) $_GET['layout'])) : 'a4';
if ($layout !== 'long') {
    $layout = 'a4';
}
$rawIds = isset($_GET['ids']) ? (string) $_GET['ids'] : '';
$ids = array_map('intval', preg_split('/[,\s]+/', $rawIds, -1, PREG_SPLIT_NO_EMPTY) ?: []);

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

$rows = propertyTagLoadPrintRows($conn, $ids);
if (!PermissionHelper::isAdmin()) {
    $oid = isset($_SESSION['user_office_id']) ? (int) $_SESSION['user_office_id'] : -1;
    $rows = array_values(array_filter($rows, static function ($row) use ($oid) {
        return (int) ($row['office_id'] ?? 0) === $oid;
    }));
}
$rows = array_values(array_filter($rows, static function ($row) {
    return strtoupper((string) ($row['category'] ?? '')) === 'SE';
}));

$perPage = $layout === 'long' ? 8 : 6;
$logoUrl = lgue_url('public/assets/property-tag-seal.png');
$cssUrl = lgue_url('assets/css/property-tag.css', defined('SYSTEM_VERSION') ? SYSTEM_VERSION : '');
$title = $layout === 'long' ? 'Long Tag' : 'A4 Tag';
$pages = $rows ? array_chunk($rows, $perPage) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> · Property Tags</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="ptag-print-body ptag-layout-<?php echo htmlspecialchars($layout, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!$rows): ?>
    <p class="ptag-print-empty">No semi-expendable items selected.</p>
<?php else: ?>
    <?php foreach ($pages as $pageRows): ?>
    <section class="ptag-sheet">
        <?php foreach ($pageRows as $row): ?>
        <div class="ptag-cell-slot">
            <?php echo propertyTagRender($row, $row['tag'], $logoUrl, 'print'); ?>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>
<?php endif; ?>
<script>
window.addEventListener('load', function () {
    if (document.querySelector('.ptag-sheet')) {
        window.print();
    }
});
</script>
</body>
</html>
