<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/AdminController.php';

require_admin();
$controller = new AdminController();
$tab = in_array($_GET['tab'] ?? '', AdminController::RENTAL_TABS, true) ? $_GET['tab'] : 'requested';
$counts = $controller->countRentals();
$rentals = $controller->getRentals($tab);

$page_title = t('adm.rentals_title');
$admin_page = 'rentals';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('adm.rentals_title') ?></h1>
        <p class="admin-intro"><?= t('adm.rentals_intro') ?></p>
    </div>

    <nav class="tabs" aria-label="<?= t('adm.rentals_title') ?>">
        <?php foreach (AdminController::RENTAL_TABS as $t): ?>
            <a href="<?= url('views/admin/admin_rentals.php?tab=' . $t) ?>" class="<?= $tab === $t ? 'active' : '' ?>">
                <?= t('rent.status_' . $t) ?> <span class="count"><?= (int) ($counts[$t] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$rentals): ?>
        <div class="empty-card"><p><?= t('adm.list_none') ?></p></div>
    <?php else: ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th><?= t('adm.col_ref') ?></th><th><?= t('rent.bride') ?></th><th><?= t('adm.seller') ?></th><th><?= t('adm.col_dates') ?></th><th><?= t('adm.col_amount') ?></th><th><?= t('adm.col_status') ?></th></tr></thead>
        <tbody>
        <?php foreach ($rentals as $r): ?>
            <tr>
                <td><strong><?= e($r['rental_ref']) ?></strong><span class="desc"><?= e($r['title']) ?></span></td>
                <td><?= e($r['bride_name']) ?><span class="desc"><?= e($r['phone']) ?></span></td>
                <td><?= e($r['shop_name'] ?: $r['seller_name']) ?></td>
                <td><?= e(date('d/m/Y', strtotime($r['start_date']))) ?> → <?= e(date('d/m/Y', strtotime($r['end_date']))) ?>
                    <span class="desc"><?= (int) $r['days'] ?> <?= t('rent.days') ?><?= $r['event_date'] ? ' · ' . t('rent.event_short') . ' ' . e(date('d/m/Y', strtotime($r['event_date']))) : '' ?></span></td>
                <td class="money-cell"><strong><?= money((int) $r['total_due']) ?></strong>
                    <span class="desc"><?= t('rent.rent_total') ?> <?= money((int) $r['rent_total']) ?> · <?= t('prod.deposit') ?> <?= money((int) $r['deposit']) ?></span>
                    <span class="desc"><?= t('adm.commission_short', ['amount' => money((int) $r['commission'])]) ?></span></td>
                <td><span class="badge badge-r-<?= e($r['status']) ?>"><?= t('rent.status_' . $r['status']) ?></span>
                    <?php if ($r['payout_status'] !== 'not_due'): ?><span class="desc"><?= t('adm.po_' . $r['payout_status']) ?></span><?php endif; ?>
                    <?php if (in_array($r['status'], ['paid', 'picked_up', 'returned'], true)): ?><span class="desc"><?= t('adm.dep_' . $r['deposit_status']) ?></span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
