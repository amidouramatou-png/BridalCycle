<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/DesignerController.php';

require_admin();

$controller = new DesignerController();
$designers  = $controller->getAllDesigners();

$editId  = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editing = $editId ? $controller->getDesigner($editId) : null;

$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['old'] ?? ($editing ? [
    'name' => $editing['name'], 'slug' => $editing['slug'],
    'country' => $editing['country'], 'description' => $editing['description'],
] : []);
unset($_SESSION['form_errors'], $_SESSION['old']);

$val = fn (string $key, string $default = ''): string => e((string) ($old[$key] ?? $default));
$err = fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
$cls = fn (string $key): string => isset($errors[$key]) ? 'field invalid' : 'field';

$page_title = t('des.title');
$admin_page = 'designers';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('des.title') ?></h1>
        <p class="admin-intro"><?= t('des.intro', ['count' => count($designers)]) ?></p>
    </div>

    <div class="admin-grid">

        <!-- Form: add a designer, or edit one -->
        <aside class="admin-card">
            <h2><?= $editing ? t('des.edit_title') : t('des.add_title') ?></h2>

            <form method="post" novalidate data-validate
                  action="<?= url($editing ? 'actions/update_designer_action.php' : 'actions/add_designer_action.php') ?>">
                <?= csrf_field() ?>
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                    <input type="hidden" name="op" value="save">
                <?php endif; ?>

                <div class="<?= $cls('name') ?>">
                    <label for="name"><?= t('des.name') ?></label>
                    <input type="text" id="name" name="name" value="<?= $val('name') ?>" required maxlength="100"
                           data-rule="catname" data-error="<?= t('des.err_name') ?>">
                    <?= $err('name') ?>
                </div>

                <div class="<?= $cls('slug') ?>">
                    <label for="slug"><?= t('cat.slug') ?></label>
                    <input type="text" id="slug" name="slug" value="<?= $val('slug') ?>" maxlength="100"
                           placeholder="maison-example" data-rule="slug" data-error="<?= t('cat.err_slug') ?>">
                    <div class="help"><?= t('cat.slug_help') ?></div>
                    <?= $err('slug') ?>
                </div>

                <div class="<?= $cls('country') ?>">
                    <label for="country"><?= t('des.country') ?></label>
                    <input type="text" id="country" name="country" value="<?= $val('country') ?>" maxlength="60"
                           placeholder="Niger" data-rule="country" data-error="<?= t('des.err_country') ?>">
                    <?= $err('country') ?>
                </div>

                <div class="<?= $cls('description') ?>">
                    <label for="description"><?= t('cat.description') ?></label>
                    <textarea id="description" name="description" maxlength="255" rows="3"><?= $val('description') ?></textarea>
                    <?= $err('description') ?>
                </div>

                <button type="submit" class="btn btn-solid btn-block" data-loading="<?= t('cat.saving') ?>">
                    <?= $editing ? t('cat.save') : t('des.add') ?>
                </button>
                <?php if ($editing): ?>
                    <p class="form-footer"><a href="<?= url('views/admin/admin_designer.php') ?>"><?= t('cat.cancel') ?></a></p>
                <?php endif; ?>
            </form>
        </aside>

        <!-- List of designers -->
        <div class="admin-list">
            <h2 class="admin-group"><?= t('des.list') ?> <span>(<?= count($designers) ?>)</span></h2>

            <?php if (!$designers): ?>
                <p class="empty"><?= t('des.none') ?></p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= t('des.name') ?></th>
                            <th><?= t('des.country') ?></th>
                            <th><?= t('cat.outfits') ?></th>
                            <th><?= t('cat.status') ?></th>
                            <th class="actions-col"><?= t('cat.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($designers as $designer): ?>
                        <tr class="<?= (int) $designer['is_active'] === 1 ? '' : 'is-hidden' ?><?= $editing && (int) $editing['id'] === (int) $designer['id'] ? ' is-editing' : '' ?>">
                            <td>
                                <strong><?= e($designer['name']) ?></strong>
                                <span class="slug">/<?= e($designer['slug']) ?></span>
                                <?php if ($designer['description']): ?>
                                    <span class="desc"><?= e($designer['description']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= $designer['country'] ? e($designer['country']) : '—' ?></td>
                            <td><?= (int) $designer['product_count'] ?></td>
                            <td>
                                <?php if ((int) $designer['is_active'] === 1): ?>
                                    <span class="badge badge-ok"><?= t('cat.visible') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-off"><?= t('cat.hidden_badge') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-col">
                                <a class="mini-btn" href="<?= url('views/admin/admin_designer.php?edit=' . (int) $designer['id']) ?>"><?= t('cat.edit') ?></a>

                                <form method="post" action="<?= url('actions/update_designer_action.php') ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $designer['id'] ?>">
                                    <input type="hidden" name="op" value="toggle">
                                    <button type="submit" class="mini-btn"><?= (int) $designer['is_active'] === 1 ? t('cat.hide') : t('cat.show') ?></button>
                                </form>

                                <?php if ((int) $designer['product_count'] === 0): ?>
                                    <form method="post" action="<?= url('actions/update_designer_action.php') ?>" class="inline-form"
                                          data-confirm="<?= t('des.confirm_delete', ['name' => $designer['name']]) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $designer['id'] ?>">
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
        </div>
    </div>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
