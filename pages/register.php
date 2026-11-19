<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/auth_layout.php';

$error = '';
$old = ['company_name' => '', 'name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['company_name'] = trim($_POST['company_name'] ?? '');
    $old['name']         = trim($_POST['name'] ?? '');
    $old['email']        = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } elseif ($old['company_name'] === '' || $old['name'] === '' || $old['email'] === '') {
        $error = 'Fill in all the fields.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $check = $pdo->prepare('SELECT 1 FROM `user` WHERE email = ?');
        $check->execute([$old['email']]);

        if ($check->fetch()) {
            $error = 'This email is already registered. Sign in instead.';
        } else {
            try {
                $pdo->beginTransaction();

                $pdo->prepare('INSERT INTO company (company_name) VALUES (?)')
                    ->execute([$old['company_name']]);
                $companyId = (int) $pdo->lastInsertId();

                $pdo->prepare('INSERT INTO `user` (company_id, name, email, password, user_type) VALUES (?,?,?,?,?)')
                    ->execute([$companyId, $old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), 'admin']);
                $userId = (int) $pdo->lastInsertId();

                $pdo->commit();

                sign_in($userId, $old['name'], 'admin', $companyId);
                redirect(next_page());
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Could not create the account. Try again.';
            }
        }
    }
}

function field(string $id, string $label, string $type, string $value = '', string $auto = 'off'): void { ?>
  <div>
    <label for="<?= $id ?>" class="block text-sm font-semibold text-brand-900 mb-1"><?= $label ?></label>
    <input id="<?= $id ?>" name="<?= $id ?>" type="<?= $type ?>" required <?= $id === 'company_name' ? 'autofocus' : '' ?> autocomplete="<?= $auto ?>"
           value="<?= e($value) ?>"
           class="<?= AUTH_INPUT ?>">
  </div>
<?php }
?>
<?php auth_open('Sign up', 'Create your account', 'Set up your company and start quoting.'); ?>

      <?php if ($error): ?>
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-semibold px-4 py-3" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" class="space-y-3" novalidate>
        <?= csrf_field() ?>
        <?php
        field('company_name', 'Company name', 'text', $old['company_name'], 'organization');
        field('name', 'Your name', 'text', $old['name'], 'name');
        field('email', 'Email address', 'email', $old['email'], 'username');
        field('password', 'Password', 'password', '', 'new-password');
        field('confirm', 'Confirm password', 'password', '', 'new-password');
        ?>
        <button type="submit" class="auth-gradient w-full h-11 mt-1 rounded-xl text-white font-bold shadow-lg shadow-brand-900/25 hover:opacity-95 transition">
          Create account
        </button>
      </form>

      <p class="text-center text-slate-600 mt-5 pt-4 border-t border-slate-200 text-sm">
        Already have an account?
        <a href="login.php<?= next_query() ?>" class="font-bold text-brand-600 hover:text-brand-900">Sign in</a>
      </p>

<?php auth_close(); ?>
