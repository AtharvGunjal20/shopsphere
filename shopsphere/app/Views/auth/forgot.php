<div class="auth-card mx-auto"><h1 class="h4 fw-bold mb-3">Forgot password</h1><p class="text-muted small">Enter your email and we'll send a reset link.</p>
<form method="post" action="<?= e(url('forgot')) ?>"><?= csrf_field() ?><div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" type="email" name="email" required></div><button class="btn btn-warning w-100">Send reset link</button></form>
<p class="small mt-3 mb-0"><a href="<?= e(url('login')) ?>">Back to sign in</a></p></div>
