<?php
/**
 * CLI smoke-test for GSO Assistant (knowledge + live facts + optional LLM).
 * Usage: C:\xampp\php\php.exe scripts\test-gso-assistant.php
 *        C:\xampp\php\php.exe scripts\test-gso-assistant.php --quick
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
    fwrite(STDERR, "No admin user\n");
    exit(1);
}

$_SESSION['user_id'] = (int) $admin['id'];
$_SESSION['user_role'] = (string) $admin['role'];
$_SESSION['user_name'] = (string) ($admin['full_name'] ?: $admin['username']);
$_SESSION['user_office_id'] = (int) ($admin['office_id'] ?? 0);
$_SESSION['user_office_name'] = '';

$quick = in_array('--quick', $argv ?? [], true);

echo "LLM configured: " . (gsoAssistantLlmConfigured() ? 'YES' : 'NO') . PHP_EOL;
echo "Admin session: {$admin['username']} (id {$admin['id']})" . PHP_EOL;
echo "Suite: " . ($quick ? 'quick' : 'full') . PHP_EOL . PHP_EOL;

$core = [
    'Ano ang meron sa Dashboard?',
    'Paano mag-encode ng bagong item?',
    'Paano mag-PAR?',
    'Paano ang procurement PR to PO to AIR?',
    'Ano ang PPE cutoff ngayon?',
    'May Returns module ba? Paano ibalik ang item?',
    'May RIS ba sa app? May IIRUP ba?',
    'Ano difference ng staff portal at admin?',
    'Sa Dashboard, lahat ba ng department nasa dropdown kahit walang data?',
    'Paano gamitin ang Forecast at bakit minsan 0 ang order qty?',
];

$extra = [
    'Ano ang hinihingi ng Accounting Office sa PR? Ano ang most requested items?',
    'Paano mag-depreciate ng PPE at i-print ang property card barcode?',
    'Ano difference ng property tag at property card sa app na ito?',
    'Paano mag-canvass ng quotations sa PR?',
    'Ano ginagawa ng Notifications bell?',
    'Paano i-print ang PAR Excel after issuance?',
    'May Records Trend ba sa Dashboard? May Smart recommendations card ba?',
    'Paano i-import ang PPE Excel — offices ba ang tabs?',
    'Ano ang Viewer role?',
    'Kung 0 ang non-draft PRs, bakit empty ang most requested chart?',
    'Paano i-report ang unserviceable at ano ang Still works?',
    'Saan makikita ang Disposal Records at may auction ba?',
    'Ano laman ng Activity Log?',
    'Paano baguhin ang PPE threshold sa Settings?',
];

$questions = $quick ? array_slice(array_merge($core, $extra), 0, 8) : array_merge($core, $extra);

$failHints = [];
foreach ($questions as $i => $q) {
    $n = $i + 1;
    echo str_repeat('=', 72) . PHP_EOL;
    echo "Q{$n}: {$q}" . PHP_EOL;
    echo str_repeat('-', 72) . PHP_EOL;
    $t0 = microtime(true);
    try {
        $out = gsoAssistantAsk($conn, $q, []);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $answer = (string) ($out['answer'] ?? '');
        $source = (string) ($out['source'] ?? '?');
        $len = mb_strlen($answer);
        echo "source={$source}  time={$ms}ms  len={$len}" . PHP_EOL;
        echo $answer . PHP_EOL . PHP_EOL;

        $lower = mb_strtolower($answer);
        // Soft quality checks (hints only)
        if (preg_match('/returns module|sidebar.*returns|may returns page/i', $answer) && !preg_match('/walang returns|no returns|hindi may returns|there is no returns/i', $answer)) {
            $failHints[] = "Q{$n}: may soft-claim Returns module";
        }
        if (stripos($q, 'depreciat') !== false || stripos($q, 'barcode') !== false) {
            if (!preg_match('/not in|hindi|walang|no .*module|does not/i', $answer)) {
                $failHints[] = "Q{$n}: should refuse depreciation/barcode inventing";
            }
        }
        if (stripos($q, 'RIS') !== false && preg_match('/\bris\b/i', $answer) && !preg_match('/walang ris|no ris|hindi.*ris|not in/i', $lower)) {
            $failHints[] = "Q{$n}: RIS refusal unclear";
        }
        if (stripos($q, 'order qty') !== false || stripos($q, 'Forecast') !== false) {
            if (!preg_match('/0|zero|sapat|on-hand|incoming|history/i', $answer)) {
                $failHints[] = "Q{$n}: Forecast why-0 incomplete";
            }
        }
        if ($len > 40 && preg_match('/\d{1,3}(?:,\d{3})*\.\d?$/u', trim($answer))) {
            // ends mid-number often means truncation
            $failHints[] = "Q{$n}: possible truncation (ends mid-number)";
        }
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . PHP_EOL . PHP_EOL;
        $failHints[] = "Q{$n}: EXCEPTION " . $e->getMessage();
    }
}

echo str_repeat('=', 72) . PHP_EOL;
if ($failHints) {
    echo "HINTS (" . count($failHints) . "):" . PHP_EOL;
    foreach ($failHints as $h) {
        echo " - {$h}" . PHP_EOL;
    }
} else {
    echo "HINTS: none" . PHP_EOL;
}
echo "DONE\n";
