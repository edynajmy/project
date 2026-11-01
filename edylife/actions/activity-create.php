<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('index.php'));
    exit;
}
verify_csrf();

$title = trim((string)($_POST['title'] ?? ''));
$category = trim((string)($_POST['category'] ?? 'Personal'));
$date = (string)($_POST['activity_date'] ?? date('Y-m-d'));
$start = (string)($_POST['start_time'] ?? '');
$end = trim((string)($_POST['end_time'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$allowedCategories = ['Personal','Work','Fitness','Gaming','Entertainment','Rest','Errands'];
$validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
$validStart = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) === 1;
$validEnd = $end === '' || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) === 1;

if ($title === '' || mb_strlen($title) > 160 || mb_strlen($notes) > 1000 ||
    !in_array($category, $allowedCategories, true) || !$validDate || !$validStart || !$validEnd) {
    redirect_home('invalid');
}
$endValue = $end === '' ? null : $end . ':00';
$stmt = $pdo->prepare('INSERT INTO activities (title, category, activity_date, start_time, end_time, notes) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([$title, $category, $date, $start . ':00', $endValue, $notes === '' ? null : $notes]);
redirect_home('activity-added');
