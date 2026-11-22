<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class CategoryClass extends Database
{
    // All categories, sorted, with how many outfits use each one
    public function getAllCategories(bool $activeOnly = false): array
    {
        $sql = 'SELECT c.*, COUNT(p.id) AS product_count
                  FROM bc_categories c
             LEFT JOIN bc_products p ON p.category_id = c.id
                 ' . ($activeOnly ? 'WHERE c.is_active = 1' : '') . '
              GROUP BY c.id
              ORDER BY c.sort_order, c.name';
        return $this->fetchAll($sql);
    }

    // One category by id, or null
    public function getCategoryById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT c.*, (SELECT COUNT(*) FROM bc_products p WHERE p.category_id = c.id) AS product_count
               FROM bc_categories c WHERE c.id = ?',
            'i', $id
        );
    }

    // Is this slug already used by ANOTHER category?
    public function slugExists(string $slug, int $exceptId = 0): bool
    {
        return $this->fetchOne('SELECT id FROM bc_categories WHERE slug = ? AND id <> ?', 'si', $slug, $exceptId) !== null;
    }

    // Add a category. Returns its new id
    public function addCategory(string $name, string $slug, string $type, ?string $description, int $sortOrder): int
    {
        $stmt = $this->run(
            'INSERT INTO bc_categories (name, slug, category_type, description, sort_order) VALUES (?, ?, ?, ?, ?)',
            'ssssi',
            $name, $slug, $type, $description, $sortOrder
        );
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // Change a category
    public function updateCategory(int $id, string $name, string $slug, string $type, ?string $description, int $sortOrder): void
    {
        $this->run(
            'UPDATE bc_categories SET name = ?, slug = ?, category_type = ?, description = ?, sort_order = ? WHERE id = ?',
            'ssssii',
            $name, $slug, $type, $description, $sortOrder, $id
        )->close();
    }

    // Show (1) or hide (0) a category in the shop
    public function setActive(int $id, bool $active): void
    {
        $this->run('UPDATE bc_categories SET is_active = ? WHERE id = ?', 'ii', $active ? 1 : 0, $id)->close();
    }

    // Delete a category
    public function deleteCategory(int $id): void
    {
        $this->run('DELETE FROM bc_categories WHERE id = ?', 'i', $id)->close();
    }
}
