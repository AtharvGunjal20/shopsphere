<?php /** @var array $t @var ?array $user @var array $addresses */
$def = $addresses[0] ?? null; ?>
<h1 class="h4 fw-bold mb-3">Checkout</h1>
<div class="row g-4">
<div class="col-lg-8">
<form id="checkoutForm" novalidate>
<?php if (!$user): ?>
<section class="co-step"><h2><span class="n">1</span> Contact details</h2>
<div class="alert alert-light border small py-2">Have an account? <a href="<?= e(url('login')) ?>">Sign in</a> for faster checkout and order history. Guest orders above <?= e(inr($guestMax)) ?> need an account.</div>
<div class="row g-2"><div class="col-md-4"><label class="form-label small" for="contact_name">Full name</label><input class="form-control" id="contact_name" name="contact_name" required autocomplete="name"></div>
<div class="col-md-4"><label class="form-label small" for="contact_email">Email</label><input class="form-control" type="email" id="contact_email" name="contact_email" required autocomplete="email"></div>
<div class="col-md-4"><label class="form-label small" for="contact_phone">Mobile</label><input class="form-control" id="contact_phone" name="contact_phone" inputmode="numeric" maxlength="10" required autocomplete="tel-national"></div></div></section>
<?php endif; ?>

<section class="co-step"><h2><span class="n"><?= $user ? 1 : 2 ?></span> Delivery address</h2>
<?php foreach ($addresses as $i => $a): ?>
<label class="addr-choice"><input type="radio" name="address_id" value="<?= (int)$a['id'] ?>" data-pin="<?= e($a['pincode']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
<span><strong><?= e($a['full_name']) ?></strong> <span class="badge text-bg-light"><?= e($a['label']) ?></span><br><span class="small text-muted"><?= e($a['line1']) ?>, <?= e($a['city']) ?>, <?= e($a['state']) ?> - <?= e($a['pincode']) ?> · <?= e($a['phone']) ?></span></span></label>
<?php endforeach; ?>
<?php if ($addresses): ?><label class="addr-choice"><input type="radio" name="address_id" value="0"><span><strong>Use a new address</strong></span></label><?php endif; ?>
<div id="newAddr" <?= $addresses ? 'hidden' : '' ?> class="mt-2"><?php partial('address_form', ['prefix' => 'addr_', 'a' => ['full_name' => $user['name'] ?? '', 'phone' => $user['phone'] ?? '', 'pincode' => $pin]]); ?>
<?php if ($user): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="save_address" value="1" id="saveaddr" checked><label class="form-check-label" for="saveaddr">Save this address</label></div><?php endif; ?></div></section>

<section class="co-step"><h2><span class="n"><?= $user ? 2 : 3 ?></span> Shipping method</h2>
<label class="addr-choice"><input type="radio" name="shipping_method" value="standard" checked><span><strong>Standard delivery</strong> <span class="text-muted">— <span id="estStandard">enter PIN for estimate</span></span><br><span class="small text-muted"><?= e(inr(cfg('shop.standard_shipping'))) ?>, FREE on <?= e(inr(cfg('shop.free_shipping_min'))) ?>+</span></span></label>
<label class="addr-choice"><input type="radio" name="shipping_method" value="express"><span><strong>Express delivery</strong> <span class="text-muted">— <span id="estExpress">enter PIN for estimate</span></span><br><span class="small text-muted"><?= e(inr(cfg('shop.express_shipping'))) ?></span></span></label>
<div class="small text-muted">Estimates use configured delivery rules, not live courier data.</div></section>

<section class="co-step"><h2><span class="n"><?= $user ? 3 : 4 ?></span> Review items &amp; coupon</h2>
<?php foreach ($t['lines'] as $l): ?><div class="d-flex gap-3 mb-2 align-items-center"><img src="<?= e(img_url($l['image'])) ?>" alt="" width="56" height="56" class="rounded" data-fallback><div class="flex-grow-1 small"><?= e($l['name']) ?><?php foreach ($l['options_arr'] as $k => $v): ?> <span class="text-muted">· <?= e($k) ?>: <?= e($v) ?></span><?php endforeach; ?><div class="text-muted">Qty <?= (int)$l['qty'] ?></div></div><strong><?= e(inr($l['line'])) ?></strong></div><?php endforeach; ?>
<div class="small">Coupon: <?= $t['coupon_code'] ? '<strong>' . e($t['coupon_code']) . '</strong> applied' : 'none' ?> — <a href="<?= e(url('cart')) ?>">change in cart</a></div></section>

<section class="co-step"><h2><span class="n"><?= $user ? 4 : 5 ?></span> Payment method</h2>
<label class="addr-choice"><input type="radio" name="payment_method" value="razorpay" <?= $razorpay ? 'checked' : 'disabled' ?>><span><strong>Pay online (Razorpay)</strong> <span class="badge text-bg-info">Test mode</span><br><span class="small text-muted"><?= $razorpay ? 'UPI, cards, net banking and wallets. Verified on our server before the order is confirmed.' : 'Not configured: add Razorpay test keys in config/config.local.php to enable.' ?></span></span></label>
<label class="addr-choice"><input type="radio" name="payment_method" value="cod" <?= $razorpay ? '' : 'checked' ?>><span><strong>Cash on Delivery</strong><br><span class="small text-muted">Extra <?= e(inr(cfg('shop.cod_fee'))) ?> handling fee. Available up to <?= e(inr(cfg('shop.cod_max_order'))) ?>.</span></span></label></section>
<div id="coError" class="alert alert-danger d-none" role="alert"></div>
</form></div>

<div class="col-lg-4"><div class="summary-card sticky-lg-top" style="top:130px" id="coSummary">
<h2 class="h6 fw-bold text-uppercase text-muted">Order summary</h2>
<dl class="summary-rows">
<div><dt>Price (<span data-q="count"><?= (int)$t['count'] ?></span> items)</dt><dd data-q="mrp_total"><?= e(inr($t['mrp_total'])) ?></dd></div>
<div class="text-success"><dt>Discount</dt><dd>− <span data-q="product_discount"><?= e(inr($t['product_discount'])) ?></span></dd></div>
<div class="text-success" id="rowCoupon" <?= $t['coupon_discount'] > 0 ? '' : 'hidden' ?>><dt>Coupon</dt><dd>− <span data-q="coupon_discount"><?= e(inr($t['coupon_discount'])) ?></span></dd></div>
<div><dt>Delivery</dt><dd data-q="shipping"><?= $t['shipping'] > 0 ? e(inr($t['shipping'])) : 'FREE' ?></dd></div>
<div id="rowCod" hidden><dt>COD fee</dt><dd data-q="cod_fee"></dd></div>
<div class="total"><dt>Total payable</dt><dd data-q="total"><?= e(inr($t['total'])) ?></dd></div></dl>
<div class="small text-muted mb-3">Includes GST of <span data-q="tax"><?= e(inr($t['tax'])) ?></span>. Prices shown are demo prices.</div>
<button class="btn btn-warning btn-lg w-100" id="placeOrder" type="button"><i class="bi bi-lock-fill"></i> Place order</button>
<div class="small text-muted text-center mt-2">By placing the order you agree to our <a href="<?= e(url('page/terms')) ?>">terms</a>.</div></div></div>
</div>
