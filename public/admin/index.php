<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Dashboard';

$stats = [
    'products'   => (int) Database::fetch("SELECT COUNT(*) c FROM products WHERE status='active'")['c'],
    'orders'     => (int) Database::fetch("SELECT COUNT(*) c FROM orders")['c'],
    'pending'    => (int) Database::fetch("SELECT COUNT(*) c FROM orders WHERE status='pending'")['c'],
    'customers'  => (int) Database::fetch("SELECT COUNT(*) c FROM users WHERE role_id=4")['c'],
    'revenue'    => (float) Database::fetch("SELECT COALESCE(SUM(total),0) t FROM orders WHERE status NOT IN ('cancelled')")['t'],
    'today'      => (float) Database::fetch("SELECT COALESCE(SUM(total),0) t FROM orders WHERE DATE(created_at)=CURDATE() AND status NOT IN ('cancelled')")['t'],
    'low_stock'  => (int) Database::fetch("SELECT COUNT(*) c FROM products WHERE stock > 0 AND stock <= 5 AND status='active'")['c'],
    'out_stock'  => (int) Database::fetch("SELECT COUNT(*) c FROM products WHERE stock <= 0 AND status='active'")['c'],
    'messages'   => (int) Database::fetch("SELECT COUNT(*) c FROM contact_messages WHERE status='new'")['c'],
];

$recentOrders = Database::fetchAll("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8");
$outOfStockProducts = Database::fetchAll(
    "SELECT p.id, p.name, p.sku, p.stock,
      (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC LIMIT 1) as thumb
     FROM products p WHERE p.stock <= 0 AND p.status='active' ORDER BY p.name LIMIT 10"
);
$lowStockProducts = Database::fetchAll(
    "SELECT p.id, p.name, p.sku, p.stock,
      (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC LIMIT 1) as thumb
     FROM products p WHERE p.stock > 0 AND p.stock <= 5 AND p.status='active' ORDER BY p.stock ASC LIMIT 8"
);
$topProducts = Database::fetchAll(
    "SELECT p.name, p.stock, p.price, p.sale_price, p.id,
      (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC LIMIT 1) as thumb
     FROM products p WHERE p.status='active' ORDER BY p.views DESC LIMIT 5"
);

// ===== Chart data: last 14 days sales =====
$salesChartRows = Database::fetchAll(
    "SELECT DATE(created_at) AS d,
            COUNT(*) AS orders_count,
            COALESCE(SUM(CASE WHEN status NOT IN ('cancelled') THEN total ELSE 0 END),0) AS revenue
     FROM orders
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at)
     ORDER BY d ASC"
);
$chartLabels = [];
$chartRevenue = [];
$chartOrders = [];
$byDate = [];
foreach ($salesChartRows as $r) {
    $byDate[$r['d']] = $r;
}
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('d M', strtotime($d));
    $chartRevenue[] = isset($byDate[$d]) ? (float)$byDate[$d]['revenue'] : 0;
    $chartOrders[]  = isset($byDate[$d]) ? (int)$byDate[$d]['orders_count'] : 0;
}

// Orders by status
$statusRows = Database::fetchAll(
    "SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status ORDER BY cnt DESC"
);
$statusLabels = [];
$statusCounts = [];
$statusColorsMap = [
    'pending'    => '#f59e0b',
    'confirmed'  => '#3b82f6',
    'processing' => '#8b5cf6',
    'shipped'    => '#06b6d4',
    'delivered'  => '#22c55e',
    'cancelled'  => '#ef4444',
];
$statusBg = [];
foreach ($statusRows as $r) {
    $statusLabels[] = ucfirst($r['status']);
    $statusCounts[] = (int)$r['cnt'];
    $statusBg[] = $statusColorsMap[$r['status']] ?? '#94a3b8';
}

// Top products by quantity sold (excl. cancelled)
$topSoldRows = Database::fetchAll(
    "SELECT oi.product_name,
            SUM(oi.quantity) AS qty_sold,
            COALESCE(SUM(oi.total),0) AS revenue
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE o.status NOT IN ('cancelled')
     GROUP BY oi.product_name
     ORDER BY qty_sold DESC
     LIMIT 8"
);
$topSoldLabels = [];
$topSoldQty = [];
$topSoldRev = [];
foreach ($topSoldRows as $r) {
    $name = $r['product_name'];
    if (mb_strlen($name) > 22) $name = mb_substr($name, 0, 20) . '…';
    $topSoldLabels[] = $name;
    $topSoldQty[] = (int)$r['qty_sold'];
    $topSoldRev[] = (float)$r['revenue'];
}

// Payment methods
$payRows = Database::fetchAll(
    "SELECT payment_method, COUNT(*) AS cnt,
            COALESCE(SUM(CASE WHEN status NOT IN ('cancelled') THEN total ELSE 0 END),0) AS amount
     FROM orders
     GROUP BY payment_method
     ORDER BY cnt DESC"
);
$payLabels = [];
$payCounts = [];
$payAmounts = [];
$payColorMap = [
    'cod' => '#f59e0b',
    'bkash' => '#e11d48',
    'nagad' => '#ea580c',
    'rocket' => '#7c3aed',
    'bank' => '#2563eb',
    'card' => '#0891b2',
    'visa' => '#1d4ed8',
    'mastercard' => '#dc2626',
];
$payBg = [];
foreach ($payRows as $r) {
    $payLabels[] = strtoupper($r['payment_method']);
    $payCounts[] = (int)$r['cnt'];
    $payAmounts[] = (float)$r['amount'];
    $payBg[] = $payColorMap[$r['payment_method']] ?? '#64748b';
}

// Date range for export links (this month)
$exportFrom = date('Y-m-01');
$exportTo = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_order_id'], $_POST['quick_status'])) {
    $oid = (int)$_POST['quick_order_id'];
    $st = $_POST['quick_status'];
    $allowed = ['pending','confirmed','processing','shipped','delivered','cancelled'];
    if (in_array($st, $allowed, true)) {
        Database::update('orders', ['status' => $st], 'id=?', [$oid]);
        if ($st === 'delivered') {
            Database::update('orders', ['payment_status'=>'paid'], "id=? AND payment_method='cod'", [$oid]);
        }
        flash('success', 'Order status updated.');
    }
    redirect('/admin/');
}

ob_start();
?>
<style>
.dash-welcome{margin-bottom:20px}
.dash-welcome h2{font-size:1.45rem;margin-bottom:4px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px}
.stat-card{
  background:var(--color-surface);
  border:1px solid var(--color-border);
  border-radius:16px;
  padding:20px;
  display:flex;gap:14px;align-items:flex-start;
  box-shadow:0 4px 20px rgba(0,0,0,.06);
  transition:transform .2s,box-shadow .2s;
}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,.12)}
.stat-link{text-decoration:none;color:inherit;cursor:pointer}
.stat-link:hover{border-color:var(--color-primary)}
.stat-card.stat-danger{
  border-color:color-mix(in srgb,#ef4444 45%,var(--color-border));
  background:color-mix(in srgb,#ef4444 8%,var(--color-surface));
}
.stat-card.stat-warning{
  border-color:color-mix(in srgb,#f59e0b 40%,var(--color-border));
  background:color-mix(in srgb,#f59e0b 8%,var(--color-surface));
}
.stat-icon{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;font-size:1.15rem;flex-shrink:0}
.stat-icon.green{background:color-mix(in srgb,#22c55e 18%,transparent);color:#4ade80}
.stat-icon.blue{background:color-mix(in srgb,#3b82f6 18%,transparent);color:#60a5fa}
.stat-icon.orange{background:color-mix(in srgb,#f97316 18%,transparent);color:#fb923c}
.stat-icon.red{background:color-mix(in srgb,#ef4444 20%,transparent);color:#f87171}
.stat-icon.pink{background:color-mix(in srgb,#ec4899 18%,transparent);color:#f472b6}
.stat-icon.purple{background:color-mix(in srgb,#a855f7 18%,transparent);color:#c084fc}
.stat-info .value{font-family:var(--font-display);font-size:1.55rem;font-weight:800;line-height:1.2}
.stat-info .label{font-size:.8rem;color:var(--color-text-muted);margin-top:2px}
.stat-danger .value{color:#ef4444}
.stat-warning .value{color:#f59e0b}
.quick-actions{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px}
.quick-actions a{padding:11px 16px;border-radius:12px;background:var(--color-surface);border:1px solid var(--color-border);font-weight:600;font-size:.85rem;display:inline-flex;align-items:center;gap:8px}
.quick-actions a:hover{border-color:var(--color-primary);color:var(--color-primary)}
.stock-zero{color:#ef4444;font-weight:700}
.badge-out{background:color-mix(in srgb,#ef4444 20%,transparent);color:#f87171;font-weight:700}
.badge-low{background:color-mix(in srgb,#f59e0b 20%,transparent);color:#fbbf24;font-weight:700}
</style>

<div class="dash-welcome">
  <h2>Welcome back, <?= e(Auth::user()['name']) ?></h2>
  <p class="text-muted">AK store overview — sales, stock & orders</p>
</div>

<!-- STAT CARDS -->
<div class="stats-grid">
  <a href="/admin/orders.php" class="stat-card stat-link">
    <div class="stat-icon green"><i class="fas fa-coins"></i></div>
    <div class="stat-info">
      <div class="value"><?= money($stats['revenue']) ?></div>
      <div class="label">Total Revenue</div>
    </div>
  </a>

  <a href="/admin/orders.php" class="stat-card stat-link">
    <div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div>
    <div class="stat-info">
      <div class="value"><?= money($stats['today']) ?></div>
      <div class="label">Today's Sales</div>
    </div>
  </a>

  <a href="/admin/orders.php?status=pending" class="stat-card stat-link">
    <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
    <div class="stat-info">
      <div class="value"><?= $stats['pending'] ?></div>
      <div class="label">Pending Orders</div>
    </div>
  </a>

  <a href="/admin/orders.php" class="stat-card stat-link">
    <div class="stat-icon purple"><i class="fas fa-box"></i></div>
    <div class="stat-info">
      <div class="value"><?= $stats['orders'] ?></div>
      <div class="label">Total Orders</div>
    </div>
  </a>

  <a href="/admin/products.php" class="stat-card stat-link">
    <div class="stat-icon blue"><i class="fas fa-shirt"></i></div>
    <div class="stat-info">
      <div class="value"><?= $stats['products'] ?></div>
      <div class="label">Active Products</div>
    </div>
  </a>

  <!-- OUT OF STOCK — RED -->
  <a href="/admin/products.php?stock=out" class="stat-card stat-link stat-danger">
    <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
    <div class="stat-info">
      <div class="value"><?= $stats['out_stock'] ?></div>
      <div class="label">Out of Stock</div>
    </div>
  </a>

  <!-- LOW STOCK — ORANGE/WARNING -->
  <a href="/admin/products.php?stock=low" class="stat-card stat-link stat-warning">
    <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="stat-info">
      <div class="value"><?= $stats['low_stock'] ?></div>
      <div class="label">Low Stock (≤5)</div>
    </div>
  </a>

  <a href="/admin/messages.php" class="stat-card stat-link">
    <div class="stat-icon pink"><i class="fas fa-envelope"></i></div>
    <div class="stat-info">
      <div class="value"><?= $stats['messages'] ?></div>
      <div class="label">New Messages</div>
    </div>
  </a>
</div>

<div class="quick-actions">
  <a href="/admin/product-edit.php"><i class="fas fa-plus"></i> Add Product</a>
  <a href="/admin/orders.php"><i class="fas fa-box"></i> Orders</a>
  <a href="/admin/products.php"><i class="fas fa-shirt"></i> Products</a>
  <a href="/admin/categories.php"><i class="fas fa-tags"></i> Categories</a>
  <a href="/admin/settings.php"><i class="fas fa-cog"></i> Settings</a>
  <a href="/" target="_blank"><i class="fas fa-store"></i> View Store</a>
</div>

<!-- ===== CHARTS ===== -->
<div class="charts-grid" style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px;margin-bottom:20px">
  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-chart-line" style="color:var(--color-primary);margin-right:6px"></i> Sales (Last 14 Days)</h3>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="/admin/report-export.php?type=sales&format=excel&from=<?= e($exportFrom) ?>&to=<?= e($exportTo) ?>" class="btn btn-sm btn-outline" title="Download Excel"><i class="fas fa-file-excel"></i> Excel</a>
        <a href="/admin/report-export.php?type=daily_detail&format=pdf&from=<?= e($exportFrom) ?>&to=<?= e($exportTo) ?>" class="btn btn-sm btn-outline" target="_blank" title="Download PDF"><i class="fas fa-file-pdf"></i> PDF</a>
        <a href="/admin/reports.php?report=sales" class="btn btn-sm btn-primary">Full Report</a>
      </div>
    </div>
    <div class="panel-body">
      <canvas id="salesChart" height="110"></canvas>
    </div>
  </div>
  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-chart-pie" style="color:var(--color-primary);margin-right:6px"></i> Orders by Status</h3>
      <div style="display:flex;gap:6px">
        <a href="/admin/report-export.php?type=orders_status&format=excel&from=<?= e($exportFrom) ?>&to=<?= e($exportTo) ?>" class="btn btn-sm btn-outline"><i class="fas fa-file-excel"></i></a>
        <a href="/admin/reports.php?report=orders_status" class="btn btn-sm btn-primary">Report</a>
      </div>
    </div>
    <div class="panel-body" style="display:flex;justify-content:center">
      <div style="max-width:260px;width:100%">
        <canvas id="statusChart"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="charts-grid" style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px;margin-bottom:24px">
  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-fire" style="color:var(--color-primary);margin-right:6px"></i> Top Selling Products</h3>
      <div style="display:flex;gap:6px">
        <a href="/admin/report-export.php?type=top_products&format=excel&from=<?= e($exportFrom) ?>&to=<?= e($exportTo) ?>" class="btn btn-sm btn-outline"><i class="fas fa-file-excel"></i> Excel</a>
        <a href="/admin/reports.php?report=top_products" class="btn btn-sm btn-primary">Report</a>
      </div>
    </div>
    <div class="panel-body">
      <canvas id="topProductsChart" height="140"></canvas>
    </div>
  </div>
  <div class="panel">
    <div class="panel-header">
      <h3><i class="fas fa-wallet" style="color:var(--color-primary);margin-right:6px"></i> Payment Methods</h3>
      <div style="display:flex;gap:6px">
        <a href="/admin/report-export.php?type=payments&format=excel&from=<?= e($exportFrom) ?>&to=<?= e($exportTo) ?>" class="btn btn-sm btn-outline"><i class="fas fa-file-excel"></i></a>
        <a href="/admin/reports.php?report=payments" class="btn btn-sm btn-primary">Report</a>
      </div>
    </div>
    <div class="panel-body" style="display:flex;justify-content:center">
      <div style="max-width:260px;width:100%">
        <canvas id="paymentChart"></canvas>
      </div>
    </div>
  </div>
</div>
<style>
@media(max-width:1000px){
  .charts-grid{grid-template-columns:1fr!important}
}
.panel-header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
</style>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px;align-items:start">
  <!-- Recent Orders -->
  <div class="panel">
    <div class="panel-header">
      <h3>Recent Orders</h3>
      <a href="/admin/orders.php" class="btn btn-sm btn-outline">View all</a>
    </div>
    <div class="panel-body" style="padding:0">
      <?php if (empty($recentOrders)): ?>
      <div style="padding:40px;text-align:center;color:var(--color-text-muted)">No orders yet</div>
      <?php else: ?>
      <table class="data-table">
        <thead>
          <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Update</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td>
            <a href="/admin/order-view.php?id=<?= $o['id'] ?>" style="color:var(--color-primary);font-weight:600"><?= e($o['order_number']) ?></a>
            <div class="text-muted" style="font-size:.75rem"><?= timeAgo($o['created_at']) ?></div>
          </td>
          <td><?= e($o['customer_name']) ?></td>
          <td><?= money($o['total']) ?></td>
          <td>
            <span class="badge badge-<?= $o['status']==='pending'?'warning':($o['status']==='delivered'?'success':($o['status']==='cancelled'?'danger':'info')) ?>">
              <?= e(ucfirst($o['status'])) ?>
            </span>
          </td>
          <td>
            <form method="POST" style="display:flex;gap:4px;align-items:center">
              <input type="hidden" name="quick_order_id" value="<?= $o['id'] ?>">
              <select name="quick_status" class="form-control" style="padding:4px 8px;font-size:.75rem;width:110px">
                <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm btn-primary" style="padding:4px 8px">OK</button>
            </form>
          </td>
          <td><a href="/admin/order-view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Out of Stock + Low Stock panels -->
  <div>
    <div class="panel" style="border-color:color-mix(in srgb,#ef4444 35%,var(--color-border))">
      <div class="panel-header">
        <h3 style="color:#ef4444"><i class="fas fa-times-circle"></i> Out of Stock</h3>
        <span class="badge badge-out"><?= $stats['out_stock'] ?></span>
      </div>
      <div class="panel-body" style="padding:0">
        <?php if (empty($outOfStockProducts)): ?>
        <div style="padding:24px;text-align:center;color:var(--color-text-muted);font-size:.9rem">All products in stock ✓</div>
        <?php else: ?>
        <table class="data-table">
          <tbody>
          <?php foreach ($outOfStockProducts as $p):
            $th = ProductImage::productThumb($p);
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <?php if ($th): ?>
                <img src="<?= e($th) ?>" alt="" style="width:36px;height:44px;object-fit:cover;border-radius:6px;opacity:.7">
                <?php else: ?>
                <div style="width:36px;height:44px;border-radius:6px;background:color-mix(in srgb,#ef4444 15%,var(--color-border))"></div>
                <?php endif; ?>
                <div>
                  <a href="/admin/product-edit.php?id=<?= $p['id'] ?>" style="font-weight:600;font-size:.85rem"><?= e(truncate($p['name'], 30)) ?></a>
                  <div class="stock-zero" style="font-size:.75rem">Stock: 0 — Out of Stock</div>
                </div>
              </div>
            </td>
            <td style="text-align:right">
              <a href="/admin/product-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Restock</a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel" style="margin-top:16px">
      <div class="panel-header">
        <h3 style="color:#f59e0b"><i class="fas fa-exclamation-triangle"></i> Low Stock</h3>
        <span class="badge badge-low"><?= $stats['low_stock'] ?></span>
      </div>
      <div class="panel-body" style="padding:0">
        <?php if (empty($lowStockProducts)): ?>
        <div style="padding:24px;text-align:center;color:var(--color-text-muted);font-size:.9rem">No low stock items</div>
        <?php else: ?>
        <table class="data-table">
          <tbody>
          <?php foreach ($lowStockProducts as $p):
            $th = ProductImage::productThumb($p);
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <?php if ($th): ?>
                <img src="<?= e($th) ?>" alt="" style="width:36px;height:44px;object-fit:cover;border-radius:6px">
                <?php else: ?>
                <div style="width:36px;height:44px;border-radius:6px;background:var(--color-border)"></div>
                <?php endif; ?>
                <div>
                  <a href="/admin/product-edit.php?id=<?= $p['id'] ?>" style="font-weight:600;font-size:.85rem"><?= e(truncate($p['name'], 30)) ?></a>
                  <div style="font-size:.75rem;color:#f59e0b;font-weight:600">Only <?= (int)$p['stock'] ?> left</div>
                </div>
              </div>
            </td>
            <td style="text-align:right">
              <a href="/admin/product-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:1000px){div[style*="grid-template-columns:1.4fr"]{grid-template-columns:1fr!important}}</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  // Sales Line Chart
  var salesCtx = document.getElementById('salesChart');
  if (salesCtx) {
    new Chart(salesCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
          {
            label: 'Revenue',
            data: <?= json_encode($chartRevenue) ?>,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78,115,223,0.12)',
            borderWidth: 2.5,
            tension: 0.35,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 6,
            yAxisID: 'y'
          },
          {
            label: 'Orders',
            data: <?= json_encode($chartOrders) ?>,
            borderColor: '#1cc88a',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [5, 4],
            tension: 0.35,
            fill: false,
            pointRadius: 3,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top', labels: { boxWidth: 12, usePointStyle: true } },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                if (ctx.dataset.label === 'Revenue') {
                  return ' Revenue: ৳' + Number(ctx.raw).toLocaleString();
                }
                return ' Orders: ' + ctx.raw;
              }
            }
          }
        },
        scales: {
          y: {
            type: 'linear',
            position: 'left',
            beginAtZero: true,
            ticks: {
              callback: function(v) { return '৳' + v.toLocaleString(); }
            },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          y1: {
            type: 'linear',
            position: 'right',
            beginAtZero: true,
            grid: { drawOnChartArea: false },
            ticks: { stepSize: 1 }
          },
          x: { grid: { display: false } }
        }
      }
    });
  }

  // Orders Status Doughnut
  var statusCtx = document.getElementById('statusChart');
  if (statusCtx) {
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($statusLabels) ?>,
        datasets: [{
          data: <?= json_encode($statusCounts) ?>,
          backgroundColor: <?= json_encode($statusBg) ?>,
          borderWidth: 0,
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        cutout: '62%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 12, padding: 14, usePointStyle: true }
          }
        }
      }
    });
  }

  // Top Products Bar Chart
  var topCtx = document.getElementById('topProductsChart');
  if (topCtx) {
    new Chart(topCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($topSoldLabels) ?>,
        datasets: [{
          label: 'Units Sold',
          data: <?= json_encode($topSoldQty) ?>,
          backgroundColor: 'rgba(78,115,223,0.75)',
          borderRadius: 6,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        indexAxis: 'y',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              afterLabel: function(ctx) {
                var revs = <?= json_encode($topSoldRev) ?>;
                return 'Revenue: ৳' + Number(revs[ctx.dataIndex] || 0).toLocaleString();
              }
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          y: { grid: { display: false } }
        }
      }
    });
  }

  // Payment Methods Doughnut
  var payCtx = document.getElementById('paymentChart');
  if (payCtx) {
    new Chart(payCtx, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($payLabels) ?>,
        datasets: [{
          data: <?= json_encode($payCounts) ?>,
          backgroundColor: <?= json_encode($payBg) ?>,
          borderWidth: 0,
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        cutout: '58%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 12, padding: 12, usePointStyle: true }
          },
          tooltip: {
            callbacks: {
              afterLabel: function(ctx) {
                var amts = <?= json_encode($payAmounts) ?>;
                return 'Amount: ৳' + Number(amts[ctx.dataIndex] || 0).toLocaleString();
              }
            }
          }
        }
      }
    });
  }
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/app/views/layouts/admin.php';