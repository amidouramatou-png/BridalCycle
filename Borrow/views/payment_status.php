<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/PaymentController.php';

require_login();
$ref = is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '';
$payment = (new PaymentController())->getPayment($ref, (int) $_SESSION['user_id']);
if ($payment === null) {
    redirect('views/account/my_account.php');
}
if ($payment['status'] !== 'pending') {
    redirect('views/' . ($payment['status'] === 'succeeded' ? 'payment_success' : 'payment_failed') . '.php?ref=' . urlencode($ref));
}

$page_title = t('pay.wait_title');
require __DIR__ . '/layout/header.php';
?>
<section class="wrap form-page">
    <div class="form-card pay-result">
        <div class="pay-icon pay-icon-wait">◷</div>
        <h1><?= t('pay.wait_title') ?></h1>
        <p class="form-intro"><?= t('pay.wait_text', ['amount' => money((int) $payment['amount']), 'method' => PaymentController::METHODS[$payment['method']][0]]) ?></p>
        <form method="post" action="<?= url('actions/check_payment_action.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="ref" value="<?= e($payment['transaction_ref']) ?>">
            <button type="submit" class="btn btn-solid btn-block"><?= t('pay.confirmed') ?></button>
        </form>
        <p class="form-footer"><?= t('pay.ref') ?> <?= e($payment['transaction_ref']) ?></p>
    </div>
</section>
<?php require __DIR__ . '/layout/footer.php'; ?>
