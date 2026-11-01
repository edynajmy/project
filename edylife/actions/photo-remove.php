<?php
declare(strict_types=1);require_once __DIR__.'/../includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.app_url('pages/gallery.php'));exit;}verify_csrf();
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);if(!$id){header('Location: '.app_url('pages/gallery.php?status=invalid'));exit;}
$q=$pdo->prepare('SELECT file_name FROM photos WHERE id=?');$q->execute([$id]);$f=$q->fetch();if($f){$d=$pdo->prepare('DELETE FROM photos WHERE id=?');$d->execute([$id]);$p=dirname(__DIR__).'/uploads/photos/'.$f['file_name'];if(is_file($p))@unlink($p);}
header('Location: '.app_url('pages/gallery.php?status=photo-removed'));exit;
