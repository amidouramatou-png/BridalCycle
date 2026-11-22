<?php
require_once dirname(__DIR__) . '/classes/CartClass.php';

class CartController
{
    public const MAX_QTY = 20;

    private CartClass $cart;

    public function __construct()
    {
        $this->cart = new CartClass();
    }

    public function count(int $userId): int
    {
        return $this->cart->countItems($userId);
    }

    // Add one outfit (or one more of it)
    public function add(int $userId, int $productId, int $quantity = 1): array
    {
        $product = $this->cart->getBuyableProduct($productId);
        if ($product === null) {
            return ['success' => false, 'error' => 'cart.err_unavailable'];
        }
        if ((int) $product['seller_id'] === $userId) {
            return ['success' => false, 'error' => 'cart.err_own'];
        }
        $wanted = $this->cart->getQuantity($userId, $productId) + max(1, $quantity);
        $max = min((int) $product['stock'], self::MAX_QTY);
        if ($wanted > $max) {
            $this->cart->setQuantity($userId, $productId, $max);
            return ['success' => false, 'error' => 'cart.err_stock', 'values' => ['max' => $max]];
        }
        $this->cart->setQuantity($userId, $productId, $wanted);
        return ['success' => true, 'title' => $product['title']];
    }

    // Change the quantity of an outfit already in the bag (0 = remove)
    public function update(int $userId, int $productId, int $quantity): array
    {
        if ($this->cart->getQuantity($userId, $productId) === 0) {
            return ['success' => false, 'error' => 'cart.err_not_in_bag'];
        }
        if ($quantity <= 0) {
            $this->cart->removeItem($userId, $productId);
            return ['success' => true];
        }
        $product = $this->cart->getBuyableProduct($productId);
        if ($product === null) {
            $this->cart->removeItem($userId, $productId);
            return ['success' => false, 'error' => 'cart.err_unavailable'];
        }
        $max = min((int) $product['stock'], self::MAX_QTY);
        $this->cart->setQuantity($userId, $productId, min($quantity, $max));
        return $quantity > $max ? ['success' => false, 'error' => 'cart.err_stock', 'values' => ['max' => $max]]
                                : ['success' => true];
    }

    public function remove(int $userId, int $productId): void
    {
        $this->cart->removeItem($userId, $productId);
    }

    // The bag ready to show: available items with line totals, the total
    public function getBag(int $userId): array
    {
        $items = [];
        $removed = [];
        $total = 0;
        foreach ($this->cart->getItems($userId) as $item) {
            if (!$item['available']) {
                $this->cart->removeItem($userId, (int) $item['product_id']);
                $removed[] = $item['title'];
                continue;
            }
            // Never more than the stock (the stock may have changed since she added it)
            if ((int) $item['quantity'] > (int) $item['stock']) {
                $item['quantity'] = (int) $item['stock'];
                $this->cart->setQuantity($userId, (int) $item['product_id'], (int) $item['quantity']);
            }
            $item['line_total'] = (int) $item['sale_price'] * (int) $item['quantity'];
            $total += $item['line_total'];
            $items[] = $item;
        }
        return ['items' => $items, 'total' => $total, 'removed' => $removed];
    }

    public function clear(int $userId): void
    {
        $this->cart->clear($userId);
    }
}
