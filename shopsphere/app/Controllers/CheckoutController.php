<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Services\Auth;
use App\Services\CartService;
use App\Services\Delivery;
use App\Services\OrderService;
use App\Services\Razorpay;

final class CheckoutController
{
    public function page(): void
    {
        $u = current_user();
        if (!$u && !cfg('app.allow_guest_checkout')) require_login();
        foreach (CartService::reconcile() as $m) flash('warning', $m);
        $t = CartService::totals();
        if (!$t['lines']) { flash('info', 'Your cart is empty.'); redirect('/cart'); }
        if ($u && cfg('app.require_email_verification') && !$u['email_verified_at']) {
            flash('warning', 'Please verify your email address before checking out.');
            redirect('/account');
        }
        render('checkout/index', [
            'title' => 'Checkout - ' . cfg('app.name'), 't' => $t, 'user' => $u,
            'addresses' => $u ? DB::all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [$u['id']]) : [],
            'razorpay' => Razorpay::enabled(), 'pin' => Delivery::currentPin(),
            'guestMax' => (float)cfg('app.guest_checkout_max'),
        ]);
    }

    /** Live, server-computed price breakdown for the checkout sidebar. */
    public function quote(): void
    {
        $ship = str_in('shipping_method', 20) ?: 'standard';
        $pay = str_in('payment_method', 20);
        $t = CartService::totals(['shipping_method' => $ship, 'payment_method' => $pay]);
        $pin = preg_replace('/\D/', '', str_in('pin', 10)) ?? '';
        $est = [];
        if (Delivery::validPin($pin)) foreach (array_keys(Delivery::methods()) as $m) $est[$m] = Delivery::estimate($pin, $m)['text'];
        json_out(['ok' => true, 'issues' => $t['issues'], 'estimates' => $est, 'totals' => [
            'count' => $t['count'], 'mrp_total' => inr($t['mrp_total']), 'product_discount' => inr($t['product_discount']), 'subtotal' => inr($t['subtotal']),
            'coupon' => $t['coupon_code'], 'coupon_discount' => inr($t['coupon_discount']), 'shipping' => $t['shipping'] > 0 ? inr($t['shipping']) : 'FREE',
            'cod_fee' => $t['cod_fee'] > 0 ? inr($t['cod_fee']) : null, 'tax' => inr($t['tax']), 'total' => inr($t['total']), 'savings' => inr($t['total_savings']),
        ]]);
    }

    public function place(): void
    {
        $u = current_user();
        $err = null;
        if (!$u && !cfg('app.allow_guest_checkout')) json_out(['ok' => false, 'error' => 'Please sign in to place your order.', 'login' => true], 401);
        if ($u && cfg('app.require_email_verification') && !$u['email_verified_at']) json_out(['ok' => false, 'error' => 'Please verify your email before placing an order.'], 403);

        // Contact details
        if ($u) {
            $contact = ['name' => $u['name'], 'email' => $u['email'], 'phone' => $u['phone'] ?: ''];
        } else {
            $contact = ['name' => str_in('contact_name', 120), 'email' => strtolower(str_in('contact_email', 190)), 'phone' => preg_replace('/\D/', '', str_in('contact_phone', 15)) ?? ''];
            if (mb_strlen($contact['name']) < 2) $err = 'Please enter your name.';
            elseif (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) $err = 'Please enter a valid email address.';
            elseif (!Auth::validPhone($contact['phone'])) $err = 'Enter a valid 10-digit mobile number.';
        }
        if ($err) json_out(['ok' => false, 'error' => $err], 422);

        // Address: saved one (must belong to the user) or a new one
        $addr = null;
        $addressId = (int)input('address_id', 0);
        if ($u && $addressId > 0) {
            $row = DB::one('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [$addressId, $u['id']]);
            if (!$row) json_out(['ok' => false, 'error' => 'Selected address not found.'], 422);
            $addr = array_intersect_key($row, array_flip(['full_name', 'phone', 'line1', 'line2', 'landmark', 'city', 'state', 'pincode']));
        } else {
            [$addr, $err] = parse_address('addr_');
            if ($err) json_out(['ok' => false, 'error' => $err], 422);
            if ($u && input('save_address')) {
                $isFirst = !DB::val('SELECT id FROM addresses WHERE user_id = ?', [$u['id']]);
                DB::insert('addresses', $addr + ['user_id' => $u['id'], 'is_default' => $isFirst ? 1 : 0, 'created_at' => now()]);
            }
        }

        $ship = str_in('shipping_method', 20) ?: 'standard';
        $pay = str_in('payment_method', 20);
        $coupon = (string)($_SESSION['coupon'] ?? '');
        try {
            $order = OrderService::place($u, $contact, $addr, $ship, $pay, $coupon);
        } catch (\RuntimeException $e) {
            json_out(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        if ($order['payment_method'] === 'cod') {
            json_out(['ok' => true, 'redirect' => url('order/' . $order['order_no'] . '/confirmation')]);
        }
        $r = $this->razorpayParams($order);
        if (!$r['ok']) {
            OrderService::cancel($order, 'Payment gateway unavailable', null, true);
            json_out(['ok' => false, 'error' => 'We could not start the payment: ' . $r['error'] . ' Please try again or choose Cash on Delivery.'], 502);
        }
        json_out(['ok' => true, 'order_no' => $order['order_no'], 'razorpay' => $r['params'], 'redirect' => url('order/' . $order['order_no'] . '/confirmation'), 'failed_redirect' => url('order/' . $order['order_no'])]);
    }

    private function razorpayParams(array $order): array
    {
        $rz = OrderService::ensureRazorpayOrder($order);
        if (!$rz['ok']) return ['ok' => false, 'error' => $rz['error']];
        return ['ok' => true, 'params' => [
            'key' => Razorpay::keyId(), 'order_id' => $rz['razorpay_order_id'], 'amount' => (int)round((float)$order['total'] * 100), 'currency' => 'INR',
            'name' => cfg('app.name'), 'description' => 'Order ' . $order['order_no'],
            'prefill' => ['name' => $order['customer_name'], 'email' => $order['customer_email'], 'contact' => $order['customer_phone']],
            'theme' => ['color' => '#f59e0b'],
        ]];
    }

    /** Retry payment for an unpaid online order. */
    public function payStart(): void
    {
        $o = OrderService::find(str_in('order_no', 30));
        if (!$o || !OrderService::canAccess($o)) json_out(['ok' => false, 'error' => 'Order not found.'], 404);
        if ($o['payment_method'] !== 'razorpay' || $o['order_status'] !== 'pending_payment') json_out(['ok' => false, 'error' => 'This order does not need a payment.'], 422);
        $r = $this->razorpayParams($o);
        if (!$r['ok']) json_out(['ok' => false, 'error' => $r['error']], 502);
        json_out(['ok' => true, 'razorpay' => $r['params'], 'redirect' => url('order/' . $o['order_no'] . '/confirmation')]);
    }

    /** Browser callback: verify the signature on the server, confirm with Razorpay, then mark paid. */
    public function payVerify(): void
    {
        $oid = str_in('razorpay_order_id', 60); $pid = str_in('razorpay_payment_id', 60); $sig = str_in('razorpay_signature', 200);
        $o = DB::one('SELECT * FROM orders WHERE razorpay_order_id = ?', [$oid]);
        if (!$o || !OrderService::canAccess($o)) json_out(['ok' => false, 'error' => 'Order not found.'], 404);
        if (!Razorpay::verifyPaymentSignature($oid, $pid, $sig)) {
            OrderService::markPaymentFailed($oid, 'Signature verification failed');
            json_out(['ok' => false, 'error' => 'Payment verification failed. If money was debited it will be refunded automatically.'], 400);
        }
        // Confirm directly with Razorpay (amount, order, status) - never trust the browser alone.
        $pay = Razorpay::fetchPayment($pid);
        if ($pay['ok']) {
            $d = $pay['data'];
            if (($d['order_id'] ?? '') !== $oid) json_out(['ok' => false, 'error' => 'Payment does not belong to this order.'], 400);
            if (($d['status'] ?? '') === 'authorized') {
                $cap = Razorpay::capture($pid, (float)$o['total']);
                if ($cap['ok']) $d = $cap['data'];
            }
            if (!in_array($d['status'] ?? '', ['captured'], true)) {
                json_out(['ok' => true, 'pending' => true, 'redirect' => url('order/' . $o['order_no'] . '/confirmation'), 'message' => 'Payment is being confirmed.']);
            }
            $amount = ((int)($d['amount'] ?? 0)) / 100;
        } else {
            // Gateway lookup unavailable: the HMAC signature already proves authenticity; use our stored total.
            log_error('Razorpay fetch failed during verify: ' . $pay['error']);
            $amount = (float)$o['total'];
        }
        $state = OrderService::markPaid($oid, $pid, (float)$amount, 'browser verify');
        if ($state === 'mismatch') json_out(['ok' => false, 'error' => 'Paid amount does not match the order. Our team has been alerted.'], 409);
        json_out(['ok' => true, 'redirect' => url('order/' . $o['order_no'] . '/confirmation')]);
    }

    public function payFailed(): void
    {
        $oid = str_in('razorpay_order_id', 60);
        $o = DB::one('SELECT * FROM orders WHERE razorpay_order_id = ?', [$oid]);
        if ($o && OrderService::canAccess($o)) OrderService::markPaymentFailed($oid, str_in('reason', 200) ?: 'Payment failed or cancelled');
        json_out(['ok' => true]);
    }

    /** Razorpay webhooks: signature-verified and idempotent via the unique event id. */
    public function webhook(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
        if (!Razorpay::verifyWebhookSignature($raw, $sig)) json_out(['ok' => false, 'error' => 'Bad signature'], 400);

        $eventId = $_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ?? '';
        $payload = json_decode($raw, true) ?: [];
        $type = (string)($payload['event'] ?? '');
        if ($eventId === '') $eventId = hash('sha256', $raw);

        try {
            DB::insert('webhook_events', ['event_id' => $eventId, 'event_type' => $type, 'payload' => mb_substr($raw, 0, 60000), 'created_at' => now()]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') json_out(['ok' => true, 'duplicate' => true]);   // already handled
            throw $e;
        }

        try {
            $pay = $payload['payload']['payment']['entity'] ?? null;
            if (in_array($type, ['payment.captured', 'order.paid'], true) && $pay) {
                OrderService::markPaid((string)$pay['order_id'], (string)$pay['id'], ((int)$pay['amount']) / 100, 'webhook');
            } elseif ($type === 'payment.failed' && $pay) {
                OrderService::markPaymentFailed((string)($pay['order_id'] ?? ''), (string)($pay['error_description'] ?? 'Payment failed'));
            } elseif ($type === 'refund.processed') {
                $rf = $payload['payload']['refund']['entity'] ?? null;
                if ($rf) DB::exec("UPDATE refunds SET status = 'processed', updated_at = ? WHERE razorpay_refund_id = ? AND status <> 'processed'", [now(), $rf['id']]);
            }
        } catch (\Throwable $e) {
            DB::exec('DELETE FROM webhook_events WHERE event_id = ?', [$eventId]);   // let Razorpay retry
            log_error('Webhook failed: ' . $e->getMessage());
            json_out(['ok' => false], 500);
        }
        json_out(['ok' => true]);
    }

    // ---- order pages ----------------------------------------------------------------------------
    private function load(string $no): array
    {
        $o = OrderService::find($no);
        if (!$o || !OrderService::canAccess($o)) not_found();
        return $o;
    }

    public function confirmation(array $p): void
    {
        $o = $this->load($p['no']);
        render('checkout/confirmation', ['title' => 'Order ' . $o['order_no'] . ' - ' . cfg('app.name'), 'o' => $o, 'items' => OrderService::items((int)$o['id'])]);
    }

    public function order(array $p): void
    {
        $o = $this->load($p['no']);
        $items = OrderService::items((int)$o['id']);
        $returns = DB::all('SELECT * FROM returns WHERE order_id = ? ORDER BY id DESC', [$o['id']]);
        $refunds = DB::all('SELECT * FROM refunds WHERE order_id = ? ORDER BY id DESC', [$o['id']]);
        render('checkout/order', [
            'title' => 'Order ' . $o['order_no'] . ' - ' . cfg('app.name'), 'o' => $o, 'items' => $items, 'steps' => OrderService::timeline($o),
            'events' => OrderService::events((int)$o['id']), 'returns' => $returns, 'refunds' => $refunds,
            'canCancel' => OrderService::canCancel($o), 'returnOpen' => OrderService::returnWindowOpen($o),
            'razorpay' => Razorpay::enabled(),
        ]);
    }

    public function invoice(array $p): void
    {
        $o = $this->load($p['no']);
        if ($o['order_status'] === 'pending_payment' || $o['order_status'] === 'cancelled') { flash('info', 'An invoice is available once the order is confirmed.'); redirect('/order/' . $o['order_no']); }
        render('checkout/invoice', ['o' => $o, 'items' => OrderService::items((int)$o['id']), 'title' => 'Invoice ' . $o['order_no']], '');
    }

    public function trackForm(): void
    {
        render('checkout/track', ['title' => 'Track your order - ' . cfg('app.name')]);
    }

    public function track(): void
    {
        $no = strtoupper(str_in('order_no', 30)); $email = strtolower(str_in('email', 190));
        $since = date('Y-m-d H:i:s', time() - 900);
        if ((int)DB::val("SELECT COUNT(*) FROM login_attempts WHERE email = 'track-lookup' AND ip = ? AND created_at > ?", [client_ip(), $since]) >= 10) {
            flash('danger', 'Too many lookups. Please try again in a few minutes.');
            redirect('/track');
        }
        $o = OrderService::find($no);
        // Same message for "no such order" and "wrong email" to avoid leaking which orders exist.
        if (!$o || strtolower($o['customer_email']) !== $email) {
            DB::insert('login_attempts', ['email' => 'track-lookup', 'ip' => client_ip(), 'created_at' => now()]);
            flash('danger', 'We could not find an order matching those details.');
            redirect('/track');
        }
        OrderService::grantGuest($o['order_no']);
        redirect('/order/' . $o['order_no']);
    }
}
