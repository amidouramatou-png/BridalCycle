<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ShopController.php';

$shop        = new ShopController();
$filters     = ShopController::readFilters($_GET);
$list_page   = 'views/all_products.php';
$list_script = t('shop.all_script');
$list_title  = t('shop.all_title');
$list_intro  = t('shop.all_intro');

$page_title = t('shop.all_title');
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/shop_list.php';
require __DIR__ . '/layout/footer.php';
