<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ShopController.php';

$shop = new ShopController();
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = $id ? $shop->getProduct($id) : null;

if ($product === null) {
    http_response_code(404);
    $page_title = t('shop.not_found');
    require __DIR__ . '/layout/header.php';
    echo '<section class="wrap form-page"><div class="empty-card"><p>' . t('shop.not_found_text') . '</p>'
       . '<a class="btn btn-line" href="' . url('views/all_products.php') . '">' . t('shop.see_all') . '</a></div></section>';
    require __DIR__ . '/layout/footer.php';
    exit;
}

// View counter: once per visit, never the seller herself or the admin
$_SESSION['viewed'] ??= [];
if ($shop->countView($product, is_logged_in() ? (int) $_SESSION['user_id'] : null, is_admin(), in_array((int) $product['id'], $_SESSION['viewed'], true))) {
    $_SESSION['viewed'] = array_slice([...$_SESSION['viewed'], (int) $product['id']], -200);
}

$images  = $shop->getImages((int) $product['id']);
$related = $shop->getRelated($product);
$seller  = public_name($product['seller_name'], $product['shop_name'], $product['seller_hidden']);
// Who can buy and borrow? Visitors (asked to log in) and brides
$canShop = !is_logged_in() || is_buyer();
$borrowedUntil = $product['borrowed_until'] ? date('d/m/Y', strtotime($product['borrowed_until'] . ' +1 day')) : null;
require_once dirname(__DIR__) . '/controllers/ReviewController.php';
$reviewController = new ReviewController();
$rating  = $reviewController->getSellerSummary((int) $product['seller_id']);
$reviews = $reviewController->getSellerReviews((int) $product['seller_id'], 3);

$page_title   = e($product['title']);
$page_scripts = ['js/gallery.js'];
require __DIR__ . '/layout/header.php';
?>

<section class="wrap product-page">
    <nav class="crumbs" aria-label="<?= t('shop.crumbs') ?>">
        <a href="<?= url('views/all_products.php') ?>"><?= t('shop.all_title') ?></a> /
        <a href="<?= url('views/all_products.php?cat=' . urlencode($product['category_slug'])) ?>"><?= e($product['category_name']) ?></a>
    </nav>

    <div class="product-layout">
        <!-- Gallery -->
        <div class="gallery" data-gallery>
            <div class="gallery-main">
                <?php if ($images): ?>
                    <img src="<?= url('images/products/' . $images[0]['file_name']) ?>" alt="<?= e($product['title']) ?>" data-gallery-main>
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach ($images as $i => $image): ?>
                        <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-gallery-src="<?= url('images/products/' . $image['file_name']) ?>"
                                aria-label="<?= t('shop.photo_n', ['n' => $i + 1]) ?>">
                            <img src="<?= url('images/products/' . $image['file_name']) ?>" alt="" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="product-details">
            <span class="product-cat"><?= e($product['category_name']) ?></span>
            <h1><?= e($product['title']) ?></h1>
            <?php if ($product['designer_name']): ?>
                <p class="designer-line"><?= t('shop.by') ?> <a href="<?= url('views/all_products.php?designer=' . urlencode($product['designer_slug'])) ?>"><?= e($product['designer_name']) ?></a><?= $product['designer_country'] ? ' · ' . e($product['designer_country']) : '' ?></p>
            <?php endif; ?>

            <!-- Prices -->
            <div class="price-boxes">
                <?php if ($product['is_for_sale']): ?>
                    <div class="price-box">
                        <span class="label"><?= t('shop.buy_it') ?></span>
                        <span class="amount"><?= money((int) $product['sale_price']) ?></span>
                        <?php if ($canShop): ?>
                            <form method="post" action="<?= url('actions/add_to_cart_action.php') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <button type="submit" class="btn btn-solid btn-block"><?= t('shop.add_to_bag') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($product['is_for_rent']): ?>
                    <div class="price-box price-box-borrow" id="borrow">
                        <span class="label"><?= t('shop.borrow_it') ?></span>
                        <span class="amount"><?= money((int) $product['rent_price_per_day']) ?> <small><?= t('prod.per_day') ?></small></span>
                        <span class="small"><?= t('shop.deposit_line', ['deposit' => money((int) $product['deposit'])]) ?></span>
                        <span class="small"><?= t('shop.borrow_rules', ['min' => RENT_MIN_DAYS, 'max' => RENT_MAX_DAYS]) ?></span>
                        <?php if ($borrowedUntil && $canShop): ?>
                            <!-- Another bride has it right now: shown to brides, but it cannot be requested -->
                            <span class="btn btn-block btn-disabled" aria-disabled="true"><?= t('shop.borrowed_now') ?></span>
                            <span class="small borrowed-note"><?= t('shop.borrowed_back', ['date' => $borrowedUntil]) ?></span>
                        <?php elseif ($canShop): ?>
                            <a class="btn btn-line btn-block" href="<?= url('views/borrow_request.php?id=' . (int) $product['id']) ?>"><?= t('shop.borrow_button') ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$canShop): ?>
                <?php if (is_seller() && (int) $_SESSION['user_id'] === (int) $product['seller_id']): ?>
                    <!-- Her own outfit: she manages it from her boutique (status, views, featuring) -->
                    <p class="role-note"><?= t('role.own_outfit') ?> <a href="<?= url('views/seller/seller_product.php') ?>"><?= t('prod.my_outfits') ?> →</a></p>
                <?php else: ?>
                    <p class="role-note"><?= is_admin() ? t('role.admin_view') : t('role.seller_view') ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <dl class="review-facts product-facts">
                <div><dt><?= t('prod.condition') ?></dt><dd><?= $product['item_condition'] === 'new' ? t('prod.cond_new') : t('prod.cond_pre_owned') ?></dd></div>
                <div><dt><?= t('prod.size') ?></dt><dd><?= e($product['size']) ?></dd></div>
                <div><dt><?= t('shop.colour') ?></dt><dd><?= $product['colour'] ? e($product['colour']) : '—' ?></dd></div>
            </dl>

            <div class="product-description">
                <h2><?= t('prod.description') ?></h2>
                <p><?= nl2br(e($product['description'])) ?></p>
            </div>

            <p class="sold-by"><?= t('shop.sold_by') ?> <strong><?= e($seller) ?></strong> · <?= e($product['seller_region']) ?>, Niger
                <?php if ($rating['n'] > 0): ?>
                    <span class="seller-stars">★ <?= e((string) $rating['avg']) ?>/5 <span class="muted">(<?= t('rev.count', ['n' => $rating['n']]) ?>)</span></span>
                <?php else: ?>
                    <span class="muted"> · <?= t('rev.none_yet') ?></span>
                <?php endif; ?>
            </p>
            <?php if ($reviews): ?>
                <div class="seller-reviews">
                    <?php foreach ($reviews as $rv): ?>
                        <blockquote>
                            <span class="stars-static"><?= str_repeat('★', (int) $rv['rating']) . str_repeat('☆', 5 - (int) $rv['rating']) ?></span>
                            <?php if ($rv['comment']): ?><p>« <?= e($rv['comment']) ?> »</p><?php endif; ?>
                            <cite><?= e(public_name($rv['full_name'], null, $rv['hide_name'], 'buyer')) ?> · <?= e(date('d/m/Y', strtotime($rv['created_at']))) ?></cite>
                        </blockquote>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($canShop && (int) ($_SESSION['user_id'] ?? 0) !== (int) $product['seller_id']): ?>
                <!-- Visitors are asked to log in first -->
                <a class="btn btn-line btn-small" href="<?= url('views/messages.php?with=' . (int) $product['seller_id'] . '&product=' . (int) $product['id'] . '#write') ?>"><?= t('msgs.ask_seller') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($related): ?>
        <div class="related">
            <h2><?= t('shop.related') ?></h2>
            <div class="product-grid product-grid-4">
                <?php foreach ($related as $card): ?>
                    <?php require __DIR__ . '/layout/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
