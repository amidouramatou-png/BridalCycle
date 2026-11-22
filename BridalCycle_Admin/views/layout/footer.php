<?php
// The 4 social networks are ALWAYS shown
$socials = [
    'facebook'  => SOCIAL_FACEBOOK !== '' ? SOCIAL_FACEBOOK : 'https://www.facebook.com/',
    'instagram' => SOCIAL_INSTAGRAM !== '' ? SOCIAL_INSTAGRAM : 'https://www.instagram.com/',
    'tiktok'    => SOCIAL_TIKTOK !== '' ? SOCIAL_TIKTOK : 'https://www.tiktok.com/',
    'whatsapp'  => SOCIAL_WHATSAPP !== '' ? 'https://wa.me/' . preg_replace('/\D/', '', SOCIAL_WHATSAPP) : 'https://www.whatsapp.com/',
];
// The icon picture
$socialImage = function (string $key): ?string {
    foreach (['svg', 'png', 'webp', 'jpg'] as $ext) {
        if (is_file(dirname(dirname(__DIR__)) . '/images/social/' . $key . '.' . $ext)) {
            return 'images/social/' . $key . '.' . $ext;
        }
    }
    return null;
};
$icons = [
    'whatsapp'  => '<path d="M5 19.5l1.1-3.6A8 8 0 1 1 9 18.6Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.3 8.6c.2-.5.6-.5.9-.5h.5l.9 2.1-.6.8c.5 1 1.3 1.8 2.3 2.3l.8-.6 2.1.9v.5c0 .3 0 .7-.5.9-.6.3-1.6.4-3-.4a8.6 8.6 0 0 1-3-3c-.8-1.4-.7-2.4-.4-3Z" fill="currentColor"/>',
    'instagram' => '<rect x="4" y="4" width="16" height="16" rx="4.5" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3.6" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="16.8" cy="7.2" r="1" fill="currentColor"/>',
    'tiktok'    => '<path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M14 4c.5 2.6 2.2 4 5 4.2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
    'facebook'  => '<path d="M14.5 20v-7h2.4l.4-2.8h-2.8V8.6c0-.8.3-1.4 1.4-1.4h1.5V4.7c-.3 0-1.2-.1-2.2-.1-2.2 0-3.6 1.3-3.6 3.7v1.9H9.2V13h2.4v7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
];
$names = ['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'];
?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="foot-top">
            <div class="foot-script">BridalCycle</div>
            <p><?= t('footer.city') ?></p>
        </div>

        <div class="foot-grid">
            <div>
                <h5><?= t('footer.house') ?></h5>
                <a href="<?= url('views/about.php') ?>"><?= t('footer.about') ?></a>
                <a href="<?= url('views/borrow.php') ?>#policy"><?= t('footer.policy') ?></a>
                <a href="<?= url('views/about.php') ?>#contact"><?= t('footer.contact') ?></a>
            </div>
            <div>
                <h5><?= t('footer.collections') ?></h5>
                <a href="<?= url('views/all_products.php') ?>"><?= t('footer.all') ?></a>
                <a href="<?= url('views/borrow.php') ?>"><?= t('footer.borrow') ?></a>
            </div>
            <div>
                <h5><?= t('footer.sellers') ?></h5>
                <a href="<?= url('views/seller/seller_product.php') ?>"><?= t('footer.sell') ?></a>
                <a href="<?= url('views/seller/seller_dashboard.php') ?>"><?= t('footer.boutique') ?></a>
            </div>
            <div>
                <h5><?= t('footer.follow') ?></h5>
                <div class="social-links">
                    <?php foreach ($socials as $key => $link): $img = $socialImage($key); ?>
                        <a class="social-icon" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $names[$key] ?>" title="<?= $names[$key] ?>">
                            <?php if ($img): ?>
                                <img src="<?= url($img) ?>" alt="<?= $names[$key] ?>">
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><?= $icons[$key] ?></svg>
                            <?php endif; ?>
                            <span class="social-name"><?= $names[$key] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>
            </div>
        </div>

        <!-- Payment methods (logos from images/payments/) -->
        <div class="foot-pay">
            <h5><?= t('footer.pay') ?></h5>
            <div class="foot-pay-logos">
                <?php foreach (['airtel_money' => 'Airtel Money', 'zamani_cash' => 'Zamani Cash', 'moov_money' => 'Moov Money',
                                'mynita' => 'MyNita', 'amanata' => 'Amanata'] as $logo_key => $logo_name): ?>
                    <span class="foot-pay-item"><?php require __DIR__ . '/payment_logo.php'; ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="copy">© <?= date('Y') ?> BridalCycle &nbsp;·&nbsp; <?= t('footer.copy', ['currency' => CURRENCY]) ?></div>
    </div>
</footer>

<script src="<?= asset('js/validate.js') ?>"></script>
<?php foreach ($page_scripts ?? [] as $script): ?>
    <!-- Extra script for this page only (set $page_scripts  ['js/....js'] in the view) -->
    <script src="<?= asset($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
