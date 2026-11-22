<?php
require_once dirname(__DIR__) . '/classes/ProductClass.php';
require_once dirname(__DIR__) . '/classes/SecurityClass.php';
require_once dirname(__DIR__) . '/classes/AdminClass.php';
require_once __DIR__ . '/ReceiptController.php';

class AdminController
{
    // The tabs of the outfits page
    public const PRODUCT_TABS = ['pending', 'approved', 'rejected'];
    // The tabs of the money page
    public const MONEY_TABS = ['payouts', 'deposits', 'refunds', 'payments', 'receipts'];
    public const ORDER_TABS = ['paid', 'completed', 'pending_payment', 'cancelled'];
    public const RENTAL_TABS = ['requested', 'approved', 'paid', 'picked_up', 'returned', 'rejected', 'cancelled'];
    public const USER_TABS = ['buyer', 'seller'];
    public const LIST_LIMIT = 200;

    private ProductClass $products;
    private SecurityClass $security;
    private AdminClass $admin;

    public function __construct()
    {
        $this->products = new ProductClass();
        $this->security = new SecurityClass();
        $this->admin = new AdminClass();
    }

    public function getProducts(string $status): array
    {
        return $this->products->getProductsForAdmin(in_array($status, self::PRODUCT_TABS, true) ? $status : '');
    }

    public function countProducts(): array
    {
        return $this->products->countByStatus();
    }

    public function getImages(int $productId): array
    {
        return $this->products->getImages($productId);
    }

    // Approve an outfit (only a waiting or refused one)
    public function approveProduct(int $id, int $adminId, string $ip): array
    {
        $product = $this->products->getProductById($id);
        if ($product === null || !in_array($product['status'], ['pending', 'rejected'], true)) {
            return ['success' => false, 'error' => 'prod.err_not_found'];
        }
        if ($this->products->countImages($id) < UPLOAD_MIN_PER_OUTFIT) {
            return ['success' => false, 'error' => 'adm.err_few_photos'];
        }
        $this->products->approveProduct($id);
        $this->security->logAdminAction($adminId, 'approve_product', $ip, 'product', $id, $product['title']);
        return ['success' => true, 'title' => $product['title']];
    }

    // Refuse an outfit, or take an approved one out of the shop
    public function rejectProduct(int $id, string $reason, int $adminId, string $ip): array
    {
        $product = $this->products->getProductById($id);
        if ($product === null || !in_array($product['status'], ['pending', 'approved'], true)) {
            return ['success' => false, 'error' => 'prod.err_not_found'];
        }
        $this->products->rejectProduct($id, $reason);
        $this->security->logAdminAction($adminId, 'reject_product', $ip, 'product', $id, mb_substr($product['title'] . ': ' . $reason, 0, 500));
        return ['success' => true, 'title' => $product['title']];
    }

    public function getStats(): array
    {
        $s = $this->admin->getStats();
        $s['commission'] = $s['com_sales'] + $s['com_rentals'];
        $s['earned'] = $s['commission'] + $s['features'] + $s['delivery'];
        return $s;
    }
    public function getMonthlyIncome(): array    { return $this->admin->getMonthlyIncome(); }
    public function getTopSellers(): array       { return $this->admin->getTopSellers(5); }
    public function getTopCategories(): array    { return $this->admin->getTopCategories(5); }
    public function getMostViewed(): array       { return $this->admin->getMostViewed(5); }
    public function getPayoutsDue(): array       { return $this->admin->getPayoutsDue(); }
    public function getDepositsToReturn(): array { return $this->admin->getDepositsToReturn(); }
    public function getRefundsDue(): array       { return $this->admin->getRefundsDue(); }
    public function getPayments(): array         { return $this->admin->getPayments(self::LIST_LIMIT); }
    public function getReceipts(): array         { return (new ReceiptController())->getAll(self::LIST_LIMIT); }
    public function getLogs(): array             { return $this->admin->getLogs(self::LIST_LIMIT); }
    public function getFailedLogins(): array     { return $this->admin->getFailedLogins(50); }
    public function countOrders(): array         { return $this->admin->countOrdersByStatus(); }
    public function countRentals(): array        { return $this->admin->countRentalsByStatus(); }
    public function countUsers(): array          { return $this->admin->countUsersByRole(); }

    public function getOrders(string $status): array
    {
        return $this->admin->getOrders(in_array($status, self::ORDER_TABS, true) ? $status : '', self::LIST_LIMIT);
    }

    public function getRentals(string $status): array
    {
        return $this->admin->getRentals(in_array($status, self::RENTAL_TABS, true) ? $status : '', self::LIST_LIMIT);
    }

    public function getUsers(string $role, string $search): array
    {
        return $this->admin->getUsers(in_array($role, self::USER_TABS, true) ? $role : '', mb_substr($search, 0, 100), self::LIST_LIMIT);
    }

    // How was it paid
    public static function cleanHow(string $how): ?string
    {
        $how = preg_replace('/\s+/', ' ', trim(strip_tags($how)));
        $len = mb_strlen($how);
        return ($len >= 3 && $len <= 100) ? $how : null;
    }

    // BridalCycle paid a seller
    public function paySeller(string $type, int $id, int $sellerId, string $how, int $adminId, string $ip): array
    {
        $clean = self::cleanHow($how);
        if (!in_array($type, ['order', 'rental'], true)) {
            return ['success' => false, 'error' => 'adm.err_already'];
        }
        if ($clean === null) {
            return ['success' => false, 'error' => 'adm.err_how'];
        }
        $this->admin->begin();
        try {
            if ($type === 'order') {
                $amount = $this->admin->payOrderSeller($id, $sellerId);
            } else {
                $rental = $this->admin->payRental($id);
                $amount = $rental ? (int) $rental['amount'] : 0;
                $sellerId = $rental ? (int) $rental['seller_id'] : 0;
            }
            if ($sellerId === 0 || $amount === 0) {
                $this->admin->rollback();
                return ['success' => false, 'error' => 'adm.err_already'];
            }
            $this->security->logAdminAction($adminId, 'pay_seller', $ip, $type, $id, 'seller ' . $sellerId . ': ' . $amount . ' XOF · ' . $clean);
            $this->admin->commit();
        } catch (Throwable $e) {
            $this->admin->rollback();
            error_log('Payout failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }
        $this->receipts(fn (ReceiptController $r) => $r->forPayout($type, $id, $sellerId, $amount, $clean));
        return ['success' => true, 'amount' => $amount];
    }

    // BridalCycle gave the deposit back to the bride
    public function returnDeposit(int $rentalId, string $how, int $adminId, string $ip): array
    {
        $clean = self::cleanHow($how);
        if ($clean === null) {
            return ['success' => false, 'error' => 'adm.err_how'];
        }
        $this->admin->begin();
        try {
            $rental = $this->admin->returnDeposit($rentalId);
            if ($rental === null) {
                $this->admin->rollback();
                return ['success' => false, 'error' => 'adm.err_already'];
            }
            $back = (int) $rental['deposit'] - (int) $rental['deposit_kept'];
            $this->security->logAdminAction($adminId, 'deposit_back', $ip, 'rental', $rentalId, $back . ' XOF · ' . $clean);
            $this->admin->commit();
        } catch (Throwable $e) {
            $this->admin->rollback();
            error_log('Deposit back failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }
        $this->receipts(fn (ReceiptController $r) => $r->forDepositBack($rentalId, $clean));
        return ['success' => true, 'amount' => $back];
    }

    // BridalCycle refunded a bride whose outfit was taken just before her payment arrived
    public function markRefunded(int $paymentId, string $how, int $adminId, string $ip): array
    {
        $clean = self::cleanHow($how);
        if ($clean === null) {
            return ['success' => false, 'error' => 'adm.err_how'];
        }
        $this->admin->begin();
        try {
            $payment = $this->admin->markRefunded($paymentId, 'REFUNDED ' . date('d/m/Y H:i') . ': ' . $clean);
            if ($payment === null) {
                $this->admin->rollback();
                return ['success' => false, 'error' => 'adm.err_already'];
            }
            $this->security->logAdminAction($adminId, 'refund', $ip, 'payment', $paymentId, $payment['amount'] . ' XOF · ' . $clean);
            $this->admin->commit();
        } catch (Throwable $e) {
            $this->admin->rollback();
            error_log('Refund failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }
        $this->receipts(fn (ReceiptController $r) => $r->forRefundMade($payment, $clean));
        return ['success' => true, 'amount' => (int) $payment['amount']];
    }

    // Receipts are made AFTER the money is safely saved
    private function receipts(callable $make): void
    {
        try {
            $make(new ReceiptController());
        } catch (Throwable $e) {
            error_log('Admin receipts failed: ' . $e->getMessage());
        }
    }

    // Suspend (with a reason) or reactivate an account. Never the admin
    public function setUserStatus(int $userId, string $op, string $reason, int $adminId, string $ip): array
    {
        $user = $this->admin->getUser($userId);
        if ($user === null || $user['role'] === 'admin' || !in_array($op, ['suspend', 'activate'], true)) {
            return ['success' => false, 'error' => 'adm.err_user'];
        }
        $reason = preg_replace('/\s+/', ' ', trim(strip_tags($reason)));
        if ($op === 'suspend' && (mb_strlen($reason) < 5 || mb_strlen($reason) > 255)) {
            return ['success' => false, 'error' => 'adm.err_reason_user'];
        }
        if (!$this->admin->setUserStatus($userId, $op === 'suspend' ? 'suspended' : 'active')) {
            return ['success' => false, 'error' => 'adm.err_already'];
        }
        $this->security->logAdminAction($adminId, $op . '_user', $ip, 'user', $userId,
                                        mb_substr($user['email'] . ($reason !== '' ? ': ' . $reason : ''), 0, 500));
        return ['success' => true, 'name' => $user['full_name']];
    }
}
