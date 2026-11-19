<?php
require_once '../includes/auth.php';
require_once '../includes/auth_layout.php';
auth_open('Forgot password', 'Forgot your password?');
?>

      <p class="text-slate-600 -mt-4 leading-relaxed">Ask your company admin to reset it from the Users page, then sign in with the new password.</p>
      <a href="login.php" class="auth-gradient flex items-center justify-center mt-6 h-11 rounded-xl text-white font-bold shadow-lg shadow-brand-900/25 hover:opacity-95 transition">Back to sign in</a>

<?php auth_close(); ?>
