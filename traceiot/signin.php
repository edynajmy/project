<?php
session_start();
include 'connection.php'; // Call database file
include 'alerts.php';

if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // -------------------------------------------------------------
    // CHECK 1: Search in STUDENT table
    // -------------------------------------------------------------
    $query_student = "SELECT * FROM student WHERE email='$email'";
    $result_student = mysqli_query($conn, $query_student);

    if (mysqli_num_rows($result_student) > 0) {
        $row = mysqli_fetch_assoc($result_student);

        // Verify password
        if (password_verify($password, $row['password'])) {
            // Login successful as Student
            $_SESSION['student_id'] = $row['Student_ID'];
            $_SESSION['student_name'] = $row['name'];

            $_SESSION['login_success'] = "Welcome, " . $row['name'] . "!";
            header("Location: home.php");
            exit();
        } else {
            setSweetAlert('error', 'Incorrect Password!', 'Please enter the correct password.');
            header("Location: signin.php");
            exit();
        }
    } 
    else {
        // -------------------------------------------------------------
        // CHECK 2: If not student, search in ADMIN table
        // -------------------------------------------------------------
        // Note: Assuming admin table is named 'admin'
        $query_admin = "SELECT * FROM admin WHERE email='$email'";
        $result_admin = mysqli_query($conn, $query_admin);

        if (mysqli_num_rows($result_admin) > 0) {
            $row_admin = mysqli_fetch_assoc($result_admin);

            // Verify password
            if (password_verify($password, $row_admin['password'])) {
                // Login successful as Admin
                $_SESSION['admin_ID'] = $row_admin['admin_ID']; // Use the correct admin ID name
                $_SESSION['admin_name'] = $row_admin['name'];

                header("Location: admin_home.php");
                exit();
            } else {
                setSweetAlert('error', 'Incorrect Password!', 'Please enter the correct password.');
                header("Location: signin.php");
                exit();
            }
        } 
        else {
            // If does not exist in both tables
            setSweetAlert('error', 'Email Not Registered!', 'This email is not registered in the system.');
            header("Location: signin.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Tracelot System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: #f9f9f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; }

        .window-container { width: 100vw; min-height: 100vh; background-color: #ffffff; position: relative; display: flex; flex-direction: column; overflow: hidden; }
        .top-bar { height: 40px; border-bottom: 1px solid #ddd; display: flex; justify-content: flex-end; align-items: center; padding: 0 15px; }
        .close-btn { width: 20px; height: 20px; background-color: #ff4d4d; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 12px; font-weight: bold; cursor: pointer; }

        .main-content { display: flex; flex: 1; padding: 40px; align-items: center; justify-content: space-around; }
        .logos-section { display: flex; align-items: center; gap: 20px; }
        .logos-section img { max-width: 250px; object-fit: contain; }
        .divider { width: 2px; height: 120px; background-color: #e0e0e0; }

        .form-container { width: 380px; border: 3px solid #000; padding: 40px 30px; background-color: #fff; }
        h2.title { text-align: center; font-size: 26px; font-weight: 600; margin-bottom: 10px; }
        .subtitle { text-align: center; font-size: 12px; color: #555; margin-bottom: 30px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 11px; font-weight: 600; color: #555; letter-spacing: 1px; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 12px 10px; border: 1px solid #ccc; border-radius: 2px; font-size: 13px; color: #333; outline: none; }
        .form-group input:focus { border-color: #888; }

        .btn-signin { width: 100%; background-color: #000; color: #fff; padding: 12px; border: none; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: background 0.2s; }
        .btn-signin:hover { background-color: #333; }

        .or-divider { text-align: center; font-size: 12px; color: #666; margin: 15px 0; }

        /* Adjusted Google Button CSS to fit <a> tag */
        .btn-google { width: 100%; background-color: #4285F4; color: #fff; padding: 2px; border: none; font-size: 13px; font-weight: 500; cursor: pointer; display: flex; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.2); text-decoration: none; }
        .google-icon { background-color: #fff; padding: 8px; display: flex; align-items: center; justify-content: center; border-radius: 1px; }
        .google-icon img { width: 18px; height: 18px; }
        .google-text { flex: 1; text-align: center; padding-right: 34px; color: #fff; }

        .copyright-pill { position: absolute; bottom: 20px; right: 20px; background-color: #fff; padding: 12px 25px; border-radius: 30px; font-size: 12px; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

    <div class="window-container">
        <div class="top-bar">
            <div class="close-btn">✖</div>
        </div>

        <div class="main-content">
            <div class="logos-section">
                <img src="uitmlogo.png" alt="Universiti Teknologi MARA">
                <div class="divider"></div>
                <img src="traceiotlogo.jpeg" alt="Tracelot System">
            </div>

            <div class="form-container">
                <h2 class="title">Sign In</h2>
                <div class="subtitle">Don't have an account? <a href="index.php" style="color: inherit; text-decoration: none; font-weight: 600;">Sign Up</a></div>

                <form action="signin.php" method="POST">
                    <div class="form-group">
                        <label>EMAIL</label>
                        <input type="email" name="email" placeholder="nasilemak@student.uitm.edu.my" required>
                    </div>

                    <div class="form-group">
                        <label>PASSWORD</label>
                        <input type="password" name="password" placeholder="******" required>
                    </div>

                    <button type="submit" name="login" class="btn-signin">Sign In</button>
                </form>

                <div class="or-divider">or</div>

                <a href="google_callback.php" class="btn-google">
                    <div class="google-icon">
                        <img src="googlelogo.jpeg" alt="Google G">
                    </div>
                    <div class="google-text">Sign in with Google</div>
                </a>

                </div>
        </div>

        <div class="copyright-pill">
            © 2026 Tracelot System
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php showSweetAlert(); ?>

</body>
</html>
