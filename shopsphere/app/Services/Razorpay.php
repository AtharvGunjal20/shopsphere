<?php
declare(strict_types=1);

namespace App\Services;

/** Thin Razorpay REST client + signature helpers (test mode keys: rzp_test_...). */
final class Razorpay
{
    private const API = 'https://api.razorpay.com/v1';

    public static function enabled(): bool
    {
        return cfg('razorpay.key_id') !== '' && cfg('razorpay.key_secret') !== '';
    }

    public static function keyId(): string
    {
        return (string)cfg('razorpay.key_id');
    }

    /** @return array{ok:bool,data:array,error:?string} */
    public static function api(string $method, string $path, ?array $body = null): array
    {
        if (!self::enabled()) return ['ok' => false, 'data' => [], 'error' => 'Razorpay is not configured.'];
        $ch = curl_init(self::API . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => self::keyId() . ':' . cfg('razorpay.key_secret'),
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) return ['ok' => false, 'data' => [], 'error' => 'Network error: ' . $err];
        $data = json_decode((string)$res, true) ?: [];
        if ($code >= 400) return ['ok' => false, 'data' => $data, 'error' => $data['error']['description'] ?? ('HTTP ' . $code)];
        return ['ok' => true, 'data' => $data, 'error' => null];
    }

    public static function createOrder(float $amountInr, string $receipt, array $notes = []): array
    {
        return self::api('POST', '/orders', [
            'amount' => (int)round($amountInr * 100), 'currency' => 'INR', 'receipt' => $receipt, 'notes' => $notes,
        ]);
    }

    public static function fetchPayment(string $paymentId): array
    {
        return self::api('GET', '/payments/' . rawurlencode($paymentId));
    }

    public static function capture(string $paymentId, float $amountInr): array
    {
        return self::api('POST', '/payments/' . rawurlencode($paymentId) . '/capture', ['amount' => (int)round($amountInr * 100), 'currency' => 'INR']);
    }

    public static function refund(string $paymentId, float $amountInr, array $notes = []): array
    {
        return self::api('POST', '/payments/' . rawurlencode($paymentId) . '/refund', ['amount' => (int)round($amountInr * 100), 'notes' => $notes]);
    }

    /** Checkout callback signature: HMAC_SHA256(order_id|payment_id, key_secret). */
    public static function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = (string)cfg('razorpay.key_secret');
        if ($secret === '' || $signature === '') return false;
        return hash_equals(hash_hmac('sha256', $orderId . '|' . $paymentId, $secret), $signature);
    }

    /** Webhook signature: HMAC_SHA256(raw_body, webhook_secret). */
    public static function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = (string)cfg('razorpay.webhook_secret');
        if ($secret === '' || $signature === '') return false;
        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
