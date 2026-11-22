<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/AdminController.php';

require_admin();
$controller = new AdminController();
$s = $controller->getStats();
$months = $controller->getMonthlyIncome();
$sellers = $controller->getTopSellers();
$categories = $controller->getTopCategories();
$viewed = $controller->getMostViewed();
$max = max(1, ...array_map(fn ($m) => (int) $m['v'], $months ?: [['v' => 1]]));

// "To do" list: only the jobs that are waiting
$todo = array_filter([
    ['adm.todo_outfits',  $s['outfits_wait'],  'views/admin/admin_products.php?tab=pending'],
    ['adm.todo_payouts',  $s['to_pay_count'],  'views/admin/admin_payments.php?tab=payouts'],
    ['adm.todo_deposits', $s['deposits_back'], 'views/admin/admin_payments.php?tab=deposits'],
    ['adm.todo_refunds',  $s['refunds'],       'views/admin/admin_payments.php?tab=refunds'],
], fn ($job) => $job[1] > 0);

// One clickable card
$card = function (string $value, string $label, string $link, string $extra = '') { ?>
    <a class="stat stat-link <?= e($extra) ?>" href="<?= url($link) ?>">
        <strong><?= $value ?></strong><span><?= $label ?></span><em class="stat-go"><?= t('adm.view') ?> →</em>
    </a>
<?php };

$page_title = t('adm.dash_title');
$admin_page = 'dashboard';
require dirname(__DIR__) . '/layout/header.php';
require dirname(__DIR__) . '/layout/admin_menu.php';
?>

<section class="wrap admin-page">
    <div class="admin-head">
        <h1><?= t('adm.dash_title') ?></h1>
        <p class="admin-intro"><?= t('adm.dash_intro', ['pct' => COMMISSION_PERCENT]) ?></p>
    </div>

    <!-- BridalCycle's income: the 4 revenue streams -->
    <h2 class="admin-group"><?= t('adm.earn_title') ?></h2>
    <div class="earn-total">
        <span><?= t('adm.st_earned') ?></span>
        <strong><?= money($s['earned']) ?></strong>
    </div>
    <div class="stat-row">
        <?php $card(money($s['com_sales']), t('adm.st_com_sales', ['pct' => COMMISSION_PERCENT]), 'views/admin/admin_orders.php?tab=completed', 'stat-earn'); ?>
        <?php $card(money($s['com_rentals']), t('adm.st_com_rentals', ['pct' => COMMISSION_PERCENT]), 'views/admin/admin_rentals.php?tab=returned', 'stat-earn'); ?>
        <?php $card(money($s['features']), t('adm.st_features', ['n' => $s['features_n']]), 'views/admin/admin_payments.php?tab=payments', 'stat-earn'); ?>
        <?php $card(money($s['delivery']), t('adm.st_delivery'), 'views/admin/admin_orders.php?tab=paid', 'stat-earn'); ?>
    </div>

    <!-- Money that BridalCycle holds for others -->
    <h2 class="admin-group"><?= t('adm.money_title') ?></h2>
    <div class="stat-row">
        <?php $card(money($s['received']), t('adm.st_received'), 'views/admin/admin_payments.php?tab=payments'); ?>
        <?php $card(money($s['to_pay']), t('adm.st_to_pay'), 'views/admin/admin_payments.php?tab=payouts', $s['to_pay'] ? 'stat-alert' : ''); ?>
        <?php $card(money($s['paid_out']), t('adm.st_paid_out'), 'views/admin/admin_payments.php?tab=receipts'); ?>
        <?php $card(money($s['waiting']), t('adm.st_waiting'), 'views/admin/admin_orders.php?tab=paid'); ?>
        <?php $card(money($s['deposits_held']), t('adm.st_deposits'), 'views/admin/admin_payments.php?tab=deposits'); ?>
        <?php $card(money($s['refunds_sum']), t('adm.st_refunds'), 'views/admin/admin_payments.php?tab=refunds', $s['refunds'] ? 'stat-alert' : ''); ?>
        <?php $card((string) $s['orders_done'], t('adm.st_orders_done'), 'views/admin/admin_orders.php?tab=completed'); ?>
        <?php $card((string) $s['rentals_open'], t('adm.st_rentals_open'), 'views/admin/admin_rentals.php?tab=paid'); ?>
    </div>

    <!-- To do -->
    <h2 class="admin-group"><?= t('adm.todo_title') ?></h2>
    <?php if (!$todo): ?>
        <div class="empty-card todo-empty"><p><?= t('adm.todo_none') ?></p></div>
    <?php else: ?>
        <ul class="todo-list">
            <?php foreach ($todo as [$key, $n, $link]): ?>
                <li><a href="<?= url($link) ?>"><span class="todo-count"><?= (int) $n ?></span> <?= t($key) ?> <span class="todo-go"><?= t('adm.open') ?> →</span></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <!-- Analytics -->
    <h2 class="admin-group"><?= t('adm.analytics_title') ?></h2>
    <div class="analytics-grid">
        <div class="analytics-card">
            <h3><?= t('adm.chart_title') ?></h3>
            <?php if (!$months): ?>
                <p class="muted"><?= t('adm.list_none') ?></p>
            <?php else: ?>
                <div class="bar-chart" role="img" aria-label="<?= t('adm.chart_title') ?>">
                    <?php foreach ($months as $m): $h = (int) round(10 * (int) $m['v'] / $max); ?>
                        <div class="bar-col">
                            <span class="bar-value"><?= e(number_format((int) $m['v'], 0, ',', ' ')) ?></span>
                            <span class="bar h-<?= max(1, $h) ?>"></span>
                            <span class="bar-label"><?= e(date('m/Y', strtotime($m['m'] . '-01'))) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="muted small"><?= t('adm.chart_note') ?></p>
            <?php endif; ?>
        </div>
        <div class="analytics-card">
            <h3><?= t('adm.top_sellers') ?></h3>
            <?php if (!$sellers): ?><p class="muted"><?= t('adm.list_none') ?></p><?php endif; ?>
            <ol class="rank-list">
                <?php foreach ($sellers as $sl): ?>
                    <li><span><?= e($sl['shop_name'] ?: $sl['full_name']) ?><?= $sl['rating'] ? ' · ★ ' . e((string) $sl['rating']) : '' ?></span>
                        <strong><?= money((int) $sl['amount']) ?></strong></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div class="analytics-card">
            <h3><?= t('adm.top_categories') ?></h3>
            <?php if (!$categories): ?><p class="muted"><?= t('adm.list_none') ?></p><?php endif; ?>
            <ol class="rank-list">
                <?php foreach ($categories as $c): ?>
                    <li><span><?= e($c['name']) ?></span><strong><?= t('adm.times', ['n' => (int) $c['n']]) ?></strong></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div class="analytics-card">
            <h3><?= t('views.most') ?></h3>
            <?php if (!$viewed): ?><p class="muted"><?= t('adm.list_none') ?></p><?php endif; ?>
            <ol class="rank-list">
                <?php foreach ($viewed as $v): ?>
                    <li><span><a href="<?= url('views/single_product.php?id=' . (int) $v['id']) ?>"><?= e($v['title']) ?></a> · <?= e($v['shop_name'] ?: $v['full_name']) ?></span>
                        <strong>👁 <?= (int) $v['view_count'] ?></strong></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>

    <h2 class="admin-group"><?= t('adm.site_title') ?></h2>
    <div class="stat-row">
        <?php $card((string) $s['brides'], t('adm.st_brides'), 'views/admin/admin_users.php?tab=buyer'); ?>
        <?php $card((string) $s['sellers'], t('adm.st_sellers'), 'views/admin/admin_users.php?tab=seller'); ?>
        <?php $card((string) $s['outfits_shop'], t('adm.st_outfits'), 'views/admin/admin_products.php?tab=approved'); ?>
        <?php $card((string) $s['orders_open'], t('adm.st_orders_open'), 'views/admin/admin_orders.php?tab=paid'); ?>
        <?php $card((string) $s['outfits_wait'], t('adm.st_outfits_wait'), 'views/admin/admin_products.php?tab=pending', $s['outfits_wait'] ? 'stat-alert' : ''); ?>
        <?php $card((string) $s['suspended'], t('adm.st_suspended'), 'views/admin/admin_users.php'); ?>
    </div>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
