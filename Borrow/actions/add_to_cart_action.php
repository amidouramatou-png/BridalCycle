<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/CartController.php';

require_buyer();
require_post();
verify_csrf();

$id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$result = $id ? (new CartController())->add((int) $_SESSION['user_id'], $id)
              : ['success' => false, 'error' => 'cart.err_unavailable'];

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg('cart.added', ['title' => $result['title']])
    : msg($result['error'], $result['values'] ?? []);
redirect('views/cart.php');
