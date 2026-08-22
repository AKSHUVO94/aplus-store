<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Settings';

$keys = [
    'site_name','site_tagline','site_email','site_phone','site_address',
    'brand_watermark_enabled','brand_watermark_text','brand_watermark_opacity','brand_watermark_position','brand_watermark_size',
    'mail_order_confirmation','smtp_enabled','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_secure',
    'shipping_cost','free_shipping_min',
    'pay_cod_enabled','pay_bkash_enabled','pay_nagad_enabled','pay_rocket_enabled','pay_bank_enabled','pay_card_enabled','pay_visa_enabled','pay_mastercard_enabled',
    'pay_bkash_number','pay_bkash_type','pay_nagad_number','pay_nagad_type',
    'pay_rocket_number','pay_rocket_type',
    'pay_bank_name','pay_bank_account_name','pay_bank_account_no','pay_bank_branch','pay_instructions',
    'pay_visa_account','pay_mastercard_account',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mail'])) {
    $testTo = trim(isset($_POST['test_mail_to']) ? $_POST['test_mail_to'] : '');
    if ($testTo === '') {
        $testTo = setting('site_email', '');
    }
    // save smtp fields first from POST so test uses latest typed values
    foreach ($keys as $k) {
        if (strpos($k, '_enabled') !== false || $k === 'mail_order_confirmation' || $k === 'smtp_enabled') {
            $val = isset($_POST[$k]) ? '1' : '0';
        } else {
            if (!isset($_POST[$k])) continue;
            $val = trim($_POST[$k]);
        }
        $ex = Database::fetch("SELECT id FROM settings WHERE `key`=?", [$k]);
        if ($ex) Database::update('settings', ['value' => $val], '`key`=?', [$k]);
        else Database::insert('settings', ['key' => $k, 'value' => $val, 'type' => 'string']);
    }
    // clear settings cache if any
    if (function_exists('setting')) {
        // static cache in setting() - call after reload
    }
    setting_clear_cache();
    $ok = Mailer::sendTest($testTo);
    if ($ok) {
        flash('success', 'Test email sent to ' . $testTo . '. Check Inbox and Spam.');
    } else {
        flash('error', 'Mail failed: ' . (Mailer::$lastError ?: 'Unknown error. Check SMTP settings.'));
    }
    redirect('/admin/settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_brand_logo'])) {
    $old = setting('brand_logo', '');
    if ($old) {
        $path = dirname(dirname(__DIR__)) . '/public/' . ltrim($old, '/');
        if (is_file($path)) @unlink($path);
    }
    $ex = Database::fetch("SELECT id FROM settings WHERE `key`='brand_logo'");
    if ($ex) Database::update('settings', array('value' => ''), '`key`=?', array('brand_logo'));
    flash('success', 'Brand logo removed.');
    redirect('/admin/settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['brand_logo']['name']) && $_FILES['brand_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['brand_logo'];
        if ($file['size'] <= 2 * 1024 * 1024) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $map = array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg');
            if (isset($map[$mime])) {
                $dir = dirname(dirname(__DIR__)) . '/public/uploads/brand';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $name = 'logo_' . time() . '.' . $map[$mime];
                if (move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
                    $logoPath = 'uploads/brand/' . $name;
                    $ex = Database::fetch("SELECT id FROM settings WHERE `key`='brand_logo'");
                    if ($ex) Database::update('settings', array('value'=>$logoPath), '`key`=?', array('brand_logo'));
                    else Database::insert('settings', array('key'=>'brand_logo','value'=>$logoPath,'type'=>'string'));
                }
            }
        }
    }

    foreach ($keys as $k) {
        if (strpos($k, '_enabled') !== false) {
            $val = isset($_POST[$k]) ? '1' : '0';
        } else {
            if (!isset($_POST[$k])) continue;
            $val = trim($_POST[$k]);
        }
        $ex = Database::fetch("SELECT id FROM settings WHERE `key`=?", [$k]);
        if ($ex) {
            Database::update('settings', ['value' => $val], '`key`=?', [$k]);
        } else {
            Database::insert('settings', ['key' => $k, 'value' => $val, 'type' => 'string']);
        }
    }
    flash('success', 'Settings saved successfully.');
    redirect('/admin/settings.php');
}

$s = [];
foreach (Database::fetchAll("SELECT `key`, value FROM settings") as $r) {
    $s[$r['key']] = $r['value'];
}
$g = function($k, $d='') use ($s) { return isset($s[$k]) ? $s[$k] : $d; };
ob_start();
?>
<form method="POST" enctype="multipart/form-data">
<div class="panel">
  <div class="panel-header"><h3>Store Information</h3></div>
  <div class="panel-body">
    <div class="form-row">
      <div class="form-group"><label>Store Name</label><input type="text" name="site_name" class="form-control" value="<?= e($g('site_name','AK')) ?>"></div>
      <div class="form-group"><label>Tagline</label><input type="text" name="site_tagline" class="form-control" value="<?= e($g('site_tagline')) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Email</label><input type="email" name="site_email" class="form-control" value="<?= e($g('site_email')) ?>"></div>
      <div class="form-group"><label>Phone</label><input type="text" name="site_phone" class="form-control" value="<?= e($g('site_phone')) ?>"></div>
    </div>
    <div class="form-group"><label>Address</label><input type="text" name="site_address" class="form-control" value="<?= e($g('site_address')) ?>"></div>
    <div class="form-row">
      <div class="form-group"><label>Shipping Cost (৳)</label><input type="number" name="shipping_cost" class="form-control" value="<?= e($g('shipping_cost','120')) ?>"></div>
      <div class="form-group"><label>Free Shipping Min (৳)</label><input type="number" name="free_shipping_min" class="form-control" value="<?= e($g('free_shipping_min','3000')) ?>"></div>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-header"><h3><i class="fas fa-wallet"></i> Payment Gateways</h3></div>
  <div class="panel-body">
    <p class="text-muted" style="margin-bottom:20px;font-size:.9rem">Enable methods customers can use at checkout. Add your real numbers/account details.</p>

    <div style="border:1px solid var(--color-border);border-radius:12px;padding:16px;margin-bottom:16px">
      <label style="display:flex;align-items:center;gap:10px;font-weight:600;margin-bottom:8px">
        <input type="checkbox" name="pay_cod_enabled" value="1" <?= $g('pay_cod_enabled','1')==='1'?'checked':'' ?>>
        Cash on Delivery (COD)
      </label>
      <p class="text-muted" style="font-size:.85rem;margin-left:28px">Customer pays when product is delivered.</p>
    </div>

    <div style="border:1px solid var(--color-border);border-radius:12px;padding:16px;margin-bottom:16px">
      <label style="display:flex;align-items:center;gap:10px;font-weight:600;margin-bottom:12px">
        <input type="checkbox" name="pay_bkash_enabled" value="1" <?= $g('pay_bkash_enabled','0')==='1'?'checked':'' ?>>
        bKash
      </label>
      <div class="form-row" style="margin-left:8px">
        <div class="form-group"><label>bKash Number</label><input type="text" name="pay_bkash_number" class="form-control" value="<?= e($g('pay_bkash_number')) ?>" placeholder="01XXXXXXXXX"></div>
        <div class="form-group"><label>Account Type</label>
          <select name="pay_bkash_type" class="form-control">
            <option value="Personal" <?= $g('pay_bkash_type')==='Personal'?'selected':'' ?>>Personal</option>
            <option value="Agent" <?= $g('pay_bkash_type')==='Agent'?'selected':'' ?>>Agent</option>
            <option value="Merchant" <?= $g('pay_bkash_type')==='Merchant'?'selected':'' ?>>Merchant</option>
          </select>
        </div>
      </div>
    </div>

    <div style="border:1px solid var(--color-border);border-radius:12px;padding:16px;margin-bottom:16px">
      <label style="display:flex;align-items:center;gap:10px;font-weight:600;margin-bottom:12px">
        <input type="checkbox" name="pay_nagad_enabled" value="1" <?= $g('pay_nagad_enabled','0')==='1'?'checked':'' ?>>
        Nagad
      </label>
      <div class="form-row" style="margin-left:8px">
        <div class="form-group"><label>Nagad Number</label><input type="text" name="pay_nagad_number" class="form-control" value="<?= e($g('pay_nagad_number')) ?>" placeholder="01XXXXXXXXX"></div>
        <div class="form-group"><label>Account Type</label>
          <select name="pay_nagad_type" class="form-control">
            <option value="Personal" <?= $g('pay_nagad_type')==='Personal'?'selected':'' ?>>Personal</option>
            <option value="Agent" <?= $g('pay_nagad_type')==='Agent'?'selected':'' ?>>Agent</option>
            <option value="Merchant" <?= $g('pay_nagad_type')==='Merchant'?'selected':'' ?>>Merchant</option>
          </select>
        </div>
      </div>
    </div>

    <div style="border:1px solid var(--color-border);border-radius:12px;padding:16px;margin-bottom:16px">
      <label style="display:flex;align-items:center;gap:10px;font-weight:600;margin-bottom:12px">
        <input type="checkbox" name="pay_bank_enabled" value="1" <?= $g('pay_bank_enabled','0')==='1'?'checked':'' ?>>
        Bank Account Transfer
      </label>
      <div class="form-row" style="margin-left:8px">
        <div class="form-group"><label>Bank Name</label><input type="text" name="pay_bank_name" class="form-control" value="<?= e($g('pay_bank_name')) ?>"></div>
        <div class="form-group"><label>Account Name</label><input type="text" name="pay_bank_account_name" class="form-control" value="<?= e($g('pay_bank_account_name')) ?>"></div>
      </div>
      <div class="form-row" style="margin-left:8px">
        <div class="form-group"><label>Account Number</label><input type="text" name="pay_bank_account_no" class="form-control" value="<?= e($g('pay_bank_account_no')) ?>"></div>
        <div class="form-group"><label>Branch</label><input type="text" name="pay_bank_branch" class="form-control" value="<?= e($g('pay_bank_branch')) ?>"></div>
      </div>
    </div>

    <div style="border:1px solid var(--color-border);border-radius:12px;padding:16px;margin-bottom:16px">
      <label style="display:flex;align-items:center;gap:10px;font-weight:600">
        <input type="checkbox" name="pay_card_enabled" value="1" <?= $g('pay_card_enabled','0')==='1'?'checked':'' ?>>
        Card Payment (coming soon / manual)
      </label>
    </div>

    <div class="form-group">
      <label>Payment Instructions (shown at checkout)</label>
      <textarea name="pay_instructions" class="form-control" rows="3"><?= e($g('pay_instructions')) ?></textarea>
    </div>
  </div>
</div>


      <div style="padding:16px;border:1px solid var(--color-border);border-radius:12px;margin-bottom:12px">
        <label style="display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:12px">
          <input type="checkbox" name="pay_visa_enabled" value="1" <?= $g('pay_visa_enabled','0')==='1'?'checked':'' ?>>
          Visa Card
        </label>
        <div class="form-group">
          <label>Visa Account / Card Number (shown to customer)</label>
          <input type="text" name="pay_visa_account" class="form-control" value="<?= e($g('pay_visa_account')) ?>" placeholder="e.g. account or merchant number">
        </div>
        <p class="text-muted" style="font-size:.8rem">Customer must enter Trx/Ref ID + upload screenshot.</p>
      </div>
      <div style="padding:16px;border:1px solid var(--color-border);border-radius:12px;margin-bottom:12px">
        <label style="display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:12px">
          <input type="checkbox" name="pay_mastercard_enabled" value="1" <?= $g('pay_mastercard_enabled','0')==='1'?'checked':'' ?>>
          Master Card
        </label>
        <div class="form-group">
          <label>Master Card Account / Card Number (shown to customer)</label>
          <input type="text" name="pay_mastercard_account" class="form-control" value="<?= e($g('pay_mastercard_account')) ?>" placeholder="e.g. account or merchant number">
        </div>
        <p class="text-muted" style="font-size:.8rem">Customer must enter Trx/Ref ID + upload screenshot.</p>
      </div>

<div class="panel" style="margin-bottom:20px">
  <div class="panel-header"><h3>Email</h3></div>
  <div class="panel-body">
    <label style="display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:16px">
      <input type="checkbox" name="mail_order_confirmation" value="1" <?= $g('mail_order_confirmation','1')==='1'?'checked':'' ?>>
      Send order confirmation email to customer
    </label>
    <label style="display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:14px">
      <input type="checkbox" name="smtp_enabled" value="1" <?= $g('smtp_enabled','0')==='1'?'checked':'' ?>>
      Use SMTP (required for Gmail on Laragon)
    </label>
    <div class="form-row">
      <div class="form-group"><label>SMTP Host</label><input type="text" name="smtp_host" class="form-control" value="<?= e($g('smtp_host','smtp.gmail.com')) ?>" placeholder="smtp.gmail.com"></div>
      <div class="form-group"><label>Port</label><input type="text" name="smtp_port" class="form-control" value="<?= e($g('smtp_port','587')) ?>" placeholder="587"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>SMTP Username (Gmail address)</label><input type="text" name="smtp_user" class="form-control" value="<?= e($g('smtp_user')) ?>" placeholder="you@gmail.com"></div>
      <div class="form-group"><label>SMTP Password (App Password)</label><input type="password" name="smtp_pass" class="form-control" value="<?= e($g('smtp_pass')) ?>" placeholder="Gmail App Password"></div>
    </div>
    <div class="form-group">
      <label>Encryption</label>
      <select name="smtp_secure" class="form-control">
        <option value="tls" <?= $g('smtp_secure','tls')==='tls'?'selected':'' ?>>TLS (port 587)</option>
        <option value="ssl" <?= $g('smtp_secure')==='ssl'?'selected':'' ?>>SSL (port 465)</option>
        <option value="none" <?= $g('smtp_secure')==='none'?'selected':'' ?>>None</option>
      </select>
    </div>
    <p class="text-muted" style="font-size:.8rem;margin-top:8px">
      <strong>Gmail:</strong> Enable 2-Step Verification → create an <em>App Password</em> → paste it in SMTP Password (not your normal Gmail password).
      Site Email should match your Gmail address.
    </p>
  </div>
</div>
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-bottom:20px;padding:16px;border:1px dashed var(--color-border);border-radius:12px">
  <div class="form-group" style="margin:0;flex:1;min-width:200px">
    <label>Test email to</label>
    <input type="email" name="test_mail_to" class="form-control" value="<?= e($g('site_email')) ?>" placeholder="you@gmail.com">
  </div>
  <button type="submit" name="test_mail" value="1" class="btn btn-outline"><i class="fas fa-paper-plane"></i> Send test email</button>
</div>


<div class="panel" style="margin-bottom:20px">
  <div class="panel-header"><h3>Brand watermark</h3></div>
  <div class="panel-body">
    <label style="display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:14px">
      <input type="checkbox" name="brand_watermark_enabled" value="1" <?= $g('brand_watermark_enabled','1')==='1'?'checked':'' ?>>
      Show brand watermark on product images
    </label>
    <div class="form-group">
      <label>Brand logo (AK)</label>
      <input type="file" name="brand_logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml">
      <small class="text-muted">Transparent PNG recommended. Max 2 MB. Upload a new file to replace.</small>
      <?php if ($g('brand_logo')): ?>
      <div style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <img src="/<?= e(ltrim($g('brand_logo'),'/')) ?>" alt="" style="max-height:56px;background:#111;padding:8px;border-radius:8px">
        <button type="submit" name="delete_brand_logo" value="1" class="btn btn-sm" style="color:#f87171"
          onclick="return confirm('Remove brand logo?');">
          <i class="fas fa-trash"></i> Delete logo
        </button>
      </div>
      <?php endif; ?>
    </div>
    <div class="form-group">
      <label>Watermark text (optional)</label>
      <input type="text" name="brand_watermark_text" class="form-control" value="<?= e($g('brand_watermark_text')) ?>" placeholder="AK — leave empty for logo only">
      <small class="text-muted">Clear the field and save to remove text.</small>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Position</label>
        <select name="brand_watermark_position" class="form-control">
          <?php
          $pos = $g('brand_watermark_position','center');
          $positions = array('center'=>'Center','bottom-right'=>'Bottom right','bottom-left'=>'Bottom left','top-right'=>'Top right','top-left'=>'Top left');
          foreach ($positions as $val => $lab):
          ?>
          <option value="<?= $val ?>" <?= $pos===$val?'selected':'' ?>><?= $lab ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Opacity (5–90%)</label>
        <input type="number" name="brand_watermark_opacity" class="form-control" min="5" max="90" value="<?= e($g('brand_watermark_opacity','28')) ?>">
      </div>
      <div class="form-group">
        <label>Logo size (px)</label>
        <input type="number" name="brand_watermark_size" class="form-control" min="24" max="160" value="<?= e($g('brand_watermark_size','72')) ?>">
      </div>
    </div>
  </div>
</div>

<button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save All Settings</button>
</form>
<?php $content=ob_get_clean(); require dirname(__DIR__,2).'/app/views/layouts/admin.php'; ?>