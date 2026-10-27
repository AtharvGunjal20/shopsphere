<?php /** @var string $heading @var array $items @var ?string $link @var ?string $id */ ?>
<?php if (!empty($items)): ?>
<section class="shelf mb-4" <?= !empty($id) ? 'id="' . e($id) . '"' : '' ?> aria-label="<?= e($heading) ?>">
  <div class="d-flex align-items-baseline justify-content-between mb-2">
    <h2 class="h5 fw-bold mb-0"><?= e($heading) ?></h2>
    <?php if (!empty($link)): ?><a href="<?= e($link) ?>" class="small fw-semibold">See all <i class="bi bi-chevron-right"></i></a><?php endif; ?>
  </div>
  <div class="shelf-track">
    <?php foreach ($items as $p): ?><div class="shelf-item"><?php partial('product_card', ['p' => $p]); ?></div><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
