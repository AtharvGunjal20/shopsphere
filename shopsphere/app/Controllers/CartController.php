<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Services\CartService;
use App\Services\Catalog;
use App\Services\Coupon;
use App\Services\Delivery;

final class CartController
{
    // ---- cart page & API ---------------------------------------------------------------
    public function page(): void
    {
        foreach (CartService::reconcile() as $m) flash('warning', $m);
        $t = CartService::totals();
        render('cart', ['title' => 'Shopping Cart - ' . cfg('app.name'), 't' => $t, 'saved' => $this->savedForLater(),
            'coupons' => DB::all('SELECT code, description FROM coupons WHERE is_active = 1 ORDER BY id LIMIT 4')]);
    }

    private function savedForLater(): array
    {
        return Catalog::recommended(4);
    }

    public function add(): void
    {
        $r = CartService::add((int)input('product_id', 0), (int)input('qty', 1), input('options', []));
        if (!$r['ok']) json_out($r, 422);
        json_out($r + ['summary' => CartService::summary()]);
    }

    public function update(): void
    {
        $r = CartService::setQty((int)input('item_id', 0), (int)input('qty', 0));
        if (!$r['ok']) json_out($r, 422);
        json_out($r + ['summary' => CartService::summary()]);
    }

    public function remove(): void
    {
        CartService::remove((int)input('item_id', 0));
        json_out(['ok' => true, 'summary' => CartService::summary()]);
    }

    public function summary(): void
    {
        json_out(['ok' => true, 'summary' => CartService::summary()]);
    }

    public function coupon(): void
    {
        $code = strtoupper(str_in('code', 30));
        if (input('remove')) {
            unset($_SESSION['coupon']);
            json_out(['ok' => true, 'message' => 'Coupon removed.', 'summary' => CartService::summary()]);
        }
        $t = CartService::totals(['coupon' => '']);
        if (!$t['lines']) json_out(['ok' => false, 'error' => 'Your cart is empty.'], 422);
        $ev = Coupon::evaluate($code, $t['subtotal']);
        if (!$ev['ok']) json_out(['ok' => false, 'error' => $ev['message']], 422);
        $_SESSION['coupon'] = $ev['coupon']['code'];
        json_out(['ok' => true, 'message' => $ev['message'], 'summary' => CartService::summary()]);
    }

    // ---- wishlist --------------------------------------------------------------------------
    public function wishlistPage(): void
    {
        $u = require_login();
        $items = DB::all('SELECT ' . Catalog::SELECT . ' FROM wishlists w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? AND p.is_active = 1 ORDER BY w.created_at DESC', [$u['id']]);
        render('wishlist', ['title' => 'My Wishlist - ' . cfg('app.name'), 'items' => $items]);
    }

    public function wishlistToggle(): void
    {
        $u = require_login();
        $pid = (int)input('product_id', 0);
        if (!DB::val('SELECT id FROM products WHERE id = ? AND is_active = 1', [$pid])) json_out(['ok' => false, 'error' => 'Product not found.'], 404);
        $id = DB::val('SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?', [$u['id'], $pid]);
        if ($id) { DB::exec('DELETE FROM wishlists WHERE id = ?', [$id]); $on = false; }
        else { DB::insert('wishlists', ['user_id' => $u['id'], 'product_id' => $pid, 'created_at' => now()]); $on = true; }
        Catalog::resetWishlistCache();
        json_out(['ok' => true, 'wished' => $on, 'count' => count(Catalog::wishlistIds()), 'message' => $on ? 'Saved to wishlist' : 'Removed from wishlist']);
    }

    public function wishlistMove(): void
    {
        $u = require_login();
        $pid = (int)input('product_id', 0);
        $p = DB::one('SELECT id, options FROM products WHERE id = ?', [$pid]);
        if (!$p || !DB::val('SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?', [$u['id'], $pid])) json_out(['ok' => false, 'error' => 'Not in your wishlist.'], 404);
        $opts = json_dec($p['options']);
        $chosen = [];
        foreach ($opts as $g => $vals) if ($vals) $chosen[$g] = $vals[0];   // default to first variant; adjustable in cart's product page
        $r = CartService::add($pid, 1, $chosen);
        if (!$r['ok']) json_out($r, 422);
        DB::exec('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?', [$u['id'], $pid]);
        Catalog::resetWishlistCache();
        json_out($r + ['moved' => true, 'message' => 'Moved to cart' . ($opts ? ' (first available options selected)' : '')]);
    }

    // ---- compare --------------------------------------------------------------------------------
    public function compareToggle(): void
    {
        $pid = (int)input('product_id', 0);
        if (!DB::val('SELECT id FROM products WHERE id = ? AND is_active = 1', [$pid])) json_out(['ok' => false, 'error' => 'Product not found.'], 404);
        $list = Catalog::compareIds();
        if (in_array($pid, $list, true)) {
            $list = array_values(array_diff($list, [$pid]));
            $on = false;
        } else {
            if (count($list) >= 4) json_out(['ok' => false, 'error' => 'You can compare up to 4 products. Remove one first.'], 422);
            $list[] = $pid; $on = true;
        }
        $_SESSION['compare'] = $list;
        json_out(['ok' => true, 'comparing' => $on, 'count' => count($list), 'message' => $on ? 'Added to compare' : 'Removed from compare']);
    }

    public function comparePage(): void
    {
        $ids = Catalog::compareIds();
        $rows = [];
        foreach ($ids as $id) {
            $p = DB::one('SELECT p.*, c.name AS category_name, (SELECT url FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order LIMIT 1) AS image FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.is_active = 1', [$id]);
            if ($p) $rows[] = $p;
        }
        $specKeys = [];
        foreach ($rows as $r) foreach (array_keys(json_dec($r['specs'])) as $k) $specKeys[$k] = true;
        render('compare', ['title' => 'Compare Products - ' . cfg('app.name'), 'rows' => $rows, 'specKeys' => array_keys($specKeys)]);
    }

    public function compareRemove(): void
    {
        $pid = (int)input('product_id', 0);
        $_SESSION['compare'] = array_values(array_diff(Catalog::compareIds(), [$pid]));
        if (wants_json()) json_out(['ok' => true]);
        redirect('/compare');
    }
}
