<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Products';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $imgs = ProductImage::forProduct($pid);
    foreach ($imgs as $img) {
        ProductImage::delete($img['id'], $pid);
    }
    Database::delete('products', 'id=?', [$pid]);
    flash('success', 'Product deleted.');
    redirect('/admin/products.php');
}

if (isset($_GET['toggle_featured']) && is_numeric($_GET['toggle_featured'])) {
    $pid = (int)$_GET['toggle_featured'];
    $row = Database::fetch("SELECT is_featured FROM products WHERE id=?", array($pid));
    if ($row) {
        Database::update('products', array('is_featured' => $row['is_featured'] ? 0 : 1), 'id=?', array($pid));
        flash('success', 'Featured flag updated.');
    }
    redirect('/admin/products.php');
}
if (isset($_GET['toggle_new']) && is_numeric($_GET['toggle_new'])) {
    $pid = (int)$_GET['toggle_new'];
    $row = Database::fetch("SELECT is_new FROM products WHERE id=?", array($pid));
    if ($row) {
        Database::update('products', array('is_new' => $row['is_new'] ? 0 : 1), 'id=?', array($pid));
        flash('success', 'New Arrival flag updated.');
    }
    redirect('/admin/products.php');
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $p = Database::fetch("SELECT status FROM products WHERE id=?", [(int)$_GET['toggle']]);
    if ($p) {
        $n = $p['status'] === 'active' ? 'inactive' : 'active';
        Database::update('products', ['status' => $n], 'id=?', [(int)$_GET['toggle']]);
        flash('success', 'Status updated.');
    }
    redirect('/admin/products.php');
}

$stockFilter = isset($_GET['stock']) ? $_GET['stock'] : '';
$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$where = ['1=1'];
$params = [];

if ($stockFilter === 'out') {
    $where[] = 'p.stock <= 0';
    $pageTitle = 'Out of Stock Products';
} elseif ($stockFilter === 'low') {
    $where[] = 'p.stock > 0 AND p.stock <= 5';
    $pageTitle = 'Low Stock Products';
}

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.slug LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$sqlWhere = implode(' AND ', $where);
$products = Database::fetchAll(
    "SELECT p.*, c.name as cat_name,
      (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC, sort_order LIMIT 1) as thumb
     FROM products p
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE $sqlWhere
     ORDER BY p.id DESC",
    $params
);
ob_start();
?>
<style>
.prod-thumb{width:48px;height:60px;border-radius:8px;object-fit:cover;background:var(--color-bg);border:1px solid var(--color-border)}
.prod-thumb-ph{width:48px;height:60px;border-radius:8px;background:linear-gradient(145deg,var(--color-primary),var(--color-secondary));opacity:.5;display:grid;place-items:center;font-size:.9rem;color:#fff}
.admin-search-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:16px}
.admin-search-bar .form-control{max-width:280px}
</style>

<div class="admin-search-bar">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;flex:1;align-items:center">
    <?php if ($stockFilter): ?><input type="hidden" name="stock" value="<?= e($stockFilter) ?>"><?php endif; ?>
    <input type="search" name="q" class="form-control" placeholder="Search name, SKU, category..." value="<?= e($q) ?>">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
    <?php if ($q !== ''): ?>
    <a href="/admin/products.php<?= $stockFilter ? '?stock='.urlencode($stockFilter) : '' ?>" class="btn btn-outline btn-sm">Clear</a>
    <?php endif; ?>
  </form>
  <span class="text-muted"><?= count($products) ?> products</span>
  <a href="/admin/product-edit.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
  <a href="/admin/report-export.php?type=products&format=excel" class="btn btn-outline"><i class="fas fa-file-excel"></i> Excel</a>
  <a href="/admin/report-export.php?type=products&format=csv" class="btn btn-outline">CSV</a>
  <a href="/admin/report-export.php?type=products&format=pdf" class="btn btn-outline" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a>
</div>

<div class="panel">
  <div class="panel-header"><h3><?= e($pageTitle) ?></h3></div>
  <div class="panel-body" style="padding:0">
    <div style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th></th>
          <th>Product</th>
          <th>SKU</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($products)): ?>
      <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--color-text-muted)">No products found<?= $q ? ' for “'.e($q).'”' : '' ?>.</td></tr>
      <?php endif; ?>
      <?php foreach ($products as $p):
        $thumb = !empty($p['thumb']) ? ProductImage::url($p['thumb']) : (!empty($p['image']) ? ProductImage::url($p['image']) : null);
      ?>
      <tr>
        <td>
          <?php if ($thumb): ?>
          <img src="<?= e($thumb) ?>" class="prod-thumb" alt="">
          <?php else: ?>
          <div class="prod-thumb-ph"><i class="fas fa-shirt"></i></div>
          <?php endif; ?>
        </td>
        <td>
          <strong><?= e($p['name']) ?></strong>
          <?php if ($p['is_featured']): ?> <span class="badge badge-purple">Featured</span><?php endif; ?>
          <?php if ($p['is_new']): ?> <span class="badge badge-info">New</span><?php endif; ?>
        </td>
        <td><code><?= e($p['sku'] ?: '—') ?></code></td>
        <td><?= e(isset($p['cat_name']) ? $p['cat_name'] : '—') ?></td>
        <td>
          <?= money(productPrice($p)) ?>
          <?php if (hasSale($p)): ?>
          <span class="text-muted" style="text-decoration:line-through;font-size:.8rem"><?= money($p['price']) ?></span>
          <?php endif; ?>
        </td>
        <td><?php if ((int)$p['stock'] <= 0): ?>
          <span class="badge" style="background:color-mix(in srgb,#ef4444 20%,transparent);color:#f87171;font-weight:700">Out of Stock</span>
          <?php elseif ((int)$p['stock'] <= 5): ?>
          <span class="badge" style="background:color-mix(in srgb,#f59e0b 20%,transparent);color:#fbbf24;font-weight:700"><?= (int)$p['stock'] ?> left</span>
          <?php else: ?>
          <?= (int)$p['stock'] ?>
          <?php endif; ?></td>
        <td><span class="badge badge-<?= $p['status']==='active'?'success':'warning' ?>"><?= e(ucfirst($p['status'])) ?></span></td>
        <td style="white-space:nowrap">
          <a href="/admin/product-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline" title="Edit"><i class="fas fa-pen"></i> Edit</a>
          <a href="?toggle_featured=<?= $p['id'] ?>" class="btn btn-sm btn-outline" title="Toggle Featured" style="<?= $p['is_featured']?'opacity:1':'opacity:.45' ?>">★</a>
          <a href="?toggle_new=<?= $p['id'] ?>" class="btn btn-sm btn-outline" title="Toggle New Arrival" style="<?= $p['is_new']?'opacity:1':'opacity:.45' ?>">N</a>
          <a href="?toggle=<?= $p['id'] ?>" class="btn btn-sm btn-outline" title="Toggle status">⚡</a>
          <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm" style="color:#f87171" onclick="return confirm('Delete this product and all images?')" title="Delete"><i class="fas fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/app/views/layouts/admin.php';
