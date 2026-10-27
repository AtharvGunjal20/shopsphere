<?php $u = current_user(); ?>
<div class="row g-4"><div class="col-lg-3"><?php partial('account_nav'); ?></div><div class="col-lg-9">
<h1 class="h4 fw-bold">Hello, <?= e($u['name']) ?></h1>
<?php if (!$u['email_verified_at']): ?><div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">Your email is not verified yet.
<form method="post" action="<?= e(url('verify/resend')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-dark">Resend verification email</button></form></div><?php endif; ?>
<div class="row g-3 mb-4">
<div class="col-4"><div class="stat-card"><div class="stat-n"><?= (int)$stats['n'] ?></div>Orders</div></div>
<div class="col-4"><div class="stat-card"><div class="stat-n"><?= e(inr($stats['spent'])) ?></div>Total spent</div></div>
<div class="col-4"><div class="stat-card"><div class="stat-n"><?= (int)$wishCount ?></div>Wishlist items</div></div></div>
<h2 class="h5 fw-bold">Recent orders</h2>
<?php if (!$orders): ?><p class="text-muted">You haven't placed any orders yet. <a href="<?= e(url('/')) ?>">Start shopping</a></p><?php endif; ?>
<?php foreach ($orders as $o): ?>
<div class="order-row"><div><a class="fw-semibold" href="<?= e(url('order/' . $o['order_no'])) ?>"><?= e($o['order_no']) ?></a><div class="small text-muted"><?= e(date('j M Y', strtotime($o['created_at']))) ?></div></div>
<div><?= status_badge($o['order_status']) ?></div><div class="fw-bold"><?= e(inr($o['total'])) ?></div><a class="btn btn-sm btn-outline-dark" href="<?= e(url('order/' . $o['order_no'])) ?>">Track</a></div>
<?php endforeach; ?>
<?php if ($recent): ?><div class="mt-4"><?php partial('row', ['heading' => 'Recently viewed', 'items' => $recent, 'link' => null]); ?></div><?php endif; ?>
</div></div>
