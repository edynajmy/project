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
$priority = (string)($_POST['priority'] ?? 'medium');
$dueDate = (string)($_POST['due_date'] ?? date('Y-m-d'));
$allowedCategories = ['Personal','Work','Fitness','Gaming','Entertainment','Errands'];
if ($title === '' || mb_strlen($title) > 160 || !in_array($priority, ['low','medium','high'], true) ||
    !in_array($category, $allowedCategories, true) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
    redirect_home('invalid');
}
$stmt = $pdo->prepare('INSERT INTO tasks (title, category, priority, due_date) VALUES (?, ?, ?, ?)');
$stmt->execute([$title, $category, $priority, $dueDate]);
redirect_home('task-added');
