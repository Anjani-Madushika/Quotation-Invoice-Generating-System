<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();

$cid      = (int) $_SESSION['company_id'];

// Update status from the list (same rule as the quotation page: only the owner or an admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_csrf_or_die();
    $qid = (int) ($_POST['id'] ?? 0);
    $st  = $_POST['status'] ?? '';
    $row = $qid ? find_quotation($pdo, $qid) : null;
    if (!$row) {
        flash('error', 'Quotation not found.');
    } elseif (!can_modify_quotation($row)) {
        flash('error', 'You can only change quotations that you created.');
    } elseif (in_array($st, QUOTATION_STATUSES, true)) {
        $pdo->prepare('UPDATE quotation SET status = ? WHERE quotation_id = ?')->execute([$st, $qid]);
        flash('success', 'Status updated.');
    }
    redirect('quotations.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}
$search   = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';

$where  = 'u.company_id = ?';
$params = [$cid];
[$sSql, $sParams] = search_clause($search, 'q');
$where  .= $sSql;
$params  = array_merge($params, $sParams);
if (in_array($status, QUOTATION_STATUSES, true)) {
    $where   .= ' AND q.status = ?';
    $params[] = $status;
}

$stmt = $pdo->prepare("
  SELECT q.*, u.name AS user_name,
         (SELECT i.invoice_id FROM invoice i WHERE i.quotation_id = q.quotation_id LIMIT 1) AS invoice_id
  FROM quotation q JOIN `user` u ON u.user_id = q.user_id
  WHERE $where
  ORDER BY q.quotation_id DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$page  = 'quotations';
$title = 'Quotations';
include '../includes/header.php';

page_head('Quotations', 'Create, edit, and send quotations.',
    '<a href="quotation_form.php" class="' . BTN . '">' . icon('plus', 'w-4 h-4') . 'New quotation</a>');
?>

<form method="get" class="no-print flex flex-col sm:flex-row gap-3 mb-5">
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search: quotation no, name, contact no, date" class="<?= INPUT ?> sm:max-w-sm">
  <select name="status" class="<?= INPUT ?> sm:max-w-[11rem]">
    <option value="">All statuses</option>
    <?php foreach (QUOTATION_STATUSES as $s): ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="<?= BTN2 ?>">Filter</button>
</form>

<section class="bg-white rounded-2xl border border-slate-200">
  <?php if (!$rows): ?>
    <div class="p-10 text-center">
      <p class="text-slate-600">No quotations found.</p>
      <a href="quotation_form.php" class="<?= BTN ?> mt-4">Create a quotation</a>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="resp-table w-full text-sm">
        <thead class="text-left text-slate-500 bg-slate-50">
          <tr>
            <th class="px-5 py-3 font-semibold">No.</th>
            <th class="px-5 py-3 font-semibold">Recipient</th>
            <th class="px-5 py-3 font-semibold">Date</th>
            <th class="px-5 py-3 font-semibold text-right whitespace-nowrap">Total (Rs.)</th>
            <th class="px-5 py-3 font-semibold">Status</th>
            <th class="px-5 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($rows as $row): ?>
            <tr class="hover:bg-brand-50/50">
              <td data-label="No." class="px-5 py-3 font-semibold whitespace-nowrap"><?= e(sprintf('Q-%04d', $row['quotation_no'])) ?></td>
              <td data-label="Recipient" class="px-5 py-3">
                <p class="font-semibold"><?= e($row['recipient_name']) ?></p>
                <?php if (!empty($row['recipient_phone'])): ?>
                  <p class="text-xs text-slate-500"><?= e($row['recipient_phone']) ?></p>
                <?php endif; ?>
              </td>
              <td data-label="Date" class="px-5 py-3 whitespace-nowrap"><?= e($row['quotation_date']) ?></td>
              <td data-label="Total (Rs.)" class="px-5 py-3 text-right"><?= money($row['total']) ?></td>
              <?php $canEdit = can_modify_quotation($row); ?>
              <td data-label="Status" class="px-5 py-3">
                <?php if ($canEdit): ?>
                  <?php /* Pick a status and it saves at once (same status as the quotation form) */ ?>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['quotation_id'] ?>">
                    <select name="status" onchange="this.form.submit()" aria-label="Status" class="rounded-full border-0 text-xs font-bold pl-2.5 pr-7 py-1 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand-500 <?= status_classes($row['status']) ?>">
                      <?php foreach (QUOTATION_STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                <?php else: ?>
                  <?= status_badge($row['status']) ?>
                <?php endif; ?>
              </td>
              <td data-label="" class="cell-actions px-5 py-3">
                <div class="flex items-center justify-end gap-3 whitespace-nowrap">
                  <a class="font-semibold text-brand-700 hover:underline" href="quotation_view.php?id=<?= (int) $row['quotation_id'] ?>">View</a>
                  <?php if ($canEdit): ?>
                  <a class="font-semibold text-slate-600 hover:underline" href="quotation_form.php?id=<?= (int) $row['quotation_id'] ?>">Edit</a>
                  <?php endif; ?>
                  <a class="font-semibold text-brand-700 hover:underline" href="quotation_view.php?id=<?= (int) $row['quotation_id'] ?>&amp;download=1">PDF</a>
                  <?php if ($canEdit): ?>
                  <form method="post" action="quotation_delete.php" onsubmit="return confirm('Delete this quotation and its invoice?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['quotation_id'] ?>">
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
