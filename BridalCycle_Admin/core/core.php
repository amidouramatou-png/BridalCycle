<?php
declare(strict_types=1);

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/db_class.php';

date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/error/error.log');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
         . "img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; "
         . "base-uri 'self'; object-src 'none'");
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header_remove('X-Powered-By');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('bc_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Automatic logout after inactivity (the admin sooner)
    $idle = (($_SESSION['user_role'] ?? '') === 'admin') ? ADMIN_IDLE_MINUTES : SESSION_IDLE_MINUTES;
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $idle * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['error'] = 'You were logged out after a period of inactivity.';
    }
    $_SESSION['last_activity'] = time();

    // New session ID every 15 minutes
    $_SESSION['created_at'] ??= time();
    if (time() - $_SESSION['created_at'] > 900) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }

    // A session only works in the browser that created it
    $agent = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (isset($_SESSION['agent']) && !hash_equals($_SESSION['agent'], $agent)) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['agent'] = $agent;

    // An account suspended by the admin
    if (isset($_SESSION['user_id'])) {
        require_once dirname(__DIR__) . '/classes/UserClass.php';
        $me = (new UserClass())->getUserById((int) $_SESSION['user_id']);
        if ($me === null || $me['status'] !== 'active' || $me['role'] !== ($_SESSION['user_role'] ?? '')) {
            $suspended = $me !== null && $me['status'] !== 'active';
            $_SESSION = isset($_SESSION['lang']) ? ['lang' => $_SESSION['lang']] : [];
            session_regenerate_id(true);
            if ($suspended) {
                $_SESSION['error'] = msg('login.err_suspended');
            }
        }
        unset($me, $suspended);
    }
}

// Escape text before showing it in HTML (stops XSS attacks)
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Full link to a file of the project, e.g. url('views/login.php')
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

// Link to a CSS/JS file with its version
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

// Go to another page of OUR site, then stop
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

// Visitor's IP address (for the login-attempts log)
function get_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'admin';
}

// A seller (the admin manages the site and does not sell outfits)
function is_seller(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'seller';
}

// A bride: the only role that buys and borrows
function is_buyer(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'buyer';
}

// Put at the top of the bag, checkout and borrowing pages/actions
function require_buyer(): void
{
    require_login();
    if (!is_buyer()) {
        $_SESSION['error'] = msg(is_admin() ? 'role.admin_no_shop' : 'role.seller_no_shop');
        redirect(is_admin() ? 'views/admin/admin_dashboard.php' : 'views/seller/seller_dashboard.php');
    }
}

// The name OTHER users see
function public_name(?string $fullName, ?string $shopName = null, int|string|null $hidden = 0, string $role = 'seller'): string
{
    if ((int) $hidden === 1) {
        return msg($role === 'seller' ? 'priv.seller' : 'priv.bride');
    }
    return $shopName ?: explode(' ', (string) $fullName)[0];
}

// Put at the very top of pages for logged-in users only
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['error'] ??= msg('msg.login_first');
        redirect('views/login.php');
    }
}

// Put at the very top of EVERY admin page and admin action, before any HTML
function require_admin(): void
{
    if (!is_admin()) {
        error_log('Blocked admin access from ' . get_ip() . ' to ' . ($_SERVER['REQUEST_URI'] ?? ''));
        $_SESSION['error'] = msg('msg.no_access');
        redirect('index.php');
    }
}

function require_seller(): void
{
    require_login();
    if (!is_seller()) {
        $_SESSION['error'] = msg('prod.become_first');
        redirect('views/seller/seller_product.php');
    }
}

// Actions only accept POST forms (never a simple link)
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
}

// CSRF protection: a secret token in every form, checked by every action

function csrf_token(): string
{
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

// Print this inside every <form method="post">
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Call at the top of every action. Stops fake forms sent from other websites
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(419);
        exit('Your session has expired. Please go back, refresh the page and try again.');
    }
}

// Messages shown once on the next page (success / error)

// Show and then delete $_SESSION['success'] and $_SESSION['error']
function show_messages(): string
{
    $html = '';
    foreach (['success', 'error'] as $type) {
        if (!empty($_SESSION[$type])) {
            $html .= '<div class="alert alert-' . $type . '" role="alert">' . e($_SESSION[$type]) . '</div>';
            unset($_SESSION[$type]);
        }
    }
    return $html;
}

// Show prices in XOF: 150000 -> "150 000 XOF"
function money(int|float|null $amount): string
{
    return number_format((float) $amount, 0, ',', ' ') . ' ' . CURRENCY;
}

// The visitor clicked EN or FR: remember the choice in the session
if (isset($_GET['lang']) && is_string($_GET['lang']) && array_key_exists($_GET['lang'], LANGUAGES)) {
    $_SESSION['lang'] = $_GET['lang'];
}

// The current language code: 'en' or 'fr'
function current_lang(): string
{
    return $_SESSION['lang'] ?? DEFAULT_LANG;
}

// Translate a text. Example: t('nav.borrow') -> "Borrow" or "Emprunter"
function t(string $key, array $values = []): string
{
    static $texts = [];
    $lang = current_lang();
    $texts[$lang] ??= require __DIR__ . "/lang/{$lang}.php";
    $texts['en'] ??= require __DIR__ . '/lang/en.php';

    // If a French text is missing, show the English one
    $text = $texts[$lang][$key] ?? $texts['en'][$key] ?? $key;
    $text = e($text);
    foreach ($values as $name => $value) {
        $text = str_replace('{' . $name . '}', e((string) $value), $text);
    }
    return $text;
}

// Link to the SAME page in another language (for the EN | FR buttons)
function lang_url(string $lang): string
{
    $query = $_GET;
    $query['lang'] = $lang;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: url('index.php');
    return e($path . '?' . http_build_query($query));
}

// Same as t() but WITHOUT the HTML escaping
function msg(string $key, array $values = []): string
{
    return html_entity_decode(t($key, $values), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
