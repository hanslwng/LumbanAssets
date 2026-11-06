<?php
if (!function_exists('lgue_url')) {
    require_once dirname(__DIR__) . '/bootstrap.php';
}
$faviconSvg = lgue_url('public/assets/favicon.svg');
$logoPng = lgue_url('public/assets/logo.png');
?>
<link rel="icon" href="<?php echo htmlspecialchars($faviconSvg, ENT_QUOTES, 'UTF-8'); ?>" type="image/svg+xml" sizes="any">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logoPng, ENT_QUOTES, 'UTF-8'); ?>">
