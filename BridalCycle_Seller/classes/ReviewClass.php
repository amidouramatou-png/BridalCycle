<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class ReviewClass extends Database
{
    // Did this bride receive an outfit from this seller in this order?
    public function canReviewOrder(int $reviewerId, int $orderId, int $sellerId): bool
    {
        return $this->fetchOne(
            "SELECT 1 FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id
              WHERE o.id = ? AND o.buyer_id = ? AND oi.seller_id = ? AND oi.payout_status IN ('due','paid') LIMIT 1",
            'iii', $orderId, $reviewerId, $sellerId
        ) !== null;
    }

    // The seller of a borrowing this bride RETURNED (or null)
    public function getReturnedRentalSeller(int $reviewerId, int $rentalId): ?int
    {
        $row = $this->fetchOne("SELECT seller_id FROM rentals WHERE id = ? AND borrower_id = ? AND status = 'returned'", 'ii', $rentalId, $reviewerId);
        return $row ? (int) $row['seller_id'] : null;
    }

    public function addReview(int $reviewerId, int $sellerId, ?int $orderId, ?int $rentalId, int $rating, ?string $comment): void
    {
        $this->run('INSERT INTO reviews (reviewer_id, seller_id, order_id, rental_id, rating, comment) VALUES (?, ?, ?, ?, ?, ?)',
                   'iiiiis', $reviewerId, $sellerId, $orderId, $rentalId, $rating, $comment)->close();
    }

    // The reviews this bride already wrote (to hide the form)
    public function getMyReviewKeys(int $reviewerId): array
    {
        $keys = [];
        foreach ($this->fetchAll('SELECT order_id, rental_id, seller_id, rating FROM reviews WHERE reviewer_id = ?', 'i', $reviewerId) as $r) {
            $keys[$r['order_id'] ? 'o' . $r['order_id'] . '-' . $r['seller_id'] : 'r' . $r['rental_id']] = (int) $r['rating'];
        }
        return $keys;
    }

    // Average and number of visible reviews of a seller
    public function getSellerSummary(int $sellerId): array
    {
        $row = $this->fetchOne('SELECT ROUND(AVG(rating), 1) AS avg, COUNT(*) AS n FROM reviews WHERE seller_id = ? AND is_hidden = 0', 'i', $sellerId);
        return ['avg' => $row['avg'] !== null ? (float) $row['avg'] : null, 'n' => (int) $row['n']];
    }

    // The latest visible reviews of a seller (first name of the bride only)
    public function getSellerReviews(int $sellerId, int $limit): array
    {
        return $this->fetchAll(
            'SELECT r.rating, r.comment, r.created_at, u.full_name, u.hide_name FROM reviews r JOIN users u ON u.id = r.reviewer_id
              WHERE r.seller_id = ? AND r.is_hidden = 0 ORDER BY r.id DESC LIMIT ?',
            'ii', $sellerId, $limit
        );
    }

    // Admin
    public function getAll(int $limit): array
    {
        return $this->fetchAll(
            'SELECT r.*, b.full_name AS reviewer_name, s.full_name AS seller_name, s.shop_name,
                    o.order_ref, l.rental_ref
               FROM reviews r JOIN users b ON b.id = r.reviewer_id JOIN users s ON s.id = r.seller_id
          LEFT JOIN bc_orders o ON o.id = r.order_id LEFT JOIN rentals l ON l.id = r.rental_id
           ORDER BY r.id DESC LIMIT ?', 'i', $limit
        );
    }

    public function setHidden(int $id, bool $hidden): bool
    {
        $stmt = $this->run('UPDATE reviews SET is_hidden = ? WHERE id = ? AND is_hidden <> ?', 'iii', (int) $hidden, $id, (int) $hidden);
        $ok = $stmt->affected_rows === 1;
        $stmt->close();
        return $ok;
    }

    public function deleteReview(int $id): bool
    {
        $stmt = $this->run('DELETE FROM reviews WHERE id = ?', 'i', $id);
        $ok = $stmt->affected_rows === 1;
        $stmt->close();
        return $ok;
    }
}
