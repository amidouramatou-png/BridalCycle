<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/OrderController.php';

require_login();
require_post();
verify_csrf();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$ok = $id && (new OrderController())->cancel($id, (int) $_SESSION['user_id']);
$_SESSION[$ok ? 'success' : 'error'] = msg($ok ? 'order.cancelled' : 'order.err_cancel');
redirect('views/account/my_orders.php');
