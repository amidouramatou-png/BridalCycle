<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class AdminClass extends Database
{
    // Orders that were really paid (money received, outfit not cancelled)
    private const PAID_ORDER = "o.status IN ('paid','ready','completed')";
    // Borrowings that were really paid
    private const PAID_RENTAL = "r.status IN ('paid','picked_up','returned')";

    public function begin(): void    { $this->conn->begin_transaction(); }
    public function commit(): void   { $this->conn->commit(); }
    public function rollback(): void { $this->conn->rollback(); }

    private function value(string $sql): int
    {
        return (int) ($this->fetchOne($sql)['v'] ?? 0);
    }

    public function getStats(): array
    {
        $o = self::PAID_ORDER;
        $r = self::PAID_RENTAL;
        return [
            'received'      => $this->value("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE status = 'succeeded'"),
            // BridalCycle's 4 revenue streams
            'com_sales'     => $this->value("SELECT COALESCE(SUM(oi.commission),0) AS v FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id WHERE $o"),
            'com_rentals'   => $this->value("SELECT COALESCE(SUM(r.commission),0) AS v FROM rentals r WHERE $r"),
            'features'      => $this->value("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE payable_type = 'feature' AND status = 'succeeded'
                                             AND (failure_reason IS NULL OR failure_reason NOT LIKE 'REFUND%')"),
            'features_n'    => $this->value("SELECT COUNT(*) AS v FROM bc_products WHERE featured_until > NOW() AND status = 'approved'"),
            'delivery'      => $this->value("SELECT COALESCE(SUM(o.delivery_fee),0) AS v FROM bc_orders o WHERE $o"),
            'to_pay'        => $this->value("SELECT COALESCE(SUM(seller_payout),0) AS v FROM order_items WHERE payout_status = 'due'")
                             + $this->value("SELECT COALESCE(SUM(seller_payout + deposit_kept),0) AS v FROM rentals WHERE payout_status = 'due'"),
            'to_pay_count'  => $this->value("SELECT COUNT(DISTINCT order_id, seller_id) AS v FROM order_items WHERE payout_status = 'due'")
                             + $this->value("SELECT COUNT(*) AS v FROM rentals WHERE payout_status = 'due'"),
            'paid_out'      => $this->value("SELECT COALESCE(SUM(seller_payout),0) AS v FROM order_items WHERE payout_status = 'paid'")
                             + $this->value("SELECT COALESCE(SUM(seller_payout + deposit_kept),0) AS v FROM rentals WHERE payout_status = 'paid'"),
            'waiting'       => $this->value("SELECT COALESCE(SUM(oi.seller_payout),0) AS v FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id
                                             WHERE $o AND oi.payout_status = 'not_due'")
                             + $this->value("SELECT COALESCE(SUM(r.seller_payout),0) AS v FROM rentals r WHERE r.status IN ('paid','picked_up') AND r.payout_status = 'not_due'"),
            'deposits_held' => $this->value("SELECT COALESCE(SUM(r.deposit),0) AS v FROM rentals r WHERE $r AND r.deposit_status = 'held'"),
            'deposits_back' => $this->value("SELECT COUNT(*) AS v FROM rentals WHERE status = 'returned' AND deposit_status = 'held'"),
            'refunds'       => $this->value("SELECT COUNT(*) AS v FROM payments WHERE status = 'succeeded' AND failure_reason LIKE 'REFUND DUE%'"),
            'refunds_sum'   => $this->value("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE status = 'succeeded' AND failure_reason LIKE 'REFUND DUE%'"),
            'brides'        => $this->value("SELECT COUNT(*) AS v FROM users WHERE role = 'buyer'"),
            'sellers'       => $this->value("SELECT COUNT(*) AS v FROM users WHERE role = 'seller'"),
            'suspended'     => $this->value("SELECT COUNT(*) AS v FROM users WHERE status = 'suspended'"),
            'outfits_shop'  => $this->value("SELECT COUNT(*) AS v FROM bc_products WHERE status = 'approved'"),
            'outfits_wait'  => $this->value("SELECT COUNT(*) AS v FROM bc_products WHERE status = 'pending'"),
            'orders_open'   => $this->value("SELECT COUNT(*) AS v FROM bc_orders WHERE status IN ('paid','ready')"),
            'orders_done'   => $this->value("SELECT COUNT(*) AS v FROM bc_orders WHERE status = 'completed'"),
            'rentals_open'  => $this->value("SELECT COUNT(*) AS v FROM rentals WHERE status IN ('requested','approved','paid','picked_up')"),
        ];
    }

    // BridalCycle's income per month (last 6 months), for the chart on the dashboard
    public function getMonthlyIncome(): array
    {
        return $this->fetchAll(
            "SELECT m, SUM(v) AS v FROM (
                SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS m, oi.commission AS v FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id
                 WHERE o.status IN ('paid','ready','completed') AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                UNION ALL
                SELECT DATE_FORMAT(o.created_at, '%Y-%m'), o.delivery_fee FROM bc_orders o
                 WHERE o.status IN ('paid','ready','completed') AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                UNION ALL
                SELECT DATE_FORMAT(r.created_at, '%Y-%m'), r.commission FROM rentals r
                 WHERE r.status IN ('paid','picked_up','returned') AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                UNION ALL
                SELECT DATE_FORMAT(paid_at, '%Y-%m'), amount FROM payments
                 WHERE payable_type = 'feature' AND status = 'succeeded' AND (failure_reason IS NULL OR failure_reason NOT LIKE 'REFUND%')
                   AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             ) x GROUP BY m ORDER BY m"
        );
    }

    // Best sellers
    public function getTopSellers(int $limit): array
    {
        return $this->fetchAll(
            "SELECT u.id, u.full_name, u.shop_name, SUM(x.amount) AS amount, SUM(x.n) AS n,
                    (SELECT ROUND(AVG(rating),1) FROM reviews rv WHERE rv.seller_id = u.id AND rv.is_hidden = 0) AS rating
               FROM (SELECT oi.seller_id, oi.unit_price * oi.quantity AS amount, 1 AS n FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id
                      WHERE o.status IN ('paid','ready','completed')
                     UNION ALL
                     SELECT seller_id, rent_total, 1 FROM rentals WHERE status IN ('paid','picked_up','returned')) x
               JOIN users u ON u.id = x.seller_id
           GROUP BY u.id, u.full_name, u.shop_name ORDER BY amount DESC LIMIT ?", 'i', $limit
        );
    }

    // Most viewed outfits (the view counter)
    public function getMostViewed(int $limit): array
    {
        return $this->fetchAll(
            "SELECT p.id, p.title, p.view_count, u.full_name, u.shop_name FROM bc_products p JOIN users u ON u.id = p.seller_id
              WHERE p.status = 'approved' AND p.view_count > 0 ORDER BY p.view_count DESC, p.id DESC LIMIT ?", 'i', $limit
        );
    }

    // Most popular traditions (outfits sold or borrowed per category)
    public function getTopCategories(int $limit): array
    {
        return $this->fetchAll(
            "SELECT c.name, COUNT(*) AS n FROM (
                SELECT oi.product_id FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id WHERE o.status IN ('paid','ready','completed')
                UNION ALL
                SELECT product_id FROM rentals WHERE status IN ('paid','picked_up','returned')) x
               JOIN bc_products p ON p.id = x.product_id JOIN bc_categories c ON c.id = p.category_id
           GROUP BY c.id, c.name ORDER BY n DESC LIMIT ?", 'i', $limit
        );
    }

    // What BridalCycle must pay now
    public function getPayoutsDue(): array
    {
        $orders = $this->fetchAll(
            "SELECT 'order' AS type, o.id, o.order_ref AS ref, oi.seller_id, SUM(oi.seller_payout) AS amount,
                    SUM(oi.commission) AS commission, GROUP_CONCAT(oi.title SEPARATOR ', ') AS titles, MAX(o.updated_at) AS since,
                    u.full_name, u.shop_name, u.phone
               FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id JOIN users u ON u.id = oi.seller_id
              WHERE oi.payout_status = 'due'
           GROUP BY o.id, o.order_ref, oi.seller_id, u.full_name, u.shop_name, u.phone"
        );
        $rentals = $this->fetchAll(
            "SELECT 'rental' AS type, r.id, r.rental_ref AS ref, r.seller_id, r.seller_payout + r.deposit_kept AS amount,
                    r.commission, r.deposit_kept, p.title AS titles, r.updated_at AS since, u.full_name, u.shop_name, u.phone
               FROM rentals r JOIN bc_products p ON p.id = r.product_id JOIN users u ON u.id = r.seller_id
              WHERE r.payout_status = 'due'"
        );
        $all = array_merge($orders, $rentals);
        usort($all, fn ($a, $b) => strcmp((string) $a['since'], (string) $b['since']));
        return $all;
    }

    // Pay ONE seller for ONE order: every 'due' item of that seller becomes 'paid'
    public function payOrderSeller(int $orderId, int $sellerId): int
    {
        $row = $this->fetchOne(
            "SELECT COALESCE(SUM(seller_payout),0) AS amount, COUNT(*) AS n FROM order_items
              WHERE order_id = ? AND seller_id = ? AND payout_status = 'due' FOR UPDATE",
            'ii', $orderId, $sellerId
        );
        if ((int) $row['n'] === 0) {
            return 0;
        }
        $this->run("UPDATE order_items SET payout_status = 'paid' WHERE order_id = ? AND seller_id = ? AND payout_status = 'due'",
                   'ii', $orderId, $sellerId)->close();
        return (int) $row['amount'];
    }

    // Pay the seller of a returned borrowing
    public function payRental(int $rentalId): ?array
    {
        $r = $this->fetchOne("SELECT id, seller_id, seller_payout + deposit_kept AS amount FROM rentals
                               WHERE id = ? AND status = 'returned' AND payout_status = 'due' FOR UPDATE", 'i', $rentalId);
        if ($r === null) {
            return null;
        }
        $this->run("UPDATE rentals SET payout_status = 'paid' WHERE id = ? AND payout_status = 'due'", 'i', $rentalId)->close();
        return $r;
    }

    public function getDepositsToReturn(): array
    {
        return $this->fetchAll(
            "SELECT r.id, r.rental_ref, r.deposit, r.deposit_kept, r.deposit - r.deposit_kept AS back, r.seller_note, r.updated_at,
                    r.phone, p.title, b.full_name AS bride_name
               FROM rentals r JOIN bc_products p ON p.id = r.product_id JOIN users b ON b.id = r.borrower_id
              WHERE r.status = 'returned' AND r.deposit_status = 'held'
           ORDER BY r.updated_at"
        );
    }

    // The deposit was given back
    public function returnDeposit(int $rentalId): ?array
    {
        $r = $this->fetchOne("SELECT id, borrower_id, deposit, deposit_kept FROM rentals
                               WHERE id = ? AND status = 'returned' AND deposit_status = 'held' FOR UPDATE", 'i', $rentalId);
        if ($r === null) {
            return null;
        }
        $this->run("UPDATE rentals SET deposit_status = IF(deposit_kept > 0, 'partly_kept', 'refunded')
                     WHERE id = ? AND deposit_status = 'held'", 'i', $rentalId)->close();
        return $r;
    }

    public function getRefundsDue(): array
    {
        return $this->fetchAll(
            "SELECT p.id, p.transaction_ref, p.payable_type, p.payable_id, p.amount, p.method, p.msisdn, p.failure_reason, p.paid_at,
                    u.full_name, u.phone
               FROM payments p JOIN users u ON u.id = p.user_id
              WHERE p.status = 'succeeded' AND p.failure_reason LIKE 'REFUND DUE%'
           ORDER BY p.paid_at"
        );
    }

    // Mark a refund as done (only one that is still due). Returns the payment or null
    public function markRefunded(int $paymentId, string $note): ?array
    {
        $p = $this->fetchOne("SELECT * FROM payments WHERE id = ? AND status = 'succeeded' AND failure_reason LIKE 'REFUND DUE%' FOR UPDATE",
                             'i', $paymentId);
        if ($p === null) {
            return null;
        }
        $this->run('UPDATE payments SET failure_reason = ? WHERE id = ?', 'si', mb_substr($note, 0, 255), $paymentId)->close();
        return $p;
    }

    // All payments (newest first), with the order/borrowing reference
    public function getPayments(int $limit): array
    {
        return $this->fetchAll(
            "SELECT p.*, u.full_name,
                    COALESCE(o.order_ref, r.rental_ref) AS ref
               FROM payments p
               JOIN users u ON u.id = p.user_id
          LEFT JOIN bc_orders o  ON p.payable_type = 'order'  AND o.id = p.payable_id
          LEFT JOIN rentals r ON p.payable_type = 'rental' AND r.id = p.payable_id
           ORDER BY p.id DESC LIMIT ?",
            'i', $limit
        );
    }

    // Orders ($status '' = all), newest first, with the bride and the items
    public function getOrders(string $status, int $limit): array
    {
        $sql = 'SELECT o.*, u.full_name AS buyer_name, u.email AS buyer_email FROM bc_orders o JOIN users u ON u.id = o.buyer_id'
             . ($status !== '' ? ' WHERE o.status = ?' : '') . ' ORDER BY o.id DESC LIMIT ?';
        $orders = $status !== '' ? $this->fetchAll($sql, 'si', $status, $limit) : $this->fetchAll($sql, 'i', $limit);
        foreach ($orders as &$order) {
            $order['items'] = $this->fetchAll(
                'SELECT oi.*, u.full_name AS seller_name, u.shop_name FROM order_items oi JOIN users u ON u.id = oi.seller_id
                  WHERE oi.order_id = ?', 'i', (int) $order['id']
            );
        }
        return $orders;
    }

    public function countOrdersByStatus(): array
    {
        return array_column($this->fetchAll('SELECT status, COUNT(*) AS n FROM bc_orders GROUP BY status'), 'n', 'status');
    }

    // Borrowings ($status '' = all), newest first
    public function getRentals(string $status, int $limit): array
    {
        $sql = "SELECT r.*, p.title, b.full_name AS bride_name, s.full_name AS seller_name, s.shop_name
                  FROM rentals r
                  JOIN bc_products p ON p.id = r.product_id
                  JOIN users b ON b.id = r.borrower_id
                  JOIN users s ON s.id = r.seller_id"
             . ($status !== '' ? ' WHERE r.status = ?' : '') . ' ORDER BY r.id DESC LIMIT ?';
        return $status !== '' ? $this->fetchAll($sql, 'si', $status, $limit) : $this->fetchAll($sql, 'i', $limit);
    }

    public function countRentalsByStatus(): array
    {
        return array_column($this->fetchAll('SELECT status, COUNT(*) AS n FROM rentals GROUP BY status'), 'n', 'status');
    }

    // Users ($role '' = everyone except the admin)
    public function getUsers(string $role, string $search, int $limit): array
    {
        $sql = "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.shop_name, u.region, u.status, u.google_id IS NOT NULL AS with_google,
                       u.last_login_at, u.created_at,
                       (SELECT COUNT(*) FROM bc_products p WHERE p.seller_id = u.id AND p.status = 'approved') AS outfits
                  FROM users u
                 WHERE u.role <> 'admin'";
        $types = '';
        $params = [];
        if ($role !== '') {
            $sql .= ' AND u.role = ?';
            $types .= 's';
            $params[] = $role;
        }
        if ($search !== '') {
            // LIKE with the special characters % and _ escaped
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.shop_name LIKE ?)';
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY u.id DESC LIMIT ?';
        $types .= 'i';
        $params[] = $limit;
        return $this->fetchAll($sql, $types, ...$params);
    }

    public function countUsersByRole(): array
    {
        return array_column($this->fetchAll("SELECT role, COUNT(*) AS n FROM users WHERE role <> 'admin' GROUP BY role"), 'n', 'role');
    }

    public function getUser(int $id): ?array
    {
        return $this->fetchOne('SELECT id, full_name, email, role, status FROM users WHERE id = ?', 'i', $id);
    }

    // Suspend or reactivate an account. The admin account can never be changed here
    public function setUserStatus(int $id, string $status): bool
    {
        $stmt = $this->run("UPDATE users SET status = ? WHERE id = ? AND role <> 'admin' AND status <> ?", 'sis', $status, $id, $status);
        $changed = $stmt->affected_rows === 1;
        $stmt->close();
        return $changed;
    }

    // The admin's activity log (newest first)
    public function getLogs(int $limit): array
    {
        return $this->fetchAll(
            'SELECT l.*, u.full_name FROM admin_logs l JOIN users u ON u.id = l.admin_id ORDER BY l.id DESC LIMIT ?', 'i', $limit
        );
    }

    // Last failed logins (security)
    public function getFailedLogins(int $limit): array
    {
        return $this->fetchAll('SELECT email, ip_address, attempted_at FROM login_attempts WHERE success = 0 ORDER BY id DESC LIMIT ?',
                               'i', $limit);
    }
}
