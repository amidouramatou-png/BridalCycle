<?php
require_once dirname(__DIR__) . '/classes/ShopClass.php';

class ShopController
{
    public const PER_PAGE = 12;
    public const SORTS = ['new', 'price_asc', 'price_desc'];
    public const MODES = ['buy', 'borrow'];
    public const CONDITIONS = ['new', 'pre-owned'];

    private ShopClass $shop;

    public function __construct()
    {
        $this->shop = new ShopClass();
    }

    // Clean filters from the address
    public static function readFilters(array $get, ?string $forcedMode = null): array
    {
        $str = fn (string $key): string => is_string($get[$key] ?? null) ? trim($get[$key]) : '';
        $slug = fn (string $v): string => preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $v) && strlen($v) <= 100 ? $v : '';

        // Search words: up to 80 characters, max 6 words, each word at least 2 characters
        $query = mb_substr(preg_replace('/\s+/', ' ', strip_tags($str('user_query'))), 0, 80);
        $words = array_slice(array_values(array_filter(explode(' ', $query), fn ($w) => mb_strlen($w) >= 2)), 0, 6);

        $page = filter_var($str('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);

        return [
            'cat'       => $slug($str('cat')),
            'designer'  => $slug($str('designer')),
            'mode'      => $forcedMode ?? (in_array($str('mode'), self::MODES, true) ? $str('mode') : ''),
            'condition' => in_array($str('condition'), self::CONDITIONS, true) ? $str('condition') : '',
            'sort'      => in_array($str('sort'), self::SORTS, true) ? $str('sort') : 'new',
            'q'         => $query,
            'words'     => $words,
            'page'      => $page ?: 1,
        ];
    }

    // One page of results: ['products', 'total', 'pages', 'page']
    public function getPage(array $filters): array
    {
        $total = $this->shop->countShopProducts($filters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($filters['page'], $pages);
        return [
            'products' => $total ? $this->shop->getShopProducts($filters, $filters['sort'], self::PER_PAGE, ($page - 1) * self::PER_PAGE) : [],
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
        ];
    }

    public function getProduct(int $id): ?array { return $this->shop->getShopProduct($id); }

    // Count a view
    public function countView(array $product, ?int $userId, bool $isAdmin, bool $alreadySeen): bool
    {
        if ($isAdmin || $alreadySeen || ($userId !== null && $userId === (int) $product['seller_id'])) {
            return false;
        }
        $this->shop->addView((int) $product['id']);
        return true;
    }
    public function getImages(int $id): array { return $this->shop->getImages($id); }
    public function getRelated(array $product): array { return $this->shop->getRelated((int) $product['id'], $product['category_slug'], 4); }
    public function getCategories(): array { return $this->shop->getCategoriesWithCount(); }
    public function getDesigners(): array { return $this->shop->getDesignersWithProducts(); }

    // Link to the same list with some filters changed
    public static function link(string $page, array $filters, array $change): string
    {
        $params = array_merge([
            'cat' => $filters['cat'], 'designer' => $filters['designer'], 'mode' => $filters['mode'],
            'condition' => $filters['condition'], 'sort' => $filters['sort'] === 'new' ? '' : $filters['sort'],
            'user_query' => $filters['q'],
        ], ['page' => ''], $change);
        $params = array_filter($params, fn ($v) => $v !== '' && $v !== null && $v !== 1);
        return url($page . ($params ? '?' . http_build_query($params) : ''));
    }
}
