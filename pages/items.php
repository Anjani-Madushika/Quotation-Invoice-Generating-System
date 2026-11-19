<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();

$cid = (int) $_SESSION['company_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_csrf_or_die();
    $action = $_POST['action'] ?? '';
    $iid    = (int) ($_POST['item_id'] ?? 0);

    // Staff may only ADD new items. Changing a price or deleting is for admins
    // (a price change would affect every new quotation).
    if (!is_admin() && ($action === 'delete' || ($action === 'save' && $iid))) {
        flash('error', 'Only admins can edit or delete products and services.');
        redirect('items.php');
    }

    if ($action === 'save') {
        $name  = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $unit  = clean_unit($_POST['unit'] ?? '');
        if ($name === '' || $price < 0) {
            flash('error', 'Enter a name and a price of 0 or more.');
            redirect($iid ? 'items.php?edit=' . $iid : 'items.php');
        }
        $name = mb_substr(preg_replace('/\s+/u', ' ', $name), 0, 150);

        // The same item name cannot be saved twice (a different row with the same name is a duplicate)
        $dup = $pdo->prepare('SELECT item_id FROM product_service WHERE company_id = ? AND LOWER(TRIM(name)) = LOWER(?) AND item_id <> ? LIMIT 1');
        $dup->execute([$cid, $name, $iid]);
        if ($dup->fetchColumn()) {
            flash('error', '"' . $name . '" is already in the list. The same item cannot be added twice.');
            redirect($iid ? 'items.php?edit=' . $iid : 'items.php');
        }

        if ($iid) {
            $pdo->prepare('UPDATE product_service SET name = ?, price = ?, unit = ? WHERE item_id = ? AND company_id = ?')
                ->execute([$name, $price, $unit, $iid, $cid]);
            flash('success', 'Item updated.');
        } else {
            $pdo->prepare('INSERT INTO product_service (company_id, name, price, unit) VALUES (?,?,?,?)')
                ->execute([$cid, $name, $price, $unit]);
            flash('success', 'Item added.');
        }
    } elseif ($action === 'delete' && $iid) {
        $pdo->prepare('DELETE FROM product_service WHERE item_id = ? AND company_id = ?')->execute([$iid, $cid]);
        flash('success', 'Item deleted.');
    }
    redirect('items.php');
}

$edit = null;
if (isset($_GET['edit']) && is_admin()) {
    $s = $pdo->prepare('SELECT * FROM product_service WHERE item_id = ? AND company_id = ?');
    $s->execute([(int) $_GET['edit'], $cid]);
    $edit = $s->fetch() ?: null;
}

$stmt = $pdo->prepare('SELECT * FROM product_service WHERE company_id = ? ORDER BY name');
$stmt->execute([$cid]);
$rows = $stmt->fetchAll();

$page  = 'items';
$title = 'Products & services';
include '../includes/header.php';
page_head('Products and services', 'Add what you sell once, then pick it when you build a quotation.');
?>

<div class="grid lg:grid-cols-3 gap-6 items-start">

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <h2 class="font-bold text-brand-900 mb-4"><?= $edit ? 'Edit item' : 'Add item' ?></h2>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="item_id" value="<?= $edit ? (int) $edit['item_id'] : 0 ?>">
      <div>
        <label for="name" class="block text-sm font-semibold mb-1">Name</label>
        <input id="name" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="Web design package" class="<?= INPUT ?>">
      </div>
      <div>
        <label for="price" class="block text-sm font-semibold mb-1">Price (Rs.)</label>
        <input id="price" name="price" type="number" step="0.01" min="0" required value="<?= e(isset($edit['price']) ? (string) $edit['price'] : '') ?>" placeholder="0.00" class="<?= INPUT ?>">
      </div>
      <div>
        <label for="unit" class="block text-sm font-semibold mb-1">Sold per (unit)</label>
        <select id="unit" name="unit" class="<?= INPUT ?>">
          <?php foreach (UNITS as $uk => $ul): ?>
            <option value="<?= e($uk) ?>" <?= ($edit['unit'] ?? '') === $uk ? 'selected' : '' ?>><?= e($ul) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-slate-500">Example: price 450 and unit kg means Rs. 450 per 1 kg. On a quotation you can then type 2.5 kg or 250 g.</p>
      </div>
      <div class="flex gap-2">
        <button class="<?= BTN ?> flex-1"><?= $edit ? 'Save changes' : 'Add item' ?></button>
        <?php if ($edit): ?><a href="items.php" class="<?= BTN2 ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200">
    <?php if (!$rows): ?>
      <div class="p-10 text-center text-slate-600">No items yet. Add your first product or service.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="resp-table w-full text-sm">
          <thead class="text-left text-slate-500 bg-slate-50">
            <tr>
              <th class="px-5 py-3 font-semibold">Name</th>
              <th class="px-5 py-3 font-semibold text-right whitespace-nowrap">Price (Rs.)</th>
              <th class="px-5 py-3 font-semibold text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $row): ?>
              <tr class="hover:bg-brand-50/50">
                <td data-label="Name" class="px-5 py-3 font-semibold"><?= e($row['name']) ?></td>
                <td data-label="Price (Rs.)" class="px-5 py-3 text-right whitespace-nowrap"><?= money($row['price']) ?><?= ($row['unit'] ?? '') !== '' ? ' <span class="text-slate-500">/ ' . e(UNITS[$row['unit']] ?? $row['unit']) . '</span>' : '' ?></td>
                <td data-label="" class="cell-actions px-5 py-3">
                  <div class="flex items-center justify-end gap-3 whitespace-nowrap">
                    <?php if (is_admin()): ?>
                    <a class="font-semibold text-brand-700 hover:underline" href="items.php?edit=<?= (int) $row['item_id'] ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this item?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="item_id" value="<?= (int) $row['item_id'] ?>">
                      <button class="font-semibold text-rose-600 hover:underline">Delete</button>
                    </form>
                    <?php else: ?>
                    <span class="text-xs text-slate-400">Admin only</span>
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
</div>

<?php include '../includes/footer.php'; ?>
