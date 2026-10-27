<div class="auth-card mx-auto"><h1 class="h4 fw-bold mb-3">Track your order</h1><p class="small text-muted">Enter your order number and the email used at checkout.</p>
<form method="post" action="<?= e(url('track')) ?>"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="order_no">Order number</label><input class="form-control text-uppercase" id="order_no" name="order_no" required placeholder="SS261027123456"></div>
<div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" type="email" name="email" required></div>
<button class="btn btn-warning w-100">Track</button></form></div>
