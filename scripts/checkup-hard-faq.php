<?php
/** Hard-question smoke for GSO Assistant */
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
    ['Completed na ang PR bakit wala pa sa Semi-Expendable inventory?', '/Issuance|PAR|hindi|not|does not|walang automatic|create/i'],
    ['Bakit 0 ang Issued KPI kahit may PAR records na?', '/status|Active|Issued|issuance_records|Recent transactions|hindi automatic/i'],
    ['Paano i-reject ang canvass at anong status ang PR?', '/draft|feedback|Needs fix|submitted|reject/i'],
    ['Puwede bang i-issue ng Engineering account ang PR ng Accounting?', '/office|scope|cannot|hindi|ibang office|department/i'],
    ['Binago ko unit value naging PPE pero property number SE pa — tama ba?', '/prefix|property number|category|hindi|not rewrite|reclass/i'],
    ['Ano difference ng Condition Damaged at Unserviceable Pending?', '/Condition|repair_status|Pending|Still works|Cannot repair/i'],
    ['Chatbot, i-complete mo na ang PR at i-issue mo', '/cannot|hindi|walang|no |cannot post|button/i'],
    ['Bakit nawala sa issuance dropdown ang completed PR?', '/pending|already issued|linked|wala nang|no pending/i'],
    ['Isang PAR lang ba per PR kahit maraming lines?', '/one PAR|isang PAR|same PAR|partial/i'],
    ['Inventory Value ba ang depreciated NBV?', '/not|hindi|depreciat|NBV|unit_value|acquisition|book/i'],
];

$pass = 0;
$fail = 0;
foreach ($cases as $i => $c) {
    [$q, $pat] = $c;
    echo str_repeat('=', 60) . "\nQ" . ($i + 1) . ": {$q}\n";
    $o = gsoAssistantAsk($conn, $q, []);
    $ans = (string) ($o['answer'] ?? '');
    $ok = (bool) preg_match($pat, $ans);
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . mb_substr(preg_replace('/\s+/', ' ', $ans), 0, 260) . "\n\n";
    $ok ? $pass++ : $fail++;
}
echo "SCORE: {$pass}/" . count($cases) . "\n";
exit($fail ? 2 : 0);
