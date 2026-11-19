<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();

$cid = (int) $_SESSION['company_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_csrf_or_die();
    $iid = (int) ($_POST['id'] ?? 0);
    $chk = $pdo->prepare('SELECT i.invoice_id, q.user_id AS owner_id FROM invoice i JOIN quotation q ON q.quotation_id = i.quotation_id JOIN `user` u ON u.user_id = q.user_id WHERE i.invoice_id = ? AND u.company_id = ?');
    $chk->execute([$iid, $cid]);
    $found = $chk->fetch();
    if ($found && !can_modify_invoice((int) $found['owner_id'])) {
        flash('error', 'You can only delete invoices for quotations that you created.');
    } elseif ($found) {
        $pdo->prepare('DELETE FROM invoice WHERE invoice_id = ?')->execute([$iid]);
        flash('success', 'Invoice deleted.');
    }
    redirect('invoices.php');
}

$search = trim($_GET['q'] ?? '');
[$sSql, $sParams] = search_clause($search, 'q', true);   // true: also find by invoice no and invoice date

$stmt = $pdo->prepare("
  SELECT i.*, q.quotation_no, q.user_id AS owner_id
  FROM invoice i
  JOIN quotation q ON q.quotation_id = i.quotation_id
  JOIN `user` u ON u.user_id = q.user_id
  WHERE u.company_id = ? AND q.status <> 'draft'$sSql
  ORDER BY i.invoice_id DESC");
$stmt->execute(array_merge([$cid], $sParams));
$rows = $stmt->fetchAll();

$page  = 'invoices';
$title = 'Invoices';
include '../includes/header.php';
page_head('Invoices', 'Invoices are generated from your quotations.');
?>

<form method="get" class="no-print flex flex-col sm:flex-row gap-3 mb-5">
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search: invoice / quotation no, name, contact no, date" class="<?= INPUT ?> sm:max-w-sm">
  <button class="<?= BTN2 ?>">Search</button>
  <?php if ($search !== ''): ?><a href="invoices.php" class="<?= BTN2 ?>">Clear</a><?php endif; ?>
</form>

<section class="bg-white rounded-2xl border border-slate-200">
  <?php if (!$rows): ?>
    <div class="p-10 text-center">
      <p class="text-slate-600"><?= $search !== '' ? 'No invoices match your search.' : 'No invoices yet. Open a quotation and choose Generate invoice.' ?></p>
      <a href="quotations.php" class="<?= BTN ?> mt-4">Go to quotations</a>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="resp-table w-full text-sm">
        <thead class="text-left text-slate-500 bg-slate-50">
          <tr>
            <th class="px-5 py-3 font-semibold">Invoice</th>
            <th class="px-5 py-3 font-semibold">Quotation</th>
            <th class="px-5 py-3 font-semibold">Recipient</th>
            <th class="px-5 py-3 font-semibold">Date</th>
            <th class="px-5 py-3 font-semibold text-right whitespace-nowrap">Total (Rs.)</th>
            <th class="px-5 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($rows as $row): ?>
            <tr class="hover:bg-brand-50/50">
              <td data-label="Invoice" class="px-5 py-3 font-semibold"><?= e(sprintf('I-%04d', $row['invoice_no'])) ?></td>
              <td data-label="Quotation" class="px-5 py-3">
                <a class="text-brand-700 hover:underline" href="quotation_view.php?id=<?= (int) $row['quotation_id'] ?>"><?= e(sprintf('Q-%04d', $row['quotation_no'])) ?></a>
              </td>
              <td data-label="Recipient" class="px-5 py-3">
                <?= e($row['recipient_name']) ?>
                <?php if (!empty($row['recipient_phone'])): ?><p class="text-xs text-slate-500"><?= e($row['recipient_phone']) ?></p><?php endif; ?>
              </td>
              <td data-label="Date" class="px-5 py-3 whitespace-nowrap"><?= e($row['invoice_date']) ?></td>
              <td data-label="Total (Rs.)" class="px-5 py-3 text-right"><?= money($row['total']) ?></td>
              <td data-label="" class="cell-actions px-5 py-3">
                <div class="flex items-center justify-end gap-3 whitespace-nowrap">
                  <a class="font-semibold text-brand-700 hover:underline" href="invoice_view.php?id=<?= (int) $row['invoice_id'] ?>">View</a>
                  <a class="font-semibold text-slate-600 hover:underline" href="invoice_view.php?id=<?= (int) $row['invoice_id'] ?>&amp;download=1">PDF</a>
                  <?php if (can_modify_invoice((int) $row['owner_id'])): ?>
                  <form method="post" onsubmit="return confirm('Delete this invoice?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['invoice_id'] ?>">
                    <button class="font-semibold text-rose-600 hover:underline">Delete</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php include '../includes/footer.php'; ?>
