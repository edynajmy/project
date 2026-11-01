<?php
// register.php
session_start();
require_once 'db.php';

// Redirect user if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'Admin':
            header("Location: admin_dashboard.php");
            break;
        case 'Recorder':
            header("Location: recorder_dashboard.php");
            break;
        case 'Reporter':
        default:
            header("Location: reporter_dashboard.php");
            break;
    }
    exit();
}

$error = '';
$full_name = '';
$email = '';
$phone = '';

$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'reporter_dashboard.php';
$allowed_redirects = ['report_lost.php', 'report_found.php', 'reporter_dashboard.php'];
if (!in_array($redirect, $allowed_redirects, true)) {
    $redirect = 'reporter_dashboard.php';
}

// --------------------------------------------------------------------
// 1. PROCESS REGISTRATION SUBMISSION
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields marked with an asterisk (*).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match. Please re-enter your password.";
    } else {
        try {
            // Check if email already exists
            $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->execute([$email]);

            if ($checkStmt->fetch()) {
                $error = "An account with this email address already exists. Please log in instead.";
            } else {
                // Securely hash the password
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                // Insert new Reporter user (Matches your database schema exactly)
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (full_name, email, phone_number, password, role)
                    VALUES (?, ?, ?, ?, 'Reporter')
                ");
                $insertStmt->execute([$full_name, $email, $phone, $hashedPassword]);

                $_SESSION['success'] = "Account created successfully! You can now log in.";
                header("Location: login.php?redirect=" . urlencode($redirect));
                exit();
            }
        } catch (\PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            // Show exact database error for troubleshooting
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Resort Lost &amp; Found System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

    <style>
        :root{
            --blue:#0d6efd;
            --blue2:#0878e8;
            --navy:#09254a;
            --muted:#6b7c93;
        }

        *{box-sizing:border-box}

        body{
            margin:0;
            min-height:100vh;
            font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
            color:var(--navy);
            background:
                radial-gradient(circle at 10% 10%,rgba(13,110,253,.16),transparent 28%),
                radial-gradient(circle at 90% 90%,rgba(38,190,255,.16),transparent 30%),
                linear-gradient(135deg,#eef6ff 0%,#fff 48%,#edf7ff 100%);
        }

        .register-page{
            min-height:100vh;
            padding:30px;
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
            overflow:hidden;
        }

        .register-page:before,
        .register-page:after{
            content:"";
            position:absolute;
            border-radius:50%;
            pointer-events:none;
        }

        .register-page:before{
            width:500px;height:500px;
            left:-250px;top:-250px;
            border:70px solid rgba(13,110,253,.035);
        }

        .register-page:after{
            width:600px;height:600px;
            right:-330px;bottom:-330px;
            border:90px solid rgba(13,110,253,.035);
        }

        .register-shell{
            position:relative;
            z-index:2;
            width:min(1180px,100%);
            min-height:690px;
            display:grid;
            grid-template-columns:1.08fr .92fr;
            overflow:hidden;
            border-radius:30px;
            background:#fff;
            box-shadow:0 30px 90px rgba(18,60,105,.18);
            border:1px solid rgba(255,255,255,.9);
        }

        /* LEFT RESORT PANEL */
        .welcome-panel{
            position:relative;
            overflow:hidden;
            padding:52px 58px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            color:#fff;
            background:
                linear-gradient(145deg,#063c8e 0%,#0d6efd 55%,#16aeea 100%);
        }

        .welcome-panel:before{
            content:"";
            position:absolute;
            width:520px;height:520px;
            right:-270px;top:-220px;
            border-radius:50%;
            border:1px solid rgba(255,255,255,.18);
            box-shadow:
                0 0 0 55px rgba(255,255,255,.035),
                0 0 0 110px rgba(255,255,255,.025);
        }

        .welcome-panel:after{
            content:"";
            position:absolute;
            width:850px;height:250px;
            left:-160px;bottom:-170px;
            border-radius:50%;
            background:rgba(255,255,255,.10);
            transform:rotate(-8deg);
        }

        .brand{
            position:relative;
            z-index:2;
            display:flex;
            align-items:center;
            gap:13px;
            font-size:1.25rem;
            font-weight:800;
        }

        .brand-icon{
            width:47px;height:47px;
            display:grid;place-items:center;
            border-radius:14px;
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.25);
            backdrop-filter:blur(8px);
        }

        .brand small{
            display:block;
            margin-top:2px;
            font-size:.62rem;
            letter-spacing:1.6px;
            text-transform:uppercase;
            opacity:.7;
        }

        .welcome-content{
            position:relative;
            z-index:2;
            max-width:590px;
            margin-top:-15px;
        }

        .eyebrow{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 13px;
            border-radius:999px;
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.2);
            backdrop-filter:blur(10px);
            font-size:.72rem;
            font-weight:800;
            letter-spacing:.6px;
        }

        .welcome-content h1{
            margin:22px 0 15px;
            font-size:clamp(2.8rem,5vw,4.7rem);
            line-height:.97;
            letter-spacing:-3px;
            font-weight:850;
        }

        .welcome-content h1 span{color:#aee7ff}

        .welcome-content p{
            max-width:500px;
            margin:0;
            font-size:1rem;
            line-height:1.7;
            color:rgba(255,255,255,.78);
        }

        .feature-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:12px;
            margin-top:34px;
        }

        .feature{
            min-height:112px;
            padding:17px;
            border-radius:18px;
            background:rgba(255,255,255,.10);
            border:1px solid rgba(255,255,255,.15);
            backdrop-filter:blur(10px);
            transition:.25s ease;
        }

        .feature:hover{
            transform:translateY(-4px);
            background:rgba(255,255,255,.15);
        }

        .feature i{
            display:grid;
            place-items:center;
            width:35px;height:35px;
            margin-bottom:12px;
            border-radius:10px;
            background:#fff;
            color:var(--blue);
        }

        .feature strong{display:block;font-size:.82rem}
        .feature span{
            display:block;
            margin-top:4px;
            font-size:.68rem;
            line-height:1.4;
            color:rgba(255,255,255,.65);
        }

        .quote{
            position:relative;
            z-index:2;
            display:flex;
            align-items:center;
            gap:13px;
            font-size:.75rem;
            color:rgba(255,255,255,.65);
        }

        .quote-line{
            width:38px;
            height:2px;
            background:#8ddcff;
            border-radius:2px;
        }

        /* REGISTER PANEL */
        .form-panel{
            position:relative;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:42px 48px;
            background:rgba(255,255,255,.97);
        }

        .form-panel:before{
            content:"";
            position:absolute;
            width:240px;height:240px;
            right:-120px;top:-120px;
            border-radius:50%;
            background:rgba(13,110,253,.055);
        }

        .register-card{
            position:relative;
            z-index:2;
            width:100%;
            max-width:445px;
            border:0;
            background:transparent;
            box-shadow:none;
        }

        .register-header{
            padding:0 0 25px!important;
            background:transparent!important;
            color:var(--navy)!important;
            border-radius:0!important;
            text-align:left!important;
        }

        .register-header h4{
            display:flex;
            align-items:center;
            gap:11px;
            margin:0 0 7px!important;
            font-size:1.65rem;
            color:var(--navy);
        }

        .register-header h4 i{
            width:44px;height:44px;
            display:grid;
            place-items:center;
            border-radius:13px;
            color:#fff;
            background:linear-gradient(135deg,var(--blue),#25b7ff);
            box-shadow:0 10px 24px rgba(13,110,253,.25);
        }

        .register-header p{
            margin-left:55px!important;
            color:var(--muted)!important;
            opacity:1!important;
            font-size:.78rem!important;
        }

        .card-body{padding:0!important}

        .alert{
            border-radius:12px;
            font-size:.75rem;
        }

        .form-label{
            color:#243b5a;
            font-size:.76rem!important;
            margin-bottom:6px;
        }

        .input-group{
            border-radius:12px;
            box-shadow:0 7px 22px rgba(24,61,100,.055);
        }

        .input-group-text{
            width:46px;
            justify-content:center;
            background:#f6f9fd!important;
            border-color:#dfe7f0;
            border-radius:12px 0 0 12px!important;
        }

        .form-control{
            height:47px;
            border-color:#dfe7f0;
            border-radius:0 12px 12px 0!important;
            font-size:.84rem;
        }

        .form-control:focus{
            border-color:#86b7fe;
            box-shadow:0 0 0 .20rem rgba(13,110,253,.10);
        }

        .register-submit{
            height:51px;
            border:0;
            border-radius:12px;
            background:linear-gradient(135deg,var(--blue),var(--blue2));
            box-shadow:0 12px 25px rgba(13,110,253,.22);
            transition:.25s ease;
        }

        .register-submit:hover{
            transform:translateY(-2px);
            box-shadow:0 16px 30px rgba(13,110,253,.28);
        }

        .login-box{
            margin-top:22px;
            padding:17px;
            text-align:center;
            border:1px solid #e5edf6;
            border-radius:15px;
            background:#f8fbff;
        }

        .login-box a{
            display:inline-block;
            margin-top:5px;
        }

        .security-note{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:7px;
            margin-top:17px;
            color:#93a0b0;
            font-size:.65rem;
        }

        .security-note i{color:#16a06a}

        .required-note{
            font-size:.66rem;
            color:#8b99aa;
            margin-bottom:16px;
        }

        .required-note span{color:#dc3545;font-weight:800}

        @media(max-width:900px){
            .register-page{padding:20px}
            .register-shell{
                grid-template-columns:1fr;
                min-height:auto;
                max-width:620px;
            }
            .welcome-panel{
                min-height:430px;
                padding:35px;
            }
            .welcome-content{margin-top:25px}
            .form-panel{padding:40px 32px}
        }

        @media(max-width:560px){
            .register-page{padding:0}
            .register-shell{
                min-height:100vh;
                border-radius:0;
            }
            .welcome-panel{
                min-height:390px;
                padding:28px 24px;
            }
            .welcome-content h1{
                font-size:2.6rem;
                letter-spacing:-2px;
            }
            .welcome-content p{font-size:.86rem}
            .feature-grid{grid-template-columns:1fr 1fr}
            .feature:last-child{display:none}
            .form-panel{padding:35px 24px}
        }
    </style>
</head>

<body>
<div class="register-page">
    <div class="register-shell">

        <section class="welcome-panel">
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div>
                    Resort L&amp;F
                    <small>Safe Items. Happier Guests.</small>
                </div>
            </div>

            <div class="welcome-content">
                <div class="eyebrow">
                    <i class="fa-solid fa-user-shield"></i>
                    JOIN THE RESORT PORTAL
                </div>

                <h1>Your journey<br><span>starts here.</span></h1>

                <p>
                    Create your Reporter account and enjoy a simpler way to
                    report lost or found items, follow updates and manage your claims.
                </p>

                <div class="feature-grid">
                    <div class="feature">
                        <i class="fa-solid fa-user-plus"></i>
                        <strong>Quick Sign Up</strong>
                        <span>Create your account in just a few steps.</span>
                    </div>
                    <div class="feature">
                        <i class="fa-solid fa-bell"></i>
                        <strong>Stay Updated</strong>
                        <span>Keep track of your item reports and claims.</span>
                    </div>
                    <div class="feature">
                        <i class="fa-solid fa-heart"></i>
                        <strong>Guest First</strong>
                        <span>A smoother experience from report to recovery.</span>
                    </div>
                </div>
            </div>

            <div class="quote">
                <span class="quote-line"></span>
                <span>Because every belonging matters.</span>
            </div>
        </section>

        <section class="form-panel">
            <div class="card register-card">

                <div class="register-header">
                    <h4>
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Create your account</span>
                    </h4>
                    <p>Register to access the Resort L&amp;F Reporter Portal</p>
                </div>

                <div class="card-body">

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show py-2 small mb-3" role="alert">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            <?= htmlspecialchars($error); ?>
                            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="required-note">
                        Fields marked with <span>*</span> are required.
                    </div>

                    <form action="register.php" method="POST" novalidate>
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect ?? 'reporter_dashboard.php', ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-semibold">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fa-solid fa-user text-muted"></i>
                                </span>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       value="<?= htmlspecialchars($full_name); ?>"
                                       placeholder="Enter your full name" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fa-solid fa-envelope text-muted"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($email); ?>"
                                       placeholder="Enter your email address" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label fw-semibold">
                                Phone Number <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fa-solid fa-phone text-muted"></i>
                                </span>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       value="<?= htmlspecialchars($phone); ?>"
                                       placeholder="Enter your phone number">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">
                                Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fa-solid fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="At least 6 characters" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fa-solid fa-check-double text-muted"></i>
                                </span>
                                <input type="password" class="form-control" id="confirm_password"
                                       name="confirm_password"
                                       placeholder="Re-enter your password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold register-submit">
                            <i class="fa-solid fa-user-check me-2"></i>
                            Create Reporter Account
                        </button>
                    </form>

                    <div class="login-box">
                        <span class="small text-muted">Already have an account?</span>
                        <br>
                        <a href="login.php?redirect=<?= urlencode($redirect ?? 'reporter_dashboard.php'); ?>"
                           class="small fw-bold text-decoration-none text-primary">
                            Sign In to Your Account
                        </a>
                    </div>

                    <div class="security-note">
                        <i class="fa-solid fa-circle-check"></i>
                        Secure resort portal • Your information is protected
                    </div>

                </div>
            </div>
        </section>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
