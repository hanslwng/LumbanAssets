<?php
/**
 * Capstone consumables PR-spending forecast helpers.
 * Baseline monthly budgets are reference-only — OLS trains on actual PR totals.
 */

const LGUE_CAPSTONE_PR_DEMO_TAG = 'SIMULATED DEMO DATA — FOR CAPSTONE TESTING';

/**
 * Spec department monthly consumables budget baselines (₱), keyed by office code.
 *
 * @return array<string,int>
 */
function consumableDeptBudgetByCode(): array
{
    return [
        'ENRO' => 37500,     // SWM
        'SWM' => 37500,
        'PDAO' => 12500,
        'SENIOR' => 15000,
        'MCR' => 29000,
        'GSO' => 100000,
        'HRMO' => 25000,
        'LICENSE' => 21000,
        'MARKET' => 42000,
        'PESO' => 17000,
        'ENGR' => 42000,
        'PLAN' => 21000,     // MPDC
        'MPDC' => 21000,
        'BUDG' => 15000,
        'BUDGET' => 15000,
        'SWDO' => 50000,     // MSWDO
        'MSWDO' => 50000,
        'ACCT' => 21000,
        'ACCOUNTING' => 21000,
        'LWS' => 83000,
        'ASSO' => 21000,
        'ASSESSOR' => 21000,
        'DILG' => 8000,
        'OTP' => 25000,      // Organic Trading Post
        'TRADING' => 25000,
        'AGRI' => 42000,     // DA
        'DA' => 42000,
        'COA' => 12500,
        'TRES' => 33000,
        'TREASURY' => 33000,
        'ADMIN' => 33000,
        'MAYOR' => 42000,    // OM
        'OM' => 42000,
        'MDRRMO' => 83000,
        'HLTH' => 125000,    // RHU
        'RHU' => 125000,
        'VMAY' => 33000,     // SB / Vice Mayor
        'SB' => 33000,
        'DEPED' => 25000,
    ];
}

function consumableDeptMonthlyBudget(?string $code, ?string $name = null): float
{
    static $mlBudgets = null;
    if ($mlBudgets === null) {
        $path = dirname(__DIR__) . '/storage/imports/ml_dept_budgets.php';
        $mlBudgets = is_file($path) ? (require $path) : [];
        if (!is_array($mlBudgets)) {
            $mlBudgets = [];
        }
    }
    $codeKey = strtoupper(trim((string) $code));
    if ($codeKey !== '' && isset($mlBudgets[$codeKey])) {
        return (float) $mlBudgets[$codeKey];
    }

    $map = consumableDeptBudgetByCode();
    if ($codeKey !== '' && isset($map[$codeKey])) {
        return (float) $map[$codeKey];
    }
    $n = strtoupper(trim((string) $name));
    if ($n === '') {
        return 0.0;
    }
    $needles = [
        'SOLID WASTE' => 37500,
        'ENVIRONMENT' => 37500,
        'DISABILITY' => 12500,
        'SENIOR' => 15000,
        'CIVIL REGISTRAR' => 29000,
        'GENERAL SERVICES' => 100000,
        'HUMAN RESOURCE' => 25000,
        'LICENSE' => 21000,
        'MARKET' => 42000,
        'EMPLOYMENT' => 17000,
        'ENGINEER' => 42000,
        'PLANNING' => 21000,
        'BUDGET' => 15000,
        'SOCIAL WELFARE' => 50000,
        'ACCOUNTING' => 21000,
        'WATERWORKS' => 83000,
        'ASSESSOR' => 21000,
        'INTERIOR' => 8000,
        'TRADING POST' => 25000,
        'AGRICULTURE' => 42000,
        'AUDIT' => 12500,
        'TREASURY' => 33000,
        'ADMINISTRAT' => 33000,
        'MAYOR' => 42000,
        'DISASTER' => 83000,
        'HEALTH' => 125000,
        'VICE MAYOR' => 33000,
        'SANGGUNIAN' => 33000,
        'EDUCATION' => 25000,
    ];
    foreach ($needles as $needle => $peso) {
        if (str_contains($n, $needle)) {
            return (float) $peso;
        }
    }

    return 0.0;
}

/**
 * Allotted monthly budget from actual consumable PR spending.
 * Average of complete training months (quiet months count as ₱0).
 *
 * @param list<float|int> $pesoSeries
 */
function consumableDeptAllottedBudgetFromSpend(array $pesoSeries): float
{
    if (!$pesoSeries) {
        return 0.0;
    }
    $sum = 0.0;
    foreach ($pesoSeries as $v) {
        $sum += (float) $v;
    }

    return round($sum / count($pesoSeries), 2);
}

/**
 * Last complete training month vs forecast target.
 * If the current calendar month has no completed consumable PRs, that month is
 * the forecast (e.g. September after those PRs were removed) and the previous
 * month is the last history month.
 *
 * @return array{today:string,current_ym:string,forecast_ym:string,train_start:string,train_end:string,current_through:string,has_current_actuals:bool}
 */
function consumableForecastPrWindow(?DateTimeImmutable $today = null, ?PDO $conn = null): array
{
    $today = $today ?? new DateTimeImmutable('today');
    $monthStart = $today->modify('first day of this month');
    $calendarYm = $monthStart->format('Y-m');
    $prevYm = $monthStart->modify('-1 month')->format('Y-m');
    $nextYm = $monthStart->modify('+1 month')->format('Y-m');

    $hasCurrentActuals = true;
    if ($conn instanceof PDO) {
        $st = $conn->prepare(
            "SELECT COUNT(*) FROM proc_pr
             WHERE is_consumable = 1
               AND status IN ('completed', 'po_issued')
               AND pr_date >= ?
               AND pr_date < ?"
        );
        $st->execute([
            $monthStart->format('Y-m-01'),
            $monthStart->modify('+1 month')->format('Y-m-01'),
        ]);
        $hasCurrentActuals = ((int) $st->fetchColumn()) > 0;
    }

    if ($hasCurrentActuals) {
        $forecastYm = $nextYm;
        $trainEnd = $prevYm;
        $currentYm = $calendarYm;
    } else {
        $forecastYm = $calendarYm;
        $trainEnd = $prevYm;
        $currentYm = '';
    }

    $trainStart = '2026-01';
    if ($trainEnd < $trainStart) {
        $trainEnd = $trainStart;
    }

    return [
        'today' => $today->format('Y-m-d'),
        'current_ym' => $currentYm,
        'forecast_ym' => $forecastYm,
        'train_start' => $trainStart,
        'train_end' => $trainEnd,
        'current_through' => $today->format('Y-m-d'),
        'has_current_actuals' => $hasCurrentActuals,
    ];
}

function consumableForecastOlsPredictAt(array $ols, int $x, array $y = []): float
{
    $pred = (float) ($ols['intercept'] ?? 0) + ((float) ($ols['slope'] ?? 0) * (float) $x);
    if ($pred < 0) {
        $pred = 0.0;
    }
    if ($y) {
        $peak = 0.0;
        $sum = 0.0;
        foreach ($y as $v) {
            $peak = max($peak, (float) $v);
            $sum += (float) $v;
        }
        $meanY = $sum / max(1, count($y));
        $cap = max($peak * 2.5, $meanY * 2.5, 1.0);
        if ($pred > $cap) {
            $pred = $cap;
        }
    }

    return $pred;
}

function consumableForecastMonthLabel(string $ym): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ym . '-01');
    if (!$dt) {
        return $ym;
    }

    return $dt->format('F Y');
}
