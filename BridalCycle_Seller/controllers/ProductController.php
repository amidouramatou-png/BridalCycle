<?php
require_once dirname(__DIR__) . '/classes/ProductClass.php';

class ProductController
{
    public const CONDITIONS = ['new', 'pre-owned'];
    public const PRICE_MIN  = 500;
    public const PRICE_MAX  = 100000000;
    // Photo types we accept
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private ProductClass $products;

    public function __construct()
    {
        $this->products = new ProductClass();
    }

    public function getSellerProducts(int $sellerId): array { return $this->products->getSellerProducts($sellerId); }
    public function getSellerProduct(int $id, int $sellerId): ?array { return $this->products->getSellerProduct($id, $sellerId); }
    public function getImages(int $productId): array { return $this->products->getImages($productId); }
    public function getActiveCategories(): array { return $this->products->getActiveCategories(); }
    public function getActiveDesigners(): array { return $this->products->getActiveDesigners(); }

    // Folder where outfit photos are saved
    public static function imageDir(): string
    {
        return dirname(__DIR__) . '/images/products/';
    }

    // Clean and check the outfit form
    public function validateForm(array $post): array
    {
        $clean = fn (string $key): string => trim(strip_tags((string) ($post[$key] ?? '')));
        $int   = fn (string $key) => filter_var(str_replace([' ', '.', ','], '', $clean($key)), FILTER_VALIDATE_INT);

        $data = [
            'title'          => preg_replace('/\s+/', ' ', $clean('title')),
            'description'    => trim(strip_tags((string) ($post['description'] ?? ''))),
            'category_id'    => $int('category_id'),
            'designer_id'    => $clean('designer_id') === '' ? null : $int('designer_id'),
            'item_condition' => $clean('item_condition'),
            'size'           => preg_replace('/\s+/', ' ', $clean('size')),
            'colour'         => preg_replace('/\s+/', ' ', $clean('colour')),
            'is_for_sale'    => isset($post['is_for_sale']) ? 1 : 0,
            'is_for_rent'    => isset($post['is_for_rent']) ? 1 : 0,
            'stock'          => $clean('stock') === '' ? 1 : $int('stock'),
        ];
        $data['sale_price']         = $data['is_for_sale'] ? $int('sale_price') : null;
        $data['rent_price_per_day'] = $data['is_for_rent'] ? $int('rent_price_per_day') : null;
        $data['deposit']            = $data['is_for_rent'] ? ($clean('deposit') === '' ? 0 : $int('deposit')) : null;

        $errors = [];
        if (!preg_match("/^[\p{L}\p{N}][\p{L}\p{N}\s'’&\/().,:!+-]{2,149}$/u", $data['title'])) {
            $errors['title'] = msg('prod.err_title');
        }
        $len = mb_strlen($data['description']);
        if ($len < 20 || $len > 3000) {
            $errors['description'] = msg('prod.err_description');
        }
        if ($data['category_id'] === false || $data['category_id'] === null || !$this->products->isActiveCategory($data['category_id'])) {
            $errors['category_id'] = msg('prod.err_category');
        }
        if ($data['designer_id'] === false || ($data['designer_id'] !== null && !$this->products->isActiveDesigner($data['designer_id']))) {
            $errors['designer_id'] = msg('prod.err_designer');
        }
        if (!in_array($data['item_condition'], self::CONDITIONS, true)) {
            $errors['item_condition'] = msg('prod.err_condition');
        }
        if (!preg_match("/^[\p{L}\p{N}][\p{L}\p{N}\s\/.-]{0,19}$/u", $data['size'])) {
            $errors['size'] = msg('prod.err_size');
        }
        if ($data['colour'] !== '' && !preg_match("/^[\p{L}][\p{L}\s,'’-]{1,39}$/u", $data['colour'])) {
            $errors['colour'] = msg('prod.err_colour');
        }
        if (!is_int($data['stock']) || $data['stock'] < 1 || $data['stock'] > 20) {
            $errors['stock'] = msg('prod.err_stock');
        }
        // At least one way: sell, lend, or both
        if (!$data['is_for_sale'] && !$data['is_for_rent']) {
            $errors['mode'] = msg('prod.err_mode');
        }
        $price = fn ($v) => is_int($v) && $v >= self::PRICE_MIN && $v <= self::PRICE_MAX;
        if ($data['is_for_sale'] && !$price($data['sale_price'])) {
            $errors['sale_price'] = msg('prod.err_price', ['min' => money(self::PRICE_MIN)]);
        }
        if ($data['is_for_rent'] && !$price($data['rent_price_per_day'])) {
            $errors['rent_price_per_day'] = msg('prod.err_price', ['min' => money(self::PRICE_MIN)]);
        }
        if ($data['is_for_rent'] && (!is_int($data['deposit']) || $data['deposit'] < 0 || $data['deposit'] > self::PRICE_MAX)) {
            $errors['deposit'] = msg('prod.err_deposit');
        }
        $data['colour'] = $data['colour'] ?: null;
        return [$data, $errors];
    }

    // Check the uploaded photos ($_FILES['photos']) WITHOUT saving them yet
    public function checkPhotos(?array $upload, int $alreadyThere): array
    {
        $files = [];
        // No photo field at all (a normal browser always sends it, a hacker may not)
        $codes = (is_array($upload) && isset($upload['error']) && is_array($upload['error'])) ? $upload['error'] : [];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($codes as $i => $code) {
            if ($code === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
                return [[], msg('prod.err_photo_size', ['mb' => UPLOAD_MAX_BYTES / 1048576])];
            }
            if ($code !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'][$i])) {
                return [[], msg('prod.err_photo_upload')];
            }
            $tmp = $upload['tmp_name'][$i];
            // 1. Size
            if (filesize($tmp) > UPLOAD_MAX_BYTES) {
                return [[], msg('prod.err_photo_size', ['mb' => UPLOAD_MAX_BYTES / 1048576])];
            }
            // 2. The REAL type, read from inside the file (the name and the browser can lie)
            $mime = $finfo->file($tmp);
            if (!isset(self::IMAGE_TYPES[$mime])) {
                return [[], msg('prod.err_photo_type')];
            }
            // 3. Is it really a picture that can be opened? (a fake image fails here)
            $info = @getimagesize($tmp);
            if ($info === false || $info[0] < 200 || $info[1] < 200 || $info[0] > 8000 || $info[1] > 8000) {
                return [[], msg('prod.err_photo_type')];
            }
            $files[] = ['tmp' => $tmp, 'ext' => self::IMAGE_TYPES[$mime]];
        }
        if ($alreadyThere + count($files) > UPLOAD_MAX_PER_OUTFIT) {
            return [[], msg('prod.err_photo_count', ['max' => UPLOAD_MAX_PER_OUTFIT])];
        }
        // At least UPLOAD_MIN_PER_OUTFIT photos in total (photos already saved + new ones)
        if ($alreadyThere + count($files) < UPLOAD_MIN_PER_OUTFIT) {
            return [[], msg('prod.err_photo_needed', ['min' => UPLOAD_MIN_PER_OUTFIT])];
        }
        return [$files, null];
    }

    // Move the checked photos into images/products/ with a RANDOM name
    private function savePhotos(int $productId, array $files): void
    {
        $order = $this->products->countImages($productId);
        foreach ($files as $file) {
            $name = bin2hex(random_bytes(16)) . '.' . $file['ext'];
            if (!move_uploaded_file($file['tmp'], self::imageDir() . $name)) {
                throw new RuntimeException('Could not save the photo.');
            }
            $this->products->addImage($productId, $name, $order++);
        }
    }

    // Add an outfit with its photos
    public function addProduct(int $sellerId, array $data, array $files): array
    {
        try {
            $id = $this->products->addProduct($sellerId, $data);
            $this->savePhotos($id, $files);
        } catch (Throwable $e) {
            error_log('Add outfit failed: ' . $e->getMessage());
            if (isset($id)) {
                $this->deleteFilesOf($id);
                $this->products->deleteProduct($id, $sellerId);
            }
            return ['success' => false, 'error' => 'reg.err_server'];
        }
        return ['success' => true, 'id' => $id];
    }

    // Change an outfit (only the seller's own) and add new photos
    public function updateProduct(int $id, int $sellerId, array $data, array $files): array
    {
        $product = $this->products->getSellerProduct($id, $sellerId);
        if ($product === null || in_array($product['status'], ['sold', 'archived'], true)) {
            return ['success' => false, 'error' => 'prod.err_not_found'];
        }
        try {
            $this->products->updateProduct($id, $sellerId, $data);
            $this->savePhotos($id, $files);
        } catch (Throwable $e) {
            error_log('Update outfit failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }
        return ['success' => true];
    }

    // Delete a photo (an outfit always keeps at least UPLOAD_MIN_PER_OUTFIT photos)
    public function deletePhoto(int $imageId, int $sellerId): array
    {
        $image = $this->products->getSellerImage($imageId, $sellerId);
        if ($image === null) {
            return ['success' => false, 'error' => 'prod.err_not_found'];
        }
        if ($this->products->countImages((int) $image['product_id']) <= UPLOAD_MIN_PER_OUTFIT) {
            return ['success' => false, 'error' => 'prod.err_last_photo', 'product_id' => (int) $image['product_id']];
        }
        $this->products->deleteImage($imageId);
        $this->deleteFile($image['file_name']);
        return ['success' => true, 'product_id' => (int) $image['product_id']];
    }

    // Choose the cover photo
    public function setCover(int $imageId, int $sellerId): array
    {
        $image = $this->products->getSellerImage($imageId, $sellerId);
        if ($image === null) {
            return ['success' => false, 'error' => 'prod.err_not_found'];
        }
        $this->products->setCover($imageId, (int) $image['product_id']);
        return ['success' => true, 'product_id' => (int) $image['product_id']];
    }

    // Remove an outfit. If it was never ordered or borrowed: deleted with its photos
    public function removeProduct(int $id, int $sellerId): array
    {
        $product = $this->products->getSellerProduct($id, $sellerId);
        if ($product === null) {
            return ['success' => false, 'error' => 'prod.err_not_found'];
        }
        $images = $this->products->getImages($id);
        try {
            $this->products->deleteProduct($id, $sellerId);
            foreach ($images as $image) {
                $this->deleteFile($image['file_name']);
            }
            return ['success' => true, 'key' => 'prod.deleted', 'title' => $product['title']];
        } catch (mysqli_sql_exception $e) {
            $this->products->archiveProduct($id, $sellerId);
            return ['success' => true, 'key' => 'prod.archived', 'title' => $product['title']];
        }
    }

    // "Become a seller": a bride (buyer) opens her boutique
    public function becomeSeller(int $userId, ?string $shopName): void
    {
        $this->products->makeSeller($userId, $shopName);
    }

    private function deleteFilesOf(int $productId): void
    {
        foreach ($this->products->getImages($productId) as $image) {
            $this->deleteFile($image['file_name']);
        }
    }

    // Delete a photo file
    private function deleteFile(string $name): void
    {
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name) && is_file(self::imageDir() . $name)) {
            unlink(self::imageDir() . $name);
        }
    }
}
