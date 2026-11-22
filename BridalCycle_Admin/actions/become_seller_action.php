<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ProductController.php';

require_login();
require_post();
verify_csrf();

if (($_SESSION['user_role'] ?? '') !== 'buyer') {
    redirect('views/seller/seller_product.php');
}

$shop = trim(strip_tags((string) ($_POST['shop_name'] ?? '')));
if (mb_strlen($shop) > 120) {
    $_SESSION['error'] = msg('reg.err_shop');
    redirect('views/seller/seller_product.php');
}

(new ProductController())->becomeSeller((int) $_SESSION['user_id'], $shop !== '' ? $shop : null);

session_regenerate_id(true);
$_SESSION['user_role'] = 'seller';
$_SESSION['success'] = msg('prod.now_seller');
redirect('views/seller/seller_product.php?new=1');
