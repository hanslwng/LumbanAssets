<?php
/**
 * System-wide health check — syntax, DB, core modules, key logic.
 * Non-destructive. Usage:
 *   C:\xampp\php\php.exe scripts\system-health-check.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$phpBin = PHP_BINARY ?: 'php';
$pass = 0;
$fail = 0;
$warn = 0;

function note(string $name, bool $ok, string $detail = '', bool $warning = false): void
{
    global $pass, $fail, $warn;
    if ($warning) {
        $warn++;
        $status = 'WARN';
    } elseif ($ok) {
        $pass++;
        $status = 'PASS';
    } else {
        $fail++;
        $status = 'FAIL';
    }
    echo sprintf("[%s] %s%s\n", $status, $name, $detail !== '' ? " — $detail" : '');
}

/* Bootstrap first (before any output) so session works cleanly */
require_once $root . '/bootstrap.php';
$conn = getDBConnection();
require_once $root . '/vendor/autoload.php';

echo "=== LGU Inventory system health check ===\n";
echo 'Root: ' . $root . "\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'DB: ' . DB_NAME . "\n\n";

note('bootstrap + DB', true, DB_NAME);

/* ── Files ── */
$mustExist = [
    'bootstrap.php', 'theme/config.php', 'theme/index.php',
    'assets/js/app.js', 'assets/css/style.css',
    'includes/excel_export_lock.php', 'includes/physical_count_report_data.php',
    'includes/rpcse_template_workbook.php', 'includes/rpcppe_template_workbook.php',
    'includes/rpcppe_workbook.php', 'includes/consumable_forecast.php',
    'includes/consumable_forecast_ml.php', 'includes/gso_assistant.php',
    'print/print-physical-count-report.php',
    'api/items.php', 'api/dashboard.php', 'api/procurement.php', 'api/forecast.php',
    'api/issuance.php', 'api/physical-count-save.php', 'vendor/autoload.php',
];
foreach ($mustExist as $rel) {
    note("file:$rel", is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel)));
}

/* ── PHP syntax ── */
$lintDirs = ['api', 'includes', 'print', 'app', 'theme'];
$lintFail = 0;
$lintOk = 0;
foreach ($lintDirs as $dir) {
    $abs = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($abs)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') {
            continue;
        }
        $out = [];
        $code = 0;
        exec(escapeshellarg($phpBin) . ' -l ' . escapeshellarg($f->getPathname()) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $lintFail++;
            note('syntax:' . str_replace($root . DIRECTORY_SEPARATOR, '', $f->getPathname()), false, implode(' ', $out));
        } else {
            $lintOk++;
        }
    }
}
note("php -l ($lintOk files)", $lintFail === 0, $lintFail === 0 ? 'all clean' : "$lintFail errors");

/* ── Tables ── */
$tables = [
    'users', 'offices', 'property_items', 'issuance_records', 'issuance_items',
    'proc_pr', 'proc_pr_line', 'user_activity', 'system_settings',
    'proc_quotation', 'proc_po', 'proc_air', 'return_records', 'unserviceable_records',
    'notifications', 'consumable_items', 'consumable_stock',
];
foreach ($tables as $t) {
    try {
        $n = (int) $conn->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        note("table:$t", true, "rows=$n");
    } catch (Throwable $e) {
        note("table:$t", false, $e->getMessage());
    }
}

/* ── Includes ── */
$includes = [
    'includes/excel_export_lock.php' => 'lgue_lock_export_spreadsheet',
    'includes/physical_count_report_data.php' => 'load_physical_count_report_items',
    'includes/rpcppe_workbook.php' => 'rpcppe_stream_download',
    'includes/consumable_forecast.php' => 'consumableForecastBuild',
    'includes/consumable_forecast_ml.php' => null,
    'includes/gso_assistant.php' => 'gsoAssistantAsk',
    'includes/input_validate.php' => 'sanitizeInput',
    'includes/audit_log.php' => 'activityLog',
    'app/helpers/PermissionHelper.php' => 'PermissionHelper',
];
foreach ($includes as $rel => $symbol) {
    try {
        require_once $root . '/' . $rel;
        if ($symbol === null) {
            note("include:$rel", true);
        } elseif ($symbol === 'PermissionHelper') {
            note("include:$rel", class_exists('PermissionHelper'));
        } else {
            note("include:$rel", function_exists($symbol), (string) $symbol);
        }
    } catch (Throwable $e) {
        note("include:$rel", false, $e->getMessage());
    }
}

note('composer Spreadsheet', class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class));

/* ── Excel lock ── */
try {
    $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $ss->getActiveSheet()->setCellValue('A1', 'lock-test');
    lgue_lock_export_spreadsheet($ss);
    $p = $ss->getActiveSheet()->getProtection();
    $sec = $ss->getSecurity();
    $ok = (bool) $p->getSheet()
        && $p->getPassword() !== ''
        && $sec->getLockStructure()
        && $sec->getLockWindows()
        && $p->getFormatCells() === false
        && $p->getInsertRows() === false;
    note('excel export lock', $ok, $ok ? 'view-only flags OK' : 'flags incomplete');
} catch (Throwable $e) {
    note('excel export lock', false, $e->getMessage());
}

/* ── Physical count ── */
try {
    $se = load_physical_count_report_items($conn, 'SE', '', 0, '', []);
    $ppe = load_physical_count_report_items($conn, 'PPE', '', 0, '', []);
    note('physical count SE load', is_array($se), 'items=' . count($se));
    note('physical count PPE load', is_array($ppe), 'items=' . count($ppe));
    $segsSe = physical_count_report_segments_rpcse(array_slice($se, 0, 80));
    $segsPpe = physical_count_report_segments_rpcppe(array_slice($ppe, 0, 80));
    note('RPCSE segments', is_array($segsSe), 'segments=' . count($segsSe));
    note('RPCPPE segments', is_array($segsPpe), 'segments=' . count($segsPpe));
} catch (Throwable $e) {
    note('physical count', false, $e->getMessage());
}

/* ── Inventory / offices ── */
try {
    $seN = (int) $conn->query("SELECT COUNT(*) FROM property_items WHERE category='SE'")->fetchColumn();
    $ppeN = (int) $conn->query("SELECT COUNT(*) FROM property_items WHERE category='PPE'")->fetchColumn();
    $offN = (int) $conn->query('SELECT COUNT(*) FROM offices')->fetchColumn();
    note('inventory mix', $seN + $ppeN > 0, "SE=$seN PPE=$ppeN offices=$offN");
    $codes = $conn->query('SELECT code, name FROM offices')->fetchAll(PDO::FETCH_ASSOC);
    $hasSwdo = false;
    foreach ($codes as $o) {
        $c = strtoupper((string) ($o['code'] ?? ''));
        $n = strtolower((string) ($o['name'] ?? ''));
        if ($c === 'SWDO' || $c === 'MSWDO' || str_contains($n, 'welfare')) {
            $hasSwdo = true;
            break;
        }
    }
    note('social welfare office', $hasSwdo, $hasSwdo ? 'found' : 'missing', !$hasSwdo);
} catch (Throwable $e) {
    note('inventory', false, $e->getMessage());
}

/* ── Forecast ── */
try {
    if (function_exists('consumableEnsureSchema')) {
        consumableEnsureSchema($conn);
    }
    $data = consumableForecastBuild($conn, null);
    note(
        'forecast build',
        isset($data['items'], $data['summary'], $data['meta']),
        'items=' . count($data['items'] ?? []) . ' depts=' . count($data['departments'] ?? [])
    );
    $ols = consumableForecastOls([10.0, 12.0, 14.0]);
    note('forecast OLS', is_array($ols) && isset($ols['next']), 'next=' . ($ols['next'] ?? '?'));
    note('forecast OLS guard', consumableForecastOls([1.0, 2.0]) === null);
    if (function_exists('consumableForecastFitBest')) {
        $fit = consumableForecastFitBest([5, 6, 7, 8, 9, 10], []);
        note('forecast ML fit', is_array($fit), is_array($fit) ? ('model=' . ($fit['model'] ?? '?')) : '');
    }
} catch (Throwable $e) {
    note('forecast', false, $e->getMessage());
}

/* ── Procurement / issuance / users ── */
try {
    $openPr = (int) $conn->query("SELECT COUNT(*) FROM proc_pr WHERE status NOT IN ('completed','cancelled','rejected')")->fetchColumn();
    $prLines = (int) $conn->query('SELECT COUNT(*) FROM proc_pr_line')->fetchColumn();
    note('procurement', true, "open_pr≈$openPr lines=$prLines");
} catch (Throwable $e) {
    note('procurement', false, $e->getMessage());
}
try {
    note('issuance', true, 'rows=' . (int) $conn->query('SELECT COUNT(*) FROM issuance_records')->fetchColumn());
    note('returns', true, 'rows=' . (int) $conn->query('SELECT COUNT(*) FROM return_records')->fetchColumn());
    note('unserviceable', true, 'rows=' . (int) $conn->query('SELECT COUNT(*) FROM unserviceable_records')->fetchColumn());
    $admins = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role='Admin' AND is_active=1")->fetchColumn();
    note('admin user', $admins > 0, "admins=$admins");
} catch (Throwable $e) {
    note('modules counts', false, $e->getMessage());
}

/* ── Activity log write smoke (no commit pollution: dry schema only) ── */
try {
    activityLogEnsureSchema($conn);
    note('user_activity schema', true);
} catch (Throwable $e) {
    note('user_activity schema', false, $e->getMessage());
}

/* ── Templates ── */
require_once $root . '/includes/rpcse_cell_map.php';
require_once $root . '/includes/rpcppe_cell_map.php';
note('RPCSE template', is_file(rpcse_template_path()), rpcse_template_path());
note('RPCPPE template', is_file(rpcppe_template_path()), rpcppe_template_path());

/* ── API presence ── */
foreach (['items.php', 'dashboard.php', 'procurement.php', 'forecast.php', 'issuance.php', 'settings.php', 'offices.php', 'chatbot.php', 'returns.php', 'unserviceable.php', 'notifications.php', 'property-tags.php'] as $api) {
    $path = $root . '/api/' . $api;
    note("api:$api", is_file($path) && filesize($path) > 100);
}

/* ── Dashboard query smoke ── */
try {
    $dashSe = (int) $conn->query("SELECT COUNT(*) FROM property_items WHERE category='SE' AND status='active'")->fetchColumn();
    $dashPpe = (int) $conn->query("SELECT COUNT(*) FROM property_items WHERE category='PPE' AND status='active'")->fetchColumn();
    note('dashboard active counts', true, "SE_active=$dashSe PPE_active=$dashPpe");
} catch (Throwable $e) {
    note('dashboard active counts', false, $e->getMessage());
}

echo "\n=== SUMMARY ===\n";
echo "PASS: $pass\nFAIL: $fail\nWARN: $warn\n";
echo ($fail === 0 ? "OVERALL: HEALTHY\n" : "OVERALL: ISSUES FOUND\n");
exit($fail === 0 ? 0 : 1);
