<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'cover=updated'); exit; }
verify_csrf();
$returnTo = (string)($_POST['return_to'] ?? 'index.php');
$allowedReturn = ['index.php', 'pages/activities.php', 'pages/work-planner.php'];
if (!in_array($returnTo, $allowedReturn, true)) { $returnTo = 'index.php'; }
$returnUrl = app_url($returnTo);
$file = $_FILES['cover'] ?? null;
if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'cover=upload-failed'); exit;
}
$tmp = $file['tmp_name']; $size = (int)($file['size'] ?? 0);
$map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$fi = new finfo(FILEINFO_MIME_TYPE); $mime = $fi->file($tmp);
if ($size < 1 || $size > 8 * 1024 * 1024 || !isset($map[$mime]) || @getimagesize($tmp) === false) {
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'cover=invalid'); exit;
}
$dir = dirname(__DIR__) . '/uploads/cover';
if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) { http_response_code(500); exit('Unable to create cover upload directory.'); }
$extension = $map[$mime]; $target = $dir . '/cover.' . $extension;
foreach (glob($dir . '/cover.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $old) { if ($old !== $target) @unlink($old); }
if (!move_uploaded_file($tmp, $target)) { header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'cover=upload-failed'); exit; }
header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'cover=updated'); exit;
