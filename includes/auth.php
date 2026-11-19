<?php
// Session, sign-in, access rules (who can change what), CSRF token and flash messages.
// Every page loads this file first. It also loads includes/ui.php and includes/helpers.php.

ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = isset($_COOKIE['remember_me']) ? 60 * 60 * 24 * 30 : 0;
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// '' when the page is in the project root (index.php), '../' when it is inside pages/
define('BASE', realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__)) === realpath(__DIR__ . '/..') ? '' : '../');

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/helpers.php';

// ---- Redirect + "where to go after login" ----
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

const NEXT_PAGES = ['dashboard.php', 'quotation_form.php', 'quotations.php', 'invoices.php', 'items.php', 'company.php', 'users.php'];

function next_page(): string {
    $n = $_GET['next'] ?? '';
    return in_array($n, NEXT_PAGES, true) ? $n : 'dashboard.php';
}

function next_query(): string {
    $n = $_GET['next'] ?? '';
    return in_array($n, NEXT_PAGES, true) ? '?next=' . urlencode($n) : '';
}

// ---- Sign in ----
// Called by Login and Sign up once the person is verified
function sign_in(int $userId, string $name, string $userType, int $companyId): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $userId;
    $_SESSION['name']       = $name;
    $_SESSION['user_type']  = $userType;
    $_SESSION['company_id'] = $companyId;
}

function remember_login(): void {
    $expires = time() + 60 * 60 * 24 * 30;
    setcookie('remember_me', '1', ['expires' => $expires, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    setcookie(session_name(), session_id(), ['expires' => $expires, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}

// ---- Checks at the top of every page ----
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        $here = basename($_SERVER['PHP_SELF'] ?? '');
        redirect('login.php' . (in_array($here, NEXT_PAGES, true) ? '?next=' . urlencode($here) : ''));
    }
    refresh_session_user();
}

// SECURITY: the role is saved in the session when someone logs in. If an admin later deletes
// that user or changes their role, the old session would still work. So on every page we
// re-read the user from the database: deleted user -> logged out, changed role -> new role applies.
function refresh_session_user(): void {
    global $pdo;
    $s = $pdo->prepare('SELECT name, user_type, company_id, is_active FROM `user` WHERE user_id = ?');
    $s->execute([(int) $_SESSION['user_id']]);
    $u = $s->fetch();

    // A deactivated user is signed out on their very next click (their data is kept)
    if (!$u || !(int) $u['is_active']) {
        unset($_SESSION['user_id'], $_SESSION['name'], $_SESSION['user_type'], $_SESSION['company_id']);
        flash('error', $u ? 'Your account has been deactivated. Please contact your company admin.' : 'Your account is no longer active. Please sign in again.');
        redirect('login.php');
    }
    $_SESSION['name']       = $u['name'];
    $_SESSION['user_type']  = $u['user_type'];
    $_SESSION['company_id'] = $u['company_id'];
}

function is_admin(): bool {
    return ($_SESSION['user_type'] ?? '') === 'admin';
}

function require_admin(): void {
    require_login();
    if (($_SESSION['user_type'] ?? '') !== 'admin') {
        // Staff go straight to the dashboard (no message)
        redirect('dashboard.php');
    }
}

// ---- Who can change what ----
// Rule: ADMIN can change everything. STAFF can only change what THEY created.
// Products & services: staff can only add (checked in pages/items.php).

// Admins can change any quotation; staff can only change the ones they created themselves
function can_modify_quotation(array $q): bool {
    if (($_SESSION['user_type'] ?? '') === 'admin') {
        return true;
    }
    return (int) $q['user_id'] === (int) ($_SESSION['user_id'] ?? 0);
}

// An invoice belongs to the person who created its quotation.
// $quotationOwnerId = quotation.user_id. Admins can change any invoice.
function can_modify_invoice(int $quotationOwnerId): bool {
    return is_admin() || $quotationOwnerId === (int) ($_SESSION['user_id'] ?? 0);
}

// ---- CSRF token (every POST form carries one) ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(string $t): bool {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function post_csrf_or_die(): void {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
        redirect('dashboard.php');
    }
}

// ---- Flash message (shown once on the next page) ----
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function take_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
