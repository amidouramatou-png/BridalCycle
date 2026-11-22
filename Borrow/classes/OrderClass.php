<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class OrderClass extends Database
{
    public function begin(): void    { $this->conn->begin_transaction(); }
    public function commit(): void   { $this->conn->commit(); }
    public function rollback(): void { $this->conn->rollback(); }

    // Lock one outfit row and read its CURRENT state
    public function lockProduct(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT p.id, p.title, p.seller_id, p.sale_price, p.stock,
                    (p.status = 'approved' AND p.is_for_sale = 1 AND c.is_active = 1 AND u.status = 'active') AS available
               FROM bc_products p
               JOIN bc_categories c ON c.id = p.category_id
               JOIN users u ON u.id = p.seller_id
              WHERE p.id = ? FOR UPDATE",
            'i', $id
        );
    }

    public function addOrder(string $ref, int $buyerId, int $total, string $method, ?string $address, string $phone, int $deliveryFee = 0): int
    {
        $stmt = $this->run(
            'INSERT INTO bc_orders (order_ref, buyer_id, total, delivery_method, delivery_address, delivery_fee, phone) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'siissis', $ref, $buyerId, $total, $method, $address, $deliveryFee, $phone
        );
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function addItem(int $orderId, array $i): void
    {
        $this->run(
            'INSERT INTO order_items (order_id, product_id, seller_id, title, unit_price, quantity, commission, seller_payout)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            'iiisiiii', $orderId, $i['product_id'], $i['seller_id'], $i['title'], $i['unit_price'], $i['quantity'],
            $i['commission'], $i['seller_payout']
        )->close();
    }

    // One order of this buyer (or null), with its items
    public function getBuyerOrder(int $id, int $buyerId): ?array
    {
        $order = $this->fetchOne('SELECT * FROM bc_orders WHERE id = ? AND buyer_id = ?', 'ii', $id, $buyerId);
        if ($order !== null) {
            $order['items'] = $this->getItems($id);
        }
        return $order;
    }

    public function getItems(int $orderId): array
    {
        return $this->fetchAll(
            'SELECT oi.*, u.shop_name, u.full_name AS seller_name, u.hide_name AS seller_hidden,
                    (SELECT file_name FROM product_images i WHERE i.product_id = oi.product_id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
               FROM order_items oi JOIN users u ON u.id = oi.seller_id
              WHERE oi.order_id = ?',
            'i', $orderId
        );
    }

    // All orders of a buyer (newest first)
    public function getBuyerOrders(int $buyerId): array
    {
        $orders = $this->fetchAll('SELECT * FROM bc_orders WHERE buyer_id = ? ORDER BY created_at DESC', 'i', $buyerId);
        foreach ($orders as &$order) {
            $order['items'] = $this->getItems((int) $order['id']);
        }
        return $orders;
    }

    // Seller: her sales

    // The items a seller sold in PAID orders, with the order and the bride
    public function getSellerSales(int $sellerId, string $payout): array
    {
        return $this->fetchAll(
            "SELECT oi.*, o.order_ref, o.created_at AS ordered_at, o.delivery_method, o.delivery_address, o.phone, o.status AS order_status,
                    b.full_name AS buyer_name, b.hide_name AS buyer_hidden, b.region AS buyer_region,
                    (SELECT file_name FROM product_images i WHERE i.product_id = oi.product_id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
               FROM order_items oi
               JOIN bc_orders o ON o.id = oi.order_id
               JOIN users b ON b.id = o.buyer_id
              WHERE oi.seller_id = ? AND oi.payout_status = ? AND o.status IN ('paid','ready','completed')
           ORDER BY o.created_at DESC, oi.id",
            'is', $sellerId, $payout
        );
    }

    // Number of paid orders where this seller still has outfits to hand over
    public function countSellerToHand(int $sellerId): int
    {
        return (int) $this->fetchOne(
            "SELECT COUNT(DISTINCT oi.order_id) AS n FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id
              WHERE oi.seller_id = ? AND oi.payout_status = 'not_due' AND o.status IN ('paid','ready')",
            'i', $sellerId
        )['n'];
    }

    // The seller handed over HER outfits of a paid order
    public function markHandedOver(int $orderId, int $sellerId): ?string
    {
        $this->begin();
        try {
            $order = $this->fetchOne("SELECT id, order_ref FROM bc_orders WHERE id = ? AND status IN ('paid','ready') FOR UPDATE", 'i', $orderId);
            if ($order === null) {
                $this->rollback();
                return null;
            }
            $stmt = $this->run("UPDATE order_items SET payout_status = 'due' WHERE order_id = ? AND seller_id = ? AND payout_status = 'not_due'",
                               'ii', $orderId, $sellerId);
            $changed = $stmt->affected_rows;
            $stmt->close();
            if ($changed === 0) {
                $this->rollback();
                return null;
            }
            $left = (int) $this->fetchOne("SELECT COUNT(*) AS n FROM order_items WHERE order_id = ? AND payout_status = 'not_due'", 'i', $orderId)['n'];
            $this->run('UPDATE bc_orders SET status = ? WHERE id = ?', 'si', $left === 0 ? 'completed' : 'ready', $orderId)->close();
            $this->commit();
            return $order['order_ref'];
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // Cancel an order that is not paid yet
    public function cancelOrder(int $id, int $buyerId): bool
    {
        $stmt = $this->run("UPDATE bc_orders SET status = 'cancelled' WHERE id = ? AND buyer_id = ? AND status = 'pending_payment'",
                           'ii', $id, $buyerId);
        $changed = $stmt->affected_rows === 1;
        $stmt->close();
        return $changed;
    }
}
