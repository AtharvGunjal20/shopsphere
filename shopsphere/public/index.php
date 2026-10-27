<?php
declare(strict_types=1);

// PHP built-in server: let it serve real static files itself.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path !== '/' && is_file(__DIR__ . $path)) return false;
}

define('ROOT', dirname(__DIR__));
require ROOT . '/app/bootstrap.php';
(require ROOT . '/app/routes.php')->dispatch();
