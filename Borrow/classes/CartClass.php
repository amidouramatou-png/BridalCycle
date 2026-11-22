<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class CartClass extends Database
{
    // Can this outfit be bought now?
    public function getBuyableProduct(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT p.id, p.title, p.seller_id, p.sale_price, p.stock
               FROM bc_products p
               JOIN bc_categories c ON c.id = p.category_id AND c.is_active = 1
               JOIN users u ON u.id = p.seller_id AND u.status = 'active'
              WHERE p.id = ? AND p.status = 'approved' AND p.is_for_sale = 1 AND p.stock > 0",
            'i', $id
        );
    }

    // The bag, with the CURRENT price and whether each outfit can still be bought
    public function getItems(int $userId): array
    {
        return $this->fetchAll(
            "SELECT ci.product_id, ci.quantity, p.title, p.sale_price, p.stock, p.size, p.seller_id,
                    u.shop_name, u.full_name AS seller_name, u.hide_name AS seller_hidden,
                    (SELECT file_name FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover,
                    (p.status = 'approved' AND p.is_for_sale = 1 AND p.stock > 0 AND c.is_active = 1 AND u.status = 'active') AS available
               FROM cart_items ci
               JOIN bc_products p ON p.id = ci.product_id
               JOIN bc_categories c ON c.id = p.category_id
               JOIN users u ON u.id = p.seller_id
              WHERE ci.user_id = ?
           ORDER BY ci.added_at DESC",
            'i', $userId
        );
    }

    // How many outfits in the bag (for the number on the bag icon)
    public function countItems(int $userId): int
    {
        return (int) $this->fetchOne('SELECT COALESCE(SUM(quantity), 0) AS n FROM cart_items WHERE user_id = ?', 'i', $userId)['n'];
    }

    // Quantity of one outfit already in the bag (0 if not there)
    public function getQuantity(int $userId, int $productId): int
    {
        return (int) ($this->fetchOne('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?', 'ii', $userId, $productId)['quantity'] ?? 0);
    }

    // Put an outfit in the bag, or set its quantity if it is already there
    public function setQuantity(int $userId, int $productId, int $quantity): void
    {
        $this->run(
            'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
            'iii', $userId, $productId, $quantity
        )->close();
    }

    public function removeItem(int $userId, int $productId): void
    {
        $this->run('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?', 'ii', $userId, $productId)->close();
    }

    public function clear(int $userId): void
    {
        $this->run('DELETE FROM cart_items WHERE user_id = ?', 'i', $userId)->close();
    }
}
