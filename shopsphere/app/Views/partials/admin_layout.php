<?php $cur = request_path(); $u = $adminUser; $nav = [
 ['/admin', 'bi-speedometer2', 'Dashboard', ['admin', 'staff']], ['/admin/orders', 'bi-box-seam', 'Orders', ['admin', 'staff']], ['/admin/returns', 'bi-arrow-return-left', 'Returns', ['admin', 'staff']],
 ['/admin/refunds', 'bi-cash-coin', 'Refunds', ['admin', 'staff']], ['/admin/products', 'bi-grid', 'Products', ['admin']], ['/admin/coupons', 'bi-ticket-perforated', 'Coupons', ['admin']], ['/admin/users', 'bi-people', 'Users', ['admin']]]; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(asset('css/style.css')) ?>?v=1" rel="stylesheet"></head><body class="admin-body">
<div class="d-flex flex-column flex-lg-row min-vh-100">
<aside class="admin-side p-3"><a class="d-block text-white fs-5 fw-bold text-decoration-none mb-3" href="<?= e(url('admin')) ?>"><i class="bi bi-bag-heart-fill text-warning"></i> ShopSphere Admin</a>
<nav class="nav flex-lg-column flex-wrap gap-1"><?php foreach ($nav as [$h, $i, $l, $roles]): if (!in_array($u['role'], $roles, true)) continue; ?><a class="nav-link <?= $cur === $h || ($h !== '/admin' && str_starts_with($cur, $h)) ? 'active' : '' ?>" href="<?= e(url($h)) ?>"><i class="bi <?= $i ?> me-2"></i><?= e($l) ?></a><?php endforeach; ?>
<a class="nav-link" href="<?= e(url('/')) ?>"><i class="bi bi-shop me-2"></i>View store</a></nav>
<div class="small text-white-50 mt-3"><?= e($u['name']) ?> (<?= e($u['role']) ?>)</div>
<form method="post" action="<?= e(url('logout')) ?>" class="mt-2"><?= csrf_field() ?><button class="btn btn-sm btn-outline-light">Sign out</button></form></aside>
<main class="flex-grow-1 p-3 p-lg-4 admin-main"><?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show"><?= e($f['msg']) ?><button class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endforeach; ?><?= $content ?></main></div>
<script>window.SS={base:<?= json_encode(base_path()) ?>,csrf:<?= json_encode(csrf_token()) ?>};</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?= e(asset('js/admin.js')) ?>?v=1"></script></body></html>
