<?php
/**
 * Scored checkup for GSO Assistant.
 * Usage: C:\xampp\php\php.exe scripts\checkup-gso-chatbot.php
 *        C:\xampp\php\php.exe scripts\checkup-gso-chatbot.php --save
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/gso_assistant.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getDBConnection();
$admin = $conn->query("SELECT id, username, full_name, role, office_id FROM users WHERE role='Admin' AND is_active=1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    fwrite(STDERR, "No admin\n");
    exit(1);
}
$_SESSION['user_id'] = (int) $admin['id'];
$_SESSION['user_role'] = (string) $admin['role'];
$_SESSION['user_name'] = (string) ($admin['full_name'] ?: $admin['username']);
$_SESSION['user_office_id'] = (int) ($admin['office_id'] ?? 0);
$_SESSION['user_office_name'] = '';

$ppe = settingsPpeThreshold($conn);
$stats = $conn->query(
    "SELECT COUNT(*) AS total,
            SUM(category='SE') AS se_n,
            SUM(category='PPE') AS ppe_n,
            SUM(status='Unserviceable') AS uns_n,
            SUM(status='Issued') AS iss_n
     FROM property_items"
)->fetch(PDO::FETCH_ASSOC) ?: [];
$offN = (int) $conn->query('SELECT COUNT(*) FROM offices WHERE is_active=1')->fetchColumn();
$prN = (int) $conn->query("SELECT COUNT(*) FROM proc_pr WHERE status <> 'draft'")->fetchColumn();
$pending = 0;
try {
    $pending = (int) $conn->query("SELECT COUNT(*) FROM unserviceable_records WHERE COALESCE(repair_status,'Pending')='Pending'")->fetchColumn();
} catch (Throwable $e) {
}
$samplePn = (string) ($conn->query("SELECT property_number FROM property_items WHERE property_number IS NOT NULL AND property_number <> '' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '');

$ppeFmt = number_format($ppe, 2);
$total = (int) ($stats['total'] ?? 0);
$seN = (int) ($stats['se_n'] ?? 0);
$ppeN = (int) ($stats['ppe_n'] ?? 0);

echo "LLM: " . (gsoAssistantLlmConfigured() ? 'YES' : 'NO') . PHP_EOL;
echo "Baseline: items={$total} SE={$seN} PPE={$ppeN} offices={$offN} non-draft PR={$prN} pendingUns={$pending} PPE thr=₱{$ppeFmt}" . PHP_EOL;
echo "Sample PN: " . ($samplePn !== '' ? $samplePn : '(none)') . PHP_EOL . PHP_EOL;

$cases = [
    [
        'id' => 'live_counts',
        'q' => 'Ilan ang total items, SE, PPE, at open purchase requests ngayon?',
        'must' => [
            (string) $total,
            (string) $seN,
            (string) $ppeN,
        ],
        'any' => ['open', 'PR', 'purchase', '0'],
        'forbid' => [],
    ],
    [
        'id' => 'ppe_threshold',
        'q' => 'Ano ang exact PPE threshold sa Settings ngayon?',
        'must' => [$ppeFmt, '50,000'],
        'any' => ['PPE', 'threshold', 'Settings'],
        'forbid' => [],
    ],
    [
        'id' => 'no_ris',
        'q' => 'Paano mag-create ng RIS form sa system?',
        'must' => [],
        'any' => ['walang RIS', 'wala.*RIS', 'no RIS', 'hindi.*RIS', 'not in'],
        'forbid' => ['sidebar.*RIS', 'pumunta sa RIS'],
    ],
    [
        'id' => 'no_ics_separate',
        'q' => 'Saan ang ICS module para sa semi-expendable?',
        'must' => [],
        'any' => ['PAR', 'walang.*ICS', 'no.*separate.*ICS', 'hindi.*hiwalay.*ICS', 'isang PAR'],
        'forbid' => [],
    ],
    [
        'id' => 'no_returns_page',
        'q' => 'Open the Returns module and process a return please.',
        'must' => [],
        'any' => ['no Returns', 'walang.*Returns', 'Still works', 'Unserviceable'],
        'forbid' => ['go to Returns', 'Pumunta sa Returns', 'Open Returns'],
    ],
    [
        'id' => 'no_edit_as_return',
        'q' => 'Para ibalik ang item from issuance, i-edit ko lang ba ang office field?',
        'must' => [],
        'any' => ['Still works', 'Unserviceable', 'hindi', 'not', 'huwag'],
        'forbid' => ['oo.*i-edit.*office', 'yes.*edit.*office.*return'],
    ],
    [
        'id' => 'no_iirup_auction',
        'q' => 'Paano mag-auction at mag-print ng IIRUP?',
        'must' => [],
        'any' => ['wala', 'walang', 'not in', 'hindi', 'no '],
        'forbid' => ['Pumunta sa IIRUP', 'Auction page'],
    ],
    [
        'id' => 'no_depreciation',
        'q' => 'Compute depreciation and NBV for all PPE.',
        'must' => [],
        'any' => ['not', 'walang', 'hindi', 'no '],
        'forbid' => ['here is the depreciation schedule'],
    ],
    [
        'id' => 'no_barcode',
        'q' => 'Generate barcode labels for property cards.',
        'must' => [],
        'any' => ['walang', 'not', 'hindi', 'Property Tag', 'tag'],
        'forbid' => [],
    ],
    [
        'id' => 'no_trip_ticket',
        'q' => 'Paano mag-issue ng trip ticket sa motor pool?',
        'must' => [],
        'any' => ['wala', 'walang', 'not in', 'hindi', 'no '],
        'forbid' => ['Pumunta sa Motor'],
    ],
    [
        'id' => 'dashboard_depts',
        'q' => 'Sa Dashboard frequency dropdown, kasama ba lahat ng department kahit walang PR?',
        'must' => [],
        'any' => ['lahat', 'all', '26', 'active office', 'kahit'],
        'forbid' => [],
    ],
    [
        'id' => 'empty_pr_chart',
        'q' => 'Bakit empty ang most requested items kung 0 ang non-draft PR?',
        'must' => [],
        'any' => ['0', 'empty', 'walang', 'draft', 'submit'],
        'forbid' => ['top item is Bond Paper'], // inventing
    ],
    [
        'id' => 'forecast_why0',
        'q' => 'Bakit walang item table o recommended order qty sa Forecast?',
        'must' => [],
        'any' => ['consumable', 'spending', 'allotted', 'briefing', 'removed', 'hindi', 'PR'],
        'forbid' => [],
    ],
    [
        'id' => 'par_steps',
        'q' => 'Step by step paano mag-PAR issuance?',
        'must' => [],
        'any' => ['Issuance', 'PAR', 'custodian'],
        'forbid' => ['RIS form'],
    ],
    [
        'id' => 'procurement_flow',
        'q' => 'Explain PR to PO to AIR statuses in this app.',
        'must' => [],
        'any' => ['submitted', 'quoting', 'po_issued', 'completed', 'AIR'],
        'forbid' => [],
    ],
    [
        'id' => 'staff_vs_admin',
        'q' => 'Ano ang hindi makikita ng staff na makikita ng admin?',
        'must' => [],
        'any' => ['Dashboard', 'staff', 'office', 'Award', 'PO'],
        'forbid' => [],
    ],
    [
        'id' => 'ppe_import_tabs',
        'q' => 'Sa PPE Excel import, offices ba ang sheet tabs?',
        'must' => [],
        'any' => ['asset', 'hindi office', 'not office', 'GSO', 'LAND', 'group'],
        'forbid' => ['each tab is an office'],
    ],
    [
        'id' => 'records_trend_gone',
        'q' => 'Nasaan ang Records Trend chart at Smart inventory recommendations sa Dashboard?',
        'must' => [],
        'any' => ['removed', 'wala', 'walang', 'Forecast', 'hindi'],
        'forbid' => [],
    ],
    [
        'id' => 'encode_howto',
        'q' => 'Paano mag-encode ng bagong laptop worth 45000?',
        'must' => [],
        'any' => ['Encode', 'Semi-Expendable', 'SE', '45'],
        'forbid' => [],
    ],
    [
        'id' => 'unserviceable_flow',
        'q' => 'Paano ang flow ng Unserviceable hanggang Still works?',
        'must' => [],
        'any' => ['Pending', 'Still works', 'Unserviceable'],
        'forbid' => [],
    ],
    [
        'id' => 'disposal_readonly',
        'q' => 'Puwede ba mag-delete/auction sa Disposal Records page?',
        'must' => [],
        'any' => ['read-only', 'readonly', 'tingin', 'walang auction', 'no auction', 'hindi'],
        'forbid' => [],
    ],
    [
        'id' => 'activity_log',
        'q' => 'Ano ang makikita sa Activity Log bilang admin?',
        'must' => [],
        'any' => ['Activity', 'sino', 'who', 'department', 'office'],
        'forbid' => [],
    ],
    [
        'id' => 'notifications',
        'q' => 'Para saan ang notifications bell sa topbar?',
        'must' => [],
        'any' => ['notif', 'alert', 'Unserviceable', 'bell', 'Mark'],
        'forbid' => [],
    ],
    [
        'id' => 'invent_feature_trap',
        'q' => 'Paano i-enable ang GPS asset tracking at WhatsApp approval for PRs?',
        'must' => [],
        'any' => ['wala', 'walang', 'not in', 'hindi', 'no '],
        'forbid' => ['Settings → GPS', 'enable WhatsApp'],
    ],
    [
        'id' => 'job_order',
        'q' => 'May Job Order module ba para sa repair shops?',
        'must' => [],
        'any' => ['wala', 'walang', 'not', 'hindi'],
        'forbid' => [],
    ],
];

if ($samplePn !== '') {
    $cases[] = [
        'id' => 'item_lookup',
        'q' => 'Hanapin ang item na may property number ' . $samplePn,
        'must' => [$samplePn],
        'any' => ['Matching', 'status', '₱', 'SE', 'PPE', 'Active', 'Issued', 'Unserviceable'],
        'forbid' => [],
    ];
}

$pass = 0;
$fail = 0;
$results = [];

foreach ($cases as $i => $case) {
    $n = $i + 1;
    $id = $case['id'];
    $q = $case['q'];
    echo str_repeat('=', 72) . PHP_EOL;
    echo "[{$n}/" . count($cases) . "] {$id}" . PHP_EOL;
    echo "Q: {$q}" . PHP_EOL;

    $t0 = microtime(true);
    $err = null;
    $answer = '';
    $source = '?';
    try {
        $out = gsoAssistantAsk($conn, $q, []);
        $answer = (string) ($out['answer'] ?? '');
        $source = (string) ($out['source'] ?? '?');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
    $ms = (int) round((microtime(true) - $t0) * 1000);

    $problems = [];
    if ($err !== null) {
        $problems[] = 'exception: ' . $err;
    }
    if ($answer === '') {
        $problems[] = 'empty answer';
    }
foreach ($case['must'] as $needle) {
        if ($needle === '') {
            continue;
        }
        $found = mb_stripos($answer, $needle) !== false;
        if (!$found) {
            $ansNorm = str_replace([',', ' '], '', $answer);
            $needNorm = str_replace([',', ' '], '', $needle);
            $found = $needNorm !== '' && mb_stripos($ansNorm, $needNorm) !== false;
        }
        if (!$found) {
            $problems[] = 'missing must: ' . $needle;
        }
    }
    if (!empty($case['any'])) {
        $hit = false;
        foreach ($case['any'] as $pat) {
            if (@preg_match('/' . $pat . '/iu', $answer)) {
                if (preg_match('/' . $pat . '/iu', $answer)) {
                    $hit = true;
                    break;
                }
            } elseif (mb_stripos($answer, $pat) !== false) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            $problems[] = 'missing any-of: ' . implode(' | ', $case['any']);
        }
    }
    foreach ($case['forbid'] as $pat) {
        if (@preg_match('/' . $pat . '/iu', $answer) && preg_match('/' . $pat . '/iu', $answer)) {
            $problems[] = 'forbid hit: ' . $pat;
        }
    }
    // truncation heuristic
    if (preg_match('/\d{1,3}$/u', trim($answer)) && preg_match('/^\d+$/u', substr(trim($answer), -1))) {
        // ends with single digit often mid-list — soft
        if (preg_match('/\n\d\s*$/u', $answer) || preg_match('/:\s*\d\s*$/u', $answer)) {
            $problems[] = 'possible truncation';
        }
    }

    $ok = $problems === [];
    if ($ok) {
        $pass++;
        echo "PASS  source={$source}  {$ms}ms  len=" . mb_strlen($answer) . PHP_EOL;
    } else {
        $fail++;
        echo "FAIL  source={$source}  {$ms}ms  len=" . mb_strlen($answer) . PHP_EOL;
        foreach ($problems as $p) {
            echo "  - {$p}" . PHP_EOL;
        }
    }
    // short preview
    $preview = preg_replace('/\s+/', ' ', $answer);
    echo 'A: ' . mb_substr($preview, 0, 280) . (mb_strlen($preview) > 280 ? '…' : '') . PHP_EOL . PHP_EOL;

    $results[] = [
        'id' => $id,
        'ok' => $ok,
        'problems' => $problems,
        'ms' => $ms,
        'source' => $source,
        'len' => mb_strlen($answer),
        'answer' => $answer,
        'q' => $q,
    ];
}

echo str_repeat('=', 72) . PHP_EOL;
echo "SCORE: {$pass} pass / {$fail} fail / " . count($cases) . " total" . PHP_EOL;
if ($fail > 0) {
    echo "FAILED IDS: ";
    echo implode(', ', array_map(static fn ($r) => $r['id'], array_filter($results, static fn ($r) => !$r['ok'])));
    echo PHP_EOL;
}

if (in_array('--save', $argv ?? [], true)) {
    $path = dirname(__DIR__) . '/scripts/checkup-gso-chatbot-last.json';
    file_put_contents($path, json_encode([
        'at' => date('c'),
        'pass' => $pass,
        'fail' => $fail,
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Saved {$path}\n";
}

exit($fail > 0 ? 2 : 0);
