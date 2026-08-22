<?php
require_once dirname(dirname(__DIR__)) . '/app/bootstrap.php';
Auth::requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$banner = null;
if ($id > 0) {
    $banner = Database::fetch('SELECT * FROM banners WHERE id=?', array($id));
}
$pageTitle = $banner ? 'Edit Banner' : 'Add Banner';

function banner_upload($field) {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['size'] > 4 * 1024 * 1024) {
        flash('error', 'Image must be under 4 MB.');
        return false;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $map = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
    if (!isset($map[$mime])) {
        flash('error', 'Use JPG, PNG or WebP.');
        return false;
    }
    $dir = dirname(dirname(__DIR__)) . '/public/uploads/banners';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = 'banner_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $map[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        flash('error', 'Upload failed.');
        return false;
    }
    return 'uploads/banners/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = array(
        'title' => trim(isset($_POST['title']) ? $_POST['title'] : ''),
        'subtitle' => trim(isset($_POST['subtitle']) ? $_POST['subtitle'] : ''),
        'description' => trim(isset($_POST['description']) ? $_POST['description'] : ''),
        'btn_text' => trim(isset($_POST['btn_text']) ? $_POST['btn_text'] : 'Shop Now'),
        'btn_link' => trim(isset($_POST['btn_link']) ? $_POST['btn_link'] : '/shop.php'),
        'btn2_text' => trim(isset($_POST['btn2_text']) ? $_POST['btn2_text'] : ''),
        'btn2_link' => trim(isset($_POST['btn2_link']) ? $_POST['btn2_link'] : ''),
        'badge_text' => trim(isset($_POST['badge_text']) ? $_POST['badge_text'] : ''),
        'text_align' => in_array(isset($_POST['text_align']) ? $_POST['text_align'] : '', array('left','center','right'), true) ? $_POST['text_align'] : 'left',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'sort_order' => (int)(isset($_POST['sort_order']) ? $_POST['sort_order'] : 0),
    );
    if ($data['title'] === '') {
        flash('error', 'Title is required.');
        redirect($id ? '/admin/banner-edit.php?id=' . $id : '/admin/banner-edit.php');
    }

    $img = banner_upload('image');
    if ($img === false) {
        redirect($id ? '/admin/banner-edit.php?id=' . $id : '/admin/banner-edit.php');
    }
    $bg = banner_upload('bg_image');
    if ($bg === false) {
        redirect($id ? '/admin/banner-edit.php?id=' . $id : '/admin/banner-edit.php');
    }
    if ($img) {
        $data['image'] = $img;
    }
    if ($bg) {
        $data['bg_image'] = $bg;
    }

    if ($banner) {
        Database::update('banners', $data, 'id=?', array($id));
        flash('success', 'Banner updated.');
        redirect('/admin/banners.php');
    } else {
        Database::insert('banners', $data);
        flash('success', 'Banner created.');
        redirect('/admin/banners.php');
    }
}

$b = $banner ?: array(
    'title' => '', 'subtitle' => '', 'description' => '',
    'btn_text' => 'Shop Now', 'btn_link' => '/shop.php',
    'btn2_text' => 'New Arrivals', 'btn2_link' => '/shop.php?filter=new',
    'badge_text' => 'NEW SEASON 2026', 'text_align' => 'left',
    'is_active' => 1, 'sort_order' => 0, 'image' => '', 'bg_image' => '',
);

ob_start();
?>
<div style="margin-bottom:16px"><a href="/admin/banners.php" class="btn btn-sm btn-outline"><i class="fas fa-arrow-left"></i> All Banners</a></div>
<form method="POST" enctype="multipart/form-data" style="max-width:720px">
  <div class="panel">
    <div class="panel-header"><h3>Content</h3></div>
    <div class="panel-body">
      <div class="form-group">
        <label>Badge text (small top line)</label>
        <input type="text" name="badge_text" class="form-control" value="<?= e($b['badge_text']) ?>" placeholder="WINTER 2026">
      </div>
      <div class="form-group">
        <label>Title *</label>
        <input type="text" name="title" class="form-control" required value="<?= e($b['title']) ?>" placeholder="Define Your Style with AK">
      </div>
      <div class="form-group">
        <label>Subtitle</label>
        <input type="text" name="subtitle" class="form-control" value="<?= e($b['subtitle']) ?>" placeholder="Winter Collection">
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($b['description']) ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Button 1 text</label><input type="text" name="btn_text" class="form-control" value="<?= e($b['btn_text']) ?>"></div>
        <div class="form-group"><label>Button 1 link</label><input type="text" name="btn_link" class="form-control" value="<?= e($b['btn_link']) ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Button 2 text</label><input type="text" name="btn2_text" class="form-control" value="<?= e($b['btn2_text']) ?>"></div>
        <div class="form-group"><label>Button 2 link</label><input type="text" name="btn2_link" class="form-control" value="<?= e($b['btn2_link']) ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Layout (image side)</label>
          <select name="text_align" class="form-control">
            <option value="left" <?= ($b['text_align']??'left')==='left'?'selected':'' ?>>Image LEFT · Text right</option>
            <option value="center" <?= ($b['text_align']??'')==='center'?'selected':'' ?>>Image LEFT · Text right</option>
            <option value="right" <?= ($b['text_align']??'')==='right'?'selected':'' ?>>Image RIGHT · Text left</option>
          </select>
        </div>
        <div class="form-group">
          <label>Sort order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int)$b['sort_order'] ?>">
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-weight:600">
        <input type="checkbox" name="is_active" value="1" <?= !empty($b['is_active'])?'checked':'' ?>> Active on homepage
      </label>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><h3>Images</h3></div>
    <div class="panel-body">
      <div class="form-group">
        <label>Main / product image (right side)</label>
        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
        <?php if (!empty($b['image'])): ?>
        <img src="/<?= e(ltrim($b['image'],'/')) ?>" alt="" style="margin-top:10px;max-width:200px;border-radius:12px">
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Background banner image (full hero background)</label>
        <input type="file" name="bg_image" class="form-control" accept="image/jpeg,image/png,image/webp">
        <small class="text-muted">Optional — e.g. winter scene. Soft blur overlay is applied automatically.</small>
        <?php if (!empty($b['bg_image'])): ?>
        <img src="/<?= e(ltrim($b['bg_image'],'/')) ?>" alt="" style="margin-top:10px;max-width:280px;border-radius:12px">
        <?php endif; ?>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save Banner</button>
</form>
<?php
$content = ob_get_clean();
require dirname(dirname(__DIR__)) . '/app/views/layouts/admin.php';