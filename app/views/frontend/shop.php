<?php
$pageTitle = 'Shop';
$catSlug = isset($_GET['category']) ? $_GET['category'] : (isset($_GET['slug']) ? $_GET['slug'] : '');
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$sql = "SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active'";
$params = array();
if ($catSlug) {
    $sql .= " AND c.slug=?";
    $params[] = $catSlug;
    $cat = Database::fetch("SELECT * FROM categories WHERE slug=?", array($catSlug));
    if ($cat) {
        $pageTitle = $cat['name'];
    }
}
if ($filter === 'new') {
    $sql .= " AND p.is_new=1";
    $pageTitle = 'New Arrivals';
}
if ($filter === 'sale') {
    $sql .= " AND p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price";
    $pageTitle = 'Sale / Discount';
}
if ($q !== '') {
    $sql .= " AND (p.name LIKE ? OR p.short_description LIKE ? OR p.sku LIKE ? OR c.name LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $pageTitle = 'Search: ' . $q;
}
$sql .= " ORDER BY p.is_featured DESC, p.id DESC";
$products = Database::fetchAll($sql, $params);
$categories = Database::fetchAll("SELECT * FROM categories WHERE status='active' ORDER BY sort_order");
ob_start();
?>
<section class="section" style="padding-top:calc(var(--header-h) + 40px)">
<div class="container">
  <div class="section-header">
    <div>
      <p class="text-muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Collection</p>
      <h1 style="font-size:2rem"><?= e($pageTitle) ?></h1>
    </div>
    <span class="text-muted"><?= count($products) ?> products</span>
  </div>
  <div class="cat-pills">
    <a href="/shop.php" class="cat-pill <?= !$catSlug && $filter === '' ? 'active' : '' ?>">All</a>
    <a href="/shop.php?filter=sale" class="cat-pill <?= $filter === 'sale' ? 'active' : '' ?>">Sale</a>
    <a href="/shop.php?filter=new" class="cat-pill <?= $filter === 'new' ? 'active' : '' ?>">New</a>
    <?php foreach ($categories as $c): ?>
    <a href="/index.php?route=category&slug=<?= e($c['slug']) ?>" class="cat-pill <?= $catSlug === $c['slug'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (empty($products)): ?>
  <div class="empty-state"><i class="fas fa-box-open"></i><p>No products found</p></div>
  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $p): ?>
      <?= render_product_card($p) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__) . '/layouts/frontend.php'; ?>