<?php
/**
 * Demo data seeder:  php database/seed.php [--fresh]
 *
 * - Brands are fictional and prices are ILLUSTRATIVE demo prices, not live market prices.
 * - Product images are stored as URLs in `product_images`. The seeder points them at the built-in
 *   SVG placeholder route (img/ph/<SKU>/<n>). Replace them with licensed/official photos via the
 *   admin product editor or `php bin/import_images.php images.csv`.
 */
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
require ROOT . '/app/bootstrap.php';

use App\Core\DB;

$fresh = in_array('--fresh', $argv ?? [], true);
if ($fresh) {
    foreach (['order_events','returns','refunds','order_items','orders','webhook_events','reviews','questions','cart_items','carts','wishlists','recently_viewed','product_images','products','categories','coupons','addresses','password_resets','email_verifications','login_attempts','newsletter_subscribers','users'] as $t) {
        DB::exec("DELETE FROM $t");
    }
}
if ((int)DB::val('SELECT COUNT(*) FROM products') > 0) {
    exit("Products already exist. Run with --fresh to wipe and reseed.\n");
}

mt_srand(2026);
$now = now();

// ---------------------------------------------------------------- users
$users = [
    ['Admin User',    'admin@shopsphere.test', '9800000001', 'Admin@12345', 'admin'],
    ['Store Staff',   'staff@shopsphere.test', '9800000002', 'Staff@12345', 'staff'],
    ['Demo Customer', 'demo@shopsphere.test',  '9800000003', 'Demo@12345',  'customer'],
    ['Aarav Mehta',   'aarav@shopsphere.test', '9800000004', 'Demo@12345',  'customer'],
    ['Priya Nair',    'priya@shopsphere.test', '9800000005', 'Demo@12345',  'customer'],
    ['Rohan Iyer',    'rohan@shopsphere.test', '9800000006', 'Demo@12345',  'customer'],
    ['Sneha Kulkarni','sneha@shopsphere.test', '9800000007', 'Demo@12345',  'customer'],
    ['Imran Sheikh',  'imran@shopsphere.test', '9800000008', 'Demo@12345',  'customer'],
];
$userIds = [];
foreach ($users as [$n, $em, $ph, $pw, $role]) {
    $userIds[] = DB::insert('users', [
        'name' => $n, 'email' => $em, 'phone' => $ph, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
        'role' => $role, 'status' => 'active', 'email_verified_at' => $now, 'created_at' => $now,
    ]);
}
$demoUserId = $userIds[2];
DB::insert('addresses', [
    'user_id' => $demoUserId, 'label' => 'Home', 'full_name' => 'Demo Customer', 'phone' => '9800000003',
    'line1' => '24, Lake View Apartments, FC Road', 'line2' => 'Shivajinagar', 'landmark' => 'Near Garware Bridge',
    'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411004', 'is_default' => 1, 'created_at' => $now,
]);

// ---------------------------------------------------------------- coupons
$coupons = [
    ['WELCOME10', '10% off your order (max ₹500)', 'percent', 10, 499, 500, null, null],
    ['FLAT200',   '₹200 off orders above ₹1,999',  'flat',    200, 1999, null, null, null],
    ['FESTIVE15', '15% off orders above ₹2,999 (max ₹1,500)', 'percent', 15, 2999, 1500, null, null],
    ['FREESHIP',  '₹49 off (covers standard shipping)', 'flat', 49, 199, null, null, null],
];
foreach ($coupons as [$code, $desc, $type, $val, $min, $max, $from, $to]) {
    DB::insert('coupons', [
        'code' => $code, 'description' => $desc, 'type' => $type, 'value' => $val, 'min_order' => $min,
        'max_discount' => $max, 'starts_at' => $from, 'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
        'usage_limit' => null, 'used_count' => 0, 'is_active' => 1,
    ]);
}

// ---------------------------------------------------------------- catalogue
// category slug => [name, icon, gst%, [subcategories], [products]]
// product: [sub, brand, name, mrp, price, specs, options, warranty]
$catalog = [
    'mobiles' => ['Smartphones & Accessories', 'bi-phone', 18, ['Smartphones', 'Phone Accessories'], [
        ['Smartphones', 'Nexora', 'Nexora N5 5G (8GB RAM, 128GB)', 29999, 24999, ['Display' => '6.6" AMOLED, 120 Hz', 'RAM' => '8 GB', 'Storage' => '128 GB', 'Camera' => '50 MP + 8 MP', 'Battery' => '5000 mAh, 33W charging', 'OS' => 'Android 15'], ['Color' => ['Midnight Black', 'Ocean Blue']], '1 year manufacturer warranty'],
        ['Smartphones', 'Zenbit', 'Zenbit Z3 Lite (6GB RAM, 128GB)', 15999, 12499, ['Display' => '6.5" IPS LCD, 90 Hz', 'RAM' => '6 GB', 'Storage' => '128 GB', 'Camera' => '50 MP dual', 'Battery' => '5000 mAh'], ['Color' => ['Graphite', 'Mint']], '1 year manufacturer warranty'],
        ['Smartphones', 'Aurelia', 'Aurelia Pro Max 5G (12GB RAM, 256GB)', 54999, 46999, ['Display' => '6.8" LTPO AMOLED, 144 Hz', 'RAM' => '12 GB', 'Storage' => '256 GB', 'Camera' => '108 MP triple', 'Battery' => '5200 mAh, 67W charging'], ['Color' => ['Titanium Grey', 'Pearl White']], '1 year manufacturer warranty'],
        ['Phone Accessories', 'Voltix', '65W GaN Fast Charger with USB-C Cable', 2499, 1299, ['Output' => '65 W (PD 3.0)', 'Ports' => '2 x USB-C, 1 x USB-A', 'Cable' => '1.5 m USB-C'], [], '6 months warranty'],
        ['Phone Accessories', 'Voltix', 'Power Bank 20000 mAh (22.5W)', 2999, 1799, ['Capacity' => '20000 mAh', 'Output' => '22.5 W', 'Ports' => '2 x USB-A, 1 x USB-C'], ['Color' => ['Black', 'Blue']], '6 months warranty'],
        ['Phone Accessories', 'ArmorX', 'Shockproof Back Case for Nexora N5', 799, 299, ['Material' => 'TPU + polycarbonate', 'Fit' => 'Nexora N5'], ['Color' => ['Clear', 'Black']], 'No warranty'],
    ]],
    'computers' => ['Laptops & Computers', 'bi-laptop', 18, ['Laptops', 'Computer Accessories'], [
        ['Laptops', 'Lumina', 'Lumina Book 14 (Core i5, 16GB, 512GB SSD)', 69990, 54990, ['Processor' => 'Intel Core i5 (13th Gen)', 'RAM' => '16 GB DDR5', 'Storage' => '512 GB NVMe SSD', 'Display' => '14" FHD IPS', 'Weight' => '1.4 kg', 'OS' => 'Windows 11 Home'], ['Color' => ['Silver', 'Space Grey']], '1 year onsite warranty'],
        ['Laptops', 'Lumina', 'Lumina Air 13 (Ryzen 5, 8GB, 512GB SSD)', 52990, 41990, ['Processor' => 'AMD Ryzen 5', 'RAM' => '8 GB', 'Storage' => '512 GB SSD', 'Display' => '13.3" 2K IPS', 'Weight' => '1.2 kg'], ['Color' => ['Silver']], '1 year onsite warranty'],
        ['Laptops', 'Forgebyte', 'Forgebyte Titan Gaming Laptop (RTX 4050, 16GB)', 109990, 89990, ['Processor' => 'Intel Core i7', 'GPU' => 'RTX 4050 6GB', 'RAM' => '16 GB', 'Storage' => '1 TB SSD', 'Display' => '15.6" FHD 144 Hz'], ['Color' => ['Black']], '1 year warranty'],
        ['Computer Accessories', 'Clickora', 'Wireless Keyboard and Mouse Combo', 1799, 899, ['Connectivity' => '2.4 GHz wireless', 'Battery' => 'AA x 2', 'Layout' => 'Full size'], ['Color' => ['Black', 'White']], '1 year warranty'],
        ['Computer Accessories', 'StoreMax', 'Portable SSD 1TB (USB 3.2)', 8999, 6499, ['Capacity' => '1 TB', 'Read speed' => 'up to 1050 MB/s', 'Interface' => 'USB 3.2 Gen2'], ['Color' => ['Black', 'Blue']], '3 years warranty'],
        ['Computer Accessories', 'ViewLine', '24-inch Full HD IPS Monitor (75 Hz)', 11999, 8499, ['Size' => '24"', 'Resolution' => '1920 x 1080', 'Panel' => 'IPS', 'Refresh rate' => '75 Hz', 'Ports' => 'HDMI, VGA'], [], '3 years warranty'],
    ]],
    'audio-tv' => ['Headphones, Speakers & TVs', 'bi-headphones', 18, ['Headphones', 'Speakers', 'Televisions'], [
        ['Headphones', 'Tarang', 'Tarang Beats Wireless Headphones (40h battery)', 4999, 1999, ['Type' => 'Over-ear Bluetooth 5.3', 'Battery' => '40 hours', 'Charging' => 'USB-C fast charge'], ['Color' => ['Black', 'Navy', 'Beige']], '1 year warranty'],
        ['Headphones', 'Tarang', 'Tarang Buds Pro True Wireless Earbuds (ANC)', 5999, 2799, ['Type' => 'In-ear TWS', 'ANC' => 'Yes, up to 35 dB', 'Battery' => '8h + 24h case'], ['Color' => ['White', 'Black']], '1 year warranty'],
        ['Speakers', 'Dhwani', 'Dhwani Boom 20W Portable Bluetooth Speaker', 3499, 1599, ['Output' => '20 W', 'Battery' => '12 hours', 'Water resistance' => 'IPX6'], ['Color' => ['Black', 'Red', 'Teal']], '1 year warranty'],
        ['Speakers', 'Dhwani', 'Dhwani Soundbar 2.1 with Subwoofer (120W)', 12990, 7499, ['Output' => '120 W RMS', 'Channels' => '2.1', 'Connectivity' => 'Bluetooth, HDMI ARC, AUX'], [], '1 year warranty'],
        ['Televisions', 'Vistara', 'Vistara 43" 4K Ultra HD Smart LED TV', 38990, 24999, ['Screen' => '43" (108 cm)', 'Resolution' => '3840 x 2160', 'Smart TV' => 'Yes', 'Ports' => '3 x HDMI, 2 x USB'], [], '1 year comprehensive warranty'],
        ['Televisions', 'Vistara', 'Vistara 55" 4K QLED Smart TV', 74990, 46990, ['Screen' => '55" (139 cm)', 'Resolution' => '3840 x 2160', 'Panel' => 'QLED', 'Refresh rate' => '60 Hz'], [], '1 year comprehensive warranty'],
    ]],
    'mens-fashion' => ['Men\'s Clothing', 'bi-person', 12, ['T-Shirts', 'Shirts', 'Jeans & Trousers'], [
        ['T-Shirts', 'Urbanic Co', 'Men\'s Cotton Round Neck T-Shirt (Pack of 2)', 1299, 599, ['Fabric' => '100% cotton', 'Fit' => 'Regular', 'Care' => 'Machine wash'], ['Size' => ['S', 'M', 'L', 'XL'], 'Color' => ['Black', 'Navy', 'Grey']], '7 days return'],
        ['Shirts', 'Urbanic Co', 'Men\'s Slim Fit Formal Shirt', 1999, 899, ['Fabric' => 'Cotton blend', 'Fit' => 'Slim', 'Sleeve' => 'Full'], ['Size' => ['38', '40', '42', '44'], 'Color' => ['White', 'Sky Blue']], '7 days return'],
        ['Jeans & Trousers', 'Denimra', 'Men\'s Slim Fit Stretch Jeans', 2499, 1199, ['Fabric' => 'Cotton elastane', 'Fit' => 'Slim', 'Rise' => 'Mid'], ['Size' => ['30', '32', '34', '36'], 'Color' => ['Indigo', 'Black']], '7 days return'],
        ['Shirts', 'Kurtaverse', 'Men\'s Cotton Kurta with Pyjama Set', 2999, 1499, ['Fabric' => 'Cotton', 'Set' => 'Kurta + pyjama', 'Occasion' => 'Festive / casual'], ['Size' => ['M', 'L', 'XL'], 'Color' => ['Cream', 'Maroon']], '7 days return'],
        ['T-Shirts', 'Urbanic Co', 'Men\'s Hooded Sweatshirt', 2299, 1099, ['Fabric' => 'Fleece', 'Fit' => 'Relaxed'], ['Size' => ['M', 'L', 'XL'], 'Color' => ['Grey Melange', 'Black']], '7 days return'],
    ]],
    'womens-fashion' => ['Women\'s Clothing', 'bi-person-standing-dress', 12, ['Kurtis & Ethnic', 'Western Wear', 'Sarees'], [
        ['Kurtis & Ethnic', 'Sutra', 'Women\'s Printed Cotton Anarkali Kurti', 1999, 799, ['Fabric' => 'Cotton', 'Pattern' => 'Printed', 'Length' => 'Calf'], ['Size' => ['S', 'M', 'L', 'XL'], 'Color' => ['Teal', 'Mustard', 'Pink']], '7 days return'],
        ['Sarees', 'Sutra', 'Banarasi Style Silk Blend Saree with Blouse Piece', 4999, 1899, ['Fabric' => 'Silk blend', 'Length' => '6.3 m with blouse piece', 'Care' => 'Dry clean'], ['Color' => ['Red', 'Green', 'Royal Blue']], '7 days return'],
        ['Western Wear', 'Veloura', 'Women\'s A-Line Midi Dress', 2799, 1299, ['Fabric' => 'Georgette', 'Fit' => 'A-line', 'Length' => 'Midi'], ['Size' => ['S', 'M', 'L'], 'Color' => ['Navy', 'Wine']], '7 days return'],
        ['Western Wear', 'Veloura', 'Women\'s High Rise Straight Jeans', 2599, 1399, ['Fabric' => 'Cotton stretch', 'Rise' => 'High', 'Fit' => 'Straight'], ['Size' => ['28', '30', '32', '34'], 'Color' => ['Light Blue', 'Black']], '7 days return'],
        ['Kurtis & Ethnic', 'Sutra', 'Women\'s Chikankari Kurta Set with Dupatta', 3999, 1999, ['Fabric' => 'Georgette', 'Set' => 'Kurta, palazzo, dupatta'], ['Size' => ['S', 'M', 'L', 'XL'], 'Color' => ['White', 'Pastel Pink']], '7 days return'],
    ]],
    'footwear-watches' => ['Shoes, Watches & Accessories', 'bi-watch', 12, ['Shoes', 'Watches', 'Fashion Accessories'], [
        ['Shoes', 'Stridex', 'Stridex Cloud Running Shoes for Men', 4499, 2499, ['Upper' => 'Breathable mesh', 'Sole' => 'EVA cushioned', 'Use' => 'Running / gym'], ['Size' => ['UK 7', 'UK 8', 'UK 9', 'UK 10'], 'Color' => ['Black/Red', 'Grey/Blue']], '90 days manufacturer warranty'],
        ['Shoes', 'Stridex', 'Women\'s Lightweight Walking Shoes', 3299, 1799, ['Upper' => 'Knit mesh', 'Sole' => 'Rubber'], ['Size' => ['UK 4', 'UK 5', 'UK 6', 'UK 7'], 'Color' => ['Pink', 'Grey']], '90 days manufacturer warranty'],
        ['Watches', 'Chronix', 'Chronix Pulse Smartwatch (1.83" display, calling)', 7999, 3499, ['Display' => '1.83" HD', 'Battery' => 'Up to 7 days', 'Features' => 'Bluetooth calling, SpO2, heart rate', 'Water resistance' => 'IP68'], ['Color' => ['Jet Black', 'Silver', 'Rose Gold']], '1 year warranty'],
        ['Watches', 'Chronix', 'Men\'s Analog Stainless Steel Watch', 5999, 2999, ['Case' => '42 mm stainless steel', 'Movement' => 'Quartz', 'Water resistance' => '30 m'], ['Color' => ['Silver/Blue', 'Black']], '2 years warranty'],
        ['Fashion Accessories', 'Leathra', 'Genuine Leather Wallet for Men (RFID)', 1499, 599, ['Material' => 'Genuine leather', 'Card slots' => '8', 'RFID' => 'Blocking'], ['Color' => ['Tan', 'Black']], '6 months warranty'],
        ['Fashion Accessories', 'Lensy', 'UV400 Polarised Sunglasses', 1999, 799, ['Lens' => 'Polarised UV400', 'Frame' => 'Lightweight TR90'], ['Color' => ['Black', 'Havana']], '6 months warranty'],
    ]],
    'appliances' => ['Home Appliances', 'bi-lightning-charge', 18, ['Large Appliances', 'Small Appliances'], [
        ['Large Appliances', 'Frostline', 'Frostline 236L Double Door Frost Free Refrigerator', 27990, 22490, ['Capacity' => '236 L', 'Type' => 'Frost free double door', 'Energy rating' => '3 star'], ['Color' => ['Steel Grey', 'Wine Red']], '1 year product + 10 years compressor'],
        ['Large Appliances', 'WashWell', 'WashWell 7 kg Fully Automatic Front Load Washing Machine', 33990, 24990, ['Capacity' => '7 kg', 'Type' => 'Front load', 'Max spin' => '1200 RPM'], [], '2 years product + 10 years motor'],
        ['Large Appliances', 'Coolwave', 'Coolwave 1.5 Ton 3 Star Inverter Split AC', 47990, 33990, ['Capacity' => '1.5 ton', 'Type' => 'Inverter split', 'Energy rating' => '3 star'], [], '1 year product + 5 years compressor'],
        ['Small Appliances', 'Breezo', 'Breezo 1200 mm Energy Saving Ceiling Fan', 3499, 2299, ['Sweep' => '1200 mm', 'Power' => '28 W BLDC', 'Remote' => 'Yes'], ['Color' => ['White', 'Brown']], '2 years warranty'],
        ['Small Appliances', 'PureDrop', 'PureDrop RO+UV Water Purifier 8L', 18990, 11990, ['Purification' => 'RO + UV + TDS control', 'Tank' => '8 L'], [], '1 year warranty'],
    ]],
    'home-decor' => ['Furniture & Home Decor', 'bi-lamp', 18, ['Furniture', 'Home Decor'], [
        ['Furniture', 'Nestora', 'Nestora Ergonomic Office Chair with Lumbar Support', 12999, 6999, ['Material' => 'Mesh back, PU seat', 'Adjustments' => 'Height, tilt, armrest', 'Max load' => '120 kg'], ['Color' => ['Black', 'Grey']], '1 year warranty'],
        ['Furniture', 'Nestora', 'Engineered Wood Study Table with Shelves', 8999, 4999, ['Material' => 'Engineered wood', 'Size' => '120 x 60 x 75 cm'], ['Color' => ['Walnut', 'Wenge']], '1 year warranty'],
        ['Furniture', 'Nestora', '3-Seater Fabric Sofa', 32999, 18999, ['Seating' => '3 seater', 'Upholstery' => 'Fabric', 'Frame' => 'Solid wood'], ['Color' => ['Grey', 'Beige']], '2 years warranty'],
        ['Home Decor', 'Gharsaaz', 'Cotton Bedsheet Set, King Size (with 2 pillow covers)', 2499, 899, ['Fabric' => '180 TC cotton', 'Size' => 'King 108 x 108 in'], ['Color' => ['Floral Blue', 'Geometric Grey']], '7 days return'],
        ['Home Decor', 'Gharsaaz', 'Decorative LED Table Lamp', 1999, 849, ['Light' => 'Warm white LED', 'Power' => 'USB / adapter'], ['Color' => ['Wood', 'White']], '6 months warranty'],
    ]],
    'kitchen' => ['Kitchen Equipment', 'bi-cup-hot', 12, ['Cookware', 'Kitchen Appliances'], [
        ['Cookware', 'Rasoi', 'Non-Stick Cookware Set, 5 Pieces (Induction Base)', 4999, 2199, ['Pieces' => '5', 'Coating' => 'Non-stick', 'Induction' => 'Yes'], [], '1 year warranty'],
        ['Cookware', 'Rasoi', 'Stainless Steel Pressure Cooker 5 L', 2899, 1499, ['Capacity' => '5 L', 'Material' => 'Stainless steel'], [], '5 years warranty'],
        ['Kitchen Appliances', 'Mixora', 'Mixora 750W Mixer Grinder with 3 Jars', 4499, 2699, ['Power' => '750 W', 'Jars' => '3'], ['Color' => ['Black', 'Red']], '2 years warranty'],
        ['Kitchen Appliances', 'Airfryo', 'Airfryo Digital Air Fryer 4.2 L', 9999, 5499, ['Capacity' => '4.2 L', 'Power' => '1500 W', 'Presets' => '8'], [], '1 year warranty'],
        ['Cookware', 'Rasoi', 'Borosilicate Glass Lunch Box Set (Pack of 3)', 1299, 699, ['Material' => 'Borosilicate glass', 'Lids' => 'Leak proof'], [], '6 months warranty'],
    ]],
    'beauty' => ['Beauty & Personal Care', 'bi-stars', 18, ['Skincare', 'Haircare', 'Grooming'], [
        ['Skincare', 'Prakriti', 'Vitamin C Face Serum 30 ml', 999, 549, ['Volume' => '30 ml', 'Skin type' => 'All skin types'], [], 'N/A'],
        ['Skincare', 'Prakriti', 'SPF 50 PA+++ Sunscreen Gel 50 g', 599, 379, ['Volume' => '50 g', 'SPF' => '50 PA+++'], [], 'N/A'],
        ['Haircare', 'Kesari', 'Onion & Bhringraj Hair Oil 200 ml', 449, 299, ['Volume' => '200 ml', 'Free from' => 'Mineral oil'], [], 'N/A'],
        ['Grooming', 'Trimax', 'Trimax Cordless Beard Trimmer (60 min runtime)', 1999, 999, ['Runtime' => '60 min', 'Blades' => 'Stainless steel', 'Charging' => 'USB-C'], [], '1 year warranty'],
        ['Grooming', 'Trimax', 'Hair Dryer 1800W with Cool Shot', 1999, 1099, ['Power' => '1800 W', 'Settings' => '3 heat, 2 speed'], [], '1 year warranty'],
    ]],
    'grocery' => ['Grocery & Daily Essentials', 'bi-basket', 5, ['Staples', 'Snacks & Beverages'], [
        ['Staples', 'Annapurna', 'Whole Wheat Atta 10 kg', 520, 439, ['Weight' => '10 kg', 'Type' => 'Chakki atta'], [], 'Best before 4 months from packing'],
        ['Staples', 'Annapurna', 'Basmati Rice, Premium 5 kg', 899, 699, ['Weight' => '5 kg', 'Grain' => 'Extra long'], [], 'Best before 12 months'],
        ['Staples', 'Suvarna', 'Refined Sunflower Oil 5 L', 899, 749, ['Volume' => '5 L'], [], 'Best before 9 months'],
        ['Snacks & Beverages', 'Chaiwala', 'Assam CTC Tea 1 kg', 599, 449, ['Weight' => '1 kg', 'Type' => 'CTC leaf tea'], [], 'Best before 12 months'],
        ['Snacks & Beverages', 'Crunchy', 'Roasted Masala Makhana, Pack of 4', 799, 449, ['Net weight' => '4 x 60 g', 'Type' => 'Roasted snack'], [], 'Best before 6 months'],
    ]],
    'books' => ['Books & Education', 'bi-book', 0, ['Books', 'Stationery'], [
        ['Books', 'Pustak House', 'The Art of Focus: A Guide to Deep Work (Paperback)', 399, 279, ['Format' => 'Paperback', 'Pages' => '288', 'Language' => 'English'], [], 'Return within 7 days if damaged'],
        ['Books', 'Pustak House', 'Class 10 Mathematics Practice Workbook', 450, 349, ['Format' => 'Paperback', 'Pages' => '416', 'Board' => 'CBSE'], [], 'Return within 7 days if damaged'],
        ['Books', 'Pustak House', 'Learn Python Programming, 3rd Edition', 899, 649, ['Format' => 'Paperback', 'Pages' => '540', 'Language' => 'English'], [], 'Return within 7 days if damaged'],
        ['Stationery', 'Lekhani', 'A5 Hardbound Ruled Notebook, Pack of 3', 599, 349, ['Pages' => '192 each', 'Paper' => '80 GSM'], ['Color' => ['Assorted']], 'N/A'],
        ['Stationery', 'Lekhani', 'Gel Pens, Pack of 20 (Blue/Black)', 299, 169, ['Count' => '20', 'Tip' => '0.5 mm'], [], 'N/A'],
    ]],
    'toys-sports' => ['Toys, Games & Sports', 'bi-controller', 12, ['Toys & Games', 'Sports & Fitness'], [
        ['Toys & Games', 'Kidoz', '500-Piece Building Blocks Creative Set', 1999, 999, ['Pieces' => '500', 'Age' => '6+ years'], [], '7 days return'],
        ['Toys & Games', 'Kidoz', 'Remote Control Stunt Car with Rechargeable Battery', 1799, 899, ['Range' => '30 m', 'Age' => '6+ years'], ['Color' => ['Red', 'Blue']], '6 months warranty'],
        ['Sports & Fitness', 'Fitora', 'Anti-Slip Yoga Mat 6 mm with Strap', 1299, 549, ['Thickness' => '6 mm', 'Material' => 'TPE'], ['Color' => ['Purple', 'Green', 'Black']], '6 months warranty'],
        ['Sports & Fitness', 'Fitora', 'Adjustable Dumbbell Set 20 kg', 3999, 2299, ['Weight' => '20 kg total', 'Material' => 'Cast iron + rubber'], [], '1 year warranty'],
        ['Sports & Fitness', 'Batwala', 'English Willow Cricket Bat (Size 6)', 3499, 1999, ['Willow' => 'English willow', 'Size' => '6'], [], '3 months warranty'],
    ]],
    'bags-luggage' => ['Bags, Luggage & Travel', 'bi-bag', 18, ['Backpacks', 'Luggage', 'Travel Accessories'], [
        ['Backpacks', 'Wandr', 'Wandr 30L Laptop Backpack (up to 15.6")', 2499, 999, ['Capacity' => '30 L', 'Laptop' => 'Up to 15.6"', 'Water resistant' => 'Yes'], ['Color' => ['Black', 'Navy', 'Olive']], '1 year warranty'],
        ['Luggage', 'Wandr', 'Wandr Hardshell Cabin Suitcase 55 cm (TSA lock)', 6999, 2999, ['Size' => '55 cm cabin', 'Wheels' => '360° spinner', 'Lock' => 'TSA'], ['Color' => ['Blue', 'Maroon', 'Silver']], '3 years warranty'],
        ['Luggage', 'Wandr', 'Wandr Check-in Trolley Set of 3 (55/65/75 cm)', 17999, 7999, ['Pieces' => '3', 'Material' => 'Polycarbonate'], ['Color' => ['Teal', 'Black']], '3 years warranty'],
        ['Travel Accessories', 'Wandr', 'Neck Pillow with Eye Mask and Earplugs', 899, 349, ['Fill' => 'Memory foam'], ['Color' => ['Grey', 'Navy']], '7 days return'],
        ['Travel Accessories', 'Wandr', 'Travel Organiser Packing Cubes, Set of 6', 1299, 499, ['Pieces' => '6'], ['Color' => ['Grey', 'Blue']], '7 days return'],
    ]],
];

$blurbs = [
    'mobiles' => 'Built for everyday use with dependable performance and all-day battery.',
    'computers' => 'A reliable choice for work, study and entertainment.',
    'audio-tv' => 'Clear, immersive sound and picture for your home or commute.',
    'mens-fashion' => 'Comfortable everyday fit made from quality fabric.',
    'womens-fashion' => 'Stylish design that works from daily wear to special occasions.',
    'footwear-watches' => 'Designed for comfort, durability and style.',
    'appliances' => 'Energy-conscious design to make home life easier.',
    'home-decor' => 'Thoughtfully designed to make your space comfortable and stylish.',
    'kitchen' => 'Durable, easy-to-clean kitchen essentials.',
    'beauty' => 'Gentle, everyday personal-care formulas.',
    'grocery' => 'Quality daily essentials, packed fresh.',
    'books' => 'Great for learning and leisure reading.',
    'toys-sports' => 'Fun, safe and durable for active play.',
    'bags-luggage' => 'Built to handle everyday commutes and long trips.',
];

$reviewTitles = [5 => ['Excellent value', 'Totally worth it', 'Very happy with this'], 4 => ['Good product', 'Does the job well', 'Nice, with minor gripes'], 3 => ['Average', 'Okay for the price'], 2 => ['Could be better']];
$reviewBodies = [
    5 => ['Quality is much better than I expected for the price. Delivery was on time too.', 'Using it for a few weeks now and no complaints at all. Recommended.'],
    4 => ['Works as described. Packaging could be better but the product itself is good.', 'Good value overall. Would buy again.'],
    3 => ['Decent, but nothing special. Fine for the price.', 'Works fine; finish could be a little better.'],
    2 => ['Not as good as I had hoped. Support was helpful though.'],
];

$catIds = $subIds = [];
$sort = 0;
foreach ($catalog as $slug => [$cname, $icon, $gst, $subs, $items]) {
    $cid = DB::insert('categories', ['parent_id' => null, 'name' => $cname, 'slug' => $slug, 'icon' => $icon, 'sort_order' => $sort++]);
    $catIds[$slug] = $cid;
    foreach ($subs as $s) {
        $subIds[$slug][$s] = DB::insert('categories', ['parent_id' => $cid, 'name' => $s, 'slug' => $slug . '-' . slugify($s), 'icon' => null, 'sort_order' => 0]);
    }
}

$count = 0;
foreach ($catalog as $slug => [$cname, $icon, $gst, $subs, $items]) {
    foreach ($items as $i => [$sub, $brand, $name, $mrp, $price, $specs, $options, $warranty]) {
        $count++;
        $sku = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $slug), 0, 3)) . '-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
        $disc = discount_pct((float)$mrp, (float)$price);
        $desc = $name . ' by ' . $brand . '. ' . $blurbs[$slug]
            . ' Key details: ' . implode('; ', array_map(fn($k, $v) => "$k: $v", array_keys($specs), $specs)) . '.'
            . ' (Demo catalogue item: fictional brand, illustrative price.)';
        $pid = DB::insert('products', [
            'sku' => $sku, 'slug' => slugify($name) . '-' . strtolower($sku), 'name' => $name, 'brand' => $brand,
            'category_id' => $catIds[$slug], 'subcategory_id' => $subIds[$slug][$sub] ?? null,
            'short_desc' => $blurbs[$slug], 'description' => $desc,
            'mrp' => $mrp, 'price' => $price, 'discount_pct' => $disc,
            'stock' => ($count % 17 === 0) ? 0 : (($count % 7 === 0) ? mt_rand(2, 5) : mt_rand(15, 120)),
            'rating_avg' => 0, 'rating_count' => 0, 'sold_count' => mt_rand(20, 2400),
            'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'options' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'shipping_info' => 'Ships in 1-2 business days. Free standard delivery on orders of ₹' . cfg('shop.free_shipping_min') . ' and above.',
            'return_policy' => $slug === 'grocery' ? 'Non-returnable except for damaged or wrong items (report within 48 hours).' : cfg('shop.return_window_days') . '-day return/replacement for damaged, defective or wrong items.',
            'warranty' => $warranty, 'gst_rate' => $gst,
            'is_active' => 1, 'is_deal' => $disc >= 35 ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(1, 90) . ' days')),
        ]);
        for ($n = 1; $n <= 3; $n++) {
            DB::insert('product_images', ['product_id' => $pid, 'url' => "img/ph/$sku/$n", 'alt' => "$name - image $n", 'sort_order' => $n]);
        }
        // demo reviews
        $nRev = mt_rand(2, 5);
        $reviewers = array_slice($userIds, 2);
        shuffle($reviewers);
        $sum = 0; $cnt = 0;
        foreach (array_slice($reviewers, 0, min($nRev, count($reviewers))) as $uid) {
            $r = [5, 5, 5, 4, 4, 4, 3, 2][mt_rand(0, 7)];
            DB::insert('reviews', [
                'product_id' => $pid, 'user_id' => $uid, 'rating' => $r,
                'title' => $reviewTitles[$r][array_rand($reviewTitles[$r])], 'body' => $reviewBodies[$r][array_rand($reviewBodies[$r])],
                'verified_purchase' => 0, 'created_at' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(1, 60) . ' days')),
            ]);
            $sum += $r; $cnt++;
        }
        DB::update('products', ['rating_avg' => $cnt ? round($sum / $cnt, 2) : 0, 'rating_count' => $cnt], 'id = ?', [$pid]);
        if ($count % 3 === 0) {
            DB::insert('questions', ['product_id' => $pid, 'user_id' => $userIds[3], 'question' => 'Is this covered under warranty and is a GST invoice provided?', 'answer' => 'Yes, a GST invoice is provided with every order. Warranty terms are listed on this page.', 'answered_at' => $now, 'created_at' => $now]);
        }
    }
}

echo "Seeded $count products, " . count($users) . " users, " . count($coupons) . " coupons.\n";
echo "Admin login:    admin@shopsphere.test / Admin@12345   (CHANGE THIS before any real deployment)\n";
echo "Staff login:    staff@shopsphere.test / Staff@12345\n";
echo "Customer login: demo@shopsphere.test  / Demo@12345\n";
