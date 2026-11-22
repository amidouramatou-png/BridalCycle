<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class ProductClass extends Database
{
    // Fields a seller can fill (used by add and update)
    private const FIELDS = 'category_id, designer_id, title, description, item_condition, size, colour,
                            is_for_sale, sale_price, is_for_rent, rent_price_per_day, deposit, stock';

    // All outfits of ONE seller (newest first), with category name and cover photo
    public function getSellerProducts(int $sellerId): array
    {
        return $this->fetchAll(
            "SELECT p.*, c.name AS category_name,
                    (SELECT file_name FROM product_images i WHERE i.product_id = p.id
                      ORDER BY i.sort_order, i.id LIMIT 1) AS cover,
                    (SELECT MIN(rb.end_date) FROM rentals rb WHERE rb.product_id = p.id AND rb.status IN ('approved','paid','picked_up')
                        AND rb.start_date <= CURDATE() AND rb.end_date >= CURDATE()) AS borrowed_until
               FROM bc_products p
               JOIN bc_categories c ON c.id = p.category_id
              WHERE p.seller_id = ?
              ORDER BY p.created_at DESC",
            'i', $sellerId
        );
    }

    // One outfit, ONLY if it belongs to this seller
    public function getSellerProduct(int $id, int $sellerId): ?array
    {
        return $this->fetchOne('SELECT * FROM bc_products WHERE id = ? AND seller_id = ?', 'ii', $id, $sellerId);
    }

    // Add an outfit
    public function addProduct(int $sellerId, array $d): int
    {
        $stmt = $this->run(
            'INSERT INTO bc_products (seller_id, ' . self::FIELDS . ', status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')',
            'iiisssssiiiiii',
            ...[$sellerId, ...$this->values($d)]
        );
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // Change an outfit. It goes back to 'pending' so the admin checks the new version
    public function updateProduct(int $id, int $sellerId, array $d): void
    {
        $this->run(
            "UPDATE bc_products SET category_id = ?, designer_id = ?, title = ?, description = ?, item_condition = ?,
                    size = ?, colour = ?, is_for_sale = ?, sale_price = ?, is_for_rent = ?, rent_price_per_day = ?,
                    deposit = ?, stock = ?, status = 'pending', rejection_reason = NULL
              WHERE id = ? AND seller_id = ? AND status NOT IN ('sold', 'archived')",
            'iisssssiiiiiiii',
            ...[...$this->values($d), $id, $sellerId]
        )->close();
    }

    // The 13 values in the same order as FIELDS
    private function values(array $d): array
    {
        return [
            $d['category_id'], $d['designer_id'], $d['title'], $d['description'], $d['item_condition'],
            $d['size'], $d['colour'], $d['is_for_sale'], $d['sale_price'],
            $d['is_for_rent'], $d['rent_price_per_day'], $d['deposit'], $d['stock'],
        ];
    }

    // Remove an outfit from the shop for good
    public function archiveProduct(int $id, int $sellerId): void
    {
        $this->run("UPDATE bc_products SET status = 'archived' WHERE id = ? AND seller_id = ?", 'ii', $id, $sellerId)->close();
    }

    // Delete an outfit completely
    public function deleteProduct(int $id, int $sellerId): void
    {
        $this->run('DELETE FROM bc_products WHERE id = ? AND seller_id = ?', 'ii', $id, $sellerId)->close();
    }

    // Photos

    // Photos of an outfit, cover first
    public function getImages(int $productId): array
    {
        return $this->fetchAll('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id', 'i', $productId);
    }

    public function countImages(int $productId): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS n FROM product_images WHERE product_id = ?', 'i', $productId)['n'];
    }

    // Save the name of an uploaded photo
    public function addImage(int $productId, string $fileName, int $sortOrder): void
    {
        $this->run('INSERT INTO product_images (product_id, file_name, sort_order) VALUES (?, ?, ?)',
                   'isi', $productId, $fileName, $sortOrder)->close();
    }

    // One photo, ONLY if its outfit belongs to this seller
    public function getSellerImage(int $imageId, int $sellerId): ?array
    {
        return $this->fetchOne(
            'SELECT i.* FROM product_images i JOIN bc_products p ON p.id = i.product_id
              WHERE i.id = ? AND p.seller_id = ?',
            'ii', $imageId, $sellerId
        );
    }

    public function deleteImage(int $imageId): void
    {
        $this->run('DELETE FROM product_images WHERE id = ?', 'i', $imageId)->close();
    }

    // Make this photo the cover (sort_order 0) and push the others after it
    public function setCover(int $imageId, int $productId): void
    {
        $this->run('UPDATE product_images SET sort_order = IF(id = ?, 0, sort_order + 1) WHERE product_id = ?',
                   'ii', $imageId, $productId)->close();
    }

    // Lists for the form

    // Visible categories, for the seller's drop-down list
    public function getActiveCategories(): array
    {
        return $this->fetchAll('SELECT id, name, category_type FROM bc_categories WHERE is_active = 1 ORDER BY sort_order, name');
    }

    // Visible designers, for the seller's drop-down list
    public function getActiveDesigners(): array
    {
        return $this->fetchAll('SELECT id, name FROM designers WHERE is_active = 1 ORDER BY name');
    }

    public function isActiveCategory(int $id): bool
    {
        return $this->fetchOne('SELECT id FROM bc_categories WHERE id = ? AND is_active = 1', 'i', $id) !== null;
    }

    public function isActiveDesigner(int $id): bool
    {
        return $this->fetchOne('SELECT id FROM designers WHERE id = ? AND is_active = 1', 'i', $id) !== null;
    }

    // Make a buyer a seller (from the "Become a seller" form)
    public function makeSeller(int $userId, ?string $shopName): void
    {
        $this->run("UPDATE users SET role = 'seller', shop_name = ? WHERE id = ? AND role = 'buyer'",
                   'si', $shopName, $userId)->close();
    }

    // Admin: checking outfits

    // Outfits for the admin, with the seller, category, designer and cover photo
    public function getProductsForAdmin(string $status): array
    {
        $where = $status === '' ? "p.status <> 'archived'" : 'p.status = ?';
        $sql = "SELECT p.*, c.name AS category_name, d.name AS designer_name,
                       u.full_name AS seller_name, u.shop_name, u.phone AS seller_phone, u.email AS seller_email, u.region AS seller_region
                  FROM bc_products p
                  JOIN bc_categories c ON c.id = p.category_id
                  JOIN users u      ON u.id = p.seller_id
             LEFT JOIN designers d  ON d.id = p.designer_id
                 WHERE $where
              ORDER BY (p.status = 'pending') DESC, p.updated_at ASC";
        return $status === '' ? $this->fetchAll($sql) : $this->fetchAll($sql, 's', $status);
    }

    // How many outfits in each status (for the tabs)
    public function countByStatus(): array
    {
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($this->fetchAll('SELECT status, COUNT(*) AS n FROM bc_products GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        return $counts;
    }

    // One outfit (any seller), for the admin
    public function getProductById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM bc_products WHERE id = ?', 'i', $id);
    }

    // Approve: the outfit appears in the shop
    public function approveProduct(int $id): void
    {
        $this->run("UPDATE bc_products SET status = 'approved', rejection_reason = NULL
                     WHERE id = ? AND status IN ('pending', 'rejected')", 'i', $id)->close();
    }

    // Refuse (or take out of the shop), with the reason shown to the seller
    public function rejectProduct(int $id, string $reason): void
    {
        $this->run("UPDATE bc_products SET status = 'rejected', rejection_reason = ?
                     WHERE id = ? AND status IN ('pending', 'approved')", 'si', $reason, $id)->close();
    }
}
