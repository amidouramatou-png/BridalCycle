<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/UserController.php';

require_login();
$user = (new UserController())->getUser((int) $_SESSION['user_id']);

$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['old'] ?? $user;
unset($_SESSION['form_errors'], $_SESSION['old']);
$val = fn (string $key): string => e((string) ($old[$key] ?? ''));
$err = fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
$cls = fn (string $key): string => isset($errors[$key]) ? 'field invalid' : 'field';

$page_title = t('acc.edit_title');
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="form-page">
    <div class="form-card">
        <div class="section-script"><?= t('acc.script') ?></div>
        <h1><?= t('acc.edit_title') ?></h1>
        <p class="form-intro"><?= t('acc.email_fixed', ['email' => $user['email']]) ?></p>

        <form method="post" action="<?= url('actions/update_account_action.php') ?>" novalidate data-validate>
            <?= csrf_field() ?>
            <div class="<?= $cls('full_name') ?>">
                <label for="full_name"><?= t('reg.name') ?></label>
                <input type="text" id="full_name" name="full_name" value="<?= $val('full_name') ?>" required maxlength="120" autocomplete="name"
                       data-rule="name" data-error="<?= t('reg.err_name') ?>">
                <?= $err('full_name') ?>
            </div>
            <div class="<?= $cls('phone') ?>">
                <label for="phone"><?= t('reg.phone') ?></label>
                <input type="tel" id="phone" name="phone" value="<?= $val('phone') ?>" required maxlength="20" placeholder="90 12 34 56" autocomplete="tel"
                       data-rule="phone" data-error="<?= t('reg.err_phone') ?>">
                <?= $err('phone') ?>
            </div>
            <div class="form-row">
                <div class="<?= $cls('region') ?>">
                    <label for="region"><?= t('reg.region') ?></label>
                    <select id="region" name="region" required data-error="<?= t('reg.err_region') ?>">
                        <?php foreach (UserController::REGIONS as $region): ?>
                            <option value="<?= e($region) ?>" <?= ($old['region'] ?? '') === $region ? 'selected' : '' ?>><?= e($region) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('region') ?>
                </div>
                <div class="<?= $cls('city') ?>">
                    <label for="city"><?= t('reg.city') ?></label>
                    <input type="text" id="city" name="city" value="<?= $val('city') ?>" maxlength="80" placeholder="<?= t('reg.city_placeholder') ?>">
                    <?= $err('city') ?>
                </div>
            </div>
            <?php if (is_seller()): ?>
                <div class="<?= $cls('shop_name') ?>">
                    <label for="shop_name"><?= t('reg.shop') ?></label>
                    <input type="text" id="shop_name" name="shop_name" value="<?= $val('shop_name') ?>" maxlength="120">
                    <?= $err('shop_name') ?>
                </div>
            <?php endif; ?>
            <!-- Privacy: other users see "Private seller" / "Private bride" -->
            <div class="field">
                <label class="choice"><input type="checkbox" name="hide_name" value="1" <?= !empty($old['hide_name']) ? 'checked' : '' ?>> <?= t('priv.hide_label') ?></label>
                <div class="help"><?= t('priv.hide_help') ?></div>
            </div>
            <button type="submit" class="btn btn-solid btn-block"><?= t('acc.save') ?></button>
        </form>
        <p class="form-footer"><a href="<?= url('views/account/my_account.php') ?>"><?= t('acc.back') ?></a></p>
    </div>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
