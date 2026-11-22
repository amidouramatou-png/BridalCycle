<?php
require_once dirname(__DIR__) . '/core/core.php';
require_once dirname(__DIR__) . '/controllers/MessageController.php';

require_login();
if (is_admin()) {
    redirect('views/admin/admin_dashboard.php');
}
$me = (int) $_SESSION['user_id'];
$controller = new MessageController();
$with = (int) filter_var($_GET['with'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$thread = $with ? $controller->openThread($me, $with) : null;
if ($with && $thread === null) {
    $_SESSION['error'] = msg('msgs.err_not_allowed');
    redirect('views/messages.php');
}
$productId = (int) filter_var($_GET['product'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = $productId ? $controller->getProduct($productId) : null;
$conversations = $controller->getConversations($me);
$oldMessage = $_SESSION['old_message'] ?? '';
unset($_SESSION['old_message']);

$page_title = t('msgs.title');
require __DIR__ . '/layout/header.php';
?>

<section class="wrap seller-page">
    <div class="seller-head">
        <div>
            <div class="section-script"><?= t('msgs.script') ?></div>
            <h1><?= t('msgs.title') ?></h1>
            <p class="admin-intro"><?= t('msgs.intro') ?></p>
        </div>
    </div>

    <div class="msg-layout">
        <!-- Conversations -->
        <aside class="msg-list" aria-label="<?= t('msgs.title') ?>">
            <?php if (!$conversations): ?>
                <p class="muted msg-empty"><?= t('msgs.none') ?></p>
            <?php endif; ?>
            <?php foreach ($conversations as $c): $name = MessageController::displayName($c); ?>
                <a class="msg-conv <?= $with === (int) $c['other_id'] ? 'active' : '' ?> <?= (int) $c['unread'] ? 'unread' : '' ?>"
                   href="<?= url('views/messages.php?with=' . (int) $c['other_id']) ?>">
                    <span class="msg-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                    <span class="msg-conv-text">
                        <strong><?= e($name) ?></strong><?php if ((int) $c['unread']): ?> <span class="count-dot"><?= (int) $c['unread'] ?></span><?php endif; ?>
                        <span class="msg-preview"><?= (int) $c['last_sender'] === $me ? t('msgs.you') . ' ' : '' ?><?= e(mb_strimwidth($c['last_body'], 0, 60, '…')) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </aside>

        <!-- One conversation -->
        <div class="msg-thread">
            <?php if ($thread === null): ?>
                <div class="empty-card"><p><?= t('msgs.choose') ?></p><a class="btn btn-line" href="<?= url('views/all_products.php') ?>"><?= t('shop.see_all') ?></a></div>
            <?php else: $other = $thread['other']; ?>
                <div class="msg-head">
                    <h2><?= e(MessageController::displayName($other)) ?></h2>
                    <span class="muted"><?= $other['role'] === 'seller' ? t('adm.role_seller') : t('adm.role_buyer') ?> · <?= e($other['region']) ?></span>
                </div>
                <div class="msg-bubbles">
                    <?php if (!$thread['messages']): ?><p class="muted msg-empty"><?= t('msgs.first') ?></p><?php endif; ?>
                    <?php foreach ($thread['messages'] as $m): ?>
                        <div class="bubble <?= (int) $m['sender_id'] === $me ? 'mine' : 'theirs' ?>">
                            <?php if ($m['product_title']): ?><span class="bubble-about"><?= t('msgs.about', ['title' => $m['product_title']]) ?></span><?php endif; ?>
                            <p><?= nl2br(e($m['body'])) ?></p>
                            <time datetime="<?= e($m['created_at']) ?>"><?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?></time>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($thread['can_write']): ?>
                    <form id="write" method="post" action="<?= url('actions/send_message_action.php') ?>" class="msg-form" novalidate data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="to" value="<?= (int) $other['id'] ?>">
                        <?php if ($product && (int) $product['seller_id'] === (int) $other['id']): ?>
                            <input type="hidden" name="product" value="<?= (int) $product['id'] ?>">
                            <p class="bubble-about"><?= t('msgs.about', ['title' => $product['title']]) ?></p>
                        <?php endif; ?>
                        <div class="field">
                            <label for="body" class="visually-hidden"><?= t('msgs.write') ?></label>
                            <textarea id="body" name="body" rows="3" required maxlength="<?= MessageController::MAX_LENGTH ?>" placeholder="<?= t('msgs.write') ?>"
                                      data-rule="length" data-min="1" data-max="<?= MessageController::MAX_LENGTH ?>"
                                      data-error="<?= t('msgs.err_length', ['max' => MessageController::MAX_LENGTH]) ?>"><?= e($oldMessage) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-solid btn-small"><?= t('msgs.send') ?></button>
                    </form>
                <?php else: ?>
                    <p class="muted"><?= t('msgs.closed') ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
