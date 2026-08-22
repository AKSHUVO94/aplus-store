<?php
Auth::requireAdmin();
$user = Auth::user();
$pageTitle = isset($pageTitle) ? $pageTitle : 'Dashboard';

$pendingOrders = 0;
$newMessages = 0;
try {
    $pendingOrders = (int) Database::fetch("SELECT COUNT(*) c FROM orders WHERE status='pending'")['c'];
    $newMessages = (int) Database::fetch("SELECT COUNT(*) c FROM contact_messages WHERE status='new'")['c'];
} catch (Exception $e) {}
$notifCount = $pendingOrders + $newMessages;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — AK Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<style><?= Theme::cssVariables() ?></style>
<style>
.admin-topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.topbar-right{display:flex;align-items:center;gap:14px}
.notif-wrap{position:relative}
.notif-btn{width:42px;height:42px;border-radius:12px;border:1px solid var(--color-border);background:var(--color-surface);display:grid;place-items:center;cursor:pointer;position:relative;color:var(--color-text)}
.notif-btn:hover{border-color:var(--color-primary);color:var(--color-primary)}
.notif-badge{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;display:grid;place-items:center;border:2px solid var(--color-bg)}
.notif-badge:empty,.notif-badge[data-count="0"]{display:none}
.notif-panel{position:absolute;right:0;top:calc(100% + 10px);width:360px;max-width:92vw;background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.2);z-index:200;opacity:0;visibility:hidden;transform:translateY(8px);transition:all .2s}
.notif-wrap.open .notif-panel{opacity:1;visibility:visible;transform:translateY(0)}
.notif-panel-head{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--color-border)}
.notif-panel-head strong{font-size:.95rem}
.notif-list{max-height:360px;overflow-y:auto}
.notif-item{display:block;padding:12px 16px;border-bottom:1px solid var(--color-border);color:inherit;transition:background .15s}
.notif-item:hover{background:color-mix(in srgb,var(--color-primary) 8%,transparent)}
.notif-item .n-title{font-weight:600;font-size:.875rem;margin-bottom:2px}
.notif-item .n-meta{font-size:.75rem;color:var(--color-text-muted)}
.notif-item.is-new .n-title{color:var(--color-primary)}
.notif-empty{padding:28px;text-align:center;color:var(--color-text-muted);font-size:.9rem}
.notif-footer{padding:12px 16px;text-align:center;border-top:1px solid var(--color-border)}
.sidebar-badge{margin-left:auto;background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:999px;min-width:18px;text-align:center}
.nav-link{display:flex;align-items:center;gap:10px}
</style>
</head>
<body class="admin-body">
<aside class="admin-sidebar">
  <div class="sidebar-brand">A<span>K</span> Admin</div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Main</div>
    <a href="/admin/" class="nav-link <?= activeClass('/admin/') ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>
    <div class="nav-section-title">Catalog</div>
    <a href="/admin/products.php" class="nav-link <?= activeClass('/admin/products') ?>"><i class="fas fa-shirt"></i> Products</a>
    <a href="/admin/categories.php" class="nav-link <?= activeClass('/admin/categories') ?>"><i class="fas fa-tags"></i> Categories</a>
    <div class="nav-section-title">Sales</div>
    <a href="/admin/reports.php" class="nav-link <?= activeClass('/admin/reports') ?>"><i class="fas fa-chart-bar"></i> Reports</a>
    <a href="/admin/orders.php" class="nav-link <?= activeClass('/admin/orders') ?>">
      <i class="fas fa-box"></i> Orders
      <?php if ($pendingOrders > 0): ?><span class="sidebar-badge" id="sidebar-order-badge"><?= $pendingOrders ?></span><?php endif; ?>
    </a>
    <a href="/admin/customers.php" class="nav-link <?= activeClass('/admin/customers') ?>"><i class="fas fa-user-friends"></i> Customers</a>
    <div class="nav-section-title">System</div>
    <a href="/admin/banners.php" class="nav-link <?= activeClass('/admin/banners') ?>"><i class="fas fa-images"></i> Banners</a>
    <a href="/admin/themes.php" class="nav-link <?= activeClass('/admin/themes') ?>"><i class="fas fa-palette"></i> Themes</a>
    <a href="/admin/settings.php" class="nav-link <?= activeClass('/admin/settings') ?>"><i class="fas fa-cog"></i> Settings</a>
    <a href="/admin/messages.php" class="nav-link <?= activeClass('/admin/messages') ?>">
      <i class="fas fa-envelope"></i> Messages
      <?php if ($newMessages > 0): ?><span class="sidebar-badge"><?= $newMessages ?></span><?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="/" class="nav-link" target="_blank"><i class="fas fa-external-link-alt"></i> View Store</a>
    <a href="/admin/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
</aside>
<div class="admin-main">
  <header class="admin-topbar">
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <div class="topbar-right">
      <div class="notif-wrap" id="notif-wrap">
        <button type="button" class="notif-btn" id="notif-btn" aria-label="Notifications">
          <i class="fas fa-bell"></i>
          <span class="notif-badge" id="notif-badge" data-count="<?= (int)$notifCount ?>"><?= $notifCount > 0 ? (int)$notifCount : '' ?></span>
        </button>
        <div class="notif-panel" id="notif-panel">
          <div class="notif-panel-head">
            <strong>Notifications</strong>
            <a href="/admin/orders.php?status=pending" class="btn btn-sm btn-outline">Pending</a>
          </div>
          <div class="notif-list" id="notif-list">
            <div class="notif-empty">Loading…</div>
          </div>
          <div class="notif-footer">
            <a href="/admin/reports.php" class="nav-link <?= activeClass('/admin/reports') ?>"><i class="fas fa-chart-bar"></i> Reports</a>
    <a href="/admin/orders.php" style="font-size:.85rem;font-weight:600;color:var(--color-primary)">View all orders</a>
          </div>
        </div>
      </div>
      <div style="font-size:.875rem"><?= e($user['name']) ?> · <span class="text-muted"><?= e($user['role_name']) ?></span></div>
    </div>
  </header>
  <div class="admin-content">
    <?php if ($m = flash('success')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
    <?php if ($m = flash('error')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>
    <?= isset($content) ? $content : '' ?>
  </div>
</div>
<script>
(function(){
  var wrap = document.getElementById('notif-wrap');
  var btn = document.getElementById('notif-btn');
  var list = document.getElementById('notif-list');
  var badge = document.getElementById('notif-badge');
  if (!btn) return;

  function render(data) {
    var count = data.total_badge || 0;
    badge.setAttribute('data-count', count);
    badge.textContent = count > 0 ? count : '';
    var side = document.getElementById('sidebar-order-badge');
    if (side) {
      if (data.pending_orders > 0) { side.textContent = data.pending_orders; side.style.display = ''; }
      else { side.style.display = 'none'; }
    }
    if (!data.orders || !data.orders.length) {
      list.innerHTML = '<div class="notif-empty">No recent orders</div>';
      return;
    }
    var html = '';
    data.orders.forEach(function(o){
      html += '<a class="notif-item' + (o.is_new ? ' is-new' : '') + '" href="' + o.url + '">' +
        '<div class="n-title">' + (o.is_new ? '🛒 New order ' : 'Order ') + o.order_number + '</div>' +
        '<div class="n-meta">' + o.customer_name + ' · ' + o.total + ' · ' + o.status + ' · ' + o.time + '</div>' +
      '</a>';
    });
    list.innerHTML = html;
  }

  function load() {
    fetch('/admin/api-notifications.php', { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(render)
      .catch(function(){ list.innerHTML = '<div class="notif-empty">Could not load</div>'; });
  }

  btn.addEventListener('click', function(e){
    e.stopPropagation();
    wrap.classList.toggle('open');
    if (wrap.classList.contains('open')) load();
  });
  document.addEventListener('click', function(){ wrap.classList.remove('open'); });
  var panel = document.getElementById('notif-panel');
  if (panel) panel.addEventListener('click', function(e){ e.stopPropagation(); });

  // Auto-refresh badge every 30s (FB-style live feel)
  load();
  setInterval(load, 30000);
})();
</script>
</body>
</html>