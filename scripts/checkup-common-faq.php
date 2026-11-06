<?php
/** Smoke common human questions after FAQ feed */
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
    ['nakalimutan ko password ko paano', '/Settings|Forgot|password|Sign out/i'],
    ['Paano ko ibibigay ang laptop sa empleyado?', '/Issuance|PAR|custodian/i'],
    ['Sino ang Mayor at GSO sa print?', '/BELEN|RHONALIZA|Mayor|GSO|Settings/i'],
    ['Paano magdagdag ng bagong office?', '/Settings|office|department/i'],
    ['Mali ang category SE dapat PPE paano ayusin?', '/threshold|Settings|unit value|PPE|Semi/i'],
    ['Paano mag-print ng sticker o property tag?', '/Property Tag|A4|Long|Semi|PPE/i'],
    ['Ano status ng mga PR ngayon?', '/draft|submitted|quoting|0|open|Purchase/i'],
    ['Makakapag-save ba ako ng PAR through chatbot?', '/cannot|hindi|no |walang|cannot post|hindi puwede|cannot create/i'],
];

$pass = 0;
$fail = 0;
foreach ($cases as $i => $c) {
    [$q, $pat] = $c;
    echo str_repeat('=', 60) . "\nQ" . ($i + 1) . ": {$q}\n";
    $o = gsoAssistantAsk($conn, $q, []);
    $ans = (string) ($o['answer'] ?? '');
    $ok = (bool) preg_match($pat, $ans);
    if ($ok) {
        $pass++;
        echo "PASS\n";
    } else {
        $fail++;
        echo "FAIL expect {$pat}\n";
    }
    echo mb_substr(preg_replace('/\s+/', ' ', $ans), 0, 260) . "\n\n";
}
echo "SCORE: {$pass}/" . count($cases) . "\n";
exit($fail ? 2 : 0);
