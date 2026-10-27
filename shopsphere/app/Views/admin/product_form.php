<?php $v = fn($k, $d = '') => e(old($k, $p[$k] ?? $d));
$specs = old('specs', $p ? implode("\n", array_map(fn($k, $x) => "$k: $x", array_keys(json_dec($p['specs'])), json_dec($p['specs']))) : '');
$opts = old('options', $p ? implode("\n", array_map(fn($k, $x) => "$k: " . implode(', ', $x), array_keys(json_dec($p['options'])), json_dec($p['options']))) : '');
$imgs = old('images', implode("\n", $images)); ?>
<h1 class="h4 fw-bold mb-3"><?= $p ? 'Edit product' : 'New product' ?></h1>
<form method="post" action="<?= e(url($p ? 'admin/products/' . (int)$p['id'] : 'admin/products/new')) ?>" class="bg-white rounded-3 shadow-sm p-3" style="max-width:900px"><?= csrf_field() ?>
<div class="row g-3">
<div class="col-md-8"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="<?= $v('name') ?>" required></div>
<div class="col-md-4"><label class="form-label" for="sku">SKU</label><input class="form-control" id="sku" name="sku" value="<?= $v('sku') ?>" required></div>
<div class="col-md-4"><label class="form-label" for="brand">Brand</label><input class="form-control" id="brand" name="brand" value="<?= $v('brand') ?>" required></div>
<div class="col-md-4"><label class="form-label" for="category_id">Category</label><select class="form-select" id="category_id" name="category_id" required><option value="">Select</option><?php foreach ($cats as $c): if ($c['parent_id']) continue; ?><option value="<?= (int)$c['id'] ?>" <?= (int)old('category_id', $p['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label" for="subcategory_id">Subcategory</label><select class="form-select" id="subcategory_id" name="subcategory_id"><option value="">None</option><?php foreach ($cats as $c): if (!$c['parent_id']) continue; ?><option value="<?= (int)$c['id'] ?>" <?= (int)old('subcategory_id', $p['subcategory_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label" for="mrp">M.R.P. (₹)</label><input class="form-control" id="mrp" name="mrp" type="number" step="0.01" min="0" value="<?= $v('mrp') ?>" required></div>
<div class="col-md-3"><label class="form-label" for="price">Selling price (₹)</label><input class="form-control" id="price" name="price" type="number" step="0.01" min="0" value="<?= $v('price') ?>" required></div>
<div class="col-md-3"><label class="form-label" for="stock">Stock</label><input class="form-control" id="stock" name="stock" type="number" min="0" value="<?= $v('stock', '0') ?>" required></div>
<div class="col-md-3"><label class="form-label" for="gst_rate">GST %</label><select class="form-select" id="gst_rate" name="gst_rate"><?php foreach ([0, 5, 12, 18, 28] as $g): ?><option <?= (float)old('gst_rate', $p['gst_rate'] ?? 18) === (float)$g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label" for="short_desc">Short description</label><input class="form-control" id="short_desc" name="short_desc" value="<?= $v('short_desc') ?>"></div>
<div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4"><?= $v('description') ?></textarea></div>
<div class="col-md-6"><label class="form-label" for="specs">Specifications <small class="text-muted">(one "Key: value" per line)</small></label><textarea class="form-control" id="specs" name="specs" rows="5"><?= e($specs) ?></textarea></div>
<div class="col-md-6"><label class="form-label" for="options">Variants <small class="text-muted">(e.g. "Color: Black, Blue")</small></label><textarea class="form-control" id="options" name="options" rows="5"><?= e($opts) ?></textarea></div>
<div class="col-12"><label class="form-label" for="images">Image URLs <small class="text-muted">(one per line; https:// links to images you are licensed to use)</small></label><textarea class="form-control" id="images" name="images" rows="4"><?= e($imgs) ?></textarea></div>
<div class="col-md-4"><label class="form-label" for="warranty">Warranty</label><input class="form-control" id="warranty" name="warranty" value="<?= $v('warranty') ?>"></div>
<div class="col-md-4"><label class="form-label" for="shipping_info">Shipping info</label><input class="form-control" id="shipping_info" name="shipping_info" value="<?= $v('shipping_info') ?>"></div>
<div class="col-md-4"><label class="form-label" for="return_policy">Return policy</label><input class="form-control" id="return_policy" name="return_policy" value="<?= $v('return_policy') ?>"></div>
<div class="col-12 d-flex gap-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= old('is_active', $p['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="act">Active (visible)</label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" name="is_deal" value="1" id="deal" <?= old('is_deal', $p['is_deal'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="deal">Deal</label></div></div>
</div><div class="mt-3 d-flex gap-2"><button class="btn btn-warning">Save product</button><a class="btn btn-link" href="<?= e(url('admin/products')) ?>">Back</a></div>
<div class="small text-muted mt-2">Discount % is calculated automatically from M.R.P. and selling price. Prices and stock are validated on the server.</div></form>
