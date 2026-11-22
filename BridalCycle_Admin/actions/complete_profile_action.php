<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

require_post();
verify_csrf();

if (is_logged_in()) {
    redirect('index.php');
}

// The verified Google identity must be there, and not older than 15 minutes
$google = $_SESSION['google_pending'] ?? null;
if (!is_array($google) || time() - $google['time'] > 900) {
    unset($_SESSION['google_pending']);
    $_SESSION['error'] = msg('google.err_expired');
    redirect('views/login.php');
}

// 1. Clean the inputs
$clean = fn (string $key): string => trim(strip_tags((string) ($_POST[$key] ?? '')));

$data = [
    'full_name' => preg_replace('/\s+/', ' ', $clean('full_name')),
    'phone'     => $clean('phone'),
    'region'    => $clean('region'),
    'city'      => $clean('city'),
    'role'      => $clean('role'),
    'shop_name' => $clean('shop_name'),
];

// 2
$errors = [];

if (!preg_match("/^[\p{L}][\p{L}\s'’.-]{1,119}$/u", $data['full_name'])) {
    $errors['full_name'] = msg('reg.err_name');
}

$phoneDigits = preg_replace('/^(\+227|00227)/', '', preg_replace('/[\s.\-]/', '', $data['phone']));
if (!preg_match('/^\d{8}$/', $phoneDigits)) {
    $errors['phone'] = msg('reg.err_phone');
}
$data['phone'] = $phoneDigits;

if (!in_array($data['region'], UserController::REGIONS, true)) {
    $errors['region'] = msg('reg.err_region');
}
if (mb_strlen($data['city']) > 80) {
    $errors['city'] = msg('reg.err_city');
}
// Role: only bride (buyer) or seller. NEVER admin
if (!in_array($data['role'], ['buyer', 'seller'], true)) {
    $errors['role'] = msg('reg.err_role');
}
if ($data['role'] !== 'seller') {
    $data['shop_name'] = '';
} elseif (mb_strlen($data['shop_name']) > 120) {
    $errors['shop_name'] = msg('reg.err_shop');
}

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/complete_profile.php');
}

// 3. Create the account
$result = (new UserController())->registerWithGoogle($data, $google);
unset($_SESSION['google_pending']);

if (!$result['success']) {
    $_SESSION['error'] = msg($result['error']);
    redirect('views/login.php');
}

// 4. Log her in (Google has already confirmed who she is)
$user = $result['user'];
session_regenerate_id(true);
$_SESSION['user_id']       = (int) $user['id'];
$_SESSION['user_name']     = $user['full_name'];
$_SESSION['user_email']    = $user['email'];
$_SESSION['user_role']     = $user['role'];
$_SESSION['last_activity'] = time();
$_SESSION['success']       = msg('google.welcome', ['name' => $user['full_name']]);

redirect('index.php');
