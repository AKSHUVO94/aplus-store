<?php
require_once dirname(dirname(__DIR__)) . '/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Messages';

if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    Database::update('contact_messages', array('status' => 'read'), 'id=?', array((int)$_GET['read']));
    flash('success', 'Marked as read.');
    $go = '/admin/messages.php';
    if (isset($_GET['id'])) {
        $go .= '?id=' . (int)$_GET['id'];
    }
    redirect($go);
}

if (isset($_GET['unread']) && is_numeric($_GET['unread'])) {
    Database::update('contact_messages', array('status' => 'new'), 'id=?', array((int)$_GET['unread']));
    flash('success', 'Marked as new.');
    redirect('/admin/messages.php?id=' . (int)$_GET['unread']);
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    Database::delete('contact_messages', 'id=?', array((int)$_GET['delete']));
    flash('success', 'Message deleted.');
    redirect('/admin/messages.php');
}

$viewId = 0;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $viewId = (int)$_GET['id'];
}

$viewMsg = null;
if ($viewId > 0) {
    $viewMsg = Database::fetch('SELECT * FROM contact_messages WHERE id=?', array($viewId));
    if ($viewMsg && isset($viewMsg['status']) && $viewMsg['status'] === 'new') {
        Database::update('contact_messages', array('status' => 'read'), 'id=?', array($viewId));
        $viewMsg['status'] = 'read';
    }
}

$filter = '';
if (isset($_GET['status'])) {
    $filter = trim($_GET['status']);
}

$q = '';
if (isset($_GET['q'])) {
    $q = trim($_GET['q']);
}

$where = array('1=1');
$params = array();

if ($filter === 'new' || $filter === 'read') {
    $where[] = 'status = ?';
    $params[] = $filter;
}

if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = 'SELECT * FROM contact_messages WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
$msgs = Database::fetchAll($sql, $params);

$newCount = 0;
try {
    $row = Database::fetch("SELECT COUNT(*) AS c FROM contact_messages WHERE status='new'");
    if ($row) {
        $newCount = (int)$row['c'];
    }
} catch (Exception $e) {
    $newCount = 0;
}

ob_start();
?>
<style>
.msg-layout{display:grid;grid-template-columns:1fr 1.2fr;gap:20px;align-items:start}
.msg-row{display:block;padding:14px 16px;border-bottom:1px solid var(--color-border);color:inherit}
.msg-row:hover,.msg-row.active{background:color-mix(in srgb,var(--color-primary) 8%,transparent)}
.msg-row.is-new .msg-from{font-weight:800;color:var(--color-primary)}
.msg-from{font-weight:600;font-size:.9rem}
.msg-sub{font-size:.8rem;color:var(--color-text-muted);margin-top:2px}
.msg-detail{padding:24px}
.msg-body{margin-top:20px;line-height:1.7;white-space:pre-wrap}
.msg-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:24px;padding-top:16px;border-top:1px solid var(--color-border)}
@media(max-width:900px){.msg-layout{grid-template-columns:1fr}}
</style>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;align-items:center">
  <a href="/admin/messages.php" class="btn btn-sm <?php echo $filter === '' ? 'btn-primary' : 'btn-outline'; ?>">All</a>
  <a href="?status=new" class="btn btn-sm <?php echo $filter === 'new' ? 'btn-primary' : 'btn-outline'; ?>">New<?php echo $newCount ? ' ('.$newCount.')' : ''; ?></a>
  <a href="?status=read" class="btn btn-sm <?php echo $filter === 'read' ? 'btn-primary' : 'btn-outline'; ?>">Read</a>
  <form method="GET" style="display:flex;gap:8px;margin-left:auto;flex-wrap:wrap">
    <?php if ($filter !== ''): ?>
    <input type="hidden" name="status" value="<?php echo e($filter); ?>">
    <?php endif; ?>
    <input type="search" name="q" class="form-control" style="max-width:220px" placeholder="Search..." value="<?php echo e($q); ?>">
    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
  </form>
</div>

<div class="msg-layout">
  <div class="panel">
    <div class="panel-header"><h3>Inbox</h3><span class="badge badge-info"><?php echo count($msgs); ?></span></div>
    <div class="panel-body" style="padding:0;max-height:640px;overflow-y:auto">
      <?php if (empty($msgs)): ?>
      <div style="padding:40px;text-align:center;color:var(--color-text-muted)">No messages</div>
      <?php endif; ?>
      <?php foreach ($msgs as $m): ?>
      <?php
        $href = '?id=' . (int)$m['id'];
        if ($filter !== '') { $href .= '&status=' . urlencode($filter); }
        if ($q !== '') { $href .= '&q=' . urlencode($q); }
        $rowClass = 'msg-row';
        if ($viewId === (int)$m['id']) { $rowClass .= ' active'; }
        if (isset($m['status']) && $m['status'] === 'new') { $rowClass .= ' is-new'; }
      ?>
      <a href="<?php echo $href; ?>" class="<?php echo $rowClass; ?>">
        <div class="msg-from"><?php echo e($m['name']); ?></div>
        <div class="msg-sub"><?php echo e(!empty($m['subject']) ? $m['subject'] : 'No subject'); ?> · <?php echo timeAgo($m['created_at']); ?></div>
        <div class="msg-sub" style="margin-top:4px"><?php echo e(truncate($m['message'], 70)); ?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <?php if ($viewMsg): ?>
    <div class="msg-detail">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:4px">
        <h2 style="font-size:1.15rem"><?php echo e(!empty($viewMsg['subject']) ? $viewMsg['subject'] : 'No subject'); ?></h2>
        <span class="badge badge-<?php echo $viewMsg['status'] === 'new' ? 'info' : 'success'; ?>"><?php echo e(ucfirst($viewMsg['status'])); ?></span>
      </div>
      <p class="text-muted" style="font-size:.9rem">
        From <strong><?php echo e($viewMsg['name']); ?></strong>
        &lt;<?php echo e($viewMsg['email']); ?>&gt;
        · <?php echo formatDate($viewMsg['created_at'], 'M d, Y H:i'); ?>
      </p>
      <div class="msg-body"><?php echo e($viewMsg['message']); ?></div>
      <div class="msg-actions">
        <a class="btn btn-primary btn-sm" href="mailto:<?php echo e($viewMsg['email']); ?>">
          <i class="fas fa-reply"></i> Reply by Email
        </a>
        <?php if ($viewMsg['status'] === 'read'): ?>
        <a class="btn btn-outline btn-sm" href="?unread=<?php echo (int)$viewMsg['id']; ?>&amp;id=<?php echo (int)$viewMsg['id']; ?>">Mark as New</a>
        <?php else: ?>
        <a class="btn btn-outline btn-sm" href="?read=<?php echo (int)$viewMsg['id']; ?>&amp;id=<?php echo (int)$viewMsg['id']; ?>">Mark as Read</a>
        <?php endif; ?>
        <a class="btn btn-sm" style="color:#f87171" href="?delete=<?php echo (int)$viewMsg['id']; ?>" onclick="return confirm('Delete this message?');">
          <i class="fas fa-trash"></i> Delete
        </a>
      </div>
    </div>
    <?php else: ?>
    <div style="padding:60px 24px;text-align:center;color:var(--color-text-muted)">
      <i class="fas fa-envelope-open" style="font-size:2rem;opacity:.4;display:block;margin-bottom:12px"></i>
      Select a message to view
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require dirname(dirname(__DIR__)) . '/app/views/layouts/admin.php';