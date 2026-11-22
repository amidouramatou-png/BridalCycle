<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/AdminController.php';

require_admin();
require_post();
verify_csrf();

$id   = (int) filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$op   = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
$role = in_array($_POST['tab'] ?? '', AdminController::USER_TABS, true) ? $_POST['tab'] : '';
$back = 'views/admin/admin_users.php' . ($role !== '' ? '?tab=' . $role : '');

$result = $id ? (new AdminController())->setUserStatus($id, $op, (string) ($_POST['reason'] ?? ''), (int) $_SESSION['user_id'], get_ip())
              : ['success' => false, 'error' => 'adm.err_user'];

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg($op === 'suspend' ? 'adm.done_suspend' : 'adm.done_activate', ['name' => $result['name']])
    : msg($result['error']);
redirect($back);
