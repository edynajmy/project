<?php
session_start();
require_once 'auth_check.php';
require_once 'email_helper.php';
checkAccess(['Reporter']);

$userName = trim((string)($_SESSION['user_name'] ?? 'Reporter'));
$userEmail = '';

try {
    require_once 'db.php';
    $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $userEmail = trim((string)($row['email'] ?? ''));
} catch (Throwable $e) {
    $error = 'Database error: ' . $e->getMessage();
}

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['email'] ?? $userEmail);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result = ['ok' => false, 'message' => 'Please enter a valid email address.'];
    } else {
        $html = buildReporterEmail(
            $userName,
            'Email Test Item',
            'TEST-2026-EMAIL',
            'Test Category',
            'Test Location',
            date('Y-m-d'),
            'Test Email',
            'Lost'
        );
        $ok = sendReporterEmail($to, $userName, 'Resort Lost & Found - Email Test', $html);
        $result = $ok
            ? ['ok' => true, 'message' => 'Test email sent successfully to ' . $to . '. Check Inbox and Spam/Junk.']
            : ['ok' => false, 'message' => 'Test email failed. Check email_config.php, Composer/PHPMailer, and the PHP error log.'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Email Test - Resort Lost &amp; Found</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:720px">
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <h3 class="fw-bold mb-2">Email Notification Test</h3>
      <p class="text-muted">Send a test email using the same SMTP configuration used by Lost/Found submissions.</p>
      <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($result): ?><div class="alert alert-<?= $result['ok'] ? 'success' : 'danger' ?>"><?= htmlspecialchars($result['message']) ?></div><?php endif; ?>
      <form method="post">
        <label class="form-label fw-semibold">Test recipient email</label>
        <input type="email" name="email" class="form-control mb-3" value="<?= htmlspecialchars($userEmail) ?>" required>
        <button class="btn btn-primary fw-bold" type="submit">Send Test Email</button>
        <a href="reporter_dashboard.php" class="btn btn-light ms-2">Back to Dashboard</a>
      </form>
    </div>
  </div>
</div>
</body>
</html>
