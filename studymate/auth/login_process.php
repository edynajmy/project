<?php

session_start();
include("../config/database.php");

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: login.php");
    exit();
}

$email = trim($_POST['email']);
$password = $_POST['password'];

$stmt = mysqli_prepare($conn, "SELECT id, full_name, email, password FROM users WHERE email=?");

mysqli_stmt_bind_param($stmt, "s", $email);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if($user = mysqli_fetch_assoc($result)){

    if(password_verify($password, $user['password'])){

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];

        header("Location: ../dashboard/index.php");
        exit();

    }

}

header("Location: login.php?error=1");
exit();

?>