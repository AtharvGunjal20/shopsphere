<h1 class="h4 fw-bold mb-3">Compare products</h1>
<?php if (count($rows) < 1): ?>
<div class="empty-state text-center py-5 bg-white rounded-3"><i class="bi bi-arrow-left-right display-3 text-muted"></i><h2 class="h5 mt-3">Nothing to compare yet</h2><p class="text-muted">Use the compare button on product cards to add up to 4 products.</p><a class="btn btn-warning" href="<?= e(url('/')) ?>">Browse products</a></div>
<?php else:
$vals = fn($f) => array_map($f, $rows);
$diff = fn(array $v) => count(array_unique($v)) > 1; ?>
<div class="table-responsive bg-white rounded-3 shadow-sm"><table class="table compare-table align-middle mb-0">
<thead><tr><th scope="col" style="width:150px"></th>
<?php foreach ($rows as $r): ?><th scope="col" class="text-center"><a href="<?= e(url('product/' . $r['slug'])) ?>"><img src="<?= e(img_url($r['image'])) ?>" alt="" width="110" height="110" data-fallback><div class="small fw-semibold text-dark mt-1"><?= e($r['name']) ?></div></a>
<form method="post" action="<?= e(url('compare/remove')) ?>"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-link btn-sm text-danger">Remove</button></form></th><?php endforeach; ?></tr></thead>
<tbody>
<?php
$lines = [
 'Price' => fn($r) => inr($r['price']), 'M.R.P.' => fn($r) => inr($r['mrp']), 'Discount' => fn($r) => (int)$r['discount_pct'] . '%',
 'Brand' => fn($r) => $r['brand'], 'Category' => fn($r) => $r['category_name'],
 'Rating' => fn($r) => $r['rating_count'] ? number_format((float)$r['rating_avg'], 1) . ' / 5 (' . (int)$r['rating_count'] . ')' : 'No reviews',
 'Warranty' => fn($r) => $r['warranty'] ?: '—', 'Availability' => fn($r) => availability($r)[0],
];
foreach ($lines as $label => $fn): $v = array_map($fn, $rows); ?>
<tr class="<?= count($rows) > 1 && $diff($v) ? 'row-diff' : '' ?>"><th scope="row"><?= e($label) ?></th><?php foreach ($v as $x): ?><td class="text-center"><?= e($x) ?></td><?php endforeach; ?></tr>
<?php endforeach; ?>
<?php foreach ($specKeys as $k): $v = array_map(fn($r) => json_dec($r['specs'])[$k] ?? '—', $rows); ?>
<tr class="<?= count($rows) > 1 && $diff($v) ? 'row-diff' : '' ?>"><th scope="row"><?= e($k) ?></th><?php foreach ($v as $x): ?><td class="text-center"><?= e($x) ?></td><?php endforeach; ?></tr>
<?php endforeach; ?>
</tbody></table></div>
<p class="small text-muted mt-2"><span class="diff-key"></span> Highlighted rows show differences between products.</p>
<?php endif; ?>
