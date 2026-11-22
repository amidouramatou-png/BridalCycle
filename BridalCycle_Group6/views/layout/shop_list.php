<?php
$result = $shop->getPage($filters);
$link = fn (array $change): string => ShopController::link($list_page, $filters, $change);
$groups = [];
foreach ($shop->getCategories() as $c) {
    $groups[$c['category_type']][] = $c;
}
$designers = $shop->getDesigners();
?>
<section class="shop-hero">
    <div class="wrap">
        <div class="section-script"><?= $list_script ?></div>
        <h1><?= $list_title ?></h1>
        <p><?= $list_intro ?></p>
    </div>
</section>

<section class="wrap shop">
    <!-- Filters -->
    <aside class="shop-filters" aria-label="<?= t('shop.filters') ?>">
        <?php if (empty($hide_mode)): ?>
            <div class="filter-block">
                <h4><?= t('shop.f_mode') ?></h4>
                <a href="<?= $link(['mode' => '']) ?>" class="<?= $filters['mode'] === '' ? 'active' : '' ?>"><?= t('shop.all') ?></a>
                <a href="<?= $link(['mode' => 'buy']) ?>" class="<?= $filters['mode'] === 'buy' ? 'active' : '' ?>"><?= t('shop.f_buy') ?></a>
                <a href="<?= $link(['mode' => 'borrow']) ?>" class="<?= $filters['mode'] === 'borrow' ? 'active' : '' ?>"><?= t('shop.f_borrow') ?></a>
            </div>
        <?php endif; ?>

        <?php foreach ($groups as $type => $list): ?>
            <div class="filter-block">
                <h4><?= t('cat.group_' . $type) ?></h4>
                <?php foreach ($list as $c): ?>
                    <a href="<?= $link(['cat' => $filters['cat'] === $c['slug'] ? '' : $c['slug']]) ?>"
                       class="<?= $filters['cat'] === $c['slug'] ? 'active' : '' ?>"><?= e($c['name']) ?> <span><?= (int) $c['n'] ?></span></a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($designers): ?>
            <div class="filter-block">
                <h4><?= t('shop.f_designer') ?></h4>
                <?php foreach ($designers as $d): ?>
                    <a href="<?= $link(['designer' => $filters['designer'] === $d['slug'] ? '' : $d['slug']]) ?>"
                       class="<?= $filters['designer'] === $d['slug'] ? 'active' : '' ?>"><?= e($d['name']) ?> <span><?= (int) $d['n'] ?></span></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="filter-block">
            <h4><?= t('prod.condition') ?></h4>
            <a href="<?= $link(['condition' => '']) ?>" class="<?= $filters['condition'] === '' ? 'active' : '' ?>"><?= t('shop.all') ?></a>
            <a href="<?= $link(['condition' => 'new']) ?>" class="<?= $filters['condition'] === 'new' ? 'active' : '' ?>"><?= t('shop.new') ?></a>
            <a href="<?= $link(['condition' => 'pre-owned']) ?>" class="<?= $filters['condition'] === 'pre-owned' ? 'active' : '' ?>"><?= t('shop.pre_owned') ?></a>
        </div>
    </aside>

    <!-- Results -->
    <div class="shop-results">
        <div class="shop-bar">
            <p><?= t('shop.count', ['count' => $result['total']]) ?>
                <?php if ($filters['cat'] || $filters['designer'] || $filters['condition'] || (empty($hide_mode) && $filters['mode']) || $filters['q'] !== ''): ?>
                    · <a href="<?= url($list_page) ?>" class="clear-link"><?= t('shop.clear') ?></a>
                <?php endif; ?>
            </p>
            <nav class="sort" aria-label="<?= t('shop.sort') ?>">
                <span><?= t('shop.sort') ?>:</span>
                <?php foreach (ShopController::SORTS as $s): ?>
                    <a href="<?= $link(['sort' => $s === 'new' ? '' : $s]) ?>" class="<?= $filters['sort'] === $s ? 'active' : '' ?>"><?= t('shop.sort_' . $s) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <?php if (!$result['products']): ?>
            <div class="empty-card">
                <p><?= t('shop.none') ?></p>
                <a class="btn btn-line" href="<?= url('views/all_products.php') ?>"><?= t('shop.see_all') ?></a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($result['products'] as $card): ?>
                    <?php require __DIR__ . '/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($result['pages'] > 1): ?>
            <nav class="pages" aria-label="<?= t('shop.pages') ?>">
                <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                    <a href="<?= $link(['page' => $i]) ?>" class="<?= $i === $result['page'] ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
