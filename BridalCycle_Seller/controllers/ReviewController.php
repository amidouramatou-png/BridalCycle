<?php
require_once dirname(__DIR__) . '/classes/ReviewClass.php';
require_once dirname(__DIR__) . '/classes/SecurityClass.php';

class ReviewController
{
    private ReviewClass $reviews;

    public function __construct()
    {
        $this->reviews = new ReviewClass();
    }

    public function getMyReviewKeys(int $userId): array { return $this->reviews->getMyReviewKeys($userId); }
    public function getSellerSummary(int $sellerId): array { return $this->reviews->getSellerSummary($sellerId); }
    public function getSellerReviews(int $sellerId, int $limit = 5): array { return $this->reviews->getSellerReviews($sellerId, $limit); }
    public function getAll(): array { return $this->reviews->getAll(200); }

    // A bride rates a seller after an order
    public function add(int $reviewerId, string $type, int $id, int $sellerId, int $rating, string $comment): array
    {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => 'rev.err_rating'];
        }
        $comment = preg_replace('/[ \t]+/', ' ', trim(strip_tags($comment)));
        if (mb_strlen($comment) > 500) {
            return ['success' => false, 'error' => 'rev.err_comment'];
        }
        if ($type === 'order') {
            if (!$this->reviews->canReviewOrder($reviewerId, $id, $sellerId)) {
                return ['success' => false, 'error' => 'rev.err_not_allowed'];
            }
            $orderId = $id;
            $rentalId = null;
        } elseif ($type === 'rental') {
            $sellerId = $this->reviews->getReturnedRentalSeller($reviewerId, $id) ?? 0;
            if ($sellerId === 0) {
                return ['success' => false, 'error' => 'rev.err_not_allowed'];
            }
            $orderId = null;
            $rentalId = $id;
        } else {
            return ['success' => false, 'error' => 'rev.err_not_allowed'];
        }
        try {
            $this->reviews->addReview($reviewerId, $sellerId, $orderId, $rentalId, $rating, $comment !== '' ? $comment : null);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                return ['success' => false, 'error' => 'rev.err_twice'];
            }
            error_log('Review failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }
        return ['success' => true];
    }

    // Admin: hide, show again, or delete a review
    public function moderate(int $id, string $op, int $adminId, string $ip): bool
    {
        $ok = match ($op) {
            'hide'   => $this->reviews->setHidden($id, true),
            'show'   => $this->reviews->setHidden($id, false),
            'delete' => $this->reviews->deleteReview($id),
            default  => false,
        };
        if ($ok) {
            (new SecurityClass())->logAdminAction($adminId, $op . '_review', $ip, 'review', $id);
        }
        return $ok;
    }
}
