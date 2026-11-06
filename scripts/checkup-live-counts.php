<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/gso_assistant.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$conn = getDBConnection();
$a = $conn->query("SELECT id,username,full_name,role,office_id FROM users WHERE role='Admin' AND is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_SESSION['user_id'] = (int) $a['id'];
$_SESSION['user_role'] = (string) $a['role'];
$_SESSION['user_name'] = (string) ($a['full_name'] ?: $a['username']);
$_SESSION['user_office_id'] = (int) ($a['office_id'] ?? 0);
$_SESSION['user_office_name'] = '';
$o = gsoAssistantAsk($conn, 'Ilan ang total items, SE, PPE, at open purchase requests ngayon?', []);
$ans = (string) ($o['answer'] ?? '');
$norm = str_replace([',', ' '], '', $ans);
$ok = str_contains($norm, '1942') && str_contains($norm, '1624') && str_contains($norm, '318');
echo ($ok ? 'PASS' : 'FAIL') . " live_counts\n";
echo $ans . "\n";
