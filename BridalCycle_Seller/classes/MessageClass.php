<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class MessageClass extends Database
{
    // Public details of the other person (never her email or phone)
    public function getPerson(int $id): ?array
    {
        return $this->fetchOne('SELECT id, full_name, shop_name, hide_name, role, status, region FROM users WHERE id = ?', 'i', $id);
    }

    // Can $from write to $to? Only to an ACTIVE seller
    public function isAllowed(int $from, int $to): bool
    {
        return $this->fetchOne(
            "SELECT 1 FROM users u WHERE u.id = ? AND u.status = 'active' AND u.role <> 'admin' AND u.id <> ?
               AND (u.role = 'seller'
                    OR EXISTS (SELECT 1 FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ?)
                    OR EXISTS (SELECT 1 FROM rentals r WHERE (r.borrower_id = u.id AND r.seller_id = ?) OR (r.borrower_id = ? AND r.seller_id = u.id))
                    OR EXISTS (SELECT 1 FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id
                                WHERE (o.buyer_id = u.id AND oi.seller_id = ?) OR (o.buyer_id = ? AND oi.seller_id = u.id)))",
            'iiiiiii', $to, $from, $from, $from, $from, $from, $from
        ) !== null;
    }

    // How many messages did this user send in the last $minutes minutes? (anti-spam)
    public function countRecentSent(int $userId, int $minutes): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS n FROM messages WHERE sender_id = ? AND created_at > NOW() - INTERVAL ? MINUTE',
                                     'ii', $userId, $minutes)['n'];
    }

    // An outfit in the shop (to say which outfit the message is about)
    public function getProduct(int $id): ?array
    {
        return $this->fetchOne("SELECT id, title, seller_id FROM bc_products WHERE id = ? AND status IN ('approved','sold')", 'i', $id);
    }

    public function addMessage(int $from, int $to, ?int $productId, string $body): void
    {
        $this->run('INSERT INTO messages (sender_id, receiver_id, product_id, body) VALUES (?, ?, ?, ?)',
                   'iiis', $from, $to, $productId, $body)->close();
    }

    // One line per person she talks with: the last message and how many are unread
    public function getConversations(int $userId): array
    {
        return $this->fetchAll(
            'SELECT c.other_id, c.unread, m.body AS last_body, m.created_at AS last_at, m.sender_id AS last_sender,
                    u.full_name, u.shop_name, u.hide_name, u.role, u.status
               FROM (SELECT IF(sender_id = ?, receiver_id, sender_id) AS other_id, MAX(id) AS last_id,
                            SUM(receiver_id = ? AND is_read = 0) AS unread
                       FROM messages WHERE sender_id = ? OR receiver_id = ?
                   GROUP BY other_id) c
               JOIN messages m ON m.id = c.last_id
               JOIN users u ON u.id = c.other_id
           ORDER BY c.last_id DESC',
            'iiii', $userId, $userId, $userId, $userId
        );
    }

    // The messages between two people (oldest first), with the outfit they are about
    public function getThread(int $userId, int $otherId, int $limit): array
    {
        return array_reverse($this->fetchAll(
            'SELECT m.*, p.title AS product_title FROM messages m LEFT JOIN bc_products p ON p.id = m.product_id
              WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
           ORDER BY m.id DESC LIMIT ?',
            'iiiii', $userId, $otherId, $otherId, $userId, $limit
        ));
    }

    public function markRead(int $userId, int $otherId): void
    {
        $this->run('UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0', 'ii', $userId, $otherId)->close();
    }

    public function countUnread(int $userId): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS n FROM messages WHERE receiver_id = ? AND is_read = 0', 'i', $userId)['n'];
    }
}
