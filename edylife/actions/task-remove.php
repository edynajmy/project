<?php
declare(strict_types=1); require_once __DIR__.'/../includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.app_url('index.php'));exit;} verify_csrf();
$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);if(!$id)redirect_home('invalid');
$q=$pdo->prepare('DELETE FROM tasks WHERE id=?');$q->execute([$id]);redirect_home('task-removed');
