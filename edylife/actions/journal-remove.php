<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . app_url('pages/journal.php')); exit; }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: ' . app_url('pages/journal.php?status=invalid')); exit; }
$st=$pdo->prepare('DELETE FROM daily_entries WHERE id=?'); $st->execute([$id]);
$date=(string)($_POST['entry_date'] ?? date('Y-m-d'));
header('Location: ' . app_url('pages/journal.php?date=' . rawurlencode($date) . '&status=journal-removed')); exit;
