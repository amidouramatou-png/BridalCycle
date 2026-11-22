<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/OrderController.php';

require_login();
require_once dirname(dirname(__DIR__)) . '/controllers/ReceiptController.php';
require_once dirname(dirname(__DIR__)) . '/controllers/ReviewController.php';
$orders = (new OrderController())->getOrders((int) $_SESSION['user_id']);
$myReviews = (new ReviewController())->getMyReviewKeys((int) $_SESSION['user_id']);
$receipts = new ReceiptController();

$page_title = t('order.my_title');
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="wrap seller-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('order.my_script') ?></div>
            <h1><?= t('order.my_title') ?></h1>
        </div>
        <a class="btn btn-line" href="<?= url('views/all_products.php') ?>"><?= t('cart.continue') ?></a>
    </div>

    <?php if (!$orders): ?>
        <div class="empty-card"><p><?= t('order.none') ?></p><a class="btn btn-solid" href="<?= url('views/all_products.php') ?>"><?= t('shop.see_all') ?></a></div>
    <?php endif; ?>

    <?php foreach ($orders as $o): ?>
        <article class="order-card">
            <div class="review-top">
                <div>
                    <span class="product-cat"><?= e($o['order_ref']) ?> · <?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></span>
                    <h2><?= money((int) $o['total']) ?></h2>
                </div>
                <span class="badge badge-o-<?= e($o['status']) ?>"><?= t('order.status_' . $o['status']) ?></span>
            </div>
            <div class="order-items">
                <?php foreach ($o['items'] as $i): ?>
                    <div class="order-item">
                        <?php if ($i['cover']): ?><img src="<?= url('images/products/' . $i['cover']) ?>" alt="" loading="lazy"><?php endif; ?>
                        <div><strong><?= e($i['title']) ?></strong> × <?= (int) $i['quantity'] ?><br>
                            <span class="muted"><?= t('shop.sold_by') ?> <?= e(public_name($i['seller_name'], $i['shop_name'], $i['seller_hidden'])) ?></span></div>
                        <span><?= money((int) $i['unit_price'] * (int) $i['quantity']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="muted"><?= $o['delivery_method'] === 'delivery' ? t('order.delivery') . ': ' . e($o['delivery_address']) : t('order.pickup') ?> · <?= e($o['phone']) ?>
                <?php if ((int) $o['delivery_fee'] > 0): ?> · <?= t('order.delivery_fee') ?> <?= money((int) $o['delivery_fee']) ?><?php endif; ?></p>
            <?php foreach ($receipts->getRefsFor('order', (int) $o['id'], (int) $_SESSION['user_id']) as $rc): ?>
                <a class="mini-btn" href="<?= url('views/receipt.php?ref=' . urlencode($rc['receipt_ref'])) ?>"><?= t('rc.see_' . $rc['kind']) ?></a>
            <?php endforeach; ?>
            <?php
            // Rate each seller who handed over her outfit (trust: like the stars on Uber)
            $rated = [];
            foreach ($o['items'] as $i) {
                $sid = (int) $i['seller_id'];
                if (isset($rated[$sid]) || !in_array($i['payout_status'], ['due', 'paid'], true)) { continue; }
                $rated[$sid] = true;
                $rv_type = 'order'; $rv_id = (int) $o['id']; $rv_seller = $sid;
                $rv_name = public_name($i['seller_name'], $i['shop_name'], $i['seller_hidden']);
                $rv_done = $myReviews['o' . $o['id'] . '-' . $sid] ?? null;
                require dirname(__DIR__) . '/layout/review_form.php';
            }
            ?>
            <?php if ($o['status'] === 'pending_payment'): ?>
                <div class="review-actions">
                    <a class="btn btn-solid btn-small" href="<?= url('views/payment.php?type=order&id=' . (int) $o['id']) ?>"><?= t('rent.pay') ?></a>
                    <form method="post" action="<?= url('actions/cancel_order_action.php') ?>" class="inline-form" data-confirm="<?= t('order.confirm_cancel', ['ref' => $o['order_ref']]) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                        <button type="submit" class="btn btn-line btn-small"><?= t('rent.cancel') ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
