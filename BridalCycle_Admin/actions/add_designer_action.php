<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/DesignerController.php';

require_admin();
require_post();
verify_csrf();

[$data, $errors] = DesignerController::validateForm($_POST);

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg('reg.err_fix');
    redirect('views/admin/admin_designer.php');
}

$result = (new DesignerController())->addDesigner($data, (int) $_SESSION['user_id'], get_ip());

if (!$result['success']) {
    $_SESSION['form_errors'] = $result['error'] === 'des.err_slug_taken' ? ['slug' => msg('des.err_slug_taken')] : [];
    $_SESSION['old'] = $data;
    $_SESSION['error'] = msg($result['error']);
    redirect('views/admin/admin_designer.php');
}

$_SESSION['success'] = msg('des.added', ['name' => $data['name']]);
redirect('views/admin/admin_designer.php');
