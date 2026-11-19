<?php
require_once '../includes/auth.php';
$_SESSION = [];
session_destroy();
setcookie('remember_me', '', time() - 3600, '/');
setcookie(session_name(), '', time() - 3600, '/');
header('Location: login.php');
exit;
