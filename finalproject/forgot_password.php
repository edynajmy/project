<?php
// forgot_password.php
session_start();
require_once 'db.php';
require_once 'email_helper.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Simple session throttle to reduce repeated reset-email requests.
        $lastRequest = (int)($_SESSION['password_reset_request_at'] ?? 0);
        if ($lastRequest > 0 && (time() - $lastRequest) < 60) {
            $error = 'Please wait a moment before requesting another reset email.';
        } else {
            $_SESSION['password_reset_request_at'] = time();

            try {
                // Reporter accounts use the registered email address for password recovery.
                $stmt = $pdo->prepare("
                    SELECT user_id, full_name, email, password, role
                    FROM users
                    WHERE LOWER(email) = ?
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Always show the same success message to avoid revealing whether
                // an email address belongs to an account.
                if ($user && ($user['role'] ?? '') === 'Reporter') {
                    $expires = time() + 3600;

                    // The current password hash is used as the signing key.
                    // Once the password is changed, all previously issued reset
                    // links automatically become invalid.
                    $payload = $user['user_id'] . '|' . $expires;
                    $signature = hash_hmac('sha256', $payload, (string)$user['password']);

                    $tokenData = base64_encode($payload . '|' . $signature);
                    $token = rtrim(strtr($tokenData, '+/', '-_'), '=');

                    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '')), '/');
                    $baseUrl = 'http' . (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 's' : '')
                        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                        . ($scriptDir === '.' ? '' : $scriptDir);

                    $resetUrl = $baseUrl . '/reset_password.php?token=' . urlencode($token);

                    $safeName = htmlspecialchars((string)$user['full_name'], ENT_QUOTES, 'UTF-8');
                    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

                    $html = '
                    <div style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;color:#243b5a;">
                        <div style="background:linear-gradient(135deg,#063c8e,#0d6efd,#16aeea);padding:28px;border-radius:16px 16px 0 0;color:#fff;">
                            <h2 style="margin:0;">Resort Lost &amp; Found</h2>
                            <p style="margin:8px 0 0;opacity:.85;">Password Reset Request</p>
                        </div>
                        <div style="padding:30px;border:1px solid #e5edf6;border-top:0;border-radius:0 0 16px 16px;">
                            <p>Hello <strong>' . $safeName . '</strong>,</p>
                            <p>We received a request to reset the password for your Reporter account.</p>
                            <p style="margin:26px 0;">
                                <a href="' . $safeUrl . '" style="display:inline-block;background:#0d6efd;color:#fff;text-decoration:none;padding:13px 22px;border-radius:9px;font-weight:700;">
                                    Reset My Password
                                </a>
                            </p>
                            <p style="font-size:13px;color:#6b7c93;">
                                This password reset link will expire in <strong>1 hour</strong>.
                                If you did not request a password reset, you can safely ignore this email.
                            </p>
                            <p style="font-size:12px;color:#93a0b0;margin-top:24px;">
                                For security, do not share this reset link with anyone.
                            </p>
                        </div>
                    </div>';

                    $sent = sendReporterEmail(
                        trim((string)$user['email']),
                        trim((string)$user['full_name']),
                        'Reset Your Resort L&F Password',
                        $html
                    );

                    if (!$sent) {
                        error_log('Password reset email could not be sent for user_id ' . (int)$user['user_id']);
                    }
                }

                $success = 'If a Reporter account exists for that email address, a password reset link has been sent. Please check your inbox and spam/junk folder.';
            } catch (Throwable $e) {
                error_log('Forgot password error: ' . $e->getMessage());
                // Do not expose database/email details to the user.
                $success = 'If a Reporter account exists for that email address, a password reset link has been sent. Please check your inbox and spam/junk folder.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - Resort Lost &amp; Found</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<style>
body{margin:0;min-height:100vh;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:linear-gradient(135deg,#eef6ff 0%,#fff 48%,#edf7ff 100%);color:#09254a}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:25px}
.card-reset{width:min(520px,100%);border:0;border-radius:24px;box-shadow:0 25px 70px rgba(18,60,105,.16);overflow:hidden}
.head{padding:30px;color:#fff;background:linear-gradient(135deg,#063c8e,#0d6efd,#16aeea)}
.body{padding:34px;background:#fff}
.icon{width:58px;height:58px;display:grid;place-items:center;border-radius:16px;background:rgba(255,255,255,.16);font-size:1.5rem;margin-bottom:15px}
.form-control{height:50px;border-color:#dfe7f0}
.input-group-text{width:48px;justify-content:center;background:#f6f9fd;border-color:#dfe7f0}
.btn-reset{height:50px;border:0;border-radius:11px;background:linear-gradient(135deg,#0d6efd,#0878e8)}
.alert{font-size:.82rem;border-radius:11px}
</style>
</head>
<body>
<div class="page">
    <div class="card-reset">
        <div class="head">
            <div class="icon"><i class="fa-solid fa-key"></i></div>
            <h3 class="fw-bold mb-1">Forgot Password?</h3>
            <p class="mb-0 opacity-75 small">Reset your Reporter account password securely.</p>
        </div>
        <div class="body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <p class="text-muted small mb-4">
                Enter the email address registered to your Reporter account.
                A password reset link will be sent to that email.
            </p>

            <form method="POST">
                <label for="email" class="form-label fw-semibold small">Email Address</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fa-solid fa-envelope text-muted"></i></span>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="Enter your registered email" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-reset w-100 fw-bold">
                    <i class="fa-solid fa-paper-plane me-2"></i>Send Reset Link
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none small fw-semibold text-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Login
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
