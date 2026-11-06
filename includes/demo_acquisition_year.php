<?php
/**
 * Demo-only assignment of acquisition years for property numbers that start with 0000
 * (unknown / forgotten year). Spreads items across 2023–2026 with a rising curve so
 * inventory/demand charts have a usable time axis for system demonstration.
 *
 * Original 0000 numbers are not treated as real years. This is for demonstration data.
 */

/**
 * Years used for demo forecasting (inclusive). 2026 dates stay on or before today.
 */
function demoAcquisitionYears(): array
{
    return [2023, 2024, 2025, 2026];
}

function demoPropertyNumberHasUnknownYear(?string $propertyNumber): bool
{
    $pn = trim((string) $propertyNumber);

    return $pn !== '' && preg_match('/^0000(?=\D|$)/', $pn) === 1;
}

/**
 * Stable hash so the same property number always gets the same demo year.
 */
function demoAcquisitionHash(string $seed): int
{
    return (int) sprintf('%u', crc32($seed));
}

/**
 * Pick 2023–2026 with a rising weight so later years have more acquisitions
 * (looks like growing municipal demand for a demo).
 *
 * Weights: 2023=18%, 2024=24%, 2025=32%, 2026=26%.
 */
function demoPickAcquisitionYear(int $hash): int
{
    $bucket = $hash % 100;
    if ($bucket < 18) {
        return 2023;
    }
    if ($bucket < 42) {
        return 2024;
    }
    if ($bucket < 74) {
        return 2025;
    }

    return 2026;
}

function demoAcquisitionDateForYear(int $year, int $hash): string
{
    $today = new DateTimeImmutable('today');
    if ($year === (int) $today->format('Y')) {
        $maxMonth = (int) $today->format('n');
        $month = ($hash % $maxMonth) + 1;
        $maxDay = min(28, (int) $today->format('j'));
        if ($month === $maxMonth) {
            $day = ($hash % $maxDay) + 1;
        } else {
            $day = ($hash % 28) + 1;
        }
    } else {
        $month = ($hash % 12) + 1;
        $day = ($hash % 28) + 1;
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/**
 * @return array{property_number: string, year: ?int, acquisition_date: ?string, changed: bool}
 */
function demoAssignUnknownAcquisitionYear(string $propertyNumber, string $extraSeed = ''): array
{
    $pn = trim($propertyNumber);
    if (!demoPropertyNumberHasUnknownYear($pn)) {
        return [
            'property_number' => $pn,
            'year' => null,
            'acquisition_date' => null,
            'changed' => false,
        ];
    }

    $hash = demoAcquisitionHash($pn . '|' . $extraSeed);
    $year = demoPickAcquisitionYear($hash);
    $newPn = preg_replace('/^0000/', (string) $year, $pn, 1);
    $date = demoAcquisitionDateForYear($year, $hash);

    return [
        'property_number' => $newPn,
        'year' => $year,
        'acquisition_date' => $date,
        'changed' => true,
    ];
}

/**
 * If the assigned property number already exists, try the other demo years in order.
 *
 * @param callable(string): bool $existsFn
 * @return array{property_number: string, year: ?int, acquisition_date: ?string, changed: bool}
 */
function demoAssignUnknownAcquisitionYearUnique(string $propertyNumber, callable $existsFn, string $extraSeed = ''): array
{
    $assigned = demoAssignUnknownAcquisitionYear($propertyNumber, $extraSeed);
    if (!$assigned['changed']) {
        return $assigned;
    }

    $years = demoAcquisitionYears();
    $ordered = array_values(array_unique(array_merge(
        [(int) $assigned['year']],
        $years
    )));

    $hash = demoAcquisitionHash($propertyNumber . '|' . $extraSeed);
    foreach ($ordered as $year) {
        $candidate = preg_replace('/^0000/', (string) $year, trim($propertyNumber), 1);
        if ($candidate === trim($propertyNumber)) {
            continue;
        }
        if (!$existsFn($candidate)) {
            return [
                'property_number' => $candidate,
                'year' => $year,
                'acquisition_date' => demoAcquisitionDateForYear($year, $hash),
                'changed' => true,
            ];
        }
    }

    return $assigned;
}
