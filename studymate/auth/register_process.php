<?php
session_start();
include("../config/database.php");

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: register.php");
    exit();
}

// Get form data
$full_name = trim($_POST['full_name']);
$email = trim($_POST['email']);
$course = trim($_POST['course']);
$semester = trim($_POST['semester']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

// Validate empty fields
if (
    empty($full_name) ||
    empty($email) ||
    empty($course) ||
    empty($semester) ||
    empty($password) ||
    empty($confirm_password)
) {
    die("Please fill in all fields.");
}

// Check password match
if ($password !== $confirm_password) {
    die("Passwords do not match.");
}

// Check if email already exists
$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    die("Email already exists.");
}

mysqli_stmt_close($stmt);

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password, course, semester) VALUES (?, ?, ?, ?, ?)");

mysqli_stmt_bind_param(
    $stmt,
    "ssssi",
    $full_name,
    $email,
    $hashedPassword,
    $course,
    $semester
);

if (mysqli_stmt_execute($stmt)) {
    header("Location: login.php?success=1");
    exit();
} else {
    echo "Registration failed: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>