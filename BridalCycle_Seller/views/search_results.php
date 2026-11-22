<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ShopController.php';

$shop        = new ShopController();
$filters     = ShopController::readFilters($_GET);
$list_page   = 'views/search_results.php';
$list_script = t('shop.search_script');
$list_title  = $filters['q'] !== '' ? t('shop.search_title', ['q' => $filters['q']]) : t('shop.search_empty');
$list_intro  = t('shop.search_intro');

$page_title = t('shop.search_script');
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/shop_list.php';
require __DIR__ . '/layout/footer.php';
