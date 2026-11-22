<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ReviewController.php';

require_admin();
require_post();
verify_csrf();

$id = (int) filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$op = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
$ok = $id && (new ReviewController())->moderate($id, $op, (int) $_SESSION['user_id'], get_ip());

$_SESSION[$ok ? 'success' : 'error'] = msg($ok ? 'rev.done_' . $op : 'adm.err_already');
redirect('views/admin/admin_reviews.php');
