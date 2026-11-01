<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $course = mysqli_real_escape_string($conn, $_POST['course']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);

    mysqli_query(
        $conn,
        "UPDATE users
         SET
            full_name='$full_name',
            course='$course',
            semester='$semester'
         WHERE id='$user_id'"
    );

    $_SESSION['user_name'] = $full_name;

    header("Location: index.php");
    exit();
}

$query = mysqli_query(
    $conn,
    "SELECT *
     FROM users
     WHERE id='$user_id'"
);

$user = mysqli_fetch_assoc($query);

include("../includes/header.php");
include("../includes/navbar.php");
?>

<div class="container mt-4">

    <div class="dashboard-card">

        <h2>✏️ Edit Profile</h2>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">
                    Full Name
                </label>

                <input
                    type="text"
                    name="full_name"
                    class="form-control"
                    value="<?= htmlspecialchars($user['full_name']); ?>"
                    required>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    Course
                </label>

                <input
                    type="text"
                    name="course"
                    class="form-control"
                    value="<?= htmlspecialchars($user['course']); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">
                    Semester
                </label>

                <input
                    type="number"
                    name="semester"
                    class="form-control"
                    value="<?= htmlspecialchars($user['semester']); ?>">
            </div>

            <button
                type="submit"
                class="btn btn-primary">

                <i class="bi bi-save"></i>
                Save Changes

            </button>

            <a
                href="index.php"
                class="btn btn-secondary">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>