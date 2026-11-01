<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

$subject = trim($_POST['subject']);
$exam_title = trim($_POST['exam_title']);
$exam_date = $_POST['exam_date'];
$exam_time = $_POST['exam_time'];
$venue = trim($_POST['venue']);
$notes = trim($_POST['notes']);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO exams
    (user_id, subject, exam_title, exam_date, exam_time, venue, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "issssss",
    $user_id,
    $subject,
    $exam_title,
    $exam_date,
    $exam_time,
    $venue,
    $notes
);

if (mysqli_stmt_execute($stmt)) {

    $message =
        "New exam ".$exam_title." has been scheduled.";

        $message =
            mysqli_real_escape_string($conn, $message);

        mysqli_query(
            $conn,
            "INSERT INTO notifications
            (
                user_id,
                message,
                is_read,
                created_at
            )
            VALUES
            (
                '$user_id',
                '$message',
                0,
                NOW()
            )"
        );

    header("Location: index.php");
    exit();

} else {

    echo mysqli_error($conn);

}
?>