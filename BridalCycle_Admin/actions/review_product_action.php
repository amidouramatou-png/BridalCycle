<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/AdminController.php';

require_admin();
require_post();
verify_csrf();

$id  = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$op  = $_POST['op'] ?? '';
$tab = in_array($_POST['tab'] ?? '', AdminController::PRODUCT_TABS, true) ? $_POST['tab'] : 'pending';
$back = 'views/admin/admin_products.php?tab=' . $tab;

if ($id === false || !in_array($op, ['approve', 'reject'], true)) {
    $_SESSION['error'] = msg('prod.err_not_found');
    redirect($back);
}

$controller = new AdminController();

if ($op === 'approve') {
    $result = $controller->approveProduct($id, (int) $_SESSION['user_id'], get_ip());
    $_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
        ? msg('adm.approved', ['title' => $result['title']])
        : msg($result['error']);
    redirect($back);
}

// Refuse: a short reason is required, so the seller knows what to fix
$reason = preg_replace('/\s+/', ' ', trim(strip_tags((string) ($_POST['reason'] ?? ''))));
$len = mb_strlen($reason);
if ($len < 5 || $len > 255) {
    $_SESSION['error'] = msg('adm.err_reason');
    redirect($back . '#outfit-' . $id);
}

$result = $controller->rejectProduct($id, $reason, (int) $_SESSION['user_id'], get_ip());
$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg('adm.rejected', ['title' => $result['title']])
    : msg($result['error']);
redirect($back);
