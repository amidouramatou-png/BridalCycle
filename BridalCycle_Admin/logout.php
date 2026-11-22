<?php
require_once __DIR__ . '/core/core.php';

// Someone typed logout.php in the address bar: just go home
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect('index.php');
}
verify_csrf();

$lang = current_lang();

$_SESSION = [];
session_regenerate_id(true);

$_SESSION['lang'] = $lang;
$_SESSION['success'] = msg('msg.logged_out');

redirect('index.php');
