<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/RentalController.php';

require_buyer();

$controller = new RentalController();
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = $id ? $controller->getProduct($id) : null;
if ($product === null) {
    $_SESSION['error'] = msg('shop.not_found_text');
    redirect('views/borrow.php');
}
if (!empty($product['borrowed_until'])) {
    $_SESSION['error'] = msg('rent.err_borrowed_now', ['date' => date('d/m/Y', strtotime($product['borrowed_until'] . ' +1 day'))]);
    redirect('views/single_product.php?id=' . (int) $product['id']);
}
$isOwn = (int) $product['seller_id'] === (int) $_SESSION['user_id'];

$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['old'] ?? ['phone' => $controller->getUserPhone((int) $_SESSION['user_id'])];
unset($_SESSION['form_errors'], $_SESSION['old']);

$val = fn (string $key): string => e((string) ($old[$key] ?? ''));
$err = fn (string $key): string => isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
$cls = fn (string $key): string => isset($errors[$key]) ? 'field invalid' : 'field';

$booked = $controller->getBookedPeriods((int) $product['id']);
$firstDay = RentalController::firstDay();
$lastDay = (new DateTimeImmutable('today'))->modify('+1 year')->format('Y-m-d');

$page_title   = t('rent.title');
$page_scripts = ['js/borrow.js'];
require __DIR__ . '/layout/header.php';
?>

<section class="wrap borrow-page">
    <div class="borrow-layout">

        <!-- The outfit -->
        <aside class="borrow-outfit">
            <?php if ($product['cover']): ?>
                <img src="<?= url('images/products/' . $product['cover']) ?>" alt="<?= e($product['title']) ?>">
            <?php endif; ?>
            <span class="product-cat"><?= e($product['category_name']) ?></span>
            <h2><?= e($product['title']) ?></h2>
            <p class="muted"><?= t('prod.size') ?> <?= e($product['size']) ?> · <?= t('shop.sold_by') ?> <?= e(public_name($product['seller_name'], $product['shop_name'], $product['seller_hidden'])) ?> · <?= e($product['seller_region']) ?></p>
            <p class="borrow-price"><?= money((int) $product['rent_price_per_day']) ?> <small><?= t('prod.per_day') ?></small></p>
            <p class="muted"><?= t('shop.deposit_line', ['deposit' => money((int) $product['deposit'])]) ?></p>

            <div class="booked">
                <h4><?= t('rent.booked_title') ?></h4>
                <?php if (!$booked): ?>
                    <p class="muted"><?= t('rent.booked_none') ?></p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($booked as $b): ?>
                            <li><?= e(date('d/m/Y', strtotime($b['start_date']))) ?> → <?= e(date('d/m/Y', strtotime($b['end_date']))) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </aside>

        <!-- The form -->
        <div class="borrow-form">
            <div class="section-script"><?= t('rent.script') ?></div>
            <h1><?= t('rent.title') ?></h1>
            <p class="form-intro"><?= t('rent.intro', ['min' => RENT_MIN_DAYS, 'max' => RENT_MAX_DAYS]) ?></p>

            <?php if ($isOwn): ?>
                <div class="empty-card"><p><?= t('rent.err_own') ?></p></div>
            <?php else: ?>
            <form method="post" action="<?= url('actions/borrow_request_action.php') ?>" novalidate data-validate
                  data-borrow data-price="<?= (int) $product['rent_price_per_day'] ?>" data-deposit="<?= (int) $product['deposit'] ?>"
                  data-min="<?= RENT_MIN_DAYS ?>" data-max="<?= RENT_MAX_DAYS ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

                <div class="form-row">
                    <div class="<?= $cls('start_date') ?>">
                        <label for="start_date"><?= t('rent.start') ?></label>
                        <input type="date" id="start_date" name="start_date" value="<?= $val('start_date') ?>" required
                               min="<?= $firstDay ?>" max="<?= $lastDay ?>" data-error="<?= t('rent.err_start', ['date' => date('d/m/Y', strtotime($firstDay))]) ?>">
                        <?= $err('start_date') ?>
                    </div>
                    <div class="<?= $cls('end_date') ?>">
                        <label for="end_date"><?= t('rent.end') ?></label>
                        <input type="date" id="end_date" name="end_date" value="<?= $val('end_date') ?>" required
                               min="<?= $firstDay ?>" data-error="<?= t('rent.err_days', ['min' => RENT_MIN_DAYS, 'max' => RENT_MAX_DAYS]) ?>">
                        <?= $err('end_date') ?>
                    </div>
                </div>

                <div class="<?= $cls('event_date') ?>">
                    <label for="event_date"><?= t('rent.event') ?></label>
                    <input type="date" id="event_date" name="event_date" value="<?= $val('event_date') ?>" min="<?= $firstDay ?>">
                    <div class="help"><?= t('rent.event_help') ?></div>
                    <?= $err('event_date') ?>
                </div>

                <!-- Live total -->
                <div class="borrow-total" data-borrow-total aria-live="polite">
                    <div><span><?= t('rent.days') ?></span><strong data-out="days">—</strong></div>
                    <div><span><?= t('rent.rent_total') ?></span><strong data-out="rent">—</strong></div>
                    <div><span><?= t('prod.deposit') ?></span><strong><?= money((int) $product['deposit']) ?></strong></div>
                    <div class="grand"><span><?= t('rent.total_due') ?></span><strong data-out="total">—</strong></div>
                    <p class="field-error" data-out="error" hidden><?= t('rent.err_days', ['min' => RENT_MIN_DAYS, 'max' => RENT_MAX_DAYS]) ?></p>
                    <p class="field-error" data-out="before" hidden><?= t('rent.err_before') ?></p>
                    <p class="field-error" data-out="event" hidden><?= t('rent.err_event') ?></p>
                </div>

                <div class="form-row">
                    <div class="<?= $cls('phone') ?>">
                        <label for="phone"><?= t('reg.phone') ?></label>
                        <input type="tel" id="phone" name="phone" value="<?= $val('phone') ?>" required maxlength="20" autocomplete="tel"
                               data-rule="phone" data-error="<?= t('reg.err_phone') ?>">
                        <?= $err('phone') ?>
                    </div>
                    <div class="<?= $cls('size_needed') ?>">
                        <label for="size_needed"><?= t('rent.size') ?></label>
                        <input type="text" id="size_needed" name="size_needed" value="<?= $val('size_needed') ?>" maxlength="20"
                               data-rule="size" data-error="<?= t('prod.err_size') ?>">
                        <?= $err('size_needed') ?>
                    </div>
                </div>

                <div class="<?= $cls('note') ?>">
                    <label for="note"><?= t('rent.note') ?></label>
                    <textarea id="note" name="note" rows="3" maxlength="500" placeholder="<?= t('rent.note_placeholder') ?>"><?= $val('note') ?></textarea>
                    <?= $err('note') ?>
                </div>

                <p class="help"><?= t('rent.how') ?></p>
                <button type="submit" class="btn btn-solid btn-block" data-loading="<?= t('rent.sending') ?>"><?= t('rent.send') ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
