<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/PaymentController.php';

require_login();
require_post();
verify_csrf();

$type   = is_string($_POST['type'] ?? null) ? $_POST['type'] : '';
$id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$method = is_string($_POST['method'] ?? null) ? $_POST['method'] : '';
$phone  = trim(strip_tags((string) ($_POST['msisdn'] ?? '')));

$result = ($id && in_array($type, PaymentController::TYPES, true))
    ? (new PaymentController())->start($type, $id, (int) $_SESSION['user_id'], $method, $phone)
    : ['status' => 'error', 'error' => 'pay.err_not_payable'];

if ($result['status'] === 'error') {
    $_SESSION['error'] = msg($result['error']);
    $_SESSION['old'] = ['method' => $method, 'msisdn' => $phone];
    redirect('views/payment.php?type=' . urlencode($type) . '&id=' . (int) $id);
}
$page = ['succeeded' => 'payment_success', 'pending' => 'payment_status', 'failed' => 'payment_failed'][$result['status']];
redirect('views/' . $page . '.php?ref=' . urlencode($result['ref']));
