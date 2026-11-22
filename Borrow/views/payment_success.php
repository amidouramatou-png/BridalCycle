<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/PaymentController.php';

require_login();
$ref = is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '';
$payment = (new PaymentController())->getPayment($ref, (int) $_SESSION['user_id']);
if ($payment === null || $payment['status'] !== 'succeeded') {
    redirect('views/account/my_account.php');
}
$refund = $payment['failure_reason'] !== null && str_starts_with($payment['failure_reason'], 'REFUND DUE');
$next = ['order' => 'views/account/my_orders.php', 'rental' => 'views/account/my_rentals.php',
         'feature' => 'views/seller/seller_product.php'][$payment['payable_type']];

$page_title = t('pay.ok_title');
require __DIR__ . '/layout/header.php';
?>
<section class="wrap form-page">
    <div class="form-card pay-result">
        <?php if ($refund): ?>
            <div class="pay-icon pay-icon-bad">!</div>
            <h1><?= t('pay.refund_title') ?></h1>
            <p class="form-intro"><?= t('pay.refund_text', ['amount' => money((int) $payment['amount'])]) ?></p>
        <?php else: ?>
            <div class="section-script"><?= t('pay.ok_script') ?></div>
            <div class="pay-icon pay-icon-ok">✓</div>
            <h1><?= t('pay.ok_title') ?></h1>
            <p class="form-intro"><?= t('pay.ok_' . $payment['payable_type'], ['amount' => money((int) $payment['amount'])]) ?></p>
        <?php endif; ?>
        <a class="btn btn-solid btn-block" href="<?= url($next) ?>"><?= t('pay.see_' . $payment['payable_type']) ?></a>
        <p class="form-footer"><?= t('pay.ref') ?> <?= e($payment['transaction_ref']) ?> · <?= e(PaymentController::METHODS[$payment['method']][0]) ?></p>
    </div>
</section>
<?php require __DIR__ . '/layout/footer.php'; ?>
