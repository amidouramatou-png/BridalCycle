<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/CategoryController.php';

require_admin();
require_post();
verify_csrf();

[$data, $errors] = CategoryController::validateForm($_POST);

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/admin/admin_category.php');
}

$result = (new CategoryController())->addCategory($data, (int) $_SESSION['user_id'], get_ip());

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['error'] === 'cat.err_slug_taken' ? ['slug' => msg('cat.err_slug_taken')] : [];
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg($result['error']);
    redirect('views/admin/admin_category.php');
}

$_SESSION['success'] = msg('cat.added', ['name' => $data['name']]);
redirect('views/admin/admin_category.php');
