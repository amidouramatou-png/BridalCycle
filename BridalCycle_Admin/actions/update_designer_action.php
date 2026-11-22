<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/DesignerController.php';

require_admin();
require_post();
verify_csrf();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$op = $_POST['op'] ?? '';
if ($id === false || !in_array($op, ['save', 'toggle', 'delete'], true)) {
    $_SESSION['error'] = msg('des.err_not_found');
    redirect('views/admin/admin_designer.php');
}

$controller = new DesignerController();
$adminId = (int) $_SESSION['user_id'];
$ip = get_ip();

// Save changes
if ($op === 'save') {
    [$data, $errors] = DesignerController::validateForm($_POST);
    if (!$errors) {
        $result = $controller->updateDesigner($id, $data, $adminId, $ip);
        if (!$result['success'] && $result['error'] === 'des.err_slug_taken') {
            $errors['slug'] = msg('des.err_slug_taken');
        } elseif (!$result['success']) {
            $_SESSION['error'] = msg($result['error']);
            redirect('views/admin/admin_designer.php');
        }
    }
    if ($errors) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['old'] = $data;
        $_SESSION['error'] = msg('reg.err_fix');
        redirect('views/admin/admin_designer.php?edit=' . $id);
    }
    $_SESSION['success'] = msg('des.updated', ['name' => $data['name']]);
    redirect('views/admin/admin_designer.php');
}

// Hide / show
if ($op === 'toggle') {
    $result = $controller->toggleDesigner($id, $adminId, $ip);
    $_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
        ? msg($result['key'], ['name' => $result['name']])
        : msg($result['error']);
    redirect('views/admin/admin_designer.php');
}

// Delete
$result = $controller->deleteDesigner($id, $adminId, $ip);
$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg('des.deleted', ['name' => $result['name']])
    : msg($result['error']);
redirect('views/admin/admin_designer.php');
