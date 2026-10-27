<?php
declare(strict_types=1);

namespace App\Services;

/** Configured (not courier-live) delivery rules. Estimates are indicative only. */
final class Delivery
{
    public static function validPin(string $pin): bool
    {
        return (bool)preg_match('/^[1-9][0-9]{5}$/', $pin);
    }

    public static function isMetro(string $pin): bool
    {
        return in_array(substr($pin, 0, 3), cfg('shop.metro_pin_prefixes', []), true);
    }

    public static function methods(): array
    {
        return [
            'standard' => ['label' => 'Standard delivery', 'fee' => (float)cfg('shop.standard_shipping')],
            'express'  => ['label' => 'Express delivery',  'fee' => (float)cfg('shop.express_shipping')],
        ];
    }

    public static function fee(string $method, float $subtotal): float
    {
        if ($method === 'express') return (float)cfg('shop.express_shipping');
        return $subtotal >= (float)cfg('shop.free_shipping_min') ? 0.0 : (float)cfg('shop.standard_shipping');
    }

    /** @return array{min:int,max:int} business days */
    public static function days(string $pin, string $method): array
    {
        $metro = self::isMetro($pin);
        if ($method === 'express') return $metro ? ['min' => 1, 'max' => 2] : ['min' => 2, 'max' => 3];
        return $metro ? ['min' => 2, 'max' => 4] : ['min' => 4, 'max' => 7];
    }

    public static function addDays(int $days, ?\DateTimeImmutable $from = null): \DateTimeImmutable
    {
        $d = $from ?? new \DateTimeImmutable('today');
        while ($days > 0) {
            $d = $d->modify('+1 day');
            if ($d->format('N') < 7) $days--;   // skip Sundays
        }
        return $d;
    }

    public static function estimate(string $pin, string $method = 'standard'): ?array
    {
        if (!self::validPin($pin)) return null;
        $d = self::days($pin, $method);
        $from = self::addDays($d['min']);
        $to = self::addDays($d['max']);
        return [
            'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
            'text' => $from->format('D, j M') . ' - ' . $to->format('D, j M'),
            'metro' => self::isMetro($pin),
        ];
    }

    public static function currentPin(): string
    {
        return (string)($_SESSION['pin'] ?? '');
    }
}
