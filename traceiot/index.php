<?php
session_start();
include 'connection.php'; // Call database file

$message = ""; 

if (isset($_POST['register'])) {
    // Get all data from the form
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    $name       = mysqli_real_escape_string($conn, $_POST['name']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_num  = mysqli_real_escape_string($conn, $_POST['phone_num']);
    $faculty    = mysqli_real_escape_string($conn, $_POST['faculty']); // NEW DATA: Capture Faculty
    $password   = mysqli_real_escape_string($conn, $_POST['password']);

    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if Student_ID or email already exists in the 'student' table
    $check_query = "SELECT * FROM student WHERE Student_ID='$student_id' OR email='$email'";
    $result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($result) > 0) {
        $message = "<div style='color: red; text-align: center; margin-bottom: 10px; font-weight: 600; font-size: 13px;'>This Student ID or Email is already registered!</div>";
    } else {
        // Insert data into the 'student' table (including 'faculty' column)
        $sql = "INSERT INTO student (Student_ID, name, email, phone_num, faculty, password) 
                VALUES ('$student_id', '$name', '$email', '$phone_num', '$faculty', '$hashed_password')";
        
        if (mysqli_query($conn, $sql)) {
            $message = "<div style='color: green; text-align: center; margin-bottom: 10px; font-weight: 600; font-size: 13px;'>Account created successfully! Please <a href='signin.php' style='color: #000; text-decoration: underline;'>Sign In</a>.</div>";
        } else {
            $message = "<div style='color: red; text-align: center; margin-bottom: 10px; font-weight: 600; font-size: 13px;'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Tracelot System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f9f9f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Main Window Container */
        .window-container {
            width: 100vw;
            min-height: 100vh;
            background-color: #ffffff;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Top Bar with Close Button */
        .top-bar {
            height: 40px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 0 15px;
        }

        .close-btn {
            width: 20px;
            height: 20px;
            background-color: #ff4d4d;
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
        }

        /* Main Content Layout */
        .main-content {
            display: flex;
            flex: 1;
            padding: 40px;
            align-items: center;
            justify-content: space-around;
        }

        /* Left Side: Logos */
        .logos-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logos-section img {
            max-width: 250px;
            object-fit: contain;
        }

        .divider {
            width: 2px;
            height: 120px;
            background-color: #e0e0e0;
        }

        /* Right Side: Form Box */
        .form-container {
            width: 380px;
            border: 3px solid #000;
            padding: 40px 30px;
            background-color: #fff;
        }

        h2.title {
            text-align: center;
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            font-size: 12px;
            color: #555;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #555;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 10px;
            border: 1px solid #ccc;
            border-radius: 2px;
            font-size: 13px;
            color: #333;
            outline: none;
            background-color: #fff;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #888;
        }

        .btn-signup {
            width: 100%;
            background-color: #000;
            color: #fff;
            padding: 12px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }

        .btn-signup:hover {
            background-color: #333;
        }

        .or-divider {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin: 15px 0;
        }

        /* Adjusted Google Button CSS to fit <a> tag */
        .btn-google {
            width: 100%;
            background-color: #4285F4;
            color: #fff;
            padding: 2px;
            border: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
            text-decoration: none;
        }

        .google-icon {
            background-color: #fff;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1px;
        }

        .google-icon img {
            width: 18px;
            height: 18px;
        }

        .google-text {
            flex: 1;
            text-align: center;
            padding-right: 34px;
            color: #fff;
        }

        /* Bottom Right Copyright Pill */
        .copyright-pill {
            position: absolute;
            bottom: 20px; 
            right: 20px;
            background-color: #fff;
            padding: 12px 25px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
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
                <h2 class="title">Create Account</h2>
                <div class="subtitle">Already Registered? <a href="signin.php" style="color: inherit; text-decoration: none; font-weight: 600;">Sign In</a></div>

                <?php echo $message; ?>

                <form action="index.php" method="POST">
                    
                    <div class="form-group">
                        <label>STUDENT ID</label>
                        <input type="text" name="student_id" placeholder="2024123456" required>
                    </div>

                    <div class="form-group">
                        <label>NAME</label>
                        <input type="text" name="name" placeholder="Name" required>
                    </div>

                    <div class="form-group">
                        <label>EMAIL</label>
                        <input type="email" name="email" placeholder="studentID@student.uitm.edu.my" required>
                    </div>

                    <div class="form-group">
                        <label>FACULTY</label>
                        <select name="faculty" required>
                            <option value="" disabled selected>Choose Faculty</option>
                            <option value="Computer and Mathematical Sciences">FSKM (Computer and Mathematical Sciences)</option>
                            <option value="Administrative Science and Policy Studies">FSPPP (Administrative Science and Policy Studies)</option>
                            <option value="Business and Management">FPP (Business and Management)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>PHONE NUMBER</label>
                        <input type="text" name="phone_num" placeholder="012 3456 7890" required>
                    </div>

                    <div class="form-group">
                        <label>PASSWORD</label>
                        <input type="password" name="password" placeholder="******" required>
                    </div>

                    <button type="submit" name="register" class="btn-signup">Sign Up</button>
                </form>
                
                <div class="or-divider">or</div>

                <a href="google_callback.php" class="btn-google">
                    <div class="google-icon">
                        <img src="googlelogo.jpeg" alt="Google G">
                    </div>
                    <div class="google-text">Sign up with Google</div>
                </a>
            </div>
        </div> 

        <div class="copyright-pill">
            © 2026 Tracelot System
        </div>
    </div>

</body>
</html>
