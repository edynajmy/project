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

    <div class="dashboard-card text-center">

        <h2>🍅 Pomodoro Timer</h2>

        <p class="text-muted">
            Stay focused and study efficiently.
        </p>

        <div class="mb-4">
            <label class="fw-bold">Study Minutes</label>

            <input
                type="number"
                id="minutesInput"
                class="form-control w-25 mx-auto"
                value="25"
                min="1"
                max="180">
        </div>

        <h1 id="timer" style="font-size:80px;">
            25:00
        </h1>

        <div class="mt-4">

            <button
                id="startBtn"
                class="btn btn-success me-2">
                ▶ Start
            </button>

            <button
                id="pauseBtn"
                class="btn btn-warning me-2">
                ⏸ Pause
            </button>

            <button
                id="resetBtn"
                class="btn btn-danger">
                🔄 Reset
            </button>

        </div>

    </div>

</div>

<script>
let time = 25 * 60;
let interval = null;

const timer = document.getElementById('timer');

function updateTimer() {
    let minutes = Math.floor(time / 60);
    let seconds = time % 60;

    timer.innerHTML =
        String(minutes).padStart(2, '0')
        + ':'
        + String(seconds).padStart(2, '0');
}

function startTimer() {

    if (interval !== null) return;

    interval = setInterval(function () {

        if (time <= 0) {

            clearInterval(interval);
            interval = null;

            let minutes =
                document.getElementById('minutesInput').value;

            fetch('save_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },
                body: 'duration=' + minutes
            });

            alert('🎉 Study session completed!');

            return;
        }

        time--;
        updateTimer();

    }, 1000);
}

document.getElementById('startBtn')
.addEventListener('click', function () {
    startTimer();
});

document.getElementById('pauseBtn')
.addEventListener('click', function () {
    clearInterval(interval);
    interval = null;
});

document.getElementById('resetBtn')
.addEventListener('click', function () {

    clearInterval(interval);
    interval = null;

    let minutes =
        document.getElementById('minutesInput').value;

    time = minutes * 60;

    updateTimer();
});

document.getElementById('minutesInput')
.addEventListener('change', function () {

    clearInterval(interval);
    interval = null;

    time = this.value * 60;

    updateTimer();
});

updateTimer();
</script>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>