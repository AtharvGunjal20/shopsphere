<?php
/**
 * Replace placeholder artwork with real product photos you are licensed to use.
 *
 *   php bin/import_images.php images.csv
 *
 * CSV (no header), one product per line:  SKU,https://image1.jpg,https://image2.jpg,...
 * Only https:// URLs are accepted. Use official manufacturer press/product feeds, an authorised
 * image API, or your own licensed photos - do not hotlink retailer images without permission.
 */
define('ROOT', dirname(__DIR__));
require ROOT . '/app/bootstrap.php';
use App\Core\DB;

$file = $argv[1] ?? null;
if (!$file || !is_file($file)) exit("Usage: php bin/import_images.php images.csv\n");
$h = fopen($file, 'r'); $n = 0;
while (($row = fgetcsv($h)) !== false) {
    $sku = strtoupper(trim($row[0] ?? ''));
    $urls = array_values(array_filter(array_map('trim', array_slice($row, 1)), fn($u) => preg_match('#^https://[^\s"\'<>]+$#', $u)));
    $pid = DB::val('SELECT id FROM products WHERE sku = ?', [$sku]);
    if (!$pid || !$urls) { echo "Skipped: $sku\n"; continue; }
    DB::tx(function () use ($pid, $urls) {
        DB::exec('DELETE FROM product_images WHERE product_id = ?', [$pid]);
        foreach ($urls as $i => $u) DB::insert('product_images', ['product_id' => $pid, 'url' => $u, 'alt' => null, 'sort_order' => $i + 1]);
    });
    $n++; echo "Updated $sku (" . count($urls) . " images)\n";
}
echo "Done: $n products updated.\n";
