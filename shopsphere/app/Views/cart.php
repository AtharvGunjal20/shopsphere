<?php /** @var array $t @var array $saved @var array $coupons */ ?>
<h1 class="h4 fw-bold mb-3">Shopping cart</h1>
<?php if (!$t['lines']): ?>
  <div class="empty-state text-center py-5 bg-white rounded-3">
    <i class="bi bi-cart-x display-3 text-muted"></i>
    <h2 class="h5 mt-3">Your cart is empty</h2>
    <p class="text-muted">Browse our catalogue and add items you love.</p>
    <a class="btn btn-warning" href="<?= e(url('/')) ?>">Continue shopping</a>
  </div>
  <div class="mt-4"><?php partial('row', ['heading' => 'You may like', 'items' => $saved, 'link' => null]); ?></div>
<?php else: ?>
<div class="row g-4">
  <div class="col-lg-8">
    <?php if ($t['free_shipping_gap'] > 0): ?>
      <div class="alert alert-info py-2 small mb-3"><i class="bi bi-truck"></i> Add <strong><?= e(inr($t['free_shipping_gap'])) ?></strong> more for FREE standard delivery.</div>
    <?php else: ?><div class="alert alert-success py-2 small mb-3"><i class="bi bi-truck"></i> You qualify for FREE standard delivery.</div><?php endif; ?>
    <?php foreach ($t['issues'] as $i): ?><div class="alert alert-warning py-2 small"><?= e($i) ?></div><?php endforeach; ?>

    <div class="cart-list bg-white rounded-3 shadow-sm">
      <?php foreach ($t['lines'] as $l): ?>
      <div class="cart-line" data-line="<?= (int)$l['item_id'] ?>">
        <a href="<?= e(url('product/' . $l['slug'])) ?>" class="cart-thumb"><img src="<?= e(img_url($l['image'])) ?>" alt="<?= e($l['name']) ?>" width="96" height="96" data-fallback></a>
        <div class="flex-grow-1">
          <a href="<?= e(url('product/' . $l['slug'])) ?>" class="fw-semibold text-dark"><?= e($l['name']) ?></a>
          <div class="small text-muted"><?= e($l['brand']) ?><?php foreach ($l['options_arr'] as $k => $v): ?> · <?= e($k) ?>: <?= e($v) ?><?php endforeach; ?></div>
          <div class="mt-1"><span class="fw-bold"><?= e(inr($l['unit'])) ?></span> <s class="text-muted small"><?= e(inr($l['unit_mrp'])) ?></s> <span class="text-success small"><?= (int)$l['discount_pct'] ?>% off</span></div>
          <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
            <div class="qty-box sm"><button type="button" class="btn btn-light" data-cart-step="-1" aria-label="Decrease quantity">−</button><input type="number" min="1" max="<?= min(10, (int)$l['stock']) ?>" value="<?= (int)$l['qty'] ?>" class="form-control text-center" data-cart-qty aria-label="Quantity for <?= e($l['name']) ?>"><button type="button" class="btn btn-light" data-cart-step="1" aria-label="Increase quantity">+</button></div>
            <button class="btn btn-link btn-sm text-danger p-0" type="button" data-cart-remove><i class="bi bi-trash"></i> Remove</button>
            <?php if (current_user()): ?><button class="btn btn-link btn-sm p-0" type="button" data-wish="<?= (int)$l['product_id'] ?>"><i class="bi bi-heart"></i> Save to wishlist</button><?php endif; ?>
          </div>
        </div>
        <div class="text-end fw-bold line-total"><?= e(inr($l['line'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="summary-card" id="cartSummary">
      <h2 class="h6 fw-bold text-uppercase text-muted">Price details</h2>
      <dl class="summary-rows">
        <div><dt>Price (<?= (int)$t['count'] ?> item<?= $t['count'] === 1 ? '' : 's' ?>)</dt><dd><?= e(inr($t['mrp_total'])) ?></dd></div>
        <div class="text-success"><dt>Product discount</dt><dd>− <?= e(inr($t['product_discount'])) ?></dd></div>
        <?php if ($t['coupon_discount'] > 0): ?><div class="text-success"><dt>Coupon (<?= e($t['coupon_code']) ?>)</dt><dd>− <?= e(inr($t['coupon_discount'])) ?></dd></div><?php endif; ?>
        <div><dt>Delivery</dt><dd><?= $t['shipping'] > 0 ? e(inr($t['shipping'])) : '<span class="text-success">FREE</span>' ?></dd></div>
        <div class="total"><dt>Total payable</dt><dd><?= e(inr($t['total'])) ?></dd></div>
      </dl>
      <div class="small text-muted mb-2">Includes GST of <?= e(inr($t['tax'])) ?>. COD adds <?= e(inr(cfg('shop.cod_fee'))) ?> handling.</div>
      <div class="text-success small fw-semibold mb-3">You will save <?= e(inr($t['total_savings'])) ?> on this order</div>

      <form id="couponForm" class="mb-1" novalidate>
        <label for="couponCode" class="form-label small fw-semibold mb-1">Have a coupon?</label>
        <?php if ($t['coupon_code']): ?>
          <div class="d-flex justify-content-between align-items-center border rounded p-2"><span><i class="bi bi-ticket-perforated-fill text-success"></i> <strong><?= e($t['coupon_code']) ?></strong> applied</span><button class="btn btn-sm btn-link text-danger p-0" type="button" id="couponRemove">Remove</button></div>
        <?php else: ?>
          <div class="input-group input-group-sm"><input id="couponCode" class="form-control text-uppercase" maxlength="30" placeholder="Enter code" autocomplete="off"><button class="btn btn-outline-dark" type="submit">Apply</button></div>
          <?php if ($t['coupon_message']): ?><div class="text-danger small mt-1"><?= e($t['coupon_message']) ?></div><?php endif; ?>
          <div class="small text-muted mt-1">Try: <?php foreach ($coupons as $c): ?><button type="button" class="btn btn-sm btn-link p-0 me-2" data-coupon="<?= e($c['code']) ?>"><?= e($c['code']) ?></button><?php endforeach; ?></div>
        <?php endif; ?>
      </form>
      <a class="btn btn-warning btn-lg w-100 mt-3 <?= $t['issues'] ? 'disabled' : '' ?>" href="<?= e(url('checkout')) ?>">Proceed to checkout <i class="bi bi-arrow-right"></i></a>
      <a class="btn btn-link w-100 mt-1" href="<?= e(url('/')) ?>">Continue shopping</a>
    </div>
  </div>
</div>
<div class="mt-5"><?php partial('row', ['heading' => 'You may also like', 'items' => $saved, 'link' => null]); ?></div>
<?php endif; ?>
