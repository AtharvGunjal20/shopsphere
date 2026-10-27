<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class Catalog
{
    public const SELECT = 'p.id, p.sku, p.slug, p.name, p.brand, p.category_id, p.subcategory_id, p.short_desc, p.mrp, p.price, p.discount_pct,
        p.stock, p.rating_avg, p.rating_count, p.sold_count, p.is_active, p.is_deal, p.created_at, p.gst_rate,
        (SELECT url FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image';

    private static ?array $wishIds = null;

    // ---- categories -----------------------------------------------------------
    public static function categoryTree(): array
    {
        static $tree = null;
        if ($tree !== null) return $tree;
        $rows = DB::all('SELECT id, parent_id, name, slug, icon FROM categories ORDER BY sort_order, name');
        $tree = [];
        foreach ($rows as $r) if (!$r['parent_id']) $tree[$r['id']] = $r + ['children' => []];
        foreach ($rows as $r) if ($r['parent_id'] && isset($tree[$r['parent_id']])) $tree[$r['parent_id']]['children'][] = $r;
        return $tree;
    }

    public static function categoryBySlug(string $slug): ?array
    {
        return DB::one('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    // ---- product lists -----------------------------------------------------------
    private static function base(string $where = '1=1', string $order = 'p.id DESC', int $limit = 12, array $params = []): array
    {
        $limit = max(1, min(60, $limit));
        return DB::all(
            'SELECT ' . self::SELECT . " FROM products p WHERE p.is_active = 1 AND $where ORDER BY $order LIMIT $limit",
            $params
        );
    }

    public static function deals(int $n = 12): array
    {
        return self::base('p.stock > 0 AND p.discount_pct >= 30', 'p.discount_pct DESC, p.sold_count DESC', $n);
    }

    public static function bestsellers(int $n = 12): array
    {
        return self::base('p.stock > 0', 'p.sold_count DESC', $n);
    }

    public static function newArrivals(int $n = 12): array
    {
        return self::base('1=1', 'p.created_at DESC, p.id DESC', $n);
    }

    public static function inCategory(string $slug, int $n = 12, string $order = 'p.sold_count DESC'): array
    {
        $c = self::categoryBySlug($slug);
        if (!$c) return [];
        return self::base('p.category_id = ?', $order, $n, [$c['id']]);
    }

    public static function topRated(int $n = 8): array
    {
        return self::base('p.rating_count > 0 AND p.stock > 0', 'p.rating_avg DESC, p.rating_count DESC', $n);
    }

    public static function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::all('SELECT ' . self::SELECT . " FROM products p WHERE p.id IN ($in) AND p.is_active = 1", $ids);
        $map = [];
        foreach ($rows as $r) $map[$r['id']] = $r;
        $out = [];
        foreach ($ids as $id) if (isset($map[$id])) $out[] = $map[$id];   // preserve requested order
        return $out;
    }

    public static function bySlug(string $slug): ?array
    {
        $p = DB::one(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, sc.name AS sub_name, sc.slug AS sub_slug
             FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN categories sc ON sc.id = p.subcategory_id
             WHERE p.slug = ?',
            [$slug]
        );
        return $p ?: null;
    }

    public static function images(int $productId): array
    {
        return DB::all('SELECT url, alt FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [$productId]);
    }

    public static function related(array $p, int $n = 8): array
    {
        return self::base('p.id <> ? AND (p.subcategory_id = ? OR p.category_id = ?)', 'p.rating_avg DESC, p.sold_count DESC', $n, [$p['id'], $p['subcategory_id'], $p['category_id']]);
    }

    /** "Frequently bought together": real co-purchases first, topped up from the same category. */
    public static function boughtTogether(array $p, int $n = 3): array
    {
        $rows = DB::all(
            'SELECT oi2.product_id AS pid, COUNT(*) AS c
             FROM order_items oi1 JOIN order_items oi2 ON oi1.order_id = oi2.order_id AND oi2.product_id <> oi1.product_id
             WHERE oi1.product_id = ? AND oi2.product_id IS NOT NULL GROUP BY oi2.product_id ORDER BY c DESC LIMIT ' . (int)$n,
            [$p['id']]
        );
        $ids = array_map(fn($r) => (int)$r['pid'], $rows);
        $items = self::byIds($ids);
        if (count($items) < $n) {
            $have = array_merge([(int)$p['id']], $ids);
            $in = implode(',', array_fill(0, count($have), '?'));
            $extra = self::base("p.id NOT IN ($in) AND p.stock > 0 AND p.category_id = ?", 'p.sold_count DESC', $n - count($items), [...$have, $p['category_id']]);
            $items = array_merge($items, $extra);
        }
        return $items;
    }

    // ---- search / listing ----------------------------------------------------------
    public static function likeEscape(string $s): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s) . '%';
    }

    public static function search(array $f, int $perPage = 12): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            foreach (array_slice(preg_split('/\s+/', $q) ?: [], 0, 6) as $tok) {
                $like = self::likeEscape($tok);
                $where[] = "(p.name LIKE ? ESCAPE '!' OR p.brand LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!' OR p.short_desc LIKE ? ESCAPE '!'
                             OR p.description LIKE ? ESCAPE '!' OR c.name LIKE ? ESCAPE '!' OR sc.name LIKE ? ESCAPE '!')";
                array_push($params, $like, $like, $like, $like, $like, $like, $like);
            }
        }
        if (!empty($f['category'])) {
            $c = self::categoryBySlug((string)$f['category']);
            if ($c) {
                if ($c['parent_id']) { $where[] = 'p.subcategory_id = ?'; } else { $where[] = 'p.category_id = ?'; }
                $params[] = $c['id'];
            } else {
                $where[] = '1 = 0';
            }
        }
        if (isset($f['min_price']) && $f['min_price'] !== '' && is_numeric($f['min_price'])) { $where[] = 'p.price >= ?'; $params[] = (float)$f['min_price']; }
        if (isset($f['max_price']) && $f['max_price'] !== '' && is_numeric($f['max_price'])) { $where[] = 'p.price <= ?'; $params[] = (float)$f['max_price']; }
        if (!empty($f['brand']) && is_array($f['brand'])) {
            $brands = array_slice(array_filter(array_map('strval', $f['brand'])), 0, 20);
            if ($brands) { $where[] = 'p.brand IN (' . implode(',', array_fill(0, count($brands), '?')) . ')'; array_push($params, ...$brands); }
        }
        if (!empty($f['rating']) && (int)$f['rating'] > 0) { $where[] = 'p.rating_avg >= ?'; $params[] = min(5, (int)$f['rating']); }
        if (!empty($f['discount']) && (int)$f['discount'] > 0) { $where[] = 'p.discount_pct >= ?'; $params[] = (int)$f['discount']; }
        if (!empty($f['in_stock'])) $where[] = 'p.stock > 0';
        foreach (['color' => 'Color', 'size' => 'Size'] as $key => $opt) {
            if (!empty($f[$key]) && is_string($f[$key])) {
                $where[] = "p.options LIKE ? ESCAPE '!'";
                $params[] = self::likeEscape(json_encode((string)$f[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        $sorts = [
            'relevance'  => 'p.sold_count DESC',
            'popularity' => 'p.sold_count DESC',
            'price_asc'  => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'rating'     => 'p.rating_avg DESC, p.rating_count DESC',
            'newest'     => 'p.created_at DESC, p.id DESC',
            'discount'   => 'p.discount_pct DESC',
        ];
        $order = $sorts[$f['sort'] ?? 'relevance'] ?? $sorts['relevance'];

        $from = 'FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN categories sc ON sc.id = p.subcategory_id WHERE ' . implode(' AND ', $where);
        $total = (int)DB::val("SELECT COUNT(*) $from", $params);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($pages, (int)($f['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;
        $items = DB::all('SELECT ' . self::SELECT . " $from ORDER BY $order, p.id DESC LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset, $params);

        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $perPage, 'from' => $from, 'params' => $params];
    }

    /** Brand / colour / size facet values for the same category+query scope. */
    public static function facets(array $f): array
    {
        $base = ['q' => $f['q'] ?? '', 'category' => $f['category'] ?? ''];
        $r = self::search($base + ['page' => 1], 1);
        $brands = DB::all('SELECT p.brand AS name, COUNT(*) AS n ' . $r['from'] . ' GROUP BY p.brand ORDER BY n DESC, p.brand LIMIT 30', $r['params']);
        $opt = DB::all('SELECT p.options ' . $r['from'] . ' LIMIT 500', $r['params']);
        $colors = $sizes = [];
        foreach ($opt as $o) {
            $j = json_dec($o['options']);
            foreach ($j['Color'] ?? [] as $c) $colors[$c] = ($colors[$c] ?? 0) + 1;
            foreach ($j['Size'] ?? [] as $s) $sizes[$s] = ($sizes[$s] ?? 0) + 1;
        }
        arsort($colors); arsort($sizes);
        return ['brands' => $brands, 'colors' => array_slice(array_keys($colors), 0, 14), 'sizes' => array_slice(array_keys($sizes), 0, 14)];
    }

    public static function suggest(string $q, int $n = 8): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $like = self::likeEscape($q);
        $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $rows = DB::all(
            "SELECT p.name, p.slug, p.brand, p.price, (SELECT url FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order LIMIT 1) AS image
             FROM products p WHERE p.is_active = 1 AND (p.name LIKE ? ESCAPE '!' OR p.brand LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')
             ORDER BY (p.name LIKE ? ESCAPE '!') DESC, p.sold_count DESC LIMIT " . (int)$n,
            [$like, $like, $like, $prefix]
        );
        $cats = DB::all("SELECT name, slug FROM categories WHERE name LIKE ? ESCAPE '!' ORDER BY parent_id IS NULL DESC LIMIT 3", [$like]);
        return ['products' => array_map(fn($r) => [
            'name' => $r['name'], 'brand' => $r['brand'], 'price' => inr($r['price']),
            'url' => url('product/' . $r['slug']), 'image' => img_url($r['image']),
        ], $rows), 'categories' => array_map(fn($c) => ['name' => $c['name'], 'url' => url('category/' . $c['slug'])], $cats)];
    }

    // ---- recently viewed / recommendations -------------------------------------------------
    public static function ownerKey(): string
    {
        $u = current_user();
        if ($u) return 'u:' . $u['id'];
        if (empty($_SESSION['guest_token'])) $_SESSION['guest_token'] = bin2hex(random_bytes(32));
        return 's:' . $_SESSION['guest_token'];
    }

    public static function trackView(int $productId): void
    {
        $owner = self::ownerKey();
        $has = DB::val('SELECT id FROM recently_viewed WHERE owner = ? AND product_id = ?', [$owner, $productId]);
        if ($has) DB::update('recently_viewed', ['viewed_at' => now()], 'id = ?', [$has]);
        else DB::insert('recently_viewed', ['owner' => $owner, 'product_id' => $productId, 'viewed_at' => now()]);
        // keep only the latest 30
        $old = DB::all('SELECT id FROM recently_viewed WHERE owner = ? ORDER BY viewed_at DESC, id DESC LIMIT 100 OFFSET 30', [$owner]);
        foreach ($old as $o) DB::exec('DELETE FROM recently_viewed WHERE id = ?', [$o['id']]);
    }

    public static function recentIds(int $n = 12, int $exclude = 0): array
    {
        $rows = DB::all('SELECT product_id FROM recently_viewed WHERE owner = ? AND product_id <> ? ORDER BY viewed_at DESC, id DESC LIMIT ' . (int)$n, [self::ownerKey(), $exclude]);
        return array_map(fn($r) => (int)$r['product_id'], $rows);
    }

    public static function recent(int $n = 12, int $exclude = 0): array
    {
        return self::byIds(self::recentIds($n, $exclude));
    }

    public static function mergeRecent(int $userId): void
    {
        if (empty($_SESSION['guest_token'])) return;
        $guest = 's:' . $_SESSION['guest_token'];
        $rows = DB::all('SELECT product_id, viewed_at FROM recently_viewed WHERE owner = ?', [$guest]);
        foreach ($rows as $r) {
            if (!DB::val('SELECT id FROM recently_viewed WHERE owner = ? AND product_id = ?', ['u:' . $userId, $r['product_id']])) {
                DB::insert('recently_viewed', ['owner' => 'u:' . $userId, 'product_id' => $r['product_id'], 'viewed_at' => $r['viewed_at']]);
            }
        }
        DB::exec('DELETE FROM recently_viewed WHERE owner = ?', [$guest]);
    }

    /** Recommendations from categories the shopper viewed or bought; falls back to top sellers. */
    public static function recommended(int $n = 12): array
    {
        $cats = [];
        foreach (DB::all('SELECT p.category_id AS c FROM recently_viewed rv JOIN products p ON p.id = rv.product_id WHERE rv.owner = ? ORDER BY rv.viewed_at DESC LIMIT 10', [self::ownerKey()]) as $r) $cats[] = (int)$r['c'];
        if ($u = current_user()) {
            foreach (DB::all('SELECT p.category_id AS c FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 10', [$u['id']]) as $r) $cats[] = (int)$r['c'];
        }
        $cats = array_values(array_unique($cats));
        $seen = self::recentIds(30);
        $items = [];
        if ($cats) {
            $in = implode(',', array_fill(0, count($cats), '?'));
            $ex = $seen ? ' AND p.id NOT IN (' . implode(',', array_fill(0, count($seen), '?')) . ')' : '';
            $items = self::base("p.category_id IN ($in) AND p.stock > 0$ex", 'p.rating_avg DESC, p.sold_count DESC', $n, [...$cats, ...$seen]);
        }
        if (count($items) < $n) {
            $have = array_map(fn($i) => (int)$i['id'], $items);
            $more = self::bestsellers($n + count($have));
            foreach ($more as $m) {
                if (count($items) >= $n) break;
                if (!in_array((int)$m['id'], $have, true)) $items[] = $m;
            }
        }
        return $items;
    }

    public static function latestReviews(int $n = 6): array
    {
        return DB::all(
            'SELECT r.rating, r.title, r.body, r.created_at, u.name AS user_name, p.name AS product_name, p.slug
             FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
             WHERE r.rating >= 4 ORDER BY r.created_at DESC LIMIT ' . (int)$n
        );
    }

    // ---- wishlist / compare -----------------------------------------------------------------
    public static function wishlistIds(): array
    {
        if (self::$wishIds === null) {
            $u = current_user();
            self::$wishIds = $u ? array_map(fn($r) => (int)$r['product_id'], DB::all('SELECT product_id FROM wishlists WHERE user_id = ?', [$u['id']])) : [];
        }
        return self::$wishIds;
    }

    public static function resetWishlistCache(): void { self::$wishIds = null; }

    public static function compareIds(): array
    {
        return array_values(array_map('intval', $_SESSION['compare'] ?? []));
    }

    public static function recalcRating(int $productId): void
    {
        $r = DB::one('SELECT COUNT(*) AS c, AVG(rating) AS a FROM reviews WHERE product_id = ?', [$productId]);
        DB::update('products', ['rating_avg' => round((float)($r['a'] ?? 0), 2), 'rating_count' => (int)($r['c'] ?? 0)], 'id = ?', [$productId]);
    }
}
