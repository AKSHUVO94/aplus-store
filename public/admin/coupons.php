<?php
require_once dirname(dirname(__DIR__)) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Coupons';

Coupon::ensureTable();

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        Database::query("DELETE FROM coupons WHERE id=?", array($id));
        flash('success', 'Coupon deleted.');
    }
    redirect('/admin/coupons.php');
}

// Toggle status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $row = Database::fetch("SELECT * FROM coupons WHERE id=?", array($id));
    if ($row) {
        $ns = $row['status'] === 'active' ? 'inactive' : 'active';
        Database::update('coupons', array('status' => $ns), 'id=?', array($id));
        flash('success', 'Coupon ' . $ns . '.');
    }
    redirect('/admin/coupons.php');
}

// Create / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
    $code = Coupon::normalizeCode(isset($_POST['code']) ? $_POST['code'] : '');
    $type = isset($_POST['type']) && $_POST['type'] === 'percent' ? 'percent' : 'fixed';
    $amount = (float)(isset($_POST['amount']) ? $_POST['amount'] : 0);
    $minOrder = (float)(isset($_POST['min_order']) ? $_POST['min_order'] : 0);
    $maxUses = trim(isset($_POST['max_uses']) ? $_POST['max_uses'] : '');
    $expires = trim(isset($_POST['expires_at']) ? $_POST['expires_at'] : '');
    $status = isset($_POST['status']) && $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if ($code === '' || $amount <= 0) {
        flash('error', 'Code and amount are required.');
        redirect('/admin/coupons.php');
    }

    // Duplicate code check
    $existing = Database::fetch("SELECT id FROM coupons WHERE code = ? LIMIT 1", array($code));
    if ($existing && (int)$existing['id'] !== $id) {
        flash('error', 'Code "' . $code . '" already exists. Use a different code or Edit the existing one.');
        redirect('/admin/coupons.php');
    }

    $data = array(
        'code' => $code,
        'type' => $type,
        'value' => $amount,
        'min_order' => $minOrder,
        'status' => $status,
    );
    // Only set optional fields when provided (avoid NULL bind issues)
    if ($maxUses !== '') {
        $data['usage_limit'] = (int)$maxUses;
    }
    if ($expires !== '') {
        $ts = strtotime($expires);
        if ($ts) $data['expires_at'] = date('Y-m-d', $ts);
    }

    try {
        if ($id > 0) {
            // Clear optional fields if left blank on edit
            if ($maxUses === '') {
                try { Database::query("UPDATE coupons SET usage_limit = NULL WHERE id = ?", array($id)); } catch (Exception $e) {}
            }
            if ($expires === '') {
                try { Database::query("UPDATE coupons SET expires_at = NULL WHERE id = ?", array($id)); } catch (Exception $e) {}
            }
            Database::update('coupons', $data, 'id=?', array($id));
            flash('success', 'Coupon updated: ' . $code);
        } else {
            if (!isset($data['usage_limit'])) {
                // leave default NULL — insert without column
            }
            Database::insert('coupons', $data);
            // If usage_limit blank, ensure NULL
            if ($maxUses === '') {
                try {
                    $row = Database::fetch("SELECT id FROM coupons WHERE code = ?", array($code));
                    if ($row) Database::query("UPDATE coupons SET usage_limit = NULL WHERE id = ?", array((int)$row['id']));
                } catch (Exception $e) {}
            }
            flash('success', 'Coupon created: ' . $code);
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate') !== false) {
            flash('error', 'Code "' . $code . '" already exists. Choose another code.');
        } else {
            flash('error', 'Could not save: ' . $msg);
        }
    }
    redirect('/admin/coupons.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = Database::fetch("SELECT * FROM coupons WHERE id=?", array((int)$_GET['edit']));
}

$list = Database::fetchAll("SELECT * FROM coupons ORDER BY id DESC LIMIT 200");

ob_start();
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
  <div>
    <h1>Coupons</h1>
    <p class="text-muted" style="margin:0">Example: code <strong>AK50</strong> with amount <strong>50</strong> = ৳50 off</p>
  </div>
</div>

<div class="panel" style="margin-bottom:20px">
  <div class="panel-header"><h3><?= $edit ? 'Edit coupon' : 'Create coupon' ?></h3></div>
  <div class="panel-body">
    <form method="post" class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
      <div class="form-group" style="margin:0">
        <label>Code *</label>
        <input type="text" name="code" class="form-control" required placeholder="AK50"
               value="<?= e($edit ? $edit['code'] : '') ?>" style="text-transform:uppercase">
      </div>
      <div class="form-group" style="margin:0">
        <label>Type</label>
        <select name="type" class="form-control">
          <option value="fixed" <?= (!$edit || $edit['type']==='fixed')?'selected':'' ?>>Fixed ৳</option>
          <option value="percent" <?= ($edit && $edit['type']==='percent')?'selected':'' ?>>Percent %</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label>Amount *</label>
        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required
               value="<?= e($edit ? (isset($edit['value']) ? $edit['value'] : (isset($edit['amount'])?$edit['amount']:'50')) : '50') ?>" placeholder="50">
      </div>
      <div class="form-group" style="margin:0">
        <label>Min order ৳</label>
        <input type="number" step="0.01" min="0" name="min_order" class="form-control"
               value="<?= e($edit ? $edit['min_order'] : '0') ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label>Max uses (blank = unlimited)</label>
        <input type="number" min="1" name="max_uses" class="form-control"
               value="<?php
                 if ($edit) {
                   $ul = array_key_exists('usage_limit',$edit) ? $edit['usage_limit'] : (isset($edit['max_uses'])?$edit['max_uses']:null);
                   echo e($ul !== null && $ul !== '' ? $ul : '');
                 }
               ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label>Expires (optional)</label>
        <input type="date" name="expires_at" class="form-control"
               value="<?php
                 if ($edit && !empty($edit['expires_at'])) {
                   echo e(date('Y-m-d', strtotime($edit['expires_at'])));
                 }
               ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label>Status</label>
        <select name="status" class="form-control">
          <option value="active" <?= (!$edit || $edit['status']==='active')?'selected':'' ?>>Active</option>
          <option value="inactive" <?= ($edit && $edit['status']==='inactive')?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <button type="submit" class="btn btn-primary"><?= $edit ? 'Update' : 'Create coupon' ?></button>
        <?php if ($edit): ?>
          <a href="/admin/coupons.php" class="btn btn-outline">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-header"><h3>All coupons</h3></div>
  <div class="panel-body" style="overflow-x:auto">
    <table class="table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Discount</th>
          <th>Min order</th>
          <th>Uses</th>
          <th>Expires</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($list)): ?>
        <tr><td colspan="7" class="text-muted">No coupons yet. Create AK50 for ৳50 off.</td></tr>
      <?php else: foreach ($list as $c): ?>
        <tr>
          <td><strong><?= e($c['code']) ?></strong></td>
          <td><?php
            $val = isset($c['value']) ? $c['value'] : (isset($c['amount']) ? $c['amount'] : 0);
            echo $c['type']==='percent' ? e($val).'%' : '৳'.e(number_format((float)$val, 0));
          ?></td>
          <td><?= (float)(isset($c['min_order'])?$c['min_order']:0) > 0 ? '৳'.e(number_format((float)$c['min_order'],0)) : '—' ?></td>
          <td><?php
            $used = (int)(isset($c['used_count']) ? $c['used_count'] : 0);
            $lim = array_key_exists('usage_limit', $c) ? $c['usage_limit'] : (isset($c['max_uses']) ? $c['max_uses'] : null);
            echo $used;
            if ($lim !== null && $lim !== '') echo ' / '.(int)$lim;
          ?></td>
          <td><?= !empty($c['expires_at']) ? e($c['expires_at']) : '—' ?></td>
          <td>
            <a href="?toggle=<?= (int)$c['id'] ?>" class="badge" style="background:<?= $c['status']==='active'?'#10b981':'#94a3b8' ?>;color:#fff;padding:4px 8px;border-radius:6px;text-decoration:none">
              <?= e($c['status']) ?>
            </a>
          </td>
          <td style="white-space:nowrap">
            <a href="?edit=<?= (int)$c['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
            <a href="?delete=<?= (int)$c['id'] ?>" class="btn btn-outline btn-sm" style="color:#e11d48"
               onclick="return confirm('Delete this coupon?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$content = ob_get_clean();
require dirname(dirname(__DIR__)) . '/app/views/layouts/admin.php';