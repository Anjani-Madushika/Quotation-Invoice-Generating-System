<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/document.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$q  = $id ? find_quotation($pdo, $id) : null;
if (!$q) {
    flash('error', 'Quotation not found.');
    redirect('quotations.php');
}

$canEdit = can_modify_quotation($q);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    post_csrf_or_die();
    $s = $_POST['status'] ?? '';
    if (!can_modify_quotation($q)) {
        flash('error', 'You can only change quotations that you created.');
    } elseif (in_array($s, QUOTATION_STATUSES, true)) {
        $pdo->prepare('UPDATE quotation SET status = ? WHERE quotation_id = ?')->execute([$s, $id]);
        flash('success', 'Status updated.');
    }
    redirect('quotation_view.php?id=' . $id);
}

$items   = quotation_items($pdo, $id);
$company = find_company($pdo);
$invStmt = $pdo->prepare('SELECT invoice_id FROM invoice WHERE quotation_id = ? LIMIT 1');
$invStmt->execute([$id]);
$invoiceId = $invStmt->fetchColumn();

$page  = 'quotations';
$title = sprintf('Q-%04d', $q['quotation_no']);
include '../includes/header.php';
?>

<div class="no-print doc-toolbar flex flex-col gap-3">
  <a href="quotations.php" class="text-sm font-semibold text-brand-700 hover:underline">&larr; Back to quotations</a>
  <div class="flex flex-wrap items-start justify-between gap-2">
    <?php if ($canEdit): ?>
    <form method="post" class="flex items-start gap-2 w-full sm:w-auto">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <!-- Status dropdown: the list floats over the page, so the quotation never moves down -->
      <div class="relative flex-1 min-w-0 sm:flex-none sm:w-44" data-status-dd>
        <input type="hidden" name="status" value="<?= e($q['status']) ?>">
        <button type="button" data-dd-btn aria-haspopup="listbox" aria-expanded="false"
                class="w-full flex items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
          <span data-dd-label><?= e(ucfirst($q['status'])) ?></span>
          <svg class="w-4 h-4 text-slate-500 transition-transform" data-dd-arrow fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
        </button>
        <ul data-dd-list role="listbox" class="hidden absolute left-0 right-0 top-full mt-1 z-30 rounded-xl border border-slate-200 bg-white shadow-lg overflow-hidden text-sm">
          <?php foreach (QUOTATION_STATUSES as $s): ?>
            <li role="option" data-value="<?= e($s) ?>" aria-selected="<?= $q['status'] === $s ? 'true' : 'false' ?>"
                class="cursor-pointer px-3 py-2 hover:bg-brand-50 <?= $q['status'] === $s ? 'font-bold text-brand-700' : '' ?>"><?= e(ucfirst($s)) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <button class="<?= BTN2 ?>">Update status</button>
    </form>
    <?php else: ?>
    <p class="text-sm text-slate-500 py-2">Created by <?= e($q['user_name']) ?> (view only)</p>
    <?php endif; ?>
    <div class="flex flex-wrap items-center gap-2">
    <button type="button" data-pdf data-filename="<?= e('Quotation-' . sprintf('Q-%04d', $q['quotation_no']) . (safe_filename($q['recipient_name']) !== '' ? '-' . safe_filename($q['recipient_name']) : '') . '.pdf') ?>" class="<?= BTN ?>"><?= icon('download', 'w-4 h-4') ?>Download PDF</button>
    <?php if ($canEdit): ?>
    <a href="quotation_form.php?id=<?= $id ?>" class="<?= BTN2 ?>">Edit</a>
    <?php endif; ?>
    <?php if ($q['status'] === 'draft'): ?>
      <button type="button" disabled title="Change the status to Sent first" class="<?= BTN ?> opacity-50 cursor-not-allowed"><?= $invoiceId ? 'View invoice' : 'Generate invoice' ?></button>
    <?php elseif ($invoiceId): ?>
      <a href="invoice_view.php?id=<?= (int) $invoiceId ?>" class="<?= BTN ?>">View invoice</a>
    <?php elseif (!$canEdit): ?>
      <?php /* staff cannot generate an invoice for someone else's quotation */ ?>
    <?php else: ?>
      <form method="post" action="invoice_create.php">
        <?= csrf_field() ?>
        <input type="hidden" name="quotation_id" value="<?= $id ?>">
        <button class="<?= BTN ?>">Generate invoice</button>
      </form>
    <?php endif; ?>
    </div>
  </div>
</div>

<?php
render_document([
    'type'              => 'Quotation',
    'label_to'          => 'Billed to',
    'recipient'         => $q['recipient_name'],
    'recipient_address' => recipient_block($q['recipient_address'] ?? '', $q['recipient_phone'] ?? ''),
    'contact_email'     => $q['user_email'],
    'meta'              => [
        ['Quotation No', sprintf('Q-%04d', $q['quotation_no'])],
        ['Date', $q['quotation_date']],
    ],
    'company'           => $company,
    'items'             => $items,
    'subtotal'          => $q['subtotal'] > 0 ? $q['subtotal'] : $q['total'],
    'adjustments'       => document_adjustments($q),
    'total'             => $q['total'],
    'notice'            => $q['special_notice'],
]);
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="../assets/js/pdf.js?v=<?= @filemtime(__DIR__ . '/../assets/js/pdf.js') ?: 1 ?>"></script>
<?php
include '../includes/footer.php';
