<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");
include("../includes/header.php");
include("../includes/navbar.php");
?>

<div class="container mt-4">

<div class="dashboard-card p-4">

    <div class="text-center">

        <i
            class="bi bi-book-half text-primary"
            style="font-size:80px;"
        ></i>

        <h2 class="fw-bold mt-3">
            About StudyMate
        </h2>

        <p class="text-muted">
            Your Personal Study Companion
        </p>

    </div>

    <hr>

    <h4 class="fw-bold">
        📖 System Description
    </h4>

    <p>
        StudyMate is a web-based study management
        system developed to help university students
        manage assignments, exams, notes and study
        sessions efficiently in one platform.
    </p>

    <h4 class="fw-bold mt-4">
        🎯 Objectives
    </h4>

    <ul>
        <li>
            Improve students' productivity and
            time management.
        </li>

        <li>
            Organize academic activities efficiently.
        </li>

        <li>
            Help students track their academic
            progress.
        </li>

        <li>
            Reduce missed deadlines and improve
            study effectiveness.
        </li>
    </ul>

    <h4 class="fw-bold mt-4">
        💡 Main Features
    </h4>

    <ul>
        <li>Assignment Management</li>
        <li>Exam Management</li>
        <li>Study Session Scheduling</li>
        <li>Notes Management</li>
        <li>Achievements System</li>
        <li>Notifications System</li>
        <li>Pomodoro Timer</li>
        <li>Progress Dashboard</li>
    </ul>

    <h4 class="fw-bold mt-4">
        💼 Future Business Model
    </h4>

    <ul>
        <li>Premium Subscription Plan</li>
        <li>Cloud Backup Service</li>
        <li>AI Study Planner</li>
        <li>Educational Partnership</li>
        <li>Advertisement Revenue</li>
    </ul>

    <h4 class="fw-bold mt-4">
        👨‍💻 Developer Information
    </h4>

    <p>
        Developed by:
        <br>
        <strong>Edy Nur Najmy bin Hamedy</strong>
        <br>
        Faculty of Computer and Mathematical Sciences
        (FSKM)
        <br>
        Universiti Teknologi MARA
        Cawangan Pahang Kampus Raub
    </p>

    <h4 class="fw-bold mt-4">
        📌 System Version
    </h4>

    <p>
        StudyMate Version 1.0
    </p>

</div>

</div>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>
