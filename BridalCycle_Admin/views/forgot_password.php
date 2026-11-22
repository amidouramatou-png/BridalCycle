<?php
require_once dirname(__DIR__) . '/core/core.php';

if (is_logged_in()) {
    redirect('index.php');
}

$page_title = t('reset.forgot_title');
require __DIR__ . '/layout/header.php';
?>

<section class="form-page">
    <div class="form-card">
        <div class="section-script"><?= t('reset.script') ?></div>
        <h1><?= t('reset.forgot_title') ?></h1>
        <p class="form-intro"><?= t('reset.forgot_intro') ?></p>

        <form method="post" action="<?= url('actions/forgot_password_action.php') ?>" novalidate data-validate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="email"><?= t('reg.email') ?></label>
                <input type="email" id="email" name="email" required maxlength="190" autocomplete="email" autofocus
                       data-rule="email" data-error="<?= t('reg.err_email') ?>">
            </div>
            <button type="submit" class="btn btn-solid btn-block" data-loading="<?= t('reset.sending') ?>"><?= t('reset.send') ?></button>
        </form>

        <p class="form-footer"><a href="<?= url('views/login.php') ?>"><?= t('reset.back_login') ?></a></p>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
