<div class="auth-card mx-auto"><h1 class="h4 fw-bold mb-3">Create account</h1>
<form method="post" action="<?= e(url('register')) ?>" novalidate><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" value="<?= e(old('name')) ?>" required autocomplete="name"></div>
<div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email"></div>
<div class="mb-3"><label class="form-label" for="phone">Mobile number</label><div class="input-group"><span class="input-group-text">+91</span><input class="form-control" id="phone" name="phone" inputmode="numeric" maxlength="12" value="<?= e(old('phone')) ?>" required autocomplete="tel-national"></div></div>
<div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" id="password" type="password" name="password" minlength="8" required autocomplete="new-password" aria-describedby="pwHelp"><div id="pwHelp" class="form-text">At least 8 characters with a letter and a number.</div></div>
<div class="mb-3"><label class="form-label" for="password2">Confirm password</label><input class="form-control" id="password2" type="password" name="password2" required autocomplete="new-password"></div>
<button class="btn btn-warning w-100">Create account</button></form>
<hr><p class="small mb-0">Already have an account? <a href="<?= e(url('login')) ?>">Sign in</a></p></div>
