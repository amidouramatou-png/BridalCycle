<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/ReceiptController.php';

require_login();
$ref = is_string($_GET['ref'] ?? null) && preg_match('/^RC-\d{6}-[A-F0-9]{8}$/', $_GET['ref']) ? $_GET['ref'] : '';
$receipt = $ref !== '' ? (new ReceiptController())->getReceipt($ref) : null;

// Only the owner of the receipt, or the admin (for BridalCycle's copies)
if ($receipt === null || (!is_admin() && (int) $receipt['user_id'] !== (int) $_SESSION['user_id'])) {
    $_SESSION['error'] = msg('rc.not_found');
    redirect('views/account/my_account.php');
}

// The receipt uses inline styles (like the email)
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
     . "img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");

$page_title   = t('rc.title');
$page_scripts = ['js/receipt.js'];
require __DIR__ . '/layout/header.php';
?>
<section class="wrap receipt-page">
    <div class="receipt-tools no-print">
        <button type="button" class="btn btn-solid btn-small" data-print><?= t('rc.print') ?></button>
        <span class="muted"><?= t('rc.email_' . $receipt['email_status'], ['email' => $receipt['email']]) ?></span>
    </div>
    <!-- The receipt HTML was built by ReceiptController with every value escaped -->
    <div class="receipt-body"><?= $receipt['body_html'] ?></div>
</section>
<?php require __DIR__ . '/layout/footer.php'; ?>
