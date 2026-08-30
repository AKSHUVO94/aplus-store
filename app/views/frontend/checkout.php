<?php
$pageTitle = 'Checkout';
$items = Cart::items();
if (empty($items)) {
    redirect('/cart.php');
}
$error = flash('error');

$methods = array();
if (setting('pay_cod_enabled', '1') === '1') {
    $methods['cod'] = 'Cash on Delivery';
}
if (setting('pay_bkash_enabled', '0') === '1') {
    $methods['bkash'] = 'bKash';
}
if (setting('pay_nagad_enabled', '0') === '1') {
    $methods['nagad'] = 'Nagad';
}
if (setting('pay_rocket_enabled', '0') === '1') {
    $methods['rocket'] = 'Rocket';
}
if (setting('pay_bank_enabled', '0') === '1') {
    $methods['bank'] = 'Bank Transfer';
}
if (setting('pay_visa_enabled', '0') === '1') {
    $methods['visa'] = 'Visa Card';
}
if (setting('pay_mastercard_enabled', '0') === '1') {
    $methods['mastercard'] = 'Master Card';
}
if (setting('pay_card_enabled', '0') === '1') {
    $methods['card'] = 'Card';
}
if (empty($methods)) {
    $methods['cod'] = 'Cash on Delivery';
}

$keys = array_keys($methods);
$sel = old('payment', $keys[0]);
$prefill = array('name' => '', 'email' => '', 'phone' => '', 'address' => '', 'city' => 'Dhaka');
if (Auth::checkCustomer()) {
    $u = Auth::user();
    $row = Database::fetch('SELECT * FROM users WHERE id=?', array(Auth::id()));
    $prefill['name'] = $u['name'];
    $prefill['email'] = $u['email'];
    if ($row) {
        $prefill['phone'] = !empty($row['phone']) ? $row['phone'] : '';
        $prefill['address'] = !empty($row['address']) ? $row['address'] : '';
        $prefill['city'] = !empty($row['city']) ? $row['city'] : 'Dhaka';
    }
}

ob_start();
?>
<section class="section checkout-page">
<div class="container checkout-wrap">
  <h1 class="checkout-title">Checkout</h1>
  <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

  <form method="POST" action="/checkout.php" id="checkout-form" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <div class="cart-layout">
    <div>
      <div class="checkout-card">
        <h3 class="checkout-card-title">Shipping Details</h3>
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" class="form-control co-input" required value="<?php echo old('name', $prefill['name']); ?>" placeholder="Your full name"></div>
          <div class="form-group"><label>Phone *</label><input type="text" name="phone" class="form-control co-input" required value="<?php echo old('phone', $prefill['phone']); ?>" placeholder="01XXXXXXXXX"></div>
        </div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control co-input" required value="<?php echo old('email', $prefill['email']); ?>" placeholder="you@email.com"></div>
        <div class="form-group"><label>Address *</label><textarea name="address" class="form-control co-input" required rows="3" placeholder="House, road, area"><?php echo old('address', $prefill['address']); ?></textarea></div>
        <div class="form-group"><label>City *</label>
          <input type="text" name="city" id="checkout-city" class="form-control co-input" required value="<?php echo old('city', $prefill['city']); ?>" placeholder="e.g. Dhaka, Chittagong">
          <small class="text-muted">Shipping: Dhaka ৳70 · Outside Dhaka ৳120</small>
        </div>
        <div class="form-group"><label>Order Notes</label><textarea name="notes" class="form-control co-input" rows="2" placeholder="Optional"><?php echo old('notes'); ?></textarea></div>
      </div>

      <div class="checkout-card">
        <h3 class="checkout-card-title">Payment Method</h3>
        <div class="pay-list">
          <?php foreach ($methods as $k => $label): ?>
          <label class="pay-option<?= $sel === $k ? ' is-active' : '' ?>">
            <input type="radio" name="payment_method" value="<?php echo e($k); ?>" <?php echo $sel === $k ? 'checked' : ''; ?> onchange="showPayInfo('<?php echo e($k); ?>')">
            <span class="pay-label"><?php echo e($label); ?></span>
            <?php if ($k === 'cod'): ?>
            <span class="pay-hint">Pay when delivered</span>
            <?php else: ?>
            <span class="pay-hint">Pay now + proof</span>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>

        <div id="pay-info-box" class="pay-info-box" style="display:none"></div>

        <div id="proof-wrap" style="display:none">
          <div class="alert alert-error" style="margin-bottom:14px;font-size:.875rem">
            <strong>Payment required before order.</strong> Send money first, then enter Transaction ID and upload screenshot.
          </div>
          <div class="form-group">
            <label>Transaction ID / TrxID *</label>
            <input type="text" name="transaction_id" id="transaction_id" class="form-control co-input" placeholder="e.g. 8N7XXXXXX" value="<?php echo old('transaction_id'); ?>">
          </div>
          <div class="form-group">
            <label>Payment Screenshot *</label>
            <input type="file" name="payment_proof" id="payment_proof" class="form-control co-input" accept="image/jpeg,image/png,image/webp,image/jpg">
            <small class="text-muted">JPG, PNG or WebP — max 3 MB</small>
          </div>
          <div id="proof-preview" style="display:none;margin-top:10px">
            <img id="proof-preview-img" src="" alt="Preview" style="max-width:220px;border-radius:10px;border:1px solid #ddd">
          </div>
        </div>
      </div>
    </div>

    <?php
      $chkCity = old('city', $prefill['city']);
      $chkShip = Cart::shipping($chkCity);
      $chkSub = Cart::subtotal();
      $chkDisc = class_exists('Coupon') ? Coupon::discount() : 0;
      $chkCoupon = class_exists('Coupon') ? Coupon::current() : null;
      $chkTotal = max(0, $chkSub + $chkShip - $chkDisc);
      $dhakaRate = (float) setting('shipping_cost_dhaka', 70);
      $outRate = (float) setting('shipping_cost_outside', setting('shipping_cost', 120));
      $freeMin = (float) setting('free_shipping_min', 3000);
    ?>
    <div class="cart-summary checkout-summary">
      <h3 style="margin-bottom:16px">Order Summary</h3>
      <div class="summary-items">
        <?php foreach ($items as $item):
          $sz = !empty($item['size']) ? $item['size'] : '';
          $cl = !empty($item['color']) ? $item['color'] : '';
        ?>
        <div class="summary-line">
          <div class="summary-line-main">
            <span class="summary-line-name"><?php echo e($item['name']); ?></span>
            <span class="summary-line-qty">× <?php echo (int)$item['qty']; ?></span>
          </div>
          <?php if ($sz !== '' || $cl !== ''): ?>
          <div class="summary-line-opts">
            <?php if ($sz !== ''): ?>Size: <?php echo e($sz); ?><?php endif; ?>
            <?php if ($sz !== '' && $cl !== ''): ?> · <?php endif; ?>
            <?php if ($cl !== ''): ?>Color: <?php echo e($cl); ?><?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="summary-line-price"><?php echo money($item['price'] * $item['qty']); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="summary-row"><span>Subtotal</span><span id="sum-subtotal"><?php echo money($chkSub); ?></span></div>
      <div class="summary-row"><span>Shipping</span><span id="sum-shipping"><?php echo $chkShip > 0 ? money($chkShip) : 'FREE'; ?></span></div>
      <?php if ($chkDisc > 0 && $chkCoupon): ?>
      <div class="summary-row" style="color:#059669"><span>Coupon (<?php echo e($chkCoupon['code']); ?>)</span><span id="sum-discount">-<?php echo money($chkDisc); ?></span></div>
      <?php else: ?>
      <div class="summary-row" id="sum-discount-row" style="display:none;color:#059669"><span>Coupon</span><span id="sum-discount">-৳0</span></div>
      <?php endif; ?>
      <div class="summary-row total"><span>Total</span><span id="sum-total"><?php echo money($chkTotal); ?></span></div>

      <div class="coupon-box" style="margin:14px 0 8px;padding-top:12px;border-top:1px dashed #e5e5e5">
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:6px">Have a coupon?</label>
        <?php if ($chkCoupon): ?>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="badge" style="background:#ecfdf5;color:#059669;padding:6px 10px;border-radius:8px;font-weight:600"><?php echo e($chkCoupon['code']); ?> applied</span>
            <button type="submit" name="coupon_action" value="remove" class="btn btn-outline btn-sm" formnovalidate>Remove</button>
          </div>
        <?php else: ?>
          <div style="display:flex;gap:8px">
            <input type="text" name="coupon_code" class="form-control" placeholder="e.g. AK50" style="text-transform:uppercase;flex:1" formnovalidate>
            <button type="submit" name="coupon_action" value="apply" class="btn btn-outline" formnovalidate>Apply</button>
          </div>
        <?php endif; ?>
      </div>
      <p class="summary-note" id="sum-ship-note">
        <?php if ($freeMin > 0 && $chkSub >= $freeMin): ?>
          Free shipping on this order
        <?php else: ?>
          Dhaka ৳<?php echo (int)$dhakaRate; ?> · Outside Dhaka ৳<?php echo (int)$outRate; ?><?php if ($freeMin > 0): ?> · Free over <?php echo money($freeMin); ?><?php endif; ?>
        <?php endif; ?>
      </p>
      <button type="submit" class="co-place-btn" id="place-order-btn">
        <i class="fas fa-lock"></i> Place Order
      </button>
    </div>
  </div>
  </form>
</div>
</section>
<script>
var payData = {
  cod: { html: '<strong>Cash on Delivery</strong><br>Pay in cash when your order arrives. No TrxID needed.' },
  bkash: { html: '<strong>bKash <?php echo e(setting("pay_bkash_type","Personal")); ?></strong><br>Payment to: <strong><?php echo e(setting("pay_bkash_number","01XXXXXXXXX")); ?></strong><br>Then enter TrxID + upload screenshot below.' },
  nagad: { html: '<strong>Nagad <?php echo e(setting("pay_nagad_type","Personal")); ?></strong><br>Payment to: <strong><?php echo e(setting("pay_nagad_number","01XXXXXXXXX")); ?></strong><br>Then enter TrxID + upload screenshot below.' },
  rocket: { html: '<strong>Rocket <?php echo e(setting("pay_rocket_type","Personal")); ?></strong><br>Payment to: <strong><?php echo e(setting("pay_rocket_number","01XXXXXXXXX")); ?></strong><br>Then enter TrxID + upload screenshot below.' },
  bank: { html: '<strong><?php echo e(setting("pay_bank_name","Bank Transfer")); ?></strong><br>A/C Name: <?php echo e(setting("pay_bank_account_name")); ?><br>A/C No: <strong><?php echo e(setting("pay_bank_account_no")); ?></strong><br>Branch: <?php echo e(setting("pay_bank_branch")); ?><br>Then enter reference + upload screenshot.' },
  visa: { html: '<strong>Visa Card</strong><br>Payment to account: <strong><?php echo e(setting("pay_visa_account","")); ?></strong><br>Transfer/pay then enter Transaction / Ref ID + upload screenshot below.' },
  mastercard: { html: '<strong>Master Card</strong><br>Payment to account: <strong><?php echo e(setting("pay_mastercard_account","")); ?></strong><br>Transfer/pay then enter Transaction / Ref ID + upload screenshot below.' },
  card: { html: '<strong>Card Payment</strong><br>We will contact you with a payment link.' }
};
var online = { bkash:1, nagad:1, rocket:1, bank:1, visa:1, mastercard:1 };

function showPayInfo(m) {
  var box = document.getElementById('pay-info-box');
  var proof = document.getElementById('proof-wrap');
  var trx = document.getElementById('transaction_id');
  var file = document.getElementById('payment_proof');
  if (payData[m]) {
    box.innerHTML = payData[m].html;
    box.style.display = 'block';
  } else {
    box.style.display = 'none';
  }
  if (online[m]) {
    proof.style.display = 'block';
    trx.setAttribute('required', 'required');
    file.setAttribute('required', 'required');
  } else {
    proof.style.display = 'none';
    trx.removeAttribute('required');
    file.removeAttribute('required');
    trx.value = '';
  }
}

document.querySelectorAll('.pay-option').forEach(function (el) {
  el.addEventListener('click', function () {
    document.querySelectorAll('.pay-option').forEach(function (x) {
      x.classList.remove('is-active');
    });
    el.classList.add('is-active');
  });
});

var fileInput = document.getElementById('payment_proof');
if (fileInput) {
  fileInput.addEventListener('change', function () {
    var prev = document.getElementById('proof-preview');
    var img = document.getElementById('proof-preview-img');
    if (this.files && this.files[0]) {
      var url = URL.createObjectURL(this.files[0]);
      img.src = url;
      prev.style.display = 'block';
    } else {
      prev.style.display = 'none';
    }
  });
}

var checked = document.querySelector('input[name=payment_method]:checked');
if (checked) showPayInfo(checked.value);

document.getElementById('checkout-form').addEventListener('submit', function (e) {
  var m = document.querySelector('input[name=payment_method]:checked');
  if (!m) return;
  if (online[m.value]) {
    var trxVal = document.getElementById('transaction_id').value.trim();
    var f = document.getElementById('payment_proof');
    if (!trxVal) {
      e.preventDefault();
      alert('Please enter Transaction ID / TrxID.');
      return;
    }
    if (!f.files || !f.files.length) {
      e.preventDefault();
      alert('Please upload payment screenshot.');
    }
  }
});

(function () {
  var cityInput = document.getElementById('checkout-city');
  var shipEl = document.getElementById('sum-shipping');
  var totalEl = document.getElementById('sum-total');
  if (!cityInput || !shipEl || !totalEl) return;
  var subtotal = <?php echo json_encode((float)$chkSub); ?>;
  var discount = <?php echo json_encode((float)$chkDisc); ?>;
  var dhaka = <?php echo json_encode((float)$dhakaRate); ?>;
  var outside = <?php echo json_encode((float)$outRate); ?>;
  var freeMin = <?php echo json_encode((float)$freeMin); ?>;
  function fmt(n) {
    return '৳' + Math.round(n).toLocaleString('en-US');
  }
  function isDhaka(c) {
    c = (c || '').toLowerCase();
    return c.indexOf('dhaka') !== -1 || c.indexOf('ঢাকা') !== -1;
  }
  function updateShip() {
    var ship = 0;
    if (!(freeMin > 0 && subtotal >= freeMin)) {
      ship = isDhaka(cityInput.value) ? dhaka : outside;
    }
    shipEl.textContent = ship > 0 ? fmt(ship) : 'FREE';
    var tot = subtotal + ship - (discount || 0);
    if (tot < 0) tot = 0;
    totalEl.textContent = fmt(tot);
  }
  cityInput.addEventListener('input', updateShip);
  cityInput.addEventListener('change', updateShip);
  updateShip();
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/frontend.php';