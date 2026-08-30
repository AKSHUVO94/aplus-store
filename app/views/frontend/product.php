<?php
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
$p = Database::fetch("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.status='active'", [$slug]);
if (!$p) { http_response_code(404); require __DIR__.'/404.php'; return; }
Database::query("UPDATE products SET views=views+1 WHERE id=?", [$p['id']]);
$pageTitle = $p['name'];
$sizes = array_filter(array_map('trim', explode(',', $p['sizes'] ?: '')));
$colors = array_filter(array_map('trim', explode(',', $p['colors'] ?: '')));
$success = flash('success'); $error = flash('error');

// Approved reviews for this product
$productReviews = array();
$avgRating = 0;
$reviewCount = 0;
$ratingBreakdown = array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0);
try {
    $productReviews = Database::fetchAll(
        "SELECT * FROM product_reviews WHERE product_id=? AND status='approved' ORDER BY created_at DESC LIMIT 50",
        array((int)$p['id'])
    );
    $reviewCount = count($productReviews);
    if ($reviewCount > 0) {
        $sum = 0;
        foreach ($productReviews as $pr) {
            $r = (int)$pr['rating'];
            $sum += $r;
            if (isset($ratingBreakdown[$r])) $ratingBreakdown[$r]++;
        }
        $avgRating = round($sum / $reviewCount, 1);
    }
} catch (Exception $e) {
    $productReviews = array();
}

ob_start();
?>
<section class="section">
<div class="container">
  <div class="product-detail">
    <?php
      $gallery = ProductImage::forProduct($p['id']);
      $mainImg = ProductImage::productThumb($p);
    ?>
    <div class="pd-gallery" style="display:flex;flex-direction:column;gap:12px;position:relative">
      <div class="pd-zoom-wrap" id="pd-zoom-wrap">
        <?php if ($mainImg): ?>
        <img src="<?= e($mainImg) ?>" alt="<?= e($p['name']) ?>" class="pd-zoom-img" id="main-img-el">
        <div class="pd-zoom-lens" id="pd-zoom-lens"></div>
        <button type="button" class="pd-full-btn" id="pd-full-btn" title="View full image"><i class="fas fa-expand"></i></button>
        <?php else: ?>
        <div class="pd-no-img"><i class="fas fa-shirt placeholder-icon"></i></div>
        <?php endif; ?>
      </div>
      <div class="pd-zoom-result" id="pd-zoom-result"></div>
      <div class="pd-lightbox" id="pd-lightbox" hidden>
        <button type="button" class="pd-lb-close" id="pd-lb-close" aria-label="Close">&times;</button>
        <img src="" alt="" id="pd-lb-img">
      </div>
      <?php if (count($gallery) > 1): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap" id="gallery-thumbs">
        <?php foreach ($gallery as $gi => $g):
          $gColor = !empty($g['color']) ? $g['color'] : '';
          // If no color label, map by index to product colors list
          if ($gColor === '' && !empty($colors)) {
              $colorList = array_values($colors);
              if (isset($colorList[$gi])) $gColor = $colorList[$gi];
          }
        ?>
        <button type="button" class="gallery-thumb"
          data-src="<?= e(ProductImage::url($g['image_path'])) ?>"
          data-path="<?= e($g['image_path']) ?>"
          data-color="<?= e($gColor) ?>"
          data-index="<?= (int)$gi ?>"
          style="width:72px;height:90px;border-radius:10px;overflow:hidden;border:2px solid <?= $g['is_primary'] ? 'var(--color-primary)' : 'var(--color-border)' ?>;cursor:pointer;padding:0;background:none">
          <img src="<?= e(ProductImage::url($g['image_path'])) ?>" style="width:100%;height:100%;object-fit:cover" alt="">
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <script>
      (function(){
        var img = document.getElementById('main-img-el');
        var wrap = document.getElementById('pd-zoom-wrap');
        var lens = document.getElementById('pd-zoom-lens');
        var result = document.getElementById('pd-zoom-result');
        if (!img || !wrap) return;

        function setResultBg() {
          if (result) {
            result.style.backgroundImage = 'url(' + img.src + ')';
          }
        }
        setResultBg();

        document.querySelectorAll('.gallery-thumb').forEach(function(btn){
          btn.addEventListener('click', function(){
            img.src = this.getAttribute('data-src');
            setResultBg();
            document.querySelectorAll('.gallery-thumb').forEach(function(b){ b.style.borderColor = 'var(--color-border)'; });
            this.style.borderColor = 'var(--color-primary)';
          });
        });

        // Desktop hover zoom (Amazon-style)
        if (window.matchMedia('(min-width: 993px)').matches && lens && result) {
          var zoom = 2.4;
          function move(e) {
            var rect = wrap.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            var lensW = rect.width / zoom;
            var lensH = rect.height / zoom;
            lens.style.width = lensW + 'px';
            lens.style.height = lensH + 'px';
            var lx = Math.max(0, Math.min(x - lensW / 2, rect.width - lensW));
            var ly = Math.max(0, Math.min(y - lensH / 2, rect.height - lensH));
            lens.style.left = lx + 'px';
            lens.style.top = ly + 'px';
            result.style.backgroundSize = (rect.width * zoom) + 'px ' + (rect.height * zoom) + 'px';
            result.style.backgroundPosition = (-lx * zoom) + 'px ' + (-ly * zoom) + 'px';
          }
          wrap.addEventListener('mouseenter', function(){
            lens.style.display = 'block';
            result.style.display = 'block';
            setResultBg();
          });
          wrap.addEventListener('mouseleave', function(){
            lens.style.display = 'none';
            result.style.display = 'none';
          });
          wrap.addEventListener('mousemove', move);
        } else {
          // Mobile: tap to toggle scale zoom
          var zoomed = false;
          wrap.addEventListener('click', function(e){
            if (!img || e.target.closest('.pd-full-btn')) return;
            zoomed = !zoomed;
            if (zoomed) {
              var rect = wrap.getBoundingClientRect();
              var x = ((e.clientX - rect.left) / rect.width) * 100;
              var y = ((e.clientY - rect.top) / rect.height) * 100;
              img.style.transformOrigin = x + '% ' + y + '%';
              img.style.transform = 'scale(2.2)';
              wrap.classList.add('is-zooming');
              wrap.style.cursor = 'zoom-out';
            } else {
              img.style.transform = 'scale(1)';
              wrap.classList.remove('is-zooming');
              wrap.style.cursor = 'zoom-in';
            }
          });
        }

        // Full image lightbox
        var lb = document.getElementById('pd-lightbox');
        var lbImg = document.getElementById('pd-lb-img');
        var fullBtn = document.getElementById('pd-full-btn');
        var lbClose = document.getElementById('pd-lb-close');
        function openLb() {
          if (!lb || !img) return;
          lbImg.src = img.src;
          lb.hidden = false;
          document.body.style.overflow = 'hidden';
        }
        function closeLb() {
          if (!lb) return;
          lb.hidden = true;
          document.body.style.overflow = '';
        }
        if (fullBtn) fullBtn.addEventListener('click', function(e){ e.stopPropagation(); openLb(); });
        if (lbClose) lbClose.addEventListener('click', closeLb);
        if (lb) lb.addEventListener('click', function(e){ if (e.target === lb) closeLb(); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeLb(); });
      })();
      </script>
    </div>
    <div class="pd-info">
      <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <?php if ($p['cat_name']): ?>
      <a href="/category/<?= e($p['cat_slug']) ?>" class="text-muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.05em"><?= e($p['cat_name']) ?></a>
      <?php endif; ?>
      <h1><?= e($p['name']) ?></h1>

      <!-- Star rating summary under title -->
      <div class="pd-rating" style="display:flex;align-items:center;gap:8px;margin:8px 0 14px;flex-wrap:wrap">
        <?php if ($reviewCount > 0): ?>
          <div class="pd-stars" style="display:inline-flex;gap:2px" title="<?= e((string)$avgRating) ?> out of 5">
            <?php
              $full = (int)floor($avgRating);
              $half = ($avgRating - $full) >= 0.5;
              for ($s = 1; $s <= 5; $s++):
                if ($s <= $full) {
                    echo '<i class="fas fa-star" style="color:#f59e0b;font-size:.95rem"></i>';
                } elseif ($s === $full + 1 && $half) {
                    echo '<i class="fas fa-star-half-alt" style="color:#f59e0b;font-size:.95rem"></i>';
                } else {
                    echo '<i class="far fa-star" style="color:#d4d4d4;font-size:.95rem"></i>';
                }
              endfor;
            ?>
          </div>
          <span style="font-weight:700;color:#f59e0b"><?= e((string)$avgRating) ?></span>
          <a href="#product-reviews" style="color:var(--color-text-muted);font-size:.9rem;text-decoration:underline">
            (<?= (int)$reviewCount ?> review<?= $reviewCount > 1 ? 's' : '' ?>)
          </a>
        <?php else: ?>
          <div class="pd-stars" style="display:inline-flex;gap:2px">
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <i class="far fa-star" style="color:#d4d4d4;font-size:.95rem"></i>
            <?php endfor; ?>
          </div>
          <a href="#product-reviews" style="color:var(--color-text-muted);font-size:.9rem">No reviews yet — be the first</a>
        <?php endif; ?>
      </div>

      <?php $isOos = (int)$p['stock'] <= 0; ?>
      <?php if ($isOos): ?>
      <div class="alert alert-error" style="margin-bottom:16px"><strong>Out of Stock</strong> — This item is currently unavailable.</div>
      <?php endif; ?>
      <div class="pd-price">
        <?= money(productPrice($p)) ?>
        <?php if (hasSale($p)): ?>
        <span style="text-decoration:line-through;color:var(--color-text-muted);font-size:1rem;font-weight:400;margin-left:8px"><?= money($p['price']) ?></span>
        <?php endif; ?>
      </div>
      <p class="pd-desc"><?= e($p['description'] ?: $p['short_description']) ?></p>
      <?php if ($p['material']): ?><p class="text-muted" style="font-size:.875rem;margin-bottom:20px"><strong>Material:</strong> <?= e($p['material']) ?></p><?php endif; ?>

      <form method="POST" action="/cart-add.php">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
        <input type="hidden" name="selected_image" id="selected-image-input" value="<?= e(!empty($p['image']) ? $p['image'] : (isset($gallery[0]['image_path']) ? $gallery[0]['image_path'] : '')) ?>">
        <?php if ($sizes): ?>
        <div class="option-group">
          <label>Size</label>
          <div class="option-pills">
            <?php foreach ($sizes as $i => $s): ?>
            <span class="option-pill <?= $i===0?'selected':'' ?>" data-input="size-input" data-value="<?= e($s) ?>"><?= e($s) ?></span>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="size" id="size-input" value="<?= e($sizes[0]) ?>">
        </div>
        <?php endif; ?>
        <?php if ($colors): ?>
        <div class="option-group">
          <label>Color</label>
          <div class="option-pills">
            <?php foreach ($colors as $i => $c): ?>
            <span class="option-pill color-pill <?= $i===0?'selected':'' ?>" data-input="color-input" data-value="<?= e($c) ?>" data-index="<?= (int)$i ?>"><?= e($c) ?></span>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="color" id="color-input" value="<?= e($colors[0]) ?>">
        </div>
        <?php endif; ?>
        <div class="option-group">
          <label>Quantity</label>
          <div class="qty-control">
            <button type="button" class="qty-minus">−</button>
            <input type="number" name="qty" value="1" min="1" max="<?= max(1,(int)$p['stock']) ?>">
            <button type="button" class="qty-plus">+</button>
          </div>
        </div>
        <div style="display:flex;gap:12px;margin-top:28px;flex-wrap:wrap;align-items:center">
          <button type="submit" class="btn btn-primary btn-lg" <?= $p['stock']<1?'disabled':'' ?>>
            <i class="fas fa-shopping-bag"></i> <?= $p['stock']<1?'Out of Stock':'Add to Cart' ?>
          </button>
          <a href="/shop" class="btn btn-outline btn-lg">Continue Shopping</a>
        </div>
      </form>
      <?php
        $callPhone = setting('site_phone', '');
        if ($callPhone):
      ?>
      <div class="pd-call-order" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--color-border)">
        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $callPhone)) ?>" style="display:inline-flex;align-items:center;gap:8px;color:var(--color-text);text-decoration:none;font-weight:600">
          <i class="fas fa-phone" style="color:var(--color-primary)"></i>
          Call For Order : <?= e($callPhone) ?>
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right sidebar: Delivery / Payment / Return (reference e-commerce style) -->
    <aside class="pd-sidebar">
      <div class="pd-side-card">
        <h3 class="pd-side-title">Delivery Options</h3>
        <ul class="pd-side-list">
          <li>
            <i class="fas fa-map-marker-alt"></i>
            <div>
              <strong>Available Delivery Area:</strong>
              <span><?= e(setting('site_address', 'All over Bangladesh')) ?></span>
            </div>
          </li>
          <li>
            <i class="fas fa-truck"></i>
            <div>
              <strong>Delivery Info</strong>
              <span>Delivery Time: 1–5 working days</span>
              <span>Inside Dhaka City: <?= money(70) ?></span>
              <span>Outside Dhaka City: <?= money(120) ?></span>
            </div>
          </li>
          <li>
            <i class="fas fa-hand-holding-usd"></i>
            <div>
              <strong>Cash on Delivery Available</strong>
            </div>
          </li>
        </ul>
      </div>

      <div class="pd-side-card">
        <h3 class="pd-side-title">Return &amp; Warranty</h3>
        <ul class="pd-side-list pd-side-plain">
          <li>Cancellation, Return &amp; Refund</li>
          <li>Change of mind is not applicable</li>
          <li class="pd-side-muted"><i class="fas fa-ban"></i> Warranty Not Available</li>
        </ul>
      </div>

      <div class="pd-side-card pd-side-seller">
        <div class="pd-side-seller-label">Sold By</div>
        <div class="pd-side-seller-name"><?= e(setting('site_name', 'AK')) ?></div>
      </div>
    </aside>
  </div>
</div>
</section>

<section class="section" style="padding-top:0" id="product-reviews">
  <div class="container">
    <div class="reviews-block">
      <div class="section-header" style="margin-bottom:20px">
        <div>
          <p class="text-muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Reviews</p>
          <h2 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0">
            Customer Reviews
            <?php if ($reviewCount > 0): ?>
              <span style="font-size:1.05rem;font-weight:700;color:#f59e0b">
                <?php for ($s=1;$s<=5;$s++): ?>
                  <i class="fas fa-star" style="color:<?= $s <= round($avgRating) ? '#f59e0b' : '#d4d4d4' ?>;font-size:.9rem"></i>
                <?php endfor; ?>
                <?= e((string)$avgRating) ?>
              </span>
              <span class="text-muted" style="font-size:.95rem;font-weight:500">(<?= (int)$reviewCount ?>)</span>
            <?php endif; ?>
          </h2>
        </div>
      </div>

      <?php if ($reviewCount > 0): ?>
      <!-- Rating summary + breakdown -->
      <div style="display:grid;grid-template-columns:auto 1fr;gap:28px;align-items:center;margin-bottom:28px;padding:18px 20px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:14px;max-width:520px">
        <div style="text-align:center">
          <div style="font-size:2.4rem;font-weight:800;line-height:1;color:#f59e0b"><?= e((string)$avgRating) ?></div>
          <div style="margin:6px 0">
            <?php for ($s=1;$s<=5;$s++): ?>
              <i class="fas fa-star" style="color:<?= $s <= round($avgRating) ? '#f59e0b' : '#d4d4d4' ?>"></i>
            <?php endfor; ?>
          </div>
          <div class="text-muted" style="font-size:.85rem"><?= (int)$reviewCount ?> review<?= $reviewCount > 1 ? 's' : '' ?></div>
        </div>
        <div>
          <?php for ($star = 5; $star >= 1; $star--):
            $cnt = $ratingBreakdown[$star];
            $pct = $reviewCount > 0 ? round(($cnt / $reviewCount) * 100) : 0;
          ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:.8rem">
            <span style="width:28px;text-align:right"><?= $star ?>★</span>
            <div style="flex:1;height:8px;background:var(--color-border);border-radius:99px;overflow:hidden">
              <div style="height:100%;width:<?= $pct ?>%;background:#f59e0b;border-radius:99px"></div>
            </div>
            <span class="text-muted" style="width:28px"><?= $cnt ?></span>
          </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Reviews side by side (grid) -->
      <div class="product-reviews-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:32px">
        <?php foreach ($productReviews as $rv): ?>
        <div class="review-card" style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:14px;padding:18px 18px 14px;box-shadow:0 2px 10px rgba(0,0,0,.04)">
          <div class="review-stars" style="margin-bottom:8px">
            <?php for ($s=1;$s<=5;$s++): ?>
              <i class="fas fa-star" style="color:<?= $s <= (int)$rv['rating'] ? '#f59e0b' : '#d4d4d4' ?>;font-size:.85rem"></i>
            <?php endfor; ?>
          </div>
          <p class="review-text" style="margin:0 0 12px;line-height:1.5;font-size:.95rem">"<?= e($rv['comment']) ?>"</p>
          <div class="review-name" style="font-size:.85rem;color:var(--color-text-muted)">
            <strong style="color:var(--color-text)"><?= e($rv['customer_name']) ?></strong>
            · <?= e(date('d M Y', strtotime($rv['created_at']))) ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="text-muted" style="margin-bottom:24px">No reviews yet. Be the first to review this product.</p>
      <?php endif; ?>

      <div class="review-form-card" style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:14px;padding:22px;max-width:560px">
        <h3 style="margin:0 0 14px;font-size:1.1rem">Write a review</h3>
        <?php if (Auth::checkCustomer()): ?>
        <form method="POST" action="/review-submit.php">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="product_slug" value="<?= e($p['slug']) ?>">
          <div class="form-group">
            <label>Your rating</label>
            <select name="rating" class="form-control" style="max-width:180px">
              <option value="5">5 ★ Excellent</option>
              <option value="4">4 ★ Good</option>
              <option value="3">3 ★ Average</option>
              <option value="2">2 ★ Poor</option>
              <option value="1">1 ★ Bad</option>
            </select>
          </div>
          <div class="form-group">
            <label>Your comment</label>
            <textarea name="comment" class="form-control" rows="3" required minlength="5" placeholder="Share your experience with this product…"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Submit review</button>
          <p class="text-muted" style="font-size:.8rem;margin-top:8px">Reviews are checked by admin before they appear on the product.</p>
        </form>
        <?php else: ?>
        <p class="text-muted">Please <a href="/login.php">login</a> as a registered customer to leave a star rating and comment.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<script>
(function(){
  var mainImg = document.getElementById('main-img-el');
  var selectedInput = document.getElementById('selected-image-input');
  var colorInput = document.getElementById('color-input');
  var sizeInput = document.getElementById('size-input');

  function setMainFromThumb(thumb) {
    if (!thumb || !mainImg) return;
    var src = thumb.getAttribute('data-src');
    var path = thumb.getAttribute('data-path') || '';
    var color = thumb.getAttribute('data-color') || '';
    if (src) mainImg.src = src;
    if (selectedInput && path) selectedInput.value = path;
    if (color && colorInput) {
      colorInput.value = color;
      document.querySelectorAll('.color-pill').forEach(function(p){
        p.classList.toggle('selected', p.getAttribute('data-value') === color);
      });
    }
    document.querySelectorAll('.gallery-thumb').forEach(function(b){
      b.style.borderColor = 'var(--color-border)';
    });
    thumb.style.borderColor = 'var(--color-primary)';
    if (typeof setResultBg === 'function') { /* zoom */ }
    var result = document.getElementById('pd-zoom-result');
    if (result) result.style.backgroundImage = 'url(' + mainImg.src + ')';
  }

  document.querySelectorAll('.gallery-thumb').forEach(function(btn){
    btn.addEventListener('click', function(){ setMainFromThumb(btn); });
  });

  // Color pill → matching gallery image (by color label or by index)
  document.querySelectorAll('.color-pill').forEach(function(pill){
    pill.addEventListener('click', function(){
      var color = pill.getAttribute('data-value');
      var index = pill.getAttribute('data-index');
      if (colorInput) colorInput.value = color;
      document.querySelectorAll('.color-pill').forEach(function(p){ p.classList.remove('selected'); });
      pill.classList.add('selected');
      var thumbs = document.querySelectorAll('.gallery-thumb');
      var matched = null;
      thumbs.forEach(function(th){
        if ((th.getAttribute('data-color') || '').toLowerCase() === (color || '').toLowerCase()) matched = th;
      });
      if (!matched && index !== null && thumbs[index]) matched = thumbs[index];
      if (matched) setMainFromThumb(matched);
    });
  });

  // Size pills
  document.querySelectorAll('.option-pill[data-input="size-input"]').forEach(function(pill){
    pill.addEventListener('click', function(){
      if (sizeInput) sizeInput.value = pill.getAttribute('data-value');
      document.querySelectorAll('.option-pill[data-input="size-input"]').forEach(function(p){ p.classList.remove('selected'); });
      pill.classList.add('selected');
    });
  });

  // Init selected_image from first/primary thumb
  var first = document.querySelector('.gallery-thumb');
  if (first && selectedInput && !selectedInput.value) {
    selectedInput.value = first.getAttribute('data-path') || '';
  }

  // qty buttons
  document.querySelectorAll('.qty-minus').forEach(function(btn){
    btn.addEventListener('click', function(){
      var input = btn.parentNode.querySelector('input[name=qty]');
      if (input) input.value = Math.max(1, (parseInt(input.value,10)||1) - 1);
    });
  });
  document.querySelectorAll('.qty-plus').forEach(function(btn){
    btn.addEventListener('click', function(){
      var input = btn.parentNode.querySelector('input[name=qty]');
      if (input) {
        var max = parseInt(input.getAttribute('max'),10) || 99;
        input.value = Math.min(max, (parseInt(input.value,10)||1) + 1);
      }
    });
  });
})();
</script>

<?php $content = ob_get_clean(); require dirname(__DIR__).'/layouts/frontend.php';