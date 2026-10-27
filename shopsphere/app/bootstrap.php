<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) return;
    $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

require ROOT . '/app/Core/helpers.php';

$GLOBALS['__cfg'] = require ROOT . '/config/config.php';
date_default_timezone_set('Asia/Kolkata');

// ---- error handling -------------------------------------------------------
$isProd = cfg('app.env') === 'production';
ini_set('display_errors', $isProd ? '0' : '1');
error_reporting(E_ALL);
set_exception_handler(function (Throwable $e) use ($isProd): void {
    log_error($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    if (wants_json()) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $isProd ? 'Something went wrong.' : $e->getMessage()]);
    } else {
        echo $isProd
            ? '<h1>Something went wrong</h1><p>Please try again later.</p>'
            : '<pre>' . e($e->getMessage() . "\n\n" . $e->getTraceAsString()) . '</pre>';
    }
    exit;
});

// ---- security headers -----------------------------------------------------
if (PHP_SAPI === 'cli') return;
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://checkout.razorpay.com; "
    . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
    . "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; "
    . "img-src 'self' data: https:; "
    . "frame-src https://api.razorpay.com https://checkout.razorpay.com; "
    . "connect-src 'self' https://lumberjack.razorpay.com https://api.razorpay.com; "
    . "form-action 'self'; base-uri 'self'");

// ---- session --------------------------------------------------------------
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('shopsphere_sid');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

// Session hijacking mitigation: bind to user agent + idle timeout.
$fp = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
$idle = (int)cfg('app.session_idle_seconds', 7200);
if (isset($_SESSION['_fp']) && ($_SESSION['_fp'] !== $fp || (time() - ($_SESSION['_last'] ?? time())) > $idle)) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['_fp'] = $fp;
$_SESSION['_last'] = time();
