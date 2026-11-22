<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/UserController.php';

require_login();
$user = (new UserController())->getUser((int) $_SESSION['user_id']);
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);
$err = fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
$cls = fn (string $key): string => isset($errors[$key]) ? 'field invalid' : 'field';
$eye = fn (string $id): string => '<button type="button" class="toggle-password" data-toggle-password="' . $id . '" aria-label="' . t('reg.show_password')
    . '" aria-pressed="false" data-label-show="' . t('reg.show_password') . '" data-label-hide="' . t('reg.hide_password') . '"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></button>';

$page_title = t('acc.pass_title');
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="form-page">
    <div class="form-card">
        <div class="section-script"><?= t('acc.script') ?></div>
        <h1><?= t('acc.pass_title') ?></h1>
        <?php if ($user['google_id']): ?><p class="form-intro"><?= t('acc.pass_google') ?></p><?php endif; ?>

        <form method="post" action="<?= url('actions/change_password_action.php') ?>" novalidate data-validate>
            <?= csrf_field() ?>
            <div class="<?= $cls('current_password') ?>">
                <label for="current_password"><?= t('acc.current_password') ?></label>
                <div class="password-wrap">
                    <input type="password" id="current_password" name="current_password" required maxlength="72" autocomplete="current-password"
                           data-error="<?= t('acc.err_current') ?>">
                    <?= $eye('current_password') ?>
                </div>
                <?= $err('current_password') ?>
            </div>
            <div class="<?= $cls('password') ?>">
                <label for="password"><?= t('acc.new_password') ?></label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>" maxlength="72" autocomplete="new-password"
                           data-rule="password" data-min="<?= PASSWORD_MIN_LENGTH ?>" data-error="<?= t('reg.err_password', ['min' => PASSWORD_MIN_LENGTH]) ?>">
                    <?= $eye('password') ?>
                </div>
                <?= $err('password') ?>
            </div>
            <ul class="pw-rules" data-password-rules="password" aria-live="polite">
                <li data-check="length" data-min="<?= PASSWORD_MIN_LENGTH ?>"><?= t('reg.rule_length', ['min' => PASSWORD_MIN_LENGTH]) ?></li>
                <li data-check="letter"><?= t('reg.rule_letter') ?></li>
                <li data-check="number"><?= t('reg.rule_number') ?></li>
            </ul>
            <div class="<?= $cls('password_confirm') ?>">
                <label for="password_confirm"><?= t('reg.confirm') ?></label>
                <div class="password-wrap">
                    <input type="password" id="password_confirm" name="password_confirm" required maxlength="72" autocomplete="new-password"
                           data-rule="match" data-match="password" data-error="<?= t('reg.err_confirm') ?>">
                    <?= $eye('password_confirm') ?>
                </div>
                <div class="match-status" aria-live="polite" data-match-status="password_confirm" data-match-with="password"
                     data-ok="<?= t('reg.match_ok') ?>" data-bad="<?= t('reg.match_bad') ?>"></div>
                <?= $err('password_confirm') ?>
            </div>
            <button type="submit" class="btn btn-solid btn-block"><?= t('acc.pass_save') ?></button>
        </form>
        <p class="form-footer"><a href="<?= url('views/account/my_account.php') ?>"><?= t('acc.back') ?></a></p>
    </div>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
