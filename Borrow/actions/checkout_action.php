<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/OrderController.php';

require_buyer();
require_post();
verify_csrf();

[$data, $errors] = OrderController::validateCheckout($_POST);
if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/checkout.php');
}

$result = (new OrderController())->createFromBag((int) $_SESSION['user_id'], $data);
if (!$result['success']) {
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg($result['error'], $result['values'] ?? []);
    redirect('views/cart.php');
}

$_SESSION['success'] = msg('order.created', ['ref' => $result['ref']]);
redirect('views/account/my_orders.php');
