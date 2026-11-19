<?php
// Database connection. The settings come from the .env file in the project root (see .env.example).
require_once __DIR__ . '/env.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'quotation_system'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', '3306'));

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Check the .env file and that MySQL is running in XAMPP.');
}

// ---- Small schema helpers (used by the upgrade in migrate.php) ----

function table_exists(PDO $pdo, string $table): bool
{
    return (bool) $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetch();
}

// The SHOW COLUMNS row of one column (has 'Type' etc.), or null when the column does not exist
function column_info(PDO $pdo, string $table, string $column): ?array
{
    $row = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column))->fetch();
    return $row ?: null;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    return column_info($pdo, $table, $column) !== null;
}

// ---- Upgrade an older database automatically (once) ----
// company.doc_layout is the newest column the upgrade adds, so when it exists the database is already current.
try {
    if (!column_exists($pdo, 'company', 'doc_layout')) {
        require __DIR__ . '/migrate.php';
        upgrade_old_database($pdo);
    }
} catch (Throwable $e) {
    // Tables not imported yet, or no permission: import database/quotation_system.sql
}
