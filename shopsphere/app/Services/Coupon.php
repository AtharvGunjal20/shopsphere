<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class Coupon
{
    /**
     * Validate a coupon against a subtotal (server-side only).
     * @return array{ok:bool,discount:float,message:string,coupon:?array}
     */
    public static function evaluate(string $code, float $subtotal): array
    {
        $code = strtoupper(trim($code));
        $c = $code === '' ? null : DB::one('SELECT * FROM coupons WHERE code = ?', [$code]);
        $fail = fn(string $m) => ['ok' => false, 'discount' => 0.0, 'message' => $m, 'coupon' => null];

        if (!$c || !(int)$c['is_active']) return $fail('This coupon code is not valid.');
        $now = now();
        if ($c['starts_at'] && $c['starts_at'] > $now) return $fail('This coupon is not active yet.');
        if ($c['expires_at'] && $c['expires_at'] < $now) return $fail('This coupon has expired.');
        if ($c['usage_limit'] !== null && (int)$c['used_count'] >= (int)$c['usage_limit']) return $fail('This coupon has reached its usage limit.');
        if ($subtotal < (float)$c['min_order']) return $fail('Add items worth ' . inr((float)$c['min_order'] - $subtotal) . ' more to use this coupon (minimum order ' . inr($c['min_order']) . ').');

        $disc = $c['type'] === 'percent' ? $subtotal * (float)$c['value'] / 100 : (float)$c['value'];
        if ($c['max_discount'] !== null) $disc = min($disc, (float)$c['max_discount']);
        $disc = round(min($disc, $subtotal), 2);
        return ['ok' => true, 'discount' => $disc, 'message' => $c['code'] . ' applied - you save ' . inr($disc), 'coupon' => $c];
    }
}
