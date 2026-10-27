<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Services\Catalog;
use App\Services\Delivery;
use App\Services\OrderService;

final class ShopController
{
    public function home(): void
    {
        if (random_int(1, 25) === 1) OrderService::expirePending();   // lazy housekeeping for unpaid orders

        $sections = [];
        foreach ([
            ['mobiles', 'Electronics & mobile deals', 'mobiles'],
            ['computers', 'Laptops & computers', 'computers'],
            ['mens-fashion', 'Fashion & clothing', null],
            ['footwear-watches', 'Footwear, watches & accessories', 'footwear-watches'],
            ['appliances', 'Home & kitchen', null],
            ['beauty', 'Beauty & personal care', 'beauty'],
            ['grocery', 'Grocery & daily essentials', 'grocery'],
            ['books', 'Books', 'books'],
            ['toys-sports', 'Toys & sports', 'toys-sports'],
        ] as [$slug, $title, $link]) {
            $items = Catalog::inCategory($slug, 10, 'p.discount_pct DESC, p.sold_count DESC');
            if ($slug === 'mens-fashion') $items = array_merge($items, Catalog::inCategory('womens-fashion', 6));
            if ($slug === 'appliances') $items = array_merge($items, Catalog::inCategory('kitchen', 6));
            if ($items) $sections[] = ['title' => $title, 'slug' => $link ?? $slug, 'items' => $items];
        }

        render('home', [
            'title' => 'Online Shopping India - ' . cfg('app.name'),
            'deals' => Catalog::deals(12),
            'bestsellers' => Catalog::bestsellers(12),
            'recommended' => Catalog::recommended(12),
            'new' => Catalog::newArrivals(12),
            'recent' => Catalog::recent(12),
            'sections' => $sections,
            'reviews' => Catalog::latestReviews(6),
            'coupons' => DB::all("SELECT code, description FROM coupons WHERE is_active = 1 ORDER BY id LIMIT 3"),
        ]);
    }

    public function listing(array $p = []): void
    {
        $f = [
            'q' => str_in('q', 100), 'category' => $p['slug'] ?? str_in('category', 120),
            'min_price' => input('min_price', ''), 'max_price' => input('max_price', ''),
            'brand' => (array)($_GET['brand'] ?? []), 'rating' => (int)input('rating', 0), 'discount' => (int)input('discount', 0),
            'in_stock' => input('in_stock', ''), 'color' => str_in('color', 40), 'size' => str_in('size', 40),
            'sort' => str_in('sort', 20) ?: 'relevance', 'page' => (int)input('page', 1),
        ];
        if (($p['_deals'] ?? false) && !$f['discount']) { $f['discount'] = 30; $f['sort'] = $_GET['sort'] ?? 'discount'; }
        $cat = $f['category'] ? Catalog::categoryBySlug($f['category']) : null;
        if ($f['category'] && !$cat) not_found();

        $res = Catalog::search($f, 12);
        $facets = Catalog::facets($f);
        $trail = [];
        if ($cat) {
            if ($cat['parent_id']) {
                $par = DB::one('SELECT name, slug FROM categories WHERE id = ?', [$cat['parent_id']]);
                if ($par) $trail[] = $par;
            }
            $trail[] = $cat;
        }
        $subs = $cat ? DB::all('SELECT name, slug FROM categories WHERE parent_id = ? ORDER BY name', [$cat['parent_id'] ?: $cat['id']]) : [];

        if ($f['q'] !== '' && !$res['total']) {
            $res['suggest'] = Catalog::bestsellers(6);
        }
        $heading = $cat ? $cat['name'] : (($p['_deals'] ?? false) ? "Today's Deals" : ($f['q'] !== '' ? 'Results for "' . $f['q'] . '"' : 'All products'));
        render('listing', [
            'title' => $heading . ' - ' . cfg('app.name'), 'heading' => $heading, 'f' => $f, 'res' => $res, 'facets' => $facets,
            'cat' => $cat, 'trail' => $trail, 'subs' => $subs, 'is_deals' => (bool)($p['_deals'] ?? false),
        ]);
    }

    public function deals(): void
    {
        $this->listing(['_deals' => true]);
    }

    public function suggest(): void
    {
        $q = str_in('q', 80);
        json_out(['ok' => true] + (Catalog::suggest($q) ?: ['products' => [], 'categories' => []]));
    }

    public function product(array $p): void
    {
        $prod = Catalog::bySlug($p['slug']);
        if (!$prod || !(int)$prod['is_active']) not_found();
        Catalog::trackView((int)$prod['id']);

        $u = current_user();
        $reviews = DB::all('SELECT r.*, u.name AS user_name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC LIMIT 20', [$prod['id']]);
        $dist = array_fill(1, 5, 0);
        foreach (DB::all('SELECT rating, COUNT(*) AS c FROM reviews WHERE product_id = ? GROUP BY rating', [$prod['id']]) as $r) $dist[(int)$r['rating']] = (int)$r['c'];
        $questions = DB::all('SELECT q.*, u.name AS user_name FROM questions q JOIN users u ON u.id = q.user_id WHERE q.product_id = ? ORDER BY q.created_at DESC LIMIT 15', [$prod['id']]);
        $myReview = $u ? DB::one('SELECT * FROM reviews WHERE product_id = ? AND user_id = ?', [$prod['id'], $u['id']]) : null;

        $pin = Delivery::currentPin();
        render('product', [
            'title' => $prod['name'] . ' - ' . cfg('app.name'),
            'p' => $prod, 'images' => Catalog::images((int)$prod['id']),
            'specs' => json_dec($prod['specs']), 'options' => array_filter(json_dec($prod['options'])),
            'reviews' => $reviews, 'dist' => $dist, 'questions' => $questions, 'myReview' => $myReview,
            'related' => Catalog::related($prod, 8), 'together' => Catalog::boughtTogether($prod, 3),
            'recent' => Catalog::recent(8, (int)$prod['id']),
            'est' => $pin ? Delivery::estimate($pin) : null, 'pin' => $pin,
            'wished' => in_array((int)$prod['id'], Catalog::wishlistIds(), true),
            'comparing' => in_array((int)$prod['id'], Catalog::compareIds(), true),
        ]);
    }

    /** POST /api/pin  |  GET /api/delivery?pin= */
    public function setPin(): void
    {
        $pin = preg_replace('/\D/', '', str_in('pin', 10)) ?? '';
        if (!Delivery::validPin($pin)) json_out(['ok' => false, 'error' => 'Enter a valid 6-digit PIN code.'], 422);
        $_SESSION['pin'] = $pin;
        $std = Delivery::estimate($pin, 'standard');
        $exp = Delivery::estimate($pin, 'express');
        json_out(['ok' => true, 'pin' => $pin, 'standard' => $std['text'], 'express' => $exp['text'],
            'note' => 'Estimated using configured delivery rules (not live courier data).']);
    }

    public function review(array $p): void
    {
        $u = require_login();
        $prod = Catalog::bySlug($p['slug']);
        if (!$prod) not_found();
        $rating = (int)input('rating', 0);
        $title = str_in('title', 150);
        $body = str_in('body', 2000);
        if ($rating < 1 || $rating > 5) { flash('danger', 'Please select a star rating.'); redirect('/product/' . $prod['slug'] . '#reviews'); }
        $verified = (int)DB::val("SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.user_id = ? AND oi.product_id = ? AND o.order_status = 'delivered'", [$u['id'], $prod['id']]) > 0 ? 1 : 0;
        $ex = DB::val('SELECT id FROM reviews WHERE product_id = ? AND user_id = ?', [$prod['id'], $u['id']]);
        if ($ex) DB::update('reviews', ['rating' => $rating, 'title' => $title, 'body' => $body, 'verified_purchase' => $verified], 'id = ?', [$ex]);
        else DB::insert('reviews', ['product_id' => $prod['id'], 'user_id' => $u['id'], 'rating' => $rating, 'title' => $title, 'body' => $body, 'verified_purchase' => $verified, 'created_at' => now()]);
        Catalog::recalcRating((int)$prod['id']);
        flash('success', 'Thanks! Your review has been saved.');
        redirect('/product/' . $prod['slug'] . '#reviews');
    }

    public function question(array $p): void
    {
        $u = require_login();
        $prod = Catalog::bySlug($p['slug']);
        if (!$prod) not_found();
        $q = str_in('question', 500);
        if (mb_strlen($q) < 8) { flash('danger', 'Please write a complete question.'); redirect('/product/' . $prod['slug'] . '#questions'); }
        DB::insert('questions', ['product_id' => $prod['id'], 'user_id' => $u['id'], 'question' => $q, 'created_at' => now()]);
        flash('success', 'Your question was posted. We will answer it soon.');
        redirect('/product/' . $prod['slug'] . '#questions');
    }

    public function newsletter(): void
    {
        $email = strtolower(str_in('email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
        if (!DB::val('SELECT id FROM newsletter_subscribers WHERE email = ?', [$email])) DB::insert('newsletter_subscribers', ['email' => $email, 'created_at' => now()]);
        json_out(['ok' => true, 'message' => 'Thanks for subscribing!']);
    }

    /** Placeholder product artwork (SVG) used until licensed product photos are added. */
    public function placeholder(array $p): void
    {
        $row = DB::one('SELECT p.name, p.brand, c.slug AS cat, c.name AS cname FROM products p JOIN categories c ON c.id = p.category_id WHERE p.sku = ?', [$p['sku']]);
        $n = max(1, min(5, (int)$p['n']));
        $hue = $row ? (crc32($row['cat']) % 360) : 210;
        $name = $row['name'] ?? 'Product';
        $brand = $row['brand'] ?? '';
        $words = explode(' ', $name);
        $lines = []; $cur = '';
        foreach ($words as $w) {
            if (mb_strlen($cur . ' ' . $w) > 22 && $cur !== '') { $lines[] = $cur; $cur = $w; } else { $cur = trim($cur . ' ' . $w); }
        }
        if ($cur !== '') $lines[] = $cur;
        $lines = array_slice($lines, 0, 4);
        $t = '';
        foreach ($lines as $i => $l) $t .= '<text x="300" y="' . (330 + $i * 34) . '" text-anchor="middle" font-size="26" font-weight="600" fill="#1f2937">' . e($l) . '</text>';
        $shape = [
            '<rect x="215" y="120" width="170" height="170" rx="28" fill="hsl(' . $hue . ',55%,48%)" opacity=".85"/>',
            '<circle cx="300" cy="205" r="90" fill="hsl(' . $hue . ',55%,48%)" opacity=".85"/>',
            '<polygon points="300,110 395,290 205,290" fill="hsl(' . $hue . ',55%,48%)" opacity=".85"/>',
            '<rect x="200" y="140" width="200" height="130" rx="14" fill="hsl(' . $hue . ',55%,48%)" opacity=".85"/><rect x="240" y="270" width="120" height="14" rx="7" fill="hsl(' . $hue . ',45%,38%)"/>',
            '<ellipse cx="300" cy="205" rx="110" ry="80" fill="hsl(' . $hue . ',55%,48%)" opacity=".85"/>',
        ][$n - 1];
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" role="img" aria-label="' . e($name) . '">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(' . $hue . ',70%,96%)"/><stop offset="1" stop-color="hsl(' . $hue . ',60%,86%)"/></linearGradient></defs>'
            . '<rect width="600" height="600" fill="url(#g)"/>' . $shape
            . '<text x="300" y="70" text-anchor="middle" font-size="24" letter-spacing="3" fill="hsl(' . $hue . ',40%,30%)">' . e(strtoupper($brand)) . '</text>' . $t
            . '<text x="300" y="568" text-anchor="middle" font-size="16" fill="hsl(' . $hue . ',25%,40%)">Placeholder image · replace with a licensed photo</text></svg>';
    }

    public function staticPage(array $p): void
    {
        $pages = [
            'about' => ['About ShopSphere India', 'ShopSphere India is a demo e-commerce platform built with PHP and MySQL. Brands, prices, reviews and stock shown here are sample data for demonstration.'],
            'shipping-policy' => ['Shipping Policy', 'Standard delivery is free on orders of ₹' . cfg('shop.free_shipping_min') . ' and above (otherwise ₹' . cfg('shop.standard_shipping') . '). Express delivery costs ₹' . cfg('shop.express_shipping') . '. Delivery estimates are based on configured rules by PIN code and are not live courier promises.'],
            'returns-policy' => ['Returns & Refunds', 'Eligible items can be returned or replaced within ' . cfg('shop.return_window_days') . ' days of delivery if damaged, defective, wrong or not as described. Refunds go to the original payment method (Razorpay) or are processed manually for Cash on Delivery orders.'],
            'privacy' => ['Privacy Policy', 'We store only the information needed to run your account and fulfil your orders. Passwords are stored as salted hashes. Replace this text with your own legal policy before going live.'],
            'terms' => ['Terms of Use', 'This is a demonstration store. Replace this text with your own terms and conditions before going live.'],
            'contact' => ['Contact & Support', 'Email: ' . cfg('shop.seller.email') . ' · Phone: ' . cfg('shop.seller.phone') . ' · ' . cfg('shop.seller.address')],
        ];
        $page = $pages[$p['page']] ?? null;
        if (!$page) not_found();
        render('static', ['title' => $page[0] . ' - ' . cfg('app.name'), 'heading' => $page[0], 'body' => $page[1]]);
    }
}
