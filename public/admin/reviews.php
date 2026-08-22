<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Product Reviews';

// Ensure table exists (safe)
try {
    Database::query("CREATE TABLE IF NOT EXISTS `product_reviews` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `product_id` INT UNSIGNED NOT NULL,
      `customer_id` INT UNSIGNED DEFAULT NULL,
      `customer_name` VARCHAR(120) NOT NULL,
      `customer_email` VARCHAR(160) DEFAULT NULL,
      `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
      `comment` TEXT NOT NULL,
      `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      `show_on_home` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_product` (`product_id`),
      KEY `idx_status` (`status`),
      KEY `idx_home` (`show_on_home`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($id > 0) {
        if ($action === 'approve') {
            Database::update('product_reviews', array('status' => 'approved'), 'id=?', array($id));
            flash('success', 'Review approved.');
        } elseif ($action === 'reject') {
            Database::update('product_reviews', array('status' => 'rejected', 'show_on_home' => 0), 'id=?', array($id));
            flash('success', 'Review rejected.');
        } elseif ($action === 'home_on') {
            $homeCount = (int) Database::fetch("SELECT COUNT(*) c FROM product_reviews WHERE show_on_home=1 AND status='approved'")['c'];
            if ($homeCount >= 12) {
                flash('error', 'Maximum homepage reviews reached. Turn off another first.');
            } else {
                Database::update('product_reviews', array('show_on_home' => 1, 'status' => 'approved'), 'id=?', array($id));
                flash('success', 'Review will show on homepage.');
            }
        } elseif ($action === 'home_off') {
            Database::update('product_reviews', array('show_on_home' => 0), 'id=?', array($id));
            flash('success', 'Removed from homepage.');
        } elseif ($action === 'delete') {
            Database::delete('product_reviews', 'id=?', array($id));
            flash('success', 'Review deleted.');
        }
    }
    redirect('/admin/reviews.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

$filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sql = "SELECT r.*, p.name as product_name, p.slug as product_slug
        FROM product_reviews r
        LEFT JOIN products p ON p.id = r.product_id";
$params = array();
if (in_array($filter, array('pending','approved','rejected','home'), true)) {
    if ($filter === 'home') {
        $sql .= " WHERE r.show_on_home=1 AND r.status='approved'";
    } else {
        $sql .= " WHERE r.status=?";
        $params[] = $filter;
    }
}
$sql .= " ORDER BY r.created_at DESC LIMIT 200";
$reviews = Database::fetchAll($sql, $params);

$counts = array(
    'all' => (int) Database::fetch("SELECT COUNT(*) c FROM product_reviews")['c'],
    'pending' => (int) Database::fetch("SELECT COUNT(*) c FROM product_reviews WHERE status='pending'")['c'],
    'approved' => (int) Database::fetch("SELECT COUNT(*) c FROM product_reviews WHERE status='approved'")['c'],
    'home' => (int) Database::fetch("SELECT COUNT(*) c FROM product_reviews WHERE show_on_home=1 AND status='approved'")['c'],
);

ob_start();
?>
<p class="text-muted" style="margin:0 0 14px">Customer product reviews. Approve them, then mark <strong>Show on home</strong> (homepage shows up to 6).</p>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
  <?php foreach (array('all'=>'All','pending'=>'Pending','approved'=>'Approved','home'=>'On Homepage') as $k=>$lab): ?>
  <a href="?status=<?= e($k) ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-outline' ?>">
    <?= e($lab) ?> (<?= (int)($counts[$k] ?? 0) ?>)
  </a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <div class="panel-body" style="padding:0;overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Product</th>
          <th>Customer</th>
          <th>Rating</th>
          <th>Comment</th>
          <th>Status</th>
          <th>Home</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($reviews)): ?>
        <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--color-text-muted)">No reviews yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($reviews as $r): ?>
        <tr>
          <td style="white-space:nowrap;font-size:.8rem"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
          <td>
            <?php if (!empty($r['product_slug'])): ?>
              <a href="/index.php?route=product&slug=<?= e($r['product_slug']) ?>" target="_blank"><?= e($r['product_name'] ?: 'Product #'.$r['product_id']) ?></a>
            <?php else: ?>
              <?= e($r['product_name'] ?: '#'.$r['product_id']) ?>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= e($r['customer_name']) ?></strong>
            <?php if ($r['customer_email']): ?><br><span class="text-muted" style="font-size:.75rem"><?= e($r['customer_email']) ?></span><?php endif; ?>
          </td>
          <td style="white-space:nowrap;color:#f59e0b">
            <?php for ($i=1;$i<=5;$i++): ?><i class="fas fa-star" style="opacity:<?= $i <= (int)$r['rating'] ? '1' : '.25' ?>"></i><?php endfor; ?>
          </td>
          <td style="max-width:280px;font-size:.875rem"><?= e($r['comment']) ?></td>
          <td>
            <span class="badge badge-<?= $r['status']==='approved'?'success':($r['status']==='pending'?'warning':'danger') ?>">
              <?= e(ucfirst($r['status'])) ?>
            </span>
          </td>
          <td><?= !empty($r['show_on_home']) ? '✓ Yes' : '—' ?></td>
          <td style="white-space:nowrap">
            <form method="POST" style="display:inline">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <?php if ($r['status'] !== 'approved'): ?>
              <button name="action" value="approve" class="btn btn-sm btn-primary">Approve</button>
              <?php endif; ?>
              <?php if ($r['status'] !== 'rejected'): ?>
              <button name="action" value="reject" class="btn btn-sm btn-outline">Reject</button>
              <?php endif; ?>
              <?php if (empty($r['show_on_home'])): ?>
              <button name="action" value="home_on" class="btn btn-sm btn-outline" title="Show on homepage">Home ✓</button>
              <?php else: ?>
              <button name="action" value="home_off" class="btn btn-sm btn-outline">Home ✕</button>
              <?php endif; ?>
              <button name="action" value="delete" class="btn btn-sm btn-outline" onclick="return confirm('Delete this review?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/app/views/layouts/admin.php';