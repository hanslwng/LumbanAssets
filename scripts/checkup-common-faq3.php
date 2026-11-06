<?php
/** Batch-3 accuracy smoke (completed-PR issuance, import disabled, etc.) */
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
    ['Paano mag-PAR / i-issue sa empleyado?', '/Completed|completed PR|Issuance|Issued to/i'],
    ['Bakit walang item sa issuance dropdown?', '/Completed|completed|PR/i'],
    ['Nasaan ang Import Excel button sa Semi-Expendable?', '/disabled|hidden|wala|not available|removed|hindi/i'],
    ['Ano ang Issued to sa PAR?', '/Issued to|name|accountable|custodian/i'],
    ['Puwede bang mag-Award PO ang staff?', '/No|Hindi|cannot|hindi|admin|GSO/i'],
    ['Ano ang Needs fix sa staff portal?', '/Needs fix|reject|canvass|Resubmit|feedback/i'],
    ['Paano mag-print ng physical count?', '/physical count|RPCSE|RPCPPE|Print/i'],
    ['Ano ang Report section sa encode?', '/Report section|item_group|group/i'],
    ['Forecast mode Live vs Demonstration?', '/Live|Demonstration|Settings|System|forecast/i'],
    ['Ano ang Still works at Cannot repair?', '/Serviceable|Defective|Pending|Still works|Cannot repair/i'],
];

$pass = 0;
$fail = 0;
foreach ($cases as $i => $c) {
    [$q, $pat] = $c;
    echo str_repeat('=', 56) . "\nQ" . ($i + 1) . ": {$q}\n";
    $o = gsoAssistantAsk($conn, $q, []);
    $ans = (string) ($o['answer'] ?? '');
    $ok = (bool) preg_match($pat, $ans);
    // Hard fail if it tells user to click Import as a how-to
    if (stripos($q, 'Import') !== false && preg_match('/Pumunta.*Import|click.*Import button|i-click ang \*\*Import/i', $ans) && !preg_match('/disabled|hidden|wala|removed/i', $ans)) {
        $ok = false;
        echo "HARDFAIL invent-import\n";
    }
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . mb_substr(preg_replace('/\s+/', ' ', $ans), 0, 240) . "\n\n";
    $ok ? $pass++ : $fail++;
}
echo "SCORE: {$pass}/" . count($cases) . "\n";
exit($fail ? 2 : 0);
