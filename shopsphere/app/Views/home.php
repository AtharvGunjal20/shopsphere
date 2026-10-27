<?php
use App\Services\Catalog;
$tree = Catalog::categoryTree();
$slides = [
    ['class' => 'slide-1', 'kicker' => 'Mega Savings', 'title' => 'Up to 60% off on electronics', 'text' => 'Smartphones, laptops, headphones & TVs at demo-store prices.', 'cta' => 'Shop electronics', 'href' => url('category/mobiles')],
    ['class' => 'slide-2', 'kicker' => 'Fashion Fiesta', 'title' => 'Fresh styles from ₹599', 'text' => 'Ethnic wear, western wear, footwear and watches.', 'cta' => 'Explore fashion', 'href' => url('category/womens-fashion')],
    ['class' => 'slide-3', 'kicker' => 'Home Makeover', 'title' => 'Appliances & decor for every room', 'text' => 'Refrigerators, ACs, furniture, cookware and more.', 'cta' => 'Upgrade your home', 'href' => url('category/appliances')],
    ['class' => 'slide-4', 'kicker' => 'Today\'s Deals', 'title' => 'Lightning deals, 30%+ off', 'text' => 'Handpicked discounts refreshed from the catalogue.', 'cta' => 'View all deals', 'href' => url('deals')],
];
?>
<div id="heroCarousel" class="carousel slide hero mb-3" data-bs-ride="carousel" aria-label="Promotions">
  <div class="carousel-indicators">
    <?php foreach ($slides as $i => $s): ?><button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-label="Slide <?= $i + 1 ?>"></button><?php endforeach; ?>
  </div>
  <div class="carousel-inner rounded-3 overflow-hidden">
    <?php foreach ($slides as $i => $s): ?>
    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
      <div class="hero-slide <?= e($s['class']) ?>">
        <div class="hero-copy">
          <span class="badge text-bg-warning mb-2"><?= e($s['kicker']) ?></span>
          <h1 class="display-6 fw-bold"><?= e($s['title']) ?></h1>
          <p class="lead d-none d-sm-block"><?= e($s['text']) ?></p>
          <a href="<?= e($s['href']) ?>" class="btn btn-light btn-lg fw-semibold"><?= e($s['cta']) ?> <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous</span></button>
  <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next</span></button>
</div>

<section class="cat-tiles mb-4" aria-label="Shop by category">
  <?php foreach ($tree as $c): ?>
    <a href="<?= e(url('category/' . $c['slug'])) ?>" class="cat-tile"><span class="cat-ico"><i class="bi <?= e($c['icon'] ?: 'bi-tag') ?>"></i></span><span><?= e($c['name']) ?></span></a>
  <?php endforeach; ?>
</section>

<?php if ($coupons): ?>
<section class="offer-strip mb-4" aria-label="Offers">
  <?php foreach ($coupons as $c): ?>
    <div class="offer"><i class="bi bi-ticket-perforated-fill"></i><div><strong>Use code <?= e($c['code']) ?></strong><div class="small"><?= e($c['description']) ?></div></div></div>
  <?php endforeach; ?>
  <div class="offer"><i class="bi bi-truck"></i><div><strong>Free delivery</strong><div class="small">on orders of <?= e(inr(cfg('shop.free_shipping_min'))) ?>+</div></div></div>
</section>
<?php endif; ?>

<?php partial('row', ['heading' => "Today's Deals", 'items' => $deals, 'link' => url('deals'), 'id' => 'deals']); ?>
<?php partial('row', ['heading' => 'Bestsellers', 'items' => $bestsellers, 'link' => url('search?sort=popularity')]); ?>
<?php partial('row', ['heading' => 'Recommended for you', 'items' => $recommended, 'link' => null]); ?>
<?php partial('row', ['heading' => 'New arrivals', 'items' => $new, 'link' => url('search?sort=newest')]); ?>
<?php if ($recent): partial('row', ['heading' => 'Recently viewed', 'items' => $recent, 'link' => null]); endif; ?>

<?php foreach ($sections as $s): partial('row', ['heading' => $s['title'], 'items' => $s['items'], 'link' => url('category/' . $s['slug'])]); endforeach; ?>

<?php if ($reviews): ?>
<section class="mb-4" aria-label="Customer reviews">
  <h2 class="h5 fw-bold mb-2">What customers are saying <small class="text-muted fw-normal">(demo reviews)</small></h2>
  <div class="row g-3">
    <?php foreach ($reviews as $r): ?>
      <div class="col-12 col-md-6 col-xl-4"><div class="review-card h-100">
        <div class="text-warning"><?= stars((float)$r['rating']) ?></div>
        <strong><?= e($r['title']) ?></strong>
        <p class="mb-1 small"><?= e($r['body']) ?></p>
        <div class="small text-muted">— <?= e($r['user_name']) ?> on <a href="<?= e(url('product/' . $r['slug'])) ?>"><?= e($r['product_name']) ?></a></div>
      </div></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="newsletter-band rounded-3 p-4 text-center mb-2">
  <h2 class="h5 fw-bold">Get deals in your inbox</h2>
  <p class="mb-2 small">Subscribe to hear about new arrivals and festive offers.</p>
  <p class="small mb-0"><a href="#newsletterForm" class="text-white text-decoration-underline">Jump to subscription form in the footer</a></p>
</section>
