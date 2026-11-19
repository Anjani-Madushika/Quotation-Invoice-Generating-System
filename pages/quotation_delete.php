<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('quotations.php');
}
post_csrf_or_die();

$id = (int) ($_POST['id'] ?? 0);
$q  = $id ? find_quotation($pdo, $id) : null;
if ($q && !can_modify_quotation($q)) {
    flash('error', 'You can only delete quotations that you created.');
} elseif ($q) {
    $pdo->prepare('DELETE FROM quotation WHERE quotation_id = ?')->execute([$id]);
    flash('success', 'Quotation deleted.');
}
redirect('quotations.php');
