<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

$title = trim($_POST['title']);
$content = trim($_POST['content']);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO notes
    (user_id, title, content)
    VALUES (?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "iss",
    $user_id,
    $title,
    $content
);

if (mysqli_stmt_execute($stmt)) {

    header("Location: index.php");
    exit();

} else {

    echo "Error: " . mysqli_error($conn);

}
?>