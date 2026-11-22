<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ProductController.php';

require_seller();
require_post();

if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $_SESSION['error'] = msg('prod.err_too_big');
    redirect('views/seller/seller_product.php');
}
verify_csrf();

$controller = new ProductController();
$sellerId = (int) $_SESSION['user_id'];
$op = $_POST['op'] ?? '';
$posInt = fn (string $key) => filter_var($_POST[$key] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

// Photo buttons
if ($op === 'delete_photo' || $op === 'cover') {
    $imageId = $posInt('image_id');
    $result = $imageId ? ($op === 'cover' ? $controller->setCover($imageId, $sellerId) : $controller->deletePhoto($imageId, $sellerId))
                       : ['success' => false, 'error' => 'prod.err_not_found'];
    $_SESSION[$result['success'] ? 'success' : 'error'] = msg($result['success']
        ? ($op === 'cover' ? 'prod.cover_set' : 'prod.photo_deleted') : $result['error']);
    redirect('views/seller/seller_product.php' . (isset($result['product_id']) ? '?edit=' . $result['product_id'] : ''));
}

// Save
$id = $posInt('id');
$product = $id ? $controller->getSellerProduct($id, $sellerId) : null;
if ($op !== 'save' || $product === null) {
    $_SESSION['error'] = msg('prod.err_not_found');
    redirect('views/seller/seller_product.php');
}

[$data, $errors] = $controller->validateForm($_POST);
[$files, $photoError] = $controller->checkPhotos($_FILES['photos'] ?? null, count($controller->getImages($id)));
if ($photoError) {
    $errors['photos'] = $photoError;
}

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/seller/seller_product.php?edit=' . $id);
}

$result = $controller->updateProduct($id, $sellerId, $data, $files);
$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg('prod.updated', ['title' => $data['title']])
    : msg($result['error']);
redirect('views/seller/seller_product.php');
