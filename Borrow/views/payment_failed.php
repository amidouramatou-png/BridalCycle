<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/PaymentController.php';

require_login();
$ref = is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '';
$payment = (new PaymentController())->getPayment($ref, (int) $_SESSION['user_id']);
if ($payment === null || $payment['status'] !== 'failed') {
    redirect('views/account/my_account.php');
}
$reasonKey = str_starts_with((string) $payment['failure_reason'], 'pay.') ? $payment['failure_reason'] : 'pay.reason_error';

$page_title = t('pay.fail_title');
require __DIR__ . '/layout/header.php';
?>
<section class="wrap form-page">
    <div class="form-card pay-result">
        <div class="pay-icon pay-icon-bad">✕</div>
        <h1><?= t('pay.fail_title') ?></h1>
        <p class="form-intro"><?= t($reasonKey) ?> <?= t('pay.fail_text') ?></p>
        <a class="btn btn-solid btn-block" href="<?= url('views/payment.php?type=' . urlencode($payment['payable_type']) . '&id=' . (int) $payment['payable_id']) ?>"><?= t('pay.try_again') ?></a>
        <p class="form-footer"><?= t('pay.ref') ?> <?= e($payment['transaction_ref']) ?></p>
    </div>
</section>
<?php require __DIR__ . '/layout/footer.php'; ?>
