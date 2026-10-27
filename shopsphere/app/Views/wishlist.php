<h1 class="h4 fw-bold mb-3">My wishlist <small class="text-muted fw-normal">(<?= count($items) ?>)</small></h1>
<?php if (!$items): ?>
<div class="empty-state text-center py-5 bg-white rounded-3"><i class="bi bi-heart display-3 text-muted"></i><h2 class="h5 mt-3">Your wishlist is empty</h2><p class="text-muted">Tap the heart on any product to save it here.</p><a class="btn btn-warning" href="<?= e(url('/')) ?>">Start shopping</a></div>
<?php else: ?>
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3">
<?php foreach ($items as $p): ?><div class="col"><?php partial('product_card', ['p' => $p]); ?>
  <?php if ((int)$p['stock'] > 0): ?><button class="btn btn-sm btn-outline-dark w-100 mt-1" data-wish-move="<?= (int)$p['id'] ?>"><i class="bi bi-cart-plus"></i> Move to cart</button><?php endif; ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
