<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class ReceiptClass extends Database
{
    public function existsForPayment(int $paymentId): bool
    {
        return $this->fetchOne('SELECT id FROM receipts WHERE payment_id = ? LIMIT 1', 'i', $paymentId) !== null;
    }

    public function addReceipt(array $r): void
    {
        $this->run(
            'INSERT INTO receipts (receipt_ref, kind, payment_id, payable_type, payable_id, recipient, user_id, email,
                                   subject, body_html, amount, email_status, email_error)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'ssisisisssiss', $r['receipt_ref'], $r['kind'], $r['payment_id'], $r['payable_type'], $r['payable_id'],
            $r['recipient'], $r['user_id'], $r['email'], $r['subject'], $r['body_html'], $r['amount'],
            $r['email_status'], $r['email_error']
        )->close();
    }

    public function getByRef(string $ref): ?array
    {
        return $this->fetchOne('SELECT * FROM receipts WHERE receipt_ref = ?', 's', $ref);
    }

    // The receipts of ONE person for an order or borrowing (oldest first)
    public function getRefsFor(string $type, int $id, int $userId): array
    {
        return $this->fetchAll(
            'SELECT receipt_ref, kind FROM receipts WHERE payable_type = ? AND payable_id = ? AND user_id = ? ORDER BY id',
            'sii', $type, $id, $userId
        );
    }

    public function getPayment(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM payments WHERE id = ?', 'i', $id);
    }

    public function getUser(int $id): ?array
    {
        return $this->fetchOne('SELECT id, full_name, email, phone, shop_name, hide_name FROM users WHERE id = ?', 'i', $id);
    }

    public function getOrder(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM bc_orders WHERE id = ?', 'i', $id);
    }

    public function getOrderItems(int $orderId): array
    {
        return $this->fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY seller_id, id', 'i', $orderId);
    }

    public function getProduct(int $id): ?array
    {
        return $this->fetchOne('SELECT id, title, seller_id, featured_until FROM bc_products WHERE id = ?', 'i', $id);
    }

    public function getRental(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT r.*, p.title FROM rentals r JOIN bc_products p ON p.id = r.product_id WHERE r.id = ?', 'i', $id
        );
    }

    // All receipts (newest first) for the admin
    public function getAll(int $limit): array
    {
        return $this->fetchAll('SELECT id, receipt_ref, kind, payable_type, recipient, email, subject, amount, email_status, email_error, created_at
                                  FROM receipts ORDER BY id DESC LIMIT ?', 'i', $limit);
    }
}
