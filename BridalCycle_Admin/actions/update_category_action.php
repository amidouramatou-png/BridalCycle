<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/CategoryController.php';

require_admin();
require_post();
verify_csrf();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$op = $_POST['op'] ?? '';
if ($id === false || !in_array($op, ['save', 'toggle', 'delete'], true)) {
    $_SESSION['error'] = msg('cat.err_not_found');
    redirect('views/admin/admin_category.php');
}

$controller = new CategoryController();
$adminId = (int) $_SESSION['user_id'];
$ip = get_ip();

// Save changes
if ($op === 'save') {
    [$data, $errors] = CategoryController::validateForm($_POST);
    if (!$errors) {
        $result = $controller->updateCategory($id, $data, $adminId, $ip);
        if (!$result['success'] && $result['error'] === 'cat.err_slug_taken') {
            $errors['slug'] = msg('cat.err_slug_taken');
        } elseif (!$result['success']) {
            $_SESSION['error'] = msg($result['error']);
            redirect('views/admin/admin_category.php');
        }
    }
    if ($errors) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['old'] = $data;
        $_SESSION['error'] = msg('reg.err_fix');
        redirect('views/admin/admin_category.php?edit=' . $id);
    }
    $_SESSION['success'] = msg('cat.updated', ['name' => $data['name']]);
    redirect('views/admin/admin_category.php');
}

// Hide / show
if ($op === 'toggle') {
    $result = $controller->toggleCategory($id, $adminId, $ip);
    $_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
        ? msg($result['key'], ['name' => $result['name']])
        : msg($result['error']);
    redirect('views/admin/admin_category.php');
}

// Delete
$result = $controller->deleteCategory($id, $adminId, $ip);
$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg('cat.deleted', ['name' => $result['name']])
    : msg($result['error']);
redirect('views/admin/admin_category.php');
