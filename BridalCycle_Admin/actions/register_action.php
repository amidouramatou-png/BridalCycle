<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

require_post();
verify_csrf();

if (is_logged_in()) {
    redirect('index.php');
}

// Anti-robot trap: this field is hidden from humans. Robots fill every field
if (!empty($_POST['website'])) {
    redirect('index.php');
}

// 1. Clean the inputs (trim + strip_tags, as in the lab)
$clean = fn (string $key): string => trim(strip_tags((string) ($_POST[$key] ?? '')));

$data = [
    'full_name' => preg_replace('/\s+/', ' ', $clean('full_name')),
    'email'     => strtolower($clean('email')),
    'phone'     => $clean('phone'),
    'region'    => $clean('region'),
    'city'      => $clean('city'),
    'role'      => $clean('role'),
    'shop_name' => $clean('shop_name'),
];
$password = (string) ($_POST['password'] ?? '');
$confirm  = (string) ($_POST['password_confirm'] ?? '');

// 2. Check every field (the same rules as js/validate.js)
$errors = [];

// Name: 2 to 120 letters (accents allowed), spaces, apostrophes, hyphens
if (!preg_match("/^[\p{L}][\p{L}\s'’.-]{1,119}$/u", $data['full_name'])) {
    $errors['full_name'] = msg('reg.err_name');
}

// Email: valid format and fits the database (VARCHAR 190)
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 190) {
    $errors['email'] = msg('reg.err_email');
}

// Phone: Niger number, 8 digits, optional +227 / 00227 -> saved as 8 digits only
$phoneDigits = preg_replace('/^(\+227|00227)/', '', preg_replace('/[\s.\-]/', '', $data['phone']));
if (!preg_match('/^\d{8}$/', $phoneDigits)) {
    $errors['phone'] = msg('reg.err_phone');
}
$data['phone'] = $phoneDigits;

// Region: must be one of the 8 regions of Niger (from the list, nothing else)
if (!in_array($data['region'], UserController::REGIONS, true)) {
    $errors['region'] = msg('reg.err_region');
}

// City / neighbourhood: optional, up to 80 characters
if (mb_strlen($data['city']) > 80) {
    $errors['city'] = msg('reg.err_city');
}

// Role: only bride (buyer) or seller. NEVER admin
if (!in_array($data['role'], ['buyer', 'seller'], true)) {
    $errors['role'] = msg('reg.err_role');
}

// Boutique name: optional, up to 120 characters, only for sellers
if ($data['role'] !== 'seller') {
    $data['shop_name'] = '';
} elseif (mb_strlen($data['shop_name']) > 120) {
    $errors['shop_name'] = msg('reg.err_shop');
}

// Password: at least PASSWORD_MIN_LENGTH characters
if (!preg_match('/^(?=.*\p{L})(?=.*\d).{' . PASSWORD_MIN_LENGTH . ',}$/u', $password)) {
    $errors['password'] = msg('reg.err_password', ['min' => PASSWORD_MIN_LENGTH]);
} elseif (strlen($password) > 72) {
    $errors['password'] = msg('reg.err_password_long');
}
if ($password !== $confirm) {
    $errors['password_confirm'] = msg('reg.err_confirm');
}

// 3
if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/register.php');
}

// 4. Register through the controller
$data['password'] = $password;
$result = (new UserController())->register($data);

if (!$result['success']) {
    $_SESSION['old'] = $data;
    unset($_SESSION['old']['password']);
    $_SESSION['error'] = msg($result['error']);
    redirect('views/register.php');
}

// 5. Success: send the new user to the LOGIN page
$user = $result['user'];
unset($_SESSION['old'], $_SESSION['form_errors']);
$_SESSION['old_email'] = $user['email'];
$_SESSION['success']   = msg('reg.success', ['name' => $user['full_name']]);

redirect('views/login.php');
