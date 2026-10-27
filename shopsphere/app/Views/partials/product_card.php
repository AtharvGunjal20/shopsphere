<?php
/** @var array $p  product row from Catalog::SELECT */
use App\Services\Catalog;

$wished = in_array((int)$p['id'], Catalog::wishlistIds(), true);
$cmp = in_array((int)$p['id'], Catalog::compareIds(), true);
[$avLabel, $avColor] = availability($p);
$hasOptions = !empty($p['_has_options']);
$href = url('product/' . $p['slug']);
?>
<article class="product-card h-100">
  <div class="pc-img">
    <a href="<?= e($href) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(img_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="300" height="300" data-fallback></a>
    <?php if ((int)$p['discount_pct'] > 0): ?><span class="pc-badge"><?= (int)$p['discount_pct'] ?>% off</span><?php endif; ?>
    <button type="button" class="pc-wish <?= $wished ? 'active' : '' ?>" data-wish="<?= (int)$p['id'] ?>" aria-label="<?= $wished ? 'Remove from wishlist' : 'Add to wishlist' ?>" aria-pressed="<?= $wished ? 'true' : 'false' ?>"><i class="bi <?= $wished ? 'bi-heart-fill' : 'bi-heart' ?>"></i></button>
  </div>
  <div class="pc-body">
    <div class="pc-brand"><?= e($p['brand']) ?></div>
    <h3 class="pc-title"><a href="<?= e($href) ?>"><?= e($p['name']) ?></a></h3>
    <?php if ((int)$p['rating_count'] > 0): ?>
      <div class="pc-rating"><span class="rating-pill"><?= e(number_format((float)$p['rating_avg'], 1)) ?> <i class="bi bi-star-fill"></i></span> <span class="text-muted small">(<?= (int)$p['rating_count'] ?>)</span></div>
    <?php else: ?><div class="pc-rating text-muted small">No reviews yet</div><?php endif; ?>
    <div class="pc-price">
      <span class="price"><?= e(inr($p['price'])) ?></span>
      <?php if ((float)$p['mrp'] > (float)$p['price']): ?><s class="mrp"><?= e(inr($p['mrp'])) ?></s><?php endif; ?>
    </div>
    <div class="small text-<?= e($avColor) ?> mb-2"><?= e($avLabel) ?></div>
    <div class="pc-actions mt-auto">
      <?php if ((int)$p['stock'] > 0): ?>
        <button class="btn btn-warning btn-sm flex-grow-1" type="button" data-quick-add="<?= (int)$p['id'] ?>" data-href="<?= e($href) ?>"><i class="bi bi-cart-plus"></i> Add to cart</button>
      <?php else: ?>
        <a class="btn btn-outline-secondary btn-sm flex-grow-1 disabled" tabindex="-1" aria-disabled="true">Out of stock</a>
      <?php endif; ?>
      <button type="button" class="btn btn-outline-secondary btn-sm cmp-btn <?= $cmp ? 'active' : '' ?>" data-compare="<?= (int)$p['id'] ?>" title="Compare" aria-label="Compare <?= e($p['name']) ?>" aria-pressed="<?= $cmp ? 'true' : 'false' ?>"><i class="bi bi-arrow-left-right"></i></button>
    </div>
  </div>
</article>
