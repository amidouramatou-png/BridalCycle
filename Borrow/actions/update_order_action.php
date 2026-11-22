<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/OrderController.php';

require_seller();
require_post();
verify_csrf();

$id = (int) filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$result = $id ? (new OrderController())->handOver($id, (int) $_SESSION['user_id']) : ['success' => false, 'error' => 'sale.err_step'];

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg('sale.done_handed', ['ref' => $result['ref']])
    : msg($result['error']);
redirect('views/seller/seller_sales.php?tab=' . ($result['success'] ? 'due' : 'not_due'));
