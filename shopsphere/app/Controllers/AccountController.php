<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Services\Auth;
use App\Services\Catalog;
use App\Services\OrderService;

final class AccountController
{
    public function dashboard(): void
    {
        $u = require_login();
        $orders = DB::all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5', [$u['id']]);
        $stats = DB::one('SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN order_status <> ? THEN total ELSE 0 END),0) AS spent FROM orders WHERE user_id = ?', ['cancelled', $u['id']]);
        render('account/dashboard', [
            'title' => 'My Account - ' . cfg('app.name'), 'orders' => $orders, 'stats' => $stats,
            'wishCount' => count(Catalog::wishlistIds()), 'recent' => Catalog::recent(6),
        ]);
    }

    public function profileForm(): void
    {
        $u = require_login();
        render('account/profile', ['title' => 'Profile & Settings - ' . cfg('app.name'), 'u' => DB::one('SELECT * FROM users WHERE id = ?', [$u['id']])]);
    }

    public function profileSave(): void
    {
        $u = require_login();
        $name = str_in('name', 120);
        $phone = preg_replace('/\D/', '', str_in('phone', 15)) ?? '';
        if (mb_strlen($name) < 2) { flash('danger', 'Please enter your name.'); redirect('/account/profile'); }
        if (!Auth::validPhone($phone)) { flash('danger', 'Enter a valid 10-digit Indian mobile number.'); redirect('/account/profile'); }
        DB::update('users', ['name' => $name, 'phone' => $phone, 'updated_at' => now()], 'id = ?', [$u['id']]);
        flash('success', 'Profile updated.');
        redirect('/account/profile');
    }

    public function passwordSave(): void
    {
        $u = require_login();
        $row = DB::one('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
        $cur = (string)input('current_password', ''); $new = (string)input('password', '');
        if (!password_verify($cur, $row['password_hash'])) { flash('danger', 'Your current password is incorrect.'); redirect('/account/profile'); }
        if ($new !== (string)input('password2', '')) { flash('danger', 'New passwords do not match.'); redirect('/account/profile'); }
        if ($err = Auth::passwordError($new)) { flash('danger', $err); redirect('/account/profile'); }
        DB::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'updated_at' => now()], 'id = ?', [$u['id']]);
        session_regenerate_id(true);
        flash('success', 'Password changed.');
        redirect('/account/profile');
    }

    // ---- addresses ----------------------------------------------------------------------------
    public function addresses(): void
    {
        $u = require_login();
        $edit = null;
        if ($id = (int)input('edit', 0)) $edit = DB::one('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [$id, $u['id']]);
        render('account/addresses', [
            'title' => 'Saved Addresses - ' . cfg('app.name'),
            'addresses' => DB::all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [$u['id']]), 'edit' => $edit,
        ]);
    }

    public function addressSave(): void
    {
        $u = require_login();
        [$a, $err] = parse_address();
        if ($err) { with_old($_POST); flash('danger', $err); redirect('/account/addresses' . (input('id') ? '?edit=' . (int)input('id') : '')); }
        $default = input('is_default') ? 1 : 0;
        DB::tx(function () use ($u, $a, $default) {
            $id = (int)input('id', 0);
            $isFirst = !DB::val('SELECT id FROM addresses WHERE user_id = ?', [$u['id']]);
            if ($default || $isFirst) { DB::exec('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$u['id']]); $default = 1; }
            if ($id && DB::val('SELECT id FROM addresses WHERE id = ? AND user_id = ?', [$id, $u['id']])) {
                DB::update('addresses', $a + ['is_default' => $default], 'id = ? AND user_id = ?', [$id, $u['id']]);
            } else {
                DB::insert('addresses', $a + ['user_id' => $u['id'], 'is_default' => $default, 'created_at' => now()]);
            }
        });
        clear_old();
        flash('success', 'Address saved.');
        redirect('/account/addresses');
    }

    public function addressDelete(): void
    {
        $u = require_login();
        DB::exec('DELETE FROM addresses WHERE id = ? AND user_id = ?', [(int)input('id', 0), $u['id']]);
        flash('info', 'Address removed.');
        redirect('/account/addresses');
    }

    // ---- orders ------------------------------------------------------------------------------------
    public function orders(): void
    {
        $u = require_login();
        $orders = DB::all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC', [$u['id']]);
        foreach ($orders as &$o) $o['items'] = OrderService::items((int)$o['id']);
        unset($o);
        render('account/orders', ['title' => 'My Orders - ' . cfg('app.name'), 'orders' => $orders]);
    }

    public function cancel(array $p): void
    {
        $o = $this->mine($p['no']);
        $reason = str_in('reason', 200) ?: 'Cancelled by customer';
        if (OrderService::cancel($o, $reason, current_user()['id'] ?? null)) flash('success', 'Your order has been cancelled.' . ($o['payment_status'] === 'paid' ? ' A refund has been initiated and will be processed within 5-7 business days.' : ''));
        else flash('danger', 'This order can no longer be cancelled. If it has shipped, you can request a return after delivery.');
        redirect('/order/' . $o['order_no']);
    }

    public function requestReturn(array $p): void
    {
        $o = $this->mine($p['no']);
        $err = OrderService::requestReturn($o, (int)input('item_id', 0), str_in('type', 15), str_in('reason', 120), str_in('details', 500));
        if ($err) flash('danger', $err); else flash('success', 'Your request has been submitted. We will update you shortly.');
        redirect('/order/' . $o['order_no']);
    }

    private function mine(string $no): array
    {
        $o = OrderService::find($no);
        if (!$o || !OrderService::canAccess($o)) not_found();
        return $o;
    }
}
