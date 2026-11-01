<?php
session_start();
include("../config/database.php");

if (!isset($_SESSION['user_id'])) {
    exit();
}

$user_id = $_SESSION['user_id'];
$duration = $_POST['duration'];

mysqli_query(
    $conn,
    "INSERT INTO pomodoro_sessions
    (user_id, session_date, duration, completed, created_at)
    VALUES
    (
        '$user_id',
        CURDATE(),
        '$duration',
        1,
        NOW()
    )"
);
?>