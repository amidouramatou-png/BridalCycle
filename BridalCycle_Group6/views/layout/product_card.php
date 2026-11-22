<?php
?>
<a class="product-card" href="<?= url('views/single_product.php?id=' . (int) $card['id']) ?>">
    <div class="product-photo">
        <?php if ($card['cover']): ?>
            <img src="<?= url('images/products/' . $card['cover']) ?>" alt="<?= e($card['title']) ?>" loading="lazy">
        <?php endif; ?>
        <span class="product-tag"><?= $card['item_condition'] === 'new' ? t('shop.new') : t('shop.pre_owned') ?></span>
        <?php if ($card['is_for_rent']): ?>
            <span class="product-tag tag-borrow"><?= t('shop.can_borrow') ?></span>
        <?php endif; ?>
        <?php if (!empty($card['borrowed_until']) && (!is_logged_in() || is_buyer())): ?>
            <span class="product-tag tag-borrowed"><?= t('shop.borrowed_tag', ['date' => date('d/m', strtotime($card['borrowed_until'] . ' +1 day'))]) ?></span>
        <?php endif; ?>
        <?php if (!empty($card['is_featured'])): ?>
            <span class="product-tag tag-featured">★ <?= t('feat.badge') ?></span>
        <?php endif; ?>
    </div>
    <div class="product-info">
        <span class="product-cat"><?= e($card['category_name']) ?><?= $card['designer_name'] ? ' · ' . e($card['designer_name']) : '' ?></span>
        <h3><?= e($card['title']) ?></h3>
        <?php if (!empty($card['seller_rating'])): ?>
            <span class="card-stars" aria-label="<?= t('rev.avg_label', ['avg' => $card['seller_rating']]) ?>">★ <?= e((string) $card['seller_rating']) ?></span>
        <?php endif; ?>
        <div class="product-prices">
            <?php if ($card['is_for_sale']): ?>
                <span class="price"><?= money((int) $card['sale_price']) ?></span>
            <?php endif; ?>
            <?php if ($card['is_for_rent']): ?>
                <span class="price-borrow"><?= t('shop.borrow_from', ['price' => money((int) $card['rent_price_per_day'])]) ?></span>
            <?php endif; ?>
        </div>
    </div>
</a>
