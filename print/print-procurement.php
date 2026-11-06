<?php
/**
 * Procurement printouts: PR, RFQ (per quotation), PO, AIR
 * URLs: /lgu_invent/print/print-procurement.php  (legacy: /lgu_invent/print-procurement.php)
 *
 * Usage:
 *   ?type=pr&id={pr_id}
 *   ?type=rfq&id={quotation_id}
 *   ?type=po&id={proc_po.id}
 *   ?type=air&id={proc_air.id}
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';

PermissionHelper::require('view');

require_once dirname(__DIR__) . '/includes/procurement_print_data.php';

$type = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!in_array($type, ['pr', 'rfq', 'po', 'air'], true) || $id <= 0) {
    http_response_code(400);
    echo 'Invalid type or id.';
    exit;
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database unavailable.';
    exit;
}

$payload = null;

if ($type === 'pr') {
    $payload = procurement_print_load_pr($conn, $id);
} elseif ($type === 'rfq') {
    $payload = procurement_print_load_quotation($conn, $id);
} elseif ($type === 'po') {
    $payload = procurement_print_load_po($conn, $id);
} elseif ($type === 'air') {
    $payload = procurement_print_load_air($conn, $id);
}

$ok = false;
if ($payload !== null) {
    if ($type === 'pr' && !empty($payload['pr'])) {
        $ok = true;
    } elseif ($type === 'rfq' && !empty($payload['quotation'])) {
        $ok = true;
    } elseif ($type === 'po' && !empty($payload['po'])) {
        $ok = true;
    } elseif ($type === 'air' && !empty($payload['air'])) {
        $ok = true;
    }
}
if (!$ok) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$printOfficeId = 0;
if ($type === 'pr') {
    $printOfficeId = (int) ($payload['pr']['office_id'] ?? 0);
} elseif ($type === 'rfq') {
    $printOfficeId = (int) ($payload['quotation']['office_id'] ?? 0);
} elseif ($type === 'po') {
    $printOfficeId = (int) ($payload['po']['office_id'] ?? 0);
} elseif ($type === 'air') {
    $printOfficeId = (int) ($payload['air']['office_id'] ?? 0);
}
if (PermissionHelper::isDepartmentScoped()) {
    $mine = PermissionHelper::officeId();
    if ($mine <= 0 || $printOfficeId !== $mine) {
        http_response_code(403);
        echo 'You can only print purchase documents for your department.';
        exit;
    }
}

$formType = $type;

require dirname(__DIR__) . '/app/views/reports/print_procurement_form.php';
