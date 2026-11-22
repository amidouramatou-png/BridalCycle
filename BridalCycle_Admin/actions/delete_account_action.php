<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

require_login();
require_post();
verify_csrf();

$result = (new UserController())->deleteAccount(
    (int) $_SESSION['user_id'],
    (string) ($_POST['confirm_email'] ?? ''),
    (string) ($_POST['password'] ?? '')
);

if (!$result['success']) {
    $_SESSION['error'] = msg($result['error']);
    redirect('views/account/delete_account.php');
}

// Log out completely, then say goodbye on the home page
$lang = $_SESSION['lang'] ?? null;
$_SESSION = $lang ? ['lang' => $lang] : [];
session_regenerate_id(true);
$_SESSION['success'] = msg('acc.deleted');
redirect('index.php');
