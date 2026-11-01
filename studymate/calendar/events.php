<?php
session_start();
include("../config/database.php");

$user_id = $_SESSION['user_id'];

$events = [];

/* Assignments */
$result = mysqli_query(
    $conn,
    "SELECT title,due_date
     FROM assignments
     WHERE user_id='$user_id'"
);

while($row = mysqli_fetch_assoc($result)){
    $events[] = [
        'title' => '📚 '.$row['title'],
        'start' => $row['due_date']
    ];
}

/* Exams */
$result = mysqli_query(
    $conn,
    "SELECT exam_title,exam_date
     FROM exams
     WHERE user_id='$user_id'"
);

while($row = mysqli_fetch_assoc($result)){
    $events[] = [
        'title' => '📝 '.$row['exam_title'],
        'start' => $row['exam_date']
    ];
}

/* Study Sessions */
$result = mysqli_query(
    $conn,
    "SELECT subject,study_date
     FROM schedules
     WHERE user_id='$user_id'"
);

while($row = mysqli_fetch_assoc($result)){
    $events[] = [
        'title' => '⏰ '.$row['subject'],
        'start' => $row['study_date']
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
?>