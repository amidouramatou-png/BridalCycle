<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/CartController.php';

require_buyer();
require_post();
verify_csrf();

$id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id) {
    (new CartController())->remove((int) $_SESSION['user_id'], $id);
    $_SESSION['success'] = msg('cart.removed');
}
redirect('views/cart.php');
