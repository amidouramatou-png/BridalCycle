<?php
require_once dirname(__DIR__) . '/classes/MessageClass.php';

class MessageController
{
    public const MAX_LENGTH = 2000;
    public const MAX_PER_10_MIN = 20;
    private const THREAD_LIMIT = 200;

    private MessageClass $messages;

    public function __construct()
    {
        $this->messages = new MessageClass();
    }

    public function getConversations(int $userId): array { return $this->messages->getConversations($userId); }
    public function countUnread(int $userId): int { return $this->messages->countUnread($userId); }
    public function getProduct(int $id): ?array { return $this->messages->getProduct($id); }
    public function canWrite(int $from, int $to): bool { return $this->messages->isAllowed($from, $to); }

    // The name shown for a person
    public static function displayName(array $person): string
    {
        if ($person['status'] !== 'active' && $person['full_name'] === 'Deleted account') {
            return $person['full_name'];
        }
        return public_name($person['full_name'], $person['role'] === 'seller' ? $person['shop_name'] : null, $person['hide_name'] ?? 0, $person['role']);
    }

    // Open a conversation: the other person + the messages (now marked as read)
    public function openThread(int $userId, int $otherId): ?array
    {
        $other = $this->messages->getPerson($otherId);
        $thread = $other ? $this->messages->getThread($userId, $otherId, self::THREAD_LIMIT) : [];
        // She may READ an old conversation even if the other account was suspended
        if ($other === null || $other['role'] === 'admin' || $otherId === $userId || (!$thread && !$this->canWrite($userId, $otherId))) {
            return null;
        }
        $this->messages->markRead($userId, $otherId);
        return ['other' => $other, 'messages' => $thread, 'can_write' => $this->canWrite($userId, $otherId)];
    }

    // Send a message
    public function send(int $from, int $to, ?int $productId, string $body): array
    {
        $body = trim(str_replace("\r\n", "\n", $body));
        $length = mb_strlen($body);
        if ($length < 1 || $length > self::MAX_LENGTH) {
            return ['success' => false, 'error' => 'msgs.err_length'];
        }
        if (!$this->canWrite($from, $to)) {
            return ['success' => false, 'error' => 'msgs.err_not_allowed'];
        }
        if ($this->messages->countRecentSent($from, 10) >= self::MAX_PER_10_MIN) {
            return ['success' => false, 'error' => 'msgs.err_too_many'];
        }
        if ($productId !== null && $this->messages->getProduct($productId) === null) {
            $productId = null;
        }
        $this->messages->addMessage($from, $to, $productId, $body);
        return ['success' => true];
    }
}
