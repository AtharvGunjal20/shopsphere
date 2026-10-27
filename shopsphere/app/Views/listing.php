<?php
/** @var array $f @var array $res @var array $facets @var ?array $cat @var array $trail @var array $subs */
$qs = function (array $override = [], array $remove = []) use ($f) {
    $p = array_filter([
        'q' => $f['q'], 'min_price' => $f['min_price'], 'max_price' => $f['max_price'], 'brand' => $f['brand'] ?: null,
        'rating' => $f['rating'] ?: null, 'discount' => $f['discount'] ?: null, 'in_stock' => $f['in_stock'] ?: null,
        'color' => $f['color'], 'size' => $f['size'], 'sort' => $f['sort'] !== 'relevance' ? $f['sort'] : null,
    ], fn($v) => $v !== null && $v !== '' && $v !== []);
    foreach ($remove as $r) unset($p[$r]);
    $p = $override + $p;
    if (!isset($override['page'])) unset($p['page']);
    return http_build_query($p);
};
$base = $cat ? url('category/' . $cat['slug']) : ($is_deals ? url('deals') : url('search'));
$activeFilters = ($f['brand'] ? 1 : 0) + ($f['rating'] ? 1 : 0) + ($f['discount'] && !$is_deals ? 1 : 0) + ($f['in_stock'] ? 1 : 0) + ($f['color'] ? 1 : 0) + ($f['size'] ? 1 : 0) + (($f['min_price'] !== '' || $f['max_price'] !== '') ? 1 : 0);
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small mb-2">
  <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
  <?php foreach ($trail as $i => $t): ?>
    <li class="breadcrumb-item <?= $i === count($trail) - 1 ? 'active' : '' ?>"><?= $i === count($trail) - 1 ? e($t['name']) : '<a href="' . e(url('category/' . $t['slug'])) . '">' . e($t['name']) . '</a>' ?></li>
  <?php endforeach; ?>
  <?php if (!$trail): ?><li class="breadcrumb-item active"><?= e($heading) ?></li><?php endif; ?>
</ol></nav>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div><h1 class="h4 fw-bold mb-0"><?= e($heading) ?></h1><div class="text-muted small"><?= (int)$res['total'] ?> product<?= $res['total'] === 1 ? '' : 's' ?> found</div></div>
  <div class="d-flex gap-2 align-items-center">
    <button class="btn btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#filters"><i class="bi bi-funnel"></i> Filters<?= $activeFilters ? ' (' . $activeFilters . ')' : '' ?></button>
    <form method="get" action="<?= e($base) ?>" class="d-flex align-items-center gap-2">
      <?php foreach (['q' => $f['q'], 'min_price' => $f['min_price'], 'max_price' => $f['max_price'], 'rating' => $f['rating'], 'discount' => $f['discount'], 'in_stock' => $f['in_stock'], 'color' => $f['color'], 'size' => $f['size']] as $k => $v): if ($v !== '' && $v !== 0 && $v !== null): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
      <?php foreach ($f['brand'] as $b): ?><input type="hidden" name="brand[]" value="<?= e($b) ?>"><?php endforeach; ?>
      <label for="sort" class="small text-muted text-nowrap mb-0">Sort by</label>
      <select name="sort" id="sort" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php foreach (['relevance' => 'Relevance', 'popularity' => 'Popularity', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'rating' => 'Customer rating', 'newest' => 'Newest arrivals', 'discount' => 'Highest discount'] as $k => $l): ?>
          <option value="<?= e($k) ?>" <?= $f['sort'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="row g-3">
  <aside class="col-lg-3 col-xl-2">
    <div class="offcanvas-lg offcanvas-start filters-panel" tabindex="-1" id="filters" aria-labelledby="filtersLabel">
      <div class="offcanvas-header"><h5 class="offcanvas-title" id="filtersLabel">Filters</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filters" aria-label="Close"></button></div>
      <div class="offcanvas-body d-block">
        <form method="get" action="<?= e($base) ?>">
          <?php if ($f['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($f['q']) ?>"><?php endif; ?>
          <?php if ($f['sort'] !== 'relevance'): ?><input type="hidden" name="sort" value="<?= e($f['sort']) ?>"><?php endif; ?>

          <?php if ($subs): ?>
            <div class="filter-group"><h6>Subcategories</h6><ul class="list-unstyled small mb-0">
              <?php foreach ($subs as $s): ?><li><a class="<?= $cat && $cat['slug'] === $s['slug'] ? 'fw-bold' : '' ?>" href="<?= e(url('category/' . $s['slug'])) ?>"><?= e($s['name']) ?></a></li><?php endforeach; ?>
            </ul></div>
          <?php endif; ?>

          <div class="filter-group"><h6>Price (₹)</h6>
            <div class="d-flex gap-2"><input type="number" min="0" name="min_price" value="<?= e($f['min_price']) ?>" class="form-control form-control-sm" placeholder="Min" aria-label="Minimum price"><input type="number" min="0" name="max_price" value="<?= e($f['max_price']) ?>" class="form-control form-control-sm" placeholder="Max" aria-label="Maximum price"></div>
          </div>

          <?php if ($facets['brands']): ?>
          <div class="filter-group"><h6>Brand</h6><div class="filter-scroll">
            <?php foreach ($facets['brands'] as $b): ?>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="brand[]" value="<?= e($b['name']) ?>" id="b-<?= e(slugify($b['name'])) ?>" <?= in_array($b['name'], $f['brand'], true) ? 'checked' : '' ?>><label class="form-check-label small" for="b-<?= e(slugify($b['name'])) ?>"><?= e($b['name']) ?> <span class="text-muted">(<?= (int)$b['n'] ?>)</span></label></div>
            <?php endforeach; ?></div></div>
          <?php endif; ?>

          <div class="filter-group"><h6>Customer rating</h6>
            <?php foreach ([4, 3, 2] as $r): ?><div class="form-check"><input class="form-check-input" type="radio" name="rating" value="<?= $r ?>" id="r<?= $r ?>" <?= $f['rating'] === $r ? 'checked' : '' ?>><label class="form-check-label small" for="r<?= $r ?>"><?= $r ?>★ &amp; up</label></div><?php endforeach; ?>
            <div class="form-check"><input class="form-check-input" type="radio" name="rating" value="" id="r0" <?= !$f['rating'] ? 'checked' : '' ?>><label class="form-check-label small" for="r0">Any</label></div>
          </div>

          <div class="filter-group"><h6>Discount</h6>
            <?php foreach ([50, 30, 20, 10] as $d): ?><div class="form-check"><input class="form-check-input" type="radio" name="discount" value="<?= $d ?>" id="d<?= $d ?>" <?= $f['discount'] === $d ? 'checked' : '' ?>><label class="form-check-label small" for="d<?= $d ?>"><?= $d ?>% or more</label></div><?php endforeach; ?>
            <div class="form-check"><input class="form-check-input" type="radio" name="discount" value="" id="d0" <?= !$f['discount'] ? 'checked' : '' ?>><label class="form-check-label small" for="d0">Any</label></div>
          </div>

          <div class="filter-group"><h6>Availability</h6>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="in_stock" value="1" id="instock" <?= $f['in_stock'] ? 'checked' : '' ?>><label class="form-check-label small" for="instock">Exclude out of stock</label></div></div>

          <?php if ($facets['colors']): ?><div class="filter-group"><h6>Colour</h6>
            <select name="color" class="form-select form-select-sm" aria-label="Colour"><option value="">Any</option><?php foreach ($facets['colors'] as $c): ?><option <?= $f['color'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div><?php endif; ?>
          <?php if ($facets['sizes']): ?><div class="filter-group"><h6>Size</h6>
            <select name="size" class="form-select form-select-sm" aria-label="Size"><option value="">Any</option><?php foreach ($facets['sizes'] as $c): ?><option <?= $f['size'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div><?php endif; ?>

          <div class="d-grid gap-2 mt-3"><button class="btn btn-warning btn-sm" type="submit">Apply filters</button>
            <?php if ($activeFilters): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e($base . ($f['q'] !== '' ? '?q=' . urlencode($f['q']) : '')) ?>">Clear all</a><?php endif; ?></div>
        </form>
      </div>
    </div>
  </aside>

  <div class="col-lg-9 col-xl-10">
    <?php if (!$res['items']): ?>
      <div class="empty-state text-center py-5">
        <i class="bi bi-search display-4 text-muted"></i>
        <h2 class="h5 mt-3">No products match your search</h2>
        <p class="text-muted">Check the spelling, try fewer or more general keywords, or remove some filters.</p>
        <?php if ($activeFilters): ?><a class="btn btn-warning" href="<?= e($base . ($f['q'] !== '' ? '?q=' . urlencode($f['q']) : '')) ?>">Clear filters</a><?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('/')) ?>">Back to home</a>
      </div>
      <?php if (!empty($res['suggest'])): partial('row', ['heading' => 'Popular right now', 'items' => $res['suggest'], 'link' => null]); endif; ?>
    <?php else: ?>
      <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
        <?php foreach ($res['items'] as $p): ?><div class="col"><?php partial('product_card', ['p' => $p]); ?></div><?php endforeach; ?>
      </div>
      <?php if ($res['pages'] > 1): ?>
      <nav class="mt-4" aria-label="Pagination"><ul class="pagination justify-content-center flex-wrap">
        <?php $pg = $res['page']; $last = $res['pages'];
        $pages = array_unique(array_filter([1, 2, $pg - 1, $pg, $pg + 1, $last - 1, $last], fn($x) => $x >= 1 && $x <= $last)); sort($pages); $prev = 0; ?>
        <li class="page-item <?= $pg <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($base . '?' . $qs(['page' => max(1, $pg - 1)])) ?>" aria-label="Previous">&laquo;</a></li>
        <?php foreach ($pages as $x): if ($prev && $x - $prev > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; $prev = $x; ?>
          <li class="page-item <?= $x === $pg ? 'active' : '' ?>"><a class="page-link" href="<?= e($base . '?' . $qs(['page' => $x])) ?>"><?= $x ?></a></li>
        <?php endforeach; ?>
        <li class="page-item <?= $pg >= $last ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($base . '?' . $qs(['page' => min($last, $pg + 1)])) ?>" aria-label="Next">&raquo;</a></li>
      </ul></nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
