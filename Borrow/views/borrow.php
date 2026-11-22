<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ShopController.php';

$shop        = new ShopController();
$filters     = ShopController::readFilters($_GET, 'borrow');
$list_page   = 'views/borrow.php';
$list_script = t('shop.borrow_script');
$list_title  = t('shop.borrow_title');
$list_intro  = t('shop.borrow_intro', ['min' => RENT_MIN_DAYS, 'max' => RENT_MAX_DAYS]);
$hide_mode   = true;

$page_title = t('shop.borrow_title');
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/shop_list.php';
?>

<!-- Borrowing policy (link "Borrowing policy" in the footer -> borrow.php#policy) -->
<section class="wrap policy" id="policy">
    <div class="section-script"><?= t('pol.script') ?></div>
    <h2><?= t('pol.title') ?></h2>
    <ol class="policy-steps">
        <li><strong><?= t('pol.1_title') ?></strong> <?= t('pol.1_text', ['notice' => RENT_MIN_NOTICE_DAYS, 'min' => RENT_MIN_DAYS, 'max' => RENT_MAX_DAYS]) ?></li>
        <li><strong><?= t('pol.2_title') ?></strong> <?= t('pol.2_text') ?></li>
        <li><strong><?= t('pol.3_title') ?></strong> <?= t('pol.3_text') ?></li>
        <li><strong><?= t('pol.4_title') ?></strong> <?= t('pol.4_text') ?></li>
        <li><strong><?= t('pol.5_title') ?></strong> <?= t('pol.5_text') ?></li>
        <li><strong><?= t('pol.6_title') ?></strong> <?= t('pol.6_text', ['pct' => COMMISSION_PERCENT]) ?></li>
    </ol>
</section>

<?php
require __DIR__ . '/layout/footer.php';
