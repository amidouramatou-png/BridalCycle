<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ProductController.php';

require_seller();
require_post();
verify_csrf();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$result = $id ? (new ProductController())->removeProduct($id, (int) $_SESSION['user_id'])
              : ['success' => false, 'error' => 'prod.err_not_found'];

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg($result['key'], ['title' => $result['title']])
    : msg($result['error']);
redirect('views/seller/seller_product.php');
