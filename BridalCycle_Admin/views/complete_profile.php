<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

if (is_logged_in()) {
    redirect('index.php');
}

$google = $_SESSION['google_pending'] ?? null;
if (!is_array($google) || time() - $google['time'] > 900) {
    unset($_SESSION['google_pending']);
    $_SESSION['error'] = msg('google.err_expired');
    redirect('views/login.php');
}

$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['old'] ?? ['full_name' => $google['name']];
unset($_SESSION['form_errors'], $_SESSION['old']);

$val = fn (string $key, string $default = ''): string => e($old[$key] ?? $default);
$err = fn (string $key): string => isset($errors[$key])
    ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
$cls = fn (string $key): string => isset($errors[$key]) ? 'field invalid' : 'field';
$role = $old['role'] ?? 'buyer';

$page_title = t('google.profile_title');
require __DIR__ . '/layout/header.php';
?>

<section class="form-page">
    <div class="form-card">
        <div class="section-script"><?= t('google.profile_script') ?></div>
        <h1><?= t('google.profile_title') ?></h1>
        <p class="form-intro"><?= t('google.profile_intro') ?></p>

        <!-- The email comes from Google: shown, but it cannot be changed -->
        <p class="google-email"><?= t('google.connected_as') ?> <strong><?= e($google['email']) ?></strong></p>

        <form id="profile-form" method="post" action="<?= url('actions/complete_profile_action.php') ?>" novalidate data-validate>
            <?= csrf_field() ?>

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

            <div class="<?= $cls('phone') ?>">
                <label for="phone"><?= t('reg.phone') ?></label>
                <input type="tel" id="phone" name="phone" value="<?= $val('phone') ?>"
                       required maxlength="20" placeholder="90 12 34 56" autocomplete="tel" autofocus
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

            <button type="submit" class="btn btn-solid btn-block" data-loading="<?= t('reg.loading') ?>"><?= t('google.profile_submit') ?></button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
