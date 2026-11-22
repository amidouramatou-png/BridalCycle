<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class ShopClass extends Database
{
    // The part of the SQL shared by the list and the count
    private const FROM = "FROM bc_products p
                          JOIN bc_categories c ON c.id = p.category_id AND c.is_active = 1
                          JOIN users u      ON u.id = p.seller_id AND u.status = 'active'
                     LEFT JOIN designers d  ON d.id = p.designer_id AND d.is_active = 1
                         WHERE p.status = 'approved' AND p.stock > 0";

    // Build the WHERE part from the filters. Every value is a ? (prepared statement)
    private function where(array $f): array
    {
        $sql = '';
        $types = '';
        $params = [];

        if (!empty($f['cat']))       { $sql .= ' AND c.slug = ?';           $types .= 's'; $params[] = $f['cat']; }
        if (!empty($f['designer']))  { $sql .= ' AND d.slug = ?';           $types .= 's'; $params[] = $f['designer']; }
        if (($f['mode'] ?? '') === 'buy')    { $sql .= ' AND p.is_for_sale = 1'; }
        if (($f['mode'] ?? '') === 'borrow') { $sql .= ' AND p.is_for_rent = 1'; }
        if (!empty($f['condition'])) { $sql .= ' AND p.item_condition = ?'; $types .= 's'; $params[] = $f['condition']; }

        // Search: EVERY word must be found in the title
        foreach ($f['words'] ?? [] as $word) {
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $sql .= ' AND (p.title LIKE ? OR p.description LIKE ? OR p.colour LIKE ? OR p.size LIKE ? OR c.name LIKE ? OR d.name LIKE ?)';
            $types .= 'ssssss';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        return [$sql, $types, $params];
    }

    // One page of outfits for the shop
    public function getShopProducts(array $f, string $sort, int $limit, int $offset): array
    {
        [$where, $types, $params] = $this->where($f);
        $order = [
            'new'        => 'p.created_at DESC',
            'price_asc'  => 'COALESCE(p.sale_price, p.rent_price_per_day) ASC',
            'price_desc' => 'COALESCE(p.sale_price, p.rent_price_per_day) DESC',
        ][$sort] ?? 'p.created_at DESC';

        $sql = "SELECT p.id, p.title, p.item_condition, p.size, p.is_for_sale, p.sale_price,
                       p.is_for_rent, p.rent_price_per_day, c.name AS category_name, d.name AS designer_name,
                       (p.featured_until IS NOT NULL AND p.featured_until > NOW()) AS is_featured,
                       (SELECT IF(COUNT(*) >= p.stock, MIN(rb.end_date), NULL) FROM rentals rb
                      WHERE rb.product_id = p.id AND rb.status IN ('approved','paid','picked_up')
                        AND rb.start_date <= CURDATE() AND rb.end_date >= CURDATE()) AS borrowed_until,
                       (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.seller_id = p.seller_id AND r.is_hidden = 0) AS seller_rating,
                       (SELECT file_name FROM product_images i WHERE i.product_id = p.id
                         ORDER BY i.sort_order, i.id LIMIT 1) AS cover
                " . self::FROM . $where . "
              ORDER BY is_featured DESC, $order, p.id DESC
                 LIMIT ? OFFSET ?";
        return $this->fetchAll($sql, $types . 'ii', ...[...$params, $limit, $offset]);
    }

    // How many outfits match (for "12 outfits" and the page numbers)
    public function countShopProducts(array $f): int
    {
        [$where, $types, $params] = $this->where($f);
        $row = $types === ''
            ? $this->fetchOne('SELECT COUNT(*) AS n ' . self::FROM . $where)
            : $this->fetchOne('SELECT COUNT(*) AS n ' . self::FROM . $where, $types, ...$params);
        return (int) $row['n'];
    }

    // One more view of an outfit page (the counter seen by the seller and the admin)
    public function addView(int $id): void
    {
        $this->run('UPDATE bc_products SET view_count = view_count + 1 WHERE id = ?', 'i', $id)->close();
    }

    // One outfit for its page, with the seller's PUBLIC information only
    public function getShopProduct(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                    d.name AS designer_name, d.slug AS designer_slug, d.country AS designer_country,
                    u.full_name AS seller_name, u.shop_name, u.hide_name AS seller_hidden, u.region AS seller_region,
                    (SELECT IF(COUNT(*) >= p.stock, MIN(rb.end_date), NULL) FROM rentals rb
                      WHERE rb.product_id = p.id AND rb.status IN ('approved','paid','picked_up')
                        AND rb.start_date <= CURDATE() AND rb.end_date >= CURDATE()) AS borrowed_until
             " . self::FROM . ' AND p.id = ?',
            'i', $id
        );
    }

    // Photos of an outfit, cover first
    public function getImages(int $productId): array
    {
        return $this->fetchAll('SELECT file_name FROM product_images WHERE product_id = ? ORDER BY sort_order, id', 'i', $productId);
    }

    // Other outfits of the same tradition (for "You may also like")
    public function getRelated(int $productId, string $categorySlug, int $limit): array
    {
        return $this->getShopProductsExcept($productId, ['cat' => $categorySlug], $limit);
    }

    private function getShopProductsExcept(int $exceptId, array $f, int $limit): array
    {
        return array_slice(array_values(array_filter(
            $this->getShopProducts($f, 'new', $limit + 1, 0),
            fn ($p) => (int) $p['id'] !== $exceptId
        )), 0, $limit);
    }

    // Visible categories that have at least one outfit in the shop, with how many
    public function getCategoriesWithCount(): array
    {
        return $this->fetchAll(
            "SELECT c.name, c.slug, c.category_type, COUNT(p.id) AS n
               FROM bc_categories c
               LEFT JOIN bc_products p ON p.category_id = c.id AND p.status = 'approved' AND p.stock > 0
              WHERE c.is_active = 1
           GROUP BY c.id
           ORDER BY c.sort_order, c.name"
        );
    }

    // Visible designers that have outfits in the shop
    public function getDesignersWithProducts(): array
    {
        return $this->fetchAll(
            "SELECT d.name, d.slug, COUNT(p.id) AS n
               FROM designers d
               JOIN bc_products p ON p.designer_id = d.id AND p.status = 'approved' AND p.stock > 0
              WHERE d.is_active = 1
           GROUP BY d.id
           ORDER BY d.name"
        );
    }
}
