<?php
// Quick status change from the Quotations list (no need to open the edit form).
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('quotations.php');
}
post_csrf_or_die();

$id     = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$q      = $id ? find_quotation($pdo, $id) : null;

if (!$q) {
    flash('error', 'Quotation not found.');
} elseif (!can_modify_quotation($q)) {
    flash('error', 'You can only change quotations that you created.');
} elseif (!in_array($status, ['draft', 'sent', 'rejected'], true)) {
    flash('error', 'Choose a valid status.');
} else {
    $pdo->prepare('UPDATE quotation SET status = ? WHERE quotation_id = ?')->execute([$status, $id]);
    flash('success', sprintf('Q-%04d is now %s.', $q['quotation_no'], ucfirst($status)));
}

// go back to the same filtered list
$back = $_POST['back'] ?? '';
redirect('quotations.php' . (preg_match('/^\?[A-Za-z0-9_=&%+.\-]*$/', $back) ? $back : ''));
