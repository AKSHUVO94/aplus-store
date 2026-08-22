<?php
require_once dirname(dirname(__DIR__)) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Banners / Slider';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $b = Database::fetch('SELECT * FROM banners WHERE id=?', array($id));
    if ($b) {
        foreach (array('image', 'bg_image') as $col) {
            if (!empty($b[$col])) {
                $path = dirname(dirname(__DIR__)) . '/public/' . ltrim($b[$col], '/');
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        Database::delete('banners', 'id=?', array($id));
        flash('success', 'Banner deleted.');
    }
    redirect('/admin/banners.php');
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $b = Database::fetch('SELECT is_active FROM banners WHERE id=?', array((int)$_GET['toggle']));
    if ($b) {
        Database::update('banners', array('is_active' => $b['is_active'] ? 0 : 1), 'id=?', array((int)$_GET['toggle']));
        flash('success', 'Status updated.');
    }
    redirect('/admin/banners.php');
}

$banners = array();
try {
    $banners = Database::fetchAll('SELECT * FROM banners ORDER BY sort_order ASC, id DESC');
} catch (Exception $e) {
    flash('error', 'Run database/upgrade_banners.sql first.');
}

ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <p class="text-muted" style="margin:0">Homepage hero slider — upload images &amp; edit all text from here.</p>
  <a href="/admin/banner-edit.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Banner</a>
</div>

<div class="panel">
  <div class="panel-header"><h3>All Banners</h3></div>
  <div class="panel-body" style="padding:0;overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Preview</th>
          <th>Title</th>
          <th>Badge</th>
          <th>Order</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($banners)): ?>
        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--color-text-muted)">No banners yet. <a href="/admin/banner-edit.php">Add one</a></td></tr>
      <?php endif; ?>
      <?php foreach ($banners as $b):
        $img = !empty($b['image']) ? '/' . ltrim($b['image'], '/') : (!empty($b['bg_image']) ? '/' . ltrim($b['bg_image'], '/') : '');
      ?>
        <tr>
          <td>
            <?php if ($img): ?>
            <img src="<?= e($img) ?>" alt="" style="width:72px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--color-border)">
            <?php else: ?>
            <div style="width:72px;height:48px;border-radius:8px;background:var(--color-border);opacity:.5"></div>
            <?php endif; ?>
          </td>
          <td><strong><?= e($b['title']) ?></strong><br><span class="text-muted" style="font-size:.8rem"><?= e(truncate($b['description'] ?: '', 50)) ?></span></td>
          <td><?= e($b['badge_text'] ?: '—') ?></td>
          <td><?= (int)$b['sort_order'] ?></td>
          <td>
            <a href="?toggle=<?= (int)$b['id'] ?>">
              <span class="badge badge-<?= $b['is_active'] ? 'success' : 'warning' ?>"><?= $b['is_active'] ? 'Active' : 'Hidden' ?></span>
            </a>
          </td>
          <td style="white-space:nowrap">
            <a href="/admin/banner-edit.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-pen"></i> Edit</a>
            <a href="?delete=<?= (int)$b['id'] ?>" class="btn btn-sm" style="color:#f87171" onclick="return confirm('Delete this banner?');"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$content = ob_get_clean();
require dirname(dirname(__DIR__)) . '/app/views/layouts/admin.php';
