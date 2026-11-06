<?php
/**
 * Extra chatbot stress checks: follow-up history, staff scope, gibberish, API rate shape.
 * Usage: C:\xampp\php\php.exe scripts\checkup-gso-chatbot-extra.php
 */
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
$pass = 0;
$fail = 0;

function check(string $id, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "PASS  {$id}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "FAIL  {$id}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}

// --- Admin follow-up history ---
$admin = $conn->query("SELECT id, username, full_name, role, office_id FROM users WHERE role='Admin' AND is_active=1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_SESSION['user_id'] = (int) $admin['id'];
$_SESSION['user_role'] = (string) $admin['role'];
$_SESSION['user_name'] = (string) ($admin['full_name'] ?: $admin['username']);
$_SESSION['user_office_id'] = (int) ($admin['office_id'] ?? 0);
$_SESSION['user_office_name'] = '';

echo "=== History follow-up ===" . PHP_EOL;
$h1 = gsoAssistantAsk($conn, 'Ano ang PPE cutoff?', []);
$a1 = (string) ($h1['answer'] ?? '');
$hist = [
    ['role' => 'user', 'content' => 'Ano ang PPE cutoff?'],
    ['role' => 'assistant', 'content' => mb_substr($a1, 0, 800)],
];
$h2 = gsoAssistantAsk($conn, 'Paano ko ito babaguhin?', $hist);
$a2 = (string) ($h2['answer'] ?? '');
check(
    'followup_settings',
    (bool) preg_match('/Settings|PPE Threshold|threshold/i', $a2),
    'len=' . mb_strlen($a2)
);
echo '  preview: ' . mb_substr(preg_replace('/\s+/', ' ', $a2), 0, 200) . PHP_EOL;

echo PHP_EOL . "=== Invent then correct ===" . PHP_EOL;
$inv = gsoAssistantAsk($conn, 'May RFID scanner module ba sa sidebar?', []);
$ainv = (string) ($inv['answer'] ?? '');
check(
    'no_rfid',
    (bool) preg_match('/wala|walang|not in|hindi|no /iu', $ainv)
        && !preg_match('/Pumunta sa RFID|go to RFID/i', $ainv),
    'len=' . mb_strlen($ainv)
);

echo PHP_EOL . "=== Gibberish / off-topic ===" . PHP_EOL;
$gib = gsoAssistantAsk($conn, 'asdf qwerty xyz123 how to cook adobo', []);
$ag = (string) ($gib['answer'] ?? '');
check(
    'gibberish_grounded',
    $ag !== '' && (bool) preg_match('/inventory|GSO|system|app|Lumban|hindi|help|tanong/iu', $ag),
    'len=' . mb_strlen($ag)
);

echo PHP_EOL . "=== English + Filipino mix ===" . PHP_EOL;
$mix = gsoAssistantAsk($conn, 'How do I mark an item as Still works after unserviceable report?', []);
$am = (string) ($mix['answer'] ?? '');
check(
    'en_still_works',
    (bool) preg_match('/Unserviceable|Still works/i', $am),
    'len=' . mb_strlen($am)
);

echo PHP_EOL . "=== Live offices count ===" . PHP_EOL;
$off = gsoAssistantAsk($conn, 'Ilan ang active offices/departments?', []);
$ao = (string) ($off['answer'] ?? '');
$offN = (int) $conn->query('SELECT COUNT(*) FROM offices WHERE is_active=1')->fetchColumn();
check(
    'office_count',
    str_contains(str_replace([',', ' '], '', $ao), (string) $offN),
    "expect {$offN}; preview=" . mb_substr(preg_replace('/\s+/', ' ', $ao), 0, 160)
);

// --- Staff user if available ---
echo PHP_EOL . "=== Staff scope (if any staff user) ===" . PHP_EOL;
$staff = $conn->query(
    "SELECT id, username, full_name, role, office_id FROM users
     WHERE role IN ('User','Staff','staff','user') AND is_active=1 AND office_id IS NOT NULL AND office_id > 0
     ORDER BY id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if ($staff) {
    $_SESSION['user_id'] = (int) $staff['id'];
    $_SESSION['user_role'] = (string) $staff['role'];
    $_SESSION['user_name'] = (string) ($staff['full_name'] ?: $staff['username']);
    $_SESSION['user_office_id'] = (int) ($staff['office_id'] ?? 0);
    $oname = '';
    try {
        $st = $conn->prepare('SELECT name FROM offices WHERE id = ?');
        $st->execute([$_SESSION['user_office_id']]);
        $oname = (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) {
    }
    $_SESSION['user_office_name'] = $oname;

    $sq = gsoAssistantAsk($conn, 'Ilan ang forecast spend ng lahat ng departments?', []);
    $as = (string) ($sq['answer'] ?? '');
    check(
        'staff_privacy_no_lgu_wide',
        (bool) preg_match('/privacy|this office|your office|hindi|cannot|office only|department/iu', $as)
            && !preg_match('/PRs by office \(top\)/i', $as),
        'user=' . $staff['username'] . ' office=' . $oname
    );
    echo '  preview: ' . mb_substr(preg_replace('/\s+/', ' ', $as), 0, 220) . PHP_EOL;

    $sf = gsoAssistantAsk($conn, 'May Forecast ba ako sa staff portal?', []);
    $af = (string) ($sf['answer'] ?? '');
    check(
        'staff_has_own_forecast',
        (bool) preg_match('/Forecast/i', $af) && (bool) preg_match('/office|department|portal|briefing|tab/i', $af),
        'len=' . mb_strlen($af)
    );
} else {
    echo "SKIP  staff_no_admin_forecast — no staff user with office in DB" . PHP_EOL;
}

// --- Guide mode smoke (facts packing) ---
echo PHP_EOL . "=== Facts pack contains PR pipeline ===" . PHP_EOL;
$_SESSION['user_id'] = (int) $admin['id'];
$_SESSION['user_role'] = (string) $admin['role'];
$_SESSION['user_name'] = (string) ($admin['full_name'] ?: $admin['username']);
$_SESSION['user_office_id'] = (int) ($admin['office_id'] ?? 0);
$_SESSION['user_office_name'] = '';
$facts = gsoAssistantLiveFacts($conn, 'dashboard procurement forecast');
check('facts_pr', str_contains($facts, 'Purchase requests'));
check('facts_items', str_contains($facts, 'Items ('));
check('facts_forecast', str_contains($facts, 'Forecast') || str_contains($facts, 'forecast'));

echo PHP_EOL . "SCORE: {$pass} pass / {$fail} fail" . PHP_EOL;
exit($fail > 0 ? 2 : 0);
