<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

require_login();
require_post();
verify_csrf();

[$data, $errors] = UserController::validateProfile($_POST, is_seller());
if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/account/edit_account.php');
}

(new UserController())->updateProfile((int) $_SESSION['user_id'], $data);
$_SESSION['user_name'] = $data['full_name'];
$_SESSION['success'] = msg('acc.saved');
redirect('views/account/my_account.php');
