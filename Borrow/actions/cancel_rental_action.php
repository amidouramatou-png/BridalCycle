<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/RentalController.php';

require_login();
require_post();
verify_csrf();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$result = $id ? (new RentalController())->cancel($id, (int) $_SESSION['user_id'])
              : ['success' => false, 'error' => 'rent.err_not_found'];

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg('rent.cancelled', ['ref' => $result['ref']])
    : msg($result['error']);
redirect('views/account/my_rentals.php');
