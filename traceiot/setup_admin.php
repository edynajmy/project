<?php
include 'connection.php'; 

$admin_name     = "Head Admin Tracelot";
$admin_email    = "admin@uitm.edu.my";
$admin_password = "admin123"; 

// Roles_ID variable has been removed since the column no longer exists!

$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

$check_query = "SELECT * FROM ADMIN WHERE email = '$admin_email'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) > 0) {
    echo "<h3 style='color: orange; font-family: sans-serif;'>Error: This Admin account already exists!</h3>";
} else {
    // Insert into ADMIN table without the Roles_ID column
    $sql = "INSERT INTO ADMIN (name, email, password) VALUES ('$admin_name', '$admin_email', '$hashed_password')";
    
    echo "<div style='font-family: sans-serif; padding: 20px; border: 2px dashed green; display: inline-block;'>";
    if (mysqli_query($conn, $sql)) {
        echo "<h3 style='color: green; margin-top: 0;'>🎉 Admin Account Successfully Created in the ADMIN table!</h3>";
        echo "<p>Please proceed to admin_signin.php to try logging in.</p>";
    } else {
        echo "<h3 style='color: red;'>Error:</h3> " . mysqli_error($conn);
    }
    echo "</div>";
}
?>