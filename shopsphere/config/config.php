<?php
/**
 * ShopSphere India - configuration.
 *
 * Do NOT edit secrets in this file. Copy config/config.local.example.php to
 * config/config.local.php (git-ignored) and override values there, or use
 * environment variables (DB_DSN, DB_USER, DB_PASS, RAZORPAY_KEY_ID ...).
 */
$cfg = [
    'app' => [
        'name'        => 'ShopSphere India',
        'env'         => getenv('APP_ENV') ?: 'development',   // development | production
        'url'         => getenv('APP_URL') ?: '',               // e.g. https://shop.example.com (used in emails)
        'demo_notice' => true,                                  // shows "demo prices" notices
        'session_idle_seconds' => 7200,
        'require_email_verification' => false,                  // true = block checkout until email verified
        'allow_guest_checkout' => true,
        'guest_checkout_max'   => 25000,                        // guest orders above this need an account
    ],
    'db' => [
        'dsn'  => getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=shopsphere;charset=utf8mb4',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],
    'razorpay' => [
        'key_id'         => getenv('RAZORPAY_KEY_ID') ?: '',       // rzp_test_xxxxx
        'key_secret'     => getenv('RAZORPAY_KEY_SECRET') ?: '',
        'webhook_secret' => getenv('RAZORPAY_WEBHOOK_SECRET') ?: '',
    ],
    'mail' => [
        'enabled'   => false,           // false = emails are written to storage/logs/mail.log
        'host'      => getenv('SMTP_HOST') ?: 'smtp.example.com',
        'port'      => (int)(getenv('SMTP_PORT') ?: 587),
        'secure'    => 'tls',           // tls (STARTTLS) | ssl | none
        'user'      => getenv('SMTP_USER') ?: '',
        'pass'      => getenv('SMTP_PASS') ?: '',
        'from'      => getenv('SMTP_FROM') ?: 'no-reply@shopsphere.example',
        'from_name' => 'ShopSphere India',
    ],
    'shop' => [
        'free_shipping_min'   => 499,     // standard shipping is free at/above this subtotal
        'standard_shipping'   => 49,
        'express_shipping'    => 99,
        'cod_fee'             => 40,
        'cod_max_order'       => 50000,
        'cancellable_status'  => ['pending_payment', 'placed', 'processing'],
        'return_window_days'  => 7,
        'pending_payment_minutes' => 30,  // unpaid online orders are expired and restocked after this
        'metro_pin_prefixes'  => ['110', '400', '560', '600', '700', '500', '411', '380', '122'],
        'seller' => [
            'name'    => 'ShopSphere Retail Pvt. Ltd. (demo)',
            'address' => '12, Demo Business Park, Pune, Maharashtra 411001',
            'gstin'   => '27AAAAA0000A1Z5 (sample)',
            'email'   => 'support@shopsphere.example',
            'phone'   => '1800-000-0000',
        ],
    ],
    'locales' => ['en' => 'English'],  // add 'hi' => 'हिन्दी' etc. and files in app/lang/<code>.php
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $cfg = array_replace_recursive($cfg, require $local);
}
return $cfg;
