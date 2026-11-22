<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/CartController.php';

require_buyer();
$bag = (new CartController())->getBag((int) $_SESSION['user_id']);

$page_title   = t('cart.title');
$page_scripts = ['js/cart.js'];
require __DIR__ . '/layout/header.php';
?>

<section class="wrap seller-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('cart.script') ?></div>
            <h1><?= t('cart.title') ?></h1>
        </div>
        <a class="btn btn-line" href="<?= url('views/all_products.php') ?>"><?= t('cart.continue') ?></a>
    </div>

    <?php if ($bag['removed']): ?>
        <div class="alert alert-error"><?= t('cart.removed_auto', ['titles' => implode(', ', $bag['removed'])]) ?></div>
    <?php endif; ?>

    <?php if (!$bag['items']): ?>
        <div class="empty-card">
            <p><?= t('cart.empty') ?></p>
            <a class="btn btn-solid" href="<?= url('views/all_products.php') ?>"><?= t('shop.see_all') ?></a>
        </div>
    <?php else: ?>
    <div class="cart-layout">
        <div class="cart-items">
            <?php foreach ($bag['items'] as $item): ?>
                <article class="cart-item">
                    <a href="<?= url('views/single_product.php?id=' . (int) $item['product_id']) ?>" class="cart-photo">
                        <?php if ($item['cover']): ?><img src="<?= url('images/products/' . $item['cover']) ?>" alt="" loading="lazy"><?php endif; ?>
                    </a>
                    <div class="cart-info">
                        <h3><a href="<?= url('views/single_product.php?id=' . (int) $item['product_id']) ?>"><?= e($item['title']) ?></a></h3>
                        <p class="muted"><?= t('prod.size') ?> <?= e($item['size']) ?> · <?= t('shop.sold_by') ?> <?= e(public_name($item['seller_name'], $item['shop_name'], $item['seller_hidden'])) ?></p>
                        <p class="price"><?= money((int) $item['sale_price']) ?></p>

                        <div class="cart-actions">
                            <form method="post" action="<?= url('actions/update_qty_action.php') ?>" class="qty-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                <label for="qty-<?= (int) $item['product_id'] ?>"><?= t('cart.qty') ?></label>
                                <input type="number" id="qty-<?= (int) $item['product_id'] ?>" name="quantity" value="<?= (int) $item['quantity'] ?>"
                                       min="1" max="<?= min((int) $item['stock'], CartController::MAX_QTY) ?>" data-autosubmit>
                                <button type="submit" class="mini-btn"><?= t('cart.update') ?></button>
                            </form>
                            <form method="post" action="<?= url('actions/remove_from_cart_action.php') ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                <button type="submit" class="mini-btn mini-danger"><?= t('cart.remove') ?></button>
                            </form>
                        </div>
                    </div>
                    <div class="cart-line"><?= money((int) $item['line_total']) ?></div>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="cart-summary">
            <h2><?= t('cart.summary') ?></h2>
            <div class="sum-line"><span><?= t('cart.items', ['count' => array_sum(array_column($bag['items'], 'quantity'))]) ?></span><span><?= money($bag['total']) ?></span></div>
            <div class="sum-line grand"><span><?= t('rent.total_due') ?></span><strong><?= money($bag['total']) ?></strong></div>
            <p class="help"><?= t('cart.delivery_note') ?></p>
            <a class="btn btn-solid btn-block" href="<?= url('views/checkout.php') ?>"><?= t('cart.checkout') ?></a>
            <p class="safe-note"><?= t('cart.safe') ?></p>
        </aside>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
