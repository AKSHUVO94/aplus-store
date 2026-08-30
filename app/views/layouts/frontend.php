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
<link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=20260827hdr8">
<link rel="stylesheet" href="<?= asset('css/pro.css') ?>?v=20260827hdr8">
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
      <input type="search" name="q" value="<?= e($qSearch) ?>" placeholder="Search Products" aria-label="Search products" autocomplete="off">
      <button type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
    </form>

    <div class="header-actions">
      <div class="theme-switcher">
        <button type="button" class="icon-btn theme-btn theme-btn-dots" title="Change theme" aria-label="Change theme">
          <span class="theme-dots" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
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

      <a href="/track-order.php" class="icon-btn" title="Track Order" aria-label="Track Order">
        <svg class="hdr-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 7h11v10H1z"/><path d="M12 10h4l3 3v4h-7"/><circle cx="5.5" cy="18.5" r="1.8"/><circle cx="16.5" cy="18.5" r="1.8"/></svg>
      </a>

      <?php if (Auth::checkCustomer()): ?>
      <a href="/my-account.php" class="icon-btn" title="My Account" aria-label="My Account">
        <svg class="hdr-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
      </a>
      <?php else: ?>
      <a href="/login.php" class="icon-btn" title="Login" aria-label="Login">
        <svg class="hdr-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
      </a>
      <?php endif; ?>

      <button type="button" class="icon-btn" id="hdr-live-chat" title="Live Chat" aria-label="Live Chat">
        <svg class="hdr-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5c-1.2 0-2.3-.2-3.3-.7L3 21l1.3-4.1A8.4 8.4 0 0 1 3.5 12 8.5 8.5 0 1 1 21 12z"/><path d="M8 10h.01M12 10h.01M16 10h.01"/></svg>
        <span class="cart-badge ak-hdr-chat-badge" id="hdr-chat-badge" hidden></span>
      </button>

      <a href="/cart.php" class="icon-btn cart-btn" title="Cart" id="cart-btn" aria-label="Cart">
        <svg class="hdr-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h15l-1.5 9h-12z"/><path d="M6 7l-1-3H2"/><circle cx="9" cy="20" r="1.2" fill="currentColor" stroke="none"/><circle cx="17" cy="20" r="1.2" fill="currentColor" stroke="none"/></svg>
        <?php if ($cartCount > 0): ?><span class="cart-badge"><?= (int)$cartCount ?></span><?php endif; ?>
      </a>

      <button type="button" class="menu-toggle icon-btn" id="menu-toggle" aria-label="Menu">
        <svg class="hdr-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
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
      <input type="hidden" name="redirect" id="qa-redirect" value="stay">

      <div id="qa-size-wrap" style="display:none;margin-bottom:12px">
        <label class="qa-label">Size</label>
        <div class="qa-options" id="qa-sizes"></div>
      </div>
      <div id="qa-color-wrap" style="display:none;margin-bottom:12px">
        <label class="qa-label">Color</label>
        <div class="qa-options" id="qa-colors"></div>
      </div>

      <div class="qa-qty-wrap">
        <label class="qa-label" for="qa-qty">Quantity</label>
        <div class="qa-qty-control">
          <button type="button" class="qa-qty-btn" id="qa-qty-minus" aria-label="Decrease quantity">−</button>
          <input type="number" name="qty" id="qa-qty" class="qa-qty-input" value="1" min="1" max="99" step="1">
          <button type="button" class="qa-qty-btn" id="qa-qty-plus" aria-label="Increase quantity">+</button>
        </div>
      </div>

      <div class="qa-actions">
        <button type="submit" class="qa-btn-primary" id="qa-add-btn">
          <i class="fas fa-shopping-bag"></i> Add to Cart
        </button>
        <button type="submit" class="qa-btn-secondary" id="qa-buy-btn">
          Add &amp; Go to Cart
        </button>
        <button type="button" class="qa-btn-cancel" id="qa-cancel">Cancel</button>
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
  var qtyInput = document.getElementById('qa-qty');

  function close(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); }
  function setQty(n) {
    n = parseInt(n, 10);
    if (isNaN(n) || n < 1) n = 1;
    if (n > 99) n = 99;
    qtyInput.value = n;
  }

  document.getElementById('qa-cancel').addEventListener('click', close);
  modal.addEventListener('click', function(e){ if (e.target === modal) close(); });
  document.getElementById('qa-qty-minus').addEventListener('click', function(){ setQty((parseInt(qtyInput.value,10)||1) - 1); });
  document.getElementById('qa-qty-plus').addEventListener('click', function(){ setQty((parseInt(qtyInput.value,10)||1) + 1); });
  qtyInput.addEventListener('change', function(){ setQty(qtyInput.value); });

  document.addEventListener('click', function(e){
    var btn = e.target.closest('.btn-quick-add');
    if (!btn) return;
    e.preventDefault();
    var id = btn.getAttribute('data-id');
    var name = btn.getAttribute('data-name') || '';
    var sizes = (btn.getAttribute('data-sizes') || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean);
    var colors = (btn.getAttribute('data-colors') || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean);
    document.getElementById('qa-pid').value = id;
    document.getElementById('qa-name').textContent = name;
    setQty(1);
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
    document.getElementById('qa-redirect').value = 'stay';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
  });

  document.getElementById('qa-buy-btn').addEventListener('click', function () {
    document.getElementById('qa-redirect').value = 'cart';
    setQty(qtyInput.value);
  });
  document.getElementById('qa-add-btn').addEventListener('click', function () {
    document.getElementById('qa-redirect').value = 'stay';
    setQty(qtyInput.value);
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

<!-- Apex-style Quick View Modal -->
<div class="qv-overlay" id="qv-overlay" aria-hidden="true">
  <div class="qv-modal" role="dialog" aria-modal="true" aria-label="Quick View" id="qv-modal">
    <button type="button" class="qv-close" id="qv-close" aria-label="Close">&times;</button>
    <div id="qv-content"><div class="qv-loading">Loading…</div></div>
  </div>
</div>
<script>
(function(){
  var overlay = document.getElementById('qv-overlay');
  var content = document.getElementById('qv-content');
  var closeBtn = document.getElementById('qv-close');
  if (!overlay) return;

  function close() {
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
  function open() {
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  if (closeBtn) closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });

  function starsHtml(avg, count) {
    var h = '';
    for (var i = 1; i <= 5; i++) {
      h += '<i class="fas fa-star" style="color:' + (i <= Math.round(avg) ? '#f59e0b' : '#d4d4d4') + '"></i>';
    }
    if (count > 0) h += '<span class="muted">(' + count + ')</span>';
    return h;
  }
  function escapeHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function render(p) {
    var imgs = p.images && p.images.length ? p.images : [];
    var main = imgs[0] || '';
    var sizePills = '';
    (p.sizes || []).forEach(function(s, i){
      sizePills += '<label class="'+(i===0?'active':'')+'"><input type="radio" name="qv_size" value="'+s.replace(/"/g,'&quot;')+'" '+(i===0?'checked':'')+'> '+s+'</label>';
    });
    var colorPills = '';
    (p.colors || []).forEach(function(c, i){
      colorPills += '<label class="'+(i===0?'active':'')+'"><input type="radio" name="qv_color" value="'+c.replace(/"/g,'&quot;')+'" '+(i===0?'checked':'')+'> '+c+'</label>';
    });
    var thumbs = '';
    imgs.forEach(function(src, i){
      thumbs += '<button type="button" class="'+(i===0?'active':'')+'" data-src="'+src+'"><img src="'+src+'" alt=""></button>';
    });
    var oldPrice = p.old_price_fmt ? '<span class="old">'+p.old_price_fmt+'</span>' : '';
    var shortDesc = p.short || '';
    if (shortDesc.length > 140) shortDesc = shortDesc.slice(0, 137) + '…';
    var fullDesc = p.description || p.short || '';
    var material = p.material || '';
    var qualities = [];
    if (material) qualities.push('<li><i class="fas fa-check"></i>Fabric: '+escapeHtml(material)+'</li>');
    if (p.stock > 0) qualities.push('<li><i class="fas fa-check"></i>In stock ('+p.stock+' available)</li>');
    else qualities.push('<li><i class="fas fa-times"></i>Out of stock</li>');
    if (p.is_new) qualities.push('<li><i class="fas fa-check"></i>New arrival</li>');
    if (p.on_sale) qualities.push('<li><i class="fas fa-check"></i>On sale</li>');
    if ((p.sizes||[]).length) qualities.push('<li><i class="fas fa-check"></i>Sizes: '+escapeHtml((p.sizes||[]).join(', '))+'</li>');
    if ((p.colors||[]).length) qualities.push('<li><i class="fas fa-check"></i>Colors: '+escapeHtml((p.colors||[]).join(', '))+'</li>');
    qualities.push('<li><i class="fas fa-check"></i>Quality checked before dispatch</li>');

    content.innerHTML =
      '<div class="qv-gallery">'+
        (main ? '<img src="'+main+'" alt="" class="qv-main-img" id="qv-main-img">' : '<div class="qv-loading">No image</div>')+
        (thumbs ? '<div class="qv-thumbs" id="qv-thumbs">'+thumbs+'</div>' : '')+
      '</div>'+
      '<div class="qv-info">'+
        (p.cat ? '<div class="qv-cat">'+escapeHtml(p.cat)+'</div>' : '')+
        '<h2>'+escapeHtml(p.name)+'</h2>'+
        (p.sku ? '<div class="qv-sku">SKU: '+escapeHtml(p.sku)+'</div>' : '')+
        '<div class="qv-stars">'+starsHtml(p.rating||0, p.reviews||0)+'</div>'+
        '<div class="qv-price">'+p.price_fmt+oldPrice+'</div>'+
        (shortDesc ? '<p class="qv-desc">'+escapeHtml(shortDesc)+'</p>' : '')+
        (sizePills ? '<div class="qv-opts"><label class="lbl">Size</label><div class="qv-pills" id="qv-sizes">'+sizePills+'</div></div>' : '')+
        (colorPills ? '<div class="qv-opts"><label class="lbl">Color</label><div class="qv-pills" id="qv-colors">'+colorPills+'</div></div>' : '')+
        '<div class="qv-qty"><span class="lbl">Qty</span><input type="number" id="qv-qty" value="1" min="1" max="'+(p.stock>0?p.stock:1)+'"></div>'+
        '<div class="qv-actions">'+
          (p.stock > 0
            ? '<button type="button" class="btn-dark" id="qv-add">Add to Cart</button>'
            : '<button type="button" class="btn-dark" disabled>Out of Stock</button>')+
          '<a class="btn-outline-qv" href="'+p.url+'">View full details</a>'+
        '</div>'+
      '</div>'+
      '<div class="qv-details">'+
        (fullDesc ? '<div class="qv-block"><h3>Description</h3><p>'+escapeHtml(fullDesc)+'</p></div>' : '')+
        (material ? '<div class="qv-block"><h3>Fabric &amp; Material</h3><div class="qv-fabric-row"><span><i class="fas fa-shirt"></i> '+escapeHtml(material)+'</span></div></div>' : '')+
        '<div class="qv-block"><h3>Quality &amp; Details</h3><ul class="qv-qualities">'+qualities.join('')+'</ul></div>'+
        '<div class="qv-block"><h3>Reviews</h3><p>'+
          ((p.reviews||0) > 0
            ? starsHtml(p.rating||0, p.reviews||0)+' — based on '+(p.reviews)+' customer review'+(p.reviews>1?'s':'')+'. <a href="'+p.url+'#reviews">Read on product page</a>'
            : 'No reviews yet. <a href="'+p.url+'">Be the first to review</a>')+
        '</p></div>'+
      '</div>';

    var thumbsEl = document.getElementById('qv-thumbs');
    var mainImg = document.getElementById('qv-main-img');
    if (thumbsEl && mainImg) {
      thumbsEl.addEventListener('click', function(e){
        var btn = e.target.closest('button');
        if (!btn) return;
        mainImg.src = btn.getAttribute('data-src');
        thumbsEl.querySelectorAll('button').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
      });
    }
    content.querySelectorAll('.qv-pills').forEach(function(wrap){
      wrap.addEventListener('change', function(e){
        if (e.target.name) {
          wrap.querySelectorAll('label').forEach(function(l){ l.classList.remove('active'); });
          if (e.target.parentElement) e.target.parentElement.classList.add('active');
        }
      });
    });
    var addBtn = document.getElementById('qv-add');
    if (addBtn) {
      addBtn.addEventListener('click', function(){
        var size = (content.querySelector('input[name="qv_size"]:checked') || {}).value || '';
        var color = (content.querySelector('input[name="qv_color"]:checked') || {}).value || '';
        var qty = parseInt((document.getElementById('qv-qty') || {}).value || '1', 10) || 1;
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/cart-add.php';
        function hid(n,v){ var i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); }
        hid('_csrf', document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '');
        // fallback: get csrf from any form on page
        var existing = document.querySelector('input[name="_csrf"]');
        if (existing) hid('_csrf', existing.value);
        hid('product_id', String(p.id));
        hid('qty', String(qty));
        if (size) hid('size', size);
        if (color) hid('color', color);
        document.body.appendChild(form);
        form.submit();
      });
    }
  }

  document.addEventListener('click', function(e){
    var btn = e.target.closest('.btn-quick-view');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var id = btn.getAttribute('data-qv-id');
    if (!id) return;
    content.innerHTML = '<div class="qv-loading">Loading…</div>';
    open();
    fetch('/api-quick-view.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok || !data.product) {
          content.innerHTML = '<div class="qv-loading">Could not load product.</div>';
          return;
        }
        render(data.product);
      })
      .catch(function(){
        content.innerHTML = '<div class="qv-loading">Network error.</div>';
      });
  });
})();
</script>

<?php if (setting('chat_enabled', '1') === '1'): ?>
<div id="ak-chat-root">
  <div class="ak-chat-panel" id="ak-chat-panel" hidden>
    <div class="ak-chat-head">
      <div>
        <strong id="ak-chat-title">Hi there! 👋</strong>
        <p id="ak-chat-greet">Let us know if we can help you with anything at all.</p>
      </div>
      <button type="button" class="ak-chat-close" id="ak-chat-close" aria-label="Close">×</button>
    </div>
    <div class="ak-chat-home" id="ak-chat-home">
      <button type="button" class="ak-chat-opt ak-chat-opt-live" id="ak-opt-live">
        <i class="fas fa-comment-dots"></i> LiveChat
      </button>
      <a class="ak-chat-opt ak-chat-opt-msg" id="ak-opt-msg" href="#" target="_blank" rel="noopener" style="display:none">
        <i class="fab fa-facebook-messenger"></i> Messenger
      </a>
      <a class="ak-chat-opt ak-chat-opt-wa" id="ak-opt-wa" href="#" target="_blank" rel="noopener" style="display:none">
        <i class="fab fa-whatsapp"></i> WhatsApp
      </a>
    </div>
    <div class="ak-chat-live" id="ak-chat-live" hidden>
      <div class="ak-chat-guest-banner" id="ak-chat-guest-banner" hidden>
        <span>Guest chat is temporary — messages auto-delete after <strong>24 hours</strong>.</span>
      </div>
      <div class="ak-chat-msgs" id="ak-chat-msgs"></div>
      <form class="ak-chat-form" id="ak-chat-form">
        <input type="text" id="ak-chat-name" placeholder="Your name (optional)" class="ak-chat-input-sm">
        <div class="ak-chat-send-row">
          <input type="text" id="ak-chat-input" placeholder="Type or paste screenshot (Ctrl+V)…" autocomplete="off">
          <button type="submit" aria-label="Send"><i class="fas fa-paper-plane"></i></button>
        </div>
        <p class="ak-chat-paste-hint">Screenshot: Ctrl+V / Cmd+V · Text paste allowed · No file attach</p>
      </form>
      <div class="ak-chat-footer-row">
        <button type="button" class="ak-chat-back" id="ak-chat-back">← Back</button>
        <button type="button" class="ak-chat-del" id="ak-chat-del" title="Delete this chat">Delete chat</button>
      </div>
    </div>
  </div>
  <button type="button" class="ak-chat-fab" id="ak-chat-fab" aria-label="Open chat">
    <i class="fas fa-comment"></i>
  </button>
</div>
<style>
#ak-chat-root{position:fixed;right:20px;bottom:20px;z-index:9998;font-family:inherit}
.ak-chat-fab{
  width:56px;height:56px;border-radius:50%;border:0;cursor:pointer;
  background:#10b981;color:#fff;font-size:1.25rem;
  box-shadow:0 8px 24px rgba(16,185,129,.4);
  display:flex;align-items:center;justify-content:center;
}
.ak-chat-fab:hover{background:#059669}
.ak-chat-panel{
  position:absolute;right:0;bottom:70px;width:320px;max-width:calc(100vw - 32px);
  background:#fff;color:#111;border-radius:16px;
  box-shadow:0 16px 48px rgba(0,0,0,.18);border:1px solid #eee;overflow:hidden;
}
.ak-chat-head{display:flex;justify-content:space-between;gap:12px;padding:16px 16px 8px}
.ak-chat-head strong{font-size:1.05rem}
.ak-chat-head p{margin:6px 0 0;font-size:.875rem;color:#555;line-height:1.4}
.ak-chat-close{border:0;background:transparent;font-size:1.4rem;line-height:1;cursor:pointer;color:#888;padding:0 4px}
.ak-chat-home{padding:8px 16px 16px;display:flex;flex-direction:column;gap:10px}
.ak-chat-opt{
  display:flex;align-items:center;justify-content:center;gap:8px;
  padding:12px 14px;border-radius:10px;border:1.5px solid #e5e5e5;
  background:#fff;color:#111;font-weight:600;font-size:.9rem;text-decoration:none;cursor:pointer;
  width:100%;box-sizing:border-box;
}
.ak-chat-opt-live{background:#10b981!important;color:#fff!important;border-color:#10b981!important}
.ak-chat-opt-live:hover{background:#059669!important;border-color:#059669!important}
.ak-chat-opt-msg{background:#0084ff!important;color:#fff!important;border-color:#0084ff!important}
.ak-chat-opt-msg:hover{background:#006bbf!important;border-color:#006bbf!important}
.ak-chat-opt-wa{background:#fff!important;color:#111!important;border-color:#e5e5e5!important}
.ak-chat-opt-wa i{color:#25d366}
.ak-chat-opt-wa:hover{border-color:#25d366!important}
.ak-chat-live{display:flex;flex-direction:column;height:360px}
.ak-chat-home[hidden],.ak-chat-live[hidden]{display:none!important}
.ak-chat-msgs:empty::before{content:'No messages yet — say hello!';display:block;text-align:center;color:#aaa;font-size:.85rem;padding:24px 8px}
.ak-chat-msgs{flex:1;overflow-y:auto;padding:12px 14px;background:#f7f7f7}
.ak-c-bubble{max-width:85%;padding:8px 12px;border-radius:12px;margin-bottom:8px;font-size:.85rem;line-height:1.4;white-space:pre-wrap}
.ak-c-bubble.visitor{background:#111;color:#fff;margin-left:auto}
.ak-c-bubble.admin{background:#fff;border:1px solid #e5e5e5;margin-right:auto}
.ak-c-bubble .t{display:block;font-size:.65rem;opacity:.55;margin-top:4px;font-weight:400}
.ak-c-bubble.visitor .t{color:rgba(255,255,255,.75)}
.ak-c-bubble.admin .t{color:#888}
.ak-c-img{display:block;max-width:100%;max-height:180px;border-radius:8px;margin-top:2px;cursor:pointer}
.ak-chat-paste-hint{font-size:.7rem;color:#999;margin:4px 0 0;padding:0 2px}
.ak-chat-guest-banner{
  margin:0;padding:8px 12px;font-size:.72rem;line-height:1.35;
  background:rgba(15,23,42,.06);color:#475569;
  border-bottom:1px solid #eee;
  backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
}
.ak-chat-guest-banner[hidden]{display:none!important}
.ak-chat-footer-row{display:flex;justify-content:space-between;align-items:center;padding:0 8px 8px}
.ak-chat-del{border:0;background:transparent;color:#e11d48;font-size:.78rem;cursor:pointer;padding:6px 8px}
.ak-chat-del:hover{text-decoration:underline}
body.ak-guest-chat .ak-chat-msgs{filter:none}
body.ak-guest-chat .ak-chat-live{position:relative}
.ak-chat-fab .ak-chat-badge[hidden],.ak-chat-badge:empty{display:none!important}
.ak-hdr-chat-badge[hidden],.ak-hdr-chat-badge:empty{display:none!important}
.ak-chat-form{padding:10px;border-top:1px solid #eee;background:#fff}
.ak-chat-input-sm{width:100%;margin-bottom:8px;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:.8rem}
.ak-chat-send-row{display:flex;gap:8px}
.ak-chat-send-row input{flex:1;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:.875rem}
.ak-chat-send-row button{width:42px;border:0;border-radius:8px;background:#10b981;color:#fff;cursor:pointer}
.ak-chat-back{border:0;background:transparent;color:#888;font-size:.8rem;padding:6px 14px 12px;cursor:pointer;text-align:left}
.ak-chat-badge{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#e11d48;color:#fff;font-size:.65rem;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid #fff;line-height:1}
.ak-chat-fab.has-unread{animation:ak-chat-pulse 1.2s ease infinite}
@keyframes ak-chat-pulse{0%,100%{box-shadow:0 8px 24px rgba(16,185,129,.4)}50%{box-shadow:0 8px 28px rgba(225,29,72,.55)}}
</style>
<script>
(function(){
  var fab = document.getElementById('ak-chat-fab');
  var panel = document.getElementById('ak-chat-panel');
  if (!fab || !panel) return;
  var home = document.getElementById('ak-chat-home');
  var live = document.getElementById('ak-chat-live');
  var msgs = document.getElementById('ak-chat-msgs');
  var form = document.getElementById('ak-chat-form');
  var input = document.getElementById('ak-chat-input');
  var nameInput = document.getElementById('ak-chat-name');
  var lastId = 0;
  var seen = {};
  var pollTimer = null;
  var liveOpen = false;
  var unreadAdmin = 0;
  var initialSync = true;
  var isCustomer = false;
  var api = '/api-chat.php';
  var convId = 0;
  try { convId = parseInt(localStorage.getItem('ak_chat_cid') || '0', 10) || 0; } catch(e) { convId = 0; }
  function saveConvId(id){
    id = parseInt(id, 10) || 0;
    if (!id) return;
    convId = id;
    try { localStorage.setItem('ak_chat_cid', String(id)); } catch(e) {}
  }

  // Badge on FAB
  var badge = document.createElement('span');
  badge.className = 'ak-chat-badge';
  badge.hidden = true;
  fab.style.position = 'relative';
  fab.appendChild(badge);

  function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

  function playBeep(){
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.connect(g); g.connect(ctx.destination);
      o.frequency.value = 880;
      g.gain.value = 0.05;
      o.start();
      setTimeout(function(){ o.stop(); ctx.close(); }, 150);
    } catch(e) {}
  }

  var hdrBadge = document.getElementById('hdr-chat-badge');
  function setBadge(n){
    unreadAdmin = Math.max(0, parseInt(n, 10) || 0);
    var label = unreadAdmin > 9 ? '9+' : String(unreadAdmin);
    if (unreadAdmin > 0) {
      badge.hidden = false;
      badge.removeAttribute('hidden');
      badge.textContent = label;
      badge.style.display = '';
      fab.classList.add('has-unread');
      if (hdrBadge) {
        hdrBadge.hidden = false;
        hdrBadge.removeAttribute('hidden');
        hdrBadge.textContent = label;
        hdrBadge.style.display = '';
      }
    } else {
      badge.hidden = true;
      badge.setAttribute('hidden', '');
      badge.textContent = '';
      badge.style.display = 'none';
      fab.classList.remove('has-unread');
      if (hdrBadge) {
        hdrBadge.hidden = true;
        hdrBadge.setAttribute('hidden', '');
        hdrBadge.textContent = '';
        hdrBadge.style.display = 'none';
      }
    }
  }

  function openPanel(){
    panel.hidden = false;
    panel.removeAttribute('hidden');
  }
  function closePanel(){
    panel.hidden = true;
    showHome();
  }
  function showHome(){
    if (home) {
      home.hidden = false;
      home.removeAttribute('hidden');
      home.style.display = '';
    }
    if (live) {
      live.hidden = true;
      live.setAttribute('hidden','');
      live.style.display = 'none';
    }
    liveOpen = false;
  }
  function showLive(){
    if (home) {
      home.hidden = true;
      home.setAttribute('hidden','');
      home.style.display = 'none';
    }
    if (live) {
      live.hidden = false;
      live.removeAttribute('hidden');
      live.style.display = 'flex';
    }
    liveOpen = true;
    lastId = 0;
    seen = {};
    initialSync = true;
    if (msgs) msgs.innerHTML = '';
    setBadge(0);
    // Force full history load with stored conversation id
    loadHistory();
    startPoll(true);
    setTimeout(function(){ setBadge(0); initialSync = false; }, 800);
  }
  function loadHistory(){
    var url = api + '?action=poll&after=0&cid=' + (convId || 0) + '&_ts=' + Date.now();
    fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.text(); })
      .then(function(text){
        var data;
        try { data = JSON.parse(text); } catch(e) { return; }
        if (!data || !data.ok) return;
        if (data.conversation_id) saveConvId(data.conversation_id);
        if (typeof setGuestMode === 'function') {
          var guest2 = (!isCustomer && !data.is_customer && !!data.is_guest);
          setGuestMode(guest2, guest2 ? (data.expires_at || null) : null);
        }
        if (data.expired || data.cleared) {
          try { localStorage.removeItem('ak_chat_cid'); } catch(e) {}
          convId = 0;
        }
        if (msgs) msgs.innerHTML = '';
        seen = {};
        lastId = 0;
        (data.messages || []).forEach(function(m){
          addBubble(m.id, m.sender, m.message, false, m.created_at);
        });
        initialSync = false;
        setBadge(0);
      })
      .catch(function(){});
  }

  fab.addEventListener('click', function(){
    if (panel.hidden) {
      openPanel();
      showHome();
      if (unreadAdmin > 0) showLive();
    } else {
      closePanel();
    }
  });
  var hdrBtn = document.getElementById('hdr-live-chat');
  if (hdrBtn) {
    hdrBtn.addEventListener('click', function(e){
      e.preventDefault();
      openPanel();
      showLive();
      setBadge(0);
    });
  }
  document.getElementById('ak-chat-close').addEventListener('click', closePanel);
  var liveBtn = document.getElementById('ak-opt-live');
  if (liveBtn) liveBtn.addEventListener('click', showLive);
  document.getElementById('ak-chat-back').addEventListener('click', showHome);

  var guestBanner = document.getElementById('ak-chat-guest-banner');
  var delBtn = document.getElementById('ak-chat-del');
  function setGuestMode(on, expiresAt){
    if (guestBanner) {
      if (on) {
        guestBanner.hidden = false;
        guestBanner.removeAttribute('hidden');
        var extra = '';
        if (expiresAt) {
          try {
            var d = new Date(String(expiresAt).replace(' ','T'));
            if (!isNaN(d.getTime())) {
              extra = ' Expires: ' + d.toLocaleString();
            }
          } catch(e) {}
        }
        guestBanner.innerHTML = '<span style="filter:none">Guest chat is temporary — messages auto-delete after <strong>24 hours</strong>.'+ (extra ? ' <em>'+extra+'</em>' : '') +'</span>';
      } else {
        guestBanner.hidden = true;
        guestBanner.setAttribute('hidden','');
      }
    }
    document.body.classList.toggle('ak-guest-chat', !!on);
  }
  if (delBtn) {
    delBtn.addEventListener('click', function(){
      if (!confirm('Delete this chat from your side?')) return;
      var fd = new FormData();
      if (convId) fd.append('cid', String(convId));
      fetch(api + '?action=delete_mine', { method:'POST', body:fd, credentials:'same-origin', cache:'no-store' })
        .then(function(r){ return r.json(); })
        .then(function(data){
          try { localStorage.removeItem('ak_chat_cid'); } catch(e) {}
          convId = 0; lastId = 0; seen = {};
          if (msgs) msgs.innerHTML = '';
          setGuestMode(false);
          setBadge(0);
          showHome();
        });
    });
  }



  function addBubble(id, sender, text, notify, createdAt){
    id = parseInt(id, 10) || 0;
    if (id && seen[id]) return false;
    if (id) seen[id] = true;
    if (id > lastId) lastId = id;
    if (liveOpen && msgs) {
      var div = document.createElement('div');
      div.className = 'ak-c-bubble ' + sender;
      if (id) div.setAttribute('data-mid', id);
      var timeStr = formatChatTime(createdAt);
      var body = renderMsgHtml(text || '');
      div.innerHTML = body + (timeStr ? '<span class="t">' + esc(timeStr) + '</span>' : '');
      msgs.appendChild(div);
      msgs.scrollTop = msgs.scrollHeight;
    }
    if (notify && sender === 'admin') {
      playBeep();
      if (!liveOpen || panel.hidden) {
        setBadge(unreadAdmin + 1);
      }
      try {
        if (document.hidden && window.Notification && Notification.permission === 'granted') {
          new Notification('New message', { body: text || 'Support replied' });
        }
      } catch(e) {}
    }
    return true;
  }

  function renderMsgHtml(text){
    var m = String(text||'').match(/^\{\{img:(.+?)\}\}$/);
    if (m) {
      var src = m[1];
      if (src.indexOf('/') === 0 || src.indexOf('http') === 0) {
        return '<a href="'+esc(src)+'" target="_blank" rel="noopener"><img class="ak-c-img" src="'+esc(src)+'" alt="Screenshot" loading="lazy"></a>';
      }
    }
    return esc(text || '');
  }
  function formatChatTime(ts){
    if (!ts) return '';
    try {
      var d = new Date(String(ts).replace(' ', 'T'));
      if (isNaN(d.getTime())) return String(ts);
      var pad = function(n){ return n < 10 ? '0'+n : ''+n; };
      var date = pad(d.getDate()) + '/' + pad(d.getMonth()+1) + '/' + d.getFullYear();
      var hm = pad(d.getHours()) + ':' + pad(d.getMinutes());
      return date + ' ' + hm;
    } catch(e) { return String(ts); }
  }

  function pollOnce(){
    var url = api + '?action=poll&after=' + lastId + '&cid=' + (convId || 0) + '&_ts=' + Date.now();
    fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.text(); })
      .then(function(text){
        var data;
        try { data = JSON.parse(text); } catch(e) { return; }
        if (!data || !data.ok) return;
        if (data.conversation_id) saveConvId(data.conversation_id);
        if (typeof setGuestMode === 'function') {
          var guest = (!isCustomer && !data.is_customer && !!data.is_guest);
          setGuestMode(guest, guest ? (data.expires_at || null) : null);
        }
        if (data.expired || data.cleared) {
          try { localStorage.removeItem('ak_chat_cid'); } catch(e) {}
          convId = 0;
          if (msgs) msgs.innerHTML = '';
        }
        var silent = initialSync;
        (data.messages || []).forEach(function(m){
          var mid = parseInt(m.id, 10) || 0;
          var isNew = mid && !seen[mid];
          // Never notify on first page-load sync (stops fake "5 notifications")
          var doNotify = !silent && isNew && m.sender === 'admin' && (!liveOpen || panel.hidden);
          addBubble(m.id, m.sender, m.message, doNotify, m.created_at);
        });
        if (silent) {
          initialSync = false;
          setBadge(0);
        }
      })
      .catch(function(){});
  }

  function startPoll(immediate){
    stopPoll();
    if (immediate) pollOnce();
    pollTimer = setInterval(pollOnce, 500);
  }
  function stopPoll(){
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  // Always poll in background so replies arrive even if panel closed
  startPoll(true);

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var text = input.value.trim();
    if (!text) return;
    var btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;
    var fd = new FormData();
    fd.append('message', text);
    fd.append('name', nameInput.value.trim());
    if (convId) fd.append('cid', String(convId));
    fetch(api + '?action=send', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function(r){ return r.text(); })
      .then(function(raw){
        if (btn) btn.disabled = false;
        var data;
        try { data = JSON.parse(raw); } catch(e) { alert('Server error: invalid response'); return; }
        if (data && data.ok) {
          input.value = '';
          if (data.conversation_id) saveConvId(data.conversation_id);
          addBubble(data.id, 'visitor', text, false, data.created_at || new Date().toISOString());
          setTimeout(pollOnce, 200);
          setTimeout(pollOnce, 600);
        } else {
          if (data && data.blocked) {
            try { localStorage.removeItem('ak_chat_cid'); } catch(e) {}
            convId = 0;
          }
          alert((data && (data.detail || data.error)) ? (data.detail || data.error) : 'Could not send message.');
        }
      })
      .catch(function(){
        if (btn) btn.disabled = false;
        alert('Network error. Please try again.');
      });
  });

  document.addEventListener('visibilitychange', function(){
    if (!document.hidden) pollOnce();
  });


  // Paste screenshot only (no file picker)
  if (input) {
    input.addEventListener('paste', function(e){
      var items = e.clipboardData && e.clipboardData.items;
      if (!items) return;
      var file = null;
      for (var i = 0; i < items.length; i++) {
        if (items[i].type && items[i].type.indexOf('image/') === 0) {
          file = items[i].getAsFile();
          break;
        }
      }
      if (!file) return; // allow normal text paste
      e.preventDefault();
      if (!liveOpen) showLive();
      var fd = new FormData();
      fd.append('image', file, 'screenshot.png');
      fd.append('name', nameInput ? nameInput.value.trim() : '');
      if (convId) fd.append('cid', String(convId));
      fetch(api + '?action=send_image', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        cache: 'no-store'
      })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (data && data.ok) {
            if (data.conversation_id) saveConvId(data.conversation_id);
            addBubble(data.id, 'visitor', data.message || ('{{img:'+data.image_url+'}}'), false, data.created_at);
            setTimeout(pollOnce, 300);
          } else {
            if (data && data.blocked) {
              try { localStorage.removeItem('ak_chat_cid'); } catch(err) {}
              convId = 0;
            }
            alert((data && (data.detail || data.error)) ? (data.detail || data.error) : 'Could not send screenshot');
          }
        })
        .catch(function(){ alert('Network error sending screenshot'); });
    });
  }

  // Request notification permission once
  try {
    if (window.Notification && Notification.permission === 'default') {
      fab.addEventListener('click', function once(){
        Notification.requestPermission();
        fab.removeEventListener('click', once);
      });
    }
  } catch(e) {}

  fetch(api + '?action=config&_ts=' + Date.now(), { credentials:'same-origin', cache:'no-store' })
    .then(function(r){ return r.json(); })
    .then(function(cfg){
      if (!cfg.ok || !cfg.enabled) {
        document.getElementById('ak-chat-root').style.display = 'none';
        stopPoll();
        return;
      }
      if (cfg.title) document.getElementById('ak-chat-title').textContent = cfg.title + ' 👋';
      if (cfg.greeting) document.getElementById('ak-chat-greet').textContent = cfg.greeting;
      var liveOpt = document.getElementById('ak-opt-live');
      if (liveOpt) liveOpt.style.display = cfg.livechat === false ? 'none' : 'flex';
      if (cfg.messenger && cfg.messenger_url) {
        var m = document.getElementById('ak-opt-msg');
        m.href = cfg.messenger_url;
        m.style.display = 'flex';
      }
      if (cfg.whatsapp && cfg.whatsapp_url) {
        var w = document.getElementById('ak-opt-wa');
        w.href = cfg.whatsapp_url;
        w.style.display = 'flex';
      }
      if (cfg.is_customer) {
        isCustomer = true;
        setGuestMode(false);
        if (cfg.customer_name && nameInput) {
          nameInput.value = cfg.customer_name;
          nameInput.style.display = 'none';
        }
        // Drop guest localStorage thread after login
        try {
          if (localStorage.getItem('ak_chat_cid')) {
            // will be ignored by API for customers; clear to be safe
            localStorage.removeItem('ak_chat_cid');
            convId = 0;
          }
        } catch(e) {}
      }
    }).catch(function(){});
})();
</script>
<?php endif; ?>

<script src="<?= asset('js/app.js') ?>?v=20260827hdr8"></script>
</body>
</html>