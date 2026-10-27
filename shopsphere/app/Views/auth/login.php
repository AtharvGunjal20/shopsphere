<div class="auth-card mx-auto"><h1 class="h4 fw-bold mb-3">Sign in</h1>
<form method="post" action="<?= e(url('login')) ?>"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="username" autofocus></div>
<div class="mb-3"><label class="form-label d-flex justify-content-between" for="password">Password <a class="small" href="<?= e(url('forgot')) ?>">Forgot password?</a></label><input class="form-control" id="password" type="password" name="password" required autocomplete="current-password"></div>
<button class="btn btn-warning w-100">Sign in</button></form>
<hr><p class="small mb-0">New to ShopSphere? <a href="<?= e(url('register')) ?>">Create your account</a></p></div>
