<?php $cancelled = $o['order_status'] === 'cancelled'; ?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li><?php if (current_user()): ?><li class="breadcrumb-item"><a href="<?= e(url('account/orders')) ?>">My orders</a></li><?php endif; ?><li class="breadcrumb-item active"><?= e($o['order_no']) ?></li></ol></nav>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h4 fw-bold mb-1">Order <?= e($o['order_no']) ?></h1><div class="text-muted small">Placed on <?= e(date('j M Y, g:i A', strtotime($o['created_at']))) ?></div></div>
<div><?= status_badge($o['order_status']) ?> <?= status_badge($o['payment_status']) ?></div></div>

<div class="row g-4"><div class="col-lg-8">
<section class="bg-white rounded-3 shadow-sm p-3 mb-3"><h2 class="h6 fw-bold">Order tracking</h2>
<?php if ($cancelled): ?><div class="alert alert-danger mb-0">This order was cancelled<?= $o['cancel_reason'] ? ': ' . e($o['cancel_reason']) : '' ?>.</div>
<?php else: ?>
<ol class="timeline">
<?php foreach ($steps as $s): ?><li class="<?= $s['done'] ? 'done' : '' ?>"><span class="dot"><?= $s['done'] ? '<i class="bi bi-check"></i>' : '' ?></span><div><strong><?= e($s['label']) ?></strong><?= $s['at'] ? '<div class="small text-muted">' . e(date('j M, g:i A', strtotime($s['at']))) . '</div>' : '' ?><?= $s['note'] && $s['done'] ? '<div class="small">' . e($s['note']) . '</div>' : '' ?></div></li><?php endforeach; ?>
</ol>
<div class="small text-muted">Tracking updates are entered by the store team (<strong>simulated tracking</strong>) — this demo is not connected to a real courier API.<?= $o['estimated_delivery'] && $o['order_status'] !== 'delivered' ? ' Estimated delivery: <strong>' . e(date('D, j M Y', strtotime($o['estimated_delivery']))) . '</strong>.' : '' ?></div>
<?php endif; ?></section>

<?php if ($o['order_status'] === 'pending_payment' && $o['payment_method'] === 'razorpay'): ?>
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">Payment for this order is not complete. Unpaid orders are released after <?= (int)cfg('shop.pending_payment_minutes') ?> minutes.<button class="btn btn-warning" id="retryPay" data-order="<?= e($o['order_no']) ?>">Pay now</button></div>
<?php endif; ?>

<section class="bg-white rounded-3 shadow-sm p-3 mb-3"><h2 class="h6 fw-bold">Items</h2>
<?php foreach ($items as $it): $opts = json_dec($it['options']); ?>
<div class="d-flex gap-3 mb-3"><img src="<?= e(img_url($it['image_url'])) ?>" width="72" height="72" class="rounded" alt="" data-fallback>
<div class="flex-grow-1"><?php if ($it['product_id']): ?><a href="<?= e(url('search?q=' . urlencode($it['sku']))) ?>"><?= e($it['name']) ?></a><?php else: ?><?= e($it['name']) ?><?php endif; ?>
<div class="small text-muted"><?= e($it['brand']) ?><?php foreach ($opts as $k => $v): ?> · <?= e($k) ?>: <?= e($v) ?><?php endforeach; ?> · Qty <?= (int)$it['qty'] ?></div>
<?php if ($returnOpen): ?><button class="btn btn-sm btn-outline-secondary mt-1" data-bs-toggle="modal" data-bs-target="#retModal" data-item="<?= (int)$it['id'] ?>" data-name="<?= e($it['name']) ?>">Return / replace</button><?php endif; ?></div>
<strong><?= e(inr($it['unit_price'] * $it['qty'])) ?></strong></div><?php endforeach; ?></section>

<?php if ($returns): ?><section class="bg-white rounded-3 shadow-sm p-3 mb-3"><h2 class="h6 fw-bold">Return &amp; replacement requests</h2>
<?php foreach ($returns as $r): ?><div class="small mb-2"><?= e(ucfirst($r['type'])) ?> — <?= e($r['reason']) ?> <?= status_badge($r['status']) ?><?= $r['admin_note'] ? '<div class="text-muted">Store note: ' . e($r['admin_note']) . '</div>' : '' ?></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($refunds): ?><section class="bg-white rounded-3 shadow-sm p-3 mb-3"><h2 class="h6 fw-bold">Refunds</h2>
<?php foreach ($refunds as $r): ?><div class="small mb-1"><?= e(inr($r['amount'])) ?> — <?= status_badge($r['status']) ?> <span class="text-muted">(<?= e($r['note']) ?>)</span></div><?php endforeach; ?></section><?php endif; ?>
</div>

<div class="col-lg-4">
<div class="summary-card mb-3"><h2 class="h6 fw-bold text-uppercase text-muted">Payment summary</h2><dl class="summary-rows">
<div><dt>Items (M.R.P.)</dt><dd><?= e(inr($o['mrp_total'])) ?></dd></div><div class="text-success"><dt>Discount</dt><dd>− <?= e(inr($o['product_discount'])) ?></dd></div>
<?php if ($o['coupon_discount'] > 0): ?><div class="text-success"><dt>Coupon <?= e($o['coupon_code']) ?></dt><dd>− <?= e(inr($o['coupon_discount'])) ?></dd></div><?php endif; ?>
<div><dt>Delivery</dt><dd><?= $o['shipping_fee'] > 0 ? e(inr($o['shipping_fee'])) : 'FREE' ?></dd></div>
<?php if ($o['cod_fee'] > 0): ?><div><dt>COD fee</dt><dd><?= e(inr($o['cod_fee'])) ?></dd></div><?php endif; ?>
<div class="total"><dt>Total</dt><dd><?= e(inr($o['total'])) ?></dd></div></dl>
<div class="small text-muted">Includes GST <?= e(inr($o['tax_amount'])) ?> · <?= $o['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Razorpay' ?></div>
<?php if (!in_array($o['order_status'], ['pending_payment', 'cancelled'], true)): ?><a class="btn btn-outline-dark w-100 mt-3" target="_blank" rel="noopener" href="<?= e(url('invoice/' . $o['order_no'])) ?>"><i class="bi bi-receipt"></i> View / print invoice</a><?php endif; ?></div>
<div class="summary-card mb-3"><h2 class="h6 fw-bold text-uppercase text-muted">Delivery address</h2><div class="small"><?= e($o['ship_name']) ?> · <?= e($o['ship_phone']) ?><br><?= e($o['ship_line1']) ?><?= $o['ship_line2'] ? ', ' . e($o['ship_line2']) : '' ?><br><?= e($o['ship_city']) ?>, <?= e($o['ship_state']) ?> - <?= e($o['ship_pincode']) ?></div></div>
<?php if ($canCancel): ?><form method="post" action="<?= e(url('order/' . $o['order_no'] . '/cancel')) ?>" data-confirm="Cancel this order?"><?= csrf_field() ?>
<select class="form-select form-select-sm mb-2" name="reason" aria-label="Cancellation reason"><?php foreach (['Ordered by mistake', 'Found a better price', 'Delivery is too slow', 'Want to change address / items', 'Other'] as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select><button class="btn btn-outline-danger w-100">Cancel order</button></form><?php endif; ?>
</div></div>

<?php if ($returnOpen): ?>
<div class="modal fade" id="retModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post" action="<?= e(url('order/' . $o['order_no'] . '/return')) ?>"><?= csrf_field() ?><input type="hidden" name="item_id" id="retItem">
<div class="modal-header"><h5 class="modal-title">Return or replace</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><p class="small fw-semibold" id="retName"></p>
<div class="mb-2"><label class="form-label small" for="retType">Request</label><select class="form-select" id="retType" name="type"><option value="return">Return &amp; refund</option><option value="replacement">Replacement</option></select></div>
<div class="mb-2"><label class="form-label small" for="retReason">Reason</label><select class="form-select" id="retReason" name="reason" required><?php foreach (\App\Services\OrderService::RETURN_REASONS as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></div>
<div><label class="form-label small" for="retDetails">Details (optional)</label><textarea class="form-control" id="retDetails" name="details" rows="3" maxlength="500"></textarea></div></div>
<div class="modal-footer"><button class="btn btn-warning">Submit request</button></div></form></div></div>
<?php endif; ?>
