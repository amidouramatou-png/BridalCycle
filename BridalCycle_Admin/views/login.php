<?php
require_once dirname(__DIR__) . '/core/core.php';

if (is_logged_in()) {
    redirect('index.php');
}

$old_email = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);

$page_title = t('login.title');
require __DIR__ . '/layout/header.php';
?>

<section class="form-page">
    <div class="form-card">
        <div class="section-script"><?= t('login.script') ?></div>
        <h1><?= t('login.title') ?></h1>
        <p class="form-intro"><?= t('login.intro') ?></p>

        <form id="login-form" method="post" action="<?= url('actions/login_action.php') ?>" novalidate data-validate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="email"><?= t('reg.email') ?></label>
                <input type="email" id="email" name="email" value="<?= e($old_email) ?>"
                       required maxlength="190" autocomplete="email" <?= $old_email === '' ? 'autofocus' : '' ?>
                       data-rule="email" data-error="<?= t('reg.err_email') ?>">
            </div>

            <div class="field">
                <label for="password"><?= t('reg.password') ?></label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" required maxlength="72"
                           autocomplete="current-password" <?= $old_email !== '' ? 'autofocus' : '' ?>
                           data-error="<?= t('login.err_password_empty') ?>">
                    <button type="button" class="toggle-password" data-toggle-password="password"
                            aria-label="<?= t('reg.show_password') ?>" aria-pressed="false"
                            data-label-show="<?= t('reg.show_password') ?>" data-label-hide="<?= t('reg.hide_password') ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></button>
                </div>
            </div>

            <p class="forgot-link"><a href="<?= url('views/forgot_password.php') ?>"><?= t('reset.forgot_link') ?></a></p>

            <button type="submit" class="btn btn-solid btn-block" data-loading="<?= t('login.loading') ?>"><?= t('login.submit') ?></button>
        </form>

        <?php require __DIR__ . '/layout/google_button.php'; ?>

        <p class="form-footer"><?= t('login.no_account') ?> <a href="<?= url('views/register.php') ?>"><?= t('login.create') ?></a></p>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
