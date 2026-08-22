<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Categories';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $cid = (int)$_GET['delete'];
    $cnt = (int)Database::fetch("SELECT COUNT(*) c FROM products WHERE category_id=?", [$cid])['c'];
    if ($cnt > 0) {
        flash('error', "Cannot delete: $cnt product(s) use this category. Reassign them first.");
    } else {
        Database::delete('categories', 'id=?', [$cid]);
        flash('success', 'Category deleted.');
    }
    redirect('/admin/categories.php');
}
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $c = Database::fetch("SELECT status FROM categories WHERE id=?", [(int)$_GET['toggle']]);
    if ($c) {
        Database::update('categories', ['status' => $c['status']==='active'?'inactive':'active'], 'id=?', [(int)$_GET['toggle']]);
        flash('success', 'Status updated.');
    }
    redirect('/admin/categories.php');
}

$cats = Database::fetchAll("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id=c.id) as product_count FROM categories c ORDER BY sort_order, id");
ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <span class="text-muted"><?= count($cats) ?> categories</span>
  <a href="/admin/category-edit.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Category</a>
</div>
<div class="panel">
  <div class="panel-header"><h3>Categories</h3></div>
  <div class="panel-body" style="padding:0">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Slug</th><th>Products</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($cats as $c): ?>
      <tr>
        <td><strong><?= e($c['name']) ?></strong>
          <?php if ($c['description']): ?><div class="text-muted" style="font-size:.8rem"><?= e(truncate($c['description'],50)) ?></div><?php endif; ?>
        </td>
        <td><code><?= e($c['slug']) ?></code></td>
        <td><?= (int)$c['product_count'] ?></td>
        <td><?= (int)$c['sort_order'] ?></td>
        <td><span class="badge badge-<?= $c['status']==='active'?'success':'warning' ?>"><?= e(ucfirst($c['status'])) ?></span></td>
        <td style="white-space:nowrap">
          <a href="/admin/category-edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-pen"></i> Edit</a>
          <a href="?toggle=<?= $c['id'] ?>" class="btn btn-sm btn-outline">⚡</a>
          <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm" style="color:#f87171" onclick="return confirm('Delete category?')"><i class="fas fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $content=ob_get_clean(); require dirname(__DIR__,2).'/app/views/layouts/admin.php'; ?>
