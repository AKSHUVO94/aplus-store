<?php
$pageTitle='Contact';
$success=flash('success'); $error=flash('error');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $n=trim(isset($_POST['name'])?$_POST['name']:'');
    $em=trim(isset($_POST['email'])?$_POST['email']:'');
    $msg=trim(isset($_POST['message'])?$_POST['message']:'');
    if ($n && $em && $msg) {
        Database::insert('contact_messages',['name'=>$n,'email'=>$em,'subject'=>isset($_POST['subject'])?$_POST['subject']:null,'message'=>$msg]);
        flash('success','Message sent! We will reply soon.');
        redirect('/contact');
    }
    flash('error','Please fill all fields.');
    redirect('/contact');
}
ob_start();
?>
<section class="section" style="padding-top:calc(var(--header-h) + 40px)">
<div class="container" style="max-width:560px">
  <h1 style="margin-bottom:8px">Contact Us</h1>
  <p class="text-muted" style="margin-bottom:28px">Questions about orders, sizing, or wholesale? Reach out.</p>
  <?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
  <?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
  <form method="POST" style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:28px">
    <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required></div>
    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control"></div>
    <div class="form-group"><label>Message</label><textarea name="message" class="form-control" rows="4" required></textarea></div>
    <button type="submit" class="btn btn-primary btn-block">Send Message</button>
  </form>
</div>
</section>
<?php $content=ob_get_clean(); require dirname(__DIR__).'/layouts/frontend.php'; ?>
