<?php
$currentFolder = basename(dirname($_SERVER['PHP_SELF']));
?>

<nav class="bottom-nav">

    <a href="../dashboard/index.php"
       class="<?= $currentFolder == 'dashboard' ? 'active' : '' ?>">

        <i class="bi bi-house-fill"></i>
        <span>Home</span>

    </a>

    <a href="../assignments/index.php"
       class="<?= $currentFolder == 'assignments' ? 'active' : '' ?>">

        <i class="bi bi-journal-bookmark-fill"></i>
        <span>Task</span>

    </a>

    <a href="../schedules/index.php"
       class="<?= $currentFolder == 'schedules' ? 'active' : '' ?>">

        <i class="bi bi-calendar-event-fill"></i>
        <span>Schedule</span>

    </a>

    <a href="../pomodoro/index.php">
        <i class="bi bi-stopwatch-fill"></i>
        <span>Timer</span>
    </a>

    <a href="../notes/index.php"
       class="<?= $currentFolder == 'notes' ? 'active' : '' ?>">

        <i class="bi bi-journal-text"></i>
        <span>Notes</span>

    </a>

    <a href="../calendar/index.php">
        <i class="bi bi-calendar3"></i>
        <span>Calendar</span>
    </a>

    <a href="../profile/index.php"
       class="<?= $currentFolder == 'profile' ? 'active' : '' ?>">

        <i class="bi bi-person-fill"></i>
        <span>Profile</span>

    </a>

    <a href="../about/index.php">
            <i class="bi bi-info-circle"></i>
            About Us
        </a>

</nav>