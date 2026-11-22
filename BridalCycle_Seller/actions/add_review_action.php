<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ReviewController.php';

require_login();
require_post();
verify_csrf();

$type   = ($_POST['type'] ?? '') === 'rental' ? 'rental' : 'order';
$id     = (int) filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$seller = (int) filter_var($_POST['seller_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$rating = (int) filter_var($_POST['rating'] ?? '', FILTER_VALIDATE_INT);

$result = $id ? (new ReviewController())->add((int) $_SESSION['user_id'], $type, $id, $seller, $rating, (string) ($_POST['comment'] ?? ''))
              : ['success' => false, 'error' => 'rev.err_not_allowed'];

$_SESSION[$result['success'] ? 'success' : 'error'] = msg($result['success'] ? 'rev.thanks' : $result['error']);
redirect($type === 'rental' ? 'views/account/my_rentals.php' : 'views/account/my_orders.php');
