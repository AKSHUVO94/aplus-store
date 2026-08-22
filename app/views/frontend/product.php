<?php
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
$p = Database::fetch("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.status='active'", [$slug]);
if (!$p) { http_response_code(404); require __DIR__.'/404.php'; return; }
Database::query("UPDATE products SET views=views+1 WHERE id=?", [$p['id']]);
$pageTitle = $p['name'];
$sizes = array_filter(array_map('trim', explode(',', $p['sizes'] ?: '')));
$colors = array_filter(array_map('trim', explode(',', $p['colors'] ?: '')));
$success = flash('success'); $error = flash('error');
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
      <div class="pd-zoom-wrap" id="pd-zoom-wrap" style="aspect-ratio:3/4">
        <?php if ($mainImg): ?>
        <img src="<?= e($mainImg) ?>" alt="<?= e($p['name']) ?>" class="pd-zoom-img" id="main-img-el">
        <div class="pd-zoom-lens" id="pd-zoom-lens"></div>
        <?php else: ?>
        <div style="width:100%;height:100%;display:grid;place-items:center"><i class="fas fa-shirt placeholder-icon"></i></div>
        <?php endif; ?>
      </div>
      <div class="pd-zoom-result" id="pd-zoom-result"></div>
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
            if (!img) return;
            zoomed = !zoomed;
            if (zoomed) {
              var rect = wrap.getBoundingClientRect();
              var x = ((e.clientX - rect.left) / rect.width) * 100;
              var y = ((e.clientY - rect.top) / rect.height) * 100;
              img.style.transformOrigin = x + '% ' + y + '%';
              img.style.transform = 'scale(2.2)';
              wrap.style.cursor = 'zoom-out';
            } else {
              img.style.transform = 'scale(1)';
              wrap.style.cursor = 'zoom-in';
            }
          });
        }
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
        <div style="display:flex;gap:12px;margin-top:28px;flex-wrap:wrap">
          <button type="submit" class="btn btn-primary btn-lg" <?= $p['stock']<1?'disabled':'' ?>>
            <i class="fas fa-shopping-bag"></i> <?= $p['stock']<1?'Out of Stock':'Add to Cart' ?>
          </button>
          <a href="/shop" class="btn btn-outline btn-lg">Continue Shopping</a>
        </div>
      </form>
      
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

<?php $content = ob_get_clean(); require dirname(__DIR__).'/layouts/frontend.php'; ?>