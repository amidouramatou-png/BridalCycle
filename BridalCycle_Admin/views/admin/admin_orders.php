<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/AdminController.php';

require_admin();
$controller = new AdminController();
$tab = in_array($_GET['tab'] ?? '', AdminController::ORDER_TABS, true) ? $_GET['tab'] : 'paid';
$counts = $controller->countOrders();
$counts['paid'] = (int) ($counts['paid'] ?? 0) + (int) ($counts['ready'] ?? 0);
$orders = $controller->getOrders($tab);
if ($tab === 'paid') {
    $orders = array_merge($controller->getOrders('ready'), $orders);
}

$page_title = t('adm.orders_title');
$admin_page = 'orders';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('adm.orders_title') ?></h1>
        <p class="admin-intro"><?= t('adm.orders_intro') ?></p>
    </div>

    <nav class="tabs" aria-label="<?= t('adm.orders_title') ?>">
        <?php foreach (AdminController::ORDER_TABS as $t): ?>
            <a href="<?= url('views/admin/admin_orders.php?tab=' . $t) ?>" class="<?= $tab === $t ? 'active' : '' ?>">
                <?= t('order.status_' . $t) ?> <span class="count"><?= (int) ($counts[$t] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$orders): ?>
        <div class="empty-card"><p><?= t('adm.list_none') ?></p></div>
    <?php else: ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th><?= t('adm.col_ref') ?></th><th><?= t('rent.bride') ?></th><th><?= t('adm.col_items') ?></th><th><?= t('adm.col_amount') ?></th><th><?= t('adm.col_status') ?></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): $commission = array_sum(array_map('intval', array_column($o['items'], 'commission'))); ?>
            <tr>
                <td><strong><?= e($o['order_ref']) ?></strong><span class="desc"><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></span></td>
                <td><?= e($o['buyer_name']) ?><span class="desc"><?= e($o['buyer_email']) ?> · <?= e($o['phone']) ?></span>
                    <span class="desc"><?= $o['delivery_method'] === 'delivery' ? t('order.delivery') . ': ' . e($o['delivery_address']) : t('order.pickup') ?></span></td>
                <td>
                    <?php foreach ($o['items'] as $i): ?>
                        <span class="item-line"><?= e($i['title']) ?> × <?= (int) $i['quantity'] ?> — <?= e($i['shop_name'] ?: $i['seller_name']) ?>
                            <?php if (in_array($o['status'], ['paid', 'ready', 'completed'], true)): ?>
                                <span class="badge badge-po-<?= e($i['payout_status']) ?>"><?= t('adm.po_' . $i['payout_status']) ?></span>
                            <?php endif; ?></span>
                    <?php endforeach; ?>
                </td>
                <td class="money-cell"><strong><?= money((int) $o['total']) ?></strong>
                    <span class="desc"><?= t('adm.commission_short', ['amount' => money($commission)]) ?></span>
                    <?php if ((int) $o['delivery_fee'] > 0): ?><span class="desc"><?= t('order.delivery_fee') ?> <?= money((int) $o['delivery_fee']) ?></span><?php endif; ?></td>
                <td><span class="badge badge-o-<?= e($o['status']) ?>"><?= t('order.status_' . $o['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
