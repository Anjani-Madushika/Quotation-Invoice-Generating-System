<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/document.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$s  = $pdo->prepare("
  SELECT i.*, q.status AS quotation_status, u.email AS user_email
  FROM invoice i
  JOIN quotation q ON q.quotation_id = i.quotation_id
  JOIN `user` u ON u.user_id = q.user_id
  WHERE i.invoice_id = ? AND u.company_id = ?");
$s->execute([$id, (int) $_SESSION['company_id']]);
$inv = $s->fetch();

if (!$inv) {
    flash('error', 'Invoice not found.');
    redirect('invoices.php');
}

if ($inv['quotation_status'] === 'draft') {
    flash('error', 'This quotation is a draft, so its invoice is not available. Change the quotation status to "sent" first.');
    redirect('quotation_view.php?id=' . (int) $inv['quotation_id']);
}

// Customer details + items come from the invoice's own copy (made when it was generated)
$items   = invoice_items($pdo, (int) $inv['invoice_id']);
$company = find_company($pdo);

$page  = 'invoices';
$title = sprintf('I-%04d', $inv['invoice_no']);
include '../includes/header.php';
?>

<div class="no-print doc-toolbar flex flex-wrap items-center justify-between gap-3">
  <a href="invoices.php" class="text-sm font-semibold text-brand-700 hover:underline">&larr; Back to invoices</a>
  <div class="flex flex-wrap gap-2">
    <a href="quotation_view.php?id=<?= (int) $inv['quotation_id'] ?>" class="<?= BTN2 ?>">View quotation</a>
    <button type="button" data-pdf data-filename="<?= e('Invoice-' . sprintf('I-%04d', $inv['invoice_no']) . (safe_filename((string) $inv['recipient_name']) !== '' ? '-' . safe_filename((string) $inv['recipient_name']) : '') . '.pdf') ?>" class="<?= BTN ?>"><?= icon('download', 'w-4 h-4') ?>Download PDF</button>
  </div>
</div>

<?php
render_document([
    'type'              => 'Invoice',
    'label_to'          => 'Billed to',
    'recipient'         => $inv['recipient_name'],
    'recipient_address' => recipient_block($inv['recipient_address'] ?? '', $inv['recipient_phone'] ?? ''),
    'contact_email'     => $inv['user_email'],
    'meta'              => [
        ['Invoice No', sprintf('I-%04d', $inv['invoice_no'])],
        ['Date', $inv['invoice_date']],
    ],
    'company'           => $company,
    'items'             => $items,
    'subtotal'          => $inv['subtotal'] > 0 ? $inv['subtotal'] : $inv['total'],
    'adjustments'       => document_adjustments($inv),
    'total'             => $inv['total'],
    'notice'            => $inv['special_notice'],
]);
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="../assets/js/pdf.js?v=<?= @filemtime(__DIR__ . '/../assets/js/pdf.js') ?: 1 ?>"></script>
<?php
include '../includes/footer.php';
