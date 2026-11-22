<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/ProductController.php';
require_once dirname(dirname(__DIR__)) . '/controllers/RentalController.php';
require_once dirname(dirname(__DIR__)) . '/controllers/OrderController.php';

require_seller();
$sellerId = (int) $_SESSION['user_id'];
$outfits  = (new ProductController())->getSellerProducts($sellerId);
$waiting  = (new RentalController())->countSellerRequests($sellerId);
$toHand   = (new OrderController())->countSellerToHand($sellerId);
$count = fn (string $status): int => count(array_filter($outfits, fn ($o) => $o['status'] === $status));
$views = array_sum(array_map(fn ($o) => (int) $o['view_count'], $outfits));

$page_title = t('nav.boutique');
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="wrap seller-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('prod.list_script') ?></div>
            <h1><?= t('nav.boutique') ?></h1>
            <p class="admin-intro"><?= t('seller.intro') ?></p>
        </div>
        <a class="btn btn-solid" href="<?= url('views/seller/seller_product.php?new=1') ?>"><?= t('prod.add_button') ?></a>
    </div>

    <div class="stat-row">
        <div class="stat"><strong><?= $count('approved') ?></strong><span><?= t('prod.status_approved') ?></span></div>
        <div class="stat"><strong><?= $count('pending') ?></strong><span><?= t('prod.status_pending') ?></span></div>
        <div class="stat"><strong><?= $count('rejected') ?></strong><span><?= t('prod.status_rejected') ?></span></div>
        <div class="stat"><strong><?= $views ?></strong><span><?= t('views.total') ?></span></div>
        <div class="stat stat-accent"><strong><?= $waiting + $toHand ?></strong><span><?= t('seller.to_do') ?></span></div>
    </div>

    <div class="hub-grid">
        <a class="hub-card" href="<?= url('views/seller/seller_product.php') ?>">
            <span class="hub-icon">❦</span>
            <h3><?= t('prod.my_outfits') ?></h3>
            <p><?= t('seller.outfits_text') ?></p>
        </a>
        <a class="hub-card" href="<?= url('views/seller/seller_rentals.php') ?>">
            <span class="hub-icon">◷</span>
            <h3><?= t('rent.seller_title') ?><?= $waiting ? ' (' . $waiting . ')' : '' ?></h3>
            <p><?= t('seller.rentals_text') ?></p>
        </a>
        <a class="hub-card" href="<?= url('views/seller/seller_sales.php') ?>">
            <span class="hub-icon">✦</span>
            <h3><?= t('sale.title') ?><?= $toHand ? ' (' . $toHand . ')' : '' ?></h3>
            <p><?= t('seller.sales_text') ?></p>
        </a>
    </div>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
