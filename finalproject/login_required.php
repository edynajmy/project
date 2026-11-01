<?php
session_start();

// Get requested page
$redirect = $_GET['redirect'] ?? 'reporter_dashboard.php';

// Only allow internal pages
$allowed_pages = [
    'report_lost.php',
    'report_found.php',
    'reporter_dashboard.php'
];

if (!in_array($redirect, $allowed_pages, true)) {
    $redirect = 'reporter_dashboard.php';
}

// If already logged in, go directly to requested page
if (isset($_SESSION['user_id'])) {
    header("Location: " . $redirect);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login Required - Resort L&F</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0866ff, #0d47c7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }

        .auth-card {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 18px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .auth-icon {
            width: 80px;
            height: 80px;
            background: #ffc107;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 35px;
            color: #222;
        }

        .btn-auth {
            width: 100%;
            padding: 13px;
            font-weight: 600;
            border-radius: 10px;
            margin-bottom: 12px;
        }
    </style>
</head>

<body>

<div class="auth-card">

    <div class="auth-icon">
        <i class="fa-solid fa-user-lock"></i>
    </div>

    <h2 class="fw-bold mb-3">
        Login Required
    </h2>

    <p class="text-muted mb-4">
        Please create an account or log in before reporting a lost or found item.
    </p>

    <!-- LOGIN -->
    <a href="login.php?redirect=<?= urlencode($redirect); ?>"
       class="btn btn-primary btn-auth">
        <i class="fa-solid fa-right-to-bracket me-2"></i>
        Login
    </a>

    <!-- REGISTER -->
    <a href="register.php?redirect=<?= urlencode($redirect); ?>"
       class="btn btn-warning btn-auth">
        <i class="fa-solid fa-user-plus me-2"></i>
        Create Account
    </a>

    <!-- BACK -->
    <a href="index.php"
       class="btn btn-outline-secondary btn-auth mt-2">
        <i class="fa-solid fa-arrow-left me-2"></i>
        Back to Home
    </a>

</div>

</body>
</html>