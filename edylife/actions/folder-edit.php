<?php
declare(strict_types=1);require_once __DIR__.'/../includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.app_url('pages/gallery.php'));exit;}verify_csrf();
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);$name=trim((string)($_POST['name']??''));$desc=trim((string)($_POST['description']??''));if(!$id||$name===''||mb_strlen($name)>80||mb_strlen($desc)>240){header('Location: '.app_url('pages/gallery.php?status=invalid'));exit;}
try{$q=$pdo->prepare('UPDATE photo_folders SET name=?,description=? WHERE id=?');$q->execute([$name,$desc?:null,$id]);}catch(PDOException $e){header('Location: '.app_url('pages/gallery.php?status=folder-exists'));exit;}
header('Location: '.app_url('pages/gallery.php?status=folder-edited'));exit;
