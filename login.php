<?php
// Shortcut: /quotation_system/login.php always opens the real login page.
// It never redirects to the dashboard, even when you are already signed in.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: pages/login.php');
exit;
