<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class OrderService
{
    public const FLOW = ['placed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered'];

    public const TIMELINE = [
        'placed'           => 'Order placed',
        'payment'          => 'Payment confirmed / COD selected',
        'processing'       => 'Order processing',
        'packed'           => 'Packed',
        'shipped'          => 'Shipped',
        'out_for_delivery' => 'Out for delivery',
        'delivered'        => 'Delivered',
    ];

    // ---- access ------------------------------------------------------------------------
    public static function find(string $orderNo): ?array
    {
        return DB::one('SELECT * FROM orders WHERE order_no = ?', [$orderNo]);
    }

    public static function canAccess(array $o): bool
    {
        $u = current_user();
        if ($u && ((int)$o['user_id'] === (int)$u['id'] || in_array($u['role'], ['admin', 'staff'], true))) return true;
        return in_array($o['order_no'], $_SESSION['guest_orders'] ?? [], true);
    }

    public static function grantGuest(string $orderNo): void
    {
        $_SESSION['guest_orders'] = array_values(array_unique([...($_SESSION['guest_orders'] ?? []), $orderNo]));
    }

    public static function items(int $orderId): array
    {
        return DB::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public static function events(int $orderId): array
    {
        return DB::all('SELECT * FROM order_events WHERE order_id = ? ORDER BY created_at, id', [$orderId]);
    }

    private static function event(int $orderId, string $status, ?string $note, bool $simulated = true, ?int $by = null): void
    {
        DB::insert('order_events', ['order_id' => $orderId, 'status' => $status, 'note' => $note, 'simulated' => $simulated ? 1 : 0, 'created_by' => $by, 'created_at' => now()]);
    }

    private static function newOrderNo(): string
    {
        do {
            $no = 'SS' . date('ymd') . random_int(100000, 999999);
        } while (DB::val('SELECT id FROM orders WHERE order_no = ?', [$no]));
        return $no;
    }

    // ---- placing an order ----------------------------------------------------------------
    /**
     * @param array $contact name,email,phone ; $addr full_name,phone,line1,line2,landmark,city,state,pincode
     * @return array the created order row
     * @throws \RuntimeException with a customer-safe message
     */
    public static function place(?array $user, array $contact, array $addr, string $shipMethod, string $payMethod, string $couponCode): array
    {
        if (!in_array($payMethod, ['razorpay', 'cod'], true)) throw new \RuntimeException('Please choose a payment method.');
        if ($payMethod === 'razorpay' && !Razorpay::enabled()) throw new \RuntimeException('Online payments are not configured on this store yet. Please use Cash on Delivery.');
        if (!isset(Delivery::methods()[$shipMethod])) throw new \RuntimeException('Please choose a shipping method.');
        if (!Delivery::validPin($addr['pincode'])) throw new \RuntimeException('Invalid delivery PIN code.');

        $order = DB::tx(function () use ($user, $contact, $addr, $shipMethod, $payMethod, $couponCode) {
            $cartId = CartService::cartId();
            $t = CartService::totals(['shipping_method' => $shipMethod, 'payment_method' => $payMethod, 'coupon' => $couponCode], true, $cartId);
            if (!$t['lines']) throw new \RuntimeException('Your cart is empty.');
            if ($t['issues']) throw new \RuntimeException(implode(' ', $t['issues']) . ' Please review your cart.');
            if (!$user && $t['total'] > (float)cfg('app.guest_checkout_max')) throw new \RuntimeException('Please sign in to place orders above ' . inr(cfg('app.guest_checkout_max')) . '.');
            if ($payMethod === 'cod' && $t['total'] > (float)cfg('shop.cod_max_order')) throw new \RuntimeException('Cash on Delivery is available only for orders up to ' . inr(cfg('shop.cod_max_order')) . '.');

            // Coupon usage is consumed atomically so limited coupons cannot be oversubscribed.
            if ($t['coupon']) {
                $n = DB::exec('UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND (usage_limit IS NULL OR used_count < usage_limit)', [$t['coupon']['id']]);
                if ($n !== 1) throw new \RuntimeException('This coupon is no longer available. Please remove it and try again.');
            }

            $est = Delivery::estimate($addr['pincode'], $shipMethod);
            $online = $payMethod === 'razorpay';
            $orderId = DB::insert('orders', [
                'order_no' => self::newOrderNo(), 'user_id' => $user['id'] ?? null,
                'customer_name' => $contact['name'], 'customer_email' => $contact['email'], 'customer_phone' => $contact['phone'],
                'ship_name' => $addr['full_name'], 'ship_phone' => $addr['phone'], 'ship_line1' => $addr['line1'], 'ship_line2' => $addr['line2'] ?: null,
                'ship_landmark' => $addr['landmark'] ?: null, 'ship_city' => $addr['city'], 'ship_state' => $addr['state'], 'ship_pincode' => $addr['pincode'],
                'shipping_method' => $shipMethod, 'mrp_total' => $t['mrp_total'], 'subtotal' => $t['subtotal'], 'product_discount' => $t['product_discount'],
                'coupon_code' => $t['coupon_code'], 'coupon_discount' => $t['coupon_discount'], 'shipping_fee' => $t['shipping'], 'cod_fee' => $t['cod_fee'],
                'tax_amount' => $t['tax'], 'total' => $t['total'], 'payment_method' => $payMethod,
                'payment_status' => $online ? 'pending' : 'cod_pending', 'order_status' => $online ? 'pending_payment' : 'placed',
                'estimated_delivery' => $est['to'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($t['lines'] as $l) {
                DB::insert('order_items', [
                    'order_id' => $orderId, 'product_id' => $l['product_id'], 'sku' => $l['sku'], 'name' => $l['name'], 'brand' => $l['brand'],
                    'image_url' => $l['image'], 'options' => json_encode($l['options_arr'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'qty' => $l['qty'], 'mrp' => $l['unit_mrp'], 'unit_price' => $l['unit'], 'gst_rate' => $l['gst_rate'],
                    'tax_amount' => $l['tax'], 'line_total' => $l['line_total'],
                ]);
                // Conditional decrement guarantees stock never goes negative even under concurrency.
                $ok = DB::exec('UPDATE products SET stock = stock - ?, sold_count = sold_count + ? WHERE id = ? AND stock >= ?', [$l['qty'], $l['qty'], $l['product_id'], $l['qty']]);
                if ($ok !== 1) throw new \RuntimeException('"' . $l['name'] . '" just went out of stock. Please review your cart.');
            }
            self::event($orderId, 'placed', $online ? 'Order created, awaiting payment' : 'Order placed with Cash on Delivery', true, $user['id'] ?? null);
            if (!$online) self::event($orderId, 'payment', 'Cash on Delivery selected', true, null);
            CartService::clear($cartId);
            return DB::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        });

        self::grantGuest($order['order_no']);
        if ($order['payment_method'] === 'cod') self::sendConfirmation($order);
        return $order;
    }

    /** Create (or reuse) the Razorpay order for a pending online order. */
    public static function ensureRazorpayOrder(array $order): array
    {
        if ($order['razorpay_order_id']) return ['ok' => true, 'razorpay_order_id' => $order['razorpay_order_id']];
        $r = Razorpay::createOrder((float)$order['total'], $order['order_no'], ['order_no' => $order['order_no']]);
        if (!$r['ok'] || empty($r['data']['id'])) return ['ok' => false, 'error' => $r['error'] ?: 'Could not start the payment.'];
        DB::update('orders', ['razorpay_order_id' => $r['data']['id'], 'updated_at' => now()], 'id = ?', [$order['id']]);
        return ['ok' => true, 'razorpay_order_id' => $r['data']['id']];
    }

    // ---- payment confirmation (idempotent) -----------------------------------------------------
    /**
     * Mark an online order as paid. Safe to call repeatedly (browser verify + webhook).
     * @return string 'paid' | 'already_paid' | 'late_refund' | 'mismatch' | 'not_found'
     */
    public static function markPaid(string $razorpayOrderId, string $paymentId, float $amountInr, string $source): string
    {
        $result = DB::tx(function () use ($razorpayOrderId, $paymentId, $amountInr, $source) {
            $o = DB::one('SELECT * FROM orders WHERE razorpay_order_id = ?' . DB::forUpdate(), [$razorpayOrderId]);
            if (!$o) return ['not_found', null];
            if (abs((float)$o['total'] - $amountInr) > 0.009) return ['mismatch', $o];
            if ($o['payment_status'] === 'paid' || in_array($o['payment_status'], ['refund_pending', 'refunded'], true)) return ['already_paid', $o];

            if ($o['order_status'] === 'cancelled') {
                // Money arrived for an order that was cancelled/expired (stock already released) -> queue a refund.
                DB::update('orders', ['payment_status' => 'refund_pending', 'razorpay_payment_id' => $paymentId, 'paid_at' => now(), 'updated_at' => now()], 'id = ?', [$o['id']]);
                DB::insert('refunds', ['order_id' => $o['id'], 'amount' => $o['total'], 'status' => 'initiated', 'method' => 'razorpay', 'note' => 'Payment received after the order was cancelled/expired', 'created_at' => now()]);
                self::event((int)$o['id'], 'cancelled', 'Late payment received for a cancelled order - refund queued', false);
                return ['late_refund', $o];
            }
            DB::update('orders', ['payment_status' => 'paid', 'order_status' => 'placed', 'razorpay_payment_id' => $paymentId, 'paid_at' => now(), 'updated_at' => now()], 'id = ?', [$o['id']]);
            self::event((int)$o['id'], 'payment', 'Payment confirmed (' . $source . ')', false);
            return ['paid', DB::one('SELECT * FROM orders WHERE id = ?', [$o['id']])];
        });
        [$state, $order] = $result;
        if ($state === 'paid' && $order) self::sendConfirmation($order);
        return $state;
    }

    public static function markPaymentFailed(string $razorpayOrderId, string $reason): void
    {
        $o = DB::one('SELECT id, payment_status FROM orders WHERE razorpay_order_id = ?', [$razorpayOrderId]);
        if ($o && $o['payment_status'] === 'pending') {
            self::event((int)$o['id'], 'payment_failed', 'Payment attempt failed: ' . mb_substr($reason, 0, 150), false);
        }
    }

    // ---- cancellation ---------------------------------------------------------------------
    public static function canCancel(array $o): bool
    {
        return in_array($o['order_status'], cfg('shop.cancellable_status', []), true);
    }

    public static function cancel(array $o, string $reason, ?int $byUser = null, bool $force = false): bool
    {
        return (bool)DB::tx(function () use ($o, $reason, $byUser, $force) {
            $o = DB::one('SELECT * FROM orders WHERE id = ?' . DB::forUpdate(), [$o['id']]);
            if (!$o || $o['order_status'] === 'cancelled') return false;
            if (!$force && !self::canCancel($o)) return false;
            if ($force && in_array($o['order_status'], ['delivered'], true)) return false;

            $wasPaid = $o['payment_status'] === 'paid';
            if (!(int)$o['stock_released']) {
                foreach (self::items((int)$o['id']) as $it) {
                    if ($it['product_id']) DB::exec('UPDATE products SET stock = stock + ?, sold_count = CASE WHEN sold_count >= ? THEN sold_count - ? ELSE 0 END WHERE id = ?', [$it['qty'], $it['qty'], $it['qty'], $it['product_id']]);
                }
            }
            if ($o['coupon_code']) DB::exec('UPDATE coupons SET used_count = CASE WHEN used_count > 0 THEN used_count - 1 ELSE 0 END WHERE code = ?', [$o['coupon_code']]);

            DB::update('orders', [
                'order_status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 250), 'stock_released' => 1,
                'payment_status' => $wasPaid ? 'refund_pending' : 'cancelled', 'updated_at' => now(),
            ], 'id = ?', [$o['id']]);
            if ($wasPaid) {
                DB::insert('refunds', ['order_id' => $o['id'], 'amount' => $o['total'], 'status' => 'initiated', 'method' => $o['payment_method'] === 'razorpay' ? 'razorpay' : 'manual', 'note' => 'Order cancelled', 'created_at' => now()]);
            }
            self::event((int)$o['id'], 'cancelled', 'Order cancelled: ' . $reason . ($wasPaid ? ' (refund initiated)' : ''), true, $byUser);
            return true;
        });
    }

    /** Expire unpaid online orders (called lazily and from bin/expire_pending.php). */
    public static function expirePending(): int
    {
        $cut = date('Y-m-d H:i:s', time() - 60 * (int)cfg('shop.pending_payment_minutes', 30));
        $n = 0;
        foreach (DB::all("SELECT * FROM orders WHERE order_status = 'pending_payment' AND payment_status = 'pending' AND created_at < ?", [$cut]) as $o) {
            if (self::cancel($o, 'Payment not completed in time', null, true)) $n++;
        }
        return $n;
    }

    // ---- admin status updates -------------------------------------------------------------------
    public static function updateStatus(array $o, string $new, ?string $note, int $adminId): ?string
    {
        if ($o['order_status'] === 'cancelled') return 'This order is cancelled.';
        if ($o['order_status'] === 'pending_payment') return 'This order is waiting for payment.';
        $cur = array_search($o['order_status'], self::FLOW, true);
        $to = array_search($new, self::FLOW, true);
        if ($to === false || $cur === false) return 'Invalid status.';
        if ($to <= $cur) return 'Status can only move forward.';
        $upd = ['order_status' => $new, 'updated_at' => now()];
        if ($new === 'delivered') {
            $upd['delivered_at'] = now();
            if ($o['payment_method'] === 'cod' && $o['payment_status'] !== 'paid') { $upd['payment_status'] = 'paid'; $upd['paid_at'] = now(); }
        }
        DB::update('orders', $upd, 'id = ?', [$o['id']]);
        self::event((int)$o['id'], $new, $note ?: null, true, $adminId);
        if (in_array($new, ['shipped', 'delivered'], true)) {
            Mailer::send($o['customer_email'], 'Your order ' . $o['order_no'] . ' is ' . status_label($new), Mailer::wrap(
                'Order ' . status_label($new),
                '<p>Hi ' . e($o['customer_name']) . ', your order <strong>' . e($o['order_no']) . '</strong> is now <strong>' . e(status_label($new)) . '</strong>.</p><p><a href="' . e(abs_url('order/' . $o['order_no'])) . '">Track your order</a></p>'
            ));
        }
        return null;
    }

    // ---- timeline for the tracking page ------------------------------------------------------------
    public static function timeline(array $o): array
    {
        $events = self::events((int)$o['id']);
        $when = [];
        foreach ($events as $e) $when[$e['status']] ??= $e;
        $idx = array_search($o['order_status'], self::FLOW, true);
        $paid = $o['payment_status'] === 'paid' || $o['payment_method'] === 'cod';
        $steps = [];
        foreach (self::TIMELINE as $key => $label) {
            if ($key === 'payment') {
                $done = $paid;
                $label = $o['payment_method'] === 'cod' ? 'Cash on Delivery selected' : 'Payment confirmed';
            } elseif ($key === 'placed') {
                $done = true;
            } else {
                $pos = array_search($key, self::FLOW, true);
                $done = $idx !== false && $pos <= $idx;
            }
            if ($o['order_status'] === 'cancelled' && !isset($when[$key]) && $key !== 'placed') $done = false;
            $steps[] = ['key' => $key, 'label' => $label, 'done' => $done, 'at' => $when[$key]['created_at'] ?? null, 'note' => $when[$key]['note'] ?? null, 'simulated' => (int)($when[$key]['simulated'] ?? 1)];
        }
        return $steps;
    }

    // ---- returns & refunds -----------------------------------------------------------------------------
    public static function returnWindowOpen(array $o): bool
    {
        if ($o['order_status'] !== 'delivered' || !$o['delivered_at']) return false;
        return strtotime((string)$o['delivered_at']) + 86400 * (int)cfg('shop.return_window_days') >= time();
    }

    public const RETURN_REASONS = ['Damaged / defective item', 'Wrong item delivered', 'Item not as described', 'Size / fit issue', 'No longer needed'];

    public static function requestReturn(array $o, int $itemId, string $type, string $reason, string $details): ?string
    {
        if (!in_array($type, ['return', 'replacement'], true)) return 'Invalid request type.';
        if (!in_array($reason, self::RETURN_REASONS, true)) return 'Please select a reason.';
        if (!self::returnWindowOpen($o)) return 'The ' . cfg('shop.return_window_days') . '-day return window for this order is closed or the order has not been delivered.';
        $it = DB::one('SELECT oi.id, oi.sku, c.slug AS cat FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id WHERE oi.id = ? AND oi.order_id = ?', [$itemId, $o['id']]);
        if (!$it) return 'Item not found in this order.';
        if (($it['cat'] ?? '') === 'grocery' && !in_array($reason, ['Damaged / defective item', 'Wrong item delivered'], true)) return 'Grocery items can only be returned if damaged or wrong.';
        if (DB::val("SELECT id FROM returns WHERE order_item_id = ? AND status IN ('requested','approved','completed')", [$itemId])) return 'A request for this item already exists.';
        DB::insert('returns', ['order_id' => $o['id'], 'order_item_id' => $itemId, 'type' => $type, 'reason' => $reason, 'details' => mb_substr($details, 0, 500), 'status' => 'requested', 'created_at' => now()]);
        self::event((int)$o['id'], 'return_requested', ucfirst($type) . ' requested: ' . $reason, false);
        return null;
    }

    public static function handleReturn(int $returnId, string $action, string $note, int $adminId): ?string
    {
        $r = DB::one('SELECT r.*, oi.line_total FROM returns r JOIN order_items oi ON oi.id = r.order_item_id WHERE r.id = ?', [$returnId]);
        if (!$r) return 'Request not found.';
        $map = ['approve' => 'approved', 'reject' => 'rejected', 'complete' => 'completed'];
        if (!isset($map[$action])) return 'Invalid action.';
        if ($action === 'complete' && $r['status'] !== 'approved') return 'Only approved requests can be completed.';
        if ($action === 'approve' && $r['status'] !== 'requested') return 'Only new requests can be approved.';
        if ($action === 'reject' && $r['status'] !== 'requested') return 'Only new requests can be rejected.';
        DB::tx(function () use ($r, $action, $map, $note) {
            DB::update('returns', ['status' => $map[$action], 'admin_note' => mb_substr($note, 0, 250), 'updated_at' => now()], 'id = ?', [$r['id']]);
            if ($action === 'complete' && $r['type'] === 'return') {
                $o = DB::one('SELECT * FROM orders WHERE id = ?', [$r['order_id']]);
                DB::insert('refunds', ['order_id' => $r['order_id'], 'return_id' => $r['id'], 'amount' => $r['line_total'], 'status' => 'initiated',
                    'method' => ($o['payment_method'] === 'razorpay' && $o['razorpay_payment_id']) ? 'razorpay' : 'manual', 'note' => 'Return completed', 'created_at' => now()]);
                DB::update('orders', ['payment_status' => 'refund_pending', 'updated_at' => now()], 'id = ?', [$r['order_id']]);
            }
            self::event((int)$r['order_id'], 'return_' . $map[$action], ucfirst($r['type']) . ' ' . $map[$action] . ($note ? ': ' . $note : ''), false, $adminId);
        });
        return null;
    }

    public static function processRefund(int $refundId, int $adminId): ?string
    {
        $rf = DB::one('SELECT * FROM refunds WHERE id = ?', [$refundId]);
        if (!$rf || $rf['status'] === 'processed') return 'Refund not found or already processed.';
        $o = DB::one('SELECT * FROM orders WHERE id = ?', [$rf['order_id']]);
        $rzpId = null; $method = 'manual'; $note = 'Marked as refunded manually';
        if ($rf['method'] === 'razorpay' && $o['razorpay_payment_id'] && Razorpay::enabled()) {
            $r = Razorpay::refund($o['razorpay_payment_id'], (float)$rf['amount'], ['order_no' => $o['order_no']]);
            if (!$r['ok']) {
                DB::update('refunds', ['status' => 'failed', 'note' => mb_substr('Razorpay: ' . $r['error'], 0, 250), 'updated_at' => now()], 'id = ?', [$refundId]);
                return 'Razorpay refund failed: ' . $r['error'];
            }
            $rzpId = $r['data']['id'] ?? null; $method = 'razorpay'; $note = 'Refunded via Razorpay';
        }
        DB::update('refunds', ['status' => 'processed', 'method' => $method, 'razorpay_refund_id' => $rzpId, 'note' => $note, 'updated_at' => now()], 'id = ?', [$refundId]);
        $refunded = (float)DB::val("SELECT COALESCE(SUM(amount),0) FROM refunds WHERE order_id = ? AND status = 'processed'", [$o['id']]);
        $pending = (int)DB::val("SELECT COUNT(*) FROM refunds WHERE order_id = ? AND status IN ('initiated','failed')", [$o['id']]);
        if ($refunded >= (float)$o['total'] - 0.009) DB::update('orders', ['payment_status' => 'refunded', 'updated_at' => now()], 'id = ?', [$o['id']]);
        elseif ($pending === 0) DB::update('orders', ['payment_status' => 'paid', 'updated_at' => now()], 'id = ?', [$o['id']]);
        self::event((int)$o['id'], 'refund_processed', 'Refund of ' . inr($rf['amount']) . ' processed', false, $adminId);
        Mailer::send($o['customer_email'], 'Refund processed for ' . $o['order_no'], Mailer::wrap('Refund processed', '<p>We have processed a refund of <strong>' . e(inr($rf['amount'])) . '</strong> for order ' . e($o['order_no']) . '. It may take 5-7 business days to reflect in your account.</p>'));
        return null;
    }

    // ---- emails -----------------------------------------------------------------------------------------
    public static function sendConfirmation(array $o): void
    {
        $rows = '';
        foreach (self::items((int)$o['id']) as $it) {
            $opts = json_dec($it['options']);
            $rows .= '<tr><td style="padding:6px 0">' . e($it['name']) . ($opts ? '<br><small style="color:#6b7280">' . e(implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($opts), $opts))) . '</small>' : '')
                . '</td><td style="text-align:center">' . (int)$it['qty'] . '</td><td style="text-align:right">' . e(inr($it['unit_price'] * $it['qty'])) . '</td></tr>';
        }
        $html = '<p>Hi ' . e($o['customer_name']) . ', thank you for your order!</p>'
            . '<p><strong>Order no:</strong> ' . e($o['order_no']) . '<br><strong>Payment:</strong> ' . ($o['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Paid online') . '<br><strong>Estimated delivery:</strong> ' . e($o['estimated_delivery'] ? date('D, j M Y', strtotime($o['estimated_delivery'])) : '-') . '</p>'
            . '<table width="100%" style="border-collapse:collapse;border-top:1px solid #e5e7eb"><tr style="text-align:left"><th>Item</th><th>Qty</th><th style="text-align:right">Amount</th></tr>' . $rows . '</table>'
            . '<p style="text-align:right"><strong>Total payable: ' . e(inr($o['total'])) . '</strong><br><small>(includes GST of ' . e(inr($o['tax_amount'])) . ')</small></p>'
            . '<p><strong>Ship to:</strong><br>' . e($o['ship_name']) . ', ' . e($o['ship_line1']) . ', ' . e($o['ship_city']) . ' - ' . e($o['ship_pincode']) . '</p>'
            . '<p><a href="' . e(abs_url('order/' . $o['order_no'])) . '">View order &amp; tracking</a> · <a href="' . e(abs_url('invoice/' . $o['order_no'])) . '">Invoice</a></p>';
        Mailer::send($o['customer_email'], 'Order confirmed: ' . $o['order_no'], Mailer::wrap('Order confirmed', $html));
    }
}
