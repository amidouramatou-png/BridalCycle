<?php
$page_title = t('home.title');
require __DIR__ . '/layout/header.php';

// Real photo for a frame: looks for images/<name>.jpg,.webp or.png
$photo = function (string $name, string $alt): string {
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
        if (is_file(dirname(__DIR__) . '/images/' . $name . '.' . $ext) && filesize(dirname(__DIR__) . '/images/' . $name . '.' . $ext) > 0) {
            $lazy = $name === 'hero' ? '' : ' loading="lazy"';
            return '<img src="' . url('images/' . $name . '.' . $ext) . '" alt="' . e($alt) . '"' . $lazy . '>';
        }
    }
    return '';
};
?>

<!-- Hero -->
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow"><?= t('home.eyebrow') ?></span>
        <h1><?= t('home.hero_title') ?> <em><?= t('home.hero_em') ?></em></h1>
        <div class="hero-script"><?= t('home.hero_script') ?></div>
        <p><?= t('home.hero_text') ?></p>
        <div class="ctas">
            <a class="btn btn-solid" href="<?= url('views/all_products.php') ?>"><?= t('home.cta_shop') ?></a>
            <a class="btn btn-line" href="<?= url('views/borrow.php') ?>"><?= t('home.cta_borrow') ?></a>
        </div>
    </div>
    <div class="photo hero-photo">
        <?= $photo('hero', t('home.hero_alt')) ?>
        <div class="frame"></div>
        <div class="photo-script"><?= t('home.photo_script') ?></div>
    </div>
</section>

<!-- Manifesto -->
<section class="manifesto">
    <div class="section-script"><?= t('home.manifesto_script') ?></div>
    <div class="ornament"><span></span></div>
    <h2><?= t('home.manifesto_title') ?></h2>
    <div class="ornament"><span></span></div>
    <p><?= t('home.manifesto_text') ?></p>
</section>

<!-- THREE WAYS: buy, borrow, sell -->
<section class="wrap ways">
    <div class="way">
        <div class="photo arch ivory"><?= $photo('home/acquire', t('home.way1_title')) ?></div>
        <div class="num">i.</div>
        <h3><?= t('home.way1_title') ?></h3>
        <p><?= t('home.way1_text') ?></p>
        <a class="link" href="<?= url('views/all_products.php') ?>"><?= t('home.way1_link') ?></a>
    </div>
    <div class="way">
        <div class="photo arch wine"><?= $photo('home/borrow', t('home.way2_title')) ?></div>
        <div class="num">ii.</div>
        <h3><?= t('home.way2_title') ?></h3>
        <p><?= t('home.way2_text') ?></p>
        <a class="link" href="<?= url('views/borrow.php') ?>"><?= t('home.way2_link') ?></a>
    </div>
    <div class="way">
        <div class="photo arch"><?= $photo('home/pass-it-on', t('home.way3_title')) ?></div>
        <div class="num">iii.</div>
        <h3><?= t('home.way3_title') ?></h3>
        <p><?= t('home.way3_text') ?></p>
        <a class="link" href="<?= url('views/seller/seller_product.php') ?>"><?= t('home.way3_link') ?></a>
    </div>
</section>

<!-- Traditions -->
<section class="traditions">
    <div class="wrap">
        <div class="sec-head">
            <div>
                <div class="section-script light"><?= t('home.trad_script') ?></div>
                <h2><?= t('home.trad_title') ?></h2>
            </div>
            <a class="link link-light" href="<?= url('views/all_products.php') ?>"><?= t('home.trad_all') ?></a>
        </div>
        <div class="trad-grid">
            <a class="trad" href="<?= url('views/all_products.php?cat=hausa') ?>"><div class="photo wine"><?= $photo('traditions/hausa', 'Hausa') ?></div><h4>Hausa</h4><span><?= t('home.trad_hausa') ?></span></a>
            <a class="trad" href="<?= url('views/all_products.php?cat=zarma') ?>"><div class="photo"><?= $photo('traditions/zarma', 'Zarma') ?></div><h4>Zarma</h4><span><?= t('home.trad_zarma') ?></span></a>
            <a class="trad" href="<?= url('views/all_products.php?cat=tuareg') ?>"><div class="photo indigo"><?= $photo('traditions/tuareg', 'Tuareg') ?></div><h4>Tuareg</h4><span><?= t('home.trad_tuareg') ?></span></a>
            <a class="trad" href="<?= url('views/all_products.php?cat=nigeria-inspiration') ?>"><div class="photo ivory"><?= $photo('traditions/nigeria-inspiration', t('home.trad_nigeria_name')) ?></div><h4><?= t('home.trad_nigeria_name') ?></h4><span><?= t('home.trad_nigeria') ?></span></a>
        </div>
    </div>
</section>

<!-- Borrow feature -->
<section class="wrap borrow-feature">
    <div class="photo wine tall"><?= $photo('home/borrow-feature', t('home.borrow_title')) ?></div>
    <div>
        <div class="section-script big"><?= t('home.borrow_script') ?></div>
        <h2><?= t('home.borrow_title') ?></h2>
        <p class="lead"><?= t('home.borrow_text') ?></p>
        <ul class="steps">
            <li><b>i.</b><span><strong><?= t('home.step1_title') ?></strong> <?= t('home.step1_text') ?></span></li>
            <li><b>ii.</b><span><strong><?= t('home.step2_title') ?></strong> <?= t('home.step2_text') ?></span></li>
            <li><b>iii.</b><span><strong><?= t('home.step3_title') ?></strong> <?= t('home.step3_text') ?></span></li>
        </ul>
        <a class="btn btn-solid" href="<?= url('views/borrow.php') ?>"><?= t('home.borrow_button') ?></a>
    </div>
</section>


<?php require __DIR__ . '/layout/footer.php'; ?>
