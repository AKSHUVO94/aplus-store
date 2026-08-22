<?php $pageTitle='About AK'; ob_start(); ?>
<section class="section" style="padding-top:calc(var(--header-h) + 60px)">
<div class="container" style="max-width:720px">
  <h1 style="font-size:2.5rem;margin-bottom:20px">About <span class="text-gradient">AK</span></h1>
  <p style="font-size:1.15rem;color:var(--color-text-muted);line-height:1.8;margin-bottom:20px">
    AK is a modern clothing brand built for people who care about quality, fit, and timeless style. We design everyday essentials and statement pieces that feel as good as they look.
  </p>
  <p style="color:var(--color-text-muted);line-height:1.8">
    From premium tees and hoodies to tailored shirts and outerwear — every AK piece is crafted with attention to fabric, detail, and comfort. Based in Bangladesh, we deliver across the country with care.
  </p>
</div>
</section>
<?php $content=ob_get_clean(); require dirname(__DIR__).'/layouts/frontend.php'; ?>
