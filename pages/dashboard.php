<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();

$cid = (int) $_SESSION['company_id'];

$q = $pdo->prepare("
  SELECT COUNT(*) AS total_q,
         SUM(q.status = 'draft') AS draft_q,
         SUM(q.status = 'sent') AS sent_q
  FROM quotation q JOIN `user` u ON u.user_id = q.user_id
  WHERE u.company_id = ?");
$q->execute([$cid]);
$qs = $q->fetch();

// Which invoices count as "Invoiced": chosen with the small dropdown on the card (default = Sent only)
$invOptions = [
    'sent'  => ['Sent only',          ['sent']],
    'draft' => ['Sent + Draft',       ['sent', 'draft']],
    'all'   => ['All (incl. rejected)', ['draft', 'sent', 'rejected']],
];
$invKey = $_GET['invoiced'] ?? 'sent';
if (!isset($invOptions[$invKey])) { $invKey = 'sent'; }
$invStatuses = $invOptions[$invKey][1];
$invIn       = implode(',', array_fill(0, count($invStatuses), '?'));

$i = $pdo->prepare("
  SELECT COALESCE(SUM(i.total),0) AS revenue
  FROM invoice i
  JOIN quotation q ON q.quotation_id = i.quotation_id
  JOIN `user` u ON u.user_id = q.user_id
  WHERE u.company_id = ? AND q.status IN ($invIn)");
$i->execute(array_merge([$cid], $invStatuses));
$revenue = $i->fetchColumn();

$r = $pdo->prepare("
  SELECT q.quotation_id, q.quotation_no, q.recipient_name, q.quotation_date, q.total, q.status
  FROM quotation q JOIN `user` u ON u.user_id = q.user_id
  WHERE u.company_id = ?
  ORDER BY q.quotation_id DESC LIMIT 6");
$r->execute([$cid]);
$recent = $r->fetchAll();

$cards = [
    ['Quotations',     (string) (int) $qs['total_q'],    'file',  'bg-brand-100 text-brand-700'],
    ['Draft',          (string) (int) $qs['draft_q'],    'clock', 'bg-brand-100 text-brand-700'],
    ['Sent',           (string) (int) $qs['sent_q'],     'check', 'bg-brand-100 text-brand-700'],
    ['Invoiced (Rs.)', money($revenue),                  'cash',  'bg-brand-100 text-brand-700', true],
];

// Invoice totals are for admins only
if (!is_admin()) {
    $cards = array_values(array_filter($cards, fn($c) => empty($c[4])));
}
$cards = array_map(fn($c) => array_pad($c, 5, false), $cards);   // 5th value = show the filter dropdown

$page  = 'dashboard';
$title = 'Dashboard';
include '../includes/header.php';

page_head(
    'Hello, ' . ($_SESSION['name'] ?? ''),
    'Here is where your quotations stand today.',
    '<a href="quotation_form.php" class="' . BTN . '">' . icon('plus', 'w-4 h-4') . 'New quotation</a>'
);
?>

<section class="grid grid-cols-2 <?= is_admin() ? 'xl:grid-cols-4' : 'xl:grid-cols-3' ?> gap-4 mb-8">
  <?php foreach ($cards as [$label, $value, $ic, $tone, $hasFilter]): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
      <div class="w-10 h-10 rounded-xl grid place-items-center <?= $tone ?>"><?= icon($ic) ?></div>
      <p class="mt-4 text-sm text-slate-500"><?= e($label) ?></p>
      <p class="mt-1 text-2xl lg:text-3xl font-extrabold text-brand-900 break-words"><?= e($value) ?></p>
      <?php if (!empty($hasFilter)): ?>
        <form method="get" class="mt-3">
          <label class="sr-only" for="invoiced">Which invoices to count</label>
          <select id="invoiced" name="invoiced" onchange="this.form.submit()"
                  class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700">
            <?php foreach ($invOptions as $k => [$optLabel]): ?>
              <option value="<?= e($k) ?>" <?= $invKey === $k ? 'selected' : '' ?>><?= e($optLabel) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>

<div class="grid lg:grid-cols-3 gap-6">
  <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <h2 class="font-bold text-brand-900">Recent quotations</h2>
      <a href="quotations.php" class="text-sm font-semibold text-brand-600 hover:text-brand-700">View all</a>
    </div>

    <?php if (!$recent): ?>
      <div class="p-10 text-center">
        <p class="text-slate-600">No quotations yet.</p>
        <a href="quotation_form.php" class="<?= BTN ?> mt-4">Create your first quotation</a>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="resp-table w-full text-sm">
          <thead class="text-left text-slate-500 bg-slate-50">
            <tr>
              <th class="px-5 py-3 font-semibold">No.</th>
              <th class="px-5 py-3 font-semibold">Recipient</th>
              <th class="px-5 py-3 font-semibold whitespace-nowrap">Date</th>
              <th class="px-5 py-3 font-semibold text-right whitespace-nowrap">Total (Rs.)</th>
              <th class="px-5 py-3 font-semibold">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($recent as $row): ?>
              <tr class="hover:bg-brand-50/50">
                <td data-label="No." class="px-5 py-3 font-semibold">
                  <a class="text-brand-700 hover:underline" href="quotation_view.php?id=<?= (int) $row['quotation_id'] ?>"><?= e(sprintf('Q-%04d', $row['quotation_no'])) ?></a>
                </td>
                <td data-label="Recipient" class="px-5 py-3"><?= e($row['recipient_name']) ?></td>
                <td data-label="Date" class="px-5 py-3 whitespace-nowrap"><?= e($row['quotation_date']) ?></td>
                <td data-label="Total (Rs.)" class="px-5 py-3 text-right"><?= money($row['total']) ?></td>
                <td data-label="Status" class="px-5 py-3"><?= status_badge($row['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <h2 class="font-bold text-brand-900 mb-4">Quick actions</h2>
    <div class="space-y-3">
      <a href="quotation_form.php" class="<?= BTN ?> w-full py-3">New quotation</a>
      <a href="items.php"          class="<?= BTN2 ?> w-full py-3">Add product or service</a>
      <a href="invoices.php"       class="<?= BTN2 ?> w-full py-3">View invoices</a>
      <?php if (is_admin()): ?>
      <a href="company.php"        class="<?= BTN2 ?> w-full py-3">Company details</a>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>
