<?php
$rv_key = $rv_type . '-' . $rv_id . '-' . $rv_seller;
?>
<?php if ($rv_done !== null): ?>
    <p class="rated"><?= t('rev.you_rated', ['name' => $rv_name]) ?> <span class="stars-static" aria-label="<?= t('rev.avg_label', ['avg' => $rv_done]) ?>"><?= str_repeat('★', $rv_done) . str_repeat('☆', 5 - $rv_done) ?></span></p>
<?php else: ?>
    <form method="post" action="<?= url('actions/add_review_action.php') ?>" class="review-form" novalidate data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e($rv_type) ?>">
        <input type="hidden" name="id" value="<?= (int) $rv_id ?>">
        <input type="hidden" name="seller_id" value="<?= (int) $rv_seller ?>">
        <fieldset class="star-input">
            <legend><?= t('rev.rate', ['name' => $rv_name]) ?></legend>
            <?php for ($s = 5; $s >= 1; $s--): ?>
                <input type="radio" id="star-<?= e($rv_key) ?>-<?= $s ?>" name="rating" value="<?= $s ?>" <?= $s === 5 ? 'required' : '' ?>>
                <label for="star-<?= e($rv_key) ?>-<?= $s ?>" title="<?= $s ?>/5">★</label>
            <?php endfor; ?>
        </fieldset>
        <label class="visually-hidden" for="comment-<?= e($rv_key) ?>"><?= t('rev.comment') ?></label>
        <input type="text" id="comment-<?= e($rv_key) ?>" name="comment" maxlength="500" placeholder="<?= t('rev.comment') ?>">
        <button type="submit" class="mini-btn mini-gold"><?= t('rev.send') ?></button>
    </form>
<?php endif; ?>
