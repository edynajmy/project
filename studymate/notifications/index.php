<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");
$user_id = $_SESSION['user_id'];
mysqli_query(
    $conn,
    "UPDATE notifications
     SET is_read = 1
     WHERE user_id='$user_id'"
);
include("../includes/header.php");
include("../includes/navbar.php");
?>

<div class="container mt-4">

    <div class="dashboard-card">

        <h2>🔔 Notifications</h2>

        <p class="text-muted">
            All your reminders and updates.
        </p>

        <?php

        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $threeDays = date('Y-m-d', strtotime('+3 days'));

        $hasNotification = false;

        $notificationQuery = mysqli_query(
            $conn,
            "SELECT *
            FROM notifications
            WHERE user_id='$user_id'
            ORDER BY created_at DESC"
        );

        while($row = mysqli_fetch_assoc($notificationQuery)){
            $hasNotification = true;
        ?>
            <div class="alert alert-primary">
                🔔 <?= htmlspecialchars($row['message']); ?>
            </div>
        <?php
        }

        /* Assignment Due Today */
        $todayAssignment = mysqli_query(
            $conn,
            "SELECT *
            FROM assignments
            WHERE user_id='$user_id'
            AND due_date='$today'
            AND status='Pending'"
        );

        while($row = mysqli_fetch_assoc($todayAssignment)){
            $hasNotification = true;
        ?>
            <div class="alert alert-danger">
                📚 Assignment <strong><?= $row['title'] ?></strong>
                is due today.
            </div>
        <?php
        }

        /* Assignment Due Tomorrow */
        $tomorrowAssignment = mysqli_query(
            $conn,
            "SELECT *
            FROM assignments
            WHERE user_id='$user_id'
            AND due_date='$tomorrow'
            AND status='Pending'"
        );

        while($row = mysqli_fetch_assoc($tomorrowAssignment)){
            $hasNotification = true;
        ?>
            <div class="alert alert-warning">
                ⏰ Assignment <strong><?= $row['title'] ?></strong>
                is due tomorrow.
            </div>
        <?php
        }

        /* Upcoming Exams */
        $examQuery = mysqli_query(
            $conn,
            "SELECT *
            FROM exams
            WHERE user_id='$user_id'
            AND exam_date BETWEEN '$today' AND '$threeDays'"
        );

        while($row = mysqli_fetch_assoc($examQuery)){
            $hasNotification = true;
        ?>
            <div class="alert alert-info">
                📝 Exam <strong><?= $row['exam_title'] ?></strong>
                on <?= date('d M Y', strtotime($row['exam_date'])) ?>
            </div>
        <?php
        }

        /* Study Sessions Today */
        $scheduleQuery = mysqli_query(
            $conn,
            "SELECT *
            FROM schedules
            WHERE user_id='$user_id'
            AND study_date='$today'"
        );

        while($row = mysqli_fetch_assoc($scheduleQuery)){
            $hasNotification = true;
        ?>
            <div class="alert alert-success">
                📅 Study session for
                <strong><?= $row['subject'] ?></strong>
                today.
            </div>
        <?php
        }

        if(!$hasNotification){
        ?>
            <div class="alert alert-info">
                No notifications yet.
            </div>
        <?php
        }
        ?>

    </div>

</div>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>