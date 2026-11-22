<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/ReviewController.php';

require_admin();
$reviews = (new ReviewController())->getAll();

$page_title = t('rev.admin_title');
$admin_page = 'reviews';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';

$button = function (int $id, string $op, string $class) { ?>
    <form method="post" action="<?= url('actions/admin_review_action.php') ?>" class="inline-form"
          <?= $op === 'delete' ? 'data-confirm="' . t('rev.confirm_delete') . '"' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="op" value="<?= e($op) ?>">
        <button type="submit" class="mini-btn <?= e($class) ?>"><?= t('rev.op_' . $op) ?></button>
    </form>
<?php };
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('rev.admin_title') ?></h1>
        <p class="admin-intro"><?= t('rev.admin_intro') ?></p>
    </div>

    <?php if (!$reviews): ?>
        <div class="empty-card"><p><?= t('adm.list_none') ?></p></div>
    <?php else: ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th><?= t('adm.col_date') ?></th><th><?= t('adm.seller') ?></th><th><?= t('rent.bride') ?></th><th><?= t('rev.col_rating') ?></th><th class="actions-col"><?= t('cat.actions') ?></th></tr></thead>
        <tbody>
        <?php foreach ($reviews as $r): ?>
            <tr class="<?= $r['is_hidden'] ? 'is-hidden' : '' ?>">
                <td><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?><span class="desc"><?= e($r['order_ref'] ?? $r['rental_ref'] ?? '') ?></span></td>
                <td><?= e($r['shop_name'] ?: $r['seller_name']) ?></td>
                <td><?= e($r['reviewer_name']) ?></td>
                <td><span class="stars-static"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span>
                    <?php if ($r['comment']): ?><span class="desc">« <?= e($r['comment']) ?> »</span><?php endif; ?>
                    <?php if ($r['is_hidden']): ?><span class="badge badge-off"><?= t('rev.hidden') ?></span><?php endif; ?></td>
                <td class="actions-col">
                    <?php $button((int) $r['id'], $r['is_hidden'] ? 'show' : 'hide', ''); ?>
                    <?php $button((int) $r['id'], 'delete', 'mini-danger'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
