<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

require_post();
verify_csrf();

if (is_logged_in()) {
    redirect('index.php');
}

// 1. Clean the inputs (the password is never changed)
$email    = strtolower(trim(strip_tags((string) ($_POST['email'] ?? ''))));
$password = (string) ($_POST['password'] ?? '');

// 2. Simple checks before asking the database
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || $password === '' || strlen($password) > 72) {
    $_SESSION['old_email'] = $email;
    $_SESSION['error'] = msg('login.err_wrong');
    redirect('views/login.php');
}

// 3. Ask the controller
$result = (new UserController())->login($email, $password, get_ip());

if (!$result['success']) {
    $_SESSION['old_email'] = $email;
    $_SESSION['error'] = msg($result['error'], $result['values']);
    redirect('views/login.php');
}

// 4. Success: new session ID
$user = $result['user'];
session_regenerate_id(true);
$_SESSION['user_id']       = (int) $user['id'];
$_SESSION['user_name']     = $user['full_name'];
$_SESSION['user_email']    = $user['email'];
$_SESSION['user_role']     = $user['role'];
$_SESSION['last_activity'] = time();
$_SESSION['success']       = msg('login.success', ['name' => $user['full_name']]);

redirect('index.php');
