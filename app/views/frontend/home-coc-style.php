<?php
$pageTitle = 'Home';
$featured = Database::fetchAll("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.is_featured=1 ORDER BY p.id DESC LIMIT 8");
$newArrivals = Database::fetchAll("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.is_new=1 ORDER BY p.id DESC LIMIT 8");
$saleProducts = Database::fetchAll("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price ORDER BY p.id DESC LIMIT 8");
$cats = Database::fetchAll("SELECT * FROM categories WHERE status='active' ORDER BY sort_order LIMIT 8");

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
/* ===== Store-style homepage (inspired layout) ===== */
.store-page-bg {
  background:
    radial-gradient(ellipse at 20% 0%, rgba(180,120,60,.18), transparent 50%),
    radial-gradient(ellipse at 80% 100%, rgba(120,70,30,.15), transparent 45%),
    linear-gradient(180deg, #3d2914 0%, #5c3d1e 35%, #4a3218 70%, #2f1f0e 100%);
  background-attachment: fixed;
  min-height: 100%;
  padding-bottom: 40px;
}
.store-page-bg .apex-hero-btns a,
.store-page-bg .cat-pill {
  background: rgba(255,248,235,.95);
  border-color: rgba(0,0,0,.12);
  color: #2a1a0a;
}
.store-page-bg .apex-hero-btns a:hover,
.store-page-bg .cat-pill:hover {
  background: #1a1208;
  color: #fff;
  border-color: #1a1208;
}
.store-page-bg .apex-section-head h2,
.store-page-bg .apex-section-head .sub { color: #fff8e7; text-shadow: 0 2px 8px rgba(0,0,0,.35); }
.store-page-bg .apex-section-head .sub { opacity: .85; }
.store-page-bg .btn-outline {
  background: rgba(255,248,235,.15);
  border-color: rgba(255,248,235,.45);
  color: #fff8e7;
}
.store-page-bg .btn-outline:hover { background: #fff8e7; color: #2a1a0a; }
.store-page-bg .apex-feat h4 { color: #fff8e7; }
.store-page-bg .apex-feat p { color: rgba(255,248,235,.75); }
.store-page-bg .apex-feat i { color: #f5c542; }
.store-page-bg .apex-features {
  border-color: rgba(255,248,235,.15);
  background: rgba(0,0,0,.15);
  border-radius: 16px;
  padding: 28px 16px;
  margin: 24px 0;
}
.store-page-bg .text-muted { color: rgba(255,248,235,.7) !important; }

/* Slider */
.home-slider{position:relative;width:100%;max-width:1100px;margin:24px auto 0;overflow:hidden;background:#1a1208;min-height:min(62vh,480px);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.45), inset 0 0 0 3px rgba(245,197,66,.35)}
.home-slider-track{position:relative;width:100%;min-height:min(62vh,480px)}
.home-slide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .6s ease,visibility .6s ease;display:flex;align-items:center}
.home-slide.active{opacity:1;visibility:visible;z-index:1}
.home-slide-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.home-slide-ph{position:absolute;inset:0;background:linear-gradient(135deg,#2a1a0a,#5c3d1e)}
.home-slide-shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.75) 0%,rgba(0,0,0,.4) 50%,rgba(0,0,0,.2) 100%);z-index:1}
.home-slide-content{position:relative;z-index:2;max-width:520px;padding:40px 28px;color:#fff}
.home-slide-content .badge{display:inline-block;background:#f5c542;color:#1a1208;font-size:.75rem;font-weight:800;padding:5px 12px;border-radius:999px;margin-bottom:12px;text-transform:uppercase}
.home-slide-content h1{font-size:clamp(1.7rem,3.5vw,2.6rem);font-weight:800;line-height:1.15;margin:0 0 10px;color:#fff;text-shadow:0 2px 12px rgba(0,0,0,.5)}
.home-slide-content p{font-size:1rem;opacity:.95;margin:0 0 18px;line-height:1.5}
.home-slide-actions{display:flex;flex-wrap:wrap;gap:10px}
.home-slide-actions .btn-solid{background:#f5c542;color:#1a1208;padding:12px 22px;border-radius:999px;font-weight:800;text-decoration:none;border:0;box-shadow:0 4px 0 #b8860b}
.home-slide-actions .btn-solid:hover{filter:brightness(1.08)}
.home-slide-actions .btn-light-outline{border:2px solid rgba(255,255,255,.75);color:#fff;background:transparent;padding:10px 18px;border-radius:999px;font-weight:700;text-decoration:none}
.slider-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:5;width:48px;height:48px;border-radius:50%;border:3px solid #f5c542;background:linear-gradient(180deg,#ffe08a,#d4a017);color:#1a1208;cursor:pointer;display:grid;place-items:center;font-size:1.1rem;box-shadow:0 4px 12px rgba(0,0,0,.35)}
.slider-nav.prev{left:12px}
.slider-nav.next{right:12px}
.slider-dots{position:absolute;bottom:14px;left:50%;transform:translateX(-50%);z-index:5;display:flex;align-items:center;gap:10px;background:rgba(0,0,0,.35);padding:6px 14px;border-radius:999px}
.slider-dots button{width:10px;height:10px;border-radius:50%;border:0;background:rgba(255,255,255,.4);cursor:pointer;padding:0}
.slider-dots button.active{background:#f5c542;width:28px;border-radius:999px}
.slider-count{color:#fff;font-size:.8rem;font-weight:700;min-width:28px;text-align:center}

.apex-hero-btns{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;padding:22px 16px 10px}
.apex-hero-btns a{display:inline-flex;align-items:center;justify-content:center;min-width:100px;padding:11px 20px;border:2px solid rgba(0,0,0,.1);border-radius:999px;font-weight:700;font-size:.9rem;text-decoration:none;box-shadow:0 3px 0 rgba(0,0,0,.12)}
.apex-section{padding:36px 0}
.apex-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:22px;flex-wrap:wrap}
.apex-section-head h2{margin:0;font-size:1.75rem;font-weight:900;letter-spacing:-.01em}
.apex-section-head .sub{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}

/* Product cards – pack style */
.store-page-bg .product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 20px;
}
.store-page-bg .product-card,
.store-page-bg .apex-card {
  background: linear-gradient(180deg, #fff8e7 0%, #f3e6c8 100%);
  border: 3px solid #c9a227;
  border-radius: 18px;
  box-shadow: 0 8px 0 rgba(0,0,0,.2), 0 12px 28px rgba(0,0,0,.25);
  overflow: hidden;
  transition: transform .2s, box-shadow .2s;
}
.store-page-bg .product-card:hover,
.store-page-bg .apex-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 0 rgba(0,0,0,.18), 0 18px 36px rgba(0,0,0,.3);
}
.store-page-bg .product-thumb {
  background: #efe0c0 !important;
  border-bottom: 2px solid rgba(201,162,39,.4);
  aspect-ratio: 1 / 1.05;
}
.store-page-bg .product-body { padding: 12px 14px 14px; }
.store-page-bg .product-name {
  color: #2a1a0a !important;
  font-weight: 800 !important;
  font-size: .95rem !important;
}
.store-page-bg .product-cat { color: #7a5c2e !important; font-weight: 600; }
.store-page-bg .price-current {
  color: #1a1208 !important;
  font-weight: 900 !important;
  font-size: 1.05rem !important;
}
.store-page-bg .btn-add-cart {
  background: #1a1208 !important;
  color: #f5c542 !important;
  border-radius: 999px !important;
  box-shadow: 0 3px 0 #000;
}
.store-page-bg .btn-quick-view {
  background: #1a1208 !important;
  color: #fff !important;
  border: 2px solid #f5c542 !important;
}

/* Offers section – night sky style */
.offers-section {
  position: relative;
  margin: 32px 16px;
  padding: 48px 20px 40px;
  border-radius: 24px;
  overflow: hidden;
  background:
    radial-gradient(ellipse at 50% 120%, rgba(255,140,60,.35), transparent 55%),
    radial-gradient(circle at 15% 30%, rgba(120,80,200,.4), transparent 40%),
    radial-gradient(circle at 85% 25%, rgba(60,40,120,.5), transparent 45%),
    linear-gradient(180deg, #1a1040 0%, #2d1b69 40%, #4a2060 70%, #8b3a2a 100%);
  box-shadow: 0 16px 40px rgba(0,0,0,.4);
}
.offers-section::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(2px 2px at 20% 30%, rgba(255,255,255,.5), transparent),
    radial-gradient(2px 2px at 60% 20%, rgba(255,255,255,.35), transparent),
    radial-gradient(1px 1px at 80% 50%, rgba(255,255,255,.4), transparent),
    radial-gradient(2px 2px at 40% 70%, rgba(255,255,255,.25), transparent);
  pointer-events: none;
}
.offers-ribbon {
  position: relative;
  z-index: 1;
  text-align: center;
  margin-bottom: 28px;
}
.offers-ribbon h2 {
  display: inline-block;
  margin: 0;
  padding: 10px 48px 14px;
  font-size: 2rem;
  font-weight: 900;
  color: #fff;
  text-shadow: 0 3px 0 rgba(0,0,0,.35);
  background: linear-gradient(180deg, #e63946, #c1121f);
  border-radius: 8px;
  box-shadow: 0 6px 0 #7a0c14, 0 10px 24px rgba(0,0,0,.35);
  transform: rotate(-2deg);
  letter-spacing: .02em;
}
.offers-section .product-grid { position: relative; z-index: 1; }
.offers-section .product-card,
.offers-section .apex-card {
  background: linear-gradient(180deg, #e8d4ff 0%, #c9b0f0 100%) !important;
  border-color: #8b6cc9 !important;
}
.offers-section .product-thumb { background: #dcc8f8 !important; }

.section-title-glow {
  text-align: center;
  width: 100%;
  margin-bottom: 8px;
}
.section-title-glow h2 {
  font-size: 2rem !important;
  font-weight: 900 !important;
  color: #fff8e7 !important;
  text-shadow: 0 0 20px rgba(245,197,66,.45), 0 3px 0 rgba(0,0,0,.4);
}

@media(max-width:700px){
  .home-slider,.home-slider-track{min-height:380px;margin:12px;border-radius:14px}
  .home-slide-content{padding:28px 16px}
  .slider-nav{width:40px;height:40px}
  .offers-ribbon h2{font-size:1.5rem;padding:8px 28px}
}
</style>

<div class="store-page-bg">

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
            <h1>Welcome to AK Store</h1>
            <p>Premium fashion for every occasion.</p>
            <div class="home-slide-actions"><a href="/shop.php" class="btn-solid">Shop Now</a></div>
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
  function start(){ stop(); timer = setInterval(next, 4500); }
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

<div class="apex-hero-btns">
  <a href="/shop.php?gender=men">Men</a>
  <a href="/shop.php?gender=women">Women</a>
  <a href="/shop.php?gender=kids">Kids</a>
  <a href="/shop.php">Shop All</a>
</div>

<section class="apex-section" style="padding-top:12px;padding-bottom:4px">
  <div class="container">
    <div class="cat-pills" style="justify-content:center;flex-wrap:wrap;gap:8px">
      <a href="/shop.php" class="cat-pill">All</a>
      <a href="/shop.php?filter=sale" class="cat-pill">Sale</a>
      <a href="/shop.php?filter=new" class="cat-pill">New</a>
      <?php foreach ($cats as $c): ?>
      <a href="/index.php?route=category&slug=<?= e($c['slug']) ?>" class="cat-pill"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="apex-section">
  <div class="container">
    <div class="apex-section-head" style="justify-content:center;text-align:center;flex-direction:column;align-items:center">
      <div class="section-title-glow">
        <div class="sub" style="color:rgba(255,248,235,.8)">Featured</div>
        <h2>Store Specials</h2>
      </div>
      <p style="color:rgba(255,248,235,.75);margin:4px 0 0;max-width:480px">Browse our featured products. Quality fashion packs for every style.</p>
      <a href="/shop.php" class="btn btn-outline btn-sm" style="margin-top:12px">View All</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $p): ?><?= render_product_card($p) ?><?php endforeach; ?>
      <?php if (empty($featured)): ?><p class="text-muted">No featured products yet.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="apex-section">
  <div class="container">
    <div class="apex-section-head" style="justify-content:center;text-align:center;flex-direction:column;align-items:center">
      <div class="section-title-glow">
        <div class="sub" style="color:rgba(255,248,235,.8)">Just Dropped</div>
        <h2>New Arrivals</h2>
      </div>
      <a href="/shop.php?filter=new" class="btn btn-outline btn-sm" style="margin-top:10px">View All</a>
    </div>
    <div class="product-grid">
      <?php foreach ($newArrivals as $p): ?><?= render_product_card($p) ?><?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!empty($saleProducts)): ?>
<section class="offers-section">
  <div class="offers-ribbon"><h2>Offers</h2></div>
  <div class="container">
    <div class="product-grid">
      <?php foreach ($saleProducts as $p): ?><?= render_product_card($p) ?><?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:20px;position:relative;z-index:1">
      <a href="/shop.php?filter=sale" class="btn btn-outline btn-sm">View All Offers</a>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="container">
  <div class="apex-features">
    <div class="apex-feat"><i class="fas fa-truck"></i><h4>Fast Delivery</h4><p>Quick delivery to your doorstep across Bangladesh.</p></div>
    <div class="apex-feat"><i class="fas fa-shield-alt"></i><h4>Secure Payment</h4><p>COD, bKash, Nagad and card — safe checkout.</p></div>
    <div class="apex-feat"><i class="fas fa-undo"></i><h4>Easy Returns</h4><p>Hassle-free exchange and return on eligible items.</p></div>
    <div class="apex-feat"><i class="fas fa-headset"></i><h4>Support</h4><p>We are here to help with orders and sizing.</p></div>
  </div>
</div>

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
<section class="apex-section reviews-section">
  <div class="container">
    <div class="apex-section-head" style="justify-content:center;text-align:center">
      <div class="section-title-glow"><div class="sub">Testimonials</div><h2>What Customers Say</h2></div>
    </div>
    <div class="reviews-grid">
      <?php foreach ($reviews as $rv): ?>
      <div class="review-card" style="background:rgba(255,248,235,.95);border-radius:14px">
        <div class="review-stars">
          <?php for ($s=1;$s<=5;$s++): ?>
            <i class="fas fa-star" style="color:<?= $s <= (int)$rv['stars'] ? '#f59e0b' : '#d4d4d4' ?>"></i>
          <?php endfor; ?>
        </div>
        <p class="review-text" style="color:#2a1a0a">"<?= e($rv['text']) ?>"</p>
        <div class="review-name" style="color:#5c4030">— <?= e($rv['name']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

</div><!-- /.store-page-bg -->

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/frontend.php';
