<?php
// Shared frame for Login, Sign up and Forgot password.
// Left: dark blue gradient brand panel.  Right: soft blue gradient with the form card.
// Usage:  auth_open('Login', 'Sign in', 'Subtitle');  ...form html...  auth_close();

function auth_open(string $pageTitle, string $heading, string $sub = ''): void {
    $title = $pageTitle;
    ?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/head.php'; ?></head>
<body class="font-inter bg-white h-[100dvh] overflow-hidden">
<div class="h-[100dvh] overflow-hidden grid lg:grid-cols-2 grid-rows-[auto_minmax(0,1fr)] lg:grid-rows-1">

  <?php include __DIR__ . '/auth_panel.php'; ?>

  <main class="auth-soft no-scrollbar flex overflow-y-auto min-h-0 px-5 py-5 sm:px-10">
    <div class="m-auto w-full max-w-md bg-white rounded-3xl border border-white shadow-xl shadow-brand-900/10 p-6 sm:p-8">
      <h2 class="text-3xl font-extrabold text-brand-900"><?= e($heading) ?></h2>
      <?php if ($sub !== ''): ?><p class="text-slate-500 mt-1.5 mb-5"><?= e($sub) ?></p><?php endif; ?>
<?php
}

function auth_close(): void {
    ?>
    </div>
  </main>
</div>
<script src="<?= BASE ?>assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?: 1 ?>"></script>
</body>
</html>
<?php
}
