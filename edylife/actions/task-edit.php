<?php
declare(strict_types=1); require_once __DIR__.'/../includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.app_url('index.php'));exit;} verify_csrf();
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);$title=trim((string)($_POST['title']??''));$cat=(string)($_POST['category']??'Personal');$pri=(string)($_POST['priority']??'medium');$date=(string)($_POST['due_date']??'');
if(!$id||$title===''||mb_strlen($title)>160||!in_array($cat,['Personal','Work','Fitness','Gaming','Entertainment','Errands'],true)||!in_array($pri,['low','medium','high'],true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))redirect_home('invalid');
$q=$pdo->prepare('UPDATE tasks SET title=?,category=?,priority=?,due_date=? WHERE id=?');$q->execute([$title,$cat,$pri,$date,$id]);redirect_home('task-edited');
