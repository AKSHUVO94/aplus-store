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
.home-slider{position:relative;width:100%;overflow:hidden;background:#111;min-height:min(72vh,560px)}
.home-slider-track{position:relative;width:100%;min-height:min(72vh,560px)}
.home-slide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .6s ease,visibility .6s ease;display:flex;align-items:center}
.home-slide.active{opacity:1;visibility:visible;z-index:1}
.home-slide-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.home-slide-ph{position:absolute;inset:0;background:linear-gradient(135deg,#1a1a1a,#333)}
.home-slide-shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.72) 0%,rgba(0,0,0,.35) 55%,rgba(0,0,0,.15) 100%);z-index:1}
.home-slide-content{position:relative;z-index:2;max-width:560px;padding:48px 24px;color:#fff}
.home-slide-content .badge{display:inline-block;background:var(--color-primary,#e11d48);color:#fff;font-size:.75rem;font-weight:700;padding:5px 12px;border-radius:999px;margin-bottom:14px;text-transform:uppercase;letter-spacing:.04em}
.home-slide-content h1{font-size:clamp(1.8rem,4vw,3rem);font-weight:800;line-height:1.15;margin:0 0 12px;color:#fff}
.home-slide-content p{font-size:1.05rem;opacity:.92;margin:0 0 22px;line-height:1.5}
.home-slide-actions{display:flex;flex-wrap:wrap;gap:10px}
.home-slide-actions .btn-light-outline{border:1px solid rgba(255,255,255,.7);color:#fff;background:transparent;padding:11px 20px;border-radius:8px;font-weight:600;text-decoration:none}
.home-slide-actions .btn-light-outline:hover{background:#fff;color:#111}
.home-slide-actions .btn-solid{background:#fff;color:#111;padding:11px 22px;border-radius:8px;font-weight:700;text-decoration:none;border:0}
.home-slide-actions .btn-solid:hover{background:var(--color-primary,#e11d48);color:#fff}
.slider-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:5;width:44px;height:44px;border-radius:50%;border:0;background:rgba(255,255,255,.9);color:#111;cursor:pointer;display:grid;place-items:center;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.slider-nav:hover{background:#fff}
.slider-nav.prev{left:16px}
.slider-nav.next{right:16px}
.slider-dots{position:absolute;bottom:18px;left:50%;transform:translateX(-50%);z-index:5;display:flex;gap:8px}
.slider-dots button{width:10px;height:10px;border-radius:50%;border:0;background:rgba(255,255,255,.45);cursor:pointer;padding:0}
.slider-dots button.active{background:#fff;width:24px;border-radius:999px}
.apex-hero-btns{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;padding:20px 16px 8px}
.apex-hero-btns a{display:inline-flex;align-items:center;justify-content:center;min-width:96px;padding:10px 20px;border:1px solid var(--color-border,#ddd);border-radius:6px;background:var(--color-surface,#fff);color:var(--color-text,#111);font-weight:600;font-size:.9rem;text-decoration:none}
.apex-hero-btns a:hover{background:#111;border-color:#111;color:#fff}
.apex-section{padding:40px 0}
.apex-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:22px;flex-wrap:wrap}
.apex-section-head h2{margin:0;font-size:1.5rem;font-weight:800}
.apex-section-head .sub{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:var(--color-text-muted);margin-bottom:4px}
.apex-features{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;padding:36px 0;border-top:1px solid var(--color-border);border-bottom:1px solid var(--color-border)}
.apex-feat{text-align:center;padding:12px 16px}
.apex-feat i{font-size:1.6rem;color:var(--color-primary);margin-bottom:10px}
.apex-feat h4{margin:0 0 6px;font-size:1rem}
.apex-feat p{margin:0;font-size:.85rem;color:var(--color-text-muted);line-height:1.45}
@media(max-width:700px){.home-slider,.home-slider-track{min-height:420px}.home-slide-content{padding:36px 18px}.slider-nav{width:36px;height:36px}.slider-nav.prev{left:8px}.slider-nav.next{right:8px}}
</style>

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
  </div>
  <?php endif; ?>
</section>

<script>
(function(){
  var root = document.getElementById('home-slider');
  if (!root) return;
  var slides = root.querySelectorAll('.home-slide');
  var dots = root.querySelectorAll('#hs-dots button');
  var n = slides.length;
  if (n < 2) return;
  var i = 0, timer = null;
  function go(to) {
    i = (to + n) % n;
    slides.forEach(function(s, k){ s.classList.toggle('active', k === i); });
    dots.forEach(function(d, k){ d.classList.toggle('active', k === i); });
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

<section class="apex-section" style="padding-top:16px;padding-bottom:8px">
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
    <div class="apex-section-head">
      <div><div class="sub">Featured</div><h2>The Collection</h2></div>
      <a href="/shop.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $p): ?><?= render_product_card($p) ?><?php endforeach; ?>
      <?php if (empty($featured)): ?><p class="text-muted">No featured products yet.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="apex-section" style="background:color-mix(in srgb,var(--color-surface) 55%,transparent)">
  <div class="container">
    <div class="apex-section-head">
      <div><div class="sub">Just Dropped</div><h2>New Arrivals</h2></div>
      <a href="/shop.php?filter=new" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="product-grid">
      <?php foreach ($newArrivals as $p): ?><?= render_product_card($p) ?><?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!empty($saleProducts)): ?>
<section class="apex-section">
  <div class="container">
    <div class="apex-section-head">
      <div><div class="sub">Offers</div><h2>Mega Sale</h2></div>
      <a href="/shop.php?filter=sale" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="product-grid">
      <?php foreach ($saleProducts as $p): ?><?= render_product_card($p) ?><?php endforeach; ?>
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
      <div><div class="sub">Testimonials</div><h2>What Customers Say</h2></div>
    </div>
    <div class="reviews-grid">
      <?php foreach ($reviews as $rv): ?>
      <div class="review-card">
        <div class="review-stars">
          <?php for ($s=1;$s<=5;$s++): ?>
            <i class="fas fa-star" style="color:<?= $s <= (int)$rv['stars'] ? '#f59e0b' : '#d4d4d4' ?>"></i>
          <?php endfor; ?>
        </div>
        <p class="review-text">"<?= e($rv['text']) ?>"</p>
        <div class="review-name">— <?= e($rv['name']) ?><?php if (!empty($rv['product_name'])): ?> <span class="text-muted">on <?= e($rv['product_name']) ?></span><?php endif; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/frontend.php';
