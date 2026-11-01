<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

// Generate assignment notifications automatically

$tomorrow = date('Y-m-d', strtotime('+1 day'));

$assignmentReminder = mysqli_query(
    $conn,
    "SELECT *
     FROM assignments
     WHERE user_id='$user_id'
     AND due_date='$tomorrow'
     AND status='Pending'"
);

while($row = mysqli_fetch_assoc($assignmentReminder)){

    $message =
        "📚 Assignment ".$row['title']." is due tomorrow.";

    $check = mysqli_query(
        $conn,
        "SELECT *
         FROM notifications
         WHERE user_id='$user_id'
         AND message='$message'"
    );

    if(mysqli_num_rows($check) == 0){

        mysqli_query(
            $conn,
            "INSERT INTO notifications
            (user_id,message)
            VALUES
            ('$user_id','$message')"
        );

    }
}

// Generate exam notifications automatically

$examReminder = mysqli_query(
    $conn,
    "SELECT *
     FROM exams
     WHERE user_id='$user_id'
     AND exam_date='$tomorrow'"
);

while($row = mysqli_fetch_assoc($examReminder)){

    $message =
        "📅 Exam ".$row['exam_title']." is tomorrow.";

    $check = mysqli_query(
        $conn,
        "SELECT *
         FROM notifications
         WHERE user_id='$user_id'
         AND message='$message'"
    );

    if(mysqli_num_rows($check) == 0){

        mysqli_query(
            $conn,
            "INSERT INTO notifications
            (user_id,message)
            VALUES
            ('$user_id','$message')"
        );

    }
}

/* ===========================
   Dashboard Statistics
=========================== */

// Total Assignments
$assignmentQuery = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM assignments
     WHERE user_id='$user_id'"
);

$assignment = mysqli_fetch_assoc($assignmentQuery);
$totalAssignments = $assignment['total'];

// Total Exams
$examQuery = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM exams
     WHERE user_id='$user_id'"
);

$exam = mysqli_fetch_assoc($examQuery);
$totalExams = $exam['total'];

// Total Study Sessions
$scheduleQuery = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM schedules
     WHERE user_id='$user_id'"
);

$schedule = mysqli_fetch_assoc($scheduleQuery);
$totalSchedules = $schedule['total'];

// Completed Assignments
$completedQuery = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM assignments
     WHERE user_id='$user_id'
     AND status='Completed'"
);

$completed = mysqli_fetch_assoc($completedQuery);

// Progress Percentage
$progress = 0;

if ($totalAssignments > 0) {
    $progress = round(($completed['total'] / $totalAssignments) * 100);
}

$nextAssignment = mysqli_query(
                $conn,
                "SELECT *
                FROM assignments
                WHERE user_id='$user_id'
                AND status='Pending'
                ORDER BY due_date ASC
                LIMIT 1"
                );

                $next = mysqli_fetch_assoc($nextAssignment);

                $nextExamQuery = mysqli_query(
                $conn,
                "SELECT *
                FROM exams
                WHERE user_id='$user_id'
                ORDER BY exam_date ASC
                LIMIT 1"
                );

                $nextExam = mysqli_fetch_assoc($nextExamQuery);

// Total Notes
$noteQuery = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notes
     WHERE user_id='$user_id'"
);

$note = mysqli_fetch_assoc($noteQuery);
$totalNotes = $note['total'];

// Assignment Status Chart
$pendingQuery = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM assignments
     WHERE user_id='$user_id'
     AND status='Pending'"
);

$completedAssignments = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) total
         FROM assignments
         WHERE user_id='$user_id'
         AND status='Completed'"
    )
);

$totalStudySessions = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) total
         FROM schedules
         WHERE user_id='$user_id'"
    )
);

$totalNotesCount = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) total
         FROM notes
         WHERE user_id='$user_id'"
    )
);

$totalExamsCount = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) total
         FROM exams
         WHERE user_id='$user_id'"
    )
);

$pending = mysqli_fetch_assoc($pendingQuery);

$completedTotal = $completed['total'];
$pendingTotal = $pending['total'];

include("../includes/header.php");
include("../includes/navbar.php");
?>

        <div class="container mt-4 mb-5">

            <div class="hero-card mb-4">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h2 class="fw-bold">

                            Good Morning,

                            <?= htmlspecialchars($_SESSION['user_name']); ?> 👋

                        </h2>

                        <p class="text-muted mb-0">

                            Stay focused and complete your study goals today.

                        </p>

                        <p class="mb-0">

                        <?= date("l, d F Y"); ?>

                        </p>

                    </div>

                <i class="bi bi-mortarboard-fill fs-1 text-primary"></i>

            </div>

            <?php if($totalAssignments == 0){ ?>

            <div class="alert alert-info mt-4">

                👋 Welcome to StudyMate! Start by adding your first assignment.

            </div>

            <?php } ?>

        </div>

    <div class="row g-3">

        <!-- Assignment -->
        <div class="col-6 col-md-3">

            <div class="dashboard-card text-center stat-card">

                <div class="stat-icon blue">

                <i class="bi bi-journal-bookmark-fill"></i>

                </div>

                <h2 class="mt-3 fw-bold">

                <?= $totalAssignments ?>

                </h2>

                <p class="text-muted mb-0">

                Assignments

                </p>

            </div>

        </div>

        <!-- Exam -->
        <div class="col-6 col-md-3">

            <div class="dashboard-card text-center stat-card">

                <div class="stat-icon green">

    <i class="bi bi-calendar-event-fill"></i>

            </div>

                <h2 class="mt-3 fw-bold">

                    <?= $totalExams ?>

                </h2>

                <p class="text-muted mb-0">

                    Upcoming Exams

                </p>

            </div>

        </div>

        <!-- Goals -->
        <div class="col-6 col-md-3">

            <div class="dashboard-card text-center stat-card">

                <div class="stat-icon orange">

                <i class="bi bi-clock-history"></i>

            </div>

            <h2 class="mt-3 fw-bold">

                <?= $totalSchedules ?>

            </h2>

            <p class="text-muted mb-0">

                Study Sessions

            </p>

            </div>

        </div>

        <!-- Notes -->
        <div class="col-6 col-md-3">

            <div class="dashboard-card text-center stat-card">

                <div class="stat-icon red">

                    <i class="bi bi-journal-text"></i>

                </div>

                <h2 class="mt-3 fw-bold">

                    <?= $totalNotes ?>

                </h2>

                <p class="text-muted mb-0">

                    Notes

                </p>

            </div>

        </div>

    </div>

    <div class="dashboard-card mt-4 text-center">

    <h5>
        <i class="bi bi-lightning-charge-fill text-warning"></i>
        Quick Actions
    </h5>

    <div class="row mt-3 text-center g-3 justify-content-center">

        <div class="col-md-3 mb-3">
            <a href="../assignments/index.php" class="btn btn-primary w-100">
                <i class="bi bi-plus-circle"></i>
                Assignment
            </a>
        </div>

        <div class="col-md-3 mb-3">
            <a href="../pomodoro/index.php" class="btn btn-danger w-100">
                🍅 Pomodoro
            </a>
        </div>

        <div class="col-md-3 mb-3">
            <a href="../calendar/index.php"
             class="btn btn-dark w-100">

                <i class="bi bi-calendar3"></i>
                Calendar

            </a>
        </div>

        <div class="col-md-3 mb-3">
            <a href="../notes/index.php" class="btn btn-success w-100">
                <i class="bi bi-journal-plus"></i>
                Notes
            </a>
        </div>

        <div class="col-md-3 mb-3">
            <a href="../schedules/index.php" class="btn btn-warning w-100">
                <i class="bi bi-calendar-plus"></i>
                Schedule
            </a>
        </div>

        <div class="col-md-3 mb-3">
            <a href="../exams/index.php" class="btn btn-info text-white w-100">
                <i class="bi bi-calendar-event"></i>
                Exams
            </a>
        </div>
        
        <div class="col-md-3 mb-3">
            <a href="../achievements/index.php"
            class="btn btn-secondary w-100">

                🏆 My Achievements

            </a>
        </div>
    </div>

</div>

<div class="dashboard-card mt-4">

    <h5>📊 Assignment Progress</h5>

    <?php if($totalAssignments > 0){ ?>

        <div class="progress mt-3" style="height:25px;">

            <div class="progress-bar bg-success"
                 role="progressbar"
                 style="width: <?= $progress ?>%;">

                <?= $progress ?>%

            </div>

        </div>

        <p class="mt-3 text-muted">

            <?= $completed['total']; ?>

            Completed out of

            <?= $totalAssignments; ?>

            Assignments

        </p>

    <?php } else { ?>

        <p class="text-muted mt-3">

            No assignments to track yet.

        </p>

    <?php } ?>

</div>

<div class="dashboard-card mt-4 text-center donut-card">

    <h5>📊 Assignment Statistics</h5>

    <?php if($totalAssignments > 0){ ?>

        <div style="max-width:350px; margin:auto;">
            <canvas id="assignmentChart"></canvas>
        </div>

    <?php } else { ?>

        <p class="text-muted mt-4">
            No assignment data available.
        </p>

    <?php } ?>

</div>

<div class="dashboard-card mt-4">

    <h5>📊 Study Statistics</h5>

    <div class="row mt-3">

        <div class="col-6 col-md-3">
            <div class="study-stat">
                <h2 class="text-success">
                    <?= $completedAssignments['total']; ?>
                </h2>
                <p>Completed Tasks</p>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="study-stat">
                <h2 class="text-primary">
                    <?= $totalStudySessions['total']; ?>
                </h2>
                <p>Study Sessions</p>
            </div>
        </div>

        <div class="col-6 col-md-3">
           <div class="study-stat">
                <h2 class="text-warning">
                    <?= $totalNotesCount['total']; ?>
                </h2>
                <p>Notes</p>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="study-stat">
                <h2 class="text-danger">
                    <?= $totalExamsCount['total']; ?>
                </h2>
                <p>Exams</p>
            </div>
        </div>

    </div>

</div>

<div class="dashboard-card mt-4">

    <h5>📈 Productivity Overview</h5>

    <canvas id="studyChart"></canvas>

</div>

<div class="row mt-4 gy-4">

    <div class="col-md-6">

        <div class="dashboard-card h-100">

            <h5>📚 Upcoming Assignment</h5>

            <?php if($next){ ?>

                <h4><?= htmlspecialchars($next['title']); ?></h4>

                <p><?= htmlspecialchars($next['subject']); ?></p>

                <p>

                    Due:

                    <?= date("d M Y",strtotime($next['due_date'])); ?>

                </p>

            <?php } else { ?>

                <p class="text-success">

                    🎉 No pending assignments.

                </p>

            <?php } ?>

        </div>

    </div>

    <!-- Upcoming Exam -->

    <div class="col-md-6">

        <div class="dashboard-card h-100">

            <h5>📅 Upcoming Exam</h5>

            <?php if($nextExam){ ?>

                <h4>

                    <?= htmlspecialchars($nextExam['exam_title']); ?>

                </h4>

                <p>

                    <?= htmlspecialchars($nextExam['subject']); ?>

                </p>

                <p>

                    <?= date("d M Y",strtotime($nextExam['exam_date'])); ?>

                </p>

            <?php } else { ?>

                <p class="text-success">

                    No upcoming exam.

                </p>

            <?php } ?>

        </div>

    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    // Assignment Doughnut Chart
    const assignmentCtx =
        document.getElementById("assignmentChart");

    if (assignmentCtx) {

        new Chart(assignmentCtx, {
    type: "doughnut",
    data: {
        labels: ["Completed", "Pending"],
        datasets: [{
            data: [
                <?= $completedTotal ?>,
                <?= $pendingTotal ?>
            ],
            backgroundColor: [
                "#10b981",
                "#f59e0b"
            ]
        }]
    },
    options: {
        responsive: true,
        layout: {
            padding: {
                bottom: 10
            }
        },
        plugins: {
            legend: {
                position: "bottom",
                labels: {
                    padding: 25
                }
            }
        }
    }
});

    }

    // Productivity Bar Chart
    const studyCtx =
        document.getElementById("studyChart");

    if (studyCtx) {

        new Chart(studyCtx, {
            type: "bar",
            data: {
                labels: [
                    "Assignments",
                    "Sessions",
                    "Notes",
                    "Exams"
                ],
                datasets: [{
                    label: "Total",
                    data: [
                        <?= $totalAssignments ?>,
                        <?= $totalSchedules ?>,
                        <?= $totalNotes ?>,
                        <?= $totalExams ?>
                    ],
                    backgroundColor: [
                        "#4f46e5",
                        "#10b981",
                        "#f59e0b",
                        "#ef4444"
                    ]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

    }

});
</script>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>