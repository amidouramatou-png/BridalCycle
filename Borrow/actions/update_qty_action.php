<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/CartController.php';

require_buyer();
require_post();
verify_csrf();

$id  = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$qty = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => CartController::MAX_QTY]]);

if ($id === false || $qty === false) {
    $_SESSION['error'] = msg('cart.err_qty', ['max' => CartController::MAX_QTY]);
    redirect('views/cart.php');
}
$result = (new CartController())->update((int) $_SESSION['user_id'], $id, $qty);
if (!$result['success']) {
    $_SESSION['error'] = msg($result['error'], $result['values'] ?? []);
}
redirect('views/cart.php');
