<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class PaymentClass extends Database
{
    public function begin(): void    { $this->conn->begin_transaction(); }
    public function commit(): void   { $this->conn->commit(); }
    public function rollback(): void { $this->conn->rollback(); }

    // The order, borrowing or outfit to feature, ONLY if it belongs to this user
    public function getPayable(string $type, int $id, int $userId): ?array
    {
        if ($type === 'feature') {
            // A seller pays to feature HER outfit
            return $this->fetchOne(
                "SELECT p.id, CONCAT('OUTFIT-', p.id) AS ref, ? AS amount, p.status, u.phone, p.title, p.featured_until
                   FROM bc_products p JOIN users u ON u.id = p.seller_id
                  WHERE p.id = ? AND p.seller_id = ?",
                'iii', FEATURE_PRICE, $id, $userId
            );
        }
        if ($type === 'order') {
            return $this->fetchOne(
                "SELECT id, order_ref AS ref, total AS amount, status, phone FROM bc_orders WHERE id = ? AND buyer_id = ?",
                'ii', $id, $userId
            );
        }
        return $this->fetchOne(
            "SELECT r.id, r.rental_ref AS ref, r.total_due AS amount, r.status, r.phone, p.title
               FROM rentals r JOIN bc_products p ON p.id = r.product_id
              WHERE r.id = ? AND r.borrower_id = ?",
            'ii', $id, $userId
        );
    }

    // Is there already a payment waiting
    public function hasRecentPending(string $type, int $id): bool
    {
        return $this->fetchOne(
            "SELECT id FROM payments WHERE payable_type = ? AND payable_id = ? AND status = 'pending'
                AND created_at > NOW() - INTERVAL 3 MINUTE",
            'si', $type, $id
        ) !== null;
    }

    public function addPayment(array $p): int
    {
        $stmt = $this->run(
            'INSERT INTO payments (transaction_ref, payable_type, payable_id, user_id, provider, method, amount, msisdn)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            'ssiissis', $p['transaction_ref'], $p['payable_type'], $p['payable_id'], $p['user_id'],
            $p['provider'], $p['method'], $p['amount'], $p['msisdn']
        );
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // Save the gateway's answer (reference + raw response) on a payment
    public function saveGatewayAnswer(int $id, ?string $gatewayRef, ?string $raw): void
    {
        $this->run('UPDATE payments SET gateway_reference = COALESCE(?, gateway_reference), raw_response = ? WHERE id = ?',
                   'ssi', $gatewayRef, $raw, $id)->close();
    }

    public function markFailed(int $id, string $reason): void
    {
        $this->run("UPDATE payments SET status = 'failed', failure_reason = ? WHERE id = ? AND status = 'pending'",
                   'si', $reason, $id)->close();
    }

    // One payment, locked so it is never counted twice
    public function lockPayment(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM payments WHERE id = ? FOR UPDATE', 'i', $id);
    }

    public function getPaymentByRef(string $ref, int $userId): ?array
    {
        return $this->fetchOne('SELECT * FROM payments WHERE transaction_ref = ? AND user_id = ?', 'si', $ref, $userId);
    }

    // A payment by iPay's reference OR by our own transaction_ref
    public function getPaymentByGatewayRef(string $ref): ?array
    {
        return $this->fetchOne('SELECT * FROM payments WHERE gateway_reference = ? OR transaction_ref = ? LIMIT 1', 'ss', $ref, $ref);
    }

    public function markSucceeded(int $id): void
    {
        $this->run("UPDATE payments SET status = 'succeeded', paid_at = NOW() WHERE id = ?", 'i', $id)->close();
    }

    // Note on a succeeded payment
    public function setFailureReason(int $id, string $reason): void
    {
        $this->run('UPDATE payments SET failure_reason = ? WHERE id = ?', 'si', $reason, $id)->close();
    }

    // Order paid

    public function lockOrder(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM bc_orders WHERE id = ? FOR UPDATE', 'i', $id);
    }

    // Items of the order with the outfit's CURRENT stock, rows locked
    public function lockOrderItems(int $orderId): array
    {
        return $this->fetchAll(
            'SELECT oi.product_id, oi.quantity, p.stock FROM order_items oi JOIN bc_products p ON p.id = oi.product_id
              WHERE oi.order_id = ? FOR UPDATE',
            'i', $orderId
        );
    }

    // Take the stock. The outfit becomes 'sold' when none is left
    public function takeStock(int $productId, int $quantity): void
    {
        $this->run("UPDATE bc_products SET stock = stock - ?, status = IF(stock = 0, 'sold', status) WHERE id = ? AND stock >= ?",
                   'iii', $quantity, $productId, $quantity)->close();
    }

    public function setOrderStatus(int $id, string $status): void
    {
        $this->run('UPDATE bc_orders SET status = ? WHERE id = ?', 'si', $status, $id)->close();
    }

    // Borrowing paid

    public function lockRental(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM rentals WHERE id = ? FOR UPDATE', 'i', $id);
    }

    public function markRentalPaid(int $id): void
    {
        $this->run("UPDATE rentals SET status = 'paid', deposit_status = 'held' WHERE id = ? AND status = 'approved'", 'i', $id)->close();
    }

    // Featured outfit paid

    public function lockProduct(int $id): ?array
    {
        return $this->fetchOne('SELECT id, status FROM bc_products WHERE id = ? FOR UPDATE', 'i', $id);
    }

    // Show the outfit first for $days more days
    public function extendFeature(int $productId, int $days): void
    {
        $this->run('UPDATE bc_products SET featured_until = DATE_ADD(GREATEST(NOW(), COALESCE(featured_until, NOW())), INTERVAL ? DAY) WHERE id = ?',
                   'ii', $days, $productId)->close();
    }

    public function getUserName(int $id): string
    {
        return (string) ($this->fetchOne('SELECT full_name FROM users WHERE id = ?', 'i', $id)['full_name'] ?? '');
    }
}
