<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

$completed = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) total
         FROM assignments
         WHERE user_id='$user_id'
         AND status='Completed'"
    )
);

if($completed['total'] >= 10){

    $exist = mysqli_num_rows(
        mysqli_query(
            $conn,
            "SELECT id
             FROM achievements
             WHERE user_id='$user_id'
             AND badge_name='Study Master'"
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
                'Study Master',
                'Completed 10 assignments.',
                CURDATE()
            )"
        );
    }
}

$totalAssignment = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) total
         FROM assignments
         WHERE user_id='$user_id'"
    )
);

$totalPending = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) total
         FROM assignments
         WHERE user_id='$user_id'
         AND status='Pending'"
    )
);

$totalCompleted = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) total
         FROM assignments
         WHERE user_id='$user_id'
         AND status='Completed'"
    )
);

$totalHigh = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) total
         FROM assignments
         WHERE user_id='$user_id'
         AND priority='High'"
    )
);

// Search
$search = "";

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {

    $search = trim($_GET['search']);
    $keyword = "%".$search."%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM assignments
         WHERE user_id = ?
         AND (title LIKE ? OR subject LIKE ?)
         ORDER BY due_date ASC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "iss",
        $user_id,
        $keyword,
        $keyword
    );

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM assignments
         WHERE user_id = ?
         ORDER BY due_date ASC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );
}

include("../includes/header.php");
include("../includes/navbar.php");

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<div class="container mt-4 mb-5">

    <div class="mb-4">

        <div>
            <div class="row mb-4 justify-content-center">

            <div class="col-md-6 col-6">

            <div class="dashboard-card stat-card assignment-stat-card text-center">

            <h3>

            <?= $totalAssignment['total']; ?>

            </h3>

            <p>Total</p>

            </div>

            </div>

            <div class="col-md-6 col-6">

            <div class="dashboard-card stat-card assignment-stat-card text-center">

            <h3 class="text-warning">

            <?= $totalPending['total']; ?>

            </h3>

            <p>Pending</p>

            </div>

            </div>

            <div class="col-md-6 col-6">

            <div class="dashboard-card stat-card assignment-stat-card text-center">

            <h3 class="text-success">

            <?= $totalCompleted['total']; ?>

            </h3>

            <p>Completed</p>

            </div>

            </div>

            <div class="col-md-6 col-6">

            <div class="dashboard-card stat-card assignment-stat-card text-center">

            <h3 class="text-danger">

            <?= $totalHigh['total']; ?>

            </h3>

            <p>High Priority</p>

            </div>

            </div>

            </div>
            <h2 class="fw-bold">
                📚 My Assignments
            </h2>

            <p class="text-muted">
                Manage all your assignments here.
            </p>

            <div class="d-flex flex-wrap gap-2 mt-3 mb-4">

                <a
                    href="export_pdf.php"
                    class="btn btn-danger">

                    <i class="bi bi-file-earmark-pdf"></i>
                    Export PDF

                </a>

                <button
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addAssignmentModal">

                    <i class="bi bi-plus-circle"></i>
                    Add Assignment

                </button>

            </div>

    <!-- Search -->

    <form method="GET" class="mb-4">

        <div class="input-group">

            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search assignment..."
                value="<?php echo htmlspecialchars($search); ?>">

            <button
                class="btn btn-outline-primary">

                <i class="bi bi-search"></i>

                Search

            </button>

        </div>

    </form>

    <div class="row">

        <?php if(mysqli_num_rows($result)>0){ ?>

            <?php while($row=mysqli_fetch_assoc($result)){ ?>

                <div class="col-lg-6 mb-4">

                    <div class="card shadow-sm border-0 rounded-4 h-100">

                        <div class="card-body">

                            <h4 class="text-primary fw-bold">

                                <i class="bi bi-journal-bookmark-fill"></i>

                                <?php echo htmlspecialchars($row['title']); ?>

                            </h4>

                            <h6 class="text-muted">

                                <?php echo htmlspecialchars($row['subject']); ?>

                            </h6>

                            <hr>

                            <p>

                                <?php echo nl2br(htmlspecialchars($row['description'])); ?>

                            </p>

                            <p>

                                <i class="bi bi-calendar-event"></i>

                                <strong>Due Date:</strong>

                                <?php echo date("d M Y", strtotime($row['due_date'])); ?>

                            </p>

                            <div class="mb-3">

                                <?php

                                if($row['priority']=="High"){

                                    echo '<span class="badge bg-danger me-2">High</span>';

                                }elseif($row['priority']=="Medium"){

                                    echo '<span class="badge bg-warning text-dark me-2">Medium</span>';

                                }else{

                                    echo '<span class="badge bg-success me-2">Low</span>';

                                }

                                ?>

                                <?php

                                if($row['status']=="Completed"){

                                    echo '<span class="badge bg-success status-badge">Completed</span>';

                                }else{

                                    echo '<span class="badge bg-secondary">Pending</span>';

                                }

                                ?>

                            </div>

                            <div class="d-flex gap-2">

                                <a
                                    href="edit_assignment.php?id=<?php echo $row['id']; ?>"
                                    class="btn btn-warning btn-sm">

                                    <i class="bi bi-pencil-square"></i>

                                    Edit

                                </a>

                                <a
                                    href="delete_assignment.php?id=<?php echo $row['id']; ?>"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete this assignment?')">

                                    <i class="bi bi-trash"></i>

                                    Delete

                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="col-12">

                <div class="alert alert-info text-center">

                    <i class="bi bi-journal-x fs-1 text-primary"></i>

                    <h5 class="mt-3">
                        📚 No assignments found.
                    </h5>

                    <p>
                        Start by clicking
                        <strong>Add Assignment</strong>
                        to create your first assignment.
                    </p>

                </div>

            </div>

        <?php } ?>

    </div>

</div>

<?php include("add_assignment.php"); ?>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>