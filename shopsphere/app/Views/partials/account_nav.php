<?php $cur = request_path(); $links = ['/account' => ['bi-speedometer2', 'Overview'], '/account/orders' => ['bi-box-seam', 'My orders'], '/wishlist' => ['bi-heart', 'Wishlist'], '/account/addresses' => ['bi-geo-alt', 'Addresses'], '/account/profile' => ['bi-gear', 'Profile & settings']]; ?>
<nav class="list-group mb-3" aria-label="Account">
<?php foreach ($links as $href => [$ico, $label]): ?><a class="list-group-item list-group-item-action <?= $cur === $href ? 'active' : '' ?>" href="<?= e(url($href)) ?>"><i class="bi <?= $ico ?> me-2"></i><?= e($label) ?></a><?php endforeach; ?>
</nav>
