<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();

$cid = (int) $_SESSION['company_id'];
$uid = (int) $_SESSION['user_id'];
$id  = (int) ($_GET['id'] ?? 0);
$q   = null;

if ($id) {
    $q = find_quotation($pdo, $id);
    if (!$q) {
        flash('error', 'Quotation not found.');
        redirect('quotations.php');
    }
    if (!can_modify_quotation($q)) {
        flash('error', 'You can only edit quotations that you created.');
        redirect('quotation_view.php?id=' . $id);
    }
}

$ps = $pdo->prepare('SELECT item_id, name, price, unit FROM product_service WHERE company_id = ? ORDER BY name');
$ps->execute([$cid]);
$products = $ps->fetchAll();
$validIds = array_map('intval', array_column($products, 'item_id'));

$error = '';
$form = [
    'recipient_name'    => $q['recipient_name'] ?? '',
    'recipient_address' => $q['recipient_address'] ?? '',
    'recipient_phone'   => $q['recipient_phone'] ?? '',
    'quotation_date' => $q['quotation_date'] ?? date('Y-m-d'),
    'status'         => $q['status'] ?? 'draft',
    'special_notice' => $q['special_notice'] ?? '',
    'discount_percent' => $q ? (float) $q['discount_percent'] : 0,
    'vat_percent'      => $q ? (float) $q['vat_percent'] : 0,
];

$rows = [];
if ($q) {
    foreach (quotation_items($pdo, $id) as $it) {
        $rows[] = [
            'item_id'     => $it['item_id'] !== null ? (int) $it['item_id'] : null,
            'name'        => $it['name'],
            'description' => $it['description'] ?? '',
            'price'       => (float) $it['unit_price'],
            'qty'         => (float) $it['quantity'],
            'unit'        => (string) ($it['unit'] ?? ''),
        ];
    }
}

// Does this quotation already have an invoice? (the invoice keeps its own copy: editing here does not change it)
$existingInvoiceNo = null;
if ($q) {
    $ei = $pdo->prepare('SELECT invoice_no FROM invoice WHERE quotation_id = ? LIMIT 1');
    $ei->execute([$id]);
    $existingInvoiceNo = $ei->fetchColumn() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['recipient_name']    = trim($_POST['recipient_name'] ?? '');
    $form['recipient_address'] = trim($_POST['recipient_address'] ?? '');
    $form['recipient_phone']   = trim($_POST['recipient_phone'] ?? '');
    $form['quotation_date'] = trim($_POST['quotation_date'] ?? '');
    $form['status']         = $_POST['status'] ?? 'draft';
    $form['special_notice'] = trim($_POST['special_notice'] ?? '');
    $form['discount_percent'] = min(100.0, max(0.0, (float) ($_POST['discount_percent'] ?? 0)));
    $form['vat_percent']      = min(100.0, max(0.0, (float) ($_POST['vat_percent'] ?? 0)));

    $names  = is_array($_POST['item_name']  ?? null) ? $_POST['item_name']  : [];
    $descs  = is_array($_POST['item_desc']  ?? null) ? $_POST['item_desc']  : [];
    $prices = is_array($_POST['item_price'] ?? null) ? $_POST['item_price'] : [];
    $qtys   = is_array($_POST['item_qty']   ?? null) ? $_POST['item_qty']   : [];
    $ids    = is_array($_POST['item_id']    ?? null) ? $_POST['item_id']    : [];
    $units  = is_array($_POST['item_unit']  ?? null) ? $_POST['item_unit']  : [];

    $rows  = [];
    $total = 0.0;
    foreach ($names as $k => $n) {
        $n = trim((string) $n);
        if ($n === '') {
            continue;
        }
        $price = min(99999999.99, max(0.0, (float) ($prices[$k] ?? 0)));
        $unit  = clean_unit($units[$k] ?? '');
        $qty   = clean_qty($qtys[$k] ?? 1, $unit);
        $iid   = (int) ($ids[$k] ?? 0);
        $rows[] = [
            'item_id'     => in_array($iid, $validIds, true) ? $iid : null,
            'name'        => $n,
            'description' => trim((string) ($descs[$k] ?? '')),
            'price'       => $price,
            'qty'         => $qty,
            'unit'        => $unit,
        ];
        $total += line_amount($price, $qty);
    }

    $calc = calc_totals($total, $form['discount_percent'], $form['vat_percent']);

    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } elseif ($form['recipient_name'] === '') {
        $error = 'Enter the recipient name.';
    } elseif (!($dt = DateTime::createFromFormat('Y-m-d', $form['quotation_date'])) || $dt->format('Y-m-d') !== $form['quotation_date']) {
        $error = 'Choose a valid date.';
    } elseif (mb_strlen($form['recipient_phone']) > 30) {
        $error = 'The contact number is too long.';
    } elseif (!in_array($form['status'], QUOTATION_STATUSES, true)) {
        $error = 'Choose a valid status.';
    } elseif (!$rows) {
        $error = 'Add at least one item.';
    } elseif (($dupName = first_duplicate_name($rows)) !== null) {
        $error = '"' . $dupName . '" is added more than once. Use one row and change the quantity instead.';
    } elseif ($calc['total'] > 9999999999) {
        $error = 'The total is too large. Check the prices and quantities.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($q) {
                $pdo->prepare('UPDATE quotation SET recipient_name = ?, recipient_address = ?, recipient_phone = ?, quotation_date = ?, subtotal = ?, discount_percent = ?, discount_amount = ?, vat_percent = ?, vat_amount = ?, total = ?, status = ?, special_notice = ? WHERE quotation_id = ?')
                    ->execute([$form['recipient_name'], $form['recipient_address'] ?: null, $form['recipient_phone'] ?: null, $form['quotation_date'], $calc['subtotal'], $calc['discount_percent'], $calc['discount_amount'], $calc['vat_percent'], $calc['vat_amount'], $calc['total'], $form['status'], $form['special_notice'] ?: null, $id]);
                // An invoice already made from this quotation is NOT touched: it keeps its own copy of the items and amounts.
                $pdo->prepare('DELETE FROM quotation_item WHERE quotation_id = ?')->execute([$id]);
                $qid = $id;
            } else {
                // Quotation numbers count up across the whole company (1, 2, 3 ...), so two users never get the same number
                $pdo->prepare('SELECT company_id FROM company WHERE company_id = ? FOR UPDATE')->execute([$cid]);
                $nx = $pdo->prepare('SELECT COALESCE(MAX(quotation_no), 0) + 1 FROM quotation WHERE company_id = ?');
                $nx->execute([$cid]);
                $quotationNo = (int) $nx->fetchColumn();

                $pdo->prepare('INSERT INTO quotation (user_id, company_id, quotation_no, recipient_name, recipient_address, recipient_phone, quotation_date, subtotal, discount_percent, discount_amount, vat_percent, vat_amount, total, status, special_notice) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$uid, $cid, $quotationNo, $form['recipient_name'], $form['recipient_address'] ?: null, $form['recipient_phone'] ?: null, $form['quotation_date'], $calc['subtotal'], $calc['discount_percent'], $calc['discount_amount'], $calc['vat_percent'], $calc['vat_amount'], $calc['total'], $form['status'], $form['special_notice'] ?: null]);
                $qid = (int) $pdo->lastInsertId();
            }
            $ins = $pdo->prepare('INSERT INTO quotation_item (quotation_id, item_id, name, description, unit_price, quantity, unit) VALUES (?,?,?,?,?,?,?)');
            foreach ($rows as $r) {
                $ins->execute([$qid, $r['item_id'], $r['name'], $r['description'] !== '' ? $r['description'] : null, $r['price'], $r['qty'], $r['unit']]);
            }
            $pdo->commit();
            flash('success', $q ? 'Quotation updated.' : 'Quotation created.');
            redirect('quotation_view.php?id=' . $qid);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Quotation save failed: ' . $ex->getMessage());
            $error = 'Could not save the quotation. Try again.';
        }
    }
}

$jsFlags    = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$jsProducts = json_encode($products, $jsFlags);
$jsRows     = json_encode($rows, $jsFlags);

$page  = 'quotations';
$title = $q ? 'Edit quotation' : 'New quotation';
include '../includes/header.php';
page_head($title, $q ? sprintf('Q-%04d', $q['quotation_no']) : 'Fill in the details and add the items you are quoting.',
    '<a href="quotations.php" class="' . BTN2 . '">Back to quotations</a>');
?>

<?php if ($existingInvoiceNo): ?>
  <div class="mb-5 rounded-xl bg-sky-50 border border-sky-200 text-sky-900 text-sm font-semibold px-4 py-3" role="status">
    Invoice <?= e(sprintf('I-%04d', $existingInvoiceNo)) ?> was already generated from this quotation. It keeps the items and amounts it was issued with, so changes you save here will not change that invoice.
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold px-4 py-3" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid gap-8 2xl:grid-cols-2 items-start">
<form method="post" id="qform" class="space-y-6 min-w-0" novalidate>
  <?= csrf_field() ?>

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <h2 class="font-bold text-brand-900 mb-4">Quotation for</h2>
    <div class="grid md:grid-cols-3 gap-4">
      <div class="md:col-span-2 space-y-4">
        <div>
          <label for="recipient_name" class="block text-sm font-semibold mb-1">Recipient name</label>
          <input id="recipient_name" name="recipient_name" required value="<?= e($form['recipient_name']) ?>" placeholder="Customer or company name" class="<?= INPUT ?>">
        </div>
        <div>
          <label for="recipient_address" class="block text-sm font-semibold mb-1">Address</label>
          <textarea id="recipient_address" name="recipient_address" rows="3" placeholder="Street&#10;City" class="<?= INPUT ?>"><?= e($form['recipient_address']) ?></textarea>
        </div>
        <div>
          <label for="recipient_phone" class="block text-sm font-semibold mb-1">Contact no</label>
          <input id="recipient_phone" name="recipient_phone" type="tel" maxlength="30" value="<?= e($form['recipient_phone']) ?>" placeholder="077 123 4567" class="<?= INPUT ?>">
        </div>
      </div>
      <div class="space-y-4">
        <div>
          <label for="quotation_date" class="block text-sm font-semibold mb-1">Date</label>
          <input id="quotation_date" name="quotation_date" type="date" required value="<?= e($form['quotation_date']) ?>" class="<?= INPUT ?>">
        </div>
        <div>
          <label for="status" class="block text-sm font-semibold mb-1">Status</label>
          <select id="status" name="status" class="<?= INPUT ?>">
            <?php foreach (QUOTATION_STATUSES as $s): ?>
              <option value="<?= $s ?>" <?= $form['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <div class="flex items-start justify-between gap-3 mb-4">
      <div class="min-w-0">
        <h2 class="font-bold text-brand-900">Items</h2>
        <p class="text-sm text-slate-500">Tap the arrow in the Item box to pick a saved item, or type to search or add a new one.</p>
      </div>
      <a href="items.php" target="_blank" class="text-sm font-semibold text-brand-700 hover:underline shrink-0">Manage items</a>
    </div>

    <div class="ir-wrap">
      <div class="ir-head text-xs font-semibold text-slate-500">
        <div>Item</div>
        <div>Unit price</div>
        <div>Qty</div>
        <div>Unit</div>
        <div class="text-right">Amount</div>
        <div></div>
      </div>
      <div id="rows" class="space-y-3"></div>
    </div>

    <!-- Add item sits under the last row: each new item appears right above this button -->
    <div class="mt-3">
      <button type="button" id="addRow" class="<?= BTN2 ?> w-full sm:w-auto"><?= icon('plus', 'w-4 h-4') ?>Add item</button>
    </div>

    <p class="mt-4 text-sm text-slate-500">Items you type by hand are used on this quotation only. They are not added to Products &amp; services.</p>

    <div class="mt-6 grid gap-5 md:grid-cols-2 items-start">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="discount_percent" class="block text-sm font-semibold mb-1">Discount (%)</label>
          <input id="discount_percent" name="discount_percent" type="number" inputmode="decimal" step="0.01" min="0" max="100" value="<?= e(pct($form['discount_percent'])) ?>" placeholder="0" class="<?= INPUT ?>">
        </div>
        <div>
          <label for="vat_percent" class="block text-sm font-semibold mb-1">VAT (%)</label>
          <input id="vat_percent" name="vat_percent" type="number" inputmode="decimal" step="0.01" min="0" max="100" value="<?= e(pct($form['vat_percent'])) ?>" placeholder="0" class="<?= INPUT ?>">
        </div>
        <p class="col-span-2 -mt-1 text-xs text-slate-500">VAT is charged on the amount left after the discount. Leave a box at 0 to hide that line on the document.</p>
      </div>

      <div class="rounded-xl border border-slate-200 px-5 py-4">
        <dl class="text-sm">
          <div class="flex items-center justify-between py-2 border-b border-slate-100"><dt class="text-slate-500">Subtotal</dt><dd class="font-bold">Rs. <span id="sub">0.00</span></dd></div>
          <div class="flex items-center justify-between py-2 border-b border-slate-100"><dt class="text-slate-500">Discount (<span id="dpLbl">0</span>%)</dt><dd class="font-bold">- Rs. <span id="disc">0.00</span></dd></div>
          <div class="flex items-center justify-between py-2 border-b border-slate-100"><dt class="text-slate-500">VAT (<span id="vpLbl">0</span>%)</dt><dd class="font-bold">Rs. <span id="vat">0.00</span></dd></div>
        </dl>
        <p class="mt-4 text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Total</p>
        <p class="text-right text-3xl font-extrabold text-brand-900">Rs. <span id="grand">0.00</span></p>
      </div>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <label for="special_notice" class="block text-sm font-semibold mb-1">Notice (shown in red on the quotation)</label>
    <textarea id="special_notice" name="special_notice" rows="4" placeholder="Payment terms, validity, delivery notes" class="<?= INPUT ?>"><?= e($form['special_notice']) ?></textarea>
  </section>

  <div class="flex flex-wrap gap-3">
    <button type="submit" class="<?= BTN ?> px-6 py-3">Save quotation</button>
    <a href="quotations.php" class="<?= BTN2 ?> px-6 py-3">Cancel</a>
  </div>
</form>

<!-- Live preview: the real quotation layout, updated as you type -->
<aside id="preview-panel" class="min-w-0 2xl:sticky 2xl:top-6">
  <div class="flex items-center justify-between gap-3 mb-3">
    <h2 class="font-bold text-brand-900">Live preview</h2>
    <span id="pvState" class="text-xs text-slate-500"></span>
  </div>
  <div id="preview" class="2xl:max-h-[calc(100vh-6rem)] 2xl:overflow-auto rounded-xl">
    <p class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Loading preview&hellip;</p>
  </div>
</aside>
</div>

<a href="#preview-panel" class="2xl:hidden no-print fixed bottom-5 right-5 z-30 rounded-full bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-5 py-3 shadow-lg">Preview</a>

<script>
window.QUOTATION_FORM = {
  products: <?= $jsProducts ?>,
  initial:  <?= $jsRows ?>,
  INPUT:    <?= json_encode(INPUT) ?>,
  UNITS:    <?= json_encode(UNITS, $jsFlags) ?>,
  WHOLE:    <?= json_encode(WHOLE_UNITS, $jsFlags) ?>,
  QID:      <?= (int) $id ?>
};
</script>
<script src="../assets/js/quotation_form.js?v=<?= @filemtime(__DIR__ . '/../assets/js/quotation_form.js') ?: 1 ?>"></script>

<?php include '../includes/footer.php'; ?>
