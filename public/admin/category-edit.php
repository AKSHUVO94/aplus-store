<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cat = null;
if ($id > 0) {
    $cat = Database::fetch("SELECT * FROM categories WHERE id=?", [$id]);
    if (!$cat) { flash('error','Category not found'); redirect('/admin/categories.php'); }
}
$pageTitle = $cat ? 'Edit Category' : 'Add Category';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $slug = trim(isset($_POST['slug']) ? $_POST['slug'] : '');
    if ($slug === '' && $name !== '') $slug = slugify($name);
    $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
    $sort_order = (int)(isset($_POST['sort_order']) ? $_POST['sort_order'] : 0);
    $status = isset($_POST['status']) && $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if ($name === '') $errors[] = 'Name is required.';
    if ($slug) {
        $ex = Database::fetch("SELECT id FROM categories WHERE slug=? AND id!=?", [$slug, $id]);
        if ($ex) $slug = $slug . '-' . time();
    }

    if (empty($errors)) {
        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description ?: null,
            'sort_order' => $sort_order,
            'status' => $status,
        ];
        if ($cat) {
            Database::update('categories', $data, 'id=?', [$id]);
            flash('success', 'Category updated.');
        } else {
            $id = Database::insert('categories', $data);
            flash('success', 'Category created.');
        }
        redirect('/admin/categories.php');
    }
}

$v = function($k, $d='') use ($cat) {
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST[$k])) return $_POST[$k];
    if ($cat && isset($cat[$k])) return $cat[$k];
    return $d;
};
ob_start();
?>
<div style="margin-bottom:16px"><a href="/admin/categories.php" class="btn btn-sm btn-outline"><i class="fas fa-arrow-left"></i> Back</a></div>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach($errors as $e) echo e($e).'<br>'; ?></div><?php endif; ?>
<form method="POST">
<div class="panel" style="max-width:640px">
  <div class="panel-header"><h3><?= e($pageTitle) ?></h3></div>
  <div class="panel-body">
    <div class="form-group"><label>Name *</label><input type="text" name="name" class="form-control" required value="<?= e($v('name')) ?>"></div>
    <div class="form-group"><label>Slug</label><input type="text" name="slug" class="form-control" value="<?= e($v('slug')) ?>" placeholder="auto if empty"></div>
    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"><?= e($v('description')) ?></textarea></div>
    <div class="form-row">
      <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?= e($v('sort_order','0')) ?>"></div>
      <div class="form-group"><label>Status</label>
        <select name="status" class="form-control">
          <option value="active" <?= $v('status','active')==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $v('status')==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $cat ? 'Update Category' : 'Create Category' ?></button>
  </div>
</div>
</form>
<?php $content=ob_get_clean(); require dirname(__DIR__,2).'/app/views/layouts/admin.php'; ?>
