<div class="row g-4"><div class="col-lg-3"><?php partial('account_nav'); ?></div><div class="col-lg-9">
<h1 class="h4 fw-bold mb-3">My orders</h1>
<?php if (!$orders): ?><div class="empty-state text-center py-5 bg-white rounded-3"><i class="bi bi-box-seam display-3 text-muted"></i><h2 class="h5 mt-3">No orders yet</h2><a class="btn btn-warning" href="<?= e(url('/')) ?>">Start shopping</a></div><?php endif; ?>
<?php foreach ($orders as $o): ?>
<div class="bg-white rounded-3 shadow-sm mb-3"><div class="d-flex flex-wrap justify-content-between gap-2 p-3 border-bottom bg-light rounded-top">
<div><div class="small text-muted">Order placed</div><?= e(date('j M Y', strtotime($o['created_at']))) ?></div><div><div class="small text-muted">Total</div><?= e(inr($o['total'])) ?></div>
<div><div class="small text-muted">Order # <?= e($o['order_no']) ?></div><a href="<?= e(url('order/' . $o['order_no'])) ?>">View details &amp; track</a></div></div>
<div class="p-3"><div class="mb-2"><?= status_badge($o['order_status']) ?> <?= status_badge($o['payment_status']) ?></div>
<?php foreach ($o['items'] as $it): ?><div class="d-flex gap-3 mb-2"><img src="<?= e(img_url($it['image_url'])) ?>" alt="" width="64" height="64" class="rounded" data-fallback><div><?= e($it['name']) ?><div class="small text-muted">Qty <?= (int)$it['qty'] ?> · <?= e(inr($it['unit_price'])) ?></div></div></div><?php endforeach; ?></div></div>
<?php endforeach; ?></div></div>
