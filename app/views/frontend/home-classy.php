<?php
$pageTitle = 'Home';
$featured = Database::fetchAll("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.is_featured=1 ORDER BY p.id DESC LIMIT 8");
$newArrivals = Database::fetchAll("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.is_new=1 ORDER BY p.id DESC LIMIT 8");
$saleProducts = Database::fetchAll("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price ORDER BY p.id DESC LIMIT 8");
$cats = Database::fetchAll("SELECT * FROM categories WHERE status='active' ORDER BY sort_order LIMIT 10");

$banners = array();
try {
    $banners = Database::fetchAll("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
} catch (Exception $e) {
    $banners = array();
}

$slides = array();
if (empty($banners)) {
    $slides = Database::fetchAll(
        "SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.stock,
          (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC, sort_order LIMIT 1) as thumb
         FROM products p WHERE p.status='active' ORDER BY p.is_featured DESC, p.id DESC LIMIT 6"
    );
}

ob_start();
?>
<style>
/* ===== Classy standard homepage ===== */
.home-wrap { background: #fafafa; color: #111; }

/* Full-width tall slider */
.home-slider {
  position: relative;
  width: 100%;
  margin: 0;
  overflow: hidden;
  background: #0a0a0a;
  min-height: min(78vh, 620px);
  height: min(78vh, 620px);
  border-radius: 0;
  box-shadow: none;
}
.home-slider-track {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: min(78vh, 620px);
}
.home-slide {
  position: absolute;
  inset: 0;
  opacity: 0;
  visibility: hidden;
  transition: opacity .55s ease, visibility .55s ease;
  display: flex;
  align-items: center;
}
.home-slide.active { opacity: 1; visibility: visible; z-index: 1; }
.home-slide-bg {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
}
.home-slide-ph {
  position: absolute; inset: 0;
  background: linear-gradient(135deg, #111 0%, #333 100%);
}
.home-slide-shade {
  position: absolute; inset: 0; z-index: 1;
  background: linear-gradient(90deg, rgba(0,0,0,.65) 0%, rgba(0,0,0,.35) 45%, rgba(0,0,0,.2) 100%);
}
.home-slide-content {
  position: relative; z-index: 2;
  max-width: 560px;
  padding: 48px 24px;
  color: #fff;
}
.home-slide-content .badge {
  display: inline-block;
  background: #fff;
  color: #111;
  font-size: .72rem;
  font-weight: 700;
  padding: 6px 14px;
  border-radius: 999px;
  margin-bottom: 16px;
  letter-spacing: .06em;
  text-transform: uppercase;
}
.home-slide-content h1 {
  font-size: clamp(2rem, 4.5vw, 3.25rem);
  font-weight: 800;
  line-height: 1.12;
  margin: 0 0 14px;
  color: #fff;
  letter-spacing: -0.02em;
}
.home-slide-content p {
  font-size: 1.05rem;
  opacity: .9;
  margin: 0 0 24px;
  line-height: 1.55;
  max-width: 420px;
}
.home-slide-actions { display: flex; flex-wrap: wrap; gap: 12px; }
.home-slide-actions .btn-solid {
  background: #fff;
  color: #111;
  padding: 13px 26px;
  border-radius: 999px;
  font-weight: 700;
  text-decoration: none;
  border: 0;
  font-size: .95rem;
}
.home-slide-actions .btn-solid:hover { background: var(--color-primary, #e11d48); color: #fff; }
.home-slide-actions .btn-light-outline {
  border: 1.5px solid rgba(255,255,255,.85);
  color: #fff;
  background: transparent;
  padding: 12px 24px;
  border-radius: 999px;
  font-weight: 600;
  text-decoration: none;
  font-size: .95rem;
}
.home-slide-actions .btn-light-outline:hover { background: #fff; color: #111; }

.slider-nav {
  position: absolute; top: 50%; transform: translateY(-50%);
  z-index: 5;
  width: 48px; height: 48px;
  border-radius: 50%;
  border: 0;
  background: rgba(255,255,255,.92);
  color: #111;
  cursor: pointer;
  display: grid; place-items: center;
  box-shadow: 0 4px 16px rgba(0,0,0,.18);
  transition: background .15s, transform .15s;
}
.slider-nav:hover { background: #fff; transform: translateY(-50%) scale(1.05); }
.slider-nav.prev { left: 20px; }
.slider-nav.next { right: 20px; }

.slider-dots {
  position: absolute;
  bottom: 22px; left: 50%;
  transform: translateX(-50%);
  z-index: 5;
  display: flex; align-items: center; gap: 10px;
  background: rgba(0,0,0,.4);
  backdrop-filter: blur(6px);
  padding: 8px 16px;
  border-radius: 999px;
}
.slider-dots button {
  width: 8px; height: 8px; border-radius: 50%;
  border: 0; background: rgba(255,255,255,.45);
  cursor: pointer; padding: 0;
  transition: width .2s, background .2s;
}
.slider-dots button.active {
  background: #fff;
  width: 24px;
  border-radius: 999px;
}
.slider-count {
  color: #fff;
  font-size: .78rem;
  font-weight: 600;
  min-width: 32px;
  text-align: center;
}

/* Breadcrumb-style category bar */
.home-breadcrumb-bar {
  background: #fff;
  border-bottom: 1px solid #eee;
  padding: 14px 0;
}
.home-breadcrumb-bar .inner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.home-breadcrumb-bar a {
  display: inline-flex;
  align-items: center;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: .85rem;
  font-weight: 600;
  text-decoration: none;
  color: #444;
  background: #f4f4f5;
  border: 1px solid transparent;
  transition: background .15s, color .15s, border-color .15s;
}
.home-breadcrumb-bar a:hover {
  background: #111;
  color: #fff;
}
.home-breadcrumb-bar a.primary {
  background: #111;
  color: #fff;
}
.home-breadcrumb-bar .sep {
  width: 1px;
  height: 20px;
  background: #e5e5e5;
  margin: 0 4px;
}

/* Sections */
.home-section { padding: 48px 0; background: #fafafa; }
.home-section.alt { background: #fff; }
.home-section-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}
.home-section-head .label {
  font-size: .75rem;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: #888;
  margin-bottom: 6px;
  font-weight: 600;
}
.home-section-head h2 {
  margin: 0;
  font-size: 1.65rem;
  font-weight: 800;
  color: #111;
  letter-spacing: -0.02em;
}
.home-section-head a.view-all {
  font-size: .875rem;
  font-weight: 600;
  color: #111;
  text-decoration: none;
  border-bottom: 1px solid #111;
  padding-bottom: 2px;
}
.home-section-head a.view-all:hover { color: var(--color-primary, #e11d48); border-color: var(--color-primary, #e11d48); }

/* Clean product cards on home */
.home-wrap .product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 20px;
}
.home-wrap .product-card,
.home-wrap .apex-card {
  background: #fff;
  border: 1px solid #eee;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,.04);
  overflow: hidden;
  transition: transform .2s, box-shadow .2s;
}
.home-wrap .product-card:hover,
.home-wrap .apex-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px rgba(0,0,0,.08);
}
.home-wrap .product-name { color: #111 !important; font-weight: 600 !important; }
.home-wrap .product-cat { color: #888 !important; }
.home-wrap .price-current { color: #111 !important; font-weight: 800 !important; }
.home-wrap .btn-add-cart {
  background: #111 !important;
  color: #fff !important;
  border-radius: 8px !important;
}
.home-wrap .btn-quick-view {
  background: #111 !important;
  color: #fff !important;
  border: 0 !important;
}

/* Offers – simple elegant */
.offers-block {
  background: #111;
  padding: 56px 0;
  color: #fff;
}
.offers-block .home-section-head .label { color: rgba(255,255,255,.55); }
.offers-block .home-section-head h2 { color: #fff; }
.offers-block .view-all { color: #fff !important; border-color: rgba(255,255,255,.5) !important; }
.offers-block .product-card,
.offers-block .apex-card {
  border-color: transparent;
}

/* Features */
.features-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 24px;
  padding: 40px 0;
  border-top: 1px solid #eee;
}
.feat-item { text-align: center; padding: 8px; }
.feat-item i {
  font-size: 1.4rem;
  color: #111;
  margin-bottom: 10px;
  opacity: .85;
}
.feat-item h4 { margin: 0 0 6px; font-size: .95rem; font-weight: 700; color: #111; }
.feat-item p { margin: 0; font-size: .85rem; color: #777; line-height: 1.45; }

@media (max-width: 768px) {
  .home-slider, .home-slider-track {
    min-height: 52vh;
    height: 52vh;
  }
  .home-slide-content { padding: 32px 18px; }
  .slider-nav { width: 40px; height: 40px; }
  .slider-nav.prev { left: 10px; }
  .slider-nav.next { right: 10px; }
  .home-section { padding: 36px 0; }
}
</style>

<div class="home-wrap">

<!-- FULL WIDTH SLIDER -->
<section class="home-slider" id="home-slider">
  <div class="home-slider-track">
    <?php if (!empty($banners)): ?>
      <?php foreach ($banners as $i => $bn):
        $mainImg = '';
        if (!empty($bn['image'])) $mainImg = '/' . ltrim(str_replace('\\', '/', $bn['image']), '/');
        elseif (!empty($bn['bg_image'])) $mainImg = '/' . ltrim(str_replace('\\', '/', $bn['bg_image']), '/');
        $link = !empty($bn['btn_link']) ? $bn['btn_link'] : '/shop.php';
      ?>
      <div class="home-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
        <?php if ($mainImg): ?>
          <img src="<?= e($mainImg) ?>" alt="<?= e($bn['title'] ?: 'Banner') ?>" class="home-slide-bg">
        <?php else: ?>
          <div class="home-slide-ph"></div>
        <?php endif; ?>
        <div class="home-slide-shade"></div>
        <div class="container" style="position:relative;z-index:2;width:100%">
          <div class="home-slide-content">
            <?php if (!empty($bn['badge_text'])): ?><span class="badge"><?= e($bn['badge_text']) ?></span><?php endif; ?>
            <?php if (!empty($bn['title'])): ?><h1><?= e($bn['title']) ?></h1><?php endif; ?>
            <?php if (!empty($bn['subtitle'])): ?><p><?= e($bn['subtitle']) ?></p>
            <?php elseif (!empty($bn['description'])): ?><p><?= e($bn['description']) ?></p><?php endif; ?>
            <div class="home-slide-actions">
              <a href="<?= e($link) ?>" class="btn-solid"><?= e(!empty($bn['btn_text']) ? $bn['btn_text'] : 'Shop Now') ?></a>
              <?php if (!empty($bn['btn2_text'])): ?>
              <a href="<?= e($bn['btn2_link'] ?: '/shop.php') ?>" class="btn-light-outline"><?= e($bn['btn2_text']) ?></a>
              <?php else: ?>
              <a href="/shop.php?filter=new" class="btn-light-outline">New Arrivals</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php elseif (!empty($slides)): ?>
      <?php foreach ($slides as $i => $s):
        $img = !empty($s['thumb']) ? ProductImage::url($s['thumb']) : '';
      ?>
      <div class="home-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($s['name']) ?>" class="home-slide-bg"><?php else: ?><div class="home-slide-ph"></div><?php endif; ?>
        <div class="home-slide-shade"></div>
        <div class="container" style="position:relative;z-index:2;width:100%">
          <div class="home-slide-content">
            <h1><?= e($s['name']) ?></h1>
            <p><?= money(productPrice($s)) ?></p>
            <div class="home-slide-actions">
              <a href="/index.php?route=product&slug=<?= e($s['slug']) ?>" class="btn-solid">View Product</a>
              <a href="/shop.php" class="btn-light-outline">Shop All</a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="home-slide active" data-index="0">
        <div class="home-slide-ph"></div>
        <div class="home-slide-shade"></div>
        <div class="container" style="position:relative;z-index:2;width:100%">
          <div class="home-slide-content">
            <span class="badge">New Season</span>
            <h1>Define Your Style</h1>
            <p>Premium fashion for every occasion.</p>
            <div class="home-slide-actions">
              <a href="/shop.php" class="btn-solid">Shop Now</a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php $slideCount = !empty($banners) ? count($banners) : count($slides); if ($slideCount < 1) $slideCount = 1; ?>
  <?php if ($slideCount > 1): ?>
  <button type="button" class="slider-nav prev" id="hs-prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
  <button type="button" class="slider-nav next" id="hs-next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
  <div class="slider-dots" id="hs-dots">
    <?php for ($i = 0; $i < $slideCount; $i++): ?>
      <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></button>
    <?php endfor; ?>
    <span class="slider-count" id="hs-count">1/<?= (int)$slideCount ?></span>
  </div>
  <?php endif; ?>
</section>

<script>
(function(){
  var root = document.getElementById('home-slider');
  if (!root) return;
  var slides = root.querySelectorAll('.home-slide');
  var dots = root.querySelectorAll('#hs-dots button');
  var countEl = document.getElementById('hs-count');
  var n = slides.length;
  if (n < 2) return;
  var i = 0, timer = null;
  function go(to) {
    i = (to + n) % n;
    slides.forEach(function(s, k){ s.classList.toggle('active', k === i); });
    dots.forEach(function(d, k){ d.classList.toggle('active', k === i); });
    if (countEl) countEl.textContent = (i + 1) + '/' + n;
  }
  function next(){ go(i + 1); }
  function prev(){ go(i - 1); }
  function start(){ stop(); timer = setInterval(next, 5000); }
  function stop(){ if (timer) clearInterval(timer); timer = null; }
  var nextBtn = document.getElementById('hs-next');
  var prevBtn = document.getElementById('hs-prev');
  if (nextBtn) nextBtn.addEventListener('click', function(){ next(); start(); });
  if (prevBtn) prevBtn.addEventListener('click', function(){ prev(); start(); });
  dots.forEach(function(d){
    d.addEventListener('click', function(){ go(parseInt(d.getAttribute('data-index'), 10) || 0); start(); });
  });
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  start();
})();
</script>

<!-- Category bar (clean) -->
<nav class="home-breadcrumb-bar" aria-label="Shop categories">
  <div class="container">
    <div class="inner">
      <a href="/shop.php?gender=men" class="primary">Men</a>
      <a href="/shop.php?gender=women" class="primary">Women</a>
      <a href="/shop.php?gender=kids" class="primary">Kids</a>
      <a href="/shop.php" class="primary">Shop All</a>
      <span class="sep" aria-hidden="true"></span>
      <a href="/shop.php">All</a>
      <a href="/shop.php?filter=sale">Sale</a>
      <a href="/shop.php?filter=new">New</a>
      <?php foreach ($cats as $c): ?>
      <a href="/index.php?route=category&slug=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</nav>

<!-- Featured -->
<section class="home-section">
  <div class="container">
    <div class="home-section-head">
      <div>
        <div class="label">Featured</div>
        <h2>The Collection</h2>
      </div>
      <a href="/shop.php" class="view-all">View all →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $p): ?><?= render_product_card($p) ?><?php endforeach; ?>
      <?php if (empty($featured)): ?><p style="color:#888">No featured products yet.</p><?php endif; ?>
    </div>
  </div>
</section>

<!-- New Arrivals -->
<section class="home-section alt">
  <div class="container">
    <div class="home-section-head">
      <div>
        <div class="label">Just Dropped</div>
        <h2>New Arrivals</h2>
      </div>
      <a href="/shop.php?filter=new" class="view-all">View all →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($newArrivals as $p): ?><?= render_product_card($p) ?><?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!empty($saleProducts)): ?>
<section class="offers-block">
  <div class="container">
    <div class="home-section-head">
      <div>
        <div class="label">Limited time</div>
        <h2>Sale &amp; Offers</h2>
      </div>
      <a href="/shop.php?filter=sale" class="view-all">View all →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($saleProducts as $p): ?><?= render_product_card($p) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="home-section alt">
  <div class="container">
    <div class="features-row">
      <div class="feat-item"><i class="fas fa-truck"></i><h4>Fast Delivery</h4><p>Delivery across Bangladesh</p></div>
      <div class="feat-item"><i class="fas fa-shield-alt"></i><h4>Secure Payment</h4><p>COD, bKash, Nagad, Card</p></div>
      <div class="feat-item"><i class="fas fa-undo"></i><h4>Easy Returns</h4><p>Simple exchange policy</p></div>
      <div class="feat-item"><i class="fas fa-headset"></i><h4>Support</h4><p>Help with orders &amp; sizing</p></div>
    </div>
  </div>
</section>

<?php
  $reviews = array();
  try {
      $reviews = Database::fetchAll(
          "SELECT r.customer_name as name, r.comment as text, r.rating as stars, p.name as product_name
           FROM product_reviews r LEFT JOIN products p ON p.id = r.product_id
           WHERE r.status='approved' AND r.show_on_home=1 ORDER BY r.created_at DESC LIMIT 6"
      );
  } catch (Exception $e) { $reviews = array(); }
?>
<?php if (!empty($reviews)): ?>
<section class="home-section">
  <div class="container">
    <div class="home-section-head" style="justify-content:center;text-align:center">
      <div>
        <div class="label">Testimonials</div>
        <h2>What Customers Say</h2>
      </div>
    </div>
    <div class="reviews-grid">
      <?php foreach ($reviews as $rv): ?>
      <div class="review-card" style="background:#fff;border:1px solid #eee;border-radius:12px;padding:20px">
        <div class="review-stars">
          <?php for ($s=1;$s<=5;$s++): ?>
            <i class="fas fa-star" style="color:<?= $s <= (int)$rv['stars'] ? '#f59e0b' : '#ddd' ?>"></i>
          <?php endfor; ?>
        </div>
        <p class="review-text" style="color:#333">"<?= e($rv['text']) ?>"</p>
        <div class="review-name" style="color:#888">— <?= e($rv['name']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

</div><!-- /.home-wrap -->

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/frontend.php';
