<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

if (is_logged_in()) {
    redirect('index.php');
}

// Errors and old values sent back by the action
$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['old'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old']);

$val = fn (string $key, string $default = ''): string => e($old[$key] ?? $default);
$err = fn (string $key): string => isset($errors[$key])
    ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
$cls = fn (string $key): string => isset($errors[$key]) ? 'field invalid' : 'field';
$role = $old['role'] ?? 'buyer';

$page_title = t('reg.title');
require __DIR__ . '/layout/header.php';
?>

<section class="form-page">
    <div class="form-card">
        <div class="section-script"><?= t('reg.script') ?></div>
        <h1><?= t('reg.title') ?></h1>
        <p class="form-intro"><?= t('reg.intro') ?></p>

        <form id="register-form" method="post" action="<?= url('actions/register_action.php') ?>" novalidate data-validate>
            <?= csrf_field() ?>

            <!-- Anti-robot trap: hidden from humans -->
            <div class="visually-hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <!-- I want to -->
            <div class="<?= $cls('role') ?>">
                <label><?= t('reg.role') ?></label>
                <label class="choice"><input type="radio" name="role" value="buyer" <?= $role === 'buyer' ? 'checked' : '' ?>> <?= t('reg.role_buyer') ?></label>
                <label class="choice"><input type="radio" name="role" value="seller" <?= $role === 'seller' ? 'checked' : '' ?>> <?= t('reg.role_seller') ?></label>
                <?= $err('role') ?>
            </div>

            <div class="<?= $cls('full_name') ?>">
                <label for="full_name"><?= t('reg.name') ?></label>
                <input type="text" id="full_name" name="full_name" value="<?= $val('full_name') ?>"
                       required maxlength="120" autocomplete="name"
                       data-rule="name" data-error="<?= t('reg.err_name') ?>">
                <?= $err('full_name') ?>
            </div>

            <div class="<?= $cls('email') ?>">
                <label for="email"><?= t('reg.email') ?></label>
                <input type="email" id="email" name="email" value="<?= $val('email') ?>"
                       required maxlength="190" autocomplete="email"
                       data-rule="email" data-error="<?= t('reg.err_email') ?>">
                <?= $err('email') ?>
            </div>

            <div class="<?= $cls('phone') ?>">
                <label for="phone"><?= t('reg.phone') ?></label>
                <input type="tel" id="phone" name="phone" value="<?= $val('phone') ?>"
                       required maxlength="20" placeholder="90 12 34 56" autocomplete="tel"
                       data-rule="phone" data-error="<?= t('reg.err_phone') ?>">
                <?= $err('phone') ?>
            </div>

            <div class="form-row">
                <div class="<?= $cls('region') ?>">
                    <label for="region"><?= t('reg.region') ?></label>
                    <select id="region" name="region" required data-error="<?= t('reg.err_region') ?>">
                        <option value=""><?= t('reg.region_choose') ?></option>
                        <?php foreach (UserController::REGIONS as $region): ?>
                            <option value="<?= e($region) ?>" <?= ($old['region'] ?? 'Niamey') === $region ? 'selected' : '' ?>><?= e($region) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('region') ?>
                </div>
                <div class="<?= $cls('city') ?>">
                    <label for="city"><?= t('reg.city') ?></label>
                    <input type="text" id="city" name="city" value="<?= $val('city') ?>"
                           maxlength="80" placeholder="<?= t('reg.city_placeholder') ?>" autocomplete="address-level2">
                    <?= $err('city') ?>
                </div>
            </div>

            <div class="<?= $cls('shop_name') ?>">
                <label for="shop_name"><?= t('reg.shop') ?></label>
                <input type="text" id="shop_name" name="shop_name" value="<?= $val('shop_name') ?>" maxlength="120">
                <div class="help"><?= t('reg.shop_help') ?></div>
                <?= $err('shop_name') ?>
            </div>

            <div class="form-row">
                <div class="<?= $cls('password') ?>">
                    <label for="password"><?= t('reg.password') ?></label>
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" required
                               minlength="<?= PASSWORD_MIN_LENGTH ?>" maxlength="72" autocomplete="new-password"
                               data-rule="password" data-min="<?= PASSWORD_MIN_LENGTH ?>"
                               data-error="<?= t('reg.err_password', ['min' => PASSWORD_MIN_LENGTH]) ?>">
                        <!-- Eye button: shows / hides the password -->
                        <button type="button" class="toggle-password" data-toggle-password="password"
                                aria-label="<?= t('reg.show_password') ?>" aria-pressed="false"
                                data-label-show="<?= t('reg.show_password') ?>" data-label-hide="<?= t('reg.hide_password') ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></button>
                    </div>
                    <?= $err('password') ?>
                </div>
                <div class="<?= $cls('password_confirm') ?>">
                    <label for="password_confirm"><?= t('reg.confirm') ?></label>
                    <div class="password-wrap">
                        <input type="password" id="password_confirm" name="password_confirm" required
                               maxlength="72" autocomplete="new-password"
                               data-rule="match" data-match="password" data-error="<?= t('reg.err_confirm') ?>">
                        <button type="button" class="toggle-password" data-toggle-password="password_confirm"
                                aria-label="<?= t('reg.show_password') ?>" aria-pressed="false"
                                data-label-show="<?= t('reg.show_password') ?>" data-label-hide="<?= t('reg.hide_password') ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></button>
                    </div>
                    <!-- Live message: passwords match or not -->
                    <div class="match-status" aria-live="polite"
                         data-match-status="password_confirm" data-match-with="password"
                         data-ok="<?= t('reg.match_ok') ?>" data-bad="<?= t('reg.match_bad') ?>"></div>
                    <?= $err('password_confirm') ?>
                </div>
            </div>

            <!-- Live checklist: each rule turns green while the password is typed -->
            <ul class="pw-rules" data-password-rules="password" aria-live="polite">
                <li data-check="length" data-min="<?= PASSWORD_MIN_LENGTH ?>"><?= t('reg.rule_length', ['min' => PASSWORD_MIN_LENGTH]) ?></li>
                <li data-check="letter"><?= t('reg.rule_letter') ?></li>
                <li data-check="number"><?= t('reg.rule_number') ?></li>
            </ul>

            <button type="submit" class="btn btn-solid btn-block" data-loading="<?= t('reg.loading') ?>"><?= t('reg.submit') ?></button>
        </form>

        <!-- "Continue with Google" button -->
        <?php require __DIR__ . '/layout/google_button.php'; ?>

        <p class="form-footer"><?= t('reg.have_account') ?> <a href="<?= url('views/login.php') ?>"><?= t('nav.login') ?></a></p>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
