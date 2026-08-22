<?php
$pageTitle = 'Cart';
$items = Cart::items();
$success = flash('success');
$error = flash('error');
ob_start();
?>
<section class="section cart-page">
<div class="container cart-container">
  <h1 class="cart-title">Your Cart</h1>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <?php if (empty($items)): ?>
  <div class="empty-state">
    <i class="fas fa-shopping-bag"></i>
    <p>Your cart is empty</p>
    <a href="/shop.php" class="btn btn-primary" style="margin-top:16px">Start Shopping</a>
  </div>
  <?php else: ?>
  <div class="cart-layout">
    <div class="cart-items">
      <?php foreach ($items as $key => $item):
        $product = null;
        if (!empty($item['product_id'])) {
            $product = Database::fetch("SELECT * FROM products WHERE id=?", array($item['product_id']));
        }
        $size = !empty($item['size']) ? $item['size'] : '';
        $color = !empty($item['color']) ? $item['color'] : '';
        // Prefer image stored for this variant; else match by color
        $thumb = !empty($item['image']) ? $item['image'] : null;
        if (!$thumb && $product) {
            $thumb = ProductImage::thumbForVariant($product, $color, $size);
        }
        $sizes = $product ? array_values(array_filter(array_map('trim', explode(',', $product['sizes'] ?: '')))) : array();
        $colors = $product ? array_values(array_filter(array_map('trim', explode(',', $product['colors'] ?: '')))) : array();
        $canEditOpts = count($sizes) > 0 || count($colors) > 0;
      ?>
      <div class="cart-item">
        <div class="cart-item-img">
          <?php if ($thumb): ?>
          <img src="<?= e($thumb) ?>" alt="<?= e($item['name']) ?>">
          <?php endif; ?>
        </div>
        <div class="cart-item-info">
          <strong class="cart-item-name"><?= e($item['name']) ?></strong>
          <?php if ($size !== '' || $color !== ''): ?>
          <div class="cart-item-meta">
            <?php if ($size !== ''): ?><span>Size: <strong><?= e($size) ?></strong></span><?php endif; ?>
            <?php if ($size !== '' && $color !== ''): ?><span class="meta-dot">·</span><?php endif; ?>
            <?php if ($color !== ''): ?><span>Color: <strong><?= e($color) ?></strong></span><?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="cart-item-price"><?= money($item['price']) ?></div>

          <?php if ($canEditOpts): ?>
          <form method="POST" action="/cart.php" class="cart-opts-form">
            <input type="hidden" name="action" value="change_options">
            <input type="hidden" name="key" value="<?= e($key) ?>">
            <?php if (count($sizes) > 0): ?>
            <select name="size" class="form-control cart-opt-select" required>
              <?php foreach ($sizes as $s): ?>
              <option value="<?= e($s) ?>" <?= $size === $s ? 'selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if (count($colors) > 0): ?>
            <select name="color" class="form-control cart-opt-select" required>
              <?php foreach ($colors as $c): ?>
              <option value="<?= e($c) ?>" <?= $color === $c ? 'selected' : '' ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-sm btn-outline">Apply</button>
          </form>
          <?php endif; ?>

          <div class="cart-item-actions">
            <form method="POST" action="/cart.php" class="cart-qty-form">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="key" value="<?= e($key) ?>">
              <input type="number" name="qty" value="<?= (int)$item['qty'] ?>" min="1" class="form-control qty-input">
              <button type="submit" class="btn btn-sm btn-outline">Update</button>
            </form>
            <form method="POST" action="/cart.php">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="key" value="<?= e($key) ?>">
              <button type="submit" class="btn btn-sm btn-remove" title="Remove"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </div>
        <div class="cart-item-total"><?= money($item['price'] * $item['qty']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <aside class="cart-summary">
      <h3>Order Summary</h3>
      <div class="summary-items">
        <?php foreach ($items as $item):
          $size = !empty($item['size']) ? $item['size'] : '';
          $color = !empty($item['color']) ? $item['color'] : '';
        ?>
        <div class="summary-line">
          <div class="summary-line-main">
            <span class="summary-line-name"><?= e($item['name']) ?></span>
            <span class="summary-line-qty">× <?= (int)$item['qty'] ?></span>
          </div>
          <?php if ($size !== '' || $color !== ''): ?>
          <div class="summary-line-opts">
            <?php if ($size !== ''): ?>Size: <?= e($size) ?><?php endif; ?>
            <?php if ($size !== '' && $color !== ''): ?> · <?php endif; ?>
            <?php if ($color !== ''): ?>Color: <?= e($color) ?><?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="summary-line-price"><?= money($item['price'] * $item['qty']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="summary-row"><span>Subtotal</span><span><?= money(Cart::subtotal()) ?></span></div>
      <div class="summary-row"><span>Shipping</span><span><?= Cart::shipping() > 0 ? money(Cart::shipping()) : 'FREE' ?></span></div>
      <div class="summary-row total"><span>Total</span><span><?= money(Cart::total()) ?></span></div>
      <?php if (Cart::shipping() > 0): ?>
      <p class="text-muted summary-note">Free shipping on orders over <?= money(setting('free_shipping_min', 3000)) ?></p>
      <?php endif; ?>
      <a href="/checkout.php" class="btn btn-primary btn-block btn-lg cart-checkout-btn">Checkout</a>
      <a href="/shop.php" class="btn btn-outline btn-block" style="margin-top:10px">Continue Shopping</a>
    </aside>
  </div>
  <?php endif; ?>
</div>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/frontend.php';