<?php
require_once dirname(__DIR__) . '/classes/OrderClass.php';
require_once __DIR__ . '/CartController.php';

class OrderController
{
    public const METHODS = ['pickup', 'delivery'];

    private OrderClass $orders;

    public function __construct()
    {
        $this->orders = new OrderClass();
    }

    // BridalCycle's commission on an amount (COMMISSION_PERCENT), in whole XOF
    public static function commission(int $amount): int
    {
        return (int) round($amount * COMMISSION_PERCENT / 100);
    }

    // Check the checkout form. Returns [$data, $errors]
    public static function validateCheckout(array $post): array
    {
        $clean = fn (string $key): string => trim(strip_tags((string) ($post[$key] ?? '')));
        $data = [
            'delivery_method'  => $clean('delivery_method'),
            'delivery_address' => preg_replace('/\s+/', ' ', $clean('delivery_address')),
            'phone'            => preg_replace('/^(\+227|00227)/', '', preg_replace('/[\s.\-]/', '', $clean('phone'))),
        ];
        $errors = [];
        if (!in_array($data['delivery_method'], self::METHODS, true)) {
            $errors['delivery_method'] = msg('order.err_method');
        }
        if ($data['delivery_method'] === 'delivery') {
            $len = mb_strlen($data['delivery_address']);
            if ($len < 10 || $len > 255) {
                $errors['delivery_address'] = msg('order.err_address');
            }
        } else {
            $data['delivery_address'] = '';
        }
        if (!preg_match('/^\d{8}$/', $data['phone'])) {
            $errors['phone'] = msg('reg.err_phone');
        }
        return [$data, $errors];
    }

    // Create the order from the bag
    public function createFromBag(int $buyerId, array $data): array
    {
        $cart = new CartController();
        $bag = $cart->getBag($buyerId);
        if (!$bag['items']) {
            return ['success' => false, 'error' => 'cart.empty'];
        }

        $this->orders->begin();
        try {
            $lines = [];
            $total = 0;
            foreach ($bag['items'] as $item) {
                $p = $this->orders->lockProduct((int) $item['product_id']);
                if ($p === null || !$p['available'] || (int) $p['stock'] < (int) $item['quantity']
                    || (int) $p['seller_id'] === $buyerId) {
                    $this->orders->rollback();
                    return ['success' => false, 'error' => 'order.err_changed', 'values' => ['title' => $item['title']]];
                }
                $amount = (int) $p['sale_price'] * (int) $item['quantity'];
                $commission = self::commission($amount);
                $lines[] = [
                    'product_id' => (int) $p['id'], 'seller_id' => (int) $p['seller_id'], 'title' => $p['title'],
                    'unit_price' => (int) $p['sale_price'], 'quantity' => (int) $item['quantity'],
                    'commission' => $commission, 'seller_payout' => $amount - $commission,
                ];
                $total += $amount;
            }

            // Delivery: BridalCycle's flat fee
            $fee = $data['delivery_method'] === 'delivery' ? DELIVERY_FEE : 0;
            $ref = 'BC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $orderId = $this->orders->addOrder($ref, $buyerId, $total + $fee, $data['delivery_method'],
                                               $data['delivery_address'] !== '' ? $data['delivery_address'] : null, $data['phone'], $fee);
            foreach ($lines as $line) {
                $this->orders->addItem($orderId, $line);
            }
            $this->orders->commit();
        } catch (Throwable $e) {
            $this->orders->rollback();
            error_log('Checkout failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }

        $cart->clear($buyerId);
        return ['success' => true, 'id' => $orderId, 'ref' => $ref];
    }

    public function getOrder(int $id, int $buyerId): ?array { return $this->orders->getBuyerOrder($id, $buyerId); }
    public function getOrders(int $buyerId): array { return $this->orders->getBuyerOrders($buyerId); }

    // Tabs of the seller's sales page
    public const SALES_TABS = ['not_due', 'due', 'paid'];

    // The seller's sales, grouped by order
    public function getSellerSales(int $sellerId, string $tab): array
    {
        $grouped = [];
        foreach ($this->orders->getSellerSales($sellerId, in_array($tab, self::SALES_TABS, true) ? $tab : 'not_due') as $item) {
            $id = (int) $item['order_id'];
            $grouped[$id] ??= ['order' => $item, 'items' => [], 'total' => 0, 'commission' => 0, 'payout' => 0];
            $grouped[$id]['items'][] = $item;
            $grouped[$id]['total'] += (int) $item['unit_price'] * (int) $item['quantity'];
            $grouped[$id]['commission'] += (int) $item['commission'];
            $grouped[$id]['payout'] += (int) $item['seller_payout'];
        }
        return $grouped;
    }

    public function countSellerToHand(int $sellerId): int { return $this->orders->countSellerToHand($sellerId); }

    // The seller gave her outfits to the bride
    public function handOver(int $orderId, int $sellerId): array
    {
        try {
            $ref = $this->orders->markHandedOver($orderId, $sellerId);
        } catch (Throwable $e) {
            error_log('Hand over failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }
        return $ref !== null ? ['success' => true, 'ref' => $ref] : ['success' => false, 'error' => 'sale.err_step'];
    }

    public function cancel(int $id, int $buyerId): bool
    {
        return $this->orders->cancelOrder($id, $buyerId);
    }
}
