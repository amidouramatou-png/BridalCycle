<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/MessageController.php';

require_login();
require_post();
verify_csrf();
if (is_admin()) {
    redirect('views/admin/admin_dashboard.php');
}

$to = (int) filter_var($_POST['to'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = filter_var($_POST['product'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$body = (string) ($_POST['body'] ?? '');

$result = $to ? (new MessageController())->send((int) $_SESSION['user_id'], $to, $product ?: null, $body)
              : ['success' => false, 'error' => 'msgs.err_not_allowed'];

if (!$result['success']) {
    $_SESSION['error'] = msg($result['error'], ['max' => MessageController::MAX_LENGTH]);
    $_SESSION['old_message'] = mb_substr($body, 0, MessageController::MAX_LENGTH);
}
redirect('views/messages.php?with=' . $to . ($product ? '&product=' . $product : '') . '#write');
