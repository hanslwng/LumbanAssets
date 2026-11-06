<?php
/** Targeted retest after knowledge harden */
if (PHP_SAPI !== 'cli') { exit(1); }
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/gso_assistant.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
session_start();
$conn = getDBConnection();
$admin = $conn->query("SELECT id, username, full_name, role, office_id FROM users WHERE role='Admin' AND is_active=1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_SESSION['user_id'] = (int) $admin['id'];
$_SESSION['user_role'] = (string) $admin['role'];
$_SESSION['user_name'] = (string) ($admin['full_name'] ?: $admin['username']);
$_SESSION['user_office_id'] = (int) ($admin['office_id'] ?? 0);
$_SESSION['user_office_name'] = '';

$qs = [
    'May Returns module ba? Paano ibalik ang item?',
    'Paano gamitin ang Forecast at bakit minsan 0 ang order qty?',
    'Ano difference ng property tag at property card sa app na ito?',
    'Paano mag-depreciate ng PPE at i-print ang property card barcode?',
    'Ano ginagawa ng Notifications bell?',
];
foreach ($qs as $i => $q) {
    echo str_repeat('=', 60) . PHP_EOL;
    echo 'Q' . ($i + 1) . ': ' . $q . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;
    $o = gsoAssistantAsk($conn, $q, []);
    echo ($o['answer'] ?? '') . PHP_EOL . PHP_EOL;
}
echo "DONE\n";
