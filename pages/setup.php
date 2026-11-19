<?php
// Run ONCE: http://localhost/quotation_system/pages/setup.php  -> then DELETE this file.
require_once '../config/db.php';

$email = 'admin@example.com';
$pass  = 'admin123';

$exists = $pdo->prepare('SELECT user_id FROM `user` WHERE email = ?');
$exists->execute([$email]);

if ($exists->fetch()) {
    echo 'Admin already exists. Delete setup.php now.';
    exit;
}

$companyId = $pdo->query('SELECT company_id FROM company ORDER BY company_id LIMIT 1')->fetchColumn();
if (!$companyId) {
    die('Company row not found. Import database/quotation_system.sql first.');
}

$stmt = $pdo->prepare('INSERT INTO `user` (company_id, name, email, password, user_type) VALUES (?,?,?,?,?)');
$stmt->execute([$companyId, 'Admin', $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']);

echo "Admin created.<br>Email: $email<br>Password: $pass<br><b>Delete setup.php now.</b>";
