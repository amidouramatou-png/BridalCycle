<?php
$logo_file = null;
foreach (['png', 'webp', 'jpg', 'jpeg', 'svg'] as $ext) {
    if (is_file(dirname(dirname(__DIR__)) . '/images/payments/' . $logo_key . '.' . $ext)) {
        $logo_file = 'images/payments/' . $logo_key . '.' . $ext;
        break;
    }
}
?>
<?php if ($logo_file): ?>
    <img class="pay-logo" src="<?= url($logo_file) ?>" alt="<?= e($logo_name) ?>">
<?php else: ?>
    <span class="pay-logo pay-logo-text"><?= e($logo_name) ?></span>
<?php endif; ?>
