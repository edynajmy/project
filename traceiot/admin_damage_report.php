<?php
session_start();
include 'connection.php';
include 'alerts.php';

if (!isset($_SESSION['admin_ID'])) {
    setSweetAlert('error', 'Access Denied!', 'Administrators only.');
    header("Location: signin.php");
    exit();
}

// MARK AS REPLACED + ADD STOCK
if (isset($_GET['action']) && $_GET['action'] == 'mark_replaced' && isset($_GET['id'])) {
    $report_id = mysqli_real_escape_string($conn, $_GET['id']);

    mysqli_begin_transaction($conn);

    try {
        $check_report = mysqli_query($conn, "
            SELECT report_id, Component_ID, issue_quantity, report_status
            FROM damaged_item_report
            WHERE report_id = '$report_id'
            FOR UPDATE
        ");

        if (!$check_report || mysqli_num_rows($check_report) == 0) {
            throw new Exception("Damage report not found.");
        }

        $report = mysqli_fetch_assoc($check_report);

        if ($report['report_status'] == 'Replaced') {
            throw new Exception("This item has already been marked as replaced.");
        }

        $component_id = $report['Component_ID'];
        $issue_quantity = (int)$report['issue_quantity'];

        $update_stock = "UPDATE COMPONENT
                         SET total_quantity = total_quantity + $issue_quantity
                         WHERE Component_ID = '$component_id'";

        if (!mysqli_query($conn, $update_stock)) {
            throw new Exception("Failed to update component stock.");
        }

        $update_report = "UPDATE damaged_item_report
                          SET report_status = 'Replaced'
                          WHERE report_id = '$report_id'";

        if (!mysqli_query($conn, $update_report)) {
            throw new Exception("Failed to update report status.");
        }

        mysqli_commit($conn);

        setSweetAlert('success', 'Updated!', 'Item marked as replaced and stock has been added back.');
        header("Location: admin_damage_report.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);

        setSweetAlert('error', 'Cannot Update', $e->getMessage());
        header("Location: admin_damage_report.php");
        exit();
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'All';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where = [];

if ($filter == 'Need Replacement') {
    $where[] = "dir.report_status = 'Need Replacement'";
} elseif ($filter == 'Replaced') {
    $where[] = "dir.report_status = 'Replaced'";
} elseif ($filter == 'Minor Damage' || $filter == 'Damaged' || $filter == 'Missing') {
    $safe_filter = mysqli_real_escape_string($conn, $filter);
    $where[] = "dir.issue_type = '$safe_filter'";
}

if ($search != '') {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $where[] = "(c.name LIKE '%$safe_search%' OR student.name LIKE '%$safe_search%' OR br.Record_ID LIKE '%$safe_search%')";
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}

$summary_query = "
    SELECT 
        COALESCE(SUM(CASE WHEN issue_type = 'Minor Damage' AND report_status = 'Need Replacement' THEN issue_quantity ELSE 0 END), 0) AS minor_total,
        COALESCE(SUM(CASE WHEN issue_type = 'Damaged' AND report_status = 'Need Replacement' THEN issue_quantity ELSE 0 END), 0) AS damaged_total,
        COALESCE(SUM(CASE WHEN issue_type = 'Missing' AND report_status = 'Need Replacement' THEN issue_quantity ELSE 0 END), 0) AS missing_total,
        COALESCE(SUM(CASE WHEN report_status = 'Need Replacement' THEN issue_quantity ELSE 0 END), 0) AS need_total
    FROM damaged_item_report
";

$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($current_page < 1) {
    $current_page = 1;
}

$count_query = "
    SELECT COUNT(*) AS total_records
    FROM damaged_item_report dir
    JOIN borrowing_record br ON dir.Record_ID = br.Record_ID
    JOIN student ON br.Student_ID = student.Student_ID
    JOIN COMPONENT c ON dir.Component_ID = c.Component_ID
    $where_sql
";

$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = (int)$count_row['total_records'];

$total_pages = ceil($total_records / $records_per_page);

if ($total_pages < 1) {
    $total_pages = 1;
}

if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $records_per_page;

$query = "
    SELECT 
        dir.*,
        br.Record_ID,
        br.return_date,
        student.name AS student_name,
        student.Student_ID AS student_matric,
        c.name AS component_name
    FROM damaged_item_report dir
    JOIN borrowing_record br ON dir.Record_ID = br.Record_ID
    JOIN student ON br.Student_ID = student.Student_ID
    JOIN COMPONENT c ON dir.Component_ID = c.Component_ID
    $where_sql
    ORDER BY FIELD(dir.report_status, 'Need Replacement', 'Replaced'), dir.report_id DESC
    LIMIT $records_per_page OFFSET $offset
";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Damaged Items - Admin TraceIoT</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f6f8fb 0%, #e9edf3 100%);
            min-height: 100vh;
            padding-bottom: 50px;
        }

        .navbar-container {
            width: 100%;
            display: flex;
            justify-content: center;
            position: fixed;
            top: 0;
            z-index: 100;
        }

        .navbar {
            width: 98%;
            max-width: 1400px;
            background-color: #ffffff;
            height: 80px;
            border-radius: 0 0 30px 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-left img {
            height: 45px;
            object-fit: contain;
        }

        .nav-divider {
            width: 1px;
            height: 40px;
            background-color: #e0e0e0;
        }

        .nav-center {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-item {
            text-decoration: none;
            color: #333;
            font-weight: 700;
            font-size: 14px;
        }

        .nav-item.active {
            color: #0d3b66;
        }

        .nav-text-divider {
            color: #ccc;
            font-weight: 300;
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .profile-icon {
            width: 35px;
            height: 35px;
            background-color: #000;
            color: #fff;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 140px;
            padding-left: 40px;
            padding-right: 40px;
        }

        .page-header {
            margin-bottom: 18px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 15px;
        }

        .page-header h1 {
            font-size: 26px;
            font-weight: 900;
            color: #000;
        }

        .page-header p {
            margin-top: 6px;
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.03);
        }

        .summary-card h3 {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .summary-card p {
            font-size: 24px;
            font-weight: 900;
            color: #0d3b66;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .filter-tab {
            text-decoration: none;
            padding: 9px 18px;
            border-radius: 20px;
            background: #ffffff;
            color: #0f172a;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            font-weight: 800;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            transition: 0.2s;
        }

        .filter-tab.active {
            background: #0d3b66;
            color: #ffffff;
            border-color: #0d3b66;
        }

        .search-container {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            background: #ffffff;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.02);
            border: 1px solid #eaeaea;
            align-items: center;
        }

        .search-form {
            display: flex;
            width: 100%;
            gap: 12px;
        }

        .search-input {
            flex: 1;
            padding: 12px 18px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            font-weight: 500;
        }

        .btn-search {
            background-color: #0d3b66;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
        }

        .report-table-card {
            background: #ffffff;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03);
            overflow: hidden;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th {
            text-align: left;
            background: #f8fafc;
            padding: 14px 16px;
            font-size: 12px;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }

        .report-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            vertical-align: top;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 900;
            border: 1px solid #000;
        }

        .badge-need {
            background: #fecaca;
            color: #7f1d1d;
        }

        .badge-replaced {
            background: #bbf7d0;
            color: #166534;
        }

        .issue-pill {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 900;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .btn-action {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #000;
            background: #4ade80;
            color: #000;
            text-decoration: none;
            font-size: 12px;
            font-weight: 900;
            box-shadow: 2px 2px 0px #000;
        }

        .muted {
            color: #94a3b8;
            font-weight: 700;
        }

        .empty-box {
            text-align: center;
            background: #ffffff;
            padding: 40px;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            font-weight: 700;
            color: #64748b;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 25px 0 10px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 13px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0d3b66;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
        }

        .pagination .active-page {
            background: #0d3b66;
            color: #ffffff;
            border-color: #0d3b66;
        }

        .pagination .disabled-page {
            color: #94a3b8;
            background: #f8fafc;
        }

        .pagination-info {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
            margin-top: 12px;
        }

        .nav-item {
            text-decoration: none;
            color: #333;
            font-weight: 700;
            font-size: 14px;
            position: relative;
        }

        .nav-item.active {
            color: #0d3b66;
        }

        .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -28px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 4px;
            background-color: #0d3b66;
            border-radius: 4px 4px 0 0;
        }

        @media (max-width: 900px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .report-table-card {
                overflow-x: auto;
            }

            .main-content {
                padding-left: 20px;
                padding-right: 20px;
            }

            .nav-center {
                gap: 12px;
            }

            .nav-item {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="navbar-container">
        <header class="navbar">
            <div class="nav-left">
                <img src="uitmlogo.png" alt="UiTM Logo">
                <div class="nav-divider"></div>
                <img src="traceiotlogo.jpeg" alt="TraceIoT System">
            </div>

            <nav class="nav-center">
                <a href="admin_home.php" class="nav-item">Dashboard</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_components.php" class="nav-item">Components</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_damage_report.php" class="nav-item active">Damaged Items</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_borrowing_list.php" class="nav-item">Booking Record</a>
            </nav>

            <div class="nav-right" style="display: flex; align-items: center; gap: 20px; flex-wrap: nowrap; white-space: nowrap;">
                <a href="admin_profile.php" class="profile-section">
                    <div class="profile-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    Admin Profile
                </a>

                <div style="width: 1px; height: 25px; background-color: #ddd;"></div>

                <a href="admin_logout.php" style="color: #ff4d4d; font-weight: 700; text-decoration: none; font-size: 14px;">
                    🚪 Logout
                </a>
            </div>
        </header>
    </div>

    <main class="main-content">
        <div class="page-header">
            <h1>Damaged / Missing Items Report</h1>
            <p>Track components returned as Minor Damage, Damaged, or Missing after admin confirms the return.</p>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <h3>Need Replacement</h3>
                <p><?php echo (int)$summary['need_total']; ?></p>
            </div>

            <div class="summary-card">
                <h3>Minor Damage</h3>
                <p><?php echo (int)$summary['minor_total']; ?></p>
            </div>

            <div class="summary-card">
                <h3>Damaged</h3>
                <p><?php echo (int)$summary['damaged_total']; ?></p>
            </div>

            <div class="summary-card">
                <h3>Missing</h3>
                <p><?php echo (int)$summary['missing_total']; ?></p>
            </div>
        </div>

        <div class="filter-tabs">
            <?php
            $filters = ['All', 'Need Replacement', 'Replaced', 'Minor Damage', 'Damaged', 'Missing'];

            foreach ($filters as $filter_name) {
                $active = ($filter == $filter_name) ? 'active' : '';
                echo '<a class="filter-tab ' . $active . '" href="admin_damage_report.php?filter=' . urlencode($filter_name) . '">' . htmlspecialchars($filter_name) . '</a>';
            }
            ?>
        </div>

        <div class="search-container">
            <form action="admin_damage_report.php" method="GET" class="search-form">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">

                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="Search by component, student, or Application ID..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >

                <button type="submit" class="btn-search">Search 🔍</button>
            </form>
        </div>

        <?php if ($result && mysqli_num_rows($result) > 0) { ?>
            <div class="report-table-card">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Student</th>
                            <th>Component</th>
                            <th>Issue</th>
                            <th>Qty</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                            <tr>
                                <td>#<?php echo $row['Record_ID']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($row['student_name']); ?><br>
                                    <span class="muted"><?php echo htmlspecialchars($row['student_matric']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['component_name']); ?></td>
                                <td>
                                    <span class="issue-pill">
                                        <?php echo htmlspecialchars($row['issue_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo (int)$row['issue_quantity']; ?> unit(s)</td>
                                <td>
                                    <?php echo !empty($row['remarks']) ? htmlspecialchars($row['remarks']) : '<span class="muted">No remarks</span>'; ?>
                                </td>
                                <td>
                                    <?php if ($row['report_status'] == 'Replaced') { ?>
                                        <span class="badge badge-replaced">Replaced</span>
                                    <?php } else { ?>
                                        <span class="badge badge-need">Need Replacement</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($row['report_status'] != 'Replaced') { ?>
                                        <a 
                                            href="admin_damage_report.php?action=mark_replaced&id=<?php echo $row['report_id']; ?>"
                                            class="btn-action"
                                            onclick="confirmReplace(event, this.href)"
                                        >
                                            Mark Replaced + Add Stock
                                        </a>
                                    <?php } else { ?>
                                        <span class="muted">Done</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-info">
                Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> reports
            </div>

            <div class="pagination">
                <?php
                $base_params = [];

                if ($filter != 'All') {
                    $base_params['filter'] = $filter;
                }

                if ($search != '') {
                    $base_params['search'] = $search;
                }

                if ($current_page > 1) {
                    $base_params['page'] = $current_page - 1;
                    echo '<a href="admin_damage_report.php?' . http_build_query($base_params) . '">Previous</a>';
                } else {
                    echo '<span class="disabled-page">Previous</span>';
                }

                for ($i = 1; $i <= $total_pages; $i++) {
                    $base_params['page'] = $i;
                    $active_class = ($i == $current_page) ? 'active-page' : '';
                    echo '<a class="' . $active_class . '" href="admin_damage_report.php?' . http_build_query($base_params) . '">' . $i . '</a>';
                }

                if ($current_page < $total_pages) {
                    $base_params['page'] = $current_page + 1;
                    echo '<a href="admin_damage_report.php?' . http_build_query($base_params) . '">Next</a>';
                } else {
                    echo '<span class="disabled-page">Next</span>';
                }
                ?>
            </div>
        <?php } else { ?>
            <div class="empty-box">
                No damaged, missing, or replacement records found.
            </div>
        <?php } ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function confirmReplace(event, url) {
            event.preventDefault();

            Swal.fire({
                icon: 'question',
                title: 'Mark as replaced?',
                text: 'This will add the issue quantity back to component stock and mark this report as Replaced.',
                showCancelButton: true,
                confirmButtonText: 'Yes, update it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }
    </script>

    <?php showSweetAlert(); ?>
</body>
</html>
