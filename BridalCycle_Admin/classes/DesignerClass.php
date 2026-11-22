<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class DesignerClass extends Database
{
    // All designers (A to Z), with how many outfits each one has
    public function getAllDesigners(bool $activeOnly = false): array
    {
        $sql = 'SELECT d.*, COUNT(p.id) AS product_count
                  FROM designers d
             LEFT JOIN bc_products p ON p.designer_id = d.id
                 ' . ($activeOnly ? 'WHERE d.is_active = 1' : '') . '
              GROUP BY d.id
              ORDER BY d.name';
        return $this->fetchAll($sql);
    }

    // One designer by id, or null
    public function getDesignerById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT d.*, (SELECT COUNT(*) FROM bc_products p WHERE p.designer_id = d.id) AS product_count
               FROM designers d WHERE d.id = ?',
            'i', $id
        );
    }

    // Is this slug already used by ANOTHER designer?
    public function slugExists(string $slug, int $exceptId = 0): bool
    {
        return $this->fetchOne('SELECT id FROM designers WHERE slug = ? AND id <> ?', 'si', $slug, $exceptId) !== null;
    }

    // Add a designer. Returns the new id
    public function addDesigner(string $name, string $slug, ?string $country, ?string $description): int
    {
        $stmt = $this->run(
            'INSERT INTO designers (name, slug, country, description) VALUES (?, ?, ?, ?)',
            'ssss',
            $name, $slug, $country, $description
        );
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // Change a designer
    public function updateDesigner(int $id, string $name, string $slug, ?string $country, ?string $description): void
    {
        $this->run(
            'UPDATE designers SET name = ?, slug = ?, country = ?, description = ? WHERE id = ?',
            'ssssi',
            $name, $slug, $country, $description, $id
        )->close();
    }

    // Show (1) or hide (0) a designer in the shop
    public function setActive(int $id, bool $active): void
    {
        $this->run('UPDATE designers SET is_active = ? WHERE id = ?', 'ii', $active ? 1 : 0, $id)->close();
    }

    // Delete a designer
    public function deleteDesigner(int $id): void
    {
        $this->run('DELETE FROM designers WHERE id = ?', 'i', $id)->close();
    }
}
