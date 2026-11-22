<?php
require_once dirname(__DIR__) . '/classes/DesignerClass.php';
require_once dirname(__DIR__) . '/classes/SecurityClass.php';
require_once __DIR__ . '/CategoryController.php';

class DesignerController
{
    private DesignerClass $designers;
    private SecurityClass $security;

    public function __construct()
    {
        $this->designers = new DesignerClass();
        $this->security  = new SecurityClass();
    }

    public function getAllDesigners(bool $activeOnly = false): array
    {
        return $this->designers->getAllDesigners($activeOnly);
    }

    public function getDesigner(int $id): ?array
    {
        return $this->designers->getDesignerById($id);
    }

    // Clean and check the designer form (used by add AND update actions)
    public static function validateForm(array $post): array
    {
        $clean = fn (string $key): string => trim(strip_tags((string) ($post[$key] ?? '')));

        $data = [
            'name'        => preg_replace('/\s+/', ' ', $clean('name')),
            'slug'        => strtolower($clean('slug')),
            'country'     => preg_replace('/\s+/', ' ', $clean('country')),
            'description' => preg_replace('/\s+/', ' ', $clean('description')),
        ];
        if ($data['slug'] === '') {
            $data['slug'] = CategoryController::makeSlug($data['name']);
        }

        $errors = [];
        // Name: 2 to 100 characters. Letters, numbers, spaces and ' & / ()
        if (!preg_match("/^[\p{L}\p{N}][\p{L}\p{N}\s'’&\/().,-]{1,99}$/u", $data['name'])) {
            $errors['name'] = msg('des.err_name');
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $data['slug']) || strlen($data['slug']) > 100) {
            $errors['slug'] = msg('cat.err_slug');
        }
        // Country: optional, letters, spaces, apostrophes and hyphens (up to 60)
        if ($data['country'] !== '' && !preg_match("/^[\p{L}][\p{L}\s'’-]{1,59}$/u", $data['country'])) {
            $errors['country'] = msg('des.err_country');
        }
        if (mb_strlen($data['description']) > 255) {
            $errors['description'] = msg('cat.err_description');
        }
        return [$data, $errors];
    }

    // Add a designer
    public function addDesigner(array $data, int $adminId, string $ip): array
    {
        if ($this->designers->slugExists($data['slug'])) {
            return ['success' => false, 'error' => 'des.err_slug_taken'];
        }
        try {
            $id = $this->designers->addDesigner($data['name'], $data['slug'],
                                                $data['country'] ?: null, $data['description'] ?: null);
        } catch (mysqli_sql_exception $e) {
            error_log('Add designer failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getCode() === 1062 ? 'des.err_slug_taken' : 'reg.err_server'];
        }
        $this->security->logAdminAction($adminId, 'add_designer', $ip, 'designer', $id, $data['name']);
        return ['success' => true, 'id' => $id];
    }

    // Change a designer
    public function updateDesigner(int $id, array $data, int $adminId, string $ip): array
    {
        if ($this->designers->getDesignerById($id) === null) {
            return ['success' => false, 'error' => 'des.err_not_found'];
        }
        if ($this->designers->slugExists($data['slug'], $id)) {
            return ['success' => false, 'error' => 'des.err_slug_taken'];
        }
        try {
            $this->designers->updateDesigner($id, $data['name'], $data['slug'],
                                             $data['country'] ?: null, $data['description'] ?: null);
        } catch (mysqli_sql_exception $e) {
            error_log('Update designer failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getCode() === 1062 ? 'des.err_slug_taken' : 'reg.err_server'];
        }
        $this->security->logAdminAction($adminId, 'update_designer', $ip, 'designer', $id, $data['name']);
        return ['success' => true];
    }

    // Hide a designer from the shop, or show it again
    public function toggleDesigner(int $id, int $adminId, string $ip): array
    {
        $designer = $this->designers->getDesignerById($id);
        if ($designer === null) {
            return ['success' => false, 'error' => 'des.err_not_found'];
        }
        $show = (int) $designer['is_active'] === 0;
        $this->designers->setActive($id, $show);
        $this->security->logAdminAction($adminId, $show ? 'show_designer' : 'hide_designer', $ip, 'designer', $id, $designer['name']);
        return ['success' => true, 'key' => $show ? 'cat.shown' : 'cat.hidden', 'name' => $designer['name']];
    }

    // Delete a designer, ONLY if no outfit uses it (otherwise hide it)
    public function deleteDesigner(int $id, int $adminId, string $ip): array
    {
        $designer = $this->designers->getDesignerById($id);
        if ($designer === null) {
            return ['success' => false, 'error' => 'des.err_not_found'];
        }
        if ((int) $designer['product_count'] > 0) {
            return ['success' => false, 'error' => 'des.err_in_use'];
        }
        try {
            $this->designers->deleteDesigner($id);
        } catch (mysqli_sql_exception $e) {
            error_log('Delete designer failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'des.err_in_use'];
        }
        $this->security->logAdminAction($adminId, 'delete_designer', $ip, 'designer', $id, $designer['name']);
        return ['success' => true, 'name' => $designer['name']];
    }
}
