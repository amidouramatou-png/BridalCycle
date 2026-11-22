<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/UserController.php';

require_login();
$user = (new UserController())->getUser((int) $_SESSION['user_id']);

$page_title = t('acc.title');
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="wrap seller-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('acc.script') ?></div>
            <h1><?= t('acc.hello', ['name' => explode(' ', $user['full_name'])[0]]) ?></h1>
            <p class="admin-intro"><?= e($user['email']) ?> · <?= e($user['phone']) ?> · <?= e($user['region']) ?><?= $user['city'] ? ', ' . e($user['city']) : '' ?></p>
        </div>
    </div>

    <div class="hub-grid">
        <?php if (is_buyer()): ?>
        <a class="hub-card" href="<?= url('views/account/my_orders.php') ?>">
            <span class="hub-icon">◈</span>
            <h3><?= t('order.my_title') ?></h3>
            <p><?= t('acc.orders_text') ?></p>
        </a>
        <a class="hub-card" href="<?= url('views/account/my_rentals.php') ?>">
            <span class="hub-icon">◷</span>
            <h3><?= t('rent.my_title') ?></h3>
            <p><?= t('acc.rentals_text') ?></p>
        </a>
        <?php endif; ?>
        <?php if (is_admin()): ?>
            <a class="hub-card" href="<?= url('views/admin/admin_dashboard.php') ?>">
                <span class="hub-icon">✦</span>
                <h3><?= t('admin.title') ?></h3>
                <p><?= t('acc.admin_text') ?></p>
            </a>
        <?php elseif (is_seller()): ?>
            <a class="hub-card" href="<?= url('views/seller/seller_dashboard.php') ?>">
                <span class="hub-icon">✦</span>
                <h3><?= t('nav.boutique') ?></h3>
                <p><?= t('acc.boutique_text') ?></p>
            </a>
        <?php else: ?>
            <a class="hub-card" href="<?= url('views/seller/seller_product.php') ?>">
                <span class="hub-icon">✦</span>
                <h3><?= t('prod.become_title') ?></h3>
                <p><?= t('acc.open_text') ?></p>
            </a>
        <?php endif; ?>
        <?php if (!is_admin()): ?>
            <a class="hub-card" href="<?= url('views/messages.php') ?>">
                <span class="hub-icon">✉</span>
                <h3><?= t('msgs.title') ?></h3>
                <p><?= t('acc.messages_text') ?></p>
            </a>
        <?php endif; ?>
        <a class="hub-card" href="<?= url('views/all_products.php') ?>">
            <span class="hub-icon">❦</span>
            <h3><?= t('shop.all_title') ?></h3>
            <p><?= t('acc.shop_text') ?></p>
        </a>
    </div>

    <!-- My details, password, delete -->
    <div class="account-links">
        <a class="mini-btn" href="<?= url('views/account/edit_account.php') ?>"><?= t('acc.edit_title') ?></a>
        <a class="mini-btn" href="<?= url('views/account/change_pass.php') ?>"><?= t('acc.pass_title') ?></a>
        <?php if (!is_admin()): ?>
            <a class="mini-btn mini-danger" href="<?= url('views/account/delete_account.php') ?>"><?= t('acc.delete_title') ?></a>
        <?php endif; ?>
    </div>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
