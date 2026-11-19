<?php
// Returns the printable quotation (same layout as the real document) for the data typed so far in the quotation form.
// Nothing is saved. Used by the live preview on quotation_form.php.
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/document.php';

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    http_response_code(400);
    exit;
}

$cid = (int) $_SESSION['company_id'];
$id  = (int) ($_POST['id'] ?? 0);
$q   = $id ? find_quotation($pdo, $id) : null;

if ($q) {
    $no    = (int) $q['quotation_no'];
    $email = (string) $q['user_email'];
} else {
    $nx = $pdo->prepare('SELECT COALESCE(MAX(quotation_no), 0) + 1 FROM quotation WHERE company_id = ?');
    $nx->execute([$cid]);
    $no = (int) $nx->fetchColumn();
    $us = $pdo->prepare('SELECT email FROM `user` WHERE user_id = ?');
    $us->execute([(int) $_SESSION['user_id']]);
    $email = (string) $us->fetchColumn();
}

$names  = is_array($_POST['item_name']  ?? null) ? $_POST['item_name']  : [];
$descs  = is_array($_POST['item_desc']  ?? null) ? $_POST['item_desc']  : [];
$prices = is_array($_POST['item_price'] ?? null) ? $_POST['item_price'] : [];
$qtys   = is_array($_POST['item_qty']   ?? null) ? $_POST['item_qty']   : [];
$units  = is_array($_POST['item_unit']  ?? null) ? $_POST['item_unit']  : [];

$items = [];
$sum   = 0.0;
foreach ($names as $k => $n) {
    $n = trim((string) $n);
    if ($n === '') {
        continue;
    }
    $price = max(0.0, (float) ($prices[$k] ?? 0));
    $unit  = clean_unit($units[$k] ?? '');
    $qty   = clean_qty($qtys[$k] ?? 1, $unit);
    $items[] = [
        'name'        => $n,
        'description' => trim((string) ($descs[$k] ?? '')),
        'quantity'    => $qty,
        'unit'        => $unit,
        'unit_price'  => $price,
    ];
    $sum += line_amount($price, $qty);
}

$calc = calc_totals($sum, (float) ($_POST['discount_percent'] ?? 0), (float) ($_POST['vat_percent'] ?? 0));
$date = trim((string) ($_POST['quotation_date'] ?? ''));
if (!strtotime($date)) {
    $date = date('Y-m-d');
}
$recipient = trim((string) ($_POST['recipient_name'] ?? ''));

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
render_document([
    'type'              => 'Quotation',
    'label_to'          => 'Billed to',
    'recipient'         => $recipient !== '' ? $recipient : 'Recipient name',
    'recipient_address' => recipient_block($_POST['recipient_address'] ?? '', $_POST['recipient_phone'] ?? ''),
    'contact_email'     => $email,
    'meta'              => [
        ['Quotation No', sprintf('Q-%04d', $no)],
        ['Date', $date],
    ],
    'company'           => find_company($pdo),
    'items'             => $items,
    'subtotal'          => $calc['subtotal'],
    'adjustments'       => document_adjustments($calc),
    'total'             => $calc['total'],
    'notice'            => trim((string) ($_POST['special_notice'] ?? '')),
]);
