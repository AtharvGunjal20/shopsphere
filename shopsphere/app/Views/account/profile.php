<div class="row g-4"><div class="col-lg-3"><?php partial('account_nav'); ?></div><div class="col-lg-9" style="max-width:640px">
<h1 class="h4 fw-bold mb-3">Profile &amp; settings</h1>
<form method="post" action="<?= e(url('account/profile')) ?>" class="bg-white rounded-3 p-3 shadow-sm mb-4"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" value="<?= e($u['name']) ?>" required></div>
<div class="mb-3"><label class="form-label" for="em">Email</label><input class="form-control" id="em" value="<?= e($u['email']) ?>" disabled> <?= $u['email_verified_at'] ? '<span class="small text-success">Verified</span>' : '<span class="small text-warning">Not verified</span>' ?></div>
<div class="mb-3"><label class="form-label" for="phone">Mobile</label><input class="form-control" id="phone" name="phone" inputmode="numeric" maxlength="10" value="<?= e($u['phone']) ?>" required></div>
<button class="btn btn-warning">Save changes</button></form>
<h2 class="h5 fw-bold">Change password</h2>
<form method="post" action="<?= e(url('account/password')) ?>" class="bg-white rounded-3 p-3 shadow-sm"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="cp">Current password</label><input class="form-control" id="cp" type="password" name="current_password" required autocomplete="current-password"></div>
<div class="mb-3"><label class="form-label" for="np">New password</label><input class="form-control" id="np" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
<div class="mb-3"><label class="form-label" for="np2">Confirm new password</label><input class="form-control" id="np2" type="password" name="password2" required autocomplete="new-password"></div>
<button class="btn btn-dark">Update password</button></form></div></div>
