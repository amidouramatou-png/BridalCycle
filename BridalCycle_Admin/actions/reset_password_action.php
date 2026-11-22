<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/PasswordResetController.php';

require_post();
verify_csrf();

$token = (string) ($_POST['token'] ?? '');
$result = (new PasswordResetController())->resetPassword(
    $token,
    (string) ($_POST['password'] ?? ''),
    (string) ($_POST['password_confirm'] ?? '')
);

if (!empty($result['expired'])) {
    $_SESSION['error'] = msg('reset.err_link');
    redirect('views/forgot_password.php');
}
if (!$result['success']) {
    $_SESSION['form_errors'] = $result['errors'];
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/reset_password.php?token=' . rawurlencode($token));
}

$_SESSION['old_email'] = $result['email'];
$_SESSION['success'] = msg('reset.done');
redirect('views/login.php');
