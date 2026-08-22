<?php
$pageTitle = 'Home';
$featured = Database::fetchAll("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.is_featured=1 ORDER BY p.id DESC LIMIT 8");
$newArrivals = Database::fetchAll("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.is_new=1 ORDER BY p.id DESC LIMIT 4");
$cats = Database::fetchAll("SELECT * FROM categories WHERE status='active' ORDER BY sort_order LIMIT 6");

$banners = array();
try {
    $banners = Database::fetchAll("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
} catch (Exception $e) {
    $banners = array();
}

$slides = array();
if (empty($banners)) {
    $slides = Database::fetchAll(
        "SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.stock, p.image,
          (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC, sort_order LIMIT 1) as thumb
         FROM products p WHERE p.status='active' ORDER BY p.is_featured DESC, p.id DESC LIMIT 6"
    );
}

ob_start();
?>
<section class="hero hero-with-slider pro-hero classy-hero" id="home-hero">
  <div class="hero-bg">
    <div class="hero-blur-bg" id="hero-blur-bg"></div>
    <div class="hero-blur-overlay"></div>
  </div>

  <?php if (!empty($banners)): ?>
  <div class="container hero-slider-full" id="hero-slider">
    <?php foreach ($banners as $i => $bn):
      $mainImg = !empty($bn['image']) ? '/' . ltrim($bn['image'], '/') : '';
      $bgImg = !empty($bn['bg_image']) ? '/' . ltrim($bn['bg_image'], '/') : $mainImg;
      $align = isset($bn['text_align']) ? $bn['text_align'] : 'left';
    ?>
    <div class="slide classy-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>" data-bg="<?= e($bgImg) ?>">
      <div class="hero-grid classy-grid align-<?= e($align) ?>">
        <div class="hero-content classy-content">
          <?php if (!empty($bn['badge_text'])): ?>
          <div class="hero-eyebrow"><?= e($bn['badge_text']) ?></div>
          <?php endif; ?>
          <h1><?= e($bn['title']) ?></h1>
          <?php if (!empty($bn['subtitle'])): ?>
          <p class="classy-sub"><?= e($bn['subtitle']) ?></p>
          <?php endif; ?>
          <?php if (!empty($bn['description'])): ?>
          <p class="hero-lead"><?= e($bn['description']) ?></p>
          <?php endif; ?>
          <div class="hero-actions">
            <?php if (!empty($bn['btn_text'])): ?>
            <a href="<?= e($bn['btn_link'] ?: '/shop.php') ?>" class="btn btn-primary btn-lg"><?= e($bn['btn_text']) ?></a>
            <?php endif; ?>
            <?php if (!empty($bn['btn2_text'])): ?>
            <a href="<?= e($bn['btn2_link'] ?: '/shop.php') ?>" class="btn btn-outline btn-lg"><?= e($bn['btn2_text']) ?></a>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($mainImg): ?>
        <div class="classy-visual">
          <div class="classy-frame">
            <img src="<?= e($mainImg) ?>" alt="<?= e($bn['title']) ?>" class="slide-img">
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (count($banners) > 1): ?>
    <button type="button" class="slider-btn slider-prev" id="slide-prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
    <button type="button" class="slider-btn slider-next" id="slide-next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
    <div class="slider-dots" id="slider-dots">
      <?php foreach ($banners as $i => $bn): ?>
      <button type="button" class="dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="container hero-grid">
    <div class="hero-content">
      <div class="hero-eyebrow">New Season 2026</div>
      <h1>Define Your<br><span class="text-gradient">Style</span> with AK</h1>
      <p class="hero-lead">Premium clothing for men and women. Quality fabrics, timeless designs.</p>
      <div class="hero-actions">
        <a href="/shop.php" class="btn btn-primary btn-lg">Shop Now</a>
        <a href="/shop.php?filter=new" class="btn btn-outline btn-lg">New Arrivals</a>
      </div>
    </div>
    <div class="hero-slider pro-slider" id="hero-slider">
      <?php foreach ($slides as $i => $s):
        $img = ProductImage::productThumb($s);
      ?>
      <div class="slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>" data-bg="<?= e($img ?: '') ?>">
        <a href="/index.php?route=product&slug=<?= e($s['slug']) ?>" class="slide-link">
          <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($s['name']) ?>" class="slide-img"><?php endif; ?>
          <div class="slide-caption"><strong><?= e($s['name']) ?></strong><span><?= money(productPrice($s)) ?></span></div>
        </a>
      </div>
      <?php endforeach; ?>
      <?php if (count($slides) > 1): ?>
      <button type="button" class="slider-btn slider-prev" id="slide-prev"><i class="fas fa-chevron-left"></i></button>
      <button type="button" class="slider-btn slider-next" id="slide-next"><i class="fas fa-chevron-right"></i></button>
      <div class="slider-dots" id="slider-dots">
        <?php foreach ($slides as $i => $s): ?>
        <button type="button" class="dot <?= $i===0?'active':'' ?>" data-index="<?= $i ?>"></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</section>

<section class="section-sm pro-cats">
  <div class="container">
    <div class="section-header" style="margin-bottom:16px;justify-content:center;text-align:center">
      <div>
        <p class="text-muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Browse</p>
        <h2>Shop by Category</h2>
      </div>
    </div>
    <div class="cat-pills" style="justify-content:center">
      <a href="/shop.php" class="cat-pill">All</a>
      <?php foreach ($cats as $c): ?>
      <a href="/index.php?route=category&slug=<?= e($c['slug']) ?>" class="cat-pill"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-header">
      <div>
        <p class="text-muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Featured</p>
        <h2>Featured Products</h2>
      </div>
      <a href="/shop.php" class="btn btn-outline btn-sm">View all</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $p): ?>
        <?= render_product_card($p) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="background:color-mix(in srgb,var(--color-surface) 50%,transparent)">
  <div class="container">
    <div class="section-header">
      <div>
        <p class="text-muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Just Dropped</p>
        <h2>New Arrivals</h2>
      </div>
    </div>
    <div class="product-grid">
      <?php foreach ($newArrivals as $p): ?>
        <?= render_product_card($p) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/frontend.php';