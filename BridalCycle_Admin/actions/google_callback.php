<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/classes/GoogleAuthClass.php';
require_once dirname(__DIR__) . '/controllers/UserController.php';

if (is_logged_in()) {
    redirect('index.php');
}

// 1. Take the secrets saved by google_login.php, and delete them
$saved = $_SESSION['google_oauth'] ?? null;
unset($_SESSION['google_oauth']);

// 2. She clicked "Cancel" on the Google page
if (isset($_GET['error'])) {
    $_SESSION['error'] = msg('google.err_cancelled');
    redirect('views/login.php');
}

// 3. Check the state: same secret, not older than 10 minutes
$state = $_GET['state'] ?? '';
$code  = $_GET['code'] ?? '';
if (!is_array($saved) || !is_string($state) || !is_string($code) || $code === '' || strlen($code) > 512
    || !hash_equals($saved['state'], $state) || time() - $saved['time'] > 600) {
    error_log('Google login: invalid or expired state from ' . get_ip());
    $_SESSION['error'] = msg('google.err_failed');
    redirect('views/login.php');
}

// 4. Ask Google for her profile (server to server)
$profile = (new GoogleAuthClass())->getProfile($code, $saved['verifier']);
if ($profile === null) {
    $_SESSION['error'] = msg('google.err_failed');
    redirect('views/login.php');
}

// 5. Known user, new user, or refused?
$result = (new UserController())->googleLogin($profile, get_ip());

if (!empty($result['needs_profile'])) {
    // New person: keep her Google identity for 15 minutes while she completes her profile
    $_SESSION['google_pending'] = [
        'sub'   => $profile['sub'],
        'email' => $profile['email'],
        'name'  => $profile['name'],
        'time'  => time(),
    ];
    redirect('views/complete_profile.php');
}

if (!$result['success']) {
    $_SESSION['error'] = msg($result['error']);
    redirect('views/login.php');
}

// 6. Success: the same session as a normal login
$user = $result['user'];
session_regenerate_id(true);
$_SESSION['user_id']       = (int) $user['id'];
$_SESSION['user_name']     = $user['full_name'];
$_SESSION['user_email']    = $user['email'];
$_SESSION['user_role']     = $user['role'];
$_SESSION['last_activity'] = time();
$_SESSION['success']       = msg('login.success', ['name' => $user['full_name']]);

redirect('index.php');
