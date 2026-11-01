<?php
declare(strict_types=1);require_once __DIR__.'/../includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.app_url('pages/gallery.php'));exit;}verify_csrf();
$folder=filter_input(INPUT_POST,'folder_id',FILTER_VALIDATE_INT);$caption=trim((string)($_POST['caption']??''));$date=trim((string)($_POST['taken_on']??''));
if(!$folder||mb_strlen($caption)>200||($date!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))||!isset($_FILES['photos']['name'])||!is_array($_FILES['photos']['name'])){header('Location: '.app_url('pages/gallery.php?status=invalid'));exit;}
$q=$pdo->prepare('SELECT id FROM photo_folders WHERE id=?');$q->execute([$folder]);if(!$q->fetch()){header('Location: '.app_url('pages/gallery.php?status=invalid'));exit;}
$dir=dirname(__DIR__).'/uploads/photos';if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir)){http_response_code(500);exit('Unable to create upload directory.');}
$map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$fi=new finfo(FILEINFO_MIME_TYPE);$n=count($_FILES['photos']['name']);if($n<1||$n>10){header('Location: '.app_url('pages/gallery.php?status=invalid'));exit;}$ok=0;
for($i=0;$i<$n;$i++){if(($_FILES['photos']['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)continue;$tmp=$_FILES['photos']['tmp_name'][$i];$size=(int)($_FILES['photos']['size'][$i]??0);if($size<1||$size>5*1024*1024||!is_uploaded_file($tmp))continue;$mime=$fi->file($tmp);if(!isset($map[$mime])||@getimagesize($tmp)===false)continue;$fn=bin2hex(random_bytes(20)).'.'.$map[$mime];if(!move_uploaded_file($tmp,$dir.'/'.$fn))continue;$original=mb_substr(basename((string)$_FILES['photos']['name'][$i]),0,255);
try{$q=$pdo->prepare('INSERT INTO photos(folder_id,file_name,original_name,caption,taken_on) VALUES(?,?,?,?,?)');$q->execute([$folder,$fn,$original,$caption?:null,$date?:null]);$ok++;}catch(Throwable $e){@unlink($dir.'/'.$fn);}
}
header('Location: '.app_url('pages/gallery.php?folder='.$folder.'&status='.($ok?'photos-uploaded':'upload-failed')));exit;
