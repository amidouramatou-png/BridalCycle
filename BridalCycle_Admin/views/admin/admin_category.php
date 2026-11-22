<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/CategoryController.php';

require_admin();

$controller = new CategoryController();
$categories = $controller->getAllCategories();

// Edit mode?
$editId  = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editing = $editId ? $controller->getCategory($editId) : null;

// Errors and old values sent back by the action (shown once)
$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['old'] ?? ($editing ? [
    'name' => $editing['name'], 'slug' => $editing['slug'], 'type' => $editing['category_type'],
    'description' => $editing['description'], 'sort_order' => $editing['sort_order'],
] : []);
unset($_SESSION['form_errors'], $_SESSION['old']);

$val = fn (string $key, string $default = ''): string => e((string) ($old[$key] ?? $default));
$err = fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
$cls = fn (string $key): string => isset($errors[$key]) ? 'field invalid' : 'field';

// Group the list by type: traditions, inspirations, styles
$groups = array_fill_keys(CategoryController::TYPES, []);
foreach ($categories as $category) {
    $groups[$category['category_type']][] = $category;
}

$page_title = t('cat.title');
$admin_page = 'categories';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('cat.title') ?></h1>
        <p class="admin-intro"><?= t('cat.intro', ['count' => count($categories)]) ?></p>
    </div>

    <div class="admin-grid">

        <!-- Form: add a category, or edit one -->
        <aside class="admin-card">
            <h2><?= $editing ? t('cat.edit_title') : t('cat.add_title') ?></h2>

            <form method="post" novalidate data-validate
                  action="<?= url($editing ? 'actions/update_category_action.php' : 'actions/add_category_action.php') ?>">
                <?= csrf_field() ?>
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                    <input type="hidden" name="op" value="save">
                <?php endif; ?>

                <div class="<?= $cls('name') ?>">
                    <label for="name"><?= t('cat.name') ?></label>
                    <input type="text" id="name" name="name" value="<?= $val('name') ?>" required maxlength="80"
                           data-rule="catname" data-error="<?= t('cat.err_name') ?>">
                    <?= $err('name') ?>
                </div>

                <div class="<?= $cls('slug') ?>">
                    <label for="slug"><?= t('cat.slug') ?></label>
                    <input type="text" id="slug" name="slug" value="<?= $val('slug') ?>" maxlength="80"
                           placeholder="grand-boubou" data-rule="slug" data-error="<?= t('cat.err_slug') ?>">
                    <div class="help"><?= t('cat.slug_help') ?></div>
                    <?= $err('slug') ?>
                </div>

                <div class="<?= $cls('type') ?>">
                    <label for="type"><?= t('cat.type') ?></label>
                    <select id="type" name="type" required data-error="<?= t('cat.err_type') ?>">
                        <?php foreach (CategoryController::TYPES as $type): ?>
                            <option value="<?= e($type) ?>" <?= ($old['type'] ?? 'tradition') === $type ? 'selected' : '' ?>><?= t('cat.type_' . $type) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('type') ?>
                </div>

                <div class="<?= $cls('description') ?>">
                    <label for="description"><?= t('cat.description') ?></label>
                    <textarea id="description" name="description" maxlength="255" rows="3"><?= $val('description') ?></textarea>
                    <?= $err('description') ?>
                </div>

                <div class="<?= $cls('sort_order') ?>">
                    <label for="sort_order"><?= t('cat.order') ?></label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= $val('sort_order', '0') ?>"
                           min="0" max="999" step="1" data-rule="order" data-error="<?= t('cat.err_order') ?>">
                    <div class="help"><?= t('cat.order_help') ?></div>
                    <?= $err('sort_order') ?>
                </div>

                <button type="submit" class="btn btn-solid btn-block" data-loading="<?= t('cat.saving') ?>">
                    <?= $editing ? t('cat.save') : t('cat.add') ?>
                </button>
                <?php if ($editing): ?>
                    <p class="form-footer"><a href="<?= url('views/admin/admin_category.php') ?>"><?= t('cat.cancel') ?></a></p>
                <?php endif; ?>
            </form>
        </aside>

        <!-- List of categories, grouped by type -->
        <div class="admin-list">
            <?php foreach ($groups as $type => $list): ?>
                <h2 class="admin-group"><?= t('cat.group_' . $type) ?> <span>(<?= count($list) ?>)</span></h2>

                <?php if (!$list): ?>
                    <p class="empty"><?= t('cat.none') ?></p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?= t('cat.order_short') ?></th>
                                <th><?= t('cat.name') ?></th>
                                <th><?= t('cat.outfits') ?></th>
                                <th><?= t('cat.status') ?></th>
                                <th class="actions-col"><?= t('cat.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $category): ?>
                            <tr class="<?= (int) $category['is_active'] === 1 ? '' : 'is-hidden' ?><?= $editing && (int) $editing['id'] === (int) $category['id'] ? ' is-editing' : '' ?>">
                                <td><?= (int) $category['sort_order'] ?></td>
                                <td>
                                    <strong><?= e($category['name']) ?></strong>
                                    <span class="slug">/<?= e($category['slug']) ?></span>
                                    <?php if ($category['description']): ?>
                                        <span class="desc"><?= e($category['description']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $category['product_count'] ?></td>
                                <td>
                                    <?php if ((int) $category['is_active'] === 1): ?>
                                        <span class="badge badge-ok"><?= t('cat.visible') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-off"><?= t('cat.hidden_badge') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-col">
                                    <a class="mini-btn" href="<?= url('views/admin/admin_category.php?edit=' . (int) $category['id']) ?>"><?= t('cat.edit') ?></a>

                                    <form method="post" action="<?= url('actions/update_category_action.php') ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                        <input type="hidden" name="op" value="toggle">
                                        <button type="submit" class="mini-btn"><?= (int) $category['is_active'] === 1 ? t('cat.hide') : t('cat.show') ?></button>
                                    </form>

                                    <?php if ((int) $category['product_count'] === 0): ?>
                                        <!-- Delete only when no outfit uses this category -->
                                        <form method="post" action="<?= url('actions/update_category_action.php') ?>" class="inline-form"
                                              data-confirm="<?= t('cat.confirm_delete', ['name' => $category['name']]) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                            <input type="hidden" name="op" value="delete">
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
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
