<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/AdminController.php';

require_admin();
$controller = new AdminController();
$logs = $controller->getLogs();
$failed = $controller->getFailedLogins();

$page_title = t('adm.logs_title');
$admin_page = 'logs';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('adm.logs_title') ?></h1>
        <p class="admin-intro"><?= t('adm.logs_intro') ?></p>
    </div>

    <h2 class="admin-group"><?= t('adm.logs_actions') ?></h2>
    <?php if (!$logs): ?>
        <div class="empty-card"><p><?= t('adm.list_none') ?></p></div>
    <?php else: ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th><?= t('adm.col_date') ?></th><th><?= t('adm.col_action') ?></th><th><?= t('adm.col_details') ?></th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                <td><strong><?= e($l['action']) ?></strong><?php if ($l['target_type']): ?><span class="desc"><?= e($l['target_type']) ?> #<?= (int) $l['target_id'] ?></span><?php endif; ?></td>
                <td><?= e($l['details']) ?></td>
                <td><?= e($l['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>

    <h2 class="admin-group"><?= t('adm.logs_failed') ?></h2>
    <?php if (!$failed): ?>
        <div class="empty-card"><p><?= t('adm.list_none') ?></p></div>
    <?php else: ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th><?= t('adm.col_date') ?></th><th><?= t('reg.email') ?></th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($failed as $f): ?>
            <tr><td><?= e(date('d/m/Y H:i', strtotime($f['attempted_at']))) ?></td><td><?= e($f['email']) ?></td><td><?= e($f['ip_address']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
