<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('index.php'));
    exit;
}
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    redirect_home('invalid');
}
$stmt = $pdo->prepare('UPDATE tasks SET is_done = CASE WHEN is_done = 1 THEN 0 ELSE 1 END WHERE id = ?');
$stmt->execute([$id]);
redirect_home('task-updated');
