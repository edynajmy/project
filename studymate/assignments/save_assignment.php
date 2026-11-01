<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

$title = trim($_POST['title']);
$subject = trim($_POST['subject']);
$description = trim($_POST['description']);
$due_date = $_POST['due_date'];
$priority = $_POST['priority'];
$status = $_POST['status'];

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO assignments
    (user_id, title, subject, description, due_date, priority, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "issssss",
    $user_id,
    $title,
    $subject,
    $description,
    $due_date,
    $priority,
    $status
);

if (mysqli_stmt_execute($stmt)) {

$count = mysqli_fetch_assoc(
            mysqli_query(
                $conn,
                "SELECT COUNT(*) total
                FROM assignments
                WHERE user_id='$user_id'"
            )
        );

if($count['total'] >= 5){

    $exist = mysqli_num_rows(
        mysqli_query(
            $conn,
            "SELECT id
             FROM achievements
             WHERE user_id='$user_id'
             AND badge_name='Assignment Creator'"
        )
    );

    if($exist == 0){

        mysqli_query(
            $conn,
            "INSERT INTO achievements
            (
                user_id,
                badge_name,
                description,
                earned_date
            )
            VALUES
            (
                '$user_id',
                'Assignment Creator',
                'Created 5 assignments.',
                CURDATE()
            )"
        );
    }
}

        if($count['total'] >= 10){

    $exist = mysqli_num_rows(
        mysqli_query(
            $conn,
            "SELECT id
             FROM achievements
             WHERE user_id='$user_id'
             AND badge_name='Assignment Expert'"
        )
    );

    if($exist == 0){

        mysqli_query(
            $conn,
            "INSERT INTO achievements
            (
                user_id,
                badge_name,
                description,
                earned_date
            )
            VALUES
            (
                '$user_id',
                'Assignment Expert',
                'Created 10 assignments.',
                CURDATE()
            )"
        );
    }
}

        if($count['total'] >= 1){

            $exist = mysqli_num_rows(
                mysqli_query(
                    $conn,
                    "SELECT id
                    FROM achievements
                    WHERE user_id='$user_id'
                    AND badge_name='First Assignment'"
                )
            );

            if($exist == 0){

                mysqli_query(
                    $conn,
                    "INSERT INTO achievements
                    (
                        user_id,
                        badge_name,
                        description,
                        earned_date
                    )
                    VALUES
                    (
                        '$user_id',
                        'First Assignment',
                        'Created your first assignment.',
                        CURDATE()
                    )"
                );
            }
        }

        $message =
            "New assignment ".$title." has been added.";

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
    echo "Error: " . mysqli_error($conn);
}
?>