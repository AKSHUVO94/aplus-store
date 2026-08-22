<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
Auth::requireAdmin();
$pageTitle = 'Themes';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['activate'])) {
    if (Theme::activate($_POST['activate'])) {
        flash('success', 'Theme activated!');
    }
    redirect('/admin/themes.php');
}
$themes = Theme::all();
$current = Theme::current();
ob_start();
?>
<div class="panel">
  <div class="panel-header"><h3>Store Themes</h3><span class="badge badge-purple">Active: <?=e($current['name'])?></span></div>
  <div class="panel-body">
    <div class="theme-grid">
      <?php foreach($themes as $t): ?>
      <form method="POST" class="theme-card <?=$t['slug']===$current['slug']?'active':''?>">
        <input type="hidden" name="activate" value="<?=e($t['slug'])?>">
        <div class="theme-preview" style="background:<?=e($t['background'])?>">
          <span style="background:<?=e($t['primary_color'])?>"></span>
          <span style="background:<?=e($t['secondary_color'])?>"></span>
          <span style="background:<?=e($t['accent_color'])?>"></span>
        </div>
        <div class="info">
          <div><strong><?=e($t['name'])?></strong><div class="text-muted" style="font-size:.75rem"><?=$t['is_dark']?'Dark':'Light'?></div></div>
          <?php if($t['slug']===$current['slug']): ?><span class="badge badge-success">Active</span>
          <?php else: ?><button type="submit" class="btn btn-sm btn-primary">Activate</button><?php endif; ?>
        </div>
      </form>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php $content=ob_get_clean(); require dirname(__DIR__,2).'/app/views/layouts/admin.php'; ?>
