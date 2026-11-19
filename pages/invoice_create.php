<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('quotations.php');
}
post_csrf_or_die();

$qid = (int) ($_POST['quotation_id'] ?? 0);
$q   = $qid ? find_quotation($pdo, $qid) : null;
if (!$q) {
    flash('error', 'Quotation not found.');
    redirect('quotations.php');
}

if (!can_modify_quotation($q)) {
    flash('error', 'You can only generate invoices for quotations that you created.');
    redirect('quotation_view.php?id=' . $qid);
}

$ex = $pdo->prepare('SELECT invoice_id FROM invoice WHERE quotation_id = ? LIMIT 1');
$ex->execute([$qid]);
$existing = $ex->fetchColumn();

if ($existing) {
    redirect('invoice_view.php?id=' . (int) $existing);
}

// A draft quotation is not final yet, so no invoice can be made from it
if ($q['status'] === 'draft') {
    flash('error', 'This quotation is still a draft. Change its status to "sent" before generating an invoice.');
    redirect('quotation_view.php?id=' . $qid);
}

try {
    $pdo->beginTransaction();
    // Invoice numbers count up across the whole company (1, 2, 3 ...), so two users never get the same number
    $pdo->prepare('SELECT company_id FROM company WHERE company_id = ? FOR UPDATE')->execute([(int) $_SESSION['company_id']]);
    $nx = $pdo->prepare('SELECT COALESCE(MAX(invoice_no), 0) + 1 FROM invoice WHERE company_id = ?');
    $nx->execute([(int) $_SESSION['company_id']]);
    $invoiceNo = (int) $nx->fetchColumn();

    // Copy the amounts over so the invoice keeps the same discount and VAT as the quotation
    // The invoice also gets its OWN copy of the customer details + notice, and of every item (invoice_item),
    // so editing the quotation afterwards never changes an invoice that was already issued.
    $pdo->prepare('INSERT INTO invoice (company_id, quotation_id, invoice_no, invoice_date, subtotal, discount_percent, discount_amount, vat_percent, vat_amount, total, recipient_name, recipient_address, recipient_phone, special_notice) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([(int) $_SESSION['company_id'], $qid, $invoiceNo, date('Y-m-d'), $q['subtotal'], $q['discount_percent'], $q['discount_amount'], $q['vat_percent'], $q['vat_amount'], $q['total'], $q['recipient_name'], $q['recipient_address'], $q['recipient_phone'], $q['special_notice']]);
    $invoiceId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO invoice_item (invoice_id, name, description, unit_price, quantity, unit) SELECT ?, name, description, unit_price, quantity, unit FROM quotation_item WHERE quotation_id = ? ORDER BY quotation_item_id')
        ->execute([$invoiceId, $qid]);
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Invoice create failed: ' . $ex->getMessage());
    // Most likely a double click: the invoice was made a moment ago, so just open it
    $ex2 = $pdo->prepare('SELECT invoice_id FROM invoice WHERE quotation_id = ? LIMIT 1');
    $ex2->execute([$qid]);
    $again = $ex2->fetchColumn();
    if ($again) {
        redirect('invoice_view.php?id=' . (int) $again);
    }
    flash('error', 'Could not generate the invoice. Try again.');
    redirect('quotation_view.php?id=' . $qid);
}

flash('success', 'Invoice generated.');
redirect('invoice_view.php?id=' . $invoiceId);
