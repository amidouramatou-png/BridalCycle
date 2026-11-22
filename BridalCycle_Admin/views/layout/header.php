<?php
$page_title = $page_title ?? APP_NAME;

// Number of outfits in the shopping bag (the little gold number on the bag icon)
$cart_count = 0;
$unread_count = 0;
if (is_logged_in() && !is_admin()) {
    require_once dirname(dirname(__DIR__)) . '/controllers/CartController.php';
    $cart_count = (new CartController())->count((int) $_SESSION['user_id']);
    // Number of unread messages (shown next to "Messages")
    require_once dirname(dirname(__DIR__)) . '/controllers/MessageController.php';
    $unread_count = (new MessageController())->countUnread((int) $_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?> | <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= t('meta.description') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>

<!-- Thin top line + language switch -->
<div class="topline">
    <span><?= t('topline') ?></span>
    <nav class="lang-switch" aria-label="Language">
        <?php foreach (LANGUAGES as $code => $name): ?>
            <a href="<?= lang_url($code) ?>" lang="<?= e($code) ?>"
               class="<?= current_lang() === $code ? 'active' : '' ?>" title="<?= e($name) ?>"><?= e(strtoupper($code)) ?></a>
        <?php endforeach; ?>
    </nav>
</div>

<header class="site-header">
    <div class="wrap nav">

        <!-- Left menu -->
        <ul class="nav-left">
            <li><a href="<?= url('views/all_products.php') ?>"><?= t('nav.collections') ?></a></li>
            <li><a href="<?= url('views/borrow.php') ?>"><?= t('nav.borrow') ?></a></li>
            <li><a href="<?= url('views/seller/seller_product.php') ?>"><?= t('nav.sell') ?></a></li>
            <li><a href="<?= url('views/about.php') ?>"><?= t('nav.about') ?></a></li>
        </ul>

        <!-- Calligraphy logo in the centre -->
        <a class="logo" href="<?= url('index.php') ?>">
            <span class="logo-script">BridalCycle</span>
            <span class="logo-sub"><?= t('logo.sub') ?></span>
        </a>

        <!-- Right menu: changes when the user is logged in -->
        <ul class="nav-right">
            <?php if (is_logged_in()): ?>
                <li class="welcome"><?= t('nav.welcome', ['name' => $_SESSION['user_name'] ?? '']) ?></li>

                <?php if (is_admin()): ?>
                    <!-- Only the admin sees this link -->
                    <li><a href="<?= url('views/admin/admin_dashboard.php') ?>"><?= t('nav.admin') ?></a></li>
                <?php elseif (is_seller()): ?>
                    <li><a href="<?= url('views/seller/seller_dashboard.php') ?>"><?= t('nav.boutique') ?></a></li>
                <?php endif; ?>

                <?php if (!is_admin()): ?>
                    <li><a href="<?= url('views/messages.php') ?>"><?= t('msgs.title') ?><?php if (!empty($unread_count)): ?> <span class="count-dot" aria-label="<?= t('msgs.unread', ['n' => $unread_count]) ?>"><?= (int) $unread_count ?></span><?php endif; ?></a></li>
                <?php endif; ?>
                <li><a href="<?= url('views/account/my_account.php') ?>"><?= t('nav.account') ?></a></li>
                <li>
                    <!-- Logout is a small form (not a link) so other websites cannot log you out -->
                    <form method="post" action="<?= url('logout.php') ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="link-button"><?= t('nav.logout') ?></button>
                    </form>
                </li>
            <?php else: ?>
                <li><a href="<?= url('views/login.php') ?>"><?= t('nav.login') ?></a></li>
                <li><a href="<?= url('views/register.php') ?>"><?= t('nav.register') ?></a></li>
            <?php endif; ?>

            <!-- Shopping bag icon -->
            <?php if (!is_logged_in() || is_buyer()): ?>
            <li>
                <a class="bag-link" href="<?= url('views/cart.php') ?>"
                   title="<?= t('nav.bag') ?>" aria-label="<?= t('nav.bag_count', ['count' => $cart_count]) ?>">
                    <svg class="bag-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"
                         fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 8h14l-1.2 12.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8L5 8z"/>
                        <path d="M9 10V6.5a3 3 0 0 1 6 0V10"/>
                    </svg>
                    <?php if ($cart_count > 0): ?>
                        <span class="bag-count"><?= $cart_count > 99 ? '99+' : (int) $cart_count ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Search bar (lab: search box in the header) -->
    <div class="search-bar">
        <form method="get" action="<?= url('views/search_results.php') ?>" class="wrap search-form" role="search">
            <label for="user_query" class="visually-hidden"><?= t('search.label') ?></label>
            <input type="search" id="user_query" name="user_query" maxlength="80"
                   placeholder="<?= t('search.placeholder') ?>"
                   value="<?= e($_GET['user_query'] ?? '') ?>">
            <button type="submit"><?= t('search.button') ?></button>
        </form>
    </div>
</header>

<main>
    <!-- Success / error messages (shown once) -->
    <div class="wrap messages"><?= show_messages() ?></div>
