<?php
declare(strict_types=1);
function config($key, $default = null) {
    static $cfg = null;
    if ($cfg === null) $cfg = require dirname(__DIR__) . '/config/config.php';
    $keys = explode('.', $key);
    $v = $cfg;
    foreach ($keys as $k) {
        if (!isset($v[$k])) return $default;
        $v = $v[$k];
    }
    return $v;
}
function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header('Location: '.$url); exit; }
function asset($path) { return '/assets/'.ltrim($path,'/'); }
function url($path = '') { return '/'.ltrim($path,'/'); }
function money($amount) {
    $sym = setting('currency_symbol', '৳');
    return $sym . number_format((float)$amount, 0);
}
function setting($key, $default = null) {
    if (!isset($GLOBALS['_settings_cache']) || !is_array($GLOBALS['_settings_cache'])) {
        $GLOBALS['_settings_cache'] = array();
    }
    $cache =& $GLOBALS['_settings_cache'];
    if (!array_key_exists($key, $cache)) {
        try {
            $row = Database::fetch("SELECT value FROM settings WHERE `key`=?", array($key));
            $cache[$key] = $row ? $row['value'] : $default;
        } catch (Exception $e) {
            $cache[$key] = $default;
        }
    }
    return $cache[$key];
}
function setting_clear_cache() {
    $GLOBALS['_settings_cache'] = array();
}

function flash($key, $msg = null) {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return null; }
    $m = isset($_SESSION['flash'][$key]) ? $_SESSION['flash'][$key] : null;
    unset($_SESSION['flash'][$key]);
    return $m;
}
function old($key, $default = '') {
    return e(isset($_SESSION['old'][$key]) ? $_SESSION['old'][$key] : $default);
}
function view($path, $data = []) {
    extract($data);
    $file = config('paths.views').'/'.str_replace('.','/',$path).'.php';
    if (!file_exists($file)) die("View not found: $path");
    require $file;
}
function formatDate($d, $f = 'M d, Y') { return $d ? date($f, strtotime($d)) : ''; }
function timeAgo($dt) {
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60).' min ago';
    if ($diff < 86400) return floor($diff/3600).' hrs ago';
    return formatDate($dt);
}
function truncate($t, $len = 100) {
    if (strlen($t) <= $len) return $t;
    return substr($t, 0, $len).'...';
}
function activeClass($path, $class = 'active') {
    $cur = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
    if (!$cur) $cur = '';
    if ($path === '/' || $path === '/admin/') return ($cur === $path || $cur === rtrim($path,'/')) ? $class : '';
    return (strpos($cur, $path) === 0) ? $class : '';
}
function slugify($t) {
    $t = strtolower(trim($t));
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim($t, '-');
}
function productPrice($p) {
    if (!empty($p['sale_price']) && $p['sale_price'] > 0 && $p['sale_price'] < $p['price']) {
        return (float)$p['sale_price'];
    }
    return (float)$p['price'];
}
function hasSale($p) {
    return !empty($p['sale_price']) && $p['sale_price'] > 0 && $p['sale_price'] < $p['price'];
}

function discountPercent($p) {
    if (!hasSale($p)) {
        return 0;
    }
    $price = (float)$p['price'];
    $sale = (float)$p['sale_price'];
    if ($price <= 0 || $sale <= 0 || $sale >= $price) {
        return 0;
    }
    return (int) round((($price - $sale) / $price) * 100);
}



function csrf_token() {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}
function csrf_verify($token) {
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}


/**
 * Resolve image URL for an order_items row
 */
function order_item_image($item) {
    if (!empty($item['product_image'])) {
        return ProductImage::url($item['product_image']);
    }
    if (!empty($item['product_id'])) {
        $p = Database::fetch("SELECT * FROM products WHERE id = ?", array((int)$item['product_id']));
        if ($p) {
            $color = isset($item['color']) ? $item['color'] : '';
            $size = isset($item['size']) ? $item['size'] : '';
            if ($color !== '' || $size !== '') {
                return ProductImage::thumbForVariant($p, $color, $size);
            }
            return ProductImage::productThumb($p);
        }
    }
    return null;
}

/**
 * Brand watermark HTML for product images
 */
function brand_watermark_html() {
    $logo = setting('brand_logo', '');
    $text = setting('brand_watermark_text', '');
    $enabled = setting('brand_watermark_enabled', '1') === '1';
    if (!$enabled) {
        return '';
    }
    if ($logo === '' && $text === '') {
        $text = setting('site_name', 'AK');
    }
    $opacity = setting('brand_watermark_opacity', '28');
    $opacity = max(5, min(90, (int)$opacity)) / 100;
    $pos = setting('brand_watermark_position', 'center');
    $size = setting('brand_watermark_size', '72');
    $size = max(24, min(160, (int)$size));
    $posMap = array(
        'center' => 'left:50%;top:50%;transform:translate(-50%,-50%);',
        'bottom-right' => 'right:10px;bottom:10px;left:auto;top:auto;transform:none;',
        'bottom-left' => 'left:10px;bottom:10px;right:auto;top:auto;transform:none;',
        'top-right' => 'right:10px;top:10px;left:auto;bottom:auto;transform:none;',
        'top-left' => 'left:10px;top:10px;right:auto;bottom:auto;transform:none;',
    );
    $stylePos = isset($posMap[$pos]) ? $posMap[$pos] : $posMap['center'];
    $html = '<div class="brand-watermark" style="opacity:' . $opacity . ';' . $stylePos . '" aria-hidden="true">';
    if ($logo !== '') {
        $src = '/' . ltrim($logo, '/');
        $html .= '<img src="' . e($src) . '" alt="" class="brand-wm-logo" style="max-width:' . $size . 'px;max-height:' . (int)($size * 0.7) . 'px">';
    }
    if ($text !== '') {
        $html .= '<span class="brand-wm-text">' . e($text) . '</span>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Product card with watermark + add to cart
 */
/**
 * Product card with watermark + add to cart (size/color aware)
 */
function render_product_card($p) {
    $thumb = ProductImage::productThumb($p);
    $oos = (int)$p['stock'] <= 0;
    $href = '/product.php?slug=' . rawurlencode($p['slug']);
    $cat = isset($p['cat_name']) ? $p['cat_name'] : '';
    $sizes = array_values(array_filter(array_map('trim', explode(',', isset($p['sizes']) ? $p['sizes'] : ''))));
    $colors = array_values(array_filter(array_map('trim', explode(',', isset($p['colors']) ? $p['colors'] : ''))));
    $needOpts = (!$oos) && (count($sizes) > 0 || count($colors) > 0);
    ob_start();
    ?>
    <div class="product-card apex-card <?= $oos ? 'is-oos' : '' ?>">
      <div class="product-thumb"<?= $thumb ? ' style="background:none"' : '' ?>>
        <a href="<?= $href ?>" class="product-card-link thumb-link" title="<?= e($p['name']) ?>">
          <div class="product-badges">
            <?php if ($oos): ?>
            <span class="badge-oos">Out of Stock</span>
            <?php else: ?>
              <?php if (hasSale($p)):
                $pct = discountPercent($p);
              ?>
              <span class="badge-sale-pct"><?= $pct > 0 ? '-'.$pct.'%' : 'Sale' ?></span>
              <?php endif; ?>
              <?php if (!empty($p['is_new'])): ?><span class="badge-new">New</span><?php endif; ?>
            <?php endif; ?>
          </div>
          <?php if ($thumb): ?>
          <img src="<?= e($thumb) ?>" alt="<?= e($p['name']) ?>" draggable="false" loading="lazy" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;pointer-events:none<?= $oos ? ';opacity:.55' : '' ?>">
          <?php else: ?>
          <i class="fas fa-shirt placeholder-icon"></i>
          <?php endif; ?>
          <?= brand_watermark_html() ?>
        </a>
        <button type="button" class="btn-quick-view" data-qv-id="<?= (int)$p['id'] ?>" title="Quick View">
          <i class="fas fa-eye"></i> Quick View
        </button>
      </div>
      <div class="product-body">
        <a href="<?= $href ?>" class="product-card-link">
          <div class="product-cat"><?= e($cat) ?></div>
          <div class="product-name"><?= e($p['name']) ?></div>
        </a>
        <div class="product-card-footer">
          <div class="product-price">
            <span class="price-current"><?= money(productPrice($p)) ?></span>
            <?php if (hasSale($p) && !$oos): ?><span class="price-old"><?= money($p['price']) ?></span><?php endif; ?>
          </div>
          <?php if (!$oos): ?>
            <?php if ($needOpts): ?>
            <button type="button" class="btn-add-cart btn-quick-add"
              title="Add to cart"
              data-id="<?= (int)$p['id'] ?>"
              data-name="<?= e($p['name']) ?>"
              data-sizes="<?= e(implode(',', $sizes)) ?>"
              data-colors="<?= e(implode(',', $colors)) ?>">
              <i class="fas fa-shopping-bag"></i>
            </button>
            <?php else: ?>
            <form method="POST" action="/cart-add.php" class="card-cart-form">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="qty" value="1">
              <button type="submit" class="btn-add-cart" title="Add to cart"><i class="fas fa-shopping-bag"></i></button>
            </form>
            <?php endif; ?>
          <?php else: ?>
          <span class="btn-add-cart is-disabled" title="Out of stock"><i class="fas fa-ban"></i></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}