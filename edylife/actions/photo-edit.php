<?php
declare(strict_types=1);require_once __DIR__.'/../includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.app_url('pages/gallery.php'));exit;}verify_csrf();
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);$folder=filter_input(INPUT_POST,'folder_id',FILTER_VALIDATE_INT);$caption=trim((string)($_POST['caption']??''));$date=trim((string)($_POST['taken_on']??''));
if(!$id||!$folder||mb_strlen($caption)>200||($date!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))){header('Location: '.app_url('pages/gallery.php?status=invalid'));exit;}
$q=$pdo->prepare('SELECT id FROM photo_folders WHERE id=?');$q->execute([$folder]);if(!$q->fetch()){header('Location: '.app_url('pages/gallery.php?status=invalid'));exit;}
$q=$pdo->prepare('UPDATE photos SET folder_id=?,caption=?,taken_on=? WHERE id=?');$q->execute([$folder,$caption?:null,$date?:null,$id]);header('Location: '.app_url('pages/gallery.php?folder='.$folder.'&status=photo-edited'));exit;
