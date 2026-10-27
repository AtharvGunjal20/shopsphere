<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Server-side cart. The browser only ever sends product ids, quantities and chosen options;
 * every price, discount, tax and total is recalculated from the database here.
 */
final class CartService
{
    public const MAX_QTY_PER_LINE = 10;

    // ---- cart identity ---------------------------------------------------------
    public static function cartId(bool $create = false): ?int
    {
        $u = current_user();
        if ($u) {
            $id = DB::val('SELECT id FROM carts WHERE user_id = ?', [$u['id']]);
            if (!$id && $create) $id = DB::insert('carts', ['user_id' => $u['id'], 'token' => null, 'created_at' => now(), 'updated_at' => now()]);
            return $id ? (int)$id : null;
        }
        if (empty($_SESSION['guest_token'])) {
            if (!$create) return null;
            $_SESSION['guest_token'] = bin2hex(random_bytes(32));
        }
        $tok = $_SESSION['guest_token'];
        $id = DB::val('SELECT id FROM carts WHERE token = ?', [$tok]);
        if (!$id && $create) $id = DB::insert('carts', ['user_id' => null, 'token' => $tok, 'created_at' => now(), 'updated_at' => now()]);
        return $id ? (int)$id : null;
    }

    public static function mergeGuestCart(int $userId): void
    {
        if (empty($_SESSION['guest_token'])) return;
        $guest = DB::one('SELECT id FROM carts WHERE token = ?', [$_SESSION['guest_token']]);
        if (!$guest) return;
        DB::tx(function () use ($guest, $userId) {
            $uc = DB::val('SELECT id FROM carts WHERE user_id = ?', [$userId]);
            if (!$uc) {
                // Simply adopt the guest cart.
                DB::update('carts', ['user_id' => $userId, 'token' => null, 'updated_at' => now()], 'id = ?', [$guest['id']]);
                return;
            }
            foreach (DB::all('SELECT product_id, qty, options, options_hash FROM cart_items WHERE cart_id = ?', [$guest['id']]) as $it) {
                $ex = DB::one('SELECT id, qty FROM cart_items WHERE cart_id = ? AND product_id = ? AND options_hash = ?', [$uc, $it['product_id'], $it['options_hash']]);
                if ($ex) DB::update('cart_items', ['qty' => min(self::MAX_QTY_PER_LINE, (int)$ex['qty'] + (int)$it['qty'])], 'id = ?', [$ex['id']]);
                else DB::insert('cart_items', ['cart_id' => $uc, 'product_id' => $it['product_id'], 'qty' => $it['qty'], 'options' => $it['options'], 'options_hash' => $it['options_hash'], 'created_at' => now()]);
            }
            DB::exec('DELETE FROM carts WHERE id = ?', [$guest['id']]);
        });
        // The session keeps the same guest_token for recently viewed merge; cart row is gone/adopted.
    }

    public static function count(): int
    {
        $id = self::cartId();
        return $id ? (int)DB::val('SELECT COALESCE(SUM(qty), 0) FROM cart_items WHERE cart_id = ?', [$id]) : 0;
    }

    // ---- options ----------------------------------------------------------------------
    /** @return array{0:?array,1:?string} normalised options or error */
    public static function normaliseOptions(array $product, mixed $chosen): array
    {
        $allowed = json_dec($product['options'] ?? null);
        $chosen = is_array($chosen) ? $chosen : [];
        $out = [];
        foreach ($allowed as $group => $values) {
            if (!$values) continue;
            $pick = $chosen[$group] ?? null;
            if (!is_string($pick) || !in_array($pick, $values, true)) return [null, 'Please select a ' . strtolower((string)$group) . '.'];
            $out[$group] = $pick;
        }
        ksort($out);
        return [$out, null];
    }

    private static function hash(array $opts): string
    {
        return sha1(json_encode($opts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // ---- mutations ----------------------------------------------------------------------
    public static function add(int $productId, int $qty, mixed $chosen): array
    {
        $qty = max(1, min(self::MAX_QTY_PER_LINE, $qty));
        $p = DB::one('SELECT id, name, stock, is_active, options FROM products WHERE id = ?', [$productId]);
        if (!$p || !(int)$p['is_active']) return ['ok' => false, 'error' => 'This product is no longer available.'];
        if ((int)$p['stock'] <= 0) return ['ok' => false, 'error' => 'Sorry, this product is out of stock.'];
        [$opts, $err] = self::normaliseOptions($p, $chosen);
        if ($err) return ['ok' => false, 'error' => $err, 'needs_options' => true];

        $cartId = self::cartId(true);
        $hash = self::hash($opts);
        $ex = DB::one('SELECT id, qty FROM cart_items WHERE cart_id = ? AND product_id = ? AND options_hash = ?', [$cartId, $productId, $hash]);
        $newQty = ($ex ? (int)$ex['qty'] : 0) + $qty;
        $limit = min((int)$p['stock'], self::MAX_QTY_PER_LINE);
        $msg = null;
        if ($newQty > $limit) { $newQty = $limit; $msg = 'Only ' . $limit . ' unit(s) can be added for this item.'; }
        if ($ex) DB::update('cart_items', ['qty' => $newQty], 'id = ?', [$ex['id']]);
        else DB::insert('cart_items', ['cart_id' => $cartId, 'product_id' => $productId, 'qty' => $newQty, 'options' => json_encode($opts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'options_hash' => $hash, 'created_at' => now()]);
        DB::update('carts', ['updated_at' => now()], 'id = ?', [$cartId]);
        return ['ok' => true, 'message' => $msg ?? 'Added to cart', 'count' => self::count()];
    }

    public static function setQty(int $itemId, int $qty): array
    {
        $cartId = self::cartId();
        if (!$cartId) return ['ok' => false, 'error' => 'Cart not found.'];
        $it = DB::one('SELECT ci.id, p.stock FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.id = ? AND ci.cart_id = ?', [$itemId, $cartId]);
        if (!$it) return ['ok' => false, 'error' => 'Item not found.'];
        if ($qty <= 0) { DB::exec('DELETE FROM cart_items WHERE id = ?', [$itemId]); return ['ok' => true]; }
        $limit = min((int)$it['stock'], self::MAX_QTY_PER_LINE);
        $msg = null;
        if ($qty > $limit) { $qty = $limit; $msg = 'Only ' . $limit . ' unit(s) available.'; }
        if ($qty <= 0) { DB::exec('DELETE FROM cart_items WHERE id = ?', [$itemId]); return ['ok' => true, 'message' => 'Item is out of stock and was removed.']; }
        DB::update('cart_items', ['qty' => $qty], 'id = ?', [$itemId]);
        return ['ok' => true, 'message' => $msg];
    }

    public static function remove(int $itemId): void
    {
        $cartId = self::cartId();
        if ($cartId) DB::exec('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cartId]);
    }

    public static function clear(?int $cartId = null): void
    {
        $cartId ??= self::cartId();
        if ($cartId) DB::exec('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
        unset($_SESSION['coupon']);
    }

    /** Clamp quantities to stock and drop unavailable lines. Returns human messages. */
    public static function reconcile(): array
    {
        $cartId = self::cartId();
        if (!$cartId) return [];
        $msgs = [];
        $rows = DB::all('SELECT ci.id, ci.qty, p.name, p.stock, p.is_active FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?', [$cartId]);
        foreach ($rows as $r) {
            if (!(int)$r['is_active'] || (int)$r['stock'] <= 0) {
                DB::exec('DELETE FROM cart_items WHERE id = ?', [$r['id']]);
                $msgs[] = '"' . $r['name'] . '" is no longer available and was removed from your cart.';
            } elseif ((int)$r['qty'] > (int)$r['stock']) {
                DB::update('cart_items', ['qty' => (int)$r['stock']], 'id = ?', [$r['id']]);
                $msgs[] = 'Quantity of "' . $r['name'] . '" was reduced to ' . (int)$r['stock'] . ' (available stock).';
            }
        }
        return $msgs;
    }

    // ---- totals (single source of truth) ------------------------------------------------------
    /**
     * @param array $ctx shipping_method, payment_method, coupon
     * @param bool  $lock lock product rows (use inside a transaction when placing an order)
     */
    public static function totals(array $ctx = [], bool $lock = false, ?int $cartId = null): array
    {
        $cartId ??= self::cartId();
        $ship = $ctx['shipping_method'] ?? 'standard';
        if (!isset(Delivery::methods()[$ship])) $ship = 'standard';
        $pay = $ctx['payment_method'] ?? '';
        $couponCode = $ctx['coupon'] ?? ($_SESSION['coupon'] ?? '');

        $rows = $cartId ? DB::all(
            'SELECT ci.id AS item_id, ci.qty, ci.options AS chosen, p.id AS product_id, p.sku, p.slug, p.name, p.brand, p.price, p.mrp, p.stock, p.is_active,
                    p.gst_rate, (SELECT url FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image
             FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ? ORDER BY ci.id' . ($lock ? DB::forUpdate() : ''),
            [$cartId]
        ) : [];

        $lines = []; $issues = []; $mrpTotal = 0.0; $subtotal = 0.0; $count = 0;
        foreach ($rows as $r) {
            $qty = (int)$r['qty'];
            $price = round((float)$r['price'], 2);
            $mrp = round((float)$r['mrp'], 2);
            if (!(int)$r['is_active'] || (int)$r['stock'] <= 0) $issues[] = '"' . $r['name'] . '" is out of stock.';
            elseif ($qty > (int)$r['stock']) $issues[] = 'Only ' . (int)$r['stock'] . ' unit(s) of "' . $r['name'] . '" are available.';
            $line = round($price * $qty, 2);
            $lines[] = $r + ['options_arr' => json_dec($r['chosen']), 'unit' => $price, 'unit_mrp' => $mrp, 'line' => $line, 'line_mrp' => round($mrp * $qty, 2), 'discount_pct' => discount_pct($mrp, $price)];
            $mrpTotal += $mrp * $qty;
            $subtotal += $line;
            $count += $qty;
        }
        $mrpTotal = round($mrpTotal, 2);
        $subtotal = round($subtotal, 2);

        $coupon = null; $couponDiscount = 0.0; $couponMsg = null;
        if ($couponCode !== '' && $lines) {
            $ev = Coupon::evaluate((string)$couponCode, $subtotal);
            if ($ev['ok']) { $coupon = $ev['coupon']; $couponDiscount = $ev['discount']; }
            else { $couponMsg = $ev['message']; if (!$lock) unset($_SESSION['coupon']); }
        }

        // Share coupon across lines to compute the GST that is already included in the prices.
        $tax = 0.0; $remaining = $couponDiscount;
        foreach ($lines as $i => &$l) {
            $share = ($i === count($lines) - 1) ? $remaining : round($couponDiscount * ($l['line'] / max($subtotal, 0.01)), 2);
            $remaining = round($remaining - $share, 2);
            $net = round($l['line'] - $share, 2);
            $rate = (float)$l['gst_rate'];
            $l['coupon_share'] = $share;
            $l['line_total'] = $net;
            $l['tax'] = round($net * $rate / (100 + $rate), 2);
            $tax += $l['tax'];
        }
        unset($l);

        $shipping = $lines ? Delivery::fee($ship, round($subtotal - $couponDiscount, 2)) : 0.0;
        $codFee = ($pay === 'cod' && $lines) ? (float)cfg('shop.cod_fee') : 0.0;
        $total = round($subtotal - $couponDiscount + $shipping + $codFee, 2);

        return [
            'lines' => $lines, 'count' => $count, 'mrp_total' => $mrpTotal, 'subtotal' => $subtotal,
            'product_discount' => round($mrpTotal - $subtotal, 2), 'coupon' => $coupon, 'coupon_code' => $coupon['code'] ?? null,
            'coupon_discount' => $couponDiscount, 'coupon_message' => $couponMsg,
            'shipping' => $shipping, 'shipping_method' => $ship, 'cod_fee' => $codFee, 'tax' => round($tax, 2),
            'total' => $total, 'total_savings' => round($mrpTotal - $subtotal + $couponDiscount, 2),
            'issues' => $issues, 'free_shipping_gap' => max(0, round((float)cfg('shop.free_shipping_min') - $subtotal, 2)),
        ];
    }

    /** Small JSON-safe summary for AJAX responses. */
    public static function summary(): array
    {
        $t = self::totals();
        return [
            'count' => $t['count'], 'subtotal' => inr($t['subtotal']), 'coupon_discount' => inr($t['coupon_discount']),
            'shipping' => $t['shipping'] > 0 ? inr($t['shipping']) : 'FREE', 'total' => inr($t['total']),
            'savings' => inr($t['total_savings']), 'tax' => inr($t['tax']),
        ];
    }
}
