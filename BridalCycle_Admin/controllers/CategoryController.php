<?php
require_once dirname(__DIR__) . '/classes/CategoryClass.php';
require_once dirname(__DIR__) . '/classes/SecurityClass.php';

class CategoryController
{
    // The 3 kinds of categories (the same list as the database ENUM)
    public const TYPES = ['tradition', 'inspiration', 'style'];

    private CategoryClass $categories;
    private SecurityClass $security;

    public function __construct()
    {
        $this->categories = new CategoryClass();
        $this->security   = new SecurityClass();
    }

    public function getAllCategories(bool $activeOnly = false): array
    {
        return $this->categories->getAllCategories($activeOnly);
    }

    public function getCategory(int $id): ?array
    {
        return $this->categories->getCategoryById($id);
    }

    // Make a slug (the short name used in links) from a name
    public static function makeSlug(string $name): string
    {
        $accents = ['à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                    'î'=>'i','ï'=>'i','í'=>'i','ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
                    'ú'=>'u','ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae'];
        $slug = strtr(mb_strtolower($name, 'UTF-8'), $accents);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return substr(trim($slug, '-'), 0, 80);
    }

    // Clean and check the category form
    public static function validateForm(array $post): array
    {
        $clean = fn (string $key): string => trim(strip_tags((string) ($post[$key] ?? '')));

        $data = [
            'name'        => preg_replace('/\s+/', ' ', $clean('name')),
            'slug'        => strtolower($clean('slug')),
            'type'        => $clean('type'),
            'description' => preg_replace('/\s+/', ' ', $clean('description')),
            'sort_order'  => $clean('sort_order'),
        ];
        // No slug typed? Make one from the name
        if ($data['slug'] === '') {
            $data['slug'] = self::makeSlug($data['name']);
        }

        $errors = [];
        // Name: 2 to 80 characters. Letters (accents ok), numbers, spaces and ' & / ()
        if (!preg_match("/^[\p{L}\p{N}][\p{L}\p{N}\s'’&\/().,-]{1,79}$/u", $data['name'])) {
            $errors['name'] = msg('cat.err_name');
        }
        // Slug: small letters, numbers and single hyphens only, e.g. "grand-boubou"
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $data['slug']) || strlen($data['slug']) > 80) {
            $errors['slug'] = msg('cat.err_slug');
        }
        if (!in_array($data['type'], self::TYPES, true)) {
            $errors['type'] = msg('cat.err_type');
        }
        if (mb_strlen($data['description']) > 255) {
            $errors['description'] = msg('cat.err_description');
        }
        // Display order: a whole number from 0 to 999 (empty = 0)
        if ($data['sort_order'] === '') {
            $data['sort_order'] = '0';
        }
        if (!preg_match('/^\d{1,3}$/', $data['sort_order'])) {
            $errors['sort_order'] = msg('cat.err_order');
        }
        $data['sort_order'] = (int) $data['sort_order'];

        return [$data, $errors];
    }

    // Add a category. $data was cleaned and validated by add_category_action.php
    public function addCategory(array $data, int $adminId, string $ip): array
    {
        if ($this->categories->slugExists($data['slug'])) {
            return ['success' => false, 'error' => 'cat.err_slug_taken'];
        }
        try {
            $id = $this->categories->addCategory($data['name'], $data['slug'], $data['type'],
                                                 $data['description'] ?: null, $data['sort_order']);
        } catch (mysqli_sql_exception $e) {
            error_log('Add category failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getCode() === 1062 ? 'cat.err_slug_taken' : 'reg.err_server'];
        }
        $this->security->logAdminAction($adminId, 'add_category', $ip, 'category', $id, $data['name']);
        return ['success' => true, 'id' => $id];
    }

    // Change a category
    public function updateCategory(int $id, array $data, int $adminId, string $ip): array
    {
        if ($this->categories->getCategoryById($id) === null) {
            return ['success' => false, 'error' => 'cat.err_not_found'];
        }
        if ($this->categories->slugExists($data['slug'], $id)) {
            return ['success' => false, 'error' => 'cat.err_slug_taken'];
        }
        try {
            $this->categories->updateCategory($id, $data['name'], $data['slug'], $data['type'],
                                              $data['description'] ?: null, $data['sort_order']);
        } catch (mysqli_sql_exception $e) {
            error_log('Update category failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getCode() === 1062 ? 'cat.err_slug_taken' : 'reg.err_server'];
        }
        $this->security->logAdminAction($adminId, 'update_category', $ip, 'category', $id, $data['name']);
        return ['success' => true];
    }

    // Hide a category from the shop, or show it again
    public function toggleCategory(int $id, int $adminId, string $ip): array
    {
        $category = $this->categories->getCategoryById($id);
        if ($category === null) {
            return ['success' => false, 'error' => 'cat.err_not_found'];
        }
        $show = (int) $category['is_active'] === 0;
        $this->categories->setActive($id, $show);
        $this->security->logAdminAction($adminId, $show ? 'show_category' : 'hide_category', $ip, 'category', $id, $category['name']);
        return ['success' => true, 'key' => $show ? 'cat.shown' : 'cat.hidden', 'name' => $category['name']];
    }

    // Delete a category, ONLY if no outfit uses it
    public function deleteCategory(int $id, int $adminId, string $ip): array
    {
        $category = $this->categories->getCategoryById($id);
        if ($category === null) {
            return ['success' => false, 'error' => 'cat.err_not_found'];
        }
        if ((int) $category['product_count'] > 0) {
            return ['success' => false, 'error' => 'cat.err_in_use'];
        }
        try {
            $this->categories->deleteCategory($id);
        } catch (mysqli_sql_exception $e) {
            // An outfit was added at the same second
            error_log('Delete category failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'cat.err_in_use'];
        }
        $this->security->logAdminAction($adminId, 'delete_category', $ip, 'category', $id, $category['name']);
        return ['success' => true, 'name' => $category['name']];
    }
}
