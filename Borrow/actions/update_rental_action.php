<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/RentalController.php';

require_seller();
require_post();
verify_csrf();

$id   = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$op   = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
$note = preg_replace('/\s+/', ' ', trim(strip_tags((string) ($_POST['note'] ?? ''))));
$keep = isset($_POST['keep_deposit']);
$kept = (int) filter_var(str_replace([' ', '.', ','], '', (string) ($_POST['amount_kept'] ?? '0')), FILTER_VALIDATE_INT);
$tab  = in_array($_POST['tab'] ?? '', RentalController::SELLER_TABS, true) ? $_POST['tab'] : 'requested';

$result = $id ? (new RentalController())->sellerStep($id, (int) $_SESSION['user_id'], $op, $note, $keep, $kept)
              : ['success' => false, 'error' => 'rent.err_not_found'];

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? msg('rent.done_' . $result['op'], ['ref' => $result['ref']])
    : msg($result['error'], $result['values'] ?? []);
redirect('views/seller/seller_rentals.php?tab=' . $tab);
