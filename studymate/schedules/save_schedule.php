<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

$subject = trim($_POST['subject']);
$topic = trim($_POST['topic']);
$study_date = $_POST['study_date'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO schedules
    (user_id, subject, study_date, start_time, end_time, topic)
    VALUES (?, ?, ?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "isssss",
    $user_id,
    $subject,
    $study_date,
    $start_time,
    $end_time,
    $topic
);

if (mysqli_stmt_execute($stmt)) {

    header("Location: index.php");
    exit();

} else {

    echo "Error: " . mysqli_error($conn);

}
?>