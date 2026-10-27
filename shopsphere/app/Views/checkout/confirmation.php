<?php $paidOnline = $o['payment_method'] === 'razorpay'; $pending = $o['order_status'] === 'pending_payment'; ?>
<div class="bg-white rounded-3 shadow-sm p-4 mx-auto" style="max-width:760px">
<?php if ($pending): ?>
  <div class="text-center"><i class="bi bi-hourglass-split display-3 text-warning"></i><h1 class="h4 mt-2">Confirming your payment…</h1>
  <p class="text-muted">We confirm payments on our server, not just in your browser. This page refreshes automatically. If you were not charged, you can retry below.</p>
  <button class="btn btn-warning" id="retryPay" data-order="<?= e($o['order_no']) ?>">Retry payment</button> <a class="btn btn-link" href="<?= e(url('order/' . $o['order_no'])) ?>">View order</a></div>
  <script>setTimeout(function(){ location.reload(); }, 8000);</script>
<?php elseif ($o['order_status'] === 'cancelled'): ?>
  <div class="text-center"><i class="bi bi-x-circle display-3 text-danger"></i><h1 class="h4 mt-2">Order cancelled</h1><p class="text-muted"><?= e($o['cancel_reason']) ?></p><a class="btn btn-warning" href="<?= e(url('/')) ?>">Continue shopping</a></div>
<?php else: ?>
  <div class="text-center"><i class="bi bi-check-circle-fill display-3 text-success"></i><h1 class="h4 mt-2">Thank you, your order is confirmed!</h1>
  <p class="mb-1">Order number <strong><?= e($o['order_no']) ?></strong></p><p class="text-muted">A confirmation email was sent to <?= e($o['customer_email']) ?>.</p></div>
  <hr>
  <div class="row small"><div class="col-sm-6"><strong>Delivering to</strong><br><?= e($o['ship_name']) ?><br><?= e($o['ship_line1']) ?><?= $o['ship_line2'] ? ', ' . e($o['ship_line2']) : '' ?><br><?= e($o['ship_city']) ?>, <?= e($o['ship_state']) ?> - <?= e($o['ship_pincode']) ?></div>
  <div class="col-sm-6"><strong>Estimated delivery</strong><br><?= $o['estimated_delivery'] ? e(date('D, j M Y', strtotime($o['estimated_delivery']))) : '—' ?><br><strong>Payment</strong><br><?= $paidOnline ? 'Paid online (Razorpay)' : 'Cash on Delivery — pay ' . e(inr($o['total'])) . ' on delivery' ?></div></div>
  <hr>
  <?php foreach ($items as $it): ?><div class="d-flex gap-3 mb-2"><img src="<?= e(img_url($it['image_url'])) ?>" width="56" height="56" class="rounded" alt="" data-fallback><div class="flex-grow-1 small"><?= e($it['name']) ?><div class="text-muted">Qty <?= (int)$it['qty'] ?></div></div><strong><?= e(inr($it['unit_price'] * $it['qty'])) ?></strong></div><?php endforeach; ?>
  <div class="text-end fs-5 fw-bold">Total <?= e(inr($o['total'])) ?></div>
  <div class="d-flex flex-wrap gap-2 justify-content-center mt-3"><a class="btn btn-warning" href="<?= e(url('order/' . $o['order_no'])) ?>">Track order</a><a class="btn btn-outline-dark" href="<?= e(url('invoice/' . $o['order_no'])) ?>" target="_blank" rel="noopener">View invoice</a><a class="btn btn-link" href="<?= e(url('/')) ?>">Continue shopping</a></div>
<?php endif; ?></div>
