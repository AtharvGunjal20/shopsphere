<?php
// Copy to config/config.local.php and edit. This file is git-ignored.
return [
    'db' => [
        'dsn'  => 'mysql:host=127.0.0.1;port=3306;dbname=shopsphere;charset=utf8mb4',
        'user' => 'root',
        'pass' => '',
    ],
    'razorpay' => [
        'key_id'         => 'rzp_test_xxxxxxxxxxxx',
        'key_secret'     => 'your_test_key_secret',
        'webhook_secret' => 'your_webhook_secret',
    ],
    'mail' => [
        'enabled' => false,   // set true after filling SMTP details
        'host' => 'smtp.gmail.com', 'port' => 587, 'secure' => 'tls',
        'user' => 'you@example.com', 'pass' => 'app-password',
        'from' => 'you@example.com', 'from_name' => 'ShopSphere India',
    ],
    'app' => [
        'env' => 'development',
        'url' => 'http://localhost/shopsphere/public',
        'require_email_verification' => false,
    ],
];
