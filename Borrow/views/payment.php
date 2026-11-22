<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/PaymentController.php';

require_login();
$controller = new PaymentController();
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$payable = $id ? $controller->getPayable($type, $id, (int) $_SESSION['user_id']) : null;

if ($payable === null) {
    $_SESSION['error'] = msg('pay.err_not_found');
    redirect('views/account/my_account.php');
}
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);
$back = ['order' => 'views/account/my_orders.php', 'rental' => 'views/account/my_rentals.php', 'feature' => 'views/seller/seller_product.php'][$type];

$page_title = t('pay.title');
require __DIR__ . '/layout/header.php';
?>

<section class="wrap seller-page pay-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('pay.script') ?></div>
            <h1><?= t('pay.title') ?></h1>
            <p class="admin-intro"><?= t('pay.for_' . $type, ['ref' => $payable['ref']]) ?></p>
        </div>
        <a class="btn btn-line" href="<?= url($back) ?>"><?= t('pay.back') ?></a>
    </div>

    <?php if (!$payable['payable']): ?>
        <div class="empty-card"><p><?= t('pay.err_not_payable') ?></p></div>
    <?php else: ?>

    <?php if ($controller->isTestMode()): ?>
        <!-- Test mode (simulation, or iPay sandbox): shown so the demo is clear -->
        <div class="sim-banner">
            <strong><?= t('pay.sim_title') ?></strong> <?= $controller->isSimulation() ? t('pay.sim_text') : t('pay.sandbox_text') ?>
            <ul>
                <li><code>40410000000</code> → <?= t('pay.sim_ok') ?></li>
                <li><code>40410000004</code> → <?= t('pay.reason_funds') ?></li>
                <li><code>40410000006</code> → <?= t('pay.reason_refused') ?></li>
                <li><code>40410000008</code> → <?= t('pay.sim_pending') ?></li>
            </ul>
        </div>
    <?php endif; ?>

    <div class="cart-layout">
        <form method="post" action="<?= url('actions/process_payment_action.php') ?>" class="outfit-form" novalidate data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="id" value="<?= (int) $payable['id'] ?>">

            <fieldset class="form-section">
                <legend><span>1</span> <?= t('pay.choose') ?></legend>
                <div class="pay-methods">
                    <?php $first = true; foreach (PaymentController::METHODS as $key => [$name, $ptype, $listed]): $enabled = $controller->isAvailable($key); ?>
                        <label class="pay-method<?= $enabled ? '' : ' is-disabled' ?>">
                            <input type="radio" name="method" value="<?= e($key) ?>" <?= $enabled ? '' : 'disabled' ?>
                                   <?= $enabled && (($old['method'] ?? '') === $key || (!isset($old['method']) && $first)) ? 'checked' : '' ?>>
                            <?php $logo_key = $key; $logo_name = $name; require __DIR__ . '/layout/payment_logo.php'; ?>
                            <?php if ($logo_file): ?><span class="pay-name"><?= e($name) ?></span><?php endif; ?>
                            <?php if (!$enabled): ?><span class="pay-soon"><?= t('pay.soon') ?></span><?php endif; ?>
                        </label>
                    <?php if ($enabled) { $first = false; } endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend><span>2</span> <?= t('pay.phone') ?></legend>
                <div class="field">
                    <label for="msisdn"><?= t('pay.phone_label') ?></label>
                    <input type="tel" id="msisdn" name="msisdn" value="<?= e($old['msisdn'] ?? $payable['phone']) ?>" required maxlength="20" autocomplete="tel"
                           data-error="<?= t('reg.err_phone') ?>">
                    <div class="help"><?= t('pay.phone_help') ?></div>
                </div>
            </fieldset>

            <button type="submit" class="btn btn-solid" data-loading="<?= t('pay.sending') ?>"><?= t('pay.button', ['amount' => money((int) $payable['amount'])]) ?></button>
        </form>

        <aside class="cart-summary">
            <h2><?= t('cart.summary') ?></h2>
            <div class="sum-line"><span><?= e($payable['ref']) ?></span><span><?= $type !== 'order' ? e($payable['title']) : '' ?></span></div>
            <div class="sum-line grand"><span><?= t('rent.total_due') ?></span><strong><?= money((int) $payable['amount']) ?></strong></div>
            <p class="safe-note"><?= t('pay.safe') ?></p>
        </aside>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
