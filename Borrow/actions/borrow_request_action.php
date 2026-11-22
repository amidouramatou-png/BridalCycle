<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/RentalController.php';

require_buyer();
require_post();
verify_csrf();

$controller = new RentalController();
$id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = $id ? $controller->getProduct($id) : null;
if ($product === null) {
    $_SESSION['error'] = msg('shop.not_found_text');
    redirect('views/borrow.php');
}

[$data, $errors] = $controller->validateRequest($_POST, $product);

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/borrow_request.php?id=' . $id);
}

$result = $controller->requestRental($product, (int) $_SESSION['user_id'], $data);

if (!$result['success']) {
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg($result['error'], $result['values'] ?? []);
    redirect('views/borrow_request.php?id=' . $id);
}

$_SESSION['success'] = msg('rent.sent', ['ref' => $result['ref']]);
redirect('views/account/my_rentals.php');
