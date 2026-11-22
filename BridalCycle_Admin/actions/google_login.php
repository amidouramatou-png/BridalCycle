<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/classes/GoogleAuthClass.php';

if (is_logged_in()) {
    redirect('index.php');
}
if (!GoogleAuthClass::isConfigured()) {
    $_SESSION['error'] = msg('google.err_not_configured');
    redirect('views/login.php');
}

// "state": a random secret
$state    = bin2hex(random_bytes(32));
$verifier = bin2hex(random_bytes(48));

$_SESSION['google_oauth'] = [
    'state'    => $state,
    'verifier' => $verifier,
    'time'     => time(),
];

header('Location: ' . (new GoogleAuthClass())->authUrl($state, $verifier));
exit;
