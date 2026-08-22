<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Orders';

// Delete order (with items + payment proof file)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $oid = (int)$_GET['delete'];
    $order = Database::fetch('SELECT * FROM orders WHERE id=?', array($oid));
    if ($order) {
        if (!empty($order['payment_proof'])) {
            $proofPath = dirname(dirname(__DIR__)) . '/public/' . ltrim($order['payment_proof'], '/');
            if (is_file($proofPath)) {
                @unlink($proofPath);
            }
        }
        Database::delete('order_items', 'order_id=?', array($oid));
        Database::delete('orders', 'id=?', array($oid));
        flash('success', 'Order ' . $order['order_number'] . ' deleted.');
    } else {
        flash('error', 'Order not found.');
    }
    redirect('/admin/orders.php');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $oid = (int)$_POST['order_id'];
    $st = $_POST['status'];
    $allowed = ['pending','confirmed','processing','shipped','delivered','cancelled'];
    if (in_array($st, $allowed, true)) {
        Database::update('orders', ['status'=>$st], 'id=?', [$oid]);
        if ($st === 'delivered') {
            Database::update('orders', ['payment_status'=>'paid'], "id=? AND payment_method='cod'", [$oid]);
        }
        flash('success', 'Order status updated.');
    }
    $redir = '/admin/orders.php';
    $qs = [];
    if (!empty($_GET['status'])) $qs[] = 'status=' . urlencode($_GET['status']);
    if (!empty($_GET['q'])) $qs[] = 'q=' . urlencode($_GET['q']);
    if ($qs) $redir .= '?' . implode('&', $qs);
    redirect($redir);
}

$filter = isset($_GET['status']) ? $_GET['status'] : '';
$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$where = [];
$params = [];

if ($filter && in_array($filter, ['pending','confirmed','processing','shipped','delivered','cancelled'], true)) {
    $where[] = 'status = ?';
    $params[] = $filter;
}
if ($q !== '') {
    $where[] = '(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)';
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$sql = 'SELECT * FROM orders';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';
$orders = Database::fetchAll($sql, $params);
ob_start();
?>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
  <a class="btn btn-sm btn-outline" href="/admin/report-export.php?type=orders&format=excel&from=<?= e(isset($_GET['from'])?$_GET['from']:date('Y-m-01')) ?>&to=<?= e(isset($_GET['to'])?$_GET['to']:date('Y-m-d')) ?>"><i class="fas fa-file-excel"></i> Orders Excel</a>
  <a class="btn btn-sm btn-outline" href="/admin/report-export.php?type=orders&format=csv&from=<?= e(date('Y-m-01')) ?>&to=<?= e(date('Y-m-d')) ?>">Orders CSV</a>
  <a class="btn btn-sm btn-outline" target="_blank" href="/admin/report-export.php?type=orders&format=pdf&from=<?= e(date('Y-m-01')) ?>&to=<?= e(date('Y-m-d')) ?>"><i class="fas fa-file-pdf"></i> Orders PDF</a>
  <a class="btn btn-sm btn-outline" href="/admin/report-export.php?type=order_items&format=excel&from=<?= e(date('Y-m-01')) ?>&to=<?= e(date('Y-m-d')) ?>">Items Excel</a>
</div>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;align-items:center">
  <a href="/admin/orders.php" class="btn btn-sm <?= $filter==='' && $q==='' ?'btn-primary':'btn-outline' ?>">All</a>
  <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
  <a href="?status=<?= $s ?><?= $q !== '' ? '&q='.urlencode($q) : '' ?>" class="btn btn-sm <?= $filter===$s?'btn-primary':'btn-outline' ?>"><?= ucfirst($s) ?></a>
  <?php endforeach; ?>
</div>

<form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center">
  <?php if ($filter): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
  <input type="search" name="q" class="form-control" style="max-width:300px" placeholder="Search order #, name, email, phone..." value="<?= e($q) ?>">
  <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
  <?php if ($q !== ''): ?>
  <a href="/admin/orders.php<?= $filter ? '?status='.urlencode($filter) : '' ?>" class="btn btn-outline btn-sm">Clear</a>
  <?php endif; ?>
  <span class="text-muted" style="margin-left:auto"><?= count($orders) ?> orders</span>
</form>

<div class="panel">
  <div class="panel-header"><h3>Orders</h3></div>
  <div class="panel-body" style="padding:0">
    <div style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr><th>Order #</th><th>Customer</th><th>Phone</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if (empty($orders)): ?>
      <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--color-text-muted)">No orders found<?= $q ? ' for “'.e($q).'”' : '' ?>.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><a href="/admin/order-view.php?id=<?= $o['id'] ?>" style="color:var(--color-primary);font-weight:700"><?= e($o['order_number']) ?></a></td>
        <td><?= e($o['customer_name']) ?><br><span class="text-muted" style="font-size:.8rem"><?= e($o['customer_email']) ?></span></td>
        <td><?= e($o['customer_phone']) ?></td>
        <td><?= money($o['total']) ?></td>
        <td>
          <?= e(strtoupper($o['payment_method'])) ?><br>
          <span class="badge badge-<?= $o['payment_status']==='paid'?'success':'warning' ?>"><?= e($o['payment_status']) ?></span>
        </td>
        <td><span class="badge badge-info"><?= e(ucfirst($o['status'])) ?></span></td>
        <td><?= formatDate($o['created_at']) ?></td>
        <td style="white-space:nowrap">
          <a href="/admin/order-view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> View</a>
          <a href="?delete=<?= (int)$o['id'] ?>" class="btn btn-sm" style="color:#f87171"
             onclick="return confirm('Delete order <?= e(addslashes($o['order_number'])) ?>?\n\nThis will permanently remove the order, items, and payment screenshot.\nThis cannot be undone.');">
            <i class="fas fa-trash"></i>
          </a>
          <form method="POST" style="display:inline-flex;gap:4px;margin-left:4px;vertical-align:middle">
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <select name="status" class="form-control" style="padding:4px;font-size:.75rem;width:100px">
              <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">OK</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php $content=ob_get_clean(); require dirname(__DIR__,2).'/app/views/layouts/admin.php'; ?>
