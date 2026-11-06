<?php
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
$q = 'Paano gamitin ang Forecast at bakit minsan 0 ang order qty?';
$o = gsoAssistantAsk($conn, $q, []);
$a = (string) ($o['answer'] ?? '');
echo 'len=' . mb_strlen($a) . PHP_EOL;
echo $a . PHP_EOL;
echo (preg_match('/incoming|Excel|snapshot|Overstock/i', $a) ? "OK: finished why-0 reasons\n" : "WARN: incomplete why-0\n");
