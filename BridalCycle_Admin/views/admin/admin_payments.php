<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/AdminController.php';

require_admin();
$controller = new AdminController();
$tab = in_array($_GET['tab'] ?? '', AdminController::MONEY_TABS, true) ? $_GET['tab'] : 'payouts';
$s = $controller->getStats();
$counts = ['payouts' => $s['to_pay_count'], 'deposits' => $s['deposits_back'], 'refunds' => $s['refunds']];
$methods = ['airtel_money' => 'Airtel Money', 'zamani_cash' => 'Zamani Cash', 'moov_money' => 'Moov Money',
            'mynita' => 'MyNita', 'amanata' => 'Amanata', 'wave' => 'Wave'];

$page_title = t('adm.money_title');
$admin_page = 'money';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';

// The small "How was it sent?" form, used on every line
$moneyForm = function (string $op, int $id, int $amount, array $extra = []): void { ?>
    <form method="post" action="<?= url('actions/admin_money_action.php') ?>" class="money-form" novalidate data-validate
          data-confirm="<?= t('adm.confirm_' . $op, ['amount' => money($amount)]) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="<?= e($op) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php foreach ($extra as $name => $value): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"><?php endforeach; ?>
        <label class="visually-hidden" for="how-<?= e($op) ?>-<?= $id ?>-<?= (int) ($extra['seller_id'] ?? 0) ?>"><?= t('adm.how') ?></label>
        <input type="text" id="how-<?= e($op) ?>-<?= $id ?>-<?= (int) ($extra['seller_id'] ?? 0) ?>" name="how" required maxlength="100"
               placeholder="<?= t('adm.how_placeholder') ?>" data-rule="length" data-min="3" data-max="100" data-error="<?= t('adm.err_how') ?>">
        <button type="submit" class="btn btn-solid btn-small"><?= t('adm.btn_' . $op) ?></button>
    </form>
<?php };
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('adm.money_title') ?></h1>
        <p class="admin-intro"><?= t('adm.money_intro') ?></p>
    </div>

    <nav class="tabs" aria-label="<?= t('adm.money_title') ?>">
        <?php foreach (AdminController::MONEY_TABS as $t): ?>
            <a href="<?= url('views/admin/admin_payments.php?tab=' . $t) ?>" class="<?= $tab === $t ? 'active' : '' ?>">
                <?= t('adm.tab_' . $t) ?><?php if (isset($counts[$t])): ?> <span class="count"><?= (int) $counts[$t] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'payouts'): $lines = $controller->getPayoutsDue(); ?>
        <p class="admin-intro"><?= t('adm.payouts_intro') ?></p>
        <?php if (!$lines): ?><div class="empty-card"><p><?= t('adm.payouts_none') ?></p></div><?php else: ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th><?= t('adm.col_ref') ?></th><th><?= t('adm.seller') ?></th><th><?= t('adm.col_amount') ?></th><th><?= t('adm.col_send') ?></th></tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><strong><?= e($l['ref']) ?></strong>
                        <span class="desc"><?= $l['type'] === 'order' ? t('adm.type_order') : t('adm.type_rental') ?> · <?= e($l['titles']) ?></span>
                        <span class="desc"><?= t('adm.since') ?> <?= e(date('d/m/Y', strtotime($l['since']))) ?></span></td>
                    <td><?= e($l['shop_name'] ?: $l['full_name']) ?><span class="desc"><?= e($l['full_name']) ?> · <strong><?= e($l['phone']) ?></strong></span></td>
                    <td class="money-cell"><strong><?= money((int) $l['amount']) ?></strong>
                        <span class="desc"><?= t('adm.commission_kept', ['amount' => money((int) $l['commission'])]) ?></span>
                        <?php if ((int) ($l['deposit_kept'] ?? 0) > 0): ?><span class="desc"><?= t('com.incl_kept', ['amount' => money((int) $l['deposit_kept'])]) ?></span><?php endif; ?></td>
                    <td><?php $moneyForm('pay_seller', (int) $l['id'], (int) $l['amount'], ['type' => $l['type'], 'seller_id' => (int) $l['seller_id']]); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>

    <?php elseif ($tab === 'deposits'): $lines = $controller->getDepositsToReturn(); ?>
        <p class="admin-intro"><?= t('adm.deposits_intro') ?></p>
        <?php if (!$lines): ?><div class="empty-card"><p><?= t('adm.deposits_none') ?></p></div><?php else: ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th><?= t('adm.col_ref') ?></th><th><?= t('rent.bride') ?></th><th><?= t('adm.col_amount') ?></th><th><?= t('adm.col_send') ?></th></tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><strong><?= e($l['rental_ref']) ?></strong><span class="desc"><?= e($l['title']) ?></span>
                        <span class="desc"><?= t('adm.returned_on') ?> <?= e(date('d/m/Y', strtotime($l['updated_at']))) ?></span></td>
                    <td><?= e($l['bride_name']) ?><span class="desc"><strong><?= e($l['phone']) ?></strong></span></td>
                    <td class="money-cell"><strong><?= money((int) $l['back']) ?></strong>
                        <span class="desc"><?= t('adm.deposit_of', ['amount' => money((int) $l['deposit'])]) ?></span>
                        <?php if ((int) $l['deposit_kept'] > 0): ?>
                            <span class="desc"><?= t('adm.kept_by_seller', ['amount' => money((int) $l['deposit_kept'])]) ?><?= $l['seller_note'] ? ': « ' . e($l['seller_note']) . ' »' : '' ?></span>
                        <?php endif; ?></td>
                    <td><?php $moneyForm('deposit_back', (int) $l['id'], (int) $l['back']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>

    <?php elseif ($tab === 'refunds'): $lines = $controller->getRefundsDue(); ?>
        <p class="admin-intro"><?= t('adm.refunds_intro') ?></p>
        <?php if (!$lines): ?><div class="empty-card"><p><?= t('adm.refunds_none') ?></p></div><?php else: ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th><?= t('adm.col_ref') ?></th><th><?= t('rent.bride') ?></th><th><?= t('adm.col_amount') ?></th><th><?= t('adm.col_send') ?></th></tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><strong><?= e($l['transaction_ref']) ?></strong>
                        <span class="desc"><?= e($methods[$l['method']] ?? $l['method']) ?> · <?= e(date('d/m/Y H:i', strtotime($l['paid_at']))) ?></span>
                        <span class="desc"><?= e($l['failure_reason']) ?></span></td>
                    <td><?= e($l['full_name']) ?><span class="desc"><?= t('adm.paid_from') ?> <strong><?= e($l['msisdn']) ?></strong></span></td>
                    <td class="money-cell"><strong><?= money((int) $l['amount']) ?></strong></td>
                    <td><?php $moneyForm('refund', (int) $l['id'], (int) $l['amount']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>

    <?php elseif ($tab === 'payments'): $lines = $controller->getPayments(); ?>
        <?php if (!$lines): ?><div class="empty-card"><p><?= t('adm.payments_none') ?></p></div><?php else: ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th><?= t('adm.col_date') ?></th><th><?= t('adm.col_ref') ?></th><th><?= t('rent.bride') ?></th><th><?= t('adm.col_method') ?></th><th><?= t('adm.col_amount') ?></th><th><?= t('adm.col_status') ?></th></tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                    <td><strong><?= e($l['transaction_ref']) ?></strong><span class="desc"><?= $l['payable_type'] === 'order' ? t('adm.type_order') : t('adm.type_rental') ?> <?= e($l['ref']) ?></span></td>
                    <td><?= e($l['full_name']) ?></td>
                    <td><?= e($methods[$l['method']] ?? $l['method']) ?><span class="desc"><?= e($l['msisdn']) ?> · <?= e($l['provider']) ?></span></td>
                    <td class="money-cell"><?= money((int) $l['amount']) ?></td>
                    <td><span class="badge badge-pay-<?= e($l['status']) ?>"><?= t('adm.pay_' . $l['status']) ?></span>
                        <?php if ($l['failure_reason']): ?><span class="desc"><?= str_starts_with($l['failure_reason'], 'pay.') ? t($l['failure_reason']) : e($l['failure_reason']) ?></span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>

    <?php else: $lines = $controller->getReceipts(); ?>
        <?php if (!$lines): ?><div class="empty-card"><p><?= t('adm.receipts_none') ?></p></div><?php else: ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th><?= t('adm.col_date') ?></th><th><?= t('rc.title') ?></th><th><?= t('adm.col_to') ?></th><th><?= t('adm.col_amount') ?></th><th><?= t('adm.col_email') ?></th></tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                    <td><a href="<?= url('views/receipt.php?ref=' . urlencode($l['receipt_ref'])) ?>"><strong><?= e($l['receipt_ref']) ?></strong></a><span class="desc"><?= e($l['subject']) ?></span></td>
                    <td><?= t('adm.to_' . $l['recipient']) ?><span class="desc"><?= e($l['email']) ?></span></td>
                    <td class="money-cell"><?= money((int) $l['amount']) ?></td>
                    <td><span class="badge badge-mail-<?= e($l['email_status']) ?>"><?= t('adm.mail_' . $l['email_status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
