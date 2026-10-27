<div class="row g-4"><div class="col-lg-3"><?php partial('account_nav'); ?></div><div class="col-lg-9">
<h1 class="h4 fw-bold mb-3">Saved addresses</h1>
<div class="row g-3 mb-4">
<?php foreach ($addresses as $a): ?><div class="col-md-6"><div class="bg-white rounded-3 shadow-sm p-3 h-100">
<div class="d-flex justify-content-between"><strong><?= e($a['label']) ?></strong><?= $a['is_default'] ? '<span class="badge text-bg-secondary">Default</span>' : '' ?></div>
<div><?= e($a['full_name']) ?> · <?= e($a['phone']) ?></div><div class="small text-muted"><?= e($a['line1']) ?><?= $a['line2'] ? ', ' . e($a['line2']) : '' ?>, <?= e($a['city']) ?>, <?= e($a['state']) ?> - <?= e($a['pincode']) ?></div>
<div class="mt-2 d-flex gap-2"><a class="btn btn-sm btn-outline-dark" href="<?= e(url('account/addresses?edit=' . (int)$a['id'])) ?>">Edit</a>
<form method="post" action="<?= e(url('account/addresses/delete')) ?>" data-confirm="Delete this address?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div></div></div><?php endforeach; ?>
<?php if (!$addresses): ?><p class="text-muted">No saved addresses yet.</p><?php endif; ?></div>
<h2 class="h5 fw-bold"><?= $edit ? 'Edit address' : 'Add a new address' ?></h2>
<form method="post" action="<?= e(url('account/addresses')) ?>" class="bg-white rounded-3 shadow-sm p-3"><?= csrf_field() ?>
<?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
<div class="mb-2" style="max-width:200px"><label class="form-label small" for="label">Label</label><select class="form-select" id="label" name="label"><?php foreach (['Home', 'Work', 'Other'] as $l): ?><option <?= ($edit['label'] ?? 'Home') === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
<?php partial('address_form', ['prefix' => '', 'a' => $edit ?? []]); ?>
<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="isdef" <?= !empty($edit['is_default']) ? 'checked' : '' ?>><label class="form-check-label" for="isdef">Make this my default address</label></div>
<button class="btn btn-warning mt-3">Save address</button><?php if ($edit): ?> <a class="btn btn-link" href="<?= e(url('account/addresses')) ?>">Cancel</a><?php endif; ?></form>
</div></div>
