<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/AdminController.php';

require_admin();
$controller = new AdminController();
$tab = in_array($_GET['tab'] ?? '', AdminController::USER_TABS, true) ? $_GET['tab'] : '';
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$counts = $controller->countUsers();
$users = $controller->getUsers($tab, $search);

$page_title = t('adm.users_title');
$admin_page = 'users';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('adm.users_title') ?></h1>
        <p class="admin-intro"><?= t('adm.users_intro') ?></p>
    </div>

    <nav class="tabs" aria-label="<?= t('adm.users_title') ?>">
        <a href="<?= url('views/admin/admin_users.php') ?>" class="<?= $tab === '' ? 'active' : '' ?>"><?= t('adm.users_all') ?> <span class="count"><?= array_sum(array_map('intval', $counts)) ?></span></a>
        <?php foreach (AdminController::USER_TABS as $t): ?>
            <a href="<?= url('views/admin/admin_users.php?tab=' . $t) ?>" class="<?= $tab === $t ? 'active' : '' ?>"><?= t('adm.role_' . $t) ?> <span class="count"><?= (int) ($counts[$t] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </nav>

    <form method="get" action="<?= url('views/admin/admin_users.php') ?>" class="admin-search">
        <?php if ($tab !== ''): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
        <label class="visually-hidden" for="q"><?= t('adm.users_search') ?></label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="<?= t('adm.users_search') ?>">
        <button type="submit" class="btn btn-line btn-small"><?= t('search.button') ?></button>
    </form>

    <?php if (!$users): ?>
        <div class="empty-card"><p><?= t('adm.list_none') ?></p></div>
    <?php else: ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th><?= t('adm.col_name') ?></th><th><?= t('adm.col_contact') ?></th><th><?= t('adm.col_role') ?></th><th><?= t('adm.col_dates_user') ?></th><th><?= t('adm.col_status') ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr class="<?= $u['status'] === 'suspended' ? 'is-hidden' : '' ?>">
                <td><strong><?= e($u['full_name']) ?></strong><?php if ($u['shop_name']): ?><span class="slug"><?= e($u['shop_name']) ?></span><?php endif; ?></td>
                <td><?= e($u['email']) ?><span class="desc"><?= e($u['phone']) ?> · <?= e($u['region']) ?><?= $u['with_google'] ? ' · Google' : '' ?></span></td>
                <td><?= t('adm.role_' . $u['role']) ?><?php if ($u['role'] === 'seller'): ?><span class="desc"><?= t('adm.outfits_in_shop', ['n' => (int) $u['outfits']]) ?></span><?php endif; ?></td>
                <td><span class="desc"><?= t('adm.joined') ?> <?= e(date('d/m/Y', strtotime($u['created_at']))) ?></span>
                    <span class="desc"><?= t('adm.last_login') ?> <?= $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime($u['last_login_at']))) : '—' ?></span></td>
                <td><span class="badge <?= $u['status'] === 'active' ? 'badge-ok' : 'badge-rejected' ?>"><?= t('adm.user_' . $u['status']) ?></span></td>
                <td class="actions-col">
                    <?php if ($u['status'] === 'active'): ?>
                        <form method="post" action="<?= url('actions/admin_user_action.php') ?>" class="money-form" novalidate data-validate
                              data-confirm="<?= t('adm.confirm_suspend', ['name' => $u['full_name']]) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="op" value="suspend"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <label class="visually-hidden" for="why-<?= (int) $u['id'] ?>"><?= t('adm.suspend_reason') ?></label>
                            <input type="text" id="why-<?= (int) $u['id'] ?>" name="reason" required maxlength="255" placeholder="<?= t('adm.suspend_reason') ?>"
                                   data-rule="length" data-min="5" data-max="255" data-error="<?= t('adm.err_reason_user') ?>">
                            <button type="submit" class="mini-btn mini-danger"><?= t('adm.suspend') ?></button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= url('actions/admin_user_action.php') ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="op" value="activate"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <button type="submit" class="mini-btn"><?= t('adm.activate') ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
