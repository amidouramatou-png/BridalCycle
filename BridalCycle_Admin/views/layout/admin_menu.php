<?php
$admin_links = [
    'dashboard'  => ['views/admin/admin_dashboard.php', t('admin.menu_dashboard')],
    'money'      => ['views/admin/admin_payments.php', t('admin.menu_money')],
    'orders'     => ['views/admin/admin_orders.php', t('admin.menu_orders')],
    'rentals'    => ['views/admin/admin_rentals.php', t('admin.menu_rentals')],
    'products'   => ['views/admin/admin_products.php', t('admin.menu_products')],
    'users'      => ['views/admin/admin_users.php', t('admin.menu_users')],
    'reviews'    => ['views/admin/admin_reviews.php', t('admin.menu_reviews')],
    'categories' => ['views/admin/admin_category.php', t('admin.menu_categories')],
    'designers'  => ['views/admin/admin_designer.php', t('admin.menu_designers')],
    'logs'       => ['views/admin/admin_logs.php', t('admin.menu_logs')],
];
$admin_page = $admin_page ?? '';
?>
<div class="admin-bar">
    <div class="wrap admin-bar-inner">
        <span class="admin-title"><?= t('admin.title') ?></span>
        <nav class="admin-menu" aria-label="<?= t('admin.title') ?>">
            <?php foreach ($admin_links as $key => [$path, $label]): ?>
                <a href="<?= url($path) ?>" class="<?= $admin_page === $key ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
