<?php $pageTitle='Not Found'; ob_start(); ?>
<section class="section" style="padding-top:calc(var(--header-h)+80px);min-height:60vh;display:flex;align-items:center">
<div class="container text-center">
  <div style="font-size:5rem;font-weight:900;color:var(--color-primary)">404</div>
  <h1 style="margin:12px 0">Page Not Found</h1>
  <a href="/" class="btn btn-primary" style="margin-top:16px">Go Home</a>
</div>
</section>
<?php $content=ob_get_clean(); require dirname(__DIR__).'/layouts/frontend.php'; ?>
