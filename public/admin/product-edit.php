<?php
/**
 * AK Admin — Professional Product Create / Edit with Multi-Image Upload
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$images = [];

if ($id > 0) {
    $product = Database::fetch("SELECT * FROM products WHERE id=?", [$id]);
    if (!$product) {
        flash('error', 'Product not found.');
        redirect('/admin/products.php');
    }
    $images = ProductImage::forProduct($id);
}

$pageTitle = $product ? 'Edit Product' : 'Add Product';
$categories = Database::fetchAll("SELECT * FROM categories WHERE status='active' ORDER BY sort_order");
$errors = [];

// Handle image-only actions (primary / delete)
if ($product && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_action']) && !isset($_POST['save_product'])) {
    $imgId = (int)(isset($_POST['image_id']) ? $_POST['image_id'] : 0);
    if ($_POST['image_action'] === 'primary' && $imgId) {
        ProductImage::setPrimary($imgId, $id);
        flash('success', 'Primary image updated.');
    }
    if ($_POST['image_action'] === 'delete' && $imgId) {
        ProductImage::delete($imgId, $id);
        flash('success', 'Image deleted.');
    }
    if ($_POST['image_action'] === 'set_color' && $imgId) {
        $color = trim(isset($_POST['image_color']) ? $_POST['image_color'] : '');
        try {
            Database::update('product_images', array('color' => ($color !== '' ? $color : null)), 'id = ? AND product_id = ?', array($imgId, $id));
            flash('success', 'Image color label saved.');
        } catch (Exception $e) {
            flash('error', 'Run database/upgrade_image_color.sql first, then try again.');
        }
    }
    redirect('/admin/product-edit.php?id=' . $id);
}

// Save product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $slug = trim(isset($_POST['slug']) ? $_POST['slug'] : '');
    if ($slug === '' && $name !== '') $slug = slugify($name);
    $sku = trim(isset($_POST['sku']) ? $_POST['sku'] : '');
    $category_id = (int)(isset($_POST['category_id']) ? $_POST['category_id'] : 0) ?: null;
    $short_description = trim(isset($_POST['short_description']) ? $_POST['short_description'] : '');
    $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
    $price = (float)(isset($_POST['price']) ? $_POST['price'] : 0);
    $sale_price = isset($_POST['sale_price']) && $_POST['sale_price'] !== '' ? (float)$_POST['sale_price'] : null;
    $stock = (int)(isset($_POST['stock']) ? $_POST['stock'] : 0);
    $sizes = trim(isset($_POST['sizes']) ? $_POST['sizes'] : 'S,M,L,XL');
    $colors = trim(isset($_POST['colors']) ? $_POST['colors'] : 'Black,White');
    $material = trim(isset($_POST['material']) ? $_POST['material'] : '');
    $gender = isset($_POST['gender']) ? $_POST['gender'] : 'unisex';
    // Checkbox + hidden: if both sent PHP keeps last value; support array too
    $is_featured = 0;
    if (isset($_POST['is_featured'])) {
        $fv = $_POST['is_featured'];
        if (is_array($fv)) {
            $is_featured = in_array('1', $fv, true) ? 1 : 0;
        } else {
            $is_featured = ((string)$fv === '1') ? 1 : 0;
        }
    }
    $is_new = 0;
    if (isset($_POST['is_new'])) {
        $nv = $_POST['is_new'];
        if (is_array($nv)) {
            $is_new = in_array('1', $nv, true) ? 1 : 0;
        } else {
            $is_new = ((string)$nv === '1') ? 1 : 0;
        }
    }
    $status = isset($_POST['status']) ? $_POST['status'] : 'active';

    if ($name === '') $errors[] = 'Product name is required.';
    if ($price < 0) $errors[] = 'Price is invalid.';
    if (!in_array($gender, ['men','women','unisex','kids'], true)) $gender = 'unisex';
    if (!in_array($status, ['active','inactive','draft'], true)) $status = 'active';

    // Unique slug
    if ($slug) {
        $exists = Database::fetch("SELECT id FROM products WHERE slug=? AND id!=?", [$slug, $id]);
        if ($exists) $slug = $slug . '-' . time();
    }

    if (empty($errors)) {
        $data = [
            'category_id' => $category_id,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku ?: null,
            'short_description' => $short_description ?: null,
            'description' => $description ?: null,
            'price' => $price,
            'sale_price' => $sale_price,
            'stock' => $stock,
            'sizes' => $sizes,
            'colors' => $colors,
            'material' => $material ?: null,
            'gender' => $gender,
            'is_featured' => $is_featured,
            'is_new' => $is_new,
            'status' => $status,
        ];

        if ($product) {
            Database::update('products', $data, 'id=?', [$id]);
            // Force flags again (guarantees uncheck saves as 0)
            Database::query(
                "UPDATE products SET is_featured = ?, is_new = ? WHERE id = ?",
                array($is_featured, $is_new, $id)
            );
            flash('success', 'Product updated successfully.');
        } else {
            $id = Database::insert('products', $data);
            flash('success', 'Product created successfully.');
        }

        // Upload images
        if (!empty($_FILES['images']) && isset($_FILES['images']['name'])) {
            $hasFile = is_array($_FILES['images']['name'])
                ? (count(array_filter($_FILES['images']['name'])) > 0)
                : ($_FILES['images']['error'] === UPLOAD_ERR_OK);
            if ($hasFile) {
                $result = ProductImage::upload($id, $_FILES['images']);
                $n = count($result['ids']);
                $msg = isset($_SESSION['flash']['success']) ? $_SESSION['flash']['success'] . ' ' : '';
                if ($n > 0) {
                    $msg .= $n . ' image(s) uploaded.';
                }
                if (!empty($result['errors'])) {
                    flash('error', implode(' | ', $result['errors']));
                }
                if ($n > 0) {
                    flash('success', trim($msg));
                }
            }
        }

        redirect('/admin/product-edit.php?id=' . $id);
    }
}

// Prefill
$v = function($key, $default = '') use ($product) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key])) return $_POST[$key];
    if ($product && isset($product[$key]) && $product[$key] !== null) return $product[$key];
    return $default;
};

ob_start();
?>
<style>
.img-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:14px;margin-top:12px}
.img-card{position:relative;border:1px solid var(--color-border);border-radius:12px;overflow:hidden;background:var(--color-bg)}
.img-card img{width:100%;aspect-ratio:3/4;object-fit:cover;display:block}
.img-card .img-actions{position:absolute;bottom:0;left:0;right:0;display:flex;gap:4px;padding:6px;background:linear-gradient(transparent,rgba(0,0,0,.75))}
.img-card .img-actions button{flex:1;padding:6px;border-radius:6px;font-size:.7rem;font-weight:600;cursor:pointer;border:none}
.img-card .badge-primary-img{position:absolute;top:8px;left:8px;background:var(--color-primary);color:#fff;font-size:.65rem;font-weight:700;padding:3px 8px;border-radius:999px}
.upload-zone{border:2px dashed var(--color-border);border-radius:14px;padding:28px;text-align:center;background:var(--color-bg);transition:border-color .2s,background .2s;cursor:pointer}
.upload-zone:hover,.upload-zone.dragover{border-color:var(--color-primary);background:color-mix(in srgb,var(--color-primary) 8%,transparent)}
.upload-zone input[type=file]{display:none}
.upload-preview{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
.upload-preview img{width:80px;height:100px;object-fit:cover;border-radius:8px;border:1px solid var(--color-border)}
.form-section{margin-bottom:28px}
.form-section h3{font-size:1rem;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--color-border)}
.checkbox-row{display:flex;gap:24px;flex-wrap:wrap;margin-top:8px}
.checkbox-row label{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.9rem}
</style>

<div style="margin-bottom:16px">
  <a href="/admin/products.php" class="btn btn-sm btn-outline"><i class="fas fa-arrow-left"></i> Back to Products</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:24px;align-items:start">

  <!-- Left: details -->
  <div>
    <div class="panel">
      <div class="panel-body">
        <div class="form-section">
          <h3>Basic Information</h3>
          <div class="form-group">
            <label>Product Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= e($v('name')) ?>" placeholder="e.g. AK Classic Logo Tee">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Slug</label>
              <input type="text" name="slug" class="form-control" value="<?= e($v('slug')) ?>" placeholder="auto-generated if empty">
            </div>
            <div class="form-group">
              <label>SKU</label>
              <input type="text" name="sku" class="form-control" value="<?= e($v('sku')) ?>" placeholder="AK-TS-001">
            </div>
          </div>
          <div class="form-group">
            <label>Short Description</label>
            <input type="text" name="short_description" class="form-control" value="<?= e($v('short_description')) ?>" maxlength="500">
          </div>
          <div class="form-group">
            <label>Full Description</label>
            <textarea name="description" class="form-control" rows="5"><?= e($v('description')) ?></textarea>
          </div>
        </div>

        <div class="form-section">
          <h3>Pricing & Stock</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Regular Price (৳) *</label>
              <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?= e($v('price', '0')) ?>">
            </div>
            <div class="form-group">
              <label>Sale Price (৳)</label>
              <input type="number" name="sale_price" class="form-control" step="0.01" min="0" value="<?= e($v('sale_price')) ?>" placeholder="Leave empty if no sale">
            </div>
          </div>
          <div class="form-group">
            <label>Stock Quantity</label>
            <input type="number" name="stock" class="form-control" min="0" value="<?= e($v('stock', '0')) ?>">
          </div>
        </div>

        <div class="form-section">
          <h3>Variants</h3>
          <div class="form-group">
            <label>Sizes <span class="text-muted">(comma separated)</span></label>
            <input type="text" name="sizes" class="form-control" value="<?= e($v('sizes', 'S,M,L,XL')) ?>" placeholder="S,M,L,XL,XXL">
          </div>
          <div class="form-group">
            <label>Colors <span class="text-muted">(comma separated)</span></label>
            <input type="text" name="colors" class="form-control" value="<?= e($v('colors', 'Black,White')) ?>" placeholder="Black,White,Navy">
          </div>
          <div class="form-group">
            <label>Material</label>
            <input type="text" name="material" class="form-control" value="<?= e($v('material')) ?>" placeholder="100% Cotton">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: category, status, images -->
  <div>
    <div class="panel" style="margin-bottom:20px">
      <div class="panel-body">
        <div class="form-group">
          <label>Category</label>
          <select name="category_id" class="form-control">
            <option value="">— Select —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (string)$v('category_id') === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Gender</label>
          <select name="gender" class="form-control">
            <?php foreach (['unisex'=>'Unisex','men'=>'Men','women'=>'Women','kids'=>'Kids'] as $gk=>$gl): ?>
            <option value="<?= $gk ?>" <?= $v('gender','unisex')===$gk?'selected':'' ?>><?= $gl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <?php foreach (['active'=>'Active','inactive'=>'Inactive','draft'=>'Draft'] as $sk=>$sl): ?>
            <option value="<?= $sk ?>" <?= $v('status','active')===$sk?'selected':'' ?>><?= $sl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="checkbox-row" style="flex-direction:column;align-items:flex-start;gap:12px;margin-top:12px;padding:14px;border:1px solid var(--color-border);border-radius:12px;background:var(--color-bg)">
          <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-muted)">Homepage flags (independent)</div>
          <?php
            // After redirect, use DB values; avoid empty("0") confusion
            $featOn = ((string)$v('is_featured', '0') === '1');
            $newOn  = ((string)$v('is_new', '0') === '1');
          ?>
          <label style="display:flex;align-items:center;gap:10px;font-weight:600">
            <input type="hidden" name="is_featured" value="0">
            <input type="checkbox" name="is_featured" value="1" <?= $featOn ? 'checked' : '' ?>>
            Featured product
          </label>
          <label style="display:flex;align-items:center;gap:10px;font-weight:600">
            <input type="hidden" name="is_new" value="0">
            <input type="checkbox" name="is_new" value="1" <?= $newOn ? 'checked' : '' ?>>
            New Arrival
          </label>
          <p class="text-muted" style="font-size:.78rem;margin:0">Uncheck and click <strong>Update Product</strong> to remove. Featured and New Arrival work separately.</p>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header"><h3>Product Images</h3></div>
      <div class="panel-body">
        <?php if ($product && $images): ?>
        <p class="text-muted" style="font-size:.85rem;margin-bottom:8px">Current images — set primary or delete</p>
        <div class="img-grid">
          <?php foreach ($images as $img): ?>
          <div class="img-card">
            <?php if ($img['is_primary']): ?><span class="badge-primary-img">Primary</span><?php endif; ?>
            <img src="<?= e(ProductImage::url($img['image_path'])) ?>" alt="">
            <div style="padding:6px 8px;display:flex;gap:4px;align-items:center;flex-wrap:wrap">
              <input type="text" form="img-color-<?= (int)$img['id'] ?>" name="image_color" value="<?= e(isset($img['color']) ? $img['color'] : '') ?>" placeholder="Color e.g. Navy" style="flex:1;min-width:70px;font-size:.75rem;padding:4px 6px;border-radius:6px;border:1px solid var(--color-border)">
              <button type="submit" form="img-color-<?= (int)$img['id'] ?>" style="background:var(--color-primary);color:#fff;font-size:.7rem;padding:4px 8px;border:none;border-radius:6px;cursor:pointer">Set</button>
            </div>
            <div class="img-actions">
              <?php if (!$img['is_primary']): ?>
              <button type="submit" form="img-primary-<?= $img['id'] ?>" style="background:#22c55e;color:#fff">Primary</button>
              <?php endif; ?>
              <button type="submit" form="img-delete-<?= $img['id'] ?>" style="background:#ef4444;color:#fff" onclick="return confirm('Delete this image?')">Delete</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php elseif ($product): ?>
        <p class="text-muted" style="font-size:.85rem;margin-bottom:12px">No images yet. Upload below.</p>
        <?php else: ?>
        <p class="text-muted" style="font-size:.85rem;margin-bottom:12px">Save product with images, or upload after creating.</p>
        <?php endif; ?>

        <label class="upload-zone" id="upload-zone" style="display:block;margin-top:16px">
          <i class="fas fa-cloud-upload-alt" style="font-size:2rem;opacity:.5;margin-bottom:8px;display:block"></i>
          <strong>Click or drag images here</strong>
          <div class="text-muted" style="font-size:.8rem;margin-top:6px">JPG, PNG, WebP, GIF · Max 5MB each · Multiple allowed</div>
          <input type="file" name="images[]" id="images-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
        </label>
        <div class="upload-preview" id="upload-preview"></div>
      </div>
    </div>

    <button type="submit" name="save_product" value="1" class="btn btn-primary btn-lg btn-block" style="margin-top:20px">
      <i class="fas fa-save"></i> <?= $product ? 'Update Product' : 'Create Product' ?>
    </button>
  </div>
</div>
</form>

<?php if ($product): foreach ($images as $img): ?>
<form id="img-color-<?= (int)$img['id'] ?>" method="POST" style="display:none">
  <input type="hidden" name="image_action" value="set_color">
  <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
</form>
<form id="img-primary-<?= (int)$img['id'] ?>" method="POST" style="display:none">
  <input type="hidden" name="image_action" value="primary">
  <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
</form>
<form id="img-delete-<?= (int)$img['id'] ?>" method="POST" style="display:none">
  <input type="hidden" name="image_action" value="delete">
  <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
</form>
<?php endforeach; endif; ?>

<script>
(function(){
  var zone = document.getElementById('upload-zone');
  var input = document.getElementById('images-input');
  var preview = document.getElementById('upload-preview');
  if (!zone || !input) return;
  zone.addEventListener('click', function(e){ if(e.target !== input) input.click(); });
  ['dragenter','dragover'].forEach(function(ev){
    zone.addEventListener(ev, function(e){ e.preventDefault(); zone.classList.add('dragover'); });
  });
  ['dragleave','drop'].forEach(function(ev){
    zone.addEventListener(ev, function(e){ e.preventDefault(); zone.classList.remove('dragover'); });
  });
  zone.addEventListener('drop', function(e){
    if (e.dataTransfer.files.length) input.files = e.dataTransfer.files;
    showPreview();
  });
  input.addEventListener('change', showPreview);
  function showPreview(){
    preview.innerHTML = '';
    if (!input.files) return;
    for (var i=0;i<input.files.length;i++){
      var f = input.files[i];
      if (!f.type.match(/^image\//)) continue;
      var img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      preview.appendChild(img);
    }
  }
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/app/views/layouts/admin.php';