<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Reports';

$from = isset($_GET['from']) ? trim($_GET['from']) : date('Y-m-01');
$to = isset($_GET['to']) ? trim($_GET['to']) : date('Y-m-d');
$report = isset($_GET['report']) ? $_GET['report'] : 'overview';
$payMethod = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';
$allowedPayFilter = array('cod','bkash','nagad','rocket','bank','visa','mastercard','card');
if ($payMethod !== '' && !in_array($payMethod, $allowedPayFilter, true)) {
    $payMethod = '';
}
$allowedReports = array('overview', 'stock', 'sales', 'orders_status', 'payments', 'top_products', 'customers');
if (!in_array($report, $allowedReports, true)) {
    $report = 'overview';
}

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}
if ($from > $to) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$fromDt = $from . ' 00:00:00';
$toDt = $to . ' 23:59:59';

function report_money($n)
{
    return money($n);
}
function report_export_url($type, $format, $from, $to, $payMethod = '')
{
    $u = '/admin/report-export.php?type=' . urlencode($type) . '&format=' . urlencode($format)
        . '&from=' . urlencode($from) . '&to=' . urlencode($to);
    if ($payMethod !== '') {
        $u .= '&payment_method=' . urlencode($payMethod);
    }
    return $u;
}

// ---- Data queries ----
$stockRows = Database::fetchAll(
    "SELECT p.id, p.name, p.sku, p.stock, p.status, p.price, p.sale_price, c.name AS cat_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.stock ASC, p.name ASC"
);

$lowStock = 0;
$outStock = 0;
$totalUnits = 0;
foreach ($stockRows as $r) {
    $s = (int)$r['stock'];
    $totalUnits += $s;
    if ($s <= 0) {
        $outStock++;
    } elseif ($s <= 5) {
        $lowStock++;
    }
}

$salesDaily = Database::fetchAll(
    "SELECT DATE(created_at) AS d,
            COUNT(*) AS orders_count,
            COALESCE(SUM(total),0) AS revenue,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END),0) AS cancelled,
            COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END),0) AS delivered
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY d ASC",
    array($fromDt, $toDt)
);

$ordersByStatus = Database::fetchAll(
    "SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS amount
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY status
     ORDER BY cnt DESC",
    array($fromDt, $toDt)
);

$paySumSql = "SELECT payment_method, COUNT(*) AS cnt,
            COALESCE(SUM(total),0) AS amount,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END),0) AS paid_amount,
            COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END),0) AS pending_cnt
     FROM orders WHERE created_at BETWEEN ? AND ?";
$paySumParams = array($fromDt, $toDt);
if ($payMethod !== '') {
    $paySumSql .= " AND payment_method = ?";
    $paySumParams[] = $payMethod;
}
$paySumSql .= " GROUP BY payment_method ORDER BY amount DESC";
$paymentsByMethod = Database::fetchAll($paySumSql, $paySumParams);

$payDailySql = "SELECT DATE(created_at) AS d, payment_method, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS amount
     FROM orders WHERE created_at BETWEEN ? AND ?";
$payDailyParams = array($fromDt, $toDt);
if ($payMethod !== '') {
    $payDailySql .= " AND payment_method = ?";
    $payDailyParams[] = $payMethod;
}
$payDailySql .= " GROUP BY DATE(created_at), payment_method ORDER BY d ASC, payment_method ASC";
$paymentsDaily = Database::fetchAll($payDailySql, $payDailyParams);

$topProducts = Database::fetchAll(
    "SELECT oi.product_name, oi.product_id,
            SUM(oi.quantity) AS qty_sold,
            COALESCE(SUM(oi.total),0) AS revenue
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE o.created_at BETWEEN ? AND ?
       AND o.status != 'cancelled'
     GROUP BY oi.product_id, oi.product_name
     ORDER BY qty_sold DESC
     LIMIT 20",
    array($fromDt, $toDt)
);

$summary = Database::fetch(
    "SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total),0) AS total_revenue,
        COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END),0) AS cancelled_orders,
        COALESCE(SUM(CASE WHEN status = 'cancelled' THEN total ELSE 0 END),0) AS cancelled_amount,
        COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END),0) AS delivered_orders,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END),0) AS paid_revenue,
        COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END),0) AS pending_payments
     FROM orders
     WHERE created_at BETWEEN ? AND ?",
    array($fromDt, $toDt)
);

$payLabels = array(
    'cod' => 'Cash on Delivery',
    'bkash' => 'bKash',
    'nagad' => 'Nagad',
    'rocket' => 'Rocket',
    'bank' => 'Bank Transfer',
    'visa' => 'Visa',
    'mastercard' => 'Master Card',
    'card' => 'Card',
);

ob_start();
?>
<style>
.report-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.report-tabs a{
  padding:8px 14px;border-radius:999px;font-size:.85rem;font-weight:600;
  border:1px solid var(--color-border);color:var(--color-text);text-decoration:none;
  background:var(--color-surface);
}
.report-tabs a.active{background:var(--color-primary);color:#fff;border-color:var(--color-primary)}
.report-filters{
  display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin-bottom:20px;
  padding:16px;border:1px solid var(--color-border);border-radius:14px;background:var(--color-surface);
}
.report-filters .form-group{margin:0}
.report-filters label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:4px}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.stat-card{padding:16px;border-radius:14px;border:1px solid var(--color-border);background:var(--color-surface)}
.stat-card .label{font-size:.75rem;color:var(--color-text-muted);font-weight:600;text-transform:uppercase}
.stat-card .value{font-size:1.35rem;font-weight:800;margin-top:4px}
.stat-card.warn .value{color:#f59e0b}
.stat-card.danger .value{color:#ef4444}
.stat-card.ok .value{color:#22c55e}
.report-table{width:100%;border-collapse:collapse;font-size:.9rem}
.report-table th,.report-table td{padding:10px 12px;border-bottom:1px solid var(--color-border);text-align:left}
.report-table th{font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-muted)}
.report-table tr:hover td{background:color-mix(in srgb,var(--color-primary) 5%,transparent)}
</style>

<?php
  $exportTypeMap = array(
    'overview' => 'overview',
    'stock' => 'stock',
    'sales' => 'sales',
    'orders_status' => 'orders_status',
    'payments' => 'payments',
    'top_products' => 'top_products',
  );
  $exportType = isset($exportTypeMap[$report]) ? $exportTypeMap[$report] : 'overview';
?>
<form method="GET" class="report-filters">
  <input type="hidden" name="report" value="<?= e($report) ?>">
  <div class="form-group">
    <label>From date</label>
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
  </div>
  <div class="form-group">
    <label>To date</label>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
  </div>
  <div class="form-group">
    <label>Payment method</label>
    <select name="payment_method" class="form-control">
      <option value="">All methods</option>
      <?php foreach (ReportExport::payLabels() as $pk => $pl): ?>
      <option value="<?= e($pk) ?>" <?= $payMethod===$pk?'selected':'' ?>><?= e($pl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
  <a href="?report=<?= e($report) ?>&from=<?= e(date('Y-m-01')) ?>&to=<?= e(date('Y-m-d')) ?>" class="btn btn-outline">This month</a>
  <a href="?report=<?= e($report) ?>&from=<?= e(date('Y-m-d', strtotime('-7 days'))) ?>&to=<?= e(date('Y-m-d')) ?>" class="btn btn-outline">Last 7 days</a>
  <a href="?report=<?= e($report) ?>&from=<?= e(date('Y-m-d')) ?>&to=<?= e(date('Y-m-d')) ?>" class="btn btn-outline">Today</a>
</form>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;align-items:center">
  <span class="text-muted" style="font-size:.8rem;font-weight:700;text-transform:uppercase">Download:</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url($exportType, 'csv', $from, $to, $payMethod)) ?>"><i class="fas fa-file-csv"></i> CSV</a>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url($exportType, 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Excel</a>
  <a class="btn btn-sm btn-outline" target="_blank" href="<?= e(report_export_url($exportType, 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> PDF</a>
  <span style="opacity:.3">|</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('products', 'excel', $from, $to)) ?>"><i class="fas fa-shirt"></i> Products Excel</a>
  <a class="btn btn-sm btn-outline" target="_blank" href="<?= e(report_export_url('products', 'pdf', $from, $to)) ?>">Products PDF</a>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('orders', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-shopping-bag"></i> Orders Excel</a>
  <a class="btn btn-sm btn-outline" target="_blank" href="<?= e(report_export_url('orders', 'pdf', $from, $to, $payMethod)) ?>">Orders PDF</a>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('order_items', 'excel', $from, $to, $payMethod)) ?>">Order items Excel</a>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('payments_daily', 'excel', $from, $to, $payMethod)) ?>">Payments daily Excel</a>
</div>

<div class="report-tabs">
  <?php
  $tabs = array(
    'overview' => 'Overview',
    'stock' => 'Stock',
    'sales' => 'Sales (date-wise)',
    'orders_status' => 'Orders status',
    'payments' => 'Payments',
    'top_products' => 'Top products',
  );
  foreach ($tabs as $k => $lab):
    $url = '?report=' . urlencode($k) . '&from=' . urlencode($from) . '&to=' . urlencode($to) . ($payMethod !== '' ? '&payment_method=' . urlencode($payMethod) : '');
  ?>
  <a href="<?= e($url) ?>" class="<?= $report === $k ? 'active' : '' ?>"><?= e($lab) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($report === 'overview'): ?>
<div class="stat-grid">
  <div class="stat-card"><div class="label">Orders</div><div class="value"><?= (int)$summary['total_orders'] ?></div></div>
  <div class="stat-card ok"><div class="label">Revenue</div><div class="value"><?= report_money($summary['total_revenue']) ?></div></div>
  <div class="stat-card ok"><div class="label">Paid revenue</div><div class="value"><?= report_money($summary['paid_revenue']) ?></div></div>
  <div class="stat-card"><div class="label">Delivered</div><div class="value"><?= (int)$summary['delivered_orders'] ?></div></div>
  <div class="stat-card danger"><div class="label">Cancelled</div><div class="value"><?= (int)$summary['cancelled_orders'] ?></div></div>
  <div class="stat-card warn"><div class="label">Pending payments</div><div class="value"><?= (int)$summary['pending_payments'] ?></div></div>
  <div class="stat-card danger"><div class="label">Out of stock</div><div class="value"><?= (int)$outStock ?></div></div>
  <div class="stat-card warn"><div class="label">Low stock (≤5)</div><div class="value"><?= (int)$lowStock ?></div></div>
</div>
<div class="panel">
  <div class="panel-header"><h3>Period <?= e($from) ?> → <?= e($to) ?></h3></div>
  <div class="panel-body">
    <p class="text-muted" style="margin:0">Cancelled amount in range: <strong><?= report_money($summary['cancelled_amount']) ?></strong></p>
    <p class="text-muted" style="margin:8px 0 0">Total units in inventory: <strong><?= (int)$totalUnits ?></strong></p>
  </div>
</div>

<?php elseif ($report === 'stock'): ?>
<div class="stat-grid">
  <div class="stat-card"><div class="label">Products</div><div class="value"><?= count($stockRows) ?></div></div>
  <div class="stat-card"><div class="label">Total units</div><div class="value"><?= (int)$totalUnits ?></div></div>
  <div class="stat-card danger"><div class="label">Out of stock</div><div class="value"><?= (int)$outStock ?></div></div>
  <div class="stat-card warn"><div class="label">Low stock</div><div class="value"><?= (int)$lowStock ?></div></div>
</div>
<div class="panel">
  <div class="panel-header"><h3>Stock by product</h3></div>
  <div class="panel-body" style="padding:0;overflow-x:auto">
    <table class="report-table">
      <thead>
        <tr><th>Product</th><th>SKU</th><th>Category</th><th>Stock</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($stockRows as $r):
        $s = (int)$r['stock'];
      ?>
        <tr>
          <td><strong><?= e($r['name']) ?></strong></td>
          <td><code><?= e($r['sku'] ?: '—') ?></code></td>
          <td><?= e($r['cat_name'] ?: '—') ?></td>
          <td>
            <?php if ($s <= 0): ?>
              <span style="color:#ef4444;font-weight:800">0 — Out</span>
            <?php elseif ($s <= 5): ?>
              <span style="color:#f59e0b;font-weight:800"><?= $s ?> left</span>
            <?php else: ?>
              <?= $s ?>
            <?php endif; ?>
          </td>
          <td><?= e(ucfirst($r['status'])) ?></td>
          <td><a href="/admin/product-edit.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($report === 'sales'): ?>
<div class="panel">
  <div class="panel-header"><h3>Date-wise sales (<?= e($from) ?> → <?= e($to) ?>)</h3></div>
  <div class="panel-body" style="padding:0;overflow-x:auto">
    <table class="report-table">
      <thead>
        <tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Delivered</th><th>Cancelled</th></tr>
      </thead>
      <tbody>
      <?php if (empty($salesDaily)): ?>
        <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--color-text-muted)">No orders in this range</td></tr>
      <?php endif; ?>
      <?php foreach ($salesDaily as $r): ?>
        <tr>
          <td><?= e($r['d']) ?></td>
          <td><?= (int)$r['orders_count'] ?></td>
          <td><strong><?= report_money($r['revenue']) ?></strong></td>
          <td><?= (int)$r['delivered'] ?></td>
          <td style="color:<?= (int)$r['cancelled'] > 0 ? '#ef4444' : 'inherit' ?>"><?= (int)$r['cancelled'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($report === 'orders_status'): ?>
<div class="panel">
  <div class="panel-header"><h3>Orders by status</h3></div>
  <div class="panel-body" style="padding:0;overflow-x:auto">
    <table class="report-table">
      <thead><tr><th>Status</th><th>Count</th><th>Amount</th></tr></thead>
      <tbody>
      <?php foreach ($ordersByStatus as $r): ?>
        <tr>
          <td>
            <span class="badge badge-<?= $r['status']==='cancelled'?'danger':($r['status']==='delivered'?'success':'info') ?>">
              <?= e(ucfirst($r['status'])) ?>
            </span>
          </td>
          <td><?= (int)$r['cnt'] ?></td>
          <td><?= report_money($r['amount']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($ordersByStatus)): ?>
        <tr><td colspan="3" style="text-align:center;padding:24px">No data</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="text-muted" style="font-size:.85rem">Includes cancelled / delivered / pending / shipped etc. for the selected dates. (Returns: use Cancelled + notes; add a dedicated return status later if needed.)</p>

<?php elseif ($report === 'payments'): ?>
<div class="panel" style="margin-bottom:18px">
  <div class="panel-header"><h3>Payment methods summary</h3></div>
  <div class="panel-body" style="padding:0;overflow-x:auto">
    <table class="report-table">
      <thead>
        <tr><th>Method</th><th>Orders</th><th>Total amount</th><th>Paid amount</th><th>Pending orders</th></tr>
      </thead>
      <tbody>
      <?php foreach ($paymentsByMethod as $r):
        $m = strtolower($r['payment_method']);
        $lab = isset($payLabels[$m]) ? $payLabels[$m] : strtoupper($r['payment_method']);
      ?>
        <tr>
          <td><strong><?= e($lab) ?></strong></td>
          <td><?= (int)$r['cnt'] ?></td>
          <td><?= report_money($r['amount']) ?></td>
          <td style="color:#22c55e;font-weight:700"><?= report_money($r['paid_amount']) ?></td>
          <td style="color:#f59e0b;font-weight:700"><?= (int)$r['pending_cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($paymentsByMethod)): ?>
        <tr><td colspan="5" style="text-align:center;padding:24px">No payments in range</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="panel">
  <div class="panel-header"><h3>Date-wise payments by method</h3></div>
  <div class="panel-body" style="padding:0;overflow-x:auto">
    <table class="report-table">
      <thead><tr><th>Date</th><th>Method</th><th>Orders</th><th>Amount</th></tr></thead>
      <tbody>
      <?php foreach ($paymentsDaily as $r):
        $m = strtolower($r['payment_method']);
        $lab = isset($payLabels[$m]) ? $payLabels[$m] : strtoupper($r['payment_method']);
      ?>
        <tr>
          <td><?= e($r['d']) ?></td>
          <td><?= e($lab) ?></td>
          <td><?= (int)$r['cnt'] ?></td>
          <td><?= report_money($r['amount']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($paymentsDaily)): ?>
        <tr><td colspan="4" style="text-align:center;padding:24px">No data</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($report === 'top_products'): ?>
<div class="panel">
  <div class="panel-header"><h3>Best sellers (excl. cancelled)</h3></div>
  <div class="panel-body" style="padding:0;overflow-x:auto">
    <table class="report-table">
      <thead><tr><th>#</th><th>Product</th><th>Qty sold</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php $i = 1; foreach ($topProducts as $r): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td>
            <strong><?= e($r['product_name']) ?></strong>
            <?php if (!empty($r['product_id'])): ?>
            <a href="/admin/product-edit.php?id=<?= (int)$r['product_id'] ?>" class="btn btn-sm btn-outline" style="margin-left:8px">Edit</a>
            <?php endif; ?>
          </td>
          <td><?= (int)$r['qty_sold'] ?></td>
          <td><?= report_money($r['revenue']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($topProducts)): ?>
        <tr><td colspan="4" style="text-align:center;padding:24px">No sales in range</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/app/views/layouts/admin.php';
