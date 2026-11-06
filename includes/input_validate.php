<?php
/**
 * Official-record input checks for LGU submissions (admin + department).
 */

function lgueLooksIllegible(string $raw): bool
{
    $compact = strtolower(preg_replace('/\s+/', '', trim($raw)) ?? '');
    if (strlen($compact) < 6) {
        return false;
    }
    if (preg_match('/(.)\1{4,}/', $compact)) {
        return true;
    }
    if (preg_match('/^(asd|asdf|qwe|qwer|zxc|xxx|aaa|test|qwerty|asdfgh)+$/i', $compact)) {
        return true;
    }
    $letters = preg_replace('/[^a-zñáéíóúü]/u', '', $compact) ?? '';
    $len = function_exists('mb_strlen') ? mb_strlen($letters, 'UTF-8') : strlen($letters);
    if ($len >= 8) {
        $chars = preg_split('//u', $letters, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $uniq = count(array_unique($chars));
        if ($uniq <= 3) {
            return true;
        }
        preg_match_all('/[aeiouáéíóúüñ]/u', $letters, $m);
        $vowels = count($m[0] ?? []);
        if ($vowels / $len < 0.12) {
            return true;
        }
    }

    return false;
}

function lgueIsDonationUnitValue($raw): bool
{
    return strcasecmp(trim((string) $raw), 'DONATION') === 0;
}

/** Numeric peso amount for totals / SE vs PPE. DONATION counts as 0. */
function lgueUnitValueNumeric($raw): float
{
    if (lgueIsDonationUnitValue($raw)) {
        return 0.0;
    }
    $s = lgueNormalizeNumberString($raw);
    if ($s === '' || !is_numeric($s)) {
        return 0.0;
    }

    return (float) $s;
}

/**
 * Value stored in property_items.unit_value after the column is VARCHAR.
 * @return string|float
 */
function lgueUnitValueForStorage($raw)
{
    if (lgueIsDonationUnitValue($raw)) {
        return 'DONATION';
    }

    return lgueUnitValueNumeric($raw);
}

function lgueIsStrictNumber($raw, bool $allowDecimal = true): bool
{
    $s = lgueNormalizeNumberString($raw);
    if ($s === '') {
        return false;
    }
    $pattern = $allowDecimal ? '/^\d+(\.\d+)?$/' : '/^\d+$/';

    return (bool) preg_match($pattern, $s);
}

function lgueNormalizeNumberString($raw): string
{
    $s = trim((string) $raw);
    $s = str_replace(["\xc2\xa0", ' ', ',', '₱', 'PHP'], '', $s);

    return $s;
}

function lgueIsReadableText(string $raw, int $minLen = 3): bool
{
    $s = trim($raw);
    if (function_exists('mb_strlen')) {
        if (mb_strlen($s, 'UTF-8') < $minLen) {
            return false;
        }
    } elseif (strlen($s) < $minLen) {
        return false;
    }
    if (!preg_match('/[A-Za-zÑñÁÉÍÓÚáéíóúüÜ]/u', $s)) {
        return false;
    }
    if (lgueLooksIllegible($s)) {
        return false;
    }

    return true;
}

function lgueIsPersonOrOfficeName(string $raw): bool
{
    $s = trim($raw);
    if (!lgueIsReadableText($s, 3)) {
        return false;
    }

    return (bool) preg_match('/^[A-Za-zÑñÁÉÍÓÚáéíóúüÜ][A-Za-zÑñÁÉÍÓÚáéíóúüÜ .\'\-]{1,99}$/u', $s);
}

function lgueIsMeasureUnit(string $raw): bool
{
    $s = trim($raw);
    if ($s === '') {
        return false;
    }
    if (!preg_match('/^[A-Za-z][A-Za-z.\-]{0,15}$/', $s)) {
        return false;
    }
    if (strlen($s) >= 6 && lgueLooksIllegible($s)) {
        return false;
    }

    return true;
}

function lgueWordCount(string $raw): int
{
    $parts = preg_split('/\s+/u', trim($raw)) ?: [];
    $n = 0;
    foreach ($parts as $w) {
        if (preg_match('/[A-Za-zÑñ]/u', $w)) {
            $n++;
        }
    }

    return $n;
}

/** @return string|null error message */
function lguePrPayloadError(string $purpose, array $lines): ?string
{
    if (!lgueIsReadableText($purpose, 12) || lgueWordCount($purpose) < 2) {
        return 'Purpose must be a clear official phrase (at least two words), not random letters.';
    }
    if ($lines === []) {
        return 'Add at least one line item with a readable description.';
    }
    $n = 0;
    foreach ($lines as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $line = $i + 1;
        $desc = trim((string) ($row['description'] ?? ''));
        $qtyRaw = $row['quantity'] ?? '';
        $unit = trim((string) ($row['unit'] ?? 'pc'));
        $estRaw = $row['est_unit_cost'] ?? '';
        if ($desc === '') {
            continue;
        }
        if (!lgueIsReadableText($desc, 4)) {
            return 'Line ' . $line . ' description must be readable official text (not random letters).';
        }
        if (!lgueIsStrictNumber($qtyRaw, true) || (float) $qtyRaw <= 0) {
            return 'Line ' . $line . ' quantity must be a number only (example: 2 or 2.5).';
        }
        if ($estRaw !== '' && $estRaw !== null && !lgueIsStrictNumber($estRaw, true)) {
            return 'Line ' . $line . ' estimated unit cost must be a number only (example: 150 or 150.00).';
        }
        if ((float) $estRaw < 0) {
            return 'Line ' . $line . ' estimated unit cost cannot be negative.';
        }
        if (!lgueIsMeasureUnit($unit === '' ? 'pc' : $unit)) {
            return 'Line ' . $line . ' unit must be text only (example: pc, box, ream, set).';
        }
        $n++;
    }
    if ($n === 0) {
        return 'Add at least one line item with a readable description.';
    }

    return null;
}

function lgueNumericFieldError($raw, string $label, bool $allowDecimal = true, ?float $min = null, bool $allowEmpty = false): ?string
{
    if ($raw === null || $raw === '') {
        return $allowEmpty ? null : $label . ' must be a number.';
    }
    if (is_int($raw) || is_float($raw)) {
        if (!is_numeric($raw)) {
            return $label . ' must be a number.';
        }
        if ($min !== null && (float) $raw < $min) {
            return $label . ' must be at least ' . $min . '.';
        }

        return null;
    }
    $s = lgueNormalizeNumberString($raw);
    if ($s === '') {
        return $allowEmpty ? null : $label . ' must be a number.';
    }
    if (!lgueIsStrictNumber($s, $allowDecimal)) {
        return $label . ' must be a number only (example: 12 or 12.50). Letters are not allowed.';
    }
    if ($min !== null && (float) $s < $min) {
        return $label . ' must be at least ' . $min . '.';
    }

    return null;
}

function lgueRequiredTextError(string $raw, string $label, int $minLen = 3, int $minWords = 0): ?string
{
    $s = trim($raw);
    $len = function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    if ($len < $minLen) {
        return $label . ' needs a clear official entry (at least ' . $minLen . ' characters).';
    }
    if (!lgueIsReadableText($s, $minLen)) {
        return $label . ' must be readable official text. Random letters cannot be submitted.';
    }
    if ($minWords > 0 && lgueWordCount($s) < $minWords) {
        return $label . ' must be a complete phrase (at least ' . $minWords . ' words).';
    }

    return null;
}

function lgueOptionalTextError(string $raw, string $label, int $minLen = 3): ?string
{
    $s = trim($raw);
    if ($s === '') {
        return null;
    }

    return lgueRequiredTextError($s, $label, $minLen, 0);
}

function lgueNameError(string $raw, string $label, bool $required = true): ?string
{
    $s = trim($raw);
    if ($s === '') {
        return $required ? $label . ' is required.' : null;
    }
    if (!lgueIsPersonOrOfficeName($s)) {
        return $label . ' must be a proper name (letters only), not random text.';
    }

    return null;
}

function lguePropertyNumberError(string $raw): ?string
{
    $s = trim($raw);
    if ($s === '') {
        return 'Property number is required.';
    }
    if (strcasecmp($s, 'NONE') === 0) {
        return null;
    }
    if (strlen($s) < 3) {
        return 'Property number is too short.';
    }

    return null;
}

function lgueNormalizePropertyNumber(string $raw): string
{
    $s = trim($raw);

    return strcasecmp($s, 'NONE') === 0 ? 'NONE' : $s;
}

function lgueFormRefError(string $raw, string $label = 'Form number'): ?string
{
    $s = trim($raw);
    if (strlen($s) < 2) {
        return $label . ' is required.';
    }
    if (lgueLooksIllegible($s)) {
        return $label . ' must be a real reference (not random letters).';
    }

    return null;
}

function lgueOfficeCodeError(string $raw): ?string
{
    $s = strtoupper(trim($raw));
    if (!preg_match('/^[A-Z][A-Z0-9\-]{1,15}$/', $s)) {
        return 'Office code must be letters and numbers only (example: GSO, MAYOR).';
    }
    if (lgueLooksIllegible($s)) {
        return 'Office code must be a real department code, not random letters.';
    }

    return null;
}

/** @return string|null */
function lgueItemFieldsError($article, string $description, string $unit, $unitValue, $quantity, $qtyPhys, string $remarks): ?string
{
    $text = trim($description);
    if ($text === '' || $text === '—' || $text === '-') {
        $text = trim((string) $article);
    }
    $err = lgueRequiredTextError($text, 'Article or description', 3);
    if ($err !== null) {
        return $err;
    }
    if (!lgueIsMeasureUnit($unit === '' ? 'pc' : $unit)) {
        return 'Unit must be text only (example: pc, box, ream, set).';
    }
    if (!lgueIsDonationUnitValue($unitValue)) {
        $err = lgueNumericFieldError($unitValue, 'Unit value', true, 0.0, true);
    }
    if ($err !== null) {
        return $err;
    }
    $err = lgueNumericFieldError($quantity, 'Quantity', false, 1.0, false);
    if ($err !== null) {
        return $err;
    }
    if ($qtyPhys !== null && $qtyPhys !== '') {
        $err = lgueNumericFieldError($qtyPhys, 'Physical count', false, 0.0, false);
        if ($err !== null) {
            return $err;
        }
    }

    return lgueOptionalTextError($remarks, 'Remarks');
}
