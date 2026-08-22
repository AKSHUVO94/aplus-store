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
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/pro.css') ?>">
<style><?= Theme::cssVariables() ?></style>
</head>
<body>
<header class="site-header pro-header">
  <div class="container header-inner">
    <a href="/" class="logo pro-logo">A<span>K</span></a>

    <nav class="nav pro-nav" id="main-nav">
      <a href="/" class="<?= activeClass('/') ?>">Home</a>
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
      <a href="/cart.php" class="nav-cart-mobile">Cart (<?= (int)$cartCount ?>)</a>
      <?php if (Auth::checkCustomer()): ?>
      <a href="/my-account.php" class="nav-cart-mobile">My Account</a>
      <?php else: ?>
      <a href="/login.php" class="nav-cart-mobile">Login</a>
      <?php endif; ?>
    </nav>

    <div class="header-actions">
      <div class="theme-switcher">
        <button type="button" class="icon-btn theme-btn" title="Theme" aria-label="Theme">
          <i class="fas fa-palette"></i>
        </button>
        <div class="theme-dropdown">
          <?php foreach ($themes as $t): ?>
          <div class="theme-option <?= $t['slug'] === $currentTheme['slug'] ? 'active' : '' ?>" data-theme="<?= e($t['slug']) ?>">
            <span class="theme-swatch" style="background:linear-gradient(135deg,<?= e($t['primary_color']) ?>,<?= e($t['secondary_color']) ?>)"></span>
            <?= e($t['name']) ?>
          </div>
          <?php endforeach; ?>
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

      <?php if (Auth::checkAdmin()): ?>
      <a href="/admin/" class="btn btn-sm btn-primary header-admin-btn">Admin</a>
      <?php else: ?>
      <a href="/admin/login.php" class="btn btn-sm btn-outline header-admin-btn">Admin</a>
      <?php endif; ?>

      <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Menu">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<main><?= isset($content) ? $content : '' ?></main>

<footer class="site-footer pro-footer">
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand">
        <a href="/" class="logo pro-logo">A<span>K</span></a>
        <p><?= e(setting('site_description', 'Premium clothing for modern lifestyle. Quality fabrics, timeless design.')) ?></p>
        <div class="footer-social">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
          <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Shop</h4>
        <a href="/shop.php">All Products</a>
        <?php foreach (array_slice($categories, 0, 5) as $cat): ?>
        <a href="/index.php?route=category&slug=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="footer-col">
        <h4>Help</h4>
        <a href="/track-order.php">Track Order</a>
        <a href="/index.php?route=contact">Contact Us</a>
        <a href="/index.php?route=about">About AK</a>
        <a href="/login.php">My Account</a>
      </div>
      <div class="footer-col">
        <h4>Contact</h4>
        <a href="mailto:<?= e(setting('site_email', 'hello@ak.com')) ?>"><?= e(setting('site_email', 'hello@ak.com')) ?></a>
        <a href="tel:<?= e(preg_replace('/\s+/', '', setting('site_phone', ''))) ?>"><?= e(setting('site_phone', '01700000000')) ?></a>
        <p class="footer-address"><?= e(setting('site_address', 'Dhaka, Bangladesh')) ?></p>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</span>
      <span class="footer-payments">
        <span>COD</span><span>bKash</span><span>Nagad</span><span>Bank</span>
      </span>
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

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>