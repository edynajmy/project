<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");
include("../includes/header.php");
include("../includes/navbar.php");

$user_id = $_SESSION['user_id'];
?>

<div class="container mt-4">

    <div class="dashboard-card">

        <h2>📅 Study Calendar</h2>

        <p class="text-muted">
            View your assignments, exams and schedules.
        </p>

        <div id="calendar"></div>

    </div>

</div>

<link
href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css"
rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

<script>

document.addEventListener('DOMContentLoaded', function () {

    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 650,
        events: 'events.php'
    });

    calendar.render();

});

</script>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>