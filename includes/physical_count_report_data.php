<?php
/**
 * Load property_items rows for the physical count report (same filters as items list).
 * Rows are returned in property-number order (e.g. 2026-SE-000001, 000002, 000003) for SE and PPE.
 */

/**
 * Sort items by property number (natural, case-insensitive), then id. Used for COA physical count
 * printout so sequence follows card numbers (e.g. 2026-SE-000001, 000002, …).
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function physical_count_sort_items(array $items) {
    usort($items, static function ($a, $b) {
        $pa = trim((string) ($a['property_number'] ?? ''));
        $pb = trim((string) ($b['property_number'] ?? ''));
        $cmp = strnatcasecmp($pa, $pb);
        if ($cmp !== 0) {
            return $cmp;
        }

        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    });

    return $items;
}

function pc_book_qty(array $row) {
    $q = (int) ($row['quantity'] ?? 0);

    return $q < 0 ? 0 : $q;
}

function pc_physical_qty(array $row) {
    $raw = $row['quantity_physical_count'] ?? null;
    if ($raw === null || $raw === '') {
        return pc_book_qty($row);
    }

    return (int) $raw;
}

function pc_variance_qty(array $row) {
    return pc_physical_qty($row) - pc_book_qty($row);
}

function pc_variance_value(array $row) {
    return pc_variance_qty($row) * (float) ($row['unit_value'] ?? 0);
}

/**
 * @return array<int, array<string, mixed>>
 */
/**
 * @param array<string, mixed> $src
 * @return array<int, int>
 */
function physical_count_parse_office_ids(array $src): array
{
    $ids = [];
    if (!empty($src['office_ids'])) {
        foreach (explode(',', (string) $src['office_ids']) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
    } elseif (!empty($src['office_id'])) {
        $id = (int) $src['office_id'];
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Flatten RPCSE segments into the in-browser workbook bootstrap rows.
 *
 * @param array<int, array<string, mixed>> $segments
 * @return array<int, array<string, mixed>>
 */
function physical_count_rpcse_boot_rows(array $segments, int $defaultOfficeId = 0): array
{
    $bootRows = [];
    foreach ($segments as $seg) {
        $title = trim((string) ($seg['title'] ?? ''));
        $lastArticle = '';
        if (!empty($seg['explicit']) && $title !== '') {
            $bootRows[] = [
                'type' => 'section',
                'title' => function_exists('mb_strtoupper') ? mb_strtoupper($title, 'UTF-8') : strtoupper($title),
                'item_group' => $title,
            ];
            $lastArticle = '';
        }
        foreach ($seg['items'] as $item) {
            $qc = pc_book_qty($item);
            $rawPhys = $item['quantity_physical_count'] ?? null;
            $qp = ($rawPhys === null || $rawPhys === '') ? $qc : (int) $rawPhys;
            $uvRaw = $item['unit_value'] ?? 0;
            $isDon = (function_exists('lgueIsDonationUnitValue') && lgueIsDonationUnitValue($uvRaw))
                || (strcasecmp(trim((string) ($item['acquisition_mode'] ?? '')), 'Donation') === 0 && (float) $uvRaw == 0.0);
            $uv = $isDon ? 0.0 : (float) $uvRaw;
            $vq = $qp - $qc;
            $article = trim((string) ($item['article'] ?? ''));
            $articleCell = '';
            if ($article !== '' && $article !== $lastArticle) {
                $articleCell = $article;
                $lastArticle = $article;
            }
            $bootRows[] = [
                'type' => 'item',
                'id' => (int) ($item['id'] ?? 0),
                'office_id' => (int) ($item['office_id'] ?? $defaultOfficeId),
                'article' => $articleCell,
                'article_full' => $article,
                'description' => (string) ($item['description'] ?? ''),
                'property_number' => (string) ($item['property_number'] ?? ''),
                'unit_of_measure' => (string) (($item['unit_of_measure'] ?? '') !== '' ? $item['unit_of_measure'] : 'pc'),
                'unit_value' => $isDon ? 'DONATION' : $uv,
                'quantity' => $qc,
                'quantity_physical_count' => $qp,
                'remarks' => (string) ($item['remarks'] ?? ''),
                'item_group' => (string) ($item['item_group'] ?? $title),
                'var_qty' => $vq,
                'var_val' => $vq * $uv,
            ];
        }
    }

    return $bootRows;
}

/**
 * @return array<int, array<string, mixed>>
 */
/**
 * @param array<string, mixed> $src
 * @return array<int, string>
 */
function physical_count_parse_ppe_tabs(array $src): array
{
    $raw = [];
    if (!empty($src['tabs'])) {
        foreach (explode(',', (string) $src['tabs']) as $part) {
            $raw[] = trim($part);
        }
    } elseif (!empty($src['tab'])) {
        $raw[] = trim((string) $src['tab']);
    }
    $allowed = function_exists('rpcppe_data_tabs') ? rpcppe_data_tabs() : [
        'LAND FINAL',
        'SUPPLIES AND EQUIPMENT FINAL',
        'SEF SCHOOL BUILDING',
        'LAND TITLE',
    ];
    $out = [];
    foreach ($raw as $tab) {
        if ($tab === '') {
            continue;
        }
        foreach ($allowed as $canon) {
            if (strcasecmp($tab, $canon) === 0) {
                $out[] = $canon;
                break;
            }
        }
    }

    return array_values(array_unique($out));
}

/**
 * Group one PPE tab's items by item_group (category rows), then sort_order.
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array{explicit:bool,title:string,book_subtotal:float,items:array}>
 */
function physical_count_report_segments_rpcppe(array $items): array
{
    $groups = [];
    $order = [];
    foreach ($items as $item) {
        $g = trim((string) ($item['item_group'] ?? ''));
        if ($g === '') {
            $g = trim((string) ($item['article'] ?? ''));
        }
        if ($g === '') {
            $g = 'ITEMS';
        }
        $key = function_exists('mb_strtoupper') ? mb_strtoupper($g, 'UTF-8') : strtoupper($g);
        if (!isset($groups[$key])) {
            $groups[$key] = ['title' => $g, 'items' => []];
            $order[] = $key;
        }
        $groups[$key]['items'][] = $item;
    }
    foreach ($groups as &$group) {
        usort($group['items'], static function ($a, $b) {
            $oa = (int) ($a['sort_order'] ?? 0);
            $ob = (int) ($b['sort_order'] ?? 0);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });
    }
    unset($group);

    $out = [];
    foreach ($order as $key) {
        $sub = 0.0;
        foreach ($groups[$key]['items'] as $it) {
            $sub += ((float) ($it['unit_value'] ?? 0)) * pc_book_qty($it);
        }
        $out[] = [
            'explicit' => true,
            'title' => $groups[$key]['title'],
            'book_subtotal' => $sub,
            'items' => $groups[$key]['items'],
        ];
    }

    return $out;
}

/**
 * Office of the Mayor (OM) accountable officer on RPCSE.
 * Certified Correct by / “For which X is accountable” — not the Municipal Mayor.
 *
 * @param array<string, mixed> $office
 */
function physical_count_is_om_office(array $office): bool
{
    $code = strtoupper(trim((string) ($office['code'] ?? $office['sheet_title'] ?? '')));
    if ($code === 'MAYOR' || $code === 'OM') {
        return true;
    }
    $name = trim((string) ($office['name'] ?? $office['office_name'] ?? ''));

    return strcasecmp($name, 'Office of the Mayor') === 0
        || strcasecmp($name, 'OM') === 0;
}

/**
 * @param array<string, mixed> $office
 * @return array{name:string,designation:string}
 */
function physical_count_accountable_for_office(array $office): array
{
    if (physical_count_is_om_office($office)) {
        return [
            'name' => 'LARRY BUTCH P. DE LEON',
            'designation' => 'Board Secretary',
        ];
    }

    return [
        'name' => trim((string) ($office['head_name'] ?? $office['officer_name'] ?? '')),
        'designation' => trim((string) ($office['head_position'] ?? $office['officer_designation'] ?? '')),
    ];
}

function physical_count_offices_map(PDO $conn): array
{
    $stmt = $conn->query('SELECT id, name, code, head_name, head_position FROM offices');
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $acc = physical_count_accountable_for_office($row);
        $row['head_name'] = $acc['name'];
        $row['head_position'] = $acc['designation'];
        $map[(int) $row['id']] = $row;
    }

    return $map;
}

function load_physical_count_report_items(PDO $conn, string $category, string $status, int $office_id, string $search, array $officeIds = []) {
    $sql = 'SELECT p.*, o.name AS office_name, o.code AS office_code
            FROM property_items p
            LEFT JOIN offices o ON p.office_id = o.id
            WHERE p.category = ?';
    $params = [$category];

    if ($status !== '') {
        $sql .= ' AND p.status = ?';
        $params[] = $status;
    }
    $ids = [];
    foreach ($officeIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $ids = array_values(array_unique($ids));
    if ($ids === [] && $office_id > 0) {
        $ids = [$office_id];
    }
    if (count($ids) === 1) {
        $sql .= ' AND p.office_id = ?';
        $params[] = $ids[0];
    } elseif (count($ids) > 1) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql .= ' AND p.office_id IN (' . $placeholders . ')';
        foreach ($ids as $id) {
            $params[] = $id;
        }
    }
    if ($search !== '') {
        $sql .= ' AND (p.description LIKE ? OR p.property_number LIKE ? OR p.article LIKE ? OR p.item_group LIKE ?)';
        $term = '%' . $search . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }
    $sql .= ' ORDER BY p.property_number ASC, p.id ASC';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $rows;
}

/**
 * Split items into segments when office or item_group changes, while preserving global
 * property-number order (rows follow 000001, 000002, 000003 down the printed report).
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array{explicit:bool,title:string,book_subtotal:float,items:array}>
 */
function physical_count_report_segments(array $items) {
    $items = physical_count_sort_items($items);
    $segments = [];
    $curKey = null;
    foreach ($items as $item) {
        $oid = (int) ($item['office_id'] ?? 0);
        $g = trim((string) ($item['item_group'] ?? ''));
        $gk = $g === '' ? '__ungrouped__' : $g;
        $bucketKey = $oid . '|' . $gk;

        if ($curKey !== $bucketKey) {
            $curKey = $bucketKey;
            $explicit = ($gk !== '__ungrouped__');
            $segments[] = [
                'explicit' => $explicit,
                'title' => $explicit ? $g : '',
                'book_subtotal' => 0.0,
                'items' => [],
            ];
        }
        $idx = count($segments) - 1;
        $segments[$idx]['items'][] = $item;
        $segments[$idx]['book_subtotal'] += ((float) ($item['unit_value'] ?? 0)) * pc_book_qty($item);
    }

    return $segments;
}

function physical_count_article_lead_index($article): ?int
{
    $a = trim((string) $article);
    if ($a === '') {
        return null;
    }
    if (preg_match('/^(\d+)\s*(?:[\.\)]\s*|\s+)/u', $a, $m) === 1) {
        return (int) $m[1];
    }
    if (preg_match('/^(\d+)\s*[\.\)]?\s*$/u', $a, $m) === 1) {
        return (int) $m[1];
    }

    return null;
}

/**
 * RPCSE sheet order: section by lowest 1. / 2. article, items by sort_order.
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array{explicit:bool,title:string,book_subtotal:float,items:array}>
 */
function physical_count_report_segments_rpcse(array $items): array
{
    $offices = [];
    $officeOrder = [];
    foreach ($items as $item) {
        $oid = (int) ($item['office_id'] ?? 0);
        $g = trim((string) ($item['item_group'] ?? ''));
        $gk = $g === '' ? '__ungrouped__' : $g;
        if (!isset($offices[$oid])) {
            $offices[$oid] = [];
            $officeOrder[] = $oid;
        }
        if (!isset($offices[$oid][$gk])) {
            $offices[$oid][$gk] = ['title' => $g, 'items' => []];
        }
        $offices[$oid][$gk]['items'][] = $item;
    }

    $segments = [];
    foreach ($officeOrder as $oid) {
        $groups = $offices[$oid];
        $keys = array_keys($groups);
        usort($keys, static function ($ka, $kb) use ($groups) {
            $rank = static function (array $bucket): int {
                $min = PHP_INT_MAX;
                foreach ($bucket['items'] as $it) {
                    $n = physical_count_article_lead_index($it['article'] ?? '');
                    if ($n !== null && $n < $min) {
                        $min = $n;
                    }
                }

                return $min;
            };
            $ra = $rank($groups[$ka]);
            $rb = $rank($groups[$kb]);
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strnatcasecmp((string) $groups[$ka]['title'], (string) $groups[$kb]['title']);
        });

        foreach ($keys as $gk) {
            $sorted = $groups[$gk]['items'];
            usort($sorted, static function ($a, $b) {
                $oa = (int) ($a['sort_order'] ?? 0);
                $ob = (int) ($b['sort_order'] ?? 0);
                if ($oa !== $ob) {
                    return $oa <=> $ob;
                }

                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            });
            $sub = 0.0;
            foreach ($sorted as $it) {
                $sub += ((float) ($it['unit_value'] ?? 0)) * pc_book_qty($it);
            }
            $segments[] = [
                'explicit' => $gk !== '__ungrouped__',
                'title' => $groups[$gk]['title'],
                'book_subtotal' => $sub,
                'items' => $sorted,
            ];
        }
    }

    return $segments;
}

/**
 * Total book value across all items (Σ unit value × qty property card).
 */
function physical_count_grand_book_total(array $items) {
    $t = 0.0;
    foreach ($items as $row) {
        $t += ((float) ($row['unit_value'] ?? 0)) * pc_book_qty($row);
    }

    return $t;
}

/**
 * @param array<int, array<string, mixed>> $items
 * @param string $reportCategory SE|PPE — for PPE, blank quantity_physical_count shows — (optional columns)
 * @return array<int, array{article:string,description:string,property_number:string,uom:string,unit_value:string,qty_card:string,qty_phys:string,var_qty:string,var_val:string,remarks:string}>
 */
function physical_count_report_table_rows(array $items, bool $mergeArticle = false, string $reportCategory = '') {
    $out = [];
    $runningKey = '';
    foreach ($items as $row) {
        $article = trim((string) ($row['article'] ?? ''));
        $desc = trim((string) ($row['description'] ?? ''));
        $pn = trim((string) ($row['property_number'] ?? ''));
        $uom = trim((string) ($row['unit_of_measure'] ?? ''));
        if ($uom === '') {
            $uom = 'pc';
        }
        $uv = (float) ($row['unit_value'] ?? 0);
        $qc = pc_book_qty($row);
        $rawPhys = $row['quantity_physical_count'] ?? null;
        $isPpe = (($row['category'] ?? '') === 'PPE') || ($reportCategory === 'PPE');
        $ppePhysOmit = $isPpe && ($rawPhys === null || $rawPhys === '');
        $qp = $ppePhysOmit ? null : pc_physical_qty($row);
        $vq = $ppePhysOmit ? null : pc_variance_qty($row);
        $vv = $ppePhysOmit ? null : pc_variance_value($row);
        $rem = trim((string) ($row['remarks'] ?? ''));

        if ($ppePhysOmit) {
            $varQtyDisplay = '—';
            $varValDisplay = '—';
        } elseif ($vq === 0) {
            $varQtyDisplay = '—';
            $varValDisplay = '—';
        } else {
            $varQtyDisplay = (string) $vq;
            $varValDisplay = number_format($vv, 2, '.', ',');
        }

        $articleCell = '—';
        if ($mergeArticle) {
            if ($article !== '') {
                if ($article !== $runningKey) {
                    $articleCell = $article;
                    $runningKey = $article;
                } else {
                    $articleCell = '';
                }
            } else {
                $articleCell = '';
            }
        } else {
            $articleCell = $article !== '' ? $article : '—';
        }

        $out[] = [
            'article' => $articleCell,
            'description' => $desc !== '' ? $desc : '—',
            'property_number' => $pn !== '' ? $pn : '—',
            'uom' => $uom,
            'unit_value' => number_format($uv, 2, '.', ','),
            'qty_card' => (string) $qc,
            'qty_phys' => $qp !== null ? (string) $qp : '—',
            'var_qty' => $varQtyDisplay,
            'var_val' => $varValDisplay,
            'remarks' => $rem !== '' ? $rem : '—',
        ];
    }

    return $out;
}
