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
<section class="section">
<div class="container">
  <h1 style="padding-top:calc(var(--header-h) + 10px);margin-bottom:28px;font-size:2rem">Checkout</h1>
  <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

  <form method="POST" action="/checkout.php" id="checkout-form" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <div class="cart-layout">
    <div>
      <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:28px;margin-bottom:20px">
        <h3 style="margin-bottom:20px">Shipping Details</h3>
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" class="form-control" required value="<?php echo old('name', $prefill['name']); ?>"></div>
          <div class="form-group"><label>Phone *</label><input type="text" name="phone" class="form-control" required value="<?php echo old('phone', $prefill['phone']); ?>" placeholder="01XXXXXXXXX"></div>
        </div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required value="<?php echo old('email', $prefill['email']); ?>"></div>
        <div class="form-group"><label>Address *</label><textarea name="address" class="form-control" required rows="3"><?php echo old('address', $prefill['address']); ?></textarea></div>
        <div class="form-group"><label>City</label><input type="text" name="city" class="form-control" value="<?php echo old('city', $prefill['city']); ?>"></div>
        <div class="form-group"><label>Order Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Optional"><?php echo old('notes'); ?></textarea></div>
      </div>

      <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:28px">
        <h3 style="margin-bottom:16px">Payment Method</h3>
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px">
          <?php foreach ($methods as $k => $label): ?>
          <label class="pay-option" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid var(--color-border);border-radius:12px;cursor:pointer;<?php echo $sel === $k ? 'border-color:var(--color-primary)' : ''; ?>">
            <input type="radio" name="payment_method" value="<?php echo e($k); ?>" <?php echo $sel === $k ? 'checked' : ''; ?> onchange="showPayInfo('<?php echo e($k); ?>')">
            <span style="font-weight:600"><?php echo e($label); ?></span>
            <?php if ($k === 'cod'): ?>
            <span class="text-muted" style="font-size:.8rem;margin-left:auto">Pay when delivered</span>
            <?php else: ?>
            <span class="text-muted" style="font-size:.8rem;margin-left:auto">Pay now + proof</span>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>

        <div id="pay-info-box" style="display:none;padding:14px 16px;border-radius:12px;background:color-mix(in srgb,var(--color-primary) 10%,transparent);border:1px solid color-mix(in srgb,var(--color-primary) 25%,transparent);font-size:.9rem;margin-bottom:16px;line-height:1.55"></div>

        <div id="proof-wrap" style="display:none">
          <div class="alert alert-error" style="margin-bottom:14px;font-size:.875rem">
            <strong>Payment required before order.</strong> Send money first, then enter Transaction ID and upload screenshot.
          </div>
          <div class="form-group">
            <label>Transaction ID / TrxID *</label>
            <input type="text" name="transaction_id" id="transaction_id" class="form-control" placeholder="e.g. 8N7XXXXXX" value="<?php echo old('transaction_id'); ?>">
          </div>
          <div class="form-group">
            <label>Payment Screenshot *</label>
            <input type="file" name="payment_proof" id="payment_proof" class="form-control" accept="image/jpeg,image/png,image/webp,image/jpg">
            <small class="text-muted">JPG, PNG or WebP — max 3 MB</small>
          </div>
          <div id="proof-preview" style="display:none;margin-top:10px">
            <img id="proof-preview-img" src="" alt="Preview" style="max-width:220px;border-radius:10px;border:1px solid var(--color-border)">
          </div>
        </div>
      </div>
    </div>

    <div class="cart-summary" style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:24px;height:fit-content;position:sticky;top:90px">
      <h3 style="margin-bottom:16px">Order Summary</h3>
      <?php foreach ($items as $item): ?>
      <div class="summary-row" style="font-size:.875rem">
        <span><?php echo e($item['name']); ?> × <?php echo (int)$item['qty']; ?></span>
        <span><?php echo money($item['price'] * $item['qty']); ?></span>
      </div>
      <?php endforeach; ?>
      <div class="summary-row"><span>Subtotal</span><span><?php echo money(Cart::subtotal()); ?></span></div>
      <div class="summary-row"><span>Shipping</span><span><?php echo Cart::shipping() > 0 ? money(Cart::shipping()) : 'FREE'; ?></span></div>
      <div class="summary-row total"><span>Total</span><span><?php echo money(Cart::total()); ?></span></div>
      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:20px">
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
      x.style.borderColor = 'var(--color-border)';
    });
    el.style.borderColor = 'var(--color-primary)';
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
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/frontend.php';