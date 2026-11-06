<?php
/**
 * Inventory report sections used on purchase requests.
 * Matches item_group values already in Semi-Expendable and PPE.
 */

function prInventoryCanonicalGroups(): array
{
    return [
        'OFFICE FIXTURES & FURNITURES',
        'OFFICE EQUIPMENTS, TOOLS & APPLIANCES',
        'IT EQUIPMENTS',
        'COMMUNICATION EQUIPMENTS',
        'MEDICAL EQUIPMENTS',
        'SOUND SYSTEM',
        'SERVICE VEHICLES',
        'OTHERS',
    ];
}

function prInventoryNormalizeGroup(string $raw): string
{
    $g = strtoupper(trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $g = preg_replace('/\s+/', ' ', $g) ?? $g;
    $g = str_replace([' ,', ',&'], [',', ' &'], $g);
    $map = [
        'OFFICE EQUIPMENTS, TOOLS, & APPLIANCES' => 'OFFICE EQUIPMENTS, TOOLS & APPLIANCES',
        'MEDICAL DENTAL LABORATORY EQUIPMENT' => 'MEDICAL EQUIPMENTS',
        'SERVICE VEHICLE' => 'SERVICE VEHICLES',
        'OTHER SUPPLIES' => 'OTHERS',
    ];

    return $map[$g] ?? $g;
}

function prInventoryGroupOptions(?PDO $conn = null): array
{
    $out = prInventoryCanonicalGroups();
    if ($conn instanceof PDO) {
        try {
            $rows = $conn->query(
                "SELECT DISTINCT item_group FROM property_items WHERE item_group IS NOT NULL AND TRIM(item_group) <> ''"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rows as $raw) {
                $g = prInventoryNormalizeGroup((string) $raw);
                if ($g === '' || in_array($g, $out, true)) {
                    continue;
                }
                if (preg_match('/^(ROAD|LAND|BUILDING|FLOOD|WATER|ELECTRIC|MARKET|HOSPITAL|SCHOOL|SEF|MACHINERY|INFRASTRUCTURE|STRUCTURE)/', $g)) {
                    continue;
                }
                $out[] = $g;
            }
        } catch (PDOException $e) {
            // Keep canonical list.
        }
    }

    return $out;
}

function prArticleKey(string $article): string
{
    $a = trim($article);
    $a = preg_replace('/^\d+\s*(?:[\.\)]\s*|\s+)/', '', $a) ?? $a;

    return strtolower(trim($a));
}

/** Singular-ish form for plural matching (tables → table, boxes → box). */
function prArticleStem(string $key): string
{
    $k = strtolower(trim($key));
    if ($k === '') {
        return '';
    }
    $len = strlen($k);
    if ($len > 4 && str_ends_with($k, 'ies')) {
        return substr($k, 0, -3) . 'y';
    }
    if ($len > 3 && preg_match('/(?:s|x|z|ch|sh)es$/', $k)) {
        return (string) preg_replace('/es$/', '', $k);
    }
    if ($len > 3 && str_ends_with($k, 's') && !str_ends_with($k, 'ss')) {
        return substr($k, 0, -1);
    }

    return $k;
}

/**
 * Same article despite case, numbering, simple plurals, or a small typo.
 * "office table" vs "table" does not match.
 */
function prArticlesCloseMatch(string $typedKey, string $existingKey): bool
{
    if ($typedKey === '' || $existingKey === '') {
        return false;
    }
    if ($typedKey === $existingKey) {
        return true;
    }
    $sa = prArticleStem($typedKey);
    $sb = prArticleStem($existingKey);
    if ($sa !== '' && $sa === $sb) {
        return true;
    }
    if ($sa === '' || $sb === '') {
        return false;
    }
    if ($sa[0] !== $sb[0]) {
        return false;
    }
    $la = strlen($sa);
    $lb = strlen($sb);
    if ($la < 4 || $lb < 4) {
        return false;
    }
    $min = $la <= $lb ? $sa : $sb;
    $max = $la <= $lb ? $sb : $sa;
    if (str_starts_with($max, $min) && (strlen($max) - strlen($min)) === 1 && strlen($min) >= 5) {
        return false;
    }
    $d = levenshtein($sa, $sb);
    if ($d === 1 && abs($la - $lb) <= 1) {
        return true;
    }
    if ($d === 2 && $la === $lb && $la >= 5) {
        return true;
    }

    return false;
}

/** Higher rank is a tighter match (3 exact, 2 plural, 1 typo). */
function prArticleMatchRank(string $typedKey, string $existingKey): int
{
    if ($typedKey === '' || $existingKey === '') {
        return 0;
    }
    if ($typedKey === $existingKey) {
        return 3;
    }
    $sa = prArticleStem($typedKey);
    $sb = prArticleStem($existingKey);
    if ($sa !== '' && $sa === $sb) {
        return 2;
    }
    if (prArticlesCloseMatch($typedKey, $existingKey)) {
        return 1;
    }

    return 0;
}

function prEnsureLineInventoryColumns(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $cols = [
        'article' => 'ALTER TABLE proc_pr_line ADD COLUMN article VARCHAR(255) NULL',
        'item_group' => 'ALTER TABLE proc_pr_line ADD COLUMN item_group VARCHAR(255) NULL',
        'property_item_id' => 'ALTER TABLE proc_pr_line ADD COLUMN property_item_id INT NULL',
    ];
    foreach ($cols as $name => $sql) {
        try {
            $conn->query('SELECT ' . $name . ' FROM proc_pr_line LIMIT 1');
        } catch (PDOException $e) {
            try {
                $conn->exec($sql);
            } catch (PDOException $e2) {
                // Best-effort.
            }
        }
    }
}

/**
 * Reuse an existing article label in the same office/section, or start a new one.
 * Match is by article name (Table = 3. Table), not a new article heading.
 */
function prResolveArticleLabel(PDO $conn, int $officeId, string $category, ?string $itemGroup, string $typed): string
{
    $typed = trim($typed);
    if ($typed === '') {
        return '';
    }
    $key = prArticleKey($typed);
    if ($key === '') {
        return $typed;
    }
    $group = ($itemGroup !== null && trim((string) $itemGroup) !== '')
        ? prInventoryNormalizeGroup((string) $itemGroup)
        : '';
    $bare = trim((string) (preg_replace('/^\d+\s*(?:[\.\)]\s*|\s+)/', '', $typed) ?? $typed));
    if ($bare === '') {
        $bare = $typed;
    }

    try {
        $st = $conn->prepare(
            'SELECT article, category, item_group FROM property_items
             WHERE office_id = ? AND article IS NOT NULL AND TRIM(article) <> \'\''
        );
        $st->execute([$officeId]);
        $sameCatMatch = null;
        $sameCatRank = 0;
        $sectionMatch = null;
        $sectionRank = 0;
        $maxIdx = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ex = trim((string) ($row['article'] ?? ''));
            if ($ex === '') {
                continue;
            }
            $exGroup = trim((string) ($row['item_group'] ?? ''));
            $exGroupN = $exGroup !== '' ? prInventoryNormalizeGroup($exGroup) : '';
            $sameSection = $group === '' ? ($exGroupN === '') : ($exGroupN === $group);
            if ($sameSection && preg_match('/^(\d+)\s*(?:[\.\)]\s*|\s+)/', $ex, $m)) {
                $maxIdx = max($maxIdx, (int) $m[1]);
            }
            if (!$sameSection) {
                continue;
            }
            $rank = prArticleMatchRank($key, prArticleKey($ex));
            if ($rank < 1) {
                continue;
            }
            $cat = (string) ($row['category'] ?? '');
            if ($category !== '' && $cat === $category && $rank > $sameCatRank) {
                $sameCatMatch = $ex;
                $sameCatRank = $rank;
            } elseif ($rank > $sectionRank) {
                $sectionMatch = $ex;
                $sectionRank = $rank;
            }
        }
        if ($sameCatMatch !== null) {
            return $sameCatMatch;
        }
        if ($sectionMatch !== null) {
            return $sectionMatch;
        }

        return ($maxIdx + 1) . '. ' . prArticleTitleCase($bare);
    } catch (PDOException $e) {
        return $typed;
    }
}

function prArticleTitleCase(string $bare): string
{
    $bare = trim($bare);
    if ($bare === '') {
        return 'Item';
    }
    $parts = preg_split('/\s+/', $bare) ?: [];
    $out = [];
    foreach ($parts as $w) {
        if ($w === '') {
            continue;
        }
        $out[] = mb_strtoupper(mb_substr($w, 0, 1)) . mb_strtolower(mb_substr($w, 1));
    }

    return $out !== [] ? implode(' ', $out) : $bare;
}

/**
 * Place a new inventory row in the office + SE/PPE + section list.
 * Existing article → blank continuation under that heading (after its last row).
 * New article → next number after the last heading (6. Combi Blind → 7. Microphone).
 *
 * @return array{article: ?string, sort_order: int, reused: bool}
 */
function prPlaceItemInArticleList(PDO $conn, int $officeId, string $category, ?string $itemGroup, string $typed): array
{
    $typed = trim($typed);
    $group = ($itemGroup !== null && trim((string) $itemGroup) !== '')
        ? prInventoryNormalizeGroup((string) $itemGroup)
        : null;
    $fallback = [
        'article' => $typed !== '' ? $typed : null,
        'sort_order' => 0,
        'reused' => false,
    ];
    if ($officeId <= 0) {
        return $fallback;
    }

    try {
        $st = $conn->prepare(
            'SELECT id, article, sort_order FROM property_items
             WHERE office_id = ? AND category = ? AND (item_group <=> ?)
             ORDER BY sort_order ASC, id ASC'
        );
        $st->execute([$officeId, $category, $group]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return $fallback;
    }

    $headlines = [];
    $maxIdx = 0;
    $maxSort = -1;
    $curKey = '';
    foreach ($rows as $row) {
        $sort = (int) ($row['sort_order'] ?? 0);
        $maxSort = max($maxSort, $sort);
        $art = trim((string) ($row['article'] ?? ''));
        if ($art !== '') {
            if (preg_match('/^(\d+)\s*(?:[\.\)]\s*|\s+)/', $art, $m)) {
                $maxIdx = max($maxIdx, (int) $m[1]);
            }
            $k = prArticleKey($art);
            foreach ($headlines as $hk => $h) {
                $headlines[$hk]['open'] = false;
            }
            if ($k !== '' && !isset($headlines[$k])) {
                $headlines[$k] = ['label' => $art, 'last_sort' => $sort, 'open' => true];
                $curKey = $k;
            } else {
                $curKey = '';
            }
            continue;
        }
        if ($curKey !== '' && isset($headlines[$curKey]) && !empty($headlines[$curKey]['open'])) {
            $headlines[$curKey]['last_sort'] = $sort;
        }
    }

    $key = $typed !== '' ? prArticleKey($typed) : '';
    $bestKey = '';
    $bestRank = 0;
    if ($key !== '') {
        foreach ($headlines as $hk => $h) {
            $rank = prArticleMatchRank($key, $hk);
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $bestKey = $hk;
            }
        }
    }

    if ($bestKey !== '' && $bestRank > 0) {
        $last = (int) $headlines[$bestKey]['last_sort'];
        $shift = $conn->prepare(
            'UPDATE property_items SET sort_order = sort_order + 1
             WHERE office_id = ? AND category = ? AND (item_group <=> ?) AND sort_order > ?'
        );
        $shift->execute([$officeId, $category, $group, $last]);

        return [
            'article' => null,
            'sort_order' => $last + 1,
            'reused' => true,
        ];
    }

    $bare = trim((string) (preg_replace('/^\d+\s*(?:[\.\)]\s*|\s+)/', '', $typed) ?? $typed));
    $label = ($maxIdx + 1) . '. ' . prArticleTitleCase($bare !== '' ? $bare : 'Item');

    return [
        'article' => $label,
        'sort_order' => $maxSort + 1,
        'reused' => false,
    ];
}