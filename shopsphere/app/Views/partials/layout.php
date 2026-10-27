<?php
use App\Services\CartService;
use App\Services\Catalog;
use App\Services\Delivery;

$u = current_user();
$cartCount = CartService::count();
$wishCount = $u ? count(Catalog::wishlistIds()) : 0;
$cmpCount = count(Catalog::compareIds());
$tree = Catalog::categoryTree();
$pin = Delivery::currentPin();
$q = $_GET['q'] ?? '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? cfg('app.name')) ?></title>
<meta name="description" content="<?= e($metaDescription ?? 'Shop smartphones, laptops, fashion, home & kitchen, beauty, grocery and more with demo prices in ₹ at ' . cfg('app.name')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(asset('css/style.css')) ?>?v=1" rel="stylesheet">
</head>
<body>
<a class="visually-hidden-focusable skip-link" href="#main">Skip to main content</a>

<?php if (cfg('app.demo_notice')): ?>
<div class="demo-bar text-center small py-1">Demo store · products, brands, prices, stock and reviews are <strong>sample data</strong>, not live market information.</div>
<?php endif; ?>

<header class="site-header sticky-top">
  <div class="container-xxl py-2">
    <div class="row align-items-center g-2">
      <div class="col-auto d-lg-none">
        <button class="btn btn-link text-white fs-4 p-0" data-bs-toggle="offcanvas" data-bs-target="#catMenu" aria-label="Open menu"><i class="bi bi-list"></i></button>
      </div>
      <div class="col-auto">
        <a href="<?= e(url('/')) ?>" class="logo text-decoration-none" aria-label="<?= e(cfg('app.name')) ?> home">
          <i class="bi bi-bag-heart-fill"></i><span class="logo-text">ShopSphere<small>India</small></span>
        </a>
      </div>
      <div class="col-auto d-none d-md-block">
        <button class="pin-btn" type="button" data-bs-toggle="modal" data-bs-target="#pinModal" aria-label="Choose delivery PIN code">
          <i class="bi bi-geo-alt"></i>
          <span class="d-block lh-1"><small>Deliver to</small><strong id="pinLabel"><?= $pin ? e($pin) : 'Select PIN' ?></strong></span>
        </button>
      </div>
      <div class="col-12 col-lg order-last order-lg-0 search-col">
        <form action="<?= e(url('/search')) ?>" method="get" class="search-form position-relative" role="search" autocomplete="off">
          <input type="search" name="q" id="searchInput" value="<?= e($q) ?>" class="form-control" placeholder="Search for products, brands and more" aria-label="Search products" maxlength="100">
          <button class="btn btn-search" type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
          <div id="suggestBox" class="suggest-box shadow" hidden></div>
        </form>
      </div>
      <div class="col col-lg-auto ms-auto">
        <ul class="nav justify-content-end flex-nowrap header-actions">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
              <i class="bi bi-person-circle"></i>
              <span class="d-none d-xl-inline"><?= $u ? e(explode(' ', $u['name'])[0]) : 'Sign in' ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <?php if ($u): ?>
                <li><h6 class="dropdown-header">Hello, <?= e($u['name']) ?></h6></li>
                <li><a class="dropdown-item" href="<?= e(url('/account')) ?>"><i class="bi bi-person me-2"></i>My account</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/account/orders')) ?>"><i class="bi bi-box-seam me-2"></i>My orders</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/wishlist')) ?>"><i class="bi bi-heart me-2"></i>Wishlist</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/account/addresses')) ?>"><i class="bi bi-geo-alt me-2"></i>Addresses</a></li>
                <?php if (is_staff()): ?><li><a class="dropdown-item" href="<?= e(url('/admin')) ?>"><i class="bi bi-speedometer2 me-2"></i>Admin dashboard</a></li><?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form></li>
              <?php else: ?>
                <li><a class="dropdown-item" href="<?= e(url('/login')) ?>"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/register')) ?>"><i class="bi bi-person-plus me-2"></i>Create account</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/track')) ?>"><i class="bi bi-truck me-2"></i>Track an order</a></li>
              <?php endif; ?>
            </ul>
          </li>
          <li class="nav-item d-none d-sm-block"><a class="nav-link position-relative" href="<?= e(url('/compare')) ?>" aria-label="Compare products"><i class="bi bi-arrow-left-right"></i><span class="count-badge" id="cmpCount" <?= $cmpCount ? '' : 'hidden' ?>><?= $cmpCount ?></span></a></li>
          <li class="nav-item"><a class="nav-link position-relative" href="<?= e(url('/wishlist')) ?>" aria-label="Wishlist"><i class="bi bi-heart"></i><span class="count-badge" id="wishCount" <?= $wishCount ? '' : 'hidden' ?>><?= $wishCount ?></span></a></li>
          <li class="nav-item"><a class="nav-link position-relative" href="<?= e(url('/cart')) ?>" aria-label="Cart"><i class="bi bi-cart3"></i><span class="d-none d-xl-inline ms-1">Cart</span><span class="count-badge" id="cartCount" <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span></a></li>
        </ul>
      </div>
    </div>
  </div>
  <nav class="cat-nav d-none d-lg-block" aria-label="Categories">
    <div class="container-xxl">
      <ul class="nav flex-nowrap">
        <li class="nav-item"><a class="nav-link deals-link" href="<?= e(url('/deals')) ?>"><i class="bi bi-lightning-charge-fill"></i> Today's Deals</a></li>
        <?php foreach ($tree as $c): ?>
          <li class="nav-item dropdown hover-dropdown">
            <a class="nav-link" href="<?= e(url('/category/' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
            <?php if ($c['children']): ?>
            <ul class="dropdown-menu shadow">
              <li><a class="dropdown-item fw-semibold" href="<?= e(url('/category/' . $c['slug'])) ?>">All <?= e($c['name']) ?></a></li>
              <?php foreach ($c['children'] as $s): ?><li><a class="dropdown-item" href="<?= e(url('/category/' . $s['slug'])) ?>"><?= e($s['name']) ?></a></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </nav>
</header>

<div class="offcanvas offcanvas-start" tabindex="-1" id="catMenu" aria-labelledby="catMenuLabel">
  <div class="offcanvas-header bg-navy text-white">
    <h5 class="offcanvas-title" id="catMenuLabel"><?= $u ? 'Hello, ' . e(explode(' ', $u['name'])[0]) : 'Browse ShopSphere' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
    <div class="list-group list-group-flush">
      <a class="list-group-item list-group-item-action fw-semibold text-danger" href="<?= e(url('/deals')) ?>"><i class="bi bi-lightning-charge-fill me-2"></i>Today's Deals</a>
      <?php foreach ($tree as $c): ?>
        <a class="list-group-item list-group-item-action" href="<?= e(url('/category/' . $c['slug'])) ?>"><i class="bi <?= e($c['icon'] ?: 'bi-tag') ?> me-2"></i><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a class="list-group-item list-group-item-action" href="<?= e(url('/track')) ?>"><i class="bi bi-truck me-2"></i>Track order</a>
      <a class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#pinModal" href="#" data-bs-dismiss="offcanvas"><i class="bi bi-geo-alt me-2"></i>Delivery PIN: <strong class="pin-text"><?= $pin ? e($pin) : 'Select' ?></strong></a>
    </div>
  </div>
</div>

<div class="modal fade" id="pinModal" tabindex="-1" aria-labelledby="pinModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="pinModalLabel">Choose your delivery location</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <form id="pinForm"><div class="modal-body">
      <p class="text-muted small mb-2">Enter your PIN code to see delivery estimates.</p>
      <div class="input-group"><input class="form-control" id="pinInput" inputmode="numeric" maxlength="6" pattern="[1-9][0-9]{5}" placeholder="6-digit PIN code" value="<?= e($pin) ?>" required aria-label="PIN code"><button class="btn btn-warning" type="submit">Apply</button></div>
      <div id="pinResult" class="small mt-2"></div>
    </div></form>
  </div></div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts" aria-live="polite" aria-atomic="true"></div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
    <div class="modal-body"><p class="mb-0" id="confirmText">Are you sure?</p></div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" id="confirmOk">Confirm</button></div>
  </div></div>
</div>

<main id="main" class="container-xxl py-3">
  <?php foreach (flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert"><?= e($f['msg']) ?><button class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>

<footer class="site-footer mt-5">
  <div class="container-xxl py-5">
    <div class="row g-4">
      <div class="col-6 col-md-3"><h6>Get to know us</h6><ul class="list-unstyled">
        <li><a href="<?= e(url('/page/about')) ?>">About ShopSphere</a></li><li><a href="<?= e(url('/page/contact')) ?>">Contact &amp; support</a></li><li><a href="<?= e(url('/deals')) ?>">Today's deals</a></li></ul></div>
      <div class="col-6 col-md-3"><h6>Help &amp; policies</h6><ul class="list-unstyled">
        <li><a href="<?= e(url('/track')) ?>">Track your order</a></li><li><a href="<?= e(url('/page/shipping-policy')) ?>">Shipping policy</a></li><li><a href="<?= e(url('/page/returns-policy')) ?>">Returns &amp; refunds</a></li>
        <li><a href="<?= e(url('/page/privacy')) ?>">Privacy</a></li><li><a href="<?= e(url('/page/terms')) ?>">Terms of use</a></li></ul></div>
      <div class="col-12 col-md-3"><h6>Stay connected</h6>
        <div class="social fs-4"><a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a><a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a><a href="#" aria-label="X"><i class="bi bi-twitter-x"></i></a><a href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a></div>
        <p class="small mt-2 mb-0">Customer care: <?= e(cfg('shop.seller.phone')) ?><br><?= e(cfg('shop.seller.email')) ?></p></div>
      <div class="col-12 col-md-3"><h6>Newsletter</h6>
        <form id="newsletterForm" class="input-group input-group-sm" novalidate><input type="email" class="form-control" placeholder="Your email" aria-label="Email for newsletter" required><button class="btn btn-warning" type="submit">Subscribe</button></form>
        <div class="small mt-1" id="newsletterMsg"></div></div>
    </div>
    <hr class="border-secondary mt-4">
    <div class="small d-flex flex-wrap justify-content-between gap-2">
      <span>© <?= date('Y') ?> <?= e(cfg('shop.seller.name')) ?> · <?= e(cfg('shop.seller.address')) ?></span>
      <span>Payments: Razorpay (test mode) · Cash on Delivery · All prices in ₹ and inclusive of GST</span>
    </div>
  </div>
</footer>

<script>window.SS = { base: <?= json_encode(base_path()) ?>, csrf: <?= json_encode(csrf_token()) ?>, loggedIn: <?= $u ? 'true' : 'false' ?>, placeholder: <?= json_encode(asset('img/placeholder.svg')) ?> };</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>?v=1"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>
