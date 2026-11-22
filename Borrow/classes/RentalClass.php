<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class RentalClass extends Database
{
    // Statuses that BLOCK the dates
    public const BLOCKING = ['approved', 'paid', 'picked_up'];

    // An outfit that can be borrowed now
    public function getBorrowableProduct(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT p.id, p.title, p.seller_id, p.rent_price_per_day, p.deposit, p.stock, p.size,
                    c.name AS category_name, u.shop_name, u.full_name AS seller_name, u.hide_name AS seller_hidden, u.region AS seller_region,
                    (SELECT file_name FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover,
                    (SELECT IF(COUNT(*) >= p.stock, MIN(rb.end_date), NULL) FROM rentals rb
                      WHERE rb.product_id = p.id AND rb.status IN ('approved','paid','picked_up')
                        AND rb.start_date <= CURDATE() AND rb.end_date >= CURDATE()) AS borrowed_until
               FROM bc_products p
               JOIN bc_categories c ON c.id = p.category_id AND c.is_active = 1
               JOIN users u ON u.id = p.seller_id AND u.status = 'active'
              WHERE p.id = ? AND p.status = 'approved' AND p.is_for_rent = 1 AND p.stock > 0",
            'i', $id
        );
    }

    // Booked periods of an outfit from today
    public function getBookedPeriods(int $productId): array
    {
        return $this->fetchAll(
            "SELECT start_date, end_date FROM rentals
              WHERE product_id = ? AND status IN ('approved', 'paid', 'picked_up') AND end_date >= CURDATE()
           ORDER BY start_date",
            'i', $productId
        );
    }

    // How many BLOCKING borrowings overlap these dates? (two periods overlap when
    public function countOverlaps(int $productId, string $start, string $end, int $exceptRentalId = 0, bool $lock = false): int
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS n FROM rentals
              WHERE product_id = ? AND id <> ? AND status IN ('approved', 'paid', 'picked_up')
                AND start_date <= ? AND end_date >= ?" . ($lock ? ' FOR UPDATE' : ''),
            'iiss', $productId, $exceptRentalId, $end, $start
        );
        return (int) $row['n'];
    }

    // Does this bride already have an open request for this outfit?
    public function hasOpenRequest(int $productId, int $borrowerId): bool
    {
        return $this->fetchOne(
            "SELECT id FROM rentals WHERE product_id = ? AND borrower_id = ? AND status IN ('requested', 'approved')",
            'ii', $productId, $borrowerId
        ) !== null;
    }

    // Save a new request
    public function addRental(array $r): int
    {
        $stmt = $this->run(
            'INSERT INTO rentals (rental_ref, product_id, borrower_id, seller_id, start_date, end_date, event_date, days,
                                  rent_price_per_day, rent_total, deposit, total_due, commission, seller_payout,
                                  phone, size_needed, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'siiisssiiiiiiisss',
            $r['rental_ref'], $r['product_id'], $r['borrower_id'], $r['seller_id'], $r['start_date'], $r['end_date'],
            $r['event_date'], $r['days'], $r['rent_price_per_day'], $r['rent_total'], $r['deposit'], $r['total_due'],
            $r['commission'], $r['seller_payout'], $r['phone'], $r['size_needed'], $r['note']
        );
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // Lock the outfit row while we check and save
    public function lockProduct(int $productId): ?array
    {
        return $this->fetchOne('SELECT id, stock FROM bc_products WHERE id = ? FOR UPDATE', 'i', $productId);
    }

    public function begin(): void    { $this->conn->begin_transaction(); }
    public function commit(): void   { $this->conn->commit(); }
    public function rollback(): void { $this->conn->rollback(); }

    // The bride's borrowings (newest first)
    public function getBorrowerRentals(int $borrowerId): array
    {
        return $this->fetchAll(
            "SELECT r.*, p.title, u.shop_name, u.full_name AS seller_name, u.hide_name AS seller_hidden, u.phone AS seller_phone,
                    (SELECT file_name FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
               FROM rentals r
               JOIN bc_products p ON p.id = r.product_id
               JOIN users u ON u.id = r.seller_id
              WHERE r.borrower_id = ?
           ORDER BY r.created_at DESC",
            'i', $borrowerId
        );
    }

    // The requests received by a seller ($status '' = all). Waiting requests first
    public function getSellerRentals(int $sellerId, string $status): array
    {
        $sql = "SELECT r.*, p.title, u.full_name AS borrower_name, u.hide_name AS borrower_hidden, u.region AS borrower_region,
                       (SELECT file_name FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
                  FROM rentals r
                  JOIN bc_products p ON p.id = r.product_id
                  JOIN users u ON u.id = r.borrower_id
                 WHERE r.seller_id = ?" . ($status !== '' ? ' AND r.status = ?' : '') . "
              ORDER BY (r.status = 'requested') DESC, r.start_date ASC";
        return $status !== '' ? $this->fetchAll($sql, 'is', $sellerId, $status) : $this->fetchAll($sql, 'i', $sellerId);
    }

    // One borrowing, ONLY if it belongs to this seller
    public function getSellerRental(int $id, int $sellerId): ?array
    {
        return $this->fetchOne('SELECT * FROM rentals WHERE id = ? AND seller_id = ?', 'ii', $id, $sellerId);
    }

    // One borrowing, ONLY if it belongs to this bride
    public function getBorrowerRental(int $id, int $borrowerId): ?array
    {
        return $this->fetchOne('SELECT * FROM rentals WHERE id = ? AND borrower_id = ?', 'ii', $id, $borrowerId);
    }

    // Change the status, ONLY if the current status is the expected one
    public function changeStatus(int $id, string $from, string $to, ?string $sellerNote = null, ?string $depositStatus = null,
                                 ?int $depositKept = null, ?string $payoutStatus = null): bool
    {
        $stmt = $this->run(
            'UPDATE rentals SET status = ?, seller_note = COALESCE(?, seller_note), deposit_status = COALESCE(?, deposit_status),
                    deposit_kept = COALESCE(?, deposit_kept), payout_status = COALESCE(?, payout_status)
              WHERE id = ? AND status = ?',
            'sssisis', $to, $sellerNote, $depositStatus, $depositKept, $payoutStatus, $id, $from
        );
        $changed = $stmt->affected_rows === 1;
        $stmt->close();
        return $changed;
    }

    // Number of waiting requests for a seller (for the badge in her boutique)
    public function countSellerRequests(int $sellerId): int
    {
        return (int) $this->fetchOne("SELECT COUNT(*) AS n FROM rentals WHERE seller_id = ? AND status = 'requested'", 'i', $sellerId)['n'];
    }

    // The bride's phone (to fill the form)
    public function getUserPhone(int $userId): string
    {
        return (string) ($this->fetchOne('SELECT phone FROM users WHERE id = ?', 'i', $userId)['phone'] ?? '');
    }
}
