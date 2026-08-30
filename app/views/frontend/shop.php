<?php
/**
 * AK Store — Shop with working filters & sorting
 */
$pageTitle = 'Shop';

$catSlug = $_GET['slug'] ?? null;
$sort    = $_GET['sort'] ?? 'featured';

$minPrice = null;
$maxPrice = null;
if (isset($_GET['min_price']) && $_GET['min_price'] !== '') {
    $minPrice = max(0, (float) $_GET['min_price']);
}
if (isset($_GET['max_price']) && $_GET['max_price'] !== '') {
    $maxPrice = max(0, (float) $_GET['max_price']);
}

$selectedCats = [];
if (!empty($_GET['cat'])) {
    $raw = is_array($_GET['cat']) ? $_GET['cat'] : [$_GET['cat']];
    $selectedCats = array_values(array_unique(array_filter(array_map('intval', $raw), fn($id) => $id > 0)));
}

$genders = [];
$allowedGenders = ['men', 'women', 'unisex', 'kids'];
if (!empty($_GET['gender'])) {
    $raw = is_array($_GET['gender']) ? $_GET['gender'] : [$_GET['gender']];
    foreach ($raw as $g) {
        $g = strtolower(trim((string) $g));
        if (in_array($g, $allowedGenders, true)) $genders[] = $g;
    }
    $genders = array_values(array_unique($genders));
}

$featuredOnly = !empty($_GET['featured']);
$onSale       = !empty($_GET['on_sale']);
// Support home "View all" links: ?filter=sale | ?filter=new
$filterParam = strtolower(trim((string)($_GET['filter'] ?? '')));
if ($filterParam === 'sale') $onSale = true;
$newOnly = ($filterParam === 'new');
if ($newOnly && $sort === 'featured') $sort = 'newest';

// Header / shop search query
$searchQ = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (mb_strlen($searchQ) > 120) {
    $searchQ = mb_substr($searchQ, 0, 120);
}
if ($searchQ !== '') {
    $pageTitle = 'Search: ' . $searchQ;
}

$catName = null;
if ($catSlug) {
    $cat = Database::fetch("SELECT * FROM categories WHERE slug = ? AND status = 'active'", [$catSlug]);
    if ($cat) {
        $selectedCats = [(int) $cat['id']];
        $catName = $cat['name'];
        $pageTitle = $cat['name'];
    }
}

$hasGender = false;
try {
    $hasGender = (bool) Database::fetch("SHOW COLUMNS FROM products LIKE 'gender'");
} catch (Throwable $e) {
    $hasGender = false;
}

$where  = ["p.status = 'active'"];
$params = [];
$priceExpr = "COALESCE(NULLIF(p.sale_price, 0), p.price)";

if ($selectedCats) {
    $ph = implode(',', array_fill(0, count($selectedCats), '?'));
    $where[] = "p.category_id IN ($ph)";
    foreach ($selectedCats as $cid) $params[] = $cid;
}

if ($minPrice !== null) {
    $where[] = "$priceExpr >= ?";
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = "$priceExpr <= ?";
    $params[] = $maxPrice;
}

if ($hasGender && $genders) {
    $ph = implode(',', array_fill(0, count($genders), '?'));
    $where[] = "p.gender IN ($ph)";
    foreach ($genders as $g) $params[] = $g;
}

if ($featuredOnly) $where[] = "p.is_featured = 1";
if ($onSale) $where[] = "p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price";
if ($newOnly) {
    try {
        if (Database::fetch("SHOW COLUMNS FROM products LIKE 'is_new'")) {
            $where[] = "p.is_new = 1";
        }
    } catch (Throwable $e) {
        // column missing — already sorted by newest above
    }
}

if ($searchQ !== '') {
    $like = '%' . $searchQ . '%';
    $where[] = "(p.name LIKE ? OR p.slug LIKE ? OR COALESCE(p.short_description, '') LIKE ? OR COALESCE(p.description, '') LIKE ? OR COALESCE(p.material, '') LIKE ? OR COALESCE(c.name, '') LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);

$salesJoin = '';
$extraSelect = '';
switch ($sort) {
    case 'price_asc':  $orderSql = "$priceExpr ASC, p.name ASC"; break;
    case 'price_desc': $orderSql = "$priceExpr DESC, p.name ASC"; break;
    case 'name_asc':   $orderSql = "p.name ASC"; break;
    case 'name_desc':  $orderSql = "p.name DESC"; break;
    case 'trending':   $orderSql = "p.views DESC, p.is_featured DESC, p.id DESC"; break;
    case 'top_sales':
        $salesJoin = "LEFT JOIN (SELECT product_id, SUM(quantity) AS sales_count FROM order_items GROUP BY product_id) oi ON oi.product_id = p.id";
        $extraSelect = ", COALESCE(oi.sales_count, 0) AS sales_count";
        $orderSql = "COALESCE(oi.sales_count, 0) DESC, p.id DESC";
        break;
    case 'newest': $orderSql = "p.id DESC"; break;
    default: $orderSql = "p.is_featured DESC, p.id DESC"; break;
}

$products = Database::fetchAll(
    "SELECT p.*, c.name AS cat_name $extraSelect,
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS thumb
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     $salesJoin
     WHERE $whereSql
     ORDER BY $orderSql",
    $params
);

$allCategories = Database::fetchAll(
    "SELECT c.id, c.name, c.slug,
            (SELECT COUNT(*) FROM products p2 WHERE p2.category_id = c.id AND p2.status = 'active') AS cnt
     FROM categories c WHERE c.status = 'active' ORDER BY c.sort_order, c.name"
);

$priceBounds = Database::fetch(
    "SELECT FLOOR(MIN($priceExpr)) AS min_p, CEIL(MAX($priceExpr)) AS max_p
     FROM products p WHERE p.status = 'active'"
);
$dbMin = (float) ($priceBounds['min_p'] ?? 0);
$dbMax = (float) ($priceBounds['max_p'] ?? 10000);
if ($dbMax <= $dbMin) $dbMax = $dbMin + 1;

$inputMin = $minPrice !== null ? (int) $minPrice : (int) $dbMin;
$inputMax = $maxPrice !== null ? (int) $maxPrice : (int) $dbMax;

$sortOptions = [
    'featured'   => 'Featured',
    'newest'     => 'Newest',
    'price_asc'  => 'Price: Low to High',
    'price_desc' => 'Price: High to Low',
    'name_asc'   => 'Name: A to Z',
    'name_desc'  => 'Name: Z to A',
    'trending'   => 'Trending',
    'top_sales'  => 'Top Sales',
];

$formAction = $catSlug ? url('/category/' . $catSlug) : url('/shop');

ob_start();
?>
<section class="section shop-section" style="padding-top:calc(var(--header-h) + 40px)">
  <div class="container">
    <div class="shop-topbar mb-3">
      <div>
        <span class="label" style="display:block;margin-bottom:6px"><?= $searchQ !== '' ? 'Search' : ($catName ? 'Category' : 'Shop') ?></span>
        <h1 class="display" style="font-size:clamp(1.6rem,3.5vw,2.2rem);margin:0"><?= e($searchQ !== '' ? ('Results for “' . $searchQ . '”') : ($catName ?? 'All Products')) ?></h1>
        <p class="text-muted" style="margin-top:6px"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?> found</p>
      </div>
      <form method="GET" action="<?= e($formAction) ?>" class="shop-sort-form" id="sortForm">
        <?php if ($searchQ !== ''): ?><input type="hidden" name="q" value="<?= e($searchQ) ?>"><?php endif; ?>
        <?php if (!$catSlug): foreach ($selectedCats as $cid): ?>
          <input type="hidden" name="cat[]" value="<?= (int) $cid ?>">
        <?php endforeach; endif; ?>
        <?php foreach ($genders as $g): ?>
          <input type="hidden" name="gender[]" value="<?= e($g) ?>">
        <?php endforeach; ?>
        <?php if ($minPrice !== null): ?><input type="hidden" name="min_price" value="<?= (int) $minPrice ?>"><?php endif; ?>
        <?php if ($maxPrice !== null): ?><input type="hidden" name="max_price" value="<?= (int) $maxPrice ?>"><?php endif; ?>
        <?php if ($featuredOnly): ?><input type="hidden" name="featured" value="1"><?php endif; ?>
        <?php if ($onSale): ?><input type="hidden" name="on_sale" value="1"><?php endif; ?>
        <label class="sort-label" for="sort">Sort by</label>
        <select name="sort" id="sort" class="form-control sort-select" onchange="this.form.submit()">
          <?php foreach ($sortOptions as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $sort === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="shop-layout">
      <aside class="shop-filters card">
        <form method="GET" action="<?= e($formAction) ?>" id="filterForm">
          <input type="hidden" name="sort" value="<?= e($sort) ?>">
          <?php if ($searchQ !== ''): ?><input type="hidden" name="q" value="<?= e($searchQ) ?>"><?php endif; ?>

          <div class="filter-block">
            <h3 class="filter-title">Price Range</h3>
            <div class="range-slider-wrap">
              <div class="range-track" id="rangeTrack">
                <div class="range-progress" id="rangeProgress"></div>
                <input type="range" id="rangeMin" min="<?= (int) $dbMin ?>" max="<?= (int) $dbMax ?>" value="<?= $inputMin ?>" step="10" aria-label="Minimum price">
                <input type="range" id="rangeMax" min="<?= (int) $dbMin ?>" max="<?= (int) $dbMax ?>" value="<?= $inputMax ?>" step="10" aria-label="Maximum price">
              </div>
            </div>
            <div class="price-inputs">
              <div>
                <label class="text-muted" style="font-size:.75rem">Min (৳)</label>
                <input type="number" name="min_price" id="minPriceInput" class="form-control"
                       min="<?= (int) $dbMin ?>" max="<?= (int) $dbMax ?>" step="1" value="<?= $inputMin ?>">
              </div>
              <span class="price-sep">—</span>
              <div>
                <label class="text-muted" style="font-size:.75rem">Max (৳)</label>
                <input type="number" name="max_price" id="maxPriceInput" class="form-control"
                       min="<?= (int) $dbMin ?>" max="<?= (int) $dbMax ?>" step="1" value="<?= $inputMax ?>">
              </div>
            </div>
            <p class="text-muted" style="font-size:.75rem;margin-top:8px">Available: <?= money($dbMin) ?> – <?= money($dbMax) ?></p>
          </div>

          <?php if (!$catSlug && $allCategories): ?>
          <div class="filter-block">
            <h3 class="filter-title">Categories</h3>
            <div class="filter-checks">
              <?php foreach ($allCategories as $c): ?>
              <label class="check-row">
                <input type="checkbox" name="cat[]" value="<?= (int) $c['id'] ?>" class="filter-auto"
                       <?= in_array((int) $c['id'], $selectedCats, true) ? 'checked' : '' ?>>
                <span><?= e($c['name']) ?></span>
                <span class="check-count"><?= (int) $c['cnt'] ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($hasGender): ?>
          <div class="filter-block">
            <h3 class="filter-title">Gender</h3>
            <div class="filter-checks">
              <?php foreach (['men' => 'Men', 'women' => 'Women', 'unisex' => 'Unisex', 'kids' => 'Kids'] as $gVal => $gLabel): ?>
              <label class="check-row">
                <input type="checkbox" name="gender[]" value="<?= $gVal ?>" class="filter-auto"
                       <?= in_array($gVal, $genders, true) ? 'checked' : '' ?>>
                <span><?= $gLabel ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <div class="filter-block">
            <h3 class="filter-title">More</h3>
            <div class="filter-checks">
              <label class="check-row">
                <input type="checkbox" name="featured" value="1" class="filter-auto" <?= $featuredOnly ? 'checked' : '' ?>>
                <span>Featured only</span>
              </label>
              <label class="check-row">
                <input type="checkbox" name="on_sale" value="1" class="filter-auto" <?= $onSale ? 'checked' : '' ?>>
                <span>On Sale</span>
              </label>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter"></i> Apply Filters</button>
          <a href="<?= e($formAction) ?>" class="btn btn-outline btn-block mt-2">Clear All</a>
        </form>
      </aside>

      <div class="shop-products">
        <?php if (empty($products)): ?>
          <div class="card" style="padding:48px;text-align:center">
            <p class="text-muted mb-3">No products match your filters.</p>
            <a href="<?= e($formAction) ?>" class="btn btn-outline">Clear filters</a>
          </div>
        <?php else: ?>
        <div class="product-grid">
          <?php foreach ($products as $p): ?>
            <?= render_product_card($p) ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  var form = document.getElementById('filterForm');
  var rangeMin = document.getElementById('rangeMin');
  var rangeMax = document.getElementById('rangeMax');
  var minInput = document.getElementById('minPriceInput');
  var maxInput = document.getElementById('maxPriceInput');
  var progress = document.getElementById('rangeProgress');
  if (!form || !rangeMin || !rangeMax || !minInput || !maxInput) return;

  var absMin = Number(rangeMin.min);
  var absMax = Number(rangeMax.max);
  var span = absMax - absMin || 1;
  var submitTimer = null;

  function updateProgress() {
    if (!progress) return;
    var a = Number(rangeMin.value);
    var b = Number(rangeMax.value);
    if (a > b) { var t = a; a = b; b = t; }
    var left = ((a - absMin) / span) * 100;
    var right = ((b - absMin) / span) * 100;
    progress.style.left = left + '%';
    progress.style.width = Math.max(0, right - left) + '%';
  }

  function clampPair(a, b) {
    if (isNaN(a)) a = absMin;
    if (isNaN(b)) b = absMax;
    a = Math.max(absMin, Math.min(absMax, a));
    b = Math.max(absMin, Math.min(absMax, b));
    if (a > b) { var t = a; a = b; b = t; }
    return [Math.round(a), Math.round(b)];
  }

  function syncFromSliders(el) {
    var a = Number(rangeMin.value);
    var b = Number(rangeMax.value);
    if (a > b) {
      if (el === rangeMin) { rangeMax.value = a; b = a; }
      else { rangeMin.value = b; a = b; }
    }
    minInput.value = a;
    maxInput.value = b;
    if (a >= b - span * 0.05) {
      rangeMin.style.zIndex = 5;
      rangeMax.style.zIndex = 6;
    } else {
      rangeMin.style.zIndex = 3;
      rangeMax.style.zIndex = 4;
    }
    updateProgress();
  }

  function syncFromInputs() {
    var pair = clampPair(Number(minInput.value), Number(maxInput.value));
    minInput.value = pair[0];
    maxInput.value = pair[1];
    rangeMin.value = pair[0];
    rangeMax.value = pair[1];
    updateProgress();
  }

  function applyFiltersSoon() {
    clearTimeout(submitTimer);
    submitTimer = setTimeout(function () {
      syncFromInputs();
      form.submit();
    }, 400);
  }

  rangeMin.addEventListener('input', function () { syncFromSliders(rangeMin); });
  rangeMax.addEventListener('input', function () { syncFromSliders(rangeMax); });
  rangeMin.addEventListener('change', applyFiltersSoon);
  rangeMax.addEventListener('change', applyFiltersSoon);

  minInput.addEventListener('input', syncFromInputs);
  maxInput.addEventListener('input', syncFromInputs);
  minInput.addEventListener('change', applyFiltersSoon);
  maxInput.addEventListener('change', applyFiltersSoon);

  form.querySelectorAll('.filter-auto').forEach(function (el) {
    el.addEventListener('change', function () { form.submit(); });
  });

  updateProgress();
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/frontend.php';