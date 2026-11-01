<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

$full_name = trim($_POST['full_name']);
$email = trim($_POST['email']);
$course = trim($_POST['course']);
$semester = intval($_POST['semester']);

$stmt = mysqli_prepare(
    $conn,
    "UPDATE users
     SET full_name=?,
         email=?,
         course=?,
         semester=?
     WHERE id=?"
);

mysqli_stmt_bind_param(
    $stmt,
    "sssii",
    $full_name,
    $email,
    $course,
    $semester,
    $user_id
);

if(mysqli_stmt_execute($stmt)){

    $_SESSION['user_name'] = $full_name;

    header("Location: index.php?success=profile");
    exit();

}else{

    echo "Update failed.";

}
?>