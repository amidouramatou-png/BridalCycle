<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/OrderController.php';
require_once dirname(dirname(__DIR__)) . '/controllers/ReceiptController.php';

require_seller();
$sellerId = (int) $_SESSION['user_id'];
$controller = new OrderController();
$tab = in_array($_GET['tab'] ?? '', OrderController::SALES_TABS, true) ? $_GET['tab'] : 'not_due';
$sales = $controller->getSellerSales($sellerId, $tab);
$receipts = new ReceiptController();

$page_title = t('sale.title');
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="wrap seller-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('prod.list_script') ?></div>
            <h1><?= t('sale.title') ?></h1>
            <p class="admin-intro"><?= t('sale.intro', ['pct' => COMMISSION_PERCENT]) ?></p>
        </div>
        <a class="btn btn-line" href="<?= url('views/seller/seller_dashboard.php') ?>"><?= t('seller.back') ?></a>
    </div>

    <nav class="tabs">
        <?php foreach (OrderController::SALES_TABS as $t): ?>
            <a href="<?= url('views/seller/seller_sales.php?tab=' . $t) ?>" class="<?= $tab === $t ? 'active' : '' ?>"><?= t('sale.tab_' . $t) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$sales): ?>
        <div class="empty-card"><p><?= t('sale.none_' . $tab) ?></p></div>
    <?php endif; ?>

    <?php foreach ($sales as $orderId => $sale): $o = $sale['order']; ?>
        <article class="order-card">
            <div class="review-top">
                <div>
                    <span class="product-cat"><?= e($o['order_ref']) ?> · <?= e(date('d/m/Y H:i', strtotime($o['ordered_at']))) ?></span>
                    <h2><?= money($sale['payout']) ?> <span class="muted small-title"><?= t('com.you_receive') ?></span></h2>
                </div>
                <span class="badge badge-po-<?= e($tab) ?>"><?= t('sale.tab_' . $tab) ?></span>
            </div>
            <div class="order-items">
                <?php foreach ($sale['items'] as $i): ?>
                    <div class="order-item">
                        <?php if ($i['cover']): ?><img src="<?= url('images/products/' . $i['cover']) ?>" alt="" loading="lazy"><?php else: ?><span></span><?php endif; ?>
                        <div><strong><?= e($i['title']) ?></strong> × <?= (int) $i['quantity'] ?></div>
                        <span><?= money((int) $i['unit_price'] * (int) $i['quantity']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <dl class="review-facts">
                <div><dt><?= t('rent.bride') ?></dt><dd><?= e(public_name($o['buyer_name'], null, $o['buyer_hidden'], 'buyer')) ?> · <?= e($o['buyer_region']) ?></dd></div>
                <div><dt><?= t('reg.phone') ?></dt><dd><?= e($o['phone']) ?></dd></div>
                <div><dt><?= t('order.how') ?></dt><dd><?= $o['delivery_method'] === 'delivery' ? t('order.delivery') . ': ' . e($o['delivery_address']) : t('order.pickup') ?></dd></div>
                <div><dt><?= t('sale.price') ?></dt><dd><?= money($sale['total']) ?></dd></div>
                <div><dt><?= t('com.commission', ['pct' => $sale['total'] > 0 ? round($sale['commission'] * 100 / $sale['total'], 1) : COMMISSION_PERCENT]) ?></dt><dd>− <?= money($sale['commission']) ?></dd></div>
                <div><dt><?= t('com.you_receive') ?></dt><dd><strong><?= money($sale['payout']) ?></strong></dd></div>
            </dl>
            <div class="review-actions">
                <?php foreach ($receipts->getRefsFor('order', (int) $orderId, $sellerId) as $rc): ?>
                    <a class="mini-btn" href="<?= url('views/receipt.php?ref=' . urlencode($rc['receipt_ref'])) ?>"><?= t('rc.see_' . $rc['kind']) ?></a>
                <?php endforeach; ?>
                <?php if ($tab === 'not_due'): ?>
                    <form method="post" action="<?= url('actions/update_order_action.php') ?>" class="inline-form"
                          data-confirm="<?= t('sale.confirm_handed', ['ref' => $o['order_ref']]) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $orderId ?>">
                        <button type="submit" class="btn btn-solid btn-small"><?= t('sale.mark_handed') ?></button>
                    </form>
                <?php else: ?>
                    <p class="rental-next"><?= t('sale.next_' . $tab) ?></p>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
