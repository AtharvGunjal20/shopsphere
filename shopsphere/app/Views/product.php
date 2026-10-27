<?php
/** @var array $p @var array $images @var array $specs @var array $options @var array $reviews @var array $dist @var array $questions */
[$avLabel, $avColor] = availability($p);
$inStock = (int)$p['stock'] > 0;
$maxQty = max(1, min(10, (int)$p['stock']));
$save = (float)$p['mrp'] - (float)$p['price'];
if (!$images) $images = [['url' => null, 'alt' => $p['name']]];
$totalReviews = array_sum($dist);
$bundle = array_merge([$p + ['image' => $images[0]['url'] ?? null]], $together);
$bundleSimple = array_values(array_filter($bundle, fn($b) => (int)$b['stock'] > 0));
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3">
  <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
  <li class="breadcrumb-item"><a href="<?= e(url('category/' . $p['category_slug'])) ?>"><?= e($p['category_name']) ?></a></li>
  <?php if ($p['sub_slug']): ?><li class="breadcrumb-item"><a href="<?= e(url('category/' . $p['sub_slug'])) ?>"><?= e($p['sub_name']) ?></a></li><?php endif; ?>
  <li class="breadcrumb-item active" aria-current="page"><?= e(mb_strimwidth($p['name'], 0, 50, '…')) ?></li>
</ol></nav>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="gallery sticky-lg-top" style="top:130px">
      <div class="gallery-main"><img id="mainImg" src="<?= e(img_url($images[0]['url'])) ?>" alt="<?= e($images[0]['alt'] ?: $p['name']) ?>" data-fallback></div>
      <div class="gallery-thumbs" role="list">
        <?php foreach ($images as $i => $im): ?>
          <button type="button" class="thumb <?= $i === 0 ? 'active' : '' ?>" data-src="<?= e(img_url($im['url'])) ?>" aria-label="Show image <?= $i + 1 ?>"><img src="<?= e(img_url($im['url'])) ?>" alt="" loading="lazy" data-fallback></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="text-muted small"><a href="<?= e(url('search?q=' . urlencode($p['brand']))) ?>"><?= e($p['brand']) ?></a> · SKU <?= e($p['sku']) ?></div>
    <h1 class="h3 fw-bold"><?= e($p['name']) ?></h1>
    <div class="d-flex align-items-center gap-2 mb-2">
      <?php if ((int)$p['rating_count'] > 0): ?>
        <span class="rating-pill"><?= e(number_format((float)$p['rating_avg'], 1)) ?> <i class="bi bi-star-fill"></i></span>
        <a href="#reviews" class="small"><?= (int)$p['rating_count'] ?> review<?= (int)$p['rating_count'] === 1 ? '' : 's' ?></a>
      <?php else: ?><a href="#reviews" class="small">Be the first to review</a><?php endif; ?>
    </div>
    <hr class="my-2">
    <div class="price-block">
      <?php if ((int)$p['discount_pct'] > 0): ?><span class="text-success fs-5 fw-semibold">-<?= (int)$p['discount_pct'] ?>%</span><?php endif; ?>
      <span class="price-lg"><?= e(inr($p['price'])) ?></span>
      <?php if ($save > 0): ?><div class="text-muted">M.R.P.: <s><?= e(inr($p['mrp'])) ?></s> <span class="text-success ms-2">You save <?= e(inr($save)) ?></span></div><?php endif; ?>
      <div class="small text-muted">Inclusive of all taxes (GST <?= e(rtrim(rtrim(number_format((float)$p['gst_rate'], 2), '0'), '.')) ?>%). <?= cfg('app.demo_notice') ? 'Demo price - illustrative, not a live market price.' : '' ?></div>
    </div>
    <div class="my-2"><span class="badge text-bg-<?= e($avColor) ?>"><?= e($avLabel) ?></span></div>
    <?php if ($p['short_desc']): ?><p class="mb-2"><?= e($p['short_desc']) ?></p><?php endif; ?>

    <form id="buyForm" data-product="<?= (int)$p['id'] ?>" class="mb-3">
      <?php foreach ($options as $group => $values): ?>
        <fieldset class="mb-2"><legend class="fs-6 fw-semibold mb-1"><?= e($group) ?></legend>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($values as $i => $v): $id = 'opt-' . slugify($group) . '-' . $i; ?>
              <input type="radio" class="btn-check" name="opt[<?= e($group) ?>]" id="<?= e($id) ?>" value="<?= e($v) ?>" data-group="<?= e($group) ?>" <?= $i === 0 && count($values) === 1 ? 'checked' : '' ?> required>
              <label class="btn btn-outline-dark btn-sm opt-pill" for="<?= e($id) ?>"><?= e($v) ?></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
      <?php if ($inStock): ?>
      <div class="d-flex align-items-center gap-2 my-3">
        <label for="qty" class="fw-semibold mb-0">Qty</label>
        <div class="qty-box"><button type="button" class="btn btn-light" data-qty-step="-1" aria-label="Decrease quantity">−</button><input id="qty" name="qty" type="number" min="1" max="<?= $maxQty ?>" value="1" class="form-control text-center" aria-label="Quantity"><button type="button" class="btn btn-light" data-qty-step="1" aria-label="Increase quantity">+</button></div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-warning btn-lg px-4" type="button" id="addToCartBtn"><i class="bi bi-cart-plus"></i> Add to cart</button>
        <button class="btn btn-danger btn-lg px-4" type="button" id="buyNowBtn"><i class="bi bi-lightning-charge-fill"></i> Buy now</button>
        <button class="btn btn-outline-secondary btn-lg pc-wish-inline <?= $wished ? 'active' : '' ?>" type="button" data-wish="<?= (int)$p['id'] ?>" aria-pressed="<?= $wished ? 'true' : 'false' ?>"><i class="bi <?= $wished ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i> <span class="d-none d-sm-inline">Wishlist</span></button>
        <button class="btn btn-outline-secondary btn-lg cmp-btn <?= $comparing ? 'active' : '' ?>" type="button" data-compare="<?= (int)$p['id'] ?>" aria-pressed="<?= $comparing ? 'true' : 'false' ?>"><i class="bi bi-arrow-left-right"></i> <span class="d-none d-sm-inline">Compare</span></button>
      </div>
      <?php else: ?>
        <div class="alert alert-secondary">Currently unavailable. Add it to your wishlist and check back later.</div>
        <button class="btn btn-outline-secondary" type="button" data-wish="<?= (int)$p['id'] ?>"><i class="bi bi-heart"></i> Add to wishlist</button>
      <?php endif; ?>
    </form>

    <div class="info-card mb-3">
      <h2 class="h6 fw-bold"><i class="bi bi-geo-alt"></i> Delivery</h2>
      <form id="pdpPin" class="input-group input-group-sm mb-1" style="max-width:320px"><input class="form-control" inputmode="numeric" maxlength="6" placeholder="Enter PIN code" aria-label="PIN code" value="<?= e($pin) ?>"><button class="btn btn-outline-dark" type="submit">Check</button></form>
      <div id="pdpEst" class="small"><?php if ($est): ?>Estimated delivery <strong><?= e($est['text']) ?></strong> (standard) — estimate based on configured delivery rules, not a live courier promise.<?php else: ?>Enter your PIN code to see estimated delivery dates.<?php endif; ?></div>
    </div>

    <div class="row g-2 mb-3 policy-icons text-center small">
      <div class="col-4"><i class="bi bi-truck fs-4"></i><div>Free delivery over <?= e(inr(cfg('shop.free_shipping_min'))) ?></div></div>
      <div class="col-4"><i class="bi bi-arrow-repeat fs-4"></i><div><?= (int)cfg('shop.return_window_days') ?>-day returns*</div></div>
      <div class="col-4"><i class="bi bi-shield-check fs-4"></i><div><?= e($p['warranty'] ?: 'Seller warranty') ?></div></div>
    </div>
    <ul class="small text-muted ps-3">
      <li><strong>Shipping:</strong> <?= e($p['shipping_info'] ?: 'Standard shipping rules apply.') ?></li>
      <li><strong>Returns:</strong> <?= e($p['return_policy'] ?: 'See our returns policy.') ?></li>
      <li><strong>Warranty:</strong> <?= e($p['warranty'] ?: 'As per brand policy.') ?></li>
    </ul>
  </div>
</div>

<?php if (count($bundleSimple) > 1): $sum = array_sum(array_map(fn($b) => (float)$b['price'], $bundleSimple)); ?>
<section class="mt-5" aria-label="Frequently bought together">
  <h2 class="h5 fw-bold">Frequently bought together</h2>
  <div class="bundle d-flex flex-wrap align-items-center gap-3">
    <?php foreach ($bundleSimple as $i => $b): ?>
      <?php if ($i): ?><span class="fs-3 text-muted">+</span><?php endif; ?>
      <a class="bundle-item text-decoration-none" href="<?= e(url('product/' . $b['slug'])) ?>"><img src="<?= e(img_url($b['image'])) ?>" alt="<?= e($b['name']) ?>" width="90" height="90" data-fallback><div class="small text-dark"><?= e(mb_strimwidth($b['name'], 0, 36, '…')) ?></div><strong class="text-dark"><?= e(inr($b['price'])) ?></strong></a>
    <?php endforeach; ?>
    <div class="ms-lg-3"><div class="text-muted small">Total for <?= count($bundleSimple) ?> items</div><div class="fs-5 fw-bold"><?= e(inr($sum)) ?></div>
      <div class="small text-muted">Items with size/colour options must be added from their own pages.</div></div>
  </div>
</section>
<?php endif; ?>

<section class="mt-5" aria-label="Product details">
  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-desc" type="button" role="tab">Description</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-specs" type="button" role="tab">Specifications</button></li>
  </ul>
  <div class="tab-content border border-top-0 p-3 bg-white">
    <div class="tab-pane fade show active" id="tab-desc" role="tabpanel"><p class="mb-0"><?= nl2br(e($p['description'] ?: $p['short_desc'])) ?></p></div>
    <div class="tab-pane fade" id="tab-specs" role="tabpanel">
      <?php if ($specs): ?><table class="table table-striped spec-table mb-0"><tbody>
        <?php foreach ($specs as $k => $v): ?><tr><th scope="row" class="w-25"><?= e($k) ?></th><td><?= e($v) ?></td></tr><?php endforeach; ?>
        <tr><th scope="row">Brand</th><td><?= e($p['brand']) ?></td></tr><tr><th scope="row">Warranty</th><td><?= e($p['warranty'] ?: '—') ?></td></tr>
      </tbody></table><?php else: ?><p class="mb-0 text-muted">No specifications listed.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="mt-5" id="reviews" aria-label="Customer reviews">
  <h2 class="h5 fw-bold">Customer reviews <small class="text-muted fw-normal">(<?= cfg('app.demo_notice') ? 'demo data' : 'verified' ?>)</small></h2>
  <div class="row g-4">
    <div class="col-md-4">
      <div class="display-5 fw-bold"><?= (int)$p['rating_count'] ? e(number_format((float)$p['rating_avg'], 1)) : '–' ?><small class="fs-5 text-muted"> / 5</small></div>
      <div class="text-warning"><?= stars((float)$p['rating_avg']) ?></div>
      <div class="text-muted small mb-2"><?= (int)$p['rating_count'] ?> rating<?= (int)$p['rating_count'] === 1 ? '' : 's' ?></div>
      <?php for ($s = 5; $s >= 1; $s--): $pct = $totalReviews ? round($dist[$s] / $totalReviews * 100) : 0; ?>
        <div class="d-flex align-items-center gap-2 small"><span style="width:2.2rem"><?= $s ?> ★</span><div class="progress flex-grow-1" style="height:8px" role="progressbar" aria-label="<?= $s ?> star" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div></div><span style="width:2.5rem" class="text-end"><?= $pct ?>%</span></div>
      <?php endfor; ?>
      <hr>
      <?php if (current_user()): ?>
        <h3 class="h6 fw-bold"><?= $myReview ? 'Update your review' : 'Write a review' ?></h3>
        <form method="post" action="<?= e(url('product/' . $p['slug'] . '/review')) ?>"><?= csrf_field() ?>
          <div class="mb-2 star-input" role="radiogroup" aria-label="Rating">
            <?php for ($s = 5; $s >= 1; $s--): ?><input type="radio" name="rating" id="rs<?= $s ?>" value="<?= $s ?>" <?= ($myReview['rating'] ?? 0) == $s ? 'checked' : '' ?> required><label for="rs<?= $s ?>" title="<?= $s ?> stars"><i class="bi bi-star-fill"></i></label><?php endfor; ?>
          </div>
          <input class="form-control form-control-sm mb-2" name="title" maxlength="150" placeholder="Headline" value="<?= e($myReview['title'] ?? '') ?>" aria-label="Review title">
          <textarea class="form-control form-control-sm mb-2" name="body" rows="3" maxlength="2000" placeholder="Share your experience" aria-label="Review"><?= e($myReview['body'] ?? '') ?></textarea>
          <button class="btn btn-sm btn-warning">Submit review</button>
        </form>
      <?php else: ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('login')) ?>">Sign in to write a review</a><?php endif; ?>
    </div>
    <div class="col-md-8">
      <?php if (!$reviews): ?><div class="text-muted">No reviews yet.</div><?php endif; ?>
      <?php foreach ($reviews as $r): ?>
        <article class="review-item">
          <div class="d-flex align-items-center gap-2"><span class="rating-pill"><?= (int)$r['rating'] ?> <i class="bi bi-star-fill"></i></span><strong><?= e($r['title']) ?></strong></div>
          <p class="mb-1 mt-1"><?= nl2br(e($r['body'])) ?></p>
          <div class="small text-muted"><?= e($r['user_name']) ?> · <?= e(date('j M Y', strtotime($r['created_at']))) ?><?php if ((int)$r['verified_purchase']): ?> · <span class="text-success"><i class="bi bi-patch-check-fill"></i> Verified purchase</span><?php endif; ?></div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mt-5" id="questions" aria-label="Questions and answers">
  <h2 class="h5 fw-bold">Questions &amp; answers</h2>
  <?php foreach ($questions as $q): ?>
    <div class="qa mb-3"><div><strong>Q:</strong> <?= e($q['question']) ?> <span class="small text-muted">— <?= e($q['user_name']) ?></span></div>
      <?php if ($q['answer']): ?><div class="ms-3"><strong>A:</strong> <?= e($q['answer']) ?></div>
      <?php elseif (is_admin()): ?><form class="ms-3 d-flex gap-2" method="post" action="<?= e(url('admin/questions/answer')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><input type="hidden" name="back" value="/product/<?= e($p['slug']) ?>"><input class="form-control form-control-sm" name="answer" placeholder="Answer as store" required><button class="btn btn-sm btn-dark">Post</button></form>
      <?php else: ?><div class="ms-3 text-muted small">Awaiting an answer.</div><?php endif; ?></div>
  <?php endforeach; ?>
  <?php if (current_user()): ?>
    <form class="d-flex gap-2" method="post" action="<?= e(url('product/' . $p['slug'] . '/question')) ?>" style="max-width:640px"><?= csrf_field() ?><input class="form-control" name="question" maxlength="500" placeholder="Ask a question about this product" aria-label="Your question" required><button class="btn btn-outline-dark">Ask</button></form>
  <?php else: ?><a href="<?= e(url('login')) ?>" class="small">Sign in to ask a question</a><?php endif; ?>
</section>

<div class="mt-5">
  <?php partial('row', ['heading' => 'Related products', 'items' => $related, 'link' => null]); ?>
  <?php partial('row', ['heading' => 'Recently viewed', 'items' => $recent, 'link' => null]); ?>
</div>
