<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Services\Catalog;
use App\Services\OrderService;

/** Admin = full access. Staff = orders, returns and refunds only. */
final class AdminController
{
    private function staff(): array { return require_role('admin', 'staff'); }
    private function admin(): array { return require_role('admin'); }

    private function view(string $v, array $d = []): void
    {
        render('admin/' . $v, $d + ['title' => 'Admin - ' . cfg('app.name'), 'adminUser' => current_user()], 'admin_layout');
    }

    // ---- dashboard ---------------------------------------------------------------------------
    public function dashboard(): void
    {
        $this->staff();
        OrderService::expirePending();
        $kpi = DB::one("SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue FROM orders WHERE order_status NOT IN ('cancelled','pending_payment')");
        $today = DB::one("SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue FROM orders WHERE order_status NOT IN ('cancelled','pending_payment') AND created_at >= ?", [date('Y-m-d 00:00:00')]);
        $kpi['customers'] = (int)DB::val("SELECT COUNT(*) FROM users WHERE role = 'customer'");
        $kpi['products'] = (int)DB::val('SELECT COUNT(*) FROM products WHERE is_active = 1');
        $kpi['low_stock'] = (int)DB::val('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= 5');
        $kpi['pending_returns'] = (int)DB::val("SELECT COUNT(*) FROM returns WHERE status = 'requested'");
        $kpi['pending_refunds'] = (int)DB::val("SELECT COUNT(*) FROM refunds WHERE status IN ('initiated','failed')");

        $days = [];
        for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = ['orders' => 0, 'revenue' => 0.0];
        $from = array_key_first($days) . ' 00:00:00';
        foreach (DB::all("SELECT created_at, total FROM orders WHERE order_status NOT IN ('cancelled','pending_payment') AND created_at >= ?", [$from]) as $r) {
            $d = substr($r['created_at'], 0, 10);
            if (isset($days[$d])) { $days[$d]['orders']++; $days[$d]['revenue'] += (float)$r['total']; }
        }
        $byStatus = DB::all('SELECT order_status AS s, COUNT(*) AS n FROM orders GROUP BY order_status');
        $byCat = DB::all("SELECT c.name AS name, COALESCE(SUM(oi.unit_price * oi.qty),0) AS rev FROM order_items oi JOIN products p ON p.id = oi.product_id JOIN categories c ON c.id = p.category_id JOIN orders o ON o.id = oi.order_id WHERE o.order_status NOT IN ('cancelled','pending_payment') GROUP BY c.name ORDER BY rev DESC LIMIT 8");
        $top = DB::all("SELECT oi.name, SUM(oi.qty) AS qty, SUM(oi.unit_price * oi.qty) AS rev FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.order_status NOT IN ('cancelled','pending_payment') GROUP BY oi.name ORDER BY qty DESC LIMIT 5");

        $this->view('dashboard', [
            'kpi' => $kpi, 'today' => $today,
            'chart' => [
                'labels' => array_map(fn($d) => date('d M', strtotime($d)), array_keys($days)),
                'revenue' => array_map(fn($d) => round($d['revenue'], 2), array_values($days)),
                'orders' => array_map(fn($d) => $d['orders'], array_values($days)),
                'status' => ['labels' => array_map(fn($r) => status_label($r['s']), $byStatus), 'data' => array_map(fn($r) => (int)$r['n'], $byStatus)],
                'cat' => ['labels' => array_map(fn($r) => $r['name'], $byCat), 'data' => array_map(fn($r) => round((float)$r['rev'], 2), $byCat)],
            ],
            'top' => $top,
            'recent' => DB::all('SELECT * FROM orders ORDER BY id DESC LIMIT 8'),
            'lowStock' => DB::all('SELECT id, name, sku, stock FROM products WHERE is_active = 1 AND stock <= 5 ORDER BY stock, id LIMIT 8'),
        ]);
    }

    // ---- products ----------------------------------------------------------------------------------
    public function products(): void
    {
        $this->admin();
        $q = str_in('q', 100);
        $page = max(1, (int)input('page', 1));
        $where = '1=1'; $params = [];
        if ($q !== '') { $like = Catalog::likeEscape($q); $where = "(p.name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!' OR p.brand LIKE ? ESCAPE '!')"; $params = [$like, $like, $like]; }
        $total = (int)DB::val("SELECT COUNT(*) FROM products p WHERE $where", $params);
        $items = DB::all("SELECT p.id, p.sku, p.name, p.brand, p.price, p.mrp, p.stock, p.is_active, c.name AS cat FROM products p JOIN categories c ON c.id = p.category_id WHERE $where ORDER BY p.id DESC LIMIT 25 OFFSET " . (($page - 1) * 25), $params);
        $this->view('products', ['items' => $items, 'q' => $q, 'page' => $page, 'pages' => max(1, (int)ceil($total / 25)), 'total' => $total]);
    }

    public function productForm(array $p = []): void
    {
        $this->admin();
        $prod = null; $images = [];
        if (!empty($p['id'])) {
            $prod = DB::one('SELECT * FROM products WHERE id = ?', [(int)$p['id']]);
            if (!$prod) not_found();
            $images = array_column(Catalog::images((int)$prod['id']), 'url');
        }
        $cats = DB::all('SELECT id, parent_id, name FROM categories ORDER BY parent_id IS NOT NULL, sort_order, name');
        $this->view('product_form', ['p' => $prod, 'images' => $images, 'cats' => $cats]);
    }

    public function productSave(array $p = []): void
    {
        $this->admin();
        $id = (int)($p['id'] ?? 0);
        $f = [
            'name' => str_in('name', 220), 'brand' => str_in('brand', 80), 'sku' => strtoupper(str_in('sku', 40)),
            'category_id' => (int)input('category_id', 0), 'subcategory_id' => (int)input('subcategory_id', 0) ?: null,
            'short_desc' => str_in('short_desc', 255), 'description' => str_in('description', 8000),
            'mrp' => round((float)input('mrp', 0), 2), 'price' => round((float)input('price', 0), 2), 'stock' => (int)input('stock', 0),
            'gst_rate' => (float)input('gst_rate', 18), 'warranty' => str_in('warranty', 190), 'shipping_info' => str_in('shipping_info', 255),
            'return_policy' => str_in('return_policy', 255), 'is_active' => input('is_active') ? 1 : 0, 'is_deal' => input('is_deal') ? 1 : 0,
        ];
        $err = null;
        if ($f['name'] === '' || $f['brand'] === '' || $f['sku'] === '') $err = 'Name, brand and SKU are required.';
        elseif (!preg_match('/^[A-Z0-9_-]{3,40}$/', $f['sku'])) $err = 'SKU may contain letters, numbers, - and _ only.';
        elseif ($f['price'] <= 0) $err = 'Selling price must be greater than 0.';
        elseif ($f['mrp'] < $f['price']) $err = 'Original price (MRP) cannot be lower than the selling price.';
        elseif ($f['stock'] < 0) $err = 'Stock cannot be negative.';
        elseif (!in_array($f['gst_rate'], [0.0, 5.0, 12.0, 18.0, 28.0], true)) $err = 'GST rate must be 0, 5, 12, 18 or 28.';
        elseif (!DB::val('SELECT id FROM categories WHERE id = ?', [$f['category_id']])) $err = 'Select a category.';
        elseif (DB::val('SELECT id FROM products WHERE sku = ? AND id <> ?', [$f['sku'], $id])) $err = 'This SKU is already used by another product.';

        // specs: "Key: value" per line; options: "Color: Black, Blue" per line
        $specs = [];
        foreach (preg_split('/\R/', (string)input('specs', '')) ?: [] as $line) if (str_contains($line, ':')) { [$k, $v] = array_map('trim', explode(':', $line, 2)); if ($k !== '' && $v !== '') $specs[mb_substr($k, 0, 60)] = mb_substr($v, 0, 200); }
        $options = [];
        foreach (preg_split('/\R/', (string)input('options', '')) ?: [] as $line) if (str_contains($line, ':')) { [$k, $v] = array_map('trim', explode(':', $line, 2)); $vals = array_values(array_filter(array_map('trim', explode(',', $v)))); if ($k !== '' && $vals) $options[mb_substr($k, 0, 40)] = array_map(fn($x) => mb_substr($x, 0, 40), $vals); }
        $imgs = [];
        foreach (preg_split('/\R/', (string)input('images', '')) ?: [] as $u) {
            $u = trim($u);
            if ($u === '') continue;
            if (!preg_match('#^(https://[^\s"\'<>]+|img/[A-Za-z0-9_./-]+|assets/[A-Za-z0-9_./-]+)$#', $u)) { $err ??= 'Image URLs must be https:// links or local paths (img/..., assets/...). Invalid: ' . mb_substr($u, 0, 60); continue; }
            $imgs[] = $u;
        }
        if ($err) { with_old($_POST); flash('danger', $err); redirect($id ? '/admin/products/' . $id : '/admin/products/new'); }

        $f['discount_pct'] = discount_pct($f['mrp'], $f['price']);
        $f['specs'] = json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $f['options'] = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $f['updated_at'] = now();

        DB::tx(function () use (&$id, $f, $imgs) {
            if ($id) {
                DB::update('products', $f, 'id = ?', [$id]);
            } else {
                $f['slug'] = slugify($f['name']) . '-' . strtolower($f['sku']);
                $f['created_at'] = now();
                $id = DB::insert('products', $f);
            }
            DB::exec('DELETE FROM product_images WHERE product_id = ?', [$id]);
            foreach ($imgs as $i => $u) DB::insert('product_images', ['product_id' => $id, 'url' => $u, 'alt' => $f['name'], 'sort_order' => $i + 1]);
        });
        clear_old();
        flash('success', 'Product saved.');
        redirect('/admin/products/' . $id);
    }

    public function productToggle(array $p): void
    {
        $this->admin();
        DB::exec('UPDATE products SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = ? WHERE id = ?', [now(), (int)$p['id']]);
        flash('info', 'Product visibility updated.');
        redirect('/admin/products');
    }

    // ---- orders -------------------------------------------------------------------------------------------
    public function orders(): void
    {
        $this->staff();
        $status = str_in('status', 30); $q = str_in('q', 60); $page = max(1, (int)input('page', 1));
        $where = ['1=1']; $params = [];
        if ($status !== '') { $where[] = 'order_status = ?'; $params[] = $status; }
        if ($q !== '') { $like = Catalog::likeEscape($q); $where[] = "(order_no LIKE ? ESCAPE '!' OR customer_email LIKE ? ESCAPE '!' OR customer_name LIKE ? ESCAPE '!')"; array_push($params, $like, $like, $like); }
        $w = implode(' AND ', $where);
        $total = (int)DB::val("SELECT COUNT(*) FROM orders WHERE $w", $params);
        $rows = DB::all("SELECT * FROM orders WHERE $w ORDER BY id DESC LIMIT 25 OFFSET " . (($page - 1) * 25), $params);
        $this->view('orders', ['orders' => $rows, 'status' => $status, 'q' => $q, 'page' => $page, 'pages' => max(1, (int)ceil($total / 25))]);
    }

    public function orderDetail(array $p): void
    {
        $this->staff();
        $o = OrderService::find($p['no']);
        if (!$o) not_found();
        $this->view('order', [
            'o' => $o, 'items' => OrderService::items((int)$o['id']), 'events' => OrderService::events((int)$o['id']),
            'returns' => DB::all('SELECT r.*, oi.name AS item_name FROM returns r JOIN order_items oi ON oi.id = r.order_item_id WHERE r.order_id = ? ORDER BY r.id DESC', [$o['id']]),
            'refunds' => DB::all('SELECT * FROM refunds WHERE order_id = ? ORDER BY id DESC', [$o['id']]),
        ]);
    }

    public function orderStatus(array $p): void
    {
        $u = $this->staff();
        $o = OrderService::find($p['no']);
        if (!$o) not_found();
        $err = OrderService::updateStatus($o, str_in('status', 30), str_in('note', 250), (int)$u['id']);
        flash($err ? 'danger' : 'success', $err ?: 'Order status updated.');
        redirect('/admin/orders/' . $o['order_no']);
    }

    public function orderCancel(array $p): void
    {
        $u = $this->staff();
        $o = OrderService::find($p['no']);
        if (!$o) not_found();
        $ok = OrderService::cancel($o, str_in('reason', 200) ?: 'Cancelled by store', (int)$u['id'], true);
        flash($ok ? 'success' : 'danger', $ok ? 'Order cancelled and stock restored.' : 'This order cannot be cancelled.');
        redirect('/admin/orders/' . $o['order_no']);
    }

    public function refundProcess(array $p): void
    {
        $u = $this->staff();
        $err = OrderService::processRefund((int)$p['id'], (int)$u['id']);
        flash($err ? 'danger' : 'success', $err ?: 'Refund processed.');
        $back = str_in('back_order', 30);
        redirect(preg_match('/^SS\d+$/', $back) ? '/admin/orders/' . $back : '/admin/refunds');
    }

    public function refunds(): void
    {
        $this->staff();
        $rows = DB::all('SELECT r.*, o.order_no, o.customer_name, o.payment_method FROM refunds r JOIN orders o ON o.id = r.order_id ORDER BY r.id DESC LIMIT 100');
        $this->view('refunds', ['refunds' => $rows]);
    }

    public function returns(): void
    {
        $this->staff();
        $rows = DB::all('SELECT r.*, o.order_no, o.customer_name, oi.name AS item_name, oi.qty FROM returns r JOIN orders o ON o.id = r.order_id JOIN order_items oi ON oi.id = r.order_item_id ORDER BY (r.status = ?) DESC, r.id DESC LIMIT 100', ['requested']);
        $this->view('returns', ['returns' => $rows]);
    }

    public function returnHandle(array $p): void
    {
        $u = $this->staff();
        $err = OrderService::handleReturn((int)$p['id'], str_in('action', 15), str_in('note', 250), (int)$u['id']);
        flash($err ? 'danger' : 'success', $err ?: 'Request updated.');
        redirect('/admin/returns');
    }

    // ---- coupons ---------------------------------------------------------------------------------------------------
    public function coupons(): void
    {
        $this->admin();
        $this->view('coupons', ['coupons' => DB::all('SELECT * FROM coupons ORDER BY id DESC')]);
    }

    public function couponSave(): void
    {
        $this->admin();
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', str_in('code', 30)) ?? '');
        $type = str_in('type', 10); $val = (float)input('value', 0);
        if ($code === '' || !in_array($type, ['percent', 'flat'], true) || $val <= 0 || ($type === 'percent' && $val > 90)) {
            flash('danger', 'Enter a code, a type and a valid value (percent coupons: 1-90).'); redirect('/admin/coupons');
        }
        $data = [
            'description' => str_in('description', 190), 'type' => $type, 'value' => $val, 'min_order' => max(0, (float)input('min_order', 0)),
            'max_discount' => input('max_discount', '') !== '' ? (float)input('max_discount') : null,
            'expires_at' => input('expires_at', '') !== '' ? date('Y-m-d 23:59:59', strtotime((string)input('expires_at'))) : null,
            'usage_limit' => input('usage_limit', '') !== '' ? (int)input('usage_limit') : null, 'is_active' => input('is_active') ? 1 : 0,
        ];
        $ex = DB::val('SELECT id FROM coupons WHERE code = ?', [$code]);
        if ($ex) DB::update('coupons', $data, 'id = ?', [$ex]); else DB::insert('coupons', $data + ['code' => $code, 'used_count' => 0]);
        flash('success', 'Coupon saved.');
        redirect('/admin/coupons');
    }

    // ---- users ---------------------------------------------------------------------------------------------------------
    public function users(): void
    {
        $this->admin();
        $this->view('users', ['users' => DB::all('SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders FROM users u ORDER BY u.id DESC LIMIT 200')]);
    }

    public function userUpdate(array $p): void
    {
        $me = $this->admin();
        $id = (int)$p['id'];
        if ($id === (int)$me['id']) { flash('danger', 'You cannot change your own role or status.'); redirect('/admin/users'); }
        $role = str_in('role', 20); $status = str_in('status', 20);
        if (!in_array($role, ['customer', 'staff', 'admin'], true) || !in_array($status, ['active', 'blocked'], true)) { flash('danger', 'Invalid values.'); redirect('/admin/users'); }
        DB::update('users', ['role' => $role, 'status' => $status, 'updated_at' => now()], 'id = ?', [$id]);
        flash('success', 'User updated.');
        redirect('/admin/users');
    }

    public function answerQuestion(): void
    {
        $this->admin();
        $a = str_in('answer', 1000);
        if ($a !== '') DB::update('questions', ['answer' => $a, 'answered_at' => now()], 'id = ?', [(int)input('id', 0)]);
        flash('success', 'Answer posted.');
        redirect(str_starts_with((string)input('back', ''), '/') ? (string)input('back') : '/admin');
    }
}
