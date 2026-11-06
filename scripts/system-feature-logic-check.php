<?php
/**
 * Feature logic probes: permissions, validation, dashboard payload shape, chatbot ask.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/gso_assistant.php';
require_once dirname(__DIR__) . '/includes/input_validate.php';
require_once dirname(__DIR__) . '/includes/audit_log.php';

$pass = 0;
$fail = 0;
function chk(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "[PASS] $n" . ($d !== '' ? " — $d" : '') . "\n"; }
    else { $fail++; echo "[FAIL] $n" . ($d !== '' ? " — $d" : '') . "\n"; }
}

$conn = getDBConnection();
$admin = $conn->query("SELECT id, username, full_name, role, office_id FROM users WHERE role='Admin' AND is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_SESSION['user_id'] = (int) $admin['id'];
$_SESSION['user_role'] = (string) $admin['role'];
$_SESSION['user_name'] = (string) ($admin['full_name'] ?: $admin['username']);
$_SESSION['user_office_id'] = (int) ($admin['office_id'] ?? 0);
$_SESSION['user_office_name'] = '';

chk('PermissionHelper::isAdmin', PermissionHelper::isAdmin() === true);
chk('PermissionHelper::can print', PermissionHelper::can('print'));

$clean = sanitizeInput("  hello <b>x</b>  ");
chk('sanitizeInput', is_string($clean) && !str_contains($clean, '<b>'), $clean);

$err = lgueNumericFieldError('12.5', 'Qty', true, 0.0, false);
chk('numeric validate ok', $err === null || $err === '');
$errBad = lgueNumericFieldError('abc', 'Qty', true, 0.0, false);
chk('numeric validate reject', $errBad !== null && $errBad !== '');

// Dashboard API internals — include and call if function exists, else SQL shape
$dashFile = dirname(__DIR__) . '/api/dashboard.php';
chk('dashboard api file', is_file($dashFile));

$o = gsoAssistantAsk($conn, 'Ano ang modules ng system?', []);
$ans = (string) ($o['answer'] ?? '');
chk('chatbot/assistant answer', $ans !== '' && mb_strlen($ans) > 40, 'len=' . mb_strlen($ans));

$o2 = gsoAssistantAsk($conn, 'Paano mag-print ng physical count?', []);
$a2 = (string) ($o2['answer'] ?? '');
chk('assistant physical count FAQ', preg_match('/physical count|RPCSE|RPCPPE/i', $a2) === 1, 'matched keywords');

// Items query by category (core list)
$st = $conn->prepare("SELECT id, property_number, article, category, office_id FROM property_items WHERE category=? LIMIT 5");
$st->execute(['SE']);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
chk('items SE sample', count($rows) > 0, 'n=' . count($rows));
$st->execute(['PPE']);
$rowsP = $st->fetchAll(PDO::FETCH_ASSOC);
chk('items PPE sample', count($rowsP) > 0, 'n=' . count($rowsP));

// Procurement latest PR join lines
try {
    $pr = $conn->query("SELECT p.id, p.pr_number, p.status,
        (SELECT COUNT(*) FROM proc_pr_line l WHERE l.pr_id = p.id) AS line_count
        FROM proc_pr p ORDER BY p.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    chk('PR + lines join', is_array($pr) && isset($pr['pr_number']), json_encode($pr));
} catch (Throwable $e) {
    chk('PR + lines join', false, $e->getMessage());
}

// Issuance join
try {
    $iss = $conn->query("SELECT r.id, r.form_number,
        (SELECT COUNT(*) FROM issuance_items i WHERE i.issuance_id = r.id) AS item_count
        FROM issuance_records r ORDER BY r.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    chk('issuance + items join', is_array($iss), json_encode($iss));
} catch (Throwable $e) {
    chk('issuance + items join', false, $e->getMessage());
}

// Zip extension (needed for Excel template fill in this PHP)
chk('ext-zip (Excel templates)', class_exists('ZipArchive'), class_exists('ZipArchive') ? 'loaded' : 'MISSING — enable extension=zip in php.ini');

echo "\nPASS=$pass FAIL=$fail\n";
exit($fail === 0 ? 0 : 1);
