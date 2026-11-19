<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_admin();

// Password box with a show / hide eye: crossed eye = hidden, open eye = visible (wired in assets/js/app.js)
function pw_field(string $name, string $placeholder = '', string $id = ''): string {
    return '<div class="relative">'
         . '<input' . ($id !== '' ? ' id="' . $id . '"' : '') . ' name="' . $name . '" type="password" minlength="6" required autocomplete="new-password" placeholder="' . e($placeholder) . '" class="' . INPUT . ' pr-12">'
         . '<button type="button" data-pw-toggle aria-label="Show password" class="absolute inset-y-0 right-0 px-3 text-slate-500 hover:text-brand-700">'
         . '<span data-eye-off>' . icon('eye_off', 'w-5 h-5') . '</span><span data-eye-on class="hidden">' . icon('eye', 'w-5 h-5') . '</span></button>'
         . '</div>';
}

$cid = (int) $_SESSION['company_id'];
$me  = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_csrf_or_die();
    $action = $_POST['action'] ?? '';
    $uid    = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $type  = ($_POST['user_type'] ?? '') === 'admin' ? 'admin' : 'staff';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
            flash('error', 'Enter a name, a valid email, and a password of at least 6 characters.');
        } elseif ($pass !== ($_POST['password_confirm'] ?? '')) {
            flash('error', 'The two passwords do not match.');
        } else {
            try {
                $pdo->prepare('INSERT INTO `user` (company_id, name, email, password, user_type) VALUES (?,?,?,?,?)')
                    ->execute([$cid, $name, $email, password_hash($pass, PASSWORD_DEFAULT), $type]);
                flash('success', 'User added.');
            } catch (PDOException $ex) {
                flash('error', 'That email is already registered.');
            }
        }
    } elseif ($action === 'edit' && $uid) {
        // Admin can change anyone's name / email / role, including their own (own role is locked so the company never loses its last admin)
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $type  = ($_POST['user_type'] ?? '') === 'admin' ? 'admin' : 'staff';
        if ($uid === $me) {
            $type = 'admin';
        }
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a name and a valid email.');
        } else {
            try {
                $pdo->prepare('UPDATE `user` SET name = ?, email = ?, user_type = ? WHERE user_id = ? AND company_id = ?')
                    ->execute([$name, $email, $type, $uid, $cid]);
                flash('success', 'User details updated.');
            } catch (PDOException $ex) {
                flash('error', 'That email is already registered.');
            }
        }
    } elseif ($action === 'reset' && $uid) {
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) {
            flash('error', 'Password must be at least 6 characters.');
        } elseif ($pass !== ($_POST['password_confirm'] ?? '')) {
            flash('error', 'The two passwords do not match.');
        } else {
            $pdo->prepare('UPDATE `user` SET password = ? WHERE user_id = ? AND company_id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $uid, $cid]);
            flash('success', 'Password reset.');
        }
    } elseif (($action === 'deactivate' || $action === 'activate') && $uid && $uid !== $me) {
        // Users are never deleted: their quotations and invoices must stay. A deactivated user simply cannot sign in.
        $on = $action === 'activate' ? 1 : 0;
        $pdo->prepare('UPDATE `user` SET is_active = ? WHERE user_id = ? AND company_id = ?')->execute([$on, $uid, $cid]);
        flash('success', $on ? 'User activated. They can sign in again.' : 'User deactivated. They can no longer sign in. Their quotations and invoices are kept.');
    }
    redirect('users.php');
}

$stmt = $pdo->prepare('SELECT user_id, name, email, user_type, is_active FROM `user` WHERE company_id = ? ORDER BY is_active DESC, name');
$stmt->execute([$cid]);
$rows = $stmt->fetchAll();

$page  = 'users';
$title = 'Users';
include '../includes/header.php';
page_head('Users', 'Add staff, reset passwords, and deactivate people who have left. Nothing they created is deleted.');
?>

<div class="grid lg:grid-cols-3 gap-6 items-start">

  <section class="bg-white rounded-2xl border border-slate-200 p-5">
    <h2 class="font-bold text-brand-900 mb-4">Add user</h2>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div>
        <label for="name" class="block text-sm font-semibold mb-1">Name</label>
        <input id="name" name="name" required class="<?= INPUT ?>">
      </div>
      <div>
        <label for="email" class="block text-sm font-semibold mb-1">Email</label>
        <input id="email" name="email" type="email" required class="<?= INPUT ?>">
      </div>
      <div>
        <label for="password" class="block text-sm font-semibold mb-1">Password</label>
        <?= pw_field('password', '', 'password') ?>
      </div>
      <div>
        <label for="password_confirm" class="block text-sm font-semibold mb-1">Confirm password</label>
        <?= pw_field('password_confirm', '', 'password_confirm') ?>
      </div>
      <div>
        <label for="user_type" class="block text-sm font-semibold mb-1">Role</label>
        <select id="user_type" name="user_type" class="<?= INPUT ?>">
          <option value="staff">Staff</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <button class="<?= BTN ?> w-full">Add user</button>
    </form>
  </section>

  <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
    <?php foreach ($rows as $row): ?>
      <div class="p-4 flex flex-col md:flex-row md:items-center gap-3 md:justify-between <?= $row['is_active'] ? '' : 'bg-slate-50' ?>">
        <div class="min-w-0 <?= $row['is_active'] ? '' : 'opacity-60' ?>">
          <p class="font-semibold truncate"><?= e($row['name']) ?>
            <span class="ml-2 text-xs font-bold px-2 py-0.5 rounded-full <?= $row['user_type'] === 'admin' ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-600' ?>"><?= e(ucfirst($row['user_type'])) ?></span>
            <?php if (!$row['is_active']): ?><span class="ml-1 text-xs font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">Deactivated</span><?php endif; ?>
          </p>
          <p class="text-sm text-slate-500 truncate"><?= e($row['email']) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
          <button type="button" data-modal-open="editModal" data-user-id="<?= (int) $row['user_id'] ?>" data-name="<?= e($row['name']) ?>" data-email="<?= e($row['email']) ?>" data-role="<?= e($row['user_type']) ?>" data-self="<?= (int) $row['user_id'] === $me ? '1' : '0' ?>" class="<?= BTN2 ?>">Edit details</button>
          <button type="button" data-modal-open="resetModal" data-user-id="<?= (int) $row['user_id'] ?>" data-name="<?= e($row['name']) ?>" class="<?= BTN2 ?>">Reset password</button>
          <?php if ((int) $row['user_id'] !== $me): ?>
            <?php if ($row['is_active']): ?>
              <form method="post" onsubmit="return confirm('Deactivate this user? They will not be able to sign in. Their quotations and invoices are kept.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                <button class="text-sm font-semibold text-rose-600 hover:underline px-2">Deactivate</button>
              </form>
            <?php else: ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="activate">
                <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                <button class="text-sm font-semibold text-emerald-700 hover:underline px-2">Activate</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<!-- Edit details modal (filled by assets/js/app.js from the button's data- attributes) -->
<dialog id="editModal" class="rounded-2xl p-0 w-[calc(100%-2rem)] max-w-md backdrop:bg-black/40">
  <form method="post" class="p-6 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="user_id" data-fill="user-id">
    <h2 class="font-bold text-brand-900 text-lg">Edit details</h2>
    <div>
      <label class="block text-sm font-semibold mb-1">Name</label>
      <input name="name" required data-fill="name" class="<?= INPUT ?>">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Email</label>
      <input name="email" type="email" required data-fill="email" class="<?= INPUT ?>">
    </div>
    <div data-role-box>
      <label class="block text-sm font-semibold mb-1">Role</label>
      <select name="user_type" data-fill="role" class="<?= INPUT ?>">
        <option value="staff">Staff</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div class="flex justify-end gap-2 pt-2">
      <button type="button" data-modal-close class="<?= BTN2 ?>">Cancel</button>
      <button class="<?= BTN ?>">Save</button>
    </div>
  </form>
</dialog>

<!-- Reset password modal -->
<dialog id="resetModal" class="rounded-2xl p-0 w-[calc(100%-2rem)] max-w-md backdrop:bg-black/40">
  <form method="post" class="p-6 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset">
    <input type="hidden" name="user_id" data-fill="user-id">
    <h2 class="font-bold text-brand-900 text-lg">Reset password</h2>
    <p class="text-sm text-slate-500">New password for <strong data-fill-text="name"></strong></p>
    <?= pw_field('password', 'New password (min 6 characters)') ?>
    <?= pw_field('password_confirm', 'Confirm new password') ?>
    <div class="flex justify-end gap-2 pt-2">
      <button type="button" data-modal-close class="<?= BTN2 ?>">Cancel</button>
      <button class="<?= BTN ?>">Reset</button>
    </div>
  </form>
</dialog>

<?php include '../includes/footer.php'; ?>
