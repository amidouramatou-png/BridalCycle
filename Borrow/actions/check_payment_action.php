<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/PaymentController.php';

require_login();
require_post();
verify_csrf();

$ref = is_string($_POST['ref'] ?? null) && preg_match('/^PAY-\d{6}-[A-F0-9]{8}$/', $_POST['ref']) ? $_POST['ref'] : '';
$payment = $ref !== '' ? (new PaymentController())->check($ref, (int) $_SESSION['user_id']) : null;

if ($payment === null) {
    $_SESSION['error'] = msg('pay.err_not_found');
    redirect('views/account/my_account.php');
}
$page = ['succeeded' => 'payment_success', 'pending' => 'payment_status', 'failed' => 'payment_failed'][$payment['status']];
if ($payment['status'] === 'pending') {
    $_SESSION['error'] = msg('pay.still_pending');
}
redirect('views/' . $page . '.php?ref=' . urlencode($ref));
