<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/CartController.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

require_buyer();
$bag = (new CartController())->getBag((int) $_SESSION['user_id']);
if (!$bag['items']) {
    redirect('views/cart.php');
}
$user = (new UserController())->getUser((int) $_SESSION['user_id']);

$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['old'] ?? ['delivery_method' => 'pickup', 'phone' => $user['phone']];
unset($_SESSION['form_errors'], $_SESSION['old']);
$err = fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
$cls = fn (string $key): string => isset($errors[$key]) ? 'field invalid' : 'field';
$method = $old['delivery_method'] ?? 'pickup';

$page_title   = t('order.checkout_title');
$page_scripts = ['js/cart.js'];
require __DIR__ . '/layout/header.php';
?>

<section class="wrap seller-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('order.checkout_script') ?></div>
            <h1><?= t('order.checkout_title') ?></h1>
        </div>
        <a class="btn btn-line" href="<?= url('views/cart.php') ?>"><?= t('order.back_bag') ?></a>
    </div>

    <div class="cart-layout">
        <form method="post" action="<?= url('actions/checkout_action.php') ?>" class="outfit-form" novalidate data-validate>
            <?= csrf_field() ?>
            <fieldset class="form-section">
                <legend><span>1</span> <?= t('order.how') ?></legend>
                <div class="<?= $cls('delivery_method') ?>">
                    <label class="choice choice-strong"><input type="radio" name="delivery_method" value="pickup" <?= $method === 'pickup' ? 'checked' : '' ?> data-address-toggle> <?= t('order.pickup') ?></label>
                    <p class="help"><?= t('order.pickup_help') ?></p>
                    <label class="choice choice-strong"><input type="radio" name="delivery_method" value="delivery" <?= $method === 'delivery' ? 'checked' : '' ?> data-address-toggle> <?= t('order.delivery') ?><?= DELIVERY_FEE > 0 ? ' (+ ' . money(DELIVERY_FEE) . ')' : '' ?></label>
                    <p class="help"><?= t('order.delivery_help') ?></p>
                    <?= $err('delivery_method') ?>
                </div>
                <div class="<?= $cls('delivery_address') ?>" id="address-block">
                    <label for="delivery_address"><?= t('order.address') ?></label>
                    <textarea id="delivery_address" name="delivery_address" rows="2" maxlength="255" required autocomplete="street-address"
                              placeholder="<?= t('order.address_placeholder') ?>"
                              data-rule="length" data-min="10" data-max="255" data-error="<?= t('order.err_address') ?>"><?= e($old['delivery_address'] ?? '') ?></textarea>
                    <?= $err('delivery_address') ?>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend><span>2</span> <?= t('order.contact') ?></legend>
                <div class="<?= $cls('phone') ?>">
                    <label for="phone"><?= t('reg.phone') ?></label>
                    <input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" required maxlength="20" autocomplete="tel"
                           data-rule="phone" data-error="<?= t('reg.err_phone') ?>">
                    <div class="help"><?= t('order.phone_help') ?></div>
                    <?= $err('phone') ?>
                </div>
            </fieldset>

            <button type="submit" class="btn btn-solid" data-loading="<?= t('rent.sending') ?>"><?= t('order.place') ?></button>
        </form>

        <aside class="cart-summary">
            <h2><?= t('cart.summary') ?></h2>
            <?php foreach ($bag['items'] as $item): ?>
                <div class="sum-line"><span><?= e($item['title']) ?> × <?= (int) $item['quantity'] ?></span><span><?= money((int) $item['line_total']) ?></span></div>
            <?php endforeach; ?>
            <!-- Delivery fee: shown by cart.js only when "Delivery" is chosen -->
            <div class="sum-line" data-fee-line <?= $method === 'delivery' ? '' : 'hidden' ?>><span><?= t('order.delivery_fee') ?></span><span><?= money(DELIVERY_FEE) ?></span></div>
            <div class="sum-line grand"><span><?= t('rent.total_due') ?></span>
                <strong data-grand data-base="<?= (int) $bag['total'] ?>" data-fee="<?= (int) DELIVERY_FEE ?>"
                        data-pickup="<?= e(money($bag['total'])) ?>" data-delivery="<?= e(money($bag['total'] + DELIVERY_FEE)) ?>"><?= money($bag['total'] + ($method === 'delivery' ? DELIVERY_FEE : 0)) ?></strong></div>
            <p class="safe-note"><?= t('cart.safe') ?></p>
        </aside>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
