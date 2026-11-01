<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . app_url('pages/journal.php')); exit; }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$date = trim((string)($_POST['entry_date'] ?? ''));
$title = trim((string)($_POST['title'] ?? ''));
$category = trim((string)($_POST['category'] ?? 'Personal'));
$mood = trim((string)($_POST['mood'] ?? ''));
$body = trim((string)($_POST['body'] ?? ''));
$categories = ['Personal','Work','Fitness','Entertainment','Family','Travel','Reflection','Other'];
$moods = ['','Happy','Calm','Productive','Excited','Tired','Stressed','Sad','Grateful','Mixed'];
$dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date || $title === '' || mb_strlen($title) > 160 || mb_strlen($body) > 10000 || !in_array($category,$categories,true) || !in_array($mood,$moods,true)) {
 header('Location: ' . app_url('pages/journal.php?status=invalid')); exit;
}
$mood = $mood === '' ? null : $mood; $body = $body === '' ? null : $body;
if ($id > 0) {
 $st=$pdo->prepare('UPDATE daily_entries SET entry_date=?, title=?, category=?, mood=?, body=? WHERE id=?');
 $st->execute([$date,$title,$category,$mood,$body,$id]);
 header('Location: ' . app_url('pages/journal.php?date=' . rawurlencode($date) . '&status=journal-edited')); exit;
}
$st=$pdo->prepare('INSERT INTO daily_entries (entry_date,title,category,mood,body) VALUES (?,?,?,?,?)');
$st->execute([$date,$title,$category,$mood,$body]);
header('Location: ' . app_url('pages/journal.php?date=' . rawurlencode($date) . '&status=journal-added')); exit;
