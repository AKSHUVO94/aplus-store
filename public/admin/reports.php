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
$allowedReports = array('overview', 'stock', 'sales', 'daily_detail', 'orders_status', 'payments', 'top_products', 'customers');
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

$topProducts = array();
try {
    $topProducts = Database::fetchAll(
        "SELECT oi.product_name, oi.product_id,
                MAX(oi.product_sku) AS product_sku,
                MAX(oi.product_image) AS product_image,
                SUM(oi.quantity) AS qty_sold,
                COALESCE(SUM(oi.total),0) AS revenue,
                COUNT(DISTINCT o.id) AS orders_count
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE o.created_at BETWEEN ? AND ?
           AND o.status != 'cancelled'
         GROUP BY oi.product_id, oi.product_name
         ORDER BY qty_sold DESC
         LIMIT 50",
        array($fromDt, $toDt)
    );
} catch (Exception $e) {
    // Fallback if product_image column missing
    try {
        $topProducts = Database::fetchAll(
            "SELECT oi.product_name, oi.product_id,
                    MAX(oi.product_sku) AS product_sku,
                    SUM(oi.quantity) AS qty_sold,
                    COALESCE(SUM(oi.total),0) AS revenue,
                    COUNT(DISTINCT o.id) AS orders_count
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.created_at BETWEEN ? AND ?
               AND o.status != 'cancelled'
             GROUP BY oi.product_id, oi.product_name
             ORDER BY qty_sold DESC
             LIMIT 50",
            array($fromDt, $toDt)
        );
    } catch (Exception $e2) {
        $topProducts = array();
    }
}

// Line items for top products detail (sizes/colors sold)
$topProductIds = array();
foreach ($topProducts as $tp) {
    if (!empty($tp['product_id'])) $topProductIds[] = (int)$tp['product_id'];
}
$topVariantMap = array();
if (!empty($topProductIds)) {
    try {
        $ph = implode(',', array_fill(0, count($topProductIds), '?'));
        $variantRows = Database::fetchAll(
            "SELECT oi.product_id, oi.size, oi.color, SUM(oi.quantity) AS qty, COALESCE(SUM(oi.total),0) AS revenue
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.created_at BETWEEN ? AND ? AND o.status != 'cancelled'
               AND oi.product_id IN ($ph)
             GROUP BY oi.product_id, oi.size, oi.color
             ORDER BY qty DESC",
            array_merge(array($fromDt, $toDt), $topProductIds)
        );
        foreach ($variantRows as $vr) {
            $pid = (int)$vr['product_id'];
            if (!isset($topVariantMap[$pid])) $topVariantMap[$pid] = array();
            $topVariantMap[$pid][] = $vr;
        }
    } catch (Exception $e) {
        $topVariantMap = array();
    }
}

// ---- Daily sales detail: orders + items for the range ----
$dailyOrdersSql = "SELECT * FROM orders WHERE created_at BETWEEN ? AND ?";
$dailyOrdersParams = array($fromDt, $toDt);
if ($payMethod !== '') {
    $dailyOrdersSql .= " AND payment_method = ?";
    $dailyOrdersParams[] = $payMethod;
}
$dailyOrdersSql .= " ORDER BY created_at DESC";
$dailyOrders = Database::fetchAll($dailyOrdersSql, $dailyOrdersParams);

$dailyOrdersByDate = array();
$dailyOrderIds = array();
foreach ($dailyOrders as $o) {
    $d = date('Y-m-d', strtotime($o['created_at']));
    if (!isset($dailyOrdersByDate[$d])) {
        $dailyOrdersByDate[$d] = array();
    }
    $dailyOrdersByDate[$d][] = $o;
    $dailyOrderIds[] = (int)$o['id'];
}

$dailyItemsByOrder = array();
if (!empty($dailyOrderIds)) {
    $placeholders = implode(',', array_fill(0, count($dailyOrderIds), '?'));
    $allItems = Database::fetchAll(
        "SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC",
        $dailyOrderIds
    );
    foreach ($allItems as $it) {
        $oid = (int)$it['order_id'];
        if (!isset($dailyItemsByOrder[$oid])) {
            $dailyItemsByOrder[$oid] = array();
        }
        $dailyItemsByOrder[$oid][] = $it;
    }
}

// Orders grouped by status / payment (must run AFTER $dailyOrders is loaded)
$ordersByStatusDetail = array();
$ordersByPayDetail = array();
foreach ($dailyOrders as $o) {
    $st = $o['status'];
    if (!isset($ordersByStatusDetail[$st])) $ordersByStatusDetail[$st] = array();
    $ordersByStatusDetail[$st][] = $o;

    $pm = strtolower($o['payment_method']);
    if (!isset($ordersByPayDetail[$pm])) $ordersByPayDetail[$pm] = array();
    $ordersByPayDetail[$pm][] = $o;
}

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
.report-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}
.report-tabs a{
  padding:9px 16px;border-radius:999px;font-size:.85rem;font-weight:600;
  border:1px solid var(--color-border);color:var(--color-text-muted);text-decoration:none;
  background:var(--color-surface);transition:all .15s;
}
.report-tabs a:hover{border-color:var(--color-primary);color:var(--color-primary)}
.report-tabs a.active{background:var(--color-primary);color:#fff;border-color:var(--color-primary);box-shadow:0 4px 14px color-mix(in srgb,var(--color-primary) 35%,transparent)}
.report-filters{
  display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin-bottom:18px;
  padding:16px 18px;border:1px solid var(--color-border);border-radius:16px;background:var(--color-surface);
}
.report-filters .form-group{margin:0}
.report-filters label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:5px;color:var(--color-text-muted)}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}
.stat-card{
  padding:16px 18px;border-radius:14px;border:1px solid var(--color-border);background:var(--color-surface);
  position:relative;overflow:hidden;
}
.stat-card::before{
  content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--color-border);
}
.stat-card.ok::before{background:#22c55e}
.stat-card.warn::before{background:#f59e0b}
.stat-card.danger::before{background:#ef4444}
.stat-card.info::before{background:var(--color-primary)}
.stat-card .label{font-size:.7rem;color:var(--color-text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.stat-card .value{font-size:1.4rem;font-weight:800;margin-top:6px;letter-spacing:-.02em}
.stat-card.warn .value{color:#f59e0b}
.stat-card.danger .value{color:#ef4444}
.stat-card.ok .value{color:#16a34a}
.stat-card.info .value{color:var(--color-primary)}
.report-table{width:100%;border-collapse:collapse;font-size:.88rem}
.report-table th,.report-table td{padding:11px 14px;border-bottom:1px solid var(--color-border);text-align:left;vertical-align:middle}
.report-table th{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);font-weight:700;background:color-mix(in srgb,var(--color-primary) 4%,var(--color-surface))}
.report-table tr:hover td{background:color-mix(in srgb,var(--color-primary) 5%,transparent)}
.report-table tr:last-child td{border-bottom:0}
.ov-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:16px;margin-bottom:20px}
@media(max-width:960px){.ov-grid{grid-template-columns:1fr}}
.ov-section-title{font-size:.95rem;font-weight:700;margin:0}
.ov-order-row{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border-bottom:1px solid var(--color-border)}
.ov-order-row:last-child{border-bottom:0}
.ov-order-row:hover{background:color-mix(in srgb,var(--color-primary) 4%,transparent)}
.ov-thumbs{display:flex;gap:4px;flex-shrink:0}
.ov-thumbs img,.ov-thumbs .ph{
  width:40px;height:50px;object-fit:cover;border-radius:7px;border:1px solid var(--color-border);
}
.ov-thumbs .ph{
  display:grid;place-items:center;background:linear-gradient(145deg,var(--color-primary),var(--color-secondary));
  opacity:.4;color:#fff;font-size:.7rem;
}
.ov-order-info{flex:1;min-width:0}
.ov-order-info .num{font-weight:800;font-size:.95rem;color:var(--color-text);text-decoration:none}
.ov-order-info .num:hover{color:var(--color-primary)}
.ov-order-meta{font-size:.8rem;color:var(--color-text-muted);margin-top:3px;line-height:1.45}
.ov-order-right{text-align:right;flex-shrink:0}
.ov-order-right .amt{font-weight:800;font-size:1rem}
.day-block{margin-bottom:24px;border:1px solid var(--color-border);border-radius:16px;overflow:hidden;background:var(--color-surface)}
.day-header{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;padding:14px 18px;background:color-mix(in srgb,var(--color-primary) 8%,var(--color-surface));border-bottom:1px solid var(--color-border)}
.day-header h3{margin:0;font-size:1.05rem}
.day-stats{display:flex;flex-wrap:wrap;gap:14px;font-size:.85rem}
.day-stats span{color:var(--color-text-muted)}
.day-stats strong{color:var(--color-text);font-weight:800}
.order-card{padding:16px 18px;border-bottom:1px solid var(--color-border)}
.order-card:last-child{border-bottom:0}
.order-card-top{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.order-meta{font-size:.85rem;color:var(--color-text-muted)}
.order-items-grid{display:grid;gap:10px}
.order-item-row{display:flex;gap:12px;align-items:center;padding:8px;border-radius:10px;background:color-mix(in srgb,var(--color-border) 30%,transparent)}
.order-item-row img,.order-item-placeholder{width:48px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--color-border);flex-shrink:0}
.order-item-placeholder{display:grid;place-items:center;background:linear-gradient(145deg,var(--color-primary),var(--color-secondary));opacity:.45;color:#fff;font-size:.85rem}
.proof-thumb{max-width:80px;max-height:80px;object-fit:contain;border-radius:8px;border:1px solid var(--color-border);background:#0a0a0a}
.badge-sm{display:inline-block;padding:3px 9px;border-radius:999px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.02em}
.badge-sm.pending{background:color-mix(in srgb,#f59e0b 18%,transparent);color:#b45309}
.badge-sm.confirmed,.badge-sm.processing,.badge-sm.shipped{background:color-mix(in srgb,#3b82f6 18%,transparent);color:#1d4ed8}
.badge-sm.delivered{background:color-mix(in srgb,#22c55e 18%,transparent);color:#15803d}
.badge-sm.cancelled{background:color-mix(in srgb,#ef4444 18%,transparent);color:#b91c1c}
.badge-sm.paid{background:color-mix(in srgb,#22c55e 18%,transparent);color:#15803d}
.badge-sm.pay-pending{background:color-mix(in srgb,#f59e0b 18%,transparent);color:#b45309}
.export-bar{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;align-items:center}
.export-bar .lbl{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-muted);margin-right:4px}
</style>

<?php
  $exportTypeMap = array(
    'overview' => 'overview',
    'stock' => 'stock',
    'sales' => 'sales',
    'daily_detail' => 'daily_detail',
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

<div class="export-bar">
  <span class="lbl">Download:</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url($exportType, 'csv', $from, $to, $payMethod)) ?>"><i class="fas fa-file-csv"></i> CSV</a>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url($exportType, 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Excel</a>
  <a class="btn btn-sm btn-outline" target="_blank" href="<?= e(report_export_url($exportType, 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> PDF</a>
  <span style="opacity:.25;margin:0 2px">|</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('products', 'excel', $from, $to)) ?>"><i class="fas fa-shirt"></i> Products</a>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('orders', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-shopping-bag"></i> Orders</a>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('order_items', 'excel', $from, $to, $payMethod)) ?>">Items</a>
  <span style="opacity:.25;margin:0 2px">|</span>
  <a class="btn btn-sm btn-primary" href="<?= e(report_export_url('daily_detail', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-calendar-day"></i> Full Detail Excel</a>
  <a class="btn btn-sm btn-primary" target="_blank" href="<?= e(report_export_url('daily_detail', 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> Full Detail PDF</a>
</div>

<div class="report-tabs">
  <?php
  $tabs = array(
    'overview' => 'Overview',
    'stock' => 'Stock',
    'sales' => 'Sales (date-wise)',
    'daily_detail' => 'Daily Sales Detail',
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
<?php
  // Overview uses same daily order data already loaded for the range
  $ovOrders = $dailyOrders;
  $ovItemsByOrder = $dailyItemsByOrder;
  $avgOrder = ((int)$summary['total_orders'] > 0)
    ? ((float)$summary['total_revenue'] / (int)$summary['total_orders'])
    : 0;
?>
<div class="stat-grid">
  <div class="stat-card info"><div class="label">Orders</div><div class="value"><?= (int)$summary['total_orders'] ?></div></div>
  <div class="stat-card ok"><div class="label">Revenue</div><div class="value"><?= report_money($summary['total_revenue']) ?></div></div>
  <div class="stat-card ok"><div class="label">Paid revenue</div><div class="value"><?= report_money($summary['paid_revenue']) ?></div></div>
  <div class="stat-card info"><div class="label">Avg. order</div><div class="value"><?= report_money($avgOrder) ?></div></div>
  <div class="stat-card ok"><div class="label">Delivered</div><div class="value"><?= (int)$summary['delivered_orders'] ?></div></div>
  <div class="stat-card danger"><div class="label">Cancelled</div><div class="value"><?= (int)$summary['cancelled_orders'] ?></div></div>
  <div class="stat-card warn"><div class="label">Pending payments</div><div class="value"><?= (int)$summary['pending_payments'] ?></div></div>
  <div class="stat-card danger"><div class="label">Out of stock</div><div class="value"><?= (int)$outStock ?></div></div>
</div>

<div class="export-bar">
  <span class="lbl">Export overview:</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('overview', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Summary Excel</a>
  <a class="btn btn-sm btn-outline" target="_blank" href="<?= e(report_export_url('overview', 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> Summary PDF</a>
  <a class="btn btn-sm btn-primary" href="<?= e(report_export_url('daily_detail', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Full Detail Excel</a>
  <a class="btn btn-sm btn-primary" target="_blank" href="<?= e(report_export_url('daily_detail', 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> Full Detail PDF</a>
</div>

<div class="ov-grid">
  <!-- Date-wise sales -->
  <div class="panel" style="margin:0">
    <div class="panel-header">
      <h3 class="ov-section-title"><i class="fas fa-chart-line" style="color:var(--color-primary);margin-right:6px"></i>Date-wise sales</h3>
      <span class="text-muted" style="font-size:.8rem"><?= e($from) ?> → <?= e($to) ?></span>
    </div>
    <div class="panel-body" style="padding:0;overflow-x:auto;max-height:340px">
      <table class="report-table">
        <thead>
          <tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Delivered</th><th>Cancelled</th></tr>
        </thead>
        <tbody>
        <?php if (empty($salesDaily)): ?>
          <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--color-text-muted)">No sales in this range</td></tr>
        <?php else: ?>
          <?php
            $sumOrd = 0; $sumRev = 0; $sumDel = 0; $sumCan = 0;
            foreach ($salesDaily as $r):
              $sumOrd += (int)$r['orders_count'];
              $sumRev += (float)$r['revenue'];
              $sumDel += (int)$r['delivered'];
              $sumCan += (int)$r['cancelled'];
          ?>
          <tr>
            <td><strong><?= e(date('D, M j', strtotime($r['d']))) ?></strong><br><span class="text-muted" style="font-size:.75rem"><?= e($r['d']) ?></span></td>
            <td><?= (int)$r['orders_count'] ?></td>
            <td><strong style="color:var(--color-primary)"><?= report_money($r['revenue']) ?></strong></td>
            <td style="color:#16a34a"><?= (int)$r['delivered'] ?></td>
            <td style="color:<?= (int)$r['cancelled'] > 0 ? '#ef4444' : 'inherit' ?>"><?= (int)$r['cancelled'] ?></td>
          </tr>
          <?php endforeach; ?>
          <tr style="background:color-mix(in srgb,var(--color-primary) 6%,transparent);font-weight:800">
            <td>Total</td>
            <td><?= $sumOrd ?></td>
            <td style="color:var(--color-primary)"><?= report_money($sumRev) ?></td>
            <td style="color:#16a34a"><?= $sumDel ?></td>
            <td style="color:<?= $sumCan > 0 ? '#ef4444' : 'inherit' ?>"><?= $sumCan ?></td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right column: payments + status + stock snapshot -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="panel" style="margin:0">
      <div class="panel-header">
        <h3 class="ov-section-title"><i class="fas fa-wallet" style="color:var(--color-primary);margin-right:6px"></i>Payments</h3>
      </div>
      <div class="panel-body" style="padding:0;overflow-x:auto">
        <table class="report-table">
          <thead><tr><th>Method</th><th>Orders</th><th>Amount</th><th>Paid</th></tr></thead>
          <tbody>
          <?php if (empty($paymentsByMethod)): ?>
            <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--color-text-muted)">No data</td></tr>
          <?php endif; ?>
          <?php foreach ($paymentsByMethod as $r):
            $m = strtolower($r['payment_method']);
            $lab = isset($payLabels[$m]) ? $payLabels[$m] : strtoupper($r['payment_method']);
          ?>
            <tr>
              <td><strong><?= e($lab) ?></strong></td>
              <td><?= (int)$r['cnt'] ?></td>
              <td><?= report_money($r['amount']) ?></td>
              <td style="color:#16a34a;font-weight:700"><?= report_money($r['paid_amount']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel" style="margin:0">
      <div class="panel-header">
        <h3 class="ov-section-title"><i class="fas fa-truck" style="color:var(--color-primary);margin-right:6px"></i>Order status</h3>
      </div>
      <div class="panel-body" style="padding:0;overflow-x:auto">
        <table class="report-table">
          <thead><tr><th>Status</th><th>Count</th><th>Amount</th></tr></thead>
          <tbody>
          <?php if (empty($ordersByStatus)): ?>
            <tr><td colspan="3" style="text-align:center;padding:20px;color:var(--color-text-muted)">No data</td></tr>
          <?php endif; ?>
          <?php foreach ($ordersByStatus as $r): ?>
            <tr>
              <td><span class="badge-sm <?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
              <td><?= (int)$r['cnt'] ?></td>
              <td><?= report_money($r['amount']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Orders in range with product images -->
<div class="panel">
  <div class="panel-header">
    <h3 class="ov-section-title"><i class="fas fa-shopping-bag" style="color:var(--color-primary);margin-right:6px"></i>Orders in period <span class="text-muted" style="font-weight:500;font-size:.85rem">(<?= count($ovOrders) ?>)</span></h3>
    <a href="?report=daily_detail&from=<?= e($from) ?>&to=<?= e($to) ?><?= $payMethod !== '' ? '&payment_method=' . urlencode($payMethod) : '' ?>" class="btn btn-sm btn-outline">Full day-by-day view →</a>
  </div>
  <div class="panel-body" style="padding:0">
    <?php if (empty($ovOrders)): ?>
      <div style="text-align:center;padding:40px;color:var(--color-text-muted)">
        <i class="fas fa-inbox" style="font-size:1.8rem;opacity:.4;display:block;margin-bottom:10px"></i>
        No orders in this date range<?= $payMethod !== '' ? ' for the selected payment method' : '' ?>.
      </div>
    <?php else: ?>
      <?php foreach ($ovOrders as $o):
        $oid = (int)$o['id'];
        $items = isset($ovItemsByOrder[$oid]) ? $ovItemsByOrder[$oid] : array();
        $trx = !empty($o['transaction_id']) ? $o['transaction_id'] : '';
        if ($trx === '' && !empty($o['notes']) && preg_match('/TrxID:\s*([^\|\n]+)/i', $o['notes'], $m)) {
            $trx = trim($m[1]);
        }
      ?>
      <div class="ov-order-row">
        <div class="ov-thumbs">
          <?php
            $shown = 0;
            foreach ($items as $it) {
                if ($shown >= 3) break;
                $img = order_item_image($it);
                if ($img) {
                    echo '<img src="' . e($img) . '" alt="">';
                } else {
                    echo '<div class="ph"><i class="fas fa-shirt"></i></div>';
                }
                $shown++;
            }
            if ($shown === 0) {
                echo '<div class="ph"><i class="fas fa-shirt"></i></div>';
            }
            if (count($items) > 3) {
                echo '<div class="ph" style="opacity:.7;font-size:.65rem;font-weight:800">+' . (count($items) - 3) . '</div>';
            }
          ?>
        </div>
        <div class="ov-order-info">
          <a class="num" href="/admin/order-view.php?id=<?= $oid ?>">#<?= e($o['order_number']) ?></a>
          <span class="badge-sm <?= e($o['status']) ?>" style="margin-left:6px"><?= e(ucfirst($o['status'])) ?></span>
          <span class="badge-sm <?= $o['payment_status'] === 'paid' ? 'paid' : 'pay-pending' ?>" style="margin-left:3px"><?= e(ucfirst($o['payment_status'])) ?></span>
          <div class="ov-order-meta">
            <?= e(formatDate($o['created_at'], 'M j, Y · h:i A')) ?>
            · <strong><?= e($o['customer_name']) ?></strong>
            · <?= e($o['customer_phone'] ?: $o['customer_email']) ?>
          </div>
          <div class="ov-order-meta">
            <?= e(ReportExport::payLabel($o['payment_method'])) ?>
            <?php if ($trx !== ''): ?> · TrxID: <code><?= e($trx) ?></code><?php endif; ?>
            <?php
              $itemNames = array();
              foreach ($items as $it) {
                  $itemNames[] = $it['product_name'] . ( $it['size'] || $it['color'] ? ' (' . trim(($it['size'] ?: '') . ' ' . ($it['color'] ?: '')) . ')' : '' ) . ' ×' . (int)$it['quantity'];
              }
              if ($itemNames):
            ?>
            · <?= e(implode(', ', array_slice($itemNames, 0, 3))) ?><?= count($itemNames) > 3 ? '…' : '' ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="ov-order-right">
          <div class="amt"><?= report_money($o['total']) ?></div>
          <div class="ov-order-meta">Ship <?= report_money($o['shipping_cost']) ?></div>
          <a href="/admin/order-view.php?id=<?= $oid ?>" class="btn btn-sm btn-outline" style="margin-top:6px">View</a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Top products + inventory snapshot -->
<div class="ov-grid">
  <div class="panel" style="margin:0">
    <div class="panel-header">
      <h3 class="ov-section-title"><i class="fas fa-fire" style="color:var(--color-primary);margin-right:6px"></i>Top products</h3>
      <a href="?report=top_products&from=<?= e($from) ?>&to=<?= e($to) ?>" class="btn btn-sm btn-outline">See all</a>
    </div>
    <div class="panel-body" style="padding:0;overflow-x:auto">
      <table class="report-table">
        <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php if (empty($topProducts)): ?>
          <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--color-text-muted)">No sales</td></tr>
        <?php endif; ?>
        <?php $i = 1; foreach (array_slice($topProducts, 0, 8) as $r): ?>
          <tr>
            <td style="color:var(--color-text-muted);font-weight:700"><?= $i++ ?></td>
            <td>
              <strong><?= e($r['product_name']) ?></strong>
              <?php if (!empty($r['product_id'])): ?>
              <a href="/admin/product-edit.php?id=<?= (int)$r['product_id'] ?>" class="text-muted" style="font-size:.75rem;margin-left:6px">Edit</a>
              <?php endif; ?>
            </td>
            <td><?= (int)$r['qty_sold'] ?></td>
            <td style="font-weight:700;color:var(--color-primary)"><?= report_money($r['revenue']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel" style="margin:0">
    <div class="panel-header">
      <h3 class="ov-section-title"><i class="fas fa-boxes" style="color:var(--color-primary);margin-right:6px"></i>Inventory snapshot</h3>
      <a href="?report=stock&from=<?= e($from) ?>&to=<?= e($to) ?>" class="btn btn-sm btn-outline">Stock report</a>
    </div>
    <div class="panel-body">
      <div class="stat-grid" style="margin:0">
        <div class="stat-card info"><div class="label">Total units</div><div class="value"><?= (int)$totalUnits ?></div></div>
        <div class="stat-card danger"><div class="label">Out of stock</div><div class="value"><?= (int)$outStock ?></div></div>
        <div class="stat-card warn"><div class="label">Low stock ≤5</div><div class="value"><?= (int)$lowStock ?></div></div>
        <div class="stat-card"><div class="label">Cancelled ৳</div><div class="value" style="font-size:1.1rem"><?= report_money($summary['cancelled_amount']) ?></div></div>
      </div>
      <p class="text-muted" style="margin:14px 0 0;font-size:.82rem">
        Period <strong><?= e($from) ?></strong> → <strong><?= e($to) ?></strong>
        <?= $payMethod !== '' ? ' · Filter: <strong>' . e(ReportExport::payLabel($payMethod)) . '</strong>' : '' ?>
      </p>
    </div>
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
<?php
  $salesTotalOrders = count($dailyOrders);
  $salesRevenue = 0;
  $salesDelivered = 0;
  $salesCancelled = 0;
  foreach ($dailyOrders as $o) {
      $salesRevenue += (float)$o['total'];
      if ($o['status'] === 'delivered') $salesDelivered++;
      if ($o['status'] === 'cancelled') $salesCancelled++;
  }
?>
<div class="stat-grid">
  <div class="stat-card info"><div class="label">Orders</div><div class="value"><?= (int)$salesTotalOrders ?></div></div>
  <div class="stat-card ok"><div class="label">Revenue</div><div class="value"><?= report_money($salesRevenue) ?></div></div>
  <div class="stat-card ok"><div class="label">Delivered</div><div class="value"><?= (int)$salesDelivered ?></div></div>
  <div class="stat-card danger"><div class="label">Cancelled</div><div class="value"><?= (int)$salesCancelled ?></div></div>
  <div class="stat-card info"><div class="label">Days with sales</div><div class="value"><?= count($dailyOrdersByDate) ?></div></div>
</div>

<div class="export-bar">
  <span class="lbl">Export:</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('sales', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Summary Excel</a>
  <a class="btn btn-sm btn-primary" href="<?= e(report_export_url('daily_detail', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Full Detail Excel</a>
  <a class="btn btn-sm btn-primary" target="_blank" href="<?= e(report_export_url('daily_detail', 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> Full Detail PDF</a>
</div>

<?php if (empty($dailyOrdersByDate)): ?>
<div class="panel">
  <div class="panel-body" style="text-align:center;padding:40px;color:var(--color-text-muted)">
    <i class="fas fa-inbox" style="font-size:1.8rem;opacity:.4;display:block;margin-bottom:10px"></i>
    No orders in this date range<?= $payMethod !== '' ? ' for the selected payment method' : '' ?>.
  </div>
</div>
<?php else: ?>
<?php
  krsort($dailyOrdersByDate);
  foreach ($dailyOrdersByDate as $day => $orders):
    $dayRev = 0;
    $dayCnt = count($orders);
    $dayDel = 0;
    $dayCan = 0;
    foreach ($orders as $o) {
        $dayRev += (float)$o['total'];
        if ($o['status'] === 'delivered') $dayDel++;
        if ($o['status'] === 'cancelled') $dayCan++;
    }
?>
<div class="day-block">
  <div class="day-header">
    <h3><i class="fas fa-calendar-day" style="opacity:.7;margin-right:6px"></i><?= e(date('D, M j, Y', strtotime($day))) ?></h3>
    <div class="day-stats">
      <span>Orders: <strong><?= $dayCnt ?></strong></span>
      <span>Revenue: <strong style="color:var(--color-primary)"><?= report_money($dayRev) ?></strong></span>
      <span>Delivered: <strong style="color:#16a34a"><?= $dayDel ?></strong></span>
      <?php if ($dayCan > 0): ?><span style="color:#ef4444">Cancelled: <strong><?= $dayCan ?></strong></span><?php endif; ?>
    </div>
  </div>
  <?php foreach ($orders as $o):
    $oid = (int)$o['id'];
    $items = isset($dailyItemsByOrder[$oid]) ? $dailyItemsByOrder[$oid] : array();
    $trx = !empty($o['transaction_id']) ? $o['transaction_id'] : '';
    $proof = !empty($o['payment_proof']) ? $o['payment_proof'] : '';
    if ($proof === '' && !empty($o['notes']) && preg_match('/Payment proof(?: file)?:\s*(\S+)/i', $o['notes'], $m)) {
        $proof = $m[1];
    }
    if ($trx === '' && !empty($o['notes']) && preg_match('/TrxID:\s*([^\|\n]+)/i', $o['notes'], $m)) {
        $trx = trim($m[1]);
    }
    $proofUrl = $proof !== '' ? '/' . ltrim(str_replace('\\', '/', $proof), '/') : '';
  ?>
  <div class="order-card">
    <div class="order-card-top">
      <div>
        <a href="/admin/order-view.php?id=<?= $oid ?>" style="font-weight:800;font-size:1rem;text-decoration:none;color:var(--color-text)">
          #<?= e($o['order_number']) ?>
        </a>
        <span class="badge-sm <?= e($o['status']) ?>" style="margin-left:8px"><?= e(ucfirst($o['status'])) ?></span>
        <span class="badge-sm <?= $o['payment_status'] === 'paid' ? 'paid' : 'pay-pending' ?>" style="margin-left:4px"><?= e(ucfirst($o['payment_status'])) ?></span>
        <div class="order-meta" style="margin-top:6px">
          <?= e(formatDate($o['created_at'], 'h:i A')) ?>
          · <strong><?= e($o['customer_name']) ?></strong>
          · <?= e($o['customer_phone'] ?: $o['customer_email']) ?>
          · <?= e(ReportExport::payLabel($o['payment_method'])) ?>
          <?php if ($trx !== ''): ?> · TrxID: <code><?= e($trx) ?></code><?php endif; ?>
        </div>
        <div class="order-meta" style="margin-top:2px">
          <?= e($o['shipping_address']) ?><?= $o['shipping_city'] ? ', ' . e($o['shipping_city']) : '' ?>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:800;font-size:1.15rem;color:var(--color-primary)"><?= report_money($o['total']) ?></div>
        <div class="order-meta">Sub <?= report_money($o['subtotal']) ?> + Ship <?= report_money($o['shipping_cost']) ?></div>
        <a href="/admin/order-view.php?id=<?= $oid ?>" class="btn btn-sm btn-outline" style="margin-top:6px">View</a>
      </div>
    </div>

    <div class="order-items-grid">
      <?php if (empty($items)): ?>
        <div class="text-muted" style="font-size:.85rem">No line items</div>
      <?php endif; ?>
      <?php foreach ($items as $it):
        $img = order_item_image($it);
      ?>
      <div class="order-item-row">
        <?php if ($img): ?>
          <img src="<?= e($img) ?>" alt="">
        <?php else: ?>
          <div class="order-item-placeholder"><i class="fas fa-shirt"></i></div>
        <?php endif; ?>
        <div style="flex:1;min-width:0">
          <strong style="font-size:.9rem"><?= e($it['product_name']) ?></strong>
          <?php if (!empty($it['product_sku'])): ?>
            <span class="text-muted" style="font-size:.75rem;margin-left:6px"><?= e($it['product_sku']) ?></span>
          <?php endif; ?>
          <div class="order-meta">
            <?php if ($it['size']): ?>Size: <?= e($it['size']) ?><?php endif; ?>
            <?php if ($it['color']): ?><?= $it['size'] ? ' · ' : '' ?>Color: <?= e($it['color']) ?><?php endif; ?>
            · Qty <?= (int)$it['quantity'] ?> × <?= report_money($it['price']) ?>
          </div>
        </div>
        <div style="font-weight:700;white-space:nowrap"><?= report_money($it['total']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($proofUrl !== ''): ?>
    <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <span class="order-meta" style="font-weight:700">Payment proof:</span>
      <a href="<?= e($proofUrl) ?>" target="_blank" rel="noopener">
        <img src="<?= e($proofUrl) ?>" alt="Payment proof" class="proof-thumb">
      </a>
      <a href="<?= e($proofUrl) ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-external-link-alt"></i> Open</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php elseif ($report === 'daily_detail'): ?>
<?php
  // Summary for whole range
  $ddTotalOrders = count($dailyOrders);
  $ddRevenue = 0;
  $ddDelivered = 0;
  $ddCancelled = 0;
  foreach ($dailyOrders as $o) {
      $ddRevenue += (float)$o['total'];
      if ($o['status'] === 'delivered') $ddDelivered++;
      if ($o['status'] === 'cancelled') $ddCancelled++;
  }
?>
<div class="stat-grid">
  <div class="stat-card"><div class="label">Orders</div><div class="value"><?= (int)$ddTotalOrders ?></div></div>
  <div class="stat-card ok"><div class="label">Revenue</div><div class="value"><?= report_money($ddRevenue) ?></div></div>
  <div class="stat-card ok"><div class="label">Delivered</div><div class="value"><?= (int)$ddDelivered ?></div></div>
  <div class="stat-card danger"><div class="label">Cancelled</div><div class="value"><?= (int)$ddCancelled ?></div></div>
  <div class="stat-card"><div class="label">Days with sales</div><div class="value"><?= count($dailyOrdersByDate) ?></div></div>
</div>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;align-items:center">
  <span class="text-muted" style="font-size:.8rem;font-weight:700;text-transform:uppercase">Export this detail report:</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('daily_detail', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Excel</a>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('daily_detail', 'csv', $from, $to, $payMethod)) ?>"><i class="fas fa-file-csv"></i> CSV</a>
  <a class="btn btn-sm btn-primary" target="_blank" href="<?= e(report_export_url('daily_detail', 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> PDF (with images)</a>
</div>

<?php if (empty($dailyOrdersByDate)): ?>
<div class="panel">
  <div class="panel-body" style="text-align:center;padding:40px;color:var(--color-text-muted)">
    No orders in this date range<?= $payMethod !== '' ? ' for the selected payment method' : '' ?>.
  </div>
</div>
<?php else: ?>
<?php
  // Show newest day first
  krsort($dailyOrdersByDate);
  foreach ($dailyOrdersByDate as $day => $orders):
    $dayRev = 0;
    $dayCnt = count($orders);
    $dayDel = 0;
    $dayCan = 0;
    foreach ($orders as $o) {
        $dayRev += (float)$o['total'];
        if ($o['status'] === 'delivered') $dayDel++;
        if ($o['status'] === 'cancelled') $dayCan++;
    }
?>
<div class="day-block">
  <div class="day-header">
    <h3><i class="fas fa-calendar-day" style="opacity:.7;margin-right:6px"></i><?= e(date('D, M j, Y', strtotime($day))) ?></h3>
    <div class="day-stats">
      <span>Orders: <strong><?= $dayCnt ?></strong></span>
      <span>Revenue: <strong><?= report_money($dayRev) ?></strong></span>
      <span>Delivered: <strong><?= $dayDel ?></strong></span>
      <?php if ($dayCan > 0): ?><span style="color:#ef4444">Cancelled: <strong><?= $dayCan ?></strong></span><?php endif; ?>
    </div>
  </div>
  <?php foreach ($orders as $o):
    $oid = (int)$o['id'];
    $items = isset($dailyItemsByOrder[$oid]) ? $dailyItemsByOrder[$oid] : array();
    $trx = !empty($o['transaction_id']) ? $o['transaction_id'] : '';
    $proof = !empty($o['payment_proof']) ? $o['payment_proof'] : '';
    if ($proof === '' && !empty($o['notes']) && preg_match('/Payment proof(?: file)?:\s*(\S+)/i', $o['notes'], $m)) {
        $proof = $m[1];
    }
    if ($trx === '' && !empty($o['notes']) && preg_match('/TrxID:\s*([^\|\n]+)/i', $o['notes'], $m)) {
        $trx = trim($m[1]);
    }
    $proofUrl = $proof !== '' ? '/' . ltrim(str_replace('\\', '/', $proof), '/') : '';
  ?>
  <div class="order-card">
    <div class="order-card-top">
      <div>
        <a href="/admin/order-view.php?id=<?= $oid ?>" style="font-weight:800;font-size:1rem;text-decoration:none;color:var(--color-text)">
          #<?= e($o['order_number']) ?>
        </a>
        <span class="badge-sm <?= e($o['status']) ?>" style="margin-left:8px"><?= e(ucfirst($o['status'])) ?></span>
        <span class="badge-sm <?= $o['payment_status'] === 'paid' ? 'paid' : 'pay-pending' ?>" style="margin-left:4px"><?= e(ucfirst($o['payment_status'])) ?></span>
        <div class="order-meta" style="margin-top:6px">
          <?= e(formatDate($o['created_at'], 'h:i A')) ?>
          · <?= e($o['customer_name']) ?>
          · <?= e($o['customer_phone'] ?: $o['customer_email']) ?>
          · <?= e(ReportExport::payLabel($o['payment_method'])) ?>
          <?php if ($trx !== ''): ?> · TrxID: <code><?= e($trx) ?></code><?php endif; ?>
        </div>
        <div class="order-meta" style="margin-top:2px">
          <?= e($o['shipping_address']) ?><?= $o['shipping_city'] ? ', ' . e($o['shipping_city']) : '' ?>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:800;font-size:1.15rem"><?= report_money($o['total']) ?></div>
        <div class="order-meta">Sub <?= report_money($o['subtotal']) ?> + Ship <?= report_money($o['shipping_cost']) ?></div>
        <a href="/admin/order-view.php?id=<?= $oid ?>" class="btn btn-sm btn-outline" style="margin-top:6px">View</a>
      </div>
    </div>

    <div class="order-items-grid">
      <?php if (empty($items)): ?>
        <div class="text-muted" style="font-size:.85rem">No line items</div>
      <?php endif; ?>
      <?php foreach ($items as $it):
        $img = order_item_image($it);
      ?>
      <div class="order-item-row">
        <?php if ($img): ?>
          <img src="<?= e($img) ?>" alt="">
        <?php else: ?>
          <div class="order-item-placeholder"><i class="fas fa-shirt"></i></div>
        <?php endif; ?>
        <div style="flex:1;min-width:0">
          <strong style="font-size:.9rem"><?= e($it['product_name']) ?></strong>
          <?php if (!empty($it['product_sku'])): ?>
            <span class="text-muted" style="font-size:.75rem;margin-left:6px"><?= e($it['product_sku']) ?></span>
          <?php endif; ?>
          <div class="order-meta">
            <?php if ($it['size']): ?>Size: <?= e($it['size']) ?><?php endif; ?>
            <?php if ($it['color']): ?><?= $it['size'] ? ' · ' : '' ?>Color: <?= e($it['color']) ?><?php endif; ?>
            · Qty <?= (int)$it['quantity'] ?> × <?= report_money($it['price']) ?>
          </div>
        </div>
        <div style="font-weight:700;white-space:nowrap"><?= report_money($it['total']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($proofUrl !== ''): ?>
    <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <span class="order-meta" style="font-weight:700">Payment proof:</span>
      <a href="<?= e($proofUrl) ?>" target="_blank" rel="noopener">
        <img src="<?= e($proofUrl) ?>" alt="Payment proof" class="proof-thumb">
      </a>
      <a href="<?= e($proofUrl) ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-external-link-alt"></i> Open</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php elseif ($report === 'orders_status'): ?>
<div class="stat-grid">
  <?php foreach ($ordersByStatus as $r): ?>
  <div class="stat-card <?= $r['status']==='cancelled'?'danger':($r['status']==='delivered'?'ok':'info') ?>">
    <div class="label"><?= e(ucfirst($r['status'])) ?></div>
    <div class="value"><?= (int)$r['cnt'] ?></div>
    <div class="text-muted" style="font-size:.8rem;margin-top:4px"><?= report_money($r['amount']) ?></div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($ordersByStatus)): ?>
  <div class="stat-card"><div class="label">No orders</div><div class="value">0</div></div>
  <?php endif; ?>
</div>

<div class="export-bar">
  <span class="lbl">Export:</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('orders_status', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Status Excel</a>
  <a class="btn btn-sm btn-primary" href="<?= e(report_export_url('orders', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Orders Excel</a>
  <a class="btn btn-sm btn-primary" target="_blank" href="<?= e(report_export_url('daily_detail', 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> Full Detail PDF</a>
</div>

<?php
  $statusOrder = array('pending','confirmed','processing','shipped','delivered','cancelled');
  $shownStatuses = array();
  foreach ($statusOrder as $st) {
      if (!empty($ordersByStatusDetail[$st])) $shownStatuses[$st] = $ordersByStatusDetail[$st];
  }
  foreach ($ordersByStatusDetail as $st => $list) {
      if (!isset($shownStatuses[$st])) $shownStatuses[$st] = $list;
  }
  if (empty($shownStatuses)):
?>
<div class="panel"><div class="panel-body" style="text-align:center;padding:36px;color:var(--color-text-muted)">No orders in this range.</div></div>
<?php else: ?>
<?php foreach ($shownStatuses as $st => $list):
  $stAmt = 0;
  foreach ($list as $o) $stAmt += (float)$o['total'];
?>
<div class="day-block">
  <div class="day-header">
    <h3><span class="badge-sm <?= e($st) ?>" style="font-size:.8rem;padding:5px 12px"><?= e(ucfirst($st)) ?></span></h3>
    <div class="day-stats">
      <span>Orders: <strong><?= count($list) ?></strong></span>
      <span>Amount: <strong style="color:var(--color-primary)"><?= report_money($stAmt) ?></strong></span>
    </div>
  </div>
  <?php foreach ($list as $o):
    $oid = (int)$o['id'];
    $items = isset($dailyItemsByOrder[$oid]) ? $dailyItemsByOrder[$oid] : array();
  ?>
  <div class="order-card">
    <div class="order-card-top">
      <div>
        <a href="/admin/order-view.php?id=<?= $oid ?>" style="font-weight:800;font-size:1rem;text-decoration:none;color:var(--color-text)">#<?= e($o['order_number']) ?></a>
        <span class="badge-sm <?= $o['payment_status']==='paid'?'paid':'pay-pending' ?>" style="margin-left:6px"><?= e(ucfirst($o['payment_status'])) ?></span>
        <div class="order-meta" style="margin-top:6px">
          <?= e(formatDate($o['created_at'], 'M j, Y · h:i A')) ?>
          · <strong><?= e($o['customer_name']) ?></strong>
          · <?= e($o['customer_phone'] ?: $o['customer_email']) ?>
          · <?= e(ReportExport::payLabel($o['payment_method'])) ?>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:800;font-size:1.1rem;color:var(--color-primary)"><?= report_money($o['total']) ?></div>
        <a href="/admin/order-view.php?id=<?= $oid ?>" class="btn btn-sm btn-outline" style="margin-top:6px">View</a>
      </div>
    </div>
    <div class="order-items-grid">
      <?php foreach ($items as $it):
        $img = order_item_image($it);
      ?>
      <div class="order-item-row">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><div class="order-item-placeholder"><i class="fas fa-shirt"></i></div><?php endif; ?>
        <div style="flex:1;min-width:0">
          <strong style="font-size:.9rem"><?= e($it['product_name']) ?></strong>
          <div class="order-meta">
            <?php if ($it['size']): ?>Size: <?= e($it['size']) ?><?php endif; ?>
            <?php if ($it['color']): ?><?= $it['size']?' · ':'' ?>Color: <?= e($it['color']) ?><?php endif; ?>
            · Qty <?= (int)$it['quantity'] ?> × <?= report_money($it['price']) ?>
          </div>
        </div>
        <div style="font-weight:700"><?= report_money($it['total']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php elseif ($report === 'payments'): ?>
<div class="stat-grid">
  <?php foreach ($paymentsByMethod as $r):
    $m = strtolower($r['payment_method']);
    $lab = isset($payLabels[$m]) ? $payLabels[$m] : strtoupper($r['payment_method']);
  ?>
  <div class="stat-card info">
    <div class="label"><?= e($lab) ?></div>
    <div class="value" style="font-size:1.2rem"><?= report_money($r['amount']) ?></div>
    <div class="text-muted" style="font-size:.8rem;margin-top:4px"><?= (int)$r['cnt'] ?> orders · Paid <?= report_money($r['paid_amount']) ?></div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($paymentsByMethod)): ?>
  <div class="stat-card"><div class="label">No payments</div><div class="value">0</div></div>
  <?php endif; ?>
</div>

<div class="export-bar">
  <span class="lbl">Export:</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('payments', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Payments Excel</a>
  <a class="btn btn-sm btn-primary" href="<?= e(report_export_url('daily_detail', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Full Detail Excel</a>
  <a class="btn btn-sm btn-primary" target="_blank" href="<?= e(report_export_url('daily_detail', 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> Full Detail PDF</a>
</div>

<?php if (empty($ordersByPayDetail)): ?>
<div class="panel"><div class="panel-body" style="text-align:center;padding:36px;color:var(--color-text-muted)">No payments in this range.</div></div>
<?php else: ?>
<?php foreach ($ordersByPayDetail as $pm => $list):
  $lab = isset($payLabels[$pm]) ? $payLabels[$pm] : strtoupper($pm);
  $pmAmt = 0; $pmPaid = 0;
  foreach ($list as $o) {
      $pmAmt += (float)$o['total'];
      if ($o['payment_status'] === 'paid') $pmPaid += (float)$o['total'];
  }
?>
<div class="day-block">
  <div class="day-header">
    <h3><i class="fas fa-wallet" style="opacity:.7;margin-right:6px"></i><?= e($lab) ?></h3>
    <div class="day-stats">
      <span>Orders: <strong><?= count($list) ?></strong></span>
      <span>Total: <strong style="color:var(--color-primary)"><?= report_money($pmAmt) ?></strong></span>
      <span>Paid: <strong style="color:#16a34a"><?= report_money($pmPaid) ?></strong></span>
    </div>
  </div>
  <?php foreach ($list as $o):
    $oid = (int)$o['id'];
    $items = isset($dailyItemsByOrder[$oid]) ? $dailyItemsByOrder[$oid] : array();
    $trx = !empty($o['transaction_id']) ? $o['transaction_id'] : '';
    $proof = !empty($o['payment_proof']) ? $o['payment_proof'] : '';
    if ($proof === '' && !empty($o['notes']) && preg_match('/Payment proof(?: file)?:\s*(\S+)/i', $o['notes'], $m)) $proof = $m[1];
    if ($trx === '' && !empty($o['notes']) && preg_match('/TrxID:\s*([^\|\n]+)/i', $o['notes'], $m)) $trx = trim($m[1]);
    $proofUrl = $proof !== '' ? '/' . ltrim(str_replace('\\', '/', $proof), '/') : '';
  ?>
  <div class="order-card">
    <div class="order-card-top">
      <div>
        <a href="/admin/order-view.php?id=<?= $oid ?>" style="font-weight:800;font-size:1rem;text-decoration:none;color:var(--color-text)">#<?= e($o['order_number']) ?></a>
        <span class="badge-sm <?= e($o['status']) ?>" style="margin-left:6px"><?= e(ucfirst($o['status'])) ?></span>
        <span class="badge-sm <?= $o['payment_status']==='paid'?'paid':'pay-pending' ?>" style="margin-left:4px"><?= e(ucfirst($o['payment_status'])) ?></span>
        <div class="order-meta" style="margin-top:6px">
          <?= e(formatDate($o['created_at'], 'M j, Y · h:i A')) ?>
          · <strong><?= e($o['customer_name']) ?></strong>
          · <?= e($o['customer_phone'] ?: $o['customer_email']) ?>
          <?php if ($trx !== ''): ?> · TrxID: <code><?= e($trx) ?></code><?php endif; ?>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:800;font-size:1.1rem;color:var(--color-primary)"><?= report_money($o['total']) ?></div>
        <a href="/admin/order-view.php?id=<?= $oid ?>" class="btn btn-sm btn-outline" style="margin-top:6px">View</a>
      </div>
    </div>
    <div class="order-items-grid">
      <?php foreach ($items as $it):
        $img = order_item_image($it);
      ?>
      <div class="order-item-row">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><div class="order-item-placeholder"><i class="fas fa-shirt"></i></div><?php endif; ?>
        <div style="flex:1;min-width:0">
          <strong style="font-size:.9rem"><?= e($it['product_name']) ?></strong>
          <div class="order-meta">
            <?php if ($it['size']): ?>Size: <?= e($it['size']) ?><?php endif; ?>
            <?php if ($it['color']): ?><?= $it['size']?' · ':'' ?>Color: <?= e($it['color']) ?><?php endif; ?>
            · Qty <?= (int)$it['quantity'] ?> × <?= report_money($it['price']) ?>
          </div>
        </div>
        <div style="font-weight:700"><?= report_money($it['total']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($proofUrl !== ''): ?>
    <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <span class="order-meta" style="font-weight:700">Payment proof:</span>
      <a href="<?= e($proofUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($proofUrl) ?>" alt="Proof" class="proof-thumb"></a>
      <a href="<?= e($proofUrl) ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-external-link-alt"></i> Open</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php elseif ($report === 'top_products'): ?>
<?php
  $tpTotalQty = 0; $tpTotalRev = 0;
  foreach ($topProducts as $r) {
      $tpTotalQty += (int)$r['qty_sold'];
      $tpTotalRev += (float)$r['revenue'];
  }
?>
<div class="stat-grid">
  <div class="stat-card info"><div class="label">Products sold</div><div class="value"><?= count($topProducts) ?></div></div>
  <div class="stat-card info"><div class="label">Units sold</div><div class="value"><?= (int)$tpTotalQty ?></div></div>
  <div class="stat-card ok"><div class="label">Revenue</div><div class="value"><?= report_money($tpTotalRev) ?></div></div>
</div>

<div class="export-bar">
  <span class="lbl">Export:</span>
  <a class="btn btn-sm btn-outline" href="<?= e(report_export_url('top_products', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> Top products Excel</a>
  <a class="btn btn-sm btn-primary" href="<?= e(report_export_url('order_items', 'excel', $from, $to, $payMethod)) ?>"><i class="fas fa-file-excel"></i> All items Excel</a>
  <a class="btn btn-sm btn-primary" target="_blank" href="<?= e(report_export_url('daily_detail', 'pdf', $from, $to, $payMethod)) ?>"><i class="fas fa-file-pdf"></i> Full Detail PDF</a>
</div>

<div class="panel">
  <div class="panel-header">
    <h3 class="ov-section-title"><i class="fas fa-fire" style="color:var(--color-primary);margin-right:6px"></i>Best sellers (excl. cancelled)</h3>
    <span class="text-muted" style="font-size:.8rem"><?= e($from) ?> → <?= e($to) ?></span>
  </div>
  <div class="panel-body" style="padding:0">
    <?php if (empty($topProducts)): ?>
      <div style="text-align:center;padding:40px;color:var(--color-text-muted)">No sales in this range.</div>
    <?php else: ?>
      <?php $i = 1; foreach ($topProducts as $r):
        $pid = (int)(isset($r['product_id']) ? $r['product_id'] : 0);
        $img = null;
        if (!empty($r['product_image'])) {
            $img = ProductImage::url($r['product_image']);
        } elseif ($pid > 0) {
            $pRow = Database::fetch("SELECT * FROM products WHERE id=?", array($pid));
            if ($pRow) $img = ProductImage::productThumb($pRow);
        }
        $variants = ($pid > 0 && isset($topVariantMap[$pid])) ? $topVariantMap[$pid] : array();
      ?>
      <div class="ov-order-row" style="align-items:center">
        <div style="width:36px;font-weight:800;color:var(--color-text-muted);font-size:1.1rem;flex-shrink:0">#<?= $i++ ?></div>
        <div class="ov-thumbs">
          <?php if ($img): ?>
            <img src="<?= e($img) ?>" alt="" style="width:52px;height:64px">
          <?php else: ?>
            <div class="ph" style="width:52px;height:64px"><i class="fas fa-shirt"></i></div>
          <?php endif; ?>
        </div>
        <div class="ov-order-info">
          <strong style="font-size:1rem"><?= e($r['product_name']) ?></strong>
          <?php if (!empty($r['product_sku'])): ?>
            <span class="text-muted" style="font-size:.8rem;margin-left:6px"><?= e($r['product_sku']) ?></span>
          <?php endif; ?>
          <?php if ($pid): ?>
            <a href="/admin/product-edit.php?id=<?= $pid ?>" class="btn btn-sm btn-outline" style="margin-left:8px">Edit</a>
          <?php endif; ?>
          <?php if (!empty($variants)): ?>
          <div class="order-meta" style="margin-top:6px">
            <?php
              $bits = array();
              foreach (array_slice($variants, 0, 6) as $v) {
                  $label = trim(($v['size'] ?: '') . ' ' . ($v['color'] ?: ''));
                  if ($label === '') $label = 'Default';
                  $bits[] = e($label) . ' ×' . (int)$v['qty'];
              }
              echo implode(' · ', $bits);
              if (count($variants) > 6) echo ' …';
            ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="ov-order-right">
          <div class="amt" style="color:var(--color-primary)"><?= report_money($r['revenue']) ?></div>
          <div class="ov-order-meta"><?= (int)$r['qty_sold'] ?> units · <?= (int)$r['orders_count'] ?> orders</div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/app/views/layouts/admin.php';