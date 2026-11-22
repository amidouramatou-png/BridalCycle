<?php
require_once dirname(dirname(__DIR__)) . '/core/core.php';
require_once dirname(dirname(__DIR__)) . '/controllers/RentalController.php';

require_seller();
$controller = new RentalController();
$tab = in_array($_GET['tab'] ?? '', RentalController::SELLER_TABS, true) ? $_GET['tab'] : 'requested';
$rentals = $controller->getSellerRentals((int) $_SESSION['user_id'], $tab);
require_once dirname(dirname(__DIR__)) . '/controllers/ReceiptController.php';
$receipts = new ReceiptController();

$page_title   = t('rent.seller_title');
$page_scripts = ['js/product_form.js'];
require dirname(__DIR__) . '/layout/header.php';
?>

<section class="wrap seller-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('prod.list_script') ?></div>
            <h1><?= t('rent.seller_title') ?></h1>
            <p class="admin-intro"><?= t('rent.seller_intro') ?></p>
        </div>
        <a class="btn btn-line" href="<?= url('views/seller/seller_dashboard.php') ?>"><?= t('seller.back') ?></a>
    </div>

    <nav class="tabs">
        <?php foreach (RentalController::SELLER_TABS as $t): ?>
            <a href="<?= url('views/seller/seller_rentals.php?tab=' . $t) ?>" class="<?= $tab === $t ? 'active' : '' ?>"><?= t('rent.status_' . $t) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$rentals): ?>
        <div class="empty-card"><p><?= t('rent.seller_none') ?></p></div>
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
                    <div><dt><?= t('rent.bride') ?></dt><dd><?= e(public_name($r['borrower_name'], null, $r['borrower_hidden'], 'buyer')) ?> · <?= e($r['borrower_region']) ?></dd></div>
                    <div><dt><?= t('reg.phone') ?></dt><dd><?= e($r['phone']) ?></dd></div>
                    <div><dt><?= t('rent.size') ?></dt><dd><?= $r['size_needed'] ? e($r['size_needed']) : '—' ?></dd></div>
                    <div><dt><?= t('rent.start') ?></dt><dd><?= e(date('d/m/Y', strtotime($r['start_date']))) ?></dd></div>
                    <div><dt><?= t('rent.end') ?></dt><dd><?= e(date('d/m/Y', strtotime($r['end_date']))) ?></dd></div>
                    <div><dt><?= t('rent.event_short') ?></dt><dd><?= $r['event_date'] ? e(date('d/m/Y', strtotime($r['event_date']))) : '—' ?></dd></div>
                    <div><dt><?= t('rent.days') ?></dt><dd><?= (int) $r['days'] ?></dd></div>
                    <div><dt><?= t('rent.rent_total') ?></dt><dd><?= money((int) $r['rent_total']) ?></dd></div>
                    <div><dt><?= t('prod.deposit') ?></dt><dd><?= money((int) $r['deposit']) ?></dd></div>
                    <div><dt><?= t('com.commission', ['pct' => (int) $r['rent_total'] > 0 ? round((int) $r['commission'] * 100 / (int) $r['rent_total'], 1) : COMMISSION_PERCENT]) ?></dt><dd>− <?= money((int) $r['commission']) ?></dd></div>
                    <div><dt><?= t('com.you_receive') ?></dt><dd><strong><?= money((int) $r['seller_payout'] + (int) $r['deposit_kept']) ?></strong><?= (int) $r['deposit_kept'] ? ' <span class="muted">(' . t('com.incl_kept', ['amount' => money((int) $r['deposit_kept'])]) . ')</span>' : '' ?></dd></div>
                    <?php if ($r['payout_status'] !== 'not_due'): ?>
                        <div><dt><?= t('com.payout') ?></dt><dd><?= t('com.payout_' . $r['payout_status']) ?></dd></div>
                    <?php endif; ?>
                </dl>
                <?php if ($r['note']): ?><p class="review-desc">« <?= e($r['note']) ?> »</p><?php endif; ?>
                <?php if ($r['seller_note']): ?><p class="review-reason note-neutral"><?= t('rent.your_note') ?>: <?= e($r['seller_note']) ?></p><?php endif; ?>

                <div class="review-actions">
                    <?php foreach ($receipts->getRefsFor('rental', (int) $r['id'], (int) $_SESSION['user_id']) as $rc): ?>
                        <a class="mini-btn" href="<?= url('views/receipt.php?ref=' . urlencode($rc['receipt_ref'])) ?>"><?= t('rc.see_' . $rc['kind']) ?></a>
                    <?php endforeach; ?>
                    <?php if ($r['status'] === 'requested'): ?>
                        <form method="post" action="<?= url('actions/update_rental_action.php') ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="op" value="approve"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <button type="submit" class="btn btn-solid btn-small"><?= t('rent.accept') ?></button>
                        </form>
                        <form method="post" action="<?= url('actions/update_rental_action.php') ?>" class="reject-form" novalidate data-validate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="op" value="reject"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <div class="field">
                                <label for="note-<?= (int) $r['id'] ?>"><?= t('rent.refuse_reason') ?></label>
                                <input type="text" id="note-<?= (int) $r['id'] ?>" name="note" required maxlength="500"
                                       data-rule="length" data-min="5" data-max="500" data-error="<?= t('adm.err_reason') ?>"
                                       placeholder="<?= t('rent.refuse_placeholder') ?>">
                            </div>
                            <button type="submit" class="btn btn-line btn-small"><?= t('adm.reject') ?></button>
                        </form>
                    <?php elseif ($r['status'] === 'paid'): ?>
                        <form method="post" action="<?= url('actions/update_rental_action.php') ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="op" value="picked_up"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <button type="submit" class="btn btn-solid btn-small"><?= t('rent.mark_picked') ?></button>
                        </form>
                    <?php elseif ($r['status'] === 'picked_up'): ?>
                        <form method="post" action="<?= url('actions/update_rental_action.php') ?>" class="reject-form return-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="op" value="returned"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <label class="choice"><input type="checkbox" name="keep_deposit" value="1" data-toggles="kept-<?= (int) $r['id'] ?>"> <?= t('rent.keep_deposit') ?></label>
                            <div class="field" id="kept-<?= (int) $r['id'] ?>">
                                <label for="kept-in-<?= (int) $r['id'] ?>"><?= t('rent.amount_kept', ['max' => money((int) $r['deposit'])]) ?></label>
                                <div class="money-input">
                                    <input type="text" inputmode="numeric" id="kept-in-<?= (int) $r['id'] ?>" name="amount_kept" maxlength="13">
                                    <span><?= CURRENCY ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label for="rnote-<?= (int) $r['id'] ?>"><?= t('rent.return_note') ?></label>
                                <input type="text" id="rnote-<?= (int) $r['id'] ?>" name="note" maxlength="500" placeholder="<?= t('rent.return_placeholder') ?>">
                            </div>
                            <button type="submit" class="btn btn-solid btn-small"><?= t('rent.mark_returned') ?></button>
                        </form>
                    <?php else: ?>
                        <p class="rental-next"><?= t('rent.seller_next_' . $r['status']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php require dirname(__DIR__) . '/layout/footer.php'; ?>
