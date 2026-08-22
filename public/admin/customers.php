<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Customers';

if (isset($_GET['block']) && is_numeric($_GET['block'])) {
    Database::update('customers', ['status' => 'blocked'], 'id=?', [(int)$_GET['block']]);
    flash('success', 'Customer blocked.');
    redirect('/admin/customers.php');
}
if (isset($_GET['unblock']) && is_numeric($_GET['unblock'])) {
    Database::update('customers', ['status' => 'active'], 'id=?', [(int)$_GET['unblock']]);
    flash('success', 'Customer unblocked.');
    redirect('/admin/customers.php');
}

$customers = [];
try {
    $customers = Database::fetchAll("SELECT * FROM customers ORDER BY created_at DESC");
} catch (Exception $e) {
    flash('error', 'Run upgrade_customers_security.sql first.');
}
ob_start();
?>
<div style="margin-bottom:16px"><span class="text-muted"><?= count($customers) ?> customers in database</span></div>
<div class="panel">
  <div class="panel-header"><h3>Customer Directory</h3></div>
  <div class="panel-body" style="padding:0">
    <div style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name</th><th>Email</th><th>Phone</th><th>City</th>
          <th>Orders</th><th>Spent</th><th>Status</th><th>Joined</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
      <tr>
        <td><strong><?= e($c['full_name']) ?></strong>
          <div class="text-muted" style="font-size:.75rem"><?= e(truncate($c['address'] ?: '', 40)) ?></div>
        </td>
        <td><?= e($c['email']) ?></td>
        <td><?= e($c['phone'] ?: '—') ?></td>
        <td><?= e($c['city'] ?: '—') ?></td>
        <td><?= (int)$c['total_orders'] ?></td>
        <td><?= money($c['total_spent']) ?></td>
        <td>
          <span class="badge badge-<?= $c['status']==='active'?'success':'danger' ?>"><?= e(ucfirst($c['status'])) ?></span>
        </td>
        <td><?= formatDate($c['created_at']) ?></td>
        <td style="white-space:nowrap">
          <?php if ($c['status']==='active'): ?>
          <a href="?block=<?= $c['id'] ?>" class="btn btn-sm" style="color:#f87171" onclick="return confirm('Block this customer?')">Block</a>
          <?php else: ?>
          <a href="?unblock=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Unblock</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($customers)): ?>
      <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--color-text-muted)">No customers yet. They appear after register or checkout.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php $content=ob_get_clean(); require dirname(__DIR__,2).'/app/views/layouts/admin.php'; ?>
