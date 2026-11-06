<?php
/**
 * Dual-role chatbot checkup: live facts + answers for admin and department users.
 * Usage: C:\xampp\php\php.exe scripts\checkup-chatbot-roles.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
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

function roleCheck(string $id, bool $ok, string $detail = ''): void
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

function loginAs(array $u, string $officeName = ''): void
{
    $_SESSION['user_id'] = (int) $u['id'];
    $_SESSION['user_role'] = (string) $u['role'];
    $_SESSION['user_name'] = (string) ($u['full_name'] ?: $u['username']);
    $_SESSION['user_office_id'] = (int) ($u['office_id'] ?? 0);
    $_SESSION['user_office_name'] = $officeName;
}

function askPreview(PDO $conn, string $q): array
{
    $t0 = microtime(true);
    $out = gsoAssistantAsk($conn, $q, []);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $ans = (string) ($out['answer'] ?? '');
    $src = (string) ($out['source'] ?? '?');

    return [$ans, $src, $ms];
}

$admin = $conn->query(
    "SELECT id, username, full_name, role, office_id FROM users
     WHERE role='Admin' AND is_active=1 AND (office_id IS NULL OR office_id = 0)
     ORDER BY id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    $admin = $conn->query("SELECT id, username, full_name, role, office_id FROM users WHERE role='Admin' AND is_active=1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
$staff = $conn->query(
    "SELECT id, username, full_name, role, office_id FROM users
     WHERE LOWER(role) IN ('user','staff') AND is_active=1 AND office_id IS NOT NULL AND office_id > 0
     ORDER BY id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$staffOffice = '';
if ($staff) {
    $st = $conn->prepare('SELECT name FROM offices WHERE id = ?');
    $st->execute([(int) $staff['office_id']]);
    $staffOffice = (string) ($st->fetchColumn() ?: '');
}

$lguItems = (int) $conn->query('SELECT COUNT(*) FROM property_items')->fetchColumn();
$lguPr = (int) $conn->query("SELECT COUNT(*) FROM proc_pr WHERE status <> 'draft'")->fetchColumn();
$samplePr = (string) ($conn->query("SELECT pr_number FROM proc_pr WHERE pr_number IS NOT NULL AND pr_number <> '' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '');
$samplePn = (string) ($conn->query("SELECT property_number FROM property_items WHERE property_number IS NOT NULL AND property_number <> '' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '');
$namedOffice = (string) ($conn->query("SELECT name FROM offices WHERE is_active=1 AND name <> '' ORDER BY id LIMIT 1")->fetchColumn() ?: '');
if ($staffOffice !== '' && strcasecmp($namedOffice, $staffOffice) === 0) {
    $namedOffice = (string) ($conn->query("SELECT name FROM offices WHERE is_active=1 AND name <> '' AND name <> " . $conn->quote($staffOffice) . " ORDER BY id LIMIT 1")->fetchColumn() ?: $namedOffice);
}

echo 'LLM: ' . (gsoAssistantLlmConfigured() ? 'YES' : 'NO (guide)') . PHP_EOL;
echo 'Admin: ' . ($admin['username'] ?? '?') . ' office_id=' . (int) ($admin['office_id'] ?? 0) . PHP_EOL;
echo 'Staff: ' . ($staff ? ($staff['username'] . ' / ' . $staffOffice) : '(none)') . PHP_EOL;
echo "LGU items={$lguItems} non-draft PR={$lguPr} samplePR={$samplePr} samplePN={$samplePn}" . PHP_EOL . PHP_EOL;

loginAs($admin);
roleCheck('admin_unscoped', PermissionHelper::isUnscopedAdmin() || !PermissionHelper::isDepartmentScoped(), 'admin can see LGU-wide');

$adminFactsQ = [
    'live_totals' => 'Ilan ang total items at open purchase requests ngayon sa buong LGU?',
    'human_how' => 'Uy, paano ba magpa-PAR? yung completed PR namin gusto nang i-issue.',
    'heavy_completed' => 'Completed na ang PR, bakit wala pa sa Semi-Expendable at bakit empty ang Issue PAR dropdown?',
    'forecast_human' => 'Magkano ba ang predicted spend next month, at ano yung allotted budget sa Forecast?',
    'missing' => 'Pwede ba i-compute ng system yung depreciation at NBV?',
    'office_named' => $namedOffice !== '' ? ('Ilan ang items at PR ng ' . $namedOffice . '?') : 'Ilan ang active offices?',
];
if ($samplePn !== '') {
    $adminFactsQ['lookup_pn'] = 'Hanapin ang property number ' . $samplePn;
}
if ($samplePr !== '') {
    $adminFactsQ['lookup_pr'] = 'Ano status ng ' . $samplePr . '?';
}

echo "=== ADMIN live facts ===" . PHP_EOL;
foreach ($adminFactsQ as $id => $q) {
    $facts = gsoAssistantLiveFacts($conn, $q);
    $ok = true;
    $why = '';
    if ($id === 'live_totals') {
        $ok = str_contains($facts, 'all offices') && str_contains(str_replace(',', '', $facts), (string) $lguItems);
        $why = 'need LGU item total ' . $lguItems;
    } elseif ($id === 'forecast_human') {
        $ok = str_contains($facts, 'forecast') || str_contains($facts, 'Forecast') || str_contains($facts, 'predicted');
        $why = 'need forecast pesos';
    } elseif ($id === 'office_named' && $namedOffice !== '') {
        $ok = str_contains($facts, 'Pulse [') || str_contains($facts, $namedOffice);
        $why = 'need named office pulse';
    } elseif ($id === 'lookup_pn') {
        $ok = str_contains($facts, $samplePn);
        $why = 'need PN in facts';
    } elseif ($id === 'lookup_pr') {
        $ok = str_contains($facts, $samplePr);
        $why = 'need PR in facts';
    }
    roleCheck('admin_facts_' . $id, $ok, $ok ? mb_strlen($facts) . ' chars' : $why);
}

echo PHP_EOL . '=== ADMIN answers ===' . PHP_EOL;
foreach ($adminFactsQ as $id => $q) {
    [$ans, $src, $ms] = askPreview($conn, $q);
    $flat = mb_strtolower($ans);
    $ok = $ans !== '' && mb_strlen($ans) > 40;
    if ($id === 'missing') {
        $ok = (bool) preg_match('/wala|walang|not in|hindi|no /iu', $ans);
    } elseif ($id === 'heavy_completed') {
        $ok = (bool) preg_match('/issuance|PAR|pending|completed/i', $ans);
    } elseif ($id === 'human_how') {
        $ok = (bool) preg_match('/Issuance|PAR|Issued to/i', $ans);
    } elseif ($id === 'forecast_human') {
        $ok = (bool) preg_match('/forecast|allotted|predicted|consumable|₱|php/i', $ans);
    } elseif ($id === 'live_totals') {
        $digits = str_replace([',', ' '], '', $ans);
        $ok = str_contains($digits, (string) $lguItems) || str_contains($ans, number_format($lguItems));
    }
    roleCheck('admin_ans_' . $id, $ok, $src . ' ' . $ms . 'ms len=' . mb_strlen($ans));
    echo '  Q: ' . $q . PHP_EOL;
    echo '  A: ' . mb_substr(preg_replace('/\s+/', ' ', $ans), 0, 220) . PHP_EOL;
}

if (!$staff) {
    echo PHP_EOL . "SKIP staff — no department user" . PHP_EOL;
} else {
    loginAs($staff, $staffOffice);
    $oid = (int) $staff['office_id'];
    $st = $conn->prepare('SELECT COUNT(*) FROM property_items WHERE office_id = ?');
    $st->execute([$oid]);
    $staffItems = (int) $st->fetchColumn();
    $st = $conn->prepare("SELECT COUNT(*) FROM proc_pr WHERE office_id = ? AND status <> 'draft'");
    $st->execute([$oid]);
    $staffPr = (int) $st->fetchColumn();
    $st = $conn->prepare("SELECT pr_number FROM proc_pr WHERE office_id = ? AND pr_number <> '' ORDER BY id DESC LIMIT 1");
    $st->execute([$oid]);
    $staffPrNo = (string) ($st->fetchColumn() ?: '');
    $st = $conn->prepare("SELECT pr_number FROM proc_pr WHERE office_id <> ? AND pr_number <> '' ORDER BY id DESC LIMIT 1");
    $st->execute([$oid]);
    $otherPrNo = (string) ($st->fetchColumn() ?: '');

    echo PHP_EOL . "=== STAFF {$staffOffice} facts (items={$staffItems} PR={$staffPr}) ===" . PHP_EOL;
    $sfq = [
        'own_counts' => 'Ilan ang items namin at ilan ang PR namin?',
        'human_queue' => 'Musta, anong next ko? may draft o needs fix ba kami?',
        'forecast' => 'Kailangan pa ba namin mag-PR next month, magkano predicted spend namin?',
        'privacy_wide' => 'Ilan ang forecast spend ng lahat ng departments?',
        'privacy_named' => $namedOffice !== '' ? ('Ilan ang inventory ng ' . $namedOffice . '?') : 'Ilan ang ibang office?',
        'casual' => 'Meron ba kaming printer or laptop na naka-encode?',
        'heavy' => 'Completed na PR namin, bakit hindi pa nami-issue at kailangan pa ba ng AIR?',
    ];
    if ($staffPrNo !== '') {
        $sfq['own_pr'] = 'Ano status ng ' . $staffPrNo . '?';
    }
    if ($otherPrNo !== '') {
        $sfq['other_pr'] = 'Ano status ng ' . $otherPrNo . '?';
    }

    foreach ($sfq as $id => $q) {
        $facts = gsoAssistantLiveFacts($conn, $q);
        $ok = true;
        $why = '';
        if ($id === 'own_counts') {
            $ok = str_contains($facts, 'this office') && str_contains(str_replace(',', '', $facts), (string) $staffItems)
                && !str_contains($facts, 'PRs by office (top)');
            $why = 'need this-office item count, no LGU ranking';
        } elseif ($id === 'privacy_wide' || $id === 'privacy_named') {
            $ok = str_contains($facts, 'PRIVACY HIT') || str_contains($facts, 'withheld');
            $why = 'need privacy hit';
        } elseif ($id === 'other_pr') {
            $ok = str_contains($facts, 'not in this office') || str_contains($facts, 'PRIVACY');
            $why = 'must not dump other office PR';
        } elseif ($id === 'own_pr') {
            $ok = str_contains($facts, $staffPrNo);
            $why = 'need own PR';
        } elseif ($id === 'forecast') {
            $ok = str_contains($facts, 'This office forecast') || str_contains($facts, 'forecast');
            $why = 'need own forecast';
        } elseif ($id === 'human_queue') {
            $ok = str_contains($facts, 'needs-fix') || str_contains($facts, 'Pulse');
            $why = 'need queue pulse';
        }
        roleCheck('staff_facts_' . $id, $ok, $ok ? mb_strlen($facts) . ' chars' : $why);
    }

    echo PHP_EOL . '=== STAFF answers ===' . PHP_EOL;
    foreach ($sfq as $id => $q) {
        [$ans, $src, $ms] = askPreview($conn, $q);
        $ok = $ans !== '' && mb_strlen($ans) > 30;
        if ($id === 'privacy_wide' || $id === 'privacy_named') {
            $ok = (bool) preg_match('/privacy|this office|your office|hindi|cannot|office only/iu', $ans);
        } elseif ($id === 'other_pr') {
            $ok = (bool) preg_match('/not in this office|privacy|cannot|hindi|your office/iu', $ans)
                && !preg_match('/status (draft|submitted|quoting|completed|po_issued)/i', $ans);
        } elseif ($id === 'forecast') {
            $ok = (bool) preg_match('/forecast|₱|allotted|predicted|PR/i', $ans);
        } elseif ($id === 'heavy') {
            $ok = (bool) preg_match('/PAR|AIR|canvass|admin|Award|Completed/i', $ans);
        } elseif ($id === 'own_counts') {
            $digits = str_replace([',', ' '], '', $ans);
            $ok = str_contains($digits, (string) $staffItems) || str_contains($ans, number_format($staffItems));
        }
        roleCheck('staff_ans_' . $id, $ok, $src . ' ' . $ms . 'ms len=' . mb_strlen($ans));
        echo '  Q: ' . $q . PHP_EOL;
        echo '  A: ' . mb_substr(preg_replace('/\s+/', ' ', $ans), 0, 220) . PHP_EOL;
    }
}

echo PHP_EOL . "RESULT  pass={$pass} fail={$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
