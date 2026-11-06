<?php
/** Batch-4 deep knowledge smoke */
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
    ['Ano ang tabs sa Settings ng Admin?', '/LGU|Inventory rules|Users|System|Profile|Password|Notifications/i'],
    ['Paano forgot password at ilang digit ang code?', '/6|Forgot|verification|5 minute|password/i'],
    ['Property tag ba available sa PPE?', '/Semi|SE only|hindi|not|physical count/i'],
    ['Ano ang May run out at Buy now sa Forecast?', '/Stockout|Reorder|May run out|Buy now/i'],
    ['Ano ang Office pulse tiles ng staff?', '/Drafts|Waiting|Needs fix|Quoting|With PO|On hand/i'],
    ['Paano mag-print ng RFQ?', '/Print RFQ|Procurement|quotation/i'],
    ['Ano columns ng Activity Log?', '/When|Who|Module|Action|Record/i'],
    ['Saan babaguhin ang GSO name sa letterhead?', '/LGU & print|Settings|GSO|letterhead/i'],
    ['A4 tag ilang pieces per sheet?', '/6|A4|Semi/i'],
    ['Password strength rules ano?', '/8|upper|lower|number|special/i'],
];

$pass = 0;
$fail = 0;
foreach ($cases as $i => $c) {
    [$q, $pat] = $c;
    echo str_repeat('=', 56) . "\nQ" . ($i + 1) . ": {$q}\n";
    $o = gsoAssistantAsk($conn, $q, []);
    $ans = (string) ($o['answer'] ?? '');
    $ok = (bool) preg_match($pat, $ans);
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . mb_substr(preg_replace('/\s+/', ' ', $ans), 0, 240) . "\n\n";
    $ok ? $pass++ : $fail++;
}
echo "SCORE: {$pass}/" . count($cases) . "\n";
exit($fail ? 2 : 0);
