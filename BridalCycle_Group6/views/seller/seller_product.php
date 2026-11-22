<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/ProductController.php';

require_login();
if (is_admin()) {
    redirect('views/admin/admin_dashboard.php');
}

$controller = new ProductController();
$sellerId   = (int) $_SESSION['user_id'];

// Which screen?
$editId  = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editing = ($editId && is_seller()) ? $controller->getSellerProduct($editId, $sellerId) : null;
if ($editId && ($editing === null || in_array($editing['status'], ['sold', 'archived'], true))) {
    $_SESSION['error'] = msg('prod.err_not_found');
    redirect('views/seller/seller_product.php');
}
$showForm = is_seller() && ($editing || isset($_GET['new']));

// Form data: values sent back after an error, or the outfit being edited
$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['old'] ?? ($editing ?: ['is_for_sale' => 1, 'stock' => 1, 'item_condition' => 'pre-owned']);
unset($_SESSION['form_errors'], $_SESSION['old']);

$val = fn (string $key, string $default = ''): string => e((string) ($old[$key] ?? $default));
$err = fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
$cls = fn (string $key, string $extra = ''): string => trim('field ' . $extra . (isset($errors[$key]) ? ' invalid' : ''));
$checked = fn (string $key): string => !empty($old[$key]) ? 'checked' : '';

$page_title   = $showForm ? ($editing ? t('prod.edit_title') : t('prod.add_title')) : t('prod.my_outfits');
$page_scripts = ['js/product_form.js'];
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="wrap seller-page">

<?php if (!is_seller()): ?>
    <!-- Not a seller yet: open a boutique -->
    <div class="form-page">
        <div class="form-card">
            <div class="section-script"><?= t('prod.become_script') ?></div>
            <h1><?= t('prod.become_title') ?></h1>
            <p class="form-intro"><?= t('prod.become_intro') ?></p>
            <form method="post" action="<?= url('actions/become_seller_action.php') ?>" novalidate data-validate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="shop_name"><?= t('reg.shop') ?></label>
                    <input type="text" id="shop_name" name="shop_name" maxlength="120">
                    <div class="help"><?= t('reg.shop_help') ?></div>
                </div>
                <button type="submit" class="btn btn-solid btn-block"><?= t('prod.become_button') ?></button>
            </form>
        </div>
    </div>

<?php elseif ($showForm): ?>
    <!-- The outfit form (add or edit) -->
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('prod.form_script') ?></div>
            <h1><?= $editing ? t('prod.edit_title') : t('prod.add_title') ?></h1>
            <p class="admin-intro"><?= t('prod.review_note') ?></p>
        </div>
        <a class="btn btn-line" href="<?= url('views/seller/seller_product.php') ?>"><?= t('prod.back') ?></a>
    </div>

    <?php if ($editing): ?>
        <!-- Photos already saved: choose the cover or delete one -->
        <div class="photo-manager">
            <h2><?= t('prod.current_photos') ?></h2>
            <div class="photo-grid">
                <?php foreach ($controller->getImages((int) $editing['id']) as $i => $image): ?>
                    <figure class="photo-item<?= $i === 0 ? ' is-cover' : '' ?>">
                        <img src="<?= url('images/products/' . $image['file_name']) ?>" alt="" loading="lazy">
                        <figcaption>
                            <?php if ($i === 0): ?>
                                <span class="badge badge-ok"><?= t('prod.cover') ?></span>
                            <?php else: ?>
                                <form method="post" action="<?= url('actions/update_product_action.php') ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="op" value="cover">
                                    <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                                    <button type="submit" class="mini-btn"><?= t('prod.make_cover') ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= url('actions/update_product_action.php') ?>" class="inline-form"
                                  data-confirm="<?= t('prod.confirm_photo') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="op" value="delete_photo">
                                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                                <button type="submit" class="mini-btn mini-danger" aria-label="<?= t('cat.delete') ?>">✕</button>
                            </form>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form class="outfit-form" method="post" enctype="multipart/form-data" novalidate data-validate
          action="<?= url($editing ? 'actions/update_product_action.php' : 'actions/add_product_action.php') ?>">
        <?= csrf_field() ?>
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <input type="hidden" name="op" value="save">
        <?php endif; ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_BYTES ?>">

        <!-- The outfit -->
        <fieldset class="form-section">
            <legend><span>1</span> <?= t('prod.section_outfit') ?></legend>

            <div class="<?= $cls('title') ?>">
                <label for="title"><?= t('prod.title') ?></label>
                <input type="text" id="title" name="title" value="<?= $val('title') ?>" required maxlength="150"
                       placeholder="<?= t('prod.title_placeholder') ?>" data-rule="title" data-error="<?= t('prod.err_title') ?>">
                <?= $err('title') ?>
            </div>

            <div class="form-row">
                <div class="<?= $cls('category_id') ?>">
                    <label for="category_id"><?= t('prod.category') ?></label>
                    <select id="category_id" name="category_id" required data-error="<?= t('prod.err_category') ?>">
                        <option value=""><?= t('prod.choose') ?></option>
                        <?php
                        $groups = [];
                        foreach ($controller->getActiveCategories() as $c) { $groups[$c['category_type']][] = $c; }
                        foreach ($groups as $type => $list): ?>
                            <optgroup label="<?= t('cat.group_' . $type) ?>">
                                <?php foreach ($list as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($old['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('category_id') ?>
                </div>
                <div class="<?= $cls('designer_id') ?>">
                    <label for="designer_id"><?= t('prod.designer') ?></label>
                    <select id="designer_id" name="designer_id">
                        <option value=""><?= t('prod.no_designer') ?></option>
                        <?php foreach ($controller->getActiveDesigners() as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= (int) ($old['designer_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('designer_id') ?>
                </div>
            </div>

            <div class="<?= $cls('item_condition') ?>">
                <label><?= t('prod.condition') ?></label>
                <label class="choice"><input type="radio" name="item_condition" value="pre-owned" <?= ($old['item_condition'] ?? '') === 'pre-owned' ? 'checked' : '' ?>> <?= t('prod.cond_pre_owned') ?></label>
                <label class="choice"><input type="radio" name="item_condition" value="new" <?= ($old['item_condition'] ?? '') === 'new' ? 'checked' : '' ?>> <?= t('prod.cond_new') ?></label>
                <?= $err('item_condition') ?>
            </div>

            <div class="form-row form-row-3">
                <div class="<?= $cls('size') ?>">
                    <label for="size"><?= t('prod.size') ?></label>
                    <input type="text" id="size" name="size" value="<?= $val('size') ?>" required maxlength="20"
                           placeholder="M, 38, 40/42…" data-rule="size" data-error="<?= t('prod.err_size') ?>">
                    <?= $err('size') ?>
                </div>
                <div class="<?= $cls('colour') ?>">
                    <label for="colour"><?= t('prod.colour') ?></label>
                    <input type="text" id="colour" name="colour" value="<?= $val('colour') ?>" maxlength="40"
                           placeholder="<?= t('prod.colour_placeholder') ?>" data-rule="colour" data-error="<?= t('prod.err_colour') ?>">
                    <?= $err('colour') ?>
                </div>
                <div class="<?= $cls('stock') ?>">
                    <label for="stock"><?= t('prod.stock') ?></label>
                    <input type="number" id="stock" name="stock" value="<?= $val('stock', '1') ?>" min="1" max="20" required
                           data-rule="positive" data-error="<?= t('prod.err_stock') ?>">
                    <?= $err('stock') ?>
                </div>
            </div>

            <div class="<?= $cls('description') ?>">
                <label for="description"><?= t('prod.description') ?></label>
                <textarea id="description" name="description" rows="6" required maxlength="3000"
                          placeholder="<?= t('prod.description_placeholder') ?>"
                          data-rule="length" data-min="20" data-max="3000" data-error="<?= t('prod.err_description') ?>"><?= $val('description') ?></textarea>
                <?= $err('description') ?>
            </div>
        </fieldset>

        <!-- Sell and / or lend -->
        <fieldset class="form-section">
            <legend><span>2</span> <?= t('prod.section_offer') ?></legend>
            <p class="help"><?= t('prod.offer_help') ?> <?= t('com.form_note', ['pct' => COMMISSION_PERCENT]) ?></p>
            <?= $err('mode') ?>

            <div class="offer-grid">
                <div class="offer-box">
                    <label class="choice choice-strong"><input type="checkbox" name="is_for_sale" value="1" <?= $checked('is_for_sale') ?> data-toggles="sale-fields"> <?= t('prod.for_sale') ?></label>
                    <div id="sale-fields" class="toggle-fields">
                        <div class="<?= $cls('sale_price') ?>">
                            <label for="sale_price"><?= t('prod.sale_price') ?></label>
                            <div class="money-input">
                                <input type="text" inputmode="numeric" id="sale_price" name="sale_price" value="<?= $val('sale_price') ?>" required maxlength="13"
                                       data-rule="money" data-min="<?= ProductController::PRICE_MIN ?>" data-error="<?= t('prod.err_price', ['min' => money(ProductController::PRICE_MIN)]) ?>">
                                <span><?= CURRENCY ?></span>
                            </div>
                            <?= $err('sale_price') ?>
                        </div>
                    </div>
                </div>

                <div class="offer-box">
                    <label class="choice choice-strong"><input type="checkbox" name="is_for_rent" value="1" <?= $checked('is_for_rent') ?> data-toggles="rent-fields"> <?= t('prod.for_rent') ?></label>
                    <div id="rent-fields" class="toggle-fields">
                        <div class="<?= $cls('rent_price_per_day') ?>">
                            <label for="rent_price_per_day"><?= t('prod.rent_price') ?></label>
                            <div class="money-input">
                                <input type="text" inputmode="numeric" id="rent_price_per_day" name="rent_price_per_day" value="<?= $val('rent_price_per_day') ?>" required maxlength="13"
                                       data-rule="money" data-min="<?= ProductController::PRICE_MIN ?>" data-error="<?= t('prod.err_price', ['min' => money(ProductController::PRICE_MIN)]) ?>">
                                <span><?= CURRENCY ?></span>
                            </div>
                            <?= $err('rent_price_per_day') ?>
                        </div>
                        <div class="<?= $cls('deposit') ?>">
                            <label for="deposit"><?= t('prod.deposit') ?></label>
                            <div class="money-input">
                                <input type="text" inputmode="numeric" id="deposit" name="deposit" value="<?= $val('deposit', '0') ?>" maxlength="13"
                                       data-rule="money" data-min="0" data-error="<?= t('prod.err_deposit') ?>">
                                <span><?= CURRENCY ?></span>
                            </div>
                            <div class="help"><?= t('prod.deposit_help') ?></div>
                            <?= $err('deposit') ?>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>

        <!-- Photos -->
        <fieldset class="form-section">
            <legend><span>3</span> <?= t('prod.section_photos') ?></legend>
            <div class="<?= $cls('photos') ?>">
                <label for="photos"><?= $editing ? t('prod.add_photos') : t('prod.photos', ['min' => UPLOAD_MIN_PER_OUTFIT]) ?></label>
                <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
                       data-max-files="<?= UPLOAD_MAX_PER_OUTFIT - ($editing ? count($controller->getImages((int) $editing['id'])) : 0) ?>"
                       data-min-files="<?= max(0, UPLOAD_MIN_PER_OUTFIT - ($editing ? count($controller->getImages((int) $editing['id'])) : 0)) ?>"
                       data-error-min="<?= t('prod.err_photo_needed', ['min' => UPLOAD_MIN_PER_OUTFIT]) ?>"
                       data-max-bytes="<?= UPLOAD_MAX_BYTES ?>"
                       data-error-count="<?= t('prod.err_photo_count', ['max' => UPLOAD_MAX_PER_OUTFIT]) ?>"
                       data-error-size="<?= t('prod.err_photo_size', ['mb' => UPLOAD_MAX_BYTES / 1048576]) ?>"
                       data-error-type="<?= t('prod.err_photo_type') ?>">
                <div class="help"><?= t('prod.photos_help', ['min' => UPLOAD_MIN_PER_OUTFIT, 'max' => UPLOAD_MAX_PER_OUTFIT, 'mb' => UPLOAD_MAX_BYTES / 1048576]) ?></div>
                <?= $err('photos') ?>
                <div class="photo-preview" id="photo-preview" aria-live="polite"></div>
            </div>
        </fieldset>

        <button type="submit" class="btn btn-solid" data-loading="<?= t('prod.saving') ?>"><?= $editing ? t('prod.save') : t('prod.publish') ?></button>
    </form>

<?php else: ?>
    <!-- List of her outfits -->
    <?php $outfits = $controller->getSellerProducts($sellerId); ?>
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('prod.list_script') ?></div>
            <h1><?= t('prod.my_outfits') ?></h1>
            <p class="admin-intro"><?= t('prod.list_intro', ['count' => count($outfits)]) ?></p>
        </div>
        <a class="btn btn-solid" href="<?= url('views/seller/seller_product.php?new=1') ?>"><?= t('prod.add_button') ?></a>
    </div>

    <?php if (!$outfits): ?>
        <div class="empty-card">
            <p><?= t('prod.none') ?></p>
            <a class="btn btn-line" href="<?= url('views/seller/seller_product.php?new=1') ?>"><?= t('prod.add_button') ?></a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table outfit-table">
                <thead>
                    <tr>
                        <th><?= t('prod.photo') ?></th>
                        <th><?= t('prod.title') ?></th>
                        <th><?= t('prod.offer') ?></th>
                        <th><?= t('cat.status') ?></th>
                        <th class="actions-col"><?= t('cat.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($outfits as $o): ?>
                    <tr class="<?= $o['status'] === 'archived' ? 'is-hidden' : '' ?>">
                        <td class="thumb-cell">
                            <?php if ($o['cover']): ?>
                                <img class="thumb" src="<?= url('images/products/' . $o['cover']) ?>" alt="" loading="lazy">
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($o['title']) ?></strong>
                            <span class="desc"><?= e($o['category_name']) ?> · <?= t('prod.size') ?> <?= e($o['size']) ?> · <?= $o['item_condition'] === 'new' ? t('prod.cond_new') : t('prod.cond_pre_owned') ?></span>
                        </td>
                        <td class="offer-cell">
                            <?php if ($o['is_for_sale']): ?><span><?= t('prod.buy') ?> <?= money((int) $o['sale_price']) ?></span><?php endif; ?>
                            <?php if ($o['is_for_rent']): ?><span><?= t('prod.borrow') ?> <?= money((int) $o['rent_price_per_day']) ?> <?= t('prod.per_day') ?></span><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= e($o['status']) ?>"><?= t('prod.status_' . $o['status']) ?></span>
                            <?php if ($o['borrowed_until']): ?>
                                <span class="badge badge-borrowed"><?= t('seller.borrowed_until', ['date' => date('d/m/Y', strtotime($o['borrowed_until']))]) ?></span>
                            <?php endif; ?>
                            <span class="desc views-line">👁 <?= t('views.count', ['n' => (int) $o['view_count']]) ?></span>
                            <?php if ($o['status'] === 'rejected' && $o['rejection_reason']): ?>
                                <span class="desc"><?= e($o['rejection_reason']) ?></span>
                            <?php endif; ?>
                            <?php if ($o['status'] === 'approved' && $o['featured_until'] && strtotime($o['featured_until']) > time()): ?>
                                <span class="badge badge-featured"><?= t('feat.until', ['date' => date('d/m/Y', strtotime($o['featured_until']))]) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-col">
                            <?php if (!in_array($o['status'], ['sold', 'archived'], true)): ?>
                                <?php if ($o['status'] === 'approved'): ?>
                                    <!-- REVENUE: the seller pays BridalCycle to show this outfit first in the shop -->
                                    <a class="mini-btn mini-gold" href="<?= url('views/payment.php?type=feature&id=' . (int) $o['id']) ?>"
                                       title="<?= t('feat.help', ['price' => money(FEATURE_PRICE), 'days' => FEATURE_DAYS]) ?>"><?= t('feat.button', ['price' => money(FEATURE_PRICE), 'days' => FEATURE_DAYS]) ?></a>
                                <?php endif; ?>
                                <a class="mini-btn" href="<?= url('views/seller/seller_product.php?edit=' . (int) $o['id']) ?>"><?= t('cat.edit') ?></a>
                                <form method="post" action="<?= url('actions/delete_product_action.php') ?>" class="inline-form"
                                      data-confirm="<?= t('prod.confirm_delete', ['title' => $o['title']]) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                    <button type="submit" class="mini-btn mini-danger"><?= t('cat.delete') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
