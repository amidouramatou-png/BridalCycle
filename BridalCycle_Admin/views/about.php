<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ShopController.php';

$traditions = array_filter((new ShopController())->getCategories(), fn ($c) => $c['category_type'] === 'tradition');

$page_title = t('about.page_title');
require __DIR__ . '/layout/header.php';
?>

<section class="manifesto">
    <div class="section-script"><?= t('about.script') ?></div>
    <div class="ornament"><span></span></div>
    <h2><?= t('about.headline') ?></h2>
    <div class="ornament"><span></span></div>
    <p><?= t('about.intro') ?></p>
</section>

<!-- Who we are -->
<section class="wrap who-we-are">
    <div class="who-text">
        <div class="section-script"><?= t('about.who_script') ?></div>
        <h2><?= t('about.who_title') ?></h2>
        <p><?= t('about.who_text') ?></p>
        <ul class="who-values">
            <li><strong><?= t('about.v1_title') ?></strong> <?= t('about.v1_text') ?></li>
            <li><strong><?= t('about.v2_title') ?></strong> <?= t('about.v2_text') ?></li>
            <li><strong><?= t('about.v3_title') ?></strong> <?= t('about.v3_text') ?></li>
        </ul>
    </div>
    <div class="who-ways">
        <h3><?= t('about.earn_title') ?></h3>
        <p class="muted"><?= t('about.earn_intro') ?></p>
        <ol>
            <li><?= t('about.earn_1', ['pct' => COMMISSION_PERCENT]) ?></li>
            <li><?= t('about.earn_2', ['pct' => COMMISSION_PERCENT]) ?></li>
            <li><?= t('about.earn_3', ['price' => money(FEATURE_PRICE), 'days' => FEATURE_DAYS]) ?></li>
            <li><?= t('about.earn_4', ['price' => money(DELIVERY_FEE)]) ?></li>
        </ol>
    </div>
</section>

<section class="wrap story-grid">
    <div class="story-block">
        <span class="num">i.</span>
        <h3><?= t('about.why_title') ?></h3>
        <p><?= t('about.why_text') ?></p>
    </div>
    <div class="story-block">
        <span class="num">ii.</span>
        <h3><?= t('about.how_title') ?></h3>
        <p><?= t('about.how_text', ['pct' => COMMISSION_PERCENT]) ?></p>
    </div>
    <div class="story-block">
        <span class="num">iii.</span>
        <h3><?= t('about.trust_title') ?></h3>
        <p><?= t('about.trust_text') ?></p>
    </div>
</section>

<?php if ($traditions): ?>
<section class="story-traditions">
    <div class="wrap">
        <div class="section-script light"><?= t('about.trad_script') ?></div>
        <h2><?= t('about.trad_title') ?></h2>
        <p><?= t('about.trad_text') ?></p>
        <ul class="trad-tags">
            <?php foreach ($traditions as $c): ?>
                <li><a href="<?= url('views/all_products.php?cat=' . urlencode($c['slug'])) ?>"><?= e($c['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- Contact -->
<section class="wrap contact-block" id="contact">
    <div class="section-script"><?= t('about.contact_script') ?></div>
    <h2><?= t('about.contact_title') ?></h2>
    <p><?= t('about.contact_text') ?></p>
    <p class="contact-lines">
        <a class="btn btn-solid" href="mailto:<?= e(CONTACT_EMAIL) ?>">✉ <?= e(CONTACT_EMAIL) ?></a>
        <?php foreach (['facebook' => [SOCIAL_FACEBOOK, 'Facebook'], 'instagram' => [SOCIAL_INSTAGRAM, 'Instagram'], 'tiktok' => [SOCIAL_TIKTOK, 'TikTok'],
                        'whatsapp' => [SOCIAL_WHATSAPP !== '' ? 'https://wa.me/' . preg_replace('/\D/', '', SOCIAL_WHATSAPP) : '', 'WhatsApp']] as [$link, $name]): ?>
            <?php if ($link !== ''): ?><a class="btn btn-line" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer"><?= $name ?></a><?php endif; ?>
        <?php endforeach; ?>
    </p>
    <p class="muted"><?= t('about.contact_city') ?></p>
</section>

<section class="manifesto story-end">
    <h2><?= t('about.end_title') ?></h2>
    <div class="ctas">
        <a class="btn btn-solid" href="<?= url('views/all_products.php') ?>"><?= t('home.cta_shop') ?></a>
        <a class="btn btn-line" href="<?= url('views/seller/seller_product.php') ?>"><?= t('home.way3_link') ?></a>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
