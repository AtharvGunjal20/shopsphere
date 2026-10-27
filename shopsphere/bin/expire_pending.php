<?php
/** Cron: cancel unpaid online orders older than shop.pending_payment_minutes and restore stock.
 *  Run every 10 minutes:  php /path/to/shopsphere/bin/expire_pending.php */
define('ROOT', dirname(__DIR__));
require ROOT . '/app/bootstrap.php';
echo 'Expired orders: ' . \App\Services\OrderService::expirePending() . "\n";
