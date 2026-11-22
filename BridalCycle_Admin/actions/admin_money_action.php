<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/AdminController.php';

require_admin();
require_post();
verify_csrf();

$op   = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
$id   = (int) filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$how  = (string) ($_POST['how'] ?? '');
$tabs = ['pay_seller' => 'payouts', 'deposit_back' => 'deposits', 'refund' => 'refunds'];
$back = 'views/admin/admin_payments.php?tab=' . ($tabs[$op] ?? 'payouts');

if ($id === 0 || !isset($tabs[$op])) {
    $_SESSION['error'] = msg('adm.err_already');
    redirect($back);
}

$controller = new AdminController();
$adminId = (int) $_SESSION['user_id'];

$result = match ($op) {
    'pay_seller'   => $controller->paySeller((string) ($_POST['type'] ?? ''), $id,
                                             (int) filter_var($_POST['seller_id'] ?? '', FILTER_VALIDATE_INT), $how, $adminId, get_ip()),
    'deposit_back' => $controller->returnDeposit($id, $how, $adminId, get_ip()),
    'refund'       => $controller->markRefunded($id, $how, $adminId, get_ip()),
};

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg('adm.done_' . $op, ['amount' => money($result['amount'])])
    : msg($result['error']);
redirect($back);
