<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/UserController.php';

require_login();
if (is_admin()) {
    $_SESSION['error'] = msg('acc.err_admin');
    redirect('views/account/my_account.php');
}
$user = (new UserController())->getUser((int) $_SESSION['user_id']);

$page_title = t('acc.delete_title');
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="form-page">
    <div class="form-card">
        <div class="section-script"><?= t('acc.script') ?></div>
        <h1><?= t('acc.delete_title') ?></h1>
        <ul class="delete-facts">
            <li><?= t('acc.delete_1') ?></li>
            <li><?= t('acc.delete_2') ?></li>
            <li><?= t('acc.delete_3') ?></li>
        </ul>

        <form method="post" action="<?= url('actions/delete_account_action.php') ?>" novalidate data-validate
              data-confirm="<?= t('acc.delete_confirm') ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="confirm_email"><?= t('acc.type_email', ['email' => $user['email']]) ?></label>
                <input type="email" id="confirm_email" name="confirm_email" required maxlength="190" autocomplete="off"
                       data-rule="email" data-error="<?= t('reg.err_email') ?>">
            </div>
            <div class="field">
                <label for="password"><?= $user['google_id'] ? t('acc.password_google') : t('reg.password') ?></label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" maxlength="72" autocomplete="current-password" <?= $user['google_id'] ? '' : 'required' ?>
                           data-error="<?= t('acc.err_current') ?>">
                    <button type="button" class="toggle-password" data-toggle-password="password" aria-label="<?= t('reg.show_password') ?>" aria-pressed="false"
                            data-label-show="<?= t('reg.show_password') ?>" data-label-hide="<?= t('reg.hide_password') ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></button>
                </div>
            </div>
            <button type="submit" class="btn btn-danger btn-block"><?= t('acc.delete_button') ?></button>
        </form>
        <p class="form-footer"><a href="<?= url('views/account/my_account.php') ?>"><?= t('acc.keep') ?></a></p>
    </div>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
