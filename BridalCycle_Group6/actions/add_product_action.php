<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ProductController.php';

require_seller();
require_post();

// Photos bigger than the server allows: PHP empties the whole form
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $_SESSION['error'] = msg('prod.err_too_big');
    redirect('views/seller/seller_product.php?new=1');
}
verify_csrf();

$controller = new ProductController();
[$data, $errors] = $controller->validateForm($_POST);

// Photos: from UPLOAD_MIN_PER_OUTFIT
[$files, $photoError] = $controller->checkPhotos($_FILES['photos'] ?? null, 0);
if ($photoError) {
    $errors['photos'] = $photoError;
}

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/seller/seller_product.php?new=1');
}

$result = $controller->addProduct((int) $_SESSION['user_id'], $data, $files);

if (!$result['success']) {
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg($result['error']);
    redirect('views/seller/seller_product.php?new=1');
}

$_SESSION['success'] = msg('prod.added', ['title' => $data['title']]);
redirect('views/seller/seller_product.php');
