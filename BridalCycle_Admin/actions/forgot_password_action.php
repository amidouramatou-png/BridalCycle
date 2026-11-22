<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/PasswordResetController.php';

require_post();
verify_csrf();

$email = strtolower(trim(strip_tags((string) ($_POST['email'] ?? ''))));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    $_SESSION['error'] = msg('reg.err_email');
    redirect('views/forgot_password.php');
}

try {
    (new PasswordResetController())->requestLink($email);
} catch (Throwable $e) {
    error_log('Password reset request failed: ' . $e->getMessage());
}

$_SESSION['success'] = msg('reset.sent', ['minutes' => PasswordResetController::LINK_MINUTES]);
redirect('views/login.php');
