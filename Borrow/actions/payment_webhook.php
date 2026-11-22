<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/PaymentController.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('{"error":"method"}');
}
$sent = (string) ($_SERVER['HTTP_SECRET_HASH'] ?? '');
if (IPAY_SECRET_HASH === '' || !hash_equals(IPAY_SECRET_HASH, $sent)) {
    error_log('Payment webhook refused (bad secret-hash) from ' . get_ip());
    http_response_code(401);
    exit('{"error":"unauthorized"}');
}

$body = json_decode((string) file_get_contents('php://input'), true);
// Official payload
$reference = is_array($body) ? (string) ($body['data']['reference'] ?? $body['data']['external_reference'] ?? $body['reference'] ?? '') : '';
if ($reference === '' || strlen($reference) > 80) {
    http_response_code(400);
    exit('{"error":"reference"}');
}

$known = (new PaymentController())->webhook($reference);
http_response_code($known ? 200 : 404);
echo $known ? '{"ok":true}' : '{"error":"unknown"}';
