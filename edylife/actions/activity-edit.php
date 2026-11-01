<?php
declare(strict_types=1); require_once __DIR__.'/../includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.app_url('index.php'));exit;} verify_csrf();
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);$title=trim((string)($_POST['title']??''));$cat=(string)($_POST['category']??'Personal');$date=(string)($_POST['activity_date']??'');$start=(string)($_POST['start_time']??'');$end=trim((string)($_POST['end_time']??''));$notes=trim((string)($_POST['notes']??''));
$cats=['Personal','Work','Fitness','Gaming','Entertainment','Rest','Errands'];
if(!$id||$title===''||mb_strlen($title)>160||mb_strlen($notes)>1000||!in_array($cat,$cats,true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$start)||($end!==''&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$end)))redirect_home('invalid');
$q=$pdo->prepare('UPDATE activities SET title=?,category=?,activity_date=?,start_time=?,end_time=?,notes=? WHERE id=?');$q->execute([$title,$cat,$date,$start.':00',$end===''?null:$end.':00',$notes?:null,$id]);redirect_home('activity-edited');
