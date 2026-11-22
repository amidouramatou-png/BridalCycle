<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/RentalController.php';

require_login();
require_once dirname(dirname(__DIR__)) . '/controllers/ReceiptController.php';
$rentals = (new RentalController())->getBorrowerRentals((int) $_SESSION['user_id']);
$receipts = new ReceiptController();
require_once dirname(dirname(__DIR__)) . '/controllers/ReviewController.php';
$myReviews = (new ReviewController())->getMyReviewKeys((int) $_SESSION['user_id']);

$page_title = t('rent.my_title');
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="wrap seller-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('rent.my_script') ?></div>
            <h1><?= t('rent.my_title') ?></h1>
            <p class="admin-intro"><?= t('rent.my_intro') ?></p>
        </div>
        <a class="btn btn-line" href="<?= url('views/borrow.php') ?>"><?= t('rent.find') ?></a>
    </div>

    <?php if (!$rentals): ?>
        <div class="empty-card">
            <p><?= t('rent.my_none') ?></p>
            <a class="btn btn-solid" href="<?= url('views/borrow.php') ?>"><?= t('rent.find') ?></a>
        </div>
    <?php endif; ?>

    <?php foreach ($rentals as $r): ?>
        <article class="rental-card">
            <div class="rental-photo">
                <?php if ($r['cover']): ?><img src="<?= url('images/products/' . $r['cover']) ?>" alt="" loading="lazy"><?php endif; ?>
            </div>
            <div class="rental-body">
                <div class="review-top">
                    <div>
                        <span class="product-cat"><?= e($r['rental_ref']) ?></span>
                        <h2><?= e($r['title']) ?></h2>
                    </div>
                    <span class="badge badge-r-<?= e($r['status']) ?>"><?= t('rent.status_' . $r['status']) ?></span>
                </div>

                <dl class="review-facts">
                    <div><dt><?= t('rent.start') ?></dt><dd><?= e(date('d/m/Y', strtotime($r['start_date']))) ?></dd></div>
                    <div><dt><?= t('rent.end') ?></dt><dd><?= e(date('d/m/Y', strtotime($r['end_date']))) ?></dd></div>
                    <div><dt><?= t('rent.event_short') ?></dt><dd><?= $r['event_date'] ? e(date('d/m/Y', strtotime($r['event_date']))) : '—' ?></dd></div>
                    <div><dt><?= t('rent.days') ?></dt><dd><?= (int) $r['days'] ?></dd></div>
                    <div><dt><?= t('rent.rent_total') ?></dt><dd><?= money((int) $r['rent_total']) ?></dd></div>
                    <div><dt><?= t('prod.deposit') ?></dt><dd><?= money((int) $r['deposit']) ?></dd></div>
                    <div><dt><?= t('rent.total_due') ?></dt><dd><strong><?= money((int) $r['total_due']) ?></strong></dd></div>
                    <div><dt><?= t('shop.sold_by') ?></dt><dd><?= e(public_name($r['seller_name'], $r['shop_name'], $r['seller_hidden'])) ?></dd></div>
                    <?php if (in_array($r['status'], ['approved', 'paid', 'picked_up'], true)): ?>
                        <div><dt><?= t('rent.seller_phone') ?></dt><dd><?= e($r['seller_phone']) ?></dd></div>
                    <?php endif; ?>
                </dl>

                <p class="rental-next"><?= t('rent.next_' . $r['status']) ?></p>
                <?php if ($r['seller_note']): ?>
                    <p class="review-reason note-neutral"><?= t('rent.seller_says') ?>: <?= e($r['seller_note']) ?></p>
                <?php endif; ?>
                <?php if ($r['status'] === 'returned'): ?>
                    <?php $rv_type = 'rental'; $rv_id = (int) $r['id']; $rv_seller = (int) $r['seller_id'];
                          $rv_name = public_name($r['seller_name'], $r['shop_name'], $r['seller_hidden']); $rv_done = $myReviews['r' . $r['id']] ?? null;
                          require dirname(__DIR__) . '/layout/review_form.php'; ?>
                    <p class="muted"><?= t('rent.deposit_' . $r['deposit_status'] . ($r['deposit_status'] === 'held' && (int) $r['deposit_kept'] > 0 ? '_kept' : ''),
                                           ['kept' => money((int) $r['deposit_kept']), 'back' => money((int) $r['deposit'] - (int) $r['deposit_kept'])]) ?></p>
                <?php endif; ?>

                <div class="review-actions">
                    <?php foreach ($receipts->getRefsFor('rental', (int) $r['id'], (int) $_SESSION['user_id']) as $rc): ?>
                        <a class="mini-btn" href="<?= url('views/receipt.php?ref=' . urlencode($rc['receipt_ref'])) ?>"><?= t('rc.see_' . $rc['kind']) ?></a>
                    <?php endforeach; ?>
                    <?php if ($r['status'] === 'approved'): ?>
                        <a class="btn btn-solid btn-small" href="<?= url('views/payment.php?type=rental&id=' . (int) $r['id']) ?>"><?= t('rent.pay') ?></a>
                    <?php endif; ?>
                    <?php if (in_array($r['status'], ['requested', 'approved'], true)): ?>
                        <form method="post" action="<?= url('actions/cancel_rental_action.php') ?>" class="inline-form"
                              data-confirm="<?= t('rent.confirm_cancel', ['ref' => $r['rental_ref']]) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="btn btn-line btn-small"><?= t('rent.cancel') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
