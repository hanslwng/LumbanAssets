<?php
/** Batch-2 common human FAQ smoke */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
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

$cases = [
    ['Ano difference ng Draft at Submit sa PR?', '/Draft|Submit|submitted/i'],
    ['Ano ang PO at AIR?', '/Purchase Order|Acceptance|AIR|PO/i'],
    ['RA 9184 ba ang system na ito?', '/9184|PhilGEPS|e-bidding|hindi|not/i'],
    ['Paano mag-edit ng description ng item?', '/Edit|Semi|PPE/i'],
    ['Paano i-edit ang cells sa PAR Excel?', '/View|Save|Issuance|PAR/i'],
    ['Ano ibig sabihin ng Inventory Value?', '/value|book|Dashboard|LIVE|₱/i'],
    ['Listahan ng offices / departments?', '/26|office|Settings/i'],
    ['After completed PR anong next step?', '/Encode|PAR|Issuance|Completed/i'],
    ['Paano mark Cannot repair?', '/Cannot repair|Unserviceable|Defective/i'],
    ['Physical count ba ang Forecast?', '/snapshot|Forecast|hindi|not|demand/i'],
    ['Mark all notifications as read?', '/Mark|bell|notif/i'],
    ['Bakit hindi makita ng staff ang ibang office?', '/office|Staff|scoped|sarili|own/i'],
];

$pass = 0;
$fail = 0;
foreach ($cases as $i => $c) {
    [$q, $pat] = $c;
    echo str_repeat('=', 56) . "\nQ" . ($i + 1) . ": {$q}\n";
    $o = gsoAssistantAsk($conn, $q, []);
    $ans = (string) ($o['answer'] ?? '');
    $ok = (bool) preg_match($pat, $ans);
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . mb_substr(preg_replace('/\s+/', ' ', $ans), 0, 220) . "\n\n";
    $ok ? $pass++ : $fail++;
}
echo "SCORE: {$pass}/" . count($cases) . "\n";
exit($fail ? 2 : 0);
