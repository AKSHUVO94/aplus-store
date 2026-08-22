<?php
$siteName = setting('site_name', 'AK');
$tagline = setting('site_tagline', 'Define Your Style');
$currentTheme = Theme::current();
$themes = Theme::all();
$cartCount = Cart::count();
$categories = [];
try {
    $categories = Database::fetchAll("SELECT * FROM categories WHERE status='active' ORDER BY sort_order LIMIT 10");
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(isset($pageTitle) ? $pageTitle . ' — ' . $siteName : $siteName . ' — ' . $tagline) ?></title>
<meta name="description" content="<?= e(isset($pageDescription) ? $pageDescription : setting('site_description')) ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=20260822f">
<link rel="stylesheet" href="<?= asset('css/pro.css') ?>?v=20260822f">
<style><?= Theme::cssVariables() ?></style>
</head>
<body>
<?php
  $headerLogo = setting('brand_logo', '');
  $headerLogoUrl = $headerLogo !== '' ? '/' . ltrim(str_replace('\\', '/', $headerLogo), '/') : '';
  $qSearch = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
?>
<header class="site-header pro-header std-header">
  <div class="container header-inner">
    <a href="/" class="brand-link">
      <?php if ($headerLogoUrl !== ''): ?>
        <img src="<?= e($headerLogoUrl) ?>" alt="<?= e($siteName) ?>" class="header-logo-img">
      <?php endif; ?>
      <span class="logo pro-logo">A<span>K</span></span>
    </a>

    <nav class="nav pro-nav" id="main-nav">
      <a href="/shop.php" class="<?= activeClass('/shop') ?>">Shop</a>
      <div class="nav-dropdown">
        <button type="button" class="nav-drop-btn" aria-expanded="false">
          Categories <i class="fas fa-chevron-down"></i>
        </button>
        <div class="nav-drop-menu">
          <a href="/shop.php">All Products</a>
          <?php foreach ($categories as $cat): ?>
          <a href="/index.php?route=category&slug=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="/index.php?route=about">About</a>
      <a href="/index.php?route=contact">Contact</a>
    </nav>

    <form class="header-search" action="/shop.php" method="get" role="search">
      <input type="search" name="q" value="<?= e($qSearch) ?>" placeholder="Search for products" aria-label="Search products" autocomplete="off">
      <button type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
    </form>

    <div class="header-actions">
      <div class="theme-switcher">
        <button type="button" class="icon-btn theme-btn theme-btn-dots" title="Change theme" aria-label="Change theme">
          <span class="theme-dots" aria-hidden="true">
            <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
          </span>
        </button>
        <div class="theme-dropdown" role="menu">
          <div class="theme-dd-head">Choose theme</div>
          <div class="theme-dd-grid">
            <?php foreach ($themes as $t): ?>
            <button type="button" class="theme-option <?= $t['slug'] === $currentTheme['slug'] ? 'active' : '' ?>" data-theme="<?= e($t['slug']) ?>" role="menuitem">
              <span class="theme-preview" style="background:<?= e($t['background'] ?? '#f8fafc') ?>">
                <span class="tp-bar" style="background:<?= e($t['surface'] ?? '#fff') ?>"></span>
                <span class="tp-dot" style="background:<?= e($t['primary_color']) ?>"></span>
                <span class="tp-dot2" style="background:<?= e($t['secondary_color']) ?>"></span>
              </span>
              <span class="theme-name"><?= e($t['name']) ?></span>
              <?php if ($t['slug'] === $currentTheme['slug']): ?><i class="fas fa-check theme-check"></i><?php endif; ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <a href="/track-order.php" class="icon-btn" title="Track Order"><i class="fas fa-truck"></i></a>

      <?php if (Auth::checkCustomer()): ?>
      <a href="/my-account.php" class="icon-btn" title="My Account"><i class="fas fa-user"></i></a>
      <?php else: ?>
      <a href="/login.php" class="icon-btn" title="Login"><i class="fas fa-user"></i></a>
      <?php endif; ?>

      <a href="/cart.php" class="icon-btn cart-btn" title="Cart" id="cart-btn">
        <i class="fas fa-shopping-bag"></i>
        <?php if ($cartCount > 0): ?><span class="cart-badge"><?= (int)$cartCount ?></span><?php endif; ?>
      </a>

      <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Menu">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<main><?= isset($content) ? $content : '' ?></main>

<?php
  $brandLogo = setting('brand_logo', '');
  $brandLogoUrl = $brandLogo !== '' ? '/' . ltrim(str_replace('\\', '/', $brandLogo), '/') : '';
?>
<footer class="site-footer pro-footer std-footer">
  <div class="footer-inner">
    <div class="container">
      <div class="footer-top">
        <div class="footer-brand">
          <a href="/" class="footer-brand-logo">
            <?php if ($brandLogoUrl !== ''): ?>
              <img src="<?= e($brandLogoUrl) ?>" alt="<?= e($siteName) ?>">
            <?php else: ?>
              <span class="logo pro-logo">A<span>K</span></span>
            <?php endif; ?>
          </a>
          <p><?= e(setting('site_description', 'Premium clothing for modern lifestyle. Quality fabrics, timeless design.')) ?></p>
        </div>
        <div class="footer-col">
          <h4>Our Category</h4>
          <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
          <a href="/index.php?route=category&slug=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
          <?php endforeach; ?>
          <?php if (empty($categories)): ?>
          <a href="/shop.php">All Products</a>
          <?php endif; ?>
        </div>
        <div class="footer-col">
          <h4>Useful Links</h4>
          <a href="/index.php?route=about">About Us</a>
          <a href="/index.php?route=contact">Contact Us</a>
          <a href="/track-order.php">Track Order</a>
          <a href="/my-account.php">My Account</a>
          <a href="/shop.php">Shop</a>
        </div>
        <div class="footer-col footer-contact">
          <h4>Contact Us</h4>
          <a href="tel:<?= e(preg_replace('/\s+/', '', setting('site_phone', ''))) ?>"><i class="fas fa-phone"></i> Phone: <?= e(setting('site_phone', '01700000000')) ?></a>
          <a href="mailto:<?= e(setting('site_email', 'hello@ak.com')) ?>"><i class="fas fa-envelope"></i> Mail: <?= e(setting('site_email', 'hello@ak.com')) ?></a>
          <div class="footer-social footer-social-brand">
            <?php
              $fbOn = setting('social_facebook_enabled','1') === '1';
              $igOn = setting('social_instagram_enabled','1') === '1';
              $waOn = setting('social_whatsapp_enabled','1') === '1';
              $fbUrl = setting('social_facebook_url','') ?: '#';
              $igUrl = setting('social_instagram_url','') ?: '#';
              $waUrl = setting('social_whatsapp_url','') ?: '#';
            ?>
            <?php if ($fbOn): ?><a href="<?= e($fbUrl) ?>" class="soc fb" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
            <?php if ($igOn): ?><a href="<?= e($igUrl) ?>" class="soc ig" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a><?php endif; ?>
            <?php if ($waOn): ?><a href="<?= e($waUrl) ?>" class="soc wa" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container footer-bottom-inner">
        <span>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</span>
        <div class="pay-logos" aria-label="Payment methods">
          <?php if (setting('footer_pay_cod','1') === '1'): ?><span class="pay-logo pay-cod" title="Cash on Delivery">COD</span><?php endif; ?>
          <?php if (setting('footer_pay_bkash','1') === '1'): ?><span class="pay-logo pay-bkash" title="bKash">bKash</span><?php endif; ?>
          <?php if (setting('footer_pay_nagad','1') === '1'): ?><span class="pay-logo pay-nagad" title="Nagad">Nagad</span><?php endif; ?>
          <?php if (setting('footer_pay_rocket','1') === '1'): ?><span class="pay-logo pay-rocket" title="Rocket">Rocket</span><?php endif; ?>
          <?php if (setting('footer_pay_visa','1') === '1'): ?><span class="pay-logo pay-visa" title="Visa">VISA</span><?php endif; ?>
          <?php if (setting('footer_pay_mc','1') === '1'): ?><span class="pay-logo pay-mc" title="Mastercard">MC</span><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</footer>

<div class="qa-modal-overlay" id="qa-modal" aria-hidden="true">
  <div class="qa-modal" role="dialog" aria-labelledby="qa-title">
    <h3 id="qa-title">Select options</h3>
    <p class="text-muted" id="qa-name" style="margin:0 0 12px;font-size:.9rem"></p>
    <form method="POST" action="/cart-add.php" id="qa-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="product_id" id="qa-pid" value="">
      <input type="hidden" name="qty" value="1">
      <div id="qa-size-wrap" style="display:none">
        <label style="font-weight:600;font-size:.85rem">Size</label>
        <div class="qa-options" id="qa-sizes"></div>
      </div>
      <div id="qa-color-wrap" style="display:none">
        <label style="font-weight:600;font-size:.85rem">Color</label>
        <div class="qa-options" id="qa-colors"></div>
      </div>
      <div class="qa-actions">
        <button type="button" class="btn btn-outline" id="qa-cancel">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1">Add to Cart</button>
      </div>
    </form>
  </div>
</div>
<script>
(function(){
  var modal = document.getElementById('qa-modal');
  if (!modal) return;
  var form = document.getElementById('qa-form');
  var sizeWrap = document.getElementById('qa-size-wrap');
  var colorWrap = document.getElementById('qa-color-wrap');
  var sizesEl = document.getElementById('qa-sizes');
  var colorsEl = document.getElementById('qa-colors');
  function close(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); }
  document.getElementById('qa-cancel').addEventListener('click', close);
  modal.addEventListener('click', function(e){ if (e.target === modal) close(); });
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.btn-quick-add');
    if (!btn) return;
    e.preventDefault();
    var id = btn.getAttribute('data-id');
    var name = btn.getAttribute('data-name') || '';
    var sizes = (btn.getAttribute('data-sizes') || '').split(',').filter(Boolean);
    var colors = (btn.getAttribute('data-colors') || '').split(',').filter(Boolean);
    document.getElementById('qa-pid').value = id;
    document.getElementById('qa-name').textContent = name;
    sizesEl.innerHTML = '';
    colorsEl.innerHTML = '';
    if (sizes.length) {
      sizeWrap.style.display = 'block';
      sizes.forEach(function(s, i){
        var lab = document.createElement('label');
        lab.innerHTML = '<input type="radio" name="size" value="'+s.replace(/"/g,'&quot;')+'" '+(i===0?'checked':'')+'> <span>'+s+'</span>';
        sizesEl.appendChild(lab);
      });
    } else { sizeWrap.style.display = 'none'; }
    if (colors.length) {
      colorWrap.style.display = 'block';
      colors.forEach(function(c, i){
        var lab = document.createElement('label');
        lab.innerHTML = '<input type="radio" name="color" value="'+c.replace(/"/g,'&quot;')+'" '+(i===0?'checked':'')+'> <span>'+c+'</span>';
        colorsEl.appendChild(lab);
      });
    } else { colorWrap.style.display = 'none'; }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
  });
})();
</script>


<?php
  $promoOn = setting('promo_enabled', '0') === '1';
  $promoTitle = setting('promo_title', '');
  $promoText = setting('promo_text', '');
  $promoBtn = setting('promo_btn_text', 'Shop Now');
  $promoLink = setting('promo_btn_link', '/shop.php');
  $promoImg = setting('promo_image', '');
  $promoImgUrl = $promoImg !== '' ? '/' . ltrim(str_replace('\\', '/', $promoImg), '/') : '';
?>
<?php if ($promoOn && ($promoTitle !== '' || $promoImgUrl !== '')): ?>
<div class="promo-overlay" id="promo-overlay" hidden>
  <div class="promo-modal" role="dialog" aria-modal="true" aria-label="Special offer">
    <button type="button" class="promo-close" id="promo-close" aria-label="Close">&times;</button>
    <div class="promo-grid">
      <?php if ($promoImgUrl): ?>
      <div class="promo-visual"><img src="<?= e($promoImgUrl) ?>" alt="<?= e($promoTitle) ?>"></div>
      <?php endif; ?>
      <div class="promo-body">
        <?php if ($promoTitle): ?><h2><?= e($promoTitle) ?></h2><?php endif; ?>
        <?php if ($promoText): ?><p><?= nl2br(e($promoText)) ?></p><?php endif; ?>
        <a href="<?= e($promoLink ?: '/shop.php') ?>" class="btn btn-primary btn-lg"><?= e($promoBtn ?: 'Shop Now') ?></a>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var key = 'ak_promo_seen_v1';
  var el = document.getElementById('promo-overlay');
  if (!el) return;
  try { if (localStorage.getItem(key)) return; } catch(e) {}
  function open(){ el.hidden = false; document.body.style.overflow = 'hidden'; }
  function close(){
    el.hidden = true; document.body.style.overflow = '';
    try { localStorage.setItem(key, '1'); } catch(e) {}
  }
  setTimeout(open, 800);
  var c = document.getElementById('promo-close');
  if (c) c.addEventListener('click', close);
  el.addEventListener('click', function(e){ if (e.target === el) close(); });
})();
</script>
<?php endif; ?>

<script src="<?= asset('js/app.js') ?>?v=20260822f"></script>
</body>
</html>