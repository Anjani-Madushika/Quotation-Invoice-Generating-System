<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/auth_layout.php';

// The login page always opens, even when already signed in (never redirects to the dashboard).
// no-store stops the browser from showing an old cached copy or skipping this page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$error = '';
$email = $_COOKIE['remember_email'] ?? '';   // filled in when "Remember me" was ticked last time

// Message left by another page (e.g. "Your account is no longer active")
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ($f = take_flash())) {
    $error = $f['msg'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM `user` WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (isset($user['is_active']) && !(int) $user['is_active']) {
                $error = 'This account has been deactivated. Please contact your company admin.';
            } else {
                sign_in((int) $user['user_id'], $user['name'], $user['user_type'], (int) $user['company_id']);

                if ($remember) {
                    remember_login();
                    setcookie('remember_email', $email, ['expires' => time() + 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                } else {
                    setcookie('remember_email', '', ['expires' => time() - 3600, 'path' => '/']);
                }

                redirect(next_page());
            }
        } else {
            $error = 'Email or password is incorrect.';
        }
    }
}
?>
<?php auth_open('Login', 'Sign in', 'Enter your email and password to open your account.'); ?>

      <?php if ($error): ?>
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-semibold px-4 py-3" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" class="space-y-4" novalidate autocomplete="on">
        <?= csrf_field() ?>

        <div>
          <label for="email" class="block text-sm font-semibold text-brand-900 mb-1.5">Email address</label>
          <input id="email" name="email" type="email" required <?= $email === '' ? 'autofocus' : '' ?> autocomplete="username" value="<?= e($email) ?>"
                 class="<?= AUTH_INPUT ?>">
        </div>

        <div>
          <label for="password" class="block text-sm font-semibold text-brand-900 mb-1.5">Password</label>
          <div class="relative">
            <input id="password" name="password" type="password" required <?= $email !== '' ? 'autofocus' : '' ?> autocomplete="current-password"
                   class="w-full h-11 rounded-xl border border-slate-300 bg-white pl-4 pr-12 text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
            <button type="button" id="togglePw" aria-label="Show password"
                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-brand-900">
              <svg id="eyeOpen" class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="eyeClosed" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 6.1A10.7 10.7 0 0 1 12 6c6.4 0 10 6 10 6a17 17 0 0 1-3.3 3.9M6.6 6.6A17 17 0 0 0 2 12s3.6 6 10 6c1.7 0 3.2-.4 4.5-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between text-sm">
          <label class="flex items-center gap-2 cursor-pointer text-slate-700 font-medium">
            <input type="checkbox" name="remember" <?= isset($_COOKIE['remember_email']) ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-brand-900 focus:ring-brand-500">
            Remember me
          </label>
          <a href="forgot_password.php" class="font-semibold text-brand-600 hover:text-brand-900">Forgot password?</a>
        </div>

        <button type="submit" class="auth-gradient w-full h-11 rounded-xl text-white font-bold flex items-center justify-center gap-2 shadow-lg shadow-brand-900/25 hover:opacity-95 transition">
          Sign in
        </button>
      </form>

      <p class="text-center text-slate-600 mt-6 pt-5 border-t border-slate-200 text-sm">
        Don't have an account?
        <a href="register.php<?= next_query() ?>" class="font-bold text-brand-600 hover:text-brand-900">Sign up</a>
      </p>

<?php auth_close(); ?>
