<div class="auth-card mx-auto"><h1 class="h4 fw-bold mb-3">Choose a new password</h1>
<form method="post" action="<?= e(url('reset/' . $token)) ?>"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control" id="password" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
<div class="mb-3"><label class="form-label" for="password2">Confirm password</label><input class="form-control" id="password2" type="password" name="password2" required autocomplete="new-password"></div>
<button class="btn btn-warning w-100">Update password</button></form></div>
