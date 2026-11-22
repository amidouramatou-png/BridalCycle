<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

require_login();
require_post();
verify_csrf();

$errors = (new UserController())->changePassword(
    (int) $_SESSION['user_id'],
    (string) ($_POST['current_password'] ?? ''),
    (string) ($_POST['password'] ?? ''),
    (string) ($_POST['password_confirm'] ?? '')
);

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/account/change_pass.php');
}

session_regenerate_id(true);
$_SESSION['success'] = msg('acc.password_changed');
redirect('views/account/my_account.php');
