<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/AdminController.php';

require_admin();

$controller = new AdminController();
$tab = in_array($_GET['tab'] ?? '', AdminController::PRODUCT_TABS, true) ? $_GET['tab'] : 'pending';
$counts  = $controller->countProducts();
$outfits = $controller->getProducts($tab);

$page_title = t('adm.products_title');
$admin_page = 'products';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('adm.products_title') ?></h1>
        <p class="admin-intro"><?= t('adm.products_intro') ?></p>
    </div>

    <!-- Tabs -->
    <nav class="tabs" aria-label="<?= t('adm.products_title') ?>">
        <?php foreach (AdminController::PRODUCT_TABS as $t): ?>
            <a href="<?= url('views/admin/admin_products.php?tab=' . $t) ?>" class="<?= $tab === $t ? 'active' : '' ?>">
                <?= t('prod.status_' . $t) ?> <span class="count"><?= (int) ($counts[$t] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$outfits): ?>
        <div class="empty-card"><p><?= t('adm.none_' . $tab) ?></p></div>
    <?php endif; ?>

    <?php foreach ($outfits as $o): ?>
        <article class="review-card" id="outfit-<?= (int) $o['id'] ?>">

            <!-- Photos -->
            <div class="review-photos">
                <?php foreach ($controller->getImages((int) $o['id']) as $i => $image): ?>
                    <a href="<?= url('images/products/' . $image['file_name']) ?>" target="_blank" rel="noopener">
                        <img src="<?= url('images/products/' . $image['file_name']) ?>" alt="" loading="lazy" class="<?= $i === 0 ? 'is-cover' : '' ?>">
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Details -->
            <div class="review-body">
                <div class="review-top">
                    <h2><?= e($o['title']) ?></h2>
                    <span class="badge badge-<?= e($o['status']) ?>"><?= t('prod.status_' . $o['status']) ?></span>
                </div>

                <dl class="review-facts">
                    <div><dt><?= t('prod.category') ?></dt><dd><?= e($o['category_name']) ?></dd></div>
                    <div><dt><?= t('prod.designer') ?></dt><dd><?= $o['designer_name'] ? e($o['designer_name']) : '—' ?></dd></div>
                    <div><dt><?= t('prod.condition') ?></dt><dd><?= $o['item_condition'] === 'new' ? t('prod.cond_new') : t('prod.cond_pre_owned') ?></dd></div>
                    <div><dt><?= t('prod.size') ?></dt><dd><?= e($o['size']) ?></dd></div>
                    <div><dt><?= t('prod.colour') ?></dt><dd><?= $o['colour'] ? e($o['colour']) : '—' ?></dd></div>
                    <div><dt><?= t('prod.stock') ?></dt><dd><?= (int) $o['stock'] ?></dd></div>
                    <div><dt><?= t('views.label') ?></dt><dd><?= (int) $o['view_count'] ?></dd></div>
                    <?php if ($o['is_for_sale']): ?>
                        <div><dt><?= t('prod.sale_price') ?></dt><dd><?= money((int) $o['sale_price']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($o['is_for_rent']): ?>
                        <div><dt><?= t('prod.rent_price') ?></dt><dd><?= money((int) $o['rent_price_per_day']) ?></dd></div>
                        <div><dt><?= t('prod.deposit') ?></dt><dd><?= money((int) $o['deposit']) ?></dd></div>
                    <?php endif; ?>
                </dl>

                <p class="review-desc"><?= nl2br(e($o['description'])) ?></p>

                <p class="review-seller">
                    <?= t('adm.seller') ?>: <strong><?= e($o['seller_name']) ?></strong>
                    <?php if ($o['shop_name']): ?>(<?= e($o['shop_name']) ?>)<?php endif; ?>
                    · <?= e($o['seller_region']) ?> · <?= e($o['seller_phone']) ?> · <?= e($o['seller_email']) ?>
                    <br><span class="muted"><?= t('adm.updated') ?> <?= e(date('d/m/Y H:i', strtotime($o['updated_at']))) ?></span>
                </p>

                <?php if ($o['status'] === 'rejected' && $o['rejection_reason']): ?>
                    <p class="review-reason"><?= t('adm.reason_given') ?>: <?= e($o['rejection_reason']) ?></p>
                <?php endif; ?>

                <!-- Decisions -->
                <div class="review-actions">
                    <?php if (in_array($o['status'], ['pending', 'rejected'], true)): ?>
                        <form method="post" action="<?= url('actions/review_product_action.php') ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                            <input type="hidden" name="op" value="approve">
                            <input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <button type="submit" class="btn btn-solid btn-small"><?= t('adm.approve') ?></button>
                        </form>
                    <?php endif; ?>

                    <?php if (in_array($o['status'], ['pending', 'approved'], true)): ?>
                        <form method="post" action="<?= url('actions/review_product_action.php') ?>" class="reject-form" novalidate data-validate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                            <input type="hidden" name="op" value="reject">
                            <input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <div class="field">
                                <label for="reason-<?= (int) $o['id'] ?>"><?= $o['status'] === 'approved' ? t('adm.remove_reason') : t('adm.reject_reason') ?></label>
                                <input type="text" id="reason-<?= (int) $o['id'] ?>" name="reason" required maxlength="255"
                                       placeholder="<?= t('adm.reason_placeholder') ?>"
                                       data-rule="length" data-min="5" data-max="255" data-error="<?= t('adm.err_reason') ?>">
                            </div>
                            <button type="submit" class="btn btn-line btn-small"><?= $o['status'] === 'approved' ? t('adm.remove') : t('adm.reject') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
