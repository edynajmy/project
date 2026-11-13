<?php
session_start();
include 'connection.php';

// UPDATED FOLDER PATH BASED ON YOUR DIRECTORY STRUCTURE
require_once 'google-api (1)/google-api/vendor/autoload.php';

// --- INSERT YOUR GOOGLE KEYS HERE ---
$clientID = '640587794999-5g44bgg0vpf0s9lc4pq6ocakje7ci6di.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/testing/google_callback.php';

// Setup Google Client
$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if(!isset($token['error'])){
        $client->setAccessToken($token['access_token']);
        $_SESSION['access_token'] = $token['access_token'];

        // Fetch Google profile data
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $email = mysqli_real_escape_string($conn, $google_account_info->email);
        
        // Extract Student ID directly from email
        $extracted_student_id = explode('@', $email)[0];

        // --- UITM EMAIL FILTER ONLY ---
        if (!preg_match("/@student\.uitm\.edu\.my$/", $email) && !preg_match("/@uitm\.edu\.my$/", $email)) {
            echo "<script>alert('Please use your official UiTM email (@student.uitm.edu.my) to log in!'); window.location.href='signin.php';</script>";
            exit();
        }

        // --- STEP 1: CHECK TRACEIOT DATABASE (Has the student logged in before?) ---
        $check_local = "SELECT * FROM student WHERE Student_ID = '$extracted_student_id'";
        $result_local = mysqli_query($conn, $check_local);

        if (mysqli_num_rows($result_local) > 0) {
            // Student exists in TraceIoT. Log them in directly.
            $row = mysqli_fetch_assoc($result_local);
            
            unset($_SESSION['admin_ID']); // Security measure
            unset($_SESSION['admin_name']);
            
            $_SESSION['student_id'] = $row['Student_ID'];
            $_SESSION['student_name'] = $row['name'];

           $_SESSION['login_success'] = "Welcome, " . $row['name'] . "!";
            
            header("Location: home.php");
            exit();
            
        } else {
            // --- STEP 2: NOT IN TRACEIOT. CHECK LECTURER'S MASTER DATABASE (dbstudentsphg) ---
            // Note: We query the dbstudentsphg database directly using the same XAMPP connection
            $check_master = "SELECT * FROM dbstudentsphg.students WHERE studentno = '$extracted_student_id'";
            $result_master = mysqli_query($conn, $check_master);

            if (mysqli_num_rows($result_master) > 0) {
                // SUCCESS! Student is a verified UiTM Raub student.
                $master_data = mysqli_fetch_assoc($result_master);
                
                // Copy their details from the lecturer's database
                $name = mysqli_real_escape_string($conn, $master_data['studentname']);
                $phone = mysqli_real_escape_string($conn, $master_data['studentphoneno']);
                $program_code = mysqli_real_escape_string($conn, $master_data['programcode']); 
                $dummy_password = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
                
                // --- STEP 3: SYNC DATA TO TRACEIOT DATABASE ---
                $insert_query = "INSERT INTO student (Student_ID, name, email, phone_num, faculty, password) 
                                 VALUES ('$extracted_student_id', '$name', '$email', '$phone', '$program_code', '$dummy_password')";
                
                if (mysqli_query($conn, $insert_query)) {
                    unset($_SESSION['admin_ID']);
                    
                    $_SESSION['student_id'] = $extracted_student_id;
                    $_SESSION['student_name'] = $name;

                    $_SESSION['login_success'] = "Welcome, " . $row['name'] . "!";
                    
                    header("Location: home.php");
                    exit();
                } else {
                    echo "Database Sync Error: " . mysqli_error($conn);
                }

            } else {
                // FAILED! Student is NOT in the lecturer's database (Outsider/Other Campus)
                echo "<script>alert('Access Denied! Your Student ID ($extracted_student_id) is not registered in the UiTM Raub database. Please contact the admin.'); window.location.href='signin.php';</script>";
                exit();
            }
        }
    } else {
        header("Location: signin.php");
        exit();
    }
} else {
    // Redirect user to Google login
    $authUrl = $client->createAuthUrl();
    header("Location: " . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();
}
?>