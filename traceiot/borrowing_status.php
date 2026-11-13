<?php
session_start();
include 'connection.php';
include 'send_email.php';
include 'alerts.php';

if (!isset($_SESSION['student_id'])) {
    setSweetAlert('warning', 'Sign In Required', 'Please sign in as a student to access this page.');
    header("Location: signin.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

// --- PROCESS STUDENT RETURN REQUEST FOR EACH COMPONENT ---
if (isset($_POST['submit_return'])) {
    $record_id = mysqli_real_escape_string($conn, $_POST['record_id']);

    $target_dir = "uploads/proof/";

    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Make sure this record belongs to the logged-in student and is Approved
    $check_record = mysqli_query($conn, "
        SELECT Record_ID, status 
        FROM borrowing_record 
        WHERE Record_ID = '$record_id'
        AND Student_ID = '$student_id'
        LIMIT 1
    ");

    if (!$check_record || mysqli_num_rows($check_record) == 0) {
        setSweetAlert('error', 'Invalid Request', 'Borrowing record not found.');
        header("Location: borrowing_status.php");
        exit();
    }

    $record_data = mysqli_fetch_assoc($check_record);

    if ($record_data['status'] != 'Approved' && $record_data['status'] != 'On Hold') {
        setSweetAlert('warning', 'Action Not Allowed', 'Only approved or on-hold borrowings can be returned.');
        header("Location: borrowing_status.php");
        exit();
    }

    $success = true;

    if (isset($_POST['good_quantity']) && is_array($_POST['good_quantity'])) {

        $shared_proof_file_name = "";

        if (
            isset($_FILES['proof_image']['name']) &&
            $_FILES['proof_image']['error'] == 0
        ) {
            $original_name = basename($_FILES['proof_image']['name']);
            $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            $allowed_ext = ['jpg', 'jpeg', 'png'];

            if (in_array($file_ext, $allowed_ext)) {
                $safe_original_name = preg_replace("/[^A-Za-z0-9._-]/", "_", $original_name);
                $shared_proof_file_name = time() . "_" . $record_id . "_" . $safe_original_name;
                $target_file = $target_dir . $shared_proof_file_name;

                if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $target_file)) {
                    setSweetAlert('error', 'Upload Failed!', 'Failed to upload proof image.');
                    header("Location: borrowing_status.php");
                    exit();
                }
            } else {
                setSweetAlert('error', 'Invalid File Type', 'Only PNG, JPG, and JPEG files are allowed.');
                header("Location: borrowing_status.php");
                exit();
            }
        } else {
            setSweetAlert('warning', 'Proof Image Required', 'Please upload one proof image for this return request.');
            header("Location: borrowing_status.php");
            exit();
        }

        foreach ($_POST['good_quantity'] as $detail_id => $good_qty) {
            $detail_id = mysqli_real_escape_string($conn, $detail_id);

            $good_qty = isset($_POST['good_quantity'][$detail_id]) ? (int)$_POST['good_quantity'][$detail_id] : 0;
            $minor_qty = isset($_POST['minor_damage_quantity'][$detail_id]) ? (int)$_POST['minor_damage_quantity'][$detail_id] : 0;
            $damaged_qty = isset($_POST['damaged_quantity'][$detail_id]) ? (int)$_POST['damaged_quantity'][$detail_id] : 0;
            $missing_qty = isset($_POST['missing_quantity'][$detail_id]) ? (int)$_POST['missing_quantity'][$detail_id] : 0;

            if ($good_qty < 0 || $minor_qty < 0 || $damaged_qty < 0 || $missing_qty < 0) {
                setSweetAlert('warning', 'Invalid Quantity', 'Return quantity cannot be negative.');
                header("Location: borrowing_status.php");
                exit();
            }

            // Get borrowed quantity for this detail
            $check_detail = mysqli_query($conn, "
                SELECT quantity_borrowed 
                FROM borrowing_detail 
                WHERE detail_id = '$detail_id'
                AND Record_ID = '$record_id'
                LIMIT 1
            ");

            if (!$check_detail || mysqli_num_rows($check_detail) == 0) {
                setSweetAlert('error', 'Invalid Item', 'Return item not found.');
                header("Location: borrowing_status.php");
                exit();
            }

            $detail_data = mysqli_fetch_assoc($check_detail);
            $borrowed_qty = (int)$detail_data['quantity_borrowed'];
            $total_return_qty = $good_qty + $minor_qty + $damaged_qty + $missing_qty;

            if ($total_return_qty != $borrowed_qty) {
                setSweetAlert(
                    'warning', 
                    'Quantity Mismatch', 
                    'For every component, Good + Minor Damage + Damaged + Missing quantity must be equal to borrowed quantity.'
                );
                header("Location: borrowing_status.php");
                exit();
            }

            $remarks = mysqli_real_escape_string($conn, $_POST['remarks'][$detail_id]);

            $condition_summary = [];
            if ($good_qty > 0) {
                $condition_summary[] = "Good: " . $good_qty;
            }
            if ($minor_qty > 0) {
                $condition_summary[] = "Minor Damage: " . $minor_qty;
            }
            if ($damaged_qty > 0) {
                $condition_summary[] = "Damaged: " . $damaged_qty;
            }
            if ($missing_qty > 0) {
                $condition_summary[] = "Missing: " . $missing_qty;
            }

            $condition_text = mysqli_real_escape_string($conn, implode(", ", $condition_summary));

            $proof_file_name = $shared_proof_file_name;

            $update_detail = "UPDATE borrowing_detail 
                              SET return_condition = '$condition_text',
                                  good_quantity = '$good_qty',
                                  minor_damage_quantity = '$minor_qty',
                                  damaged_quantity = '$damaged_qty',
                                  missing_quantity = '$missing_qty',
                                  return_remarks = '$remarks',
                                  return_proof = '$proof_file_name'
                              WHERE detail_id = '$detail_id'
                              AND Record_ID = '$record_id'";

            if (!mysqli_query($conn, $update_detail)) {
                $success = false;
                setSweetAlert('error', 'Database Error', mysqli_error($conn));
                header("Location: borrowing_status.php");
                exit();
            }
        }

        if ($success) {
            $query_return = "UPDATE borrowing_record 
                             SET status = 'Pending Return'
                             WHERE Record_ID = '$record_id'
                             AND Student_ID = '$student_id'";

            if (mysqli_query($conn, $query_return)) {

                // GET ASSIGNED ADMIN EMAIL
                $get_admin = "SELECT admin.name, admin.email
                              FROM borrowing_record
                              JOIN admin ON borrowing_record.Admin_ID = admin.Admin_ID
                              WHERE borrowing_record.Record_ID = '$record_id'";

                $result_admin = mysqli_query($conn, $get_admin);

                if ($result_admin && mysqli_num_rows($result_admin) > 0) {
                    $admin_data = mysqli_fetch_assoc($result_admin);

                    $admin_name = $admin_data['name'];
                    $admin_email = $admin_data['email'];

                    $student_name = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'A student';

                    $subject = "TraceIoT Return Request Submitted";

                    $body = "
                        <h2>A student has submitted a return request</h2>
                        <p>Hello <strong>$admin_name</strong>,</p>
                        <p><strong>$student_name</strong> has submitted a return request for Application ID <strong>#$record_id</strong>.</p>
                        <p>Please log in to TraceIoT System to review the condition quantity, remarks, and proof image.</p>
                        <br>
                        <p>Thank you,<br><strong>TraceIoT System</strong></p>
                    ";

                    sendEmail($admin_email, $admin_name, $subject, $body);
                }

                setSweetAlert('success', 'Return Submitted!', 'Return request submitted! Waiting for admin confirmation.');
                header("Location: borrowing_status.php");
                exit();

            } else {
                setSweetAlert('error', 'Database Error', mysqli_error($conn));
                header("Location: borrowing_status.php");
                exit();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing Status - TraceIoT System</title>

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
            font-weight: 600; 
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

        .nav-text-divider { 
            color: #ccc; 
            font-weight: 300; 
        }

        .nav-right { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
        }

        .profile-section { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            font-weight: 600; 
            font-size: 14px; 
            cursor: pointer; 
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
            max-width: 950px; 
            margin: 130px auto 0; 
            padding: 0 20px; 
        }

        .page-title { 
            font-size: 26px; 
            font-weight: 800; 
            color: #000; 
            margin-bottom: 20px; 
        }

        .status-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .status-tab {
            text-decoration: none;
            padding: 9px 18px;
            border-radius: 20px;
            background: #ffffff;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid #e2e8f0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            transition: 0.2s;
        }

        .status-tab:hover {
            transform: translateY(-2px);
            background: #f8fafc;
        }

        .status-tab.active {
            background: #0d3b66;
            color: #ffffff;
            border-color: #0d3b66;
        }

        .status-card { 
            background-color: #ffffff; 
            border-radius: 15px; 
            padding: 22px 25px; 
            margin-bottom: 16px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.02); 
            border: 1px solid #eee; 
        }

        .compact-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
        }

        .compact-left {
            flex: 1;
        }

        .record-id { 
            font-size: 16px; 
            font-weight: 800; 
            color: #333; 
        }

        .record-date { 
            font-size: 13px; 
            color: #888; 
            font-weight: 500; 
            display: block; 
            margin-top: 5px; 
        }

        .compact-items {
            margin-top: 10px;
            font-size: 14px;
            color: #475569;
            font-weight: 600;
            line-height: 1.5;
        }

        .compact-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .badge { 
            padding: 6px 15px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 700; 
            text-transform: uppercase; 
            white-space: nowrap;
        }

        .badge-pending { 
            background-color: #fef08a; 
            color: #854d0e; 
        }

        .badge-approved { 
            background-color: #bbf7d0; 
            color: #166534; 
        }

        .badge-p-return { 
            background-color: #fed7aa; 
            color: #c2410c; 
        }

        .badge-onhold {
            background-color: #fecaca;
            color: #991b1b;
        }

        .badge-returned { 
            background-color: #e2e8f0; 
            color: #475569; 
        }

        .badge-rejected { 
            background-color: #fecaca; 
            color: #991b1b; 
        }

        .btn-view-details {
            background: #0d3b66;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-view-details:hover {
            background: #092b4a;
        }

        .details-section {
            display: none;
            margin-top: 20px;
            border-top: 1px dashed #e5e7eb;
            padding-top: 20px;
        }

        .status-card.open .details-section {
            display: block;
        }

        .pickup-instruction-box { 
            background-color: #f0f7ff; 
            border: 1px solid #cbdff7; 
            border-left: 5px solid #0284c7; 
            padding: 18px 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            margin-top: 5px; 
            display: flex; 
            flex-direction: column; 
            gap: 5px; 
        }

        .pickup-instruction-box h4 { 
            font-size: 13px; 
            font-weight: 800; 
            color: #0369a1; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        .pickup-instruction-box p { 
            font-size: 13px; 
            font-weight: 600; 
            color: #334155; 
            line-height: 1.5; 
        }

        .items-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 15px; 
            margin-top: 15px; 
        }

        .items-table th { 
            text-align: left; 
            font-size: 11px; 
            color: #aaa; 
            text-transform: uppercase; 
            padding-bottom: 10px; 
        }

        .items-table td { 
            padding: 10px 0; 
            font-size: 14px; 
            font-weight: 600; 
            color: #444; 
            border-top: 1px solid #f5f5f5; 
        }

        .btn-trigger-return { 
            background: #0d3b66; 
            color: white; 
            border: none; 
            padding: 8px 16px; 
            border-radius: 6px; 
            font-weight: 700; 
            font-size: 13px; 
            cursor: pointer; 
        }

        .btn-trigger-return:hover {
            background: #092b4a;
        }

        .return-form-box { 
            background: #f8fafc; 
            padding: 15px; 
            border-radius: 10px; 
            border: 1px solid #e2e8f0; 
            margin-top: 15px; 
            display: none; 
        }

        .return-item-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .return-item-box h4 {
            font-size: 15px;
            color: #0d3b66;
            margin-bottom: 5px;
        }

        .return-item-box p {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .return-item-box label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #334155;
        }

        .return-item-box select,
        .return-item-box textarea,
        .return-item-box input[type="file"] {
            width: 100%;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .return-item-box select {
            padding: 9px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: white;
        }

        .return-item-box textarea {
            min-height: 80px;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            resize: vertical;
        }

        .btn-submit-return { 
            background: #22c55e; 
            color: white; 
            border: none; 
            padding: 10px 18px; 
            border-radius: 6px; 
            font-weight: 700; 
            font-size: 13px; 
            cursor: pointer; 
            margin-top: 5px; 
        }

        .btn-submit-return:hover {
            background: #16a34a;
        }

        .return-note {
            font-size: 13px;
            color: #475569;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .proof-link {
            color: #0d3b66;
            font-weight: 700;
            text-decoration: none;
            font-size: 13px;
        }

        .proof-link:hover {
            text-decoration: underline;
        }

        .file-note-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .file-note {
            font-size: 12px;
            color: #64748b;
            font-style: italic;
        }


        .condition-qty-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }

        .condition-qty-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px;
        }

        .condition-qty-box label {
            font-size: 12px;
            margin-bottom: 5px;
        }

        .condition-qty-box input {
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
        }

        .qty-warning {
            display: none;
            color: #dc2626;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .qty-help {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .locked-qty {
            background-color: #e5e7eb !important;
            cursor: not-allowed;
        }

        .return-qty-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(110px, 1fr));
            gap: 8px;
            margin: 10px 0;
        }

        .return-qty-pill {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 0 20px;
            }

            .nav-center {
                gap: 12px;
            }

            .nav-item {
                font-size: 12px;
            }

            .main-content {
                margin-top: 120px;
            }

            .compact-summary {
                flex-direction: column;
                align-items: flex-start;
            }

            .compact-right {
                width: 100%;
                justify-content: space-between;
            }

            .file-note-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .condition-qty-grid,
            .return-qty-summary {
                grid-template-columns: 1fr 1fr;
            }
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

        .pagination a:hover {
            background: #f1f5f9;
        }

        .pagination .active-page {
            background: #0d3b66;
            color: #ffffff;
            border-color: #0d3b66;
        }

        .pagination .disabled-page {
            color: #94a3b8;
            background: #f8fafc;
            cursor: not-allowed;
        }

        .pagination-info {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
            margin-top: 12px;
        }

    </style>
</head>

<body>

    <div class="navbar-container">
        <header class="navbar">
            <div class="nav-left">
                <img src="uitmlogo.png" alt="UiTM Logo">
                <div class="nav-divider"></div>
                <img src="traceiotlogo.jpeg" alt="TraceIoT Logo">
            </div>

            <nav class="nav-center">
                <a href="home.php" class="nav-item">Home</a>
                <span class="nav-text-divider">|</span>
                <a href="components.php" class="nav-item">Components</a>
                <span class="nav-text-divider">|</span>
                <a href="borrowing_status.php" class="nav-item active">My Borrowings</a>
            </nav>
            
            <div class="nav-right">
                <a href="profile.php" class="profile-section" style="text-decoration: none; color: inherit;">
                    <div class="profile-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>

                    <div style="display: flex; flex-direction: column; line-height: 1.2;">
    <span style="
        font-size: 13px; 
        font-weight: 800; 
        display: block;
        width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    ">
        <?php 
        $display_name = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Student';
        echo htmlspecialchars($display_name);
        ?>
    </span>

    <span style="font-size: 11px; color: #64748b; font-weight: 600;">
        My Profile
    </span>
</div>
                </a>
            </div>
        </header>
    </div>

    <main class="main-content">
        <h1 class="page-title">My Borrowing Status</h1>

        <div class="status-tabs">
            <a href="borrowing_status.php" class="status-tab <?php echo ($status_filter == 'All') ? 'active' : ''; ?>">All</a>
            <a href="borrowing_status.php?status=Pending" class="status-tab <?php echo ($status_filter == 'Pending') ? 'active' : ''; ?>">Pending</a>
            <a href="borrowing_status.php?status=Approved" class="status-tab <?php echo ($status_filter == 'Approved') ? 'active' : ''; ?>">Approved</a>
            <a href="borrowing_status.php?status=Pending Return" class="status-tab <?php echo ($status_filter == 'Pending Return') ? 'active' : ''; ?>">Pending Return</a>
            <a href="borrowing_status.php?status=On Hold" class="status-tab <?php echo ($status_filter == 'On Hold') ? 'active' : ''; ?>">On Hold</a>
            <a href="borrowing_status.php?status=Returned" class="status-tab <?php echo ($status_filter == 'Returned') ? 'active' : ''; ?>">Returned</a>
            <a href="borrowing_status.php?status=Rejected" class="status-tab <?php echo ($status_filter == 'Rejected') ? 'active' : ''; ?>">Rejected</a>
        </div>

        <?php
        $query = "SELECT br.*, s.name AS admin_name, s.office_location 
                  FROM borrowing_record br 
                  LEFT JOIN ADMIN s ON br.Admin_ID = s.Admin_ID 
                  WHERE br.Student_ID = '$student_id'";

        if ($status_filter != 'All') {
            $safe_status = mysqli_real_escape_string($conn, $status_filter);
            $query .= " AND br.status = '$safe_status'";
        }        $records_per_page = 10;
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        if ($current_page < 1) {
            $current_page = 1;
        }

        $count_query = "SELECT COUNT(*) AS total_records
                        FROM borrowing_record br
                        WHERE br.Student_ID = '$student_id'";

        if ($status_filter != 'All') {
            $count_query .= " AND br.status = '$safe_status'";
        }

        $count_result = mysqli_query($conn, $count_query);
        $total_records = 0;

        if ($count_result) {
            $count_row = mysqli_fetch_assoc($count_result);
            $total_records = (int)$count_row['total_records'];
        }

        $total_pages = ceil($total_records / $records_per_page);

        if ($total_pages < 1) {
            $total_pages = 1;
        }

        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }

        $offset = ($current_page - 1) * $records_per_page;

        $query .= " ORDER BY 
                    FIELD(br.status, 'Pending', 'Pending Return', 'On Hold', 'Approved', 'Returned', 'Rejected'),
                    br.Record_ID DESC
                    LIMIT $records_per_page OFFSET $offset";

        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $r_id = $row['Record_ID'];
                $status = $row['status'];
                
                $badge_class = 'badge-pending';

                if ($status == 'Approved') {
                    $badge_class = 'badge-approved';
                } elseif ($status == 'Pending Return') {
                    $badge_class = 'badge-p-return';
                } elseif ($status == 'On Hold') {
                    $badge_class = 'badge-onhold';
                } elseif ($status == 'Returned') {
                    $badge_class = 'badge-returned';
                } elseif ($status == 'Rejected') {
                    $badge_class = 'badge-rejected';
                }

                $query_items = "SELECT bd.detail_id,
                                       bd.Component_ID,
                                       bd.quantity_borrowed,
                                       bd.return_condition,
                                       bd.good_quantity,
                                       bd.minor_damage_quantity,
                                       bd.damaged_quantity,
                                       bd.missing_quantity,
                                       bd.return_remarks,
                                       bd.return_proof,
                                       c.name
                                FROM borrowing_detail bd
                                JOIN COMPONENT c ON bd.Component_ID = c.Component_ID
                                WHERE bd.Record_ID = '$r_id'";

                $result_items = mysqli_query($conn, $query_items);

                $items = [];
                $summary_items = [];

                while ($item = mysqli_fetch_assoc($result_items)) {
                    $items[] = $item;
                    $summary_items[] = htmlspecialchars($item['name']) . " × " . $item['quantity_borrowed'];
                }

                $summary_text = !empty($summary_items) ? implode(" • ", $summary_items) : "No components found";
        ?>

                <div class="status-card" id="card-<?php echo $r_id; ?>">
                    
                    <div class="compact-summary">
                        <div class="compact-left">
                            <span class="record-id">Application ID: #<?php echo $r_id; ?></span>
                            <span class="record-date">📅 Date: <?php echo $row['borrow_date']; ?></span>
                            <div class="compact-items">
                                <?php echo $summary_text; ?>
                            </div>
                        </div>

                        <div class="compact-right">
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo $status; ?>
                            </span>

                            <button class="btn-view-details" id="btn-details-<?php echo $r_id; ?>" onclick="toggleDetails(<?php echo $r_id; ?>)">
                                View Details
                            </button>
                        </div>
                    </div>

                    <div class="details-section">

                        <?php if ($status == 'Approved') { 
                            $venue = !empty($row['office_location']) ? $row['office_location'] : 'IoT Laboratory, Block A';
                            $officer = !empty($row['admin_name']) ? $row['admin_name'] : 'The Duty Lab Admin';
                        ?>

                            <?php
                            $collection_day = !empty($row['collection_day']) ? $row['collection_day'] : date('l', strtotime($row['borrow_date']));
                            $collection_date = date('d-m-Y', strtotime($row['borrow_date']));
                            $collection_time = !empty($row['collection_time']) ? $row['collection_time'] : 'Please refer to the lab admin';
                            $collection_venue = !empty($row['collection_venue']) ? $row['collection_venue'] : $venue;
                            ?>

                            <div class="pickup-instruction-box">
                                <h4>📍 Collection Instructions</h4>

                                <p>
                                    Your request is approved! Please collect your components according to the details below:
                                </p>

                                <div style="background:#ffffff; border:1px solid #cbdff7; border-radius:8px; padding:12px; margin-top:8px;">
                                    <p><strong>Collection Day:</strong> <?php echo htmlspecialchars($collection_day); ?></p>
                                    <p><strong>Collection Date:</strong> <?php echo htmlspecialchars($collection_date); ?></p>
                                    <p><strong>Collection Time:</strong> <?php echo htmlspecialchars($collection_time); ?></p>
                                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($collection_venue); ?></p>
                                    <p><strong>Person In Charge:</strong> <?php echo htmlspecialchars($officer); ?></p>
                                </div>

                                <p style="margin-top:8px;">
                                    Please bring your student ID for verification. Do not attempt to open the storage independently.
                                </p>
                            </div>

                        <?php } elseif ($status == 'Pending') { ?>

                            <div class="pickup-instruction-box" style="background-color: #fffbeb; border-color: #fef3c7; border-left-color: #f59e0b;">
                                <h4 style="color: #b45309;">⏳ Verification Pending</h4>
                                <p style="color: #78350f;">
                                    The collection venue and handling personnel will be assigned once an administrator reviews and approves this request form.
                                </p>
                            </div>

                        <?php } elseif ($status == 'Pending Return') { ?>

                            <div class="pickup-instruction-box" style="background-color: #fff7ed; border-color: #fed7aa; border-left-color: #f97316;">
                                <h4 style="color: #c2410c;">📦 Return Verification Pending</h4>
                                <p style="color: #7c2d12;">
                                    Your return request has been submitted. Please wait for admin verification.
                                </p>
                            </div>

                        <?php } elseif ($status == 'On Hold') { ?>

                            <div class="pickup-instruction-box" style="background-color: #fef2f2; border-color: #fecaca; border-left-color: #ef4444;">
                                <h4 style="color: #991b1b;">⏸ Return On Hold</h4>
                                <p style="color: #7f1d1d;">
                                    Your return request has been rejected because one or more items are damaged, missing, or incomplete.
                                    Please replace or complete the required item(s), then submit the return request again.
                                </p>
                            </div>

                        <?php } elseif ($status == 'Returned') { ?>

                            <div class="pickup-instruction-box" style="background-color: #f8fafc; border-color: #cbd5e1; border-left-color: #64748b;">
                                <h4 style="color: #475569;">✅ Components Returned</h4>
                                <p style="color: #334155;">
                                    This borrowing record has been verified and marked as returned.
                                </p>
                            </div>

                        <?php } elseif ($status == 'Rejected') { ?>

                            <div class="pickup-instruction-box" style="background-color: #fef2f2; border-color: #fecaca; border-left-color: #ef4444;">
                                <h4 style="color: #991b1b;">❌ Request Rejected</h4>
                                <p style="color: #7f1d1d;">
                                    This borrowing request has been rejected. Please contact the lab admin for more information.
                                </p>
                            </div>

                        <?php } ?>

                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Component Name</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($items as $item) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo $item['quantity_borrowed']; ?> Units</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>

                        <?php if ($status == 'Approved' || $status == 'On Hold') { ?>

                            <button class="btn-trigger-return" onclick="toggleReturnForm(<?php echo $r_id; ?>)">
                                <?php echo ($status == 'On Hold') ? 'Submit Return Again 📦' : 'Return Components 📦'; ?>
                            </button>
                            
                            <div class="return-form-box" id="form-<?php echo $r_id; ?>">
                                <form action="borrowing_status.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="record_id" value="<?php echo $r_id; ?>">

                                    <p class="return-note">
                                        Please enter the return quantity for each component and upload one proof image at the bottom before submitting your return request.
                                        If this record is On Hold, replace or complete the required item(s) first, then submit the return again.
                                    </p>

                                    <?php foreach ($items as $item) { 
                                        $detail_id = $item['detail_id'];
                                    ?>

                                        <div class="return-item-box">
                                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                            <p>Quantity: <?php echo $item['quantity_borrowed']; ?> Units</p>

                                            <label>Return Condition Quantity</label>
                                            <div class="qty-help">
                                                Please enter the quantity for each condition. Total must be equal to <?php echo $item['quantity_borrowed']; ?> units.
                                            </div>

                                            <div class="condition-qty-grid">
                                                <div class="condition-qty-box">
                                                    <label>Good</label>
                                                    <input 
                                                        type="number" 
                                                        name="good_quantity[<?php echo $detail_id; ?>]" 
                                                        value="0" 
                                                        min="0" 
                                                        max="<?php echo $item['quantity_borrowed']; ?>"
                                                        data-detail="<?php echo $detail_id; ?>"
                                                        data-max="<?php echo $item['quantity_borrowed']; ?>"
                                                        class="condition-qty-input"
                                                        required>
                                                </div>

                                                <div class="condition-qty-box">
                                                    <label>Minor Damage</label>
                                                    <input 
                                                        type="number" 
                                                        name="minor_damage_quantity[<?php echo $detail_id; ?>]" 
                                                        value="0" 
                                                        min="0" 
                                                        max="<?php echo $item['quantity_borrowed']; ?>"
                                                        data-detail="<?php echo $detail_id; ?>"
                                                        data-max="<?php echo $item['quantity_borrowed']; ?>"
                                                        class="condition-qty-input"
                                                        required>
                                                </div>

                                                <div class="condition-qty-box">
                                                    <label>Damaged</label>
                                                    <input 
                                                        type="number" 
                                                        name="damaged_quantity[<?php echo $detail_id; ?>]" 
                                                        value="0" 
                                                        min="0" 
                                                        max="<?php echo $item['quantity_borrowed']; ?>"
                                                        data-detail="<?php echo $detail_id; ?>"
                                                        data-max="<?php echo $item['quantity_borrowed']; ?>"
                                                        class="condition-qty-input"
                                                        required>
                                                </div>

                                                <div class="condition-qty-box">
                                                    <label>Missing</label>
                                                    <input 
                                                        type="number" 
                                                        name="missing_quantity[<?php echo $detail_id; ?>]" 
                                                        value="0" 
                                                        min="0" 
                                                        max="<?php echo $item['quantity_borrowed']; ?>"
                                                        data-detail="<?php echo $detail_id; ?>"
                                                        data-max="<?php echo $item['quantity_borrowed']; ?>"
                                                        class="condition-qty-input"
                                                        required>
                                                </div>
                                            </div>

                                            <div class="qty-warning" id="qty-warning-<?php echo $detail_id; ?>">
                                                Total return quantity must be exactly <?php echo $item['quantity_borrowed']; ?> units.
                                            </div>

                                            <label>Remarks</label>
                                            <textarea 
                                                name="remarks[<?php echo $detail_id; ?>]" 
                                                placeholder="Example: Component is working properly / one pin is bent / sensor not responding..."
                                                required></textarea>


                                        </div>

                                    <?php } ?>

                                    <div class="return-item-box">
                                        <h4>Proof Image</h4>
                                        <p>Upload one proof image for all returned components in this request.</p>

                                        <div class="file-note-row">
                                            <input 
                                                type="file" 
                                                name="proof_image" 
                                                accept=".png,.jpg,.jpeg" 
                                                required>

                                            <small class="file-note">* Only JPG, JPEG, and PNG files are allowed.</small>
                                        </div>
                                    </div>

                                    <button type="submit" name="submit_return" class="btn-submit-return">
                                        Send Return Request
                                    </button>
                                </form>
                            </div>

                        <?php } ?>

                        <?php if ($status == 'Pending Return' || $status == 'Returned') { ?>

                            <div class="return-form-box" style="display:block;">
                                <p class="return-note">
                                    Return details submitted:
                                </p>

                                <?php foreach ($items as $item) { ?>

                                    <div class="return-item-box">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p>Quantity: <?php echo $item['quantity_borrowed']; ?> Units</p>

                                        <p>
                                            <strong>Condition Summary:</strong>
                                            <?php echo !empty($item['return_condition']) ? htmlspecialchars($item['return_condition']) : 'Not provided'; ?>
                                        </p>

                                        <div class="return-qty-summary">
                                            <div class="return-qty-pill">Good: <?php echo (int)$item['good_quantity']; ?></div>
                                            <div class="return-qty-pill">Minor Damage: <?php echo (int)$item['minor_damage_quantity']; ?></div>
                                            <div class="return-qty-pill">Damaged: <?php echo (int)$item['damaged_quantity']; ?></div>
                                            <div class="return-qty-pill">Missing: <?php echo (int)$item['missing_quantity']; ?></div>
                                        </div>

                                        <p>
                                            <strong>Remarks:</strong>
                                            <?php echo !empty($item['return_remarks']) ? htmlspecialchars($item['return_remarks']) : 'Not provided'; ?>
                                        </p>


                                    </div>

                                <?php } ?>

                                <?php 
                                $shared_proof = '';
                                foreach ($items as $proof_item) {
                                    if (!empty($proof_item['return_proof'])) {
                                        $shared_proof = $proof_item['return_proof'];
                                        break;
                                    }
                                }
                                ?>

                                <div class="return-item-box">
                                    <h4>Proof Image</h4>

                                    <?php if (!empty($shared_proof)) { ?>
                                        <p>
                                            <a class="proof-link" href="uploads/proof/<?php echo htmlspecialchars($shared_proof); ?>" target="_blank">
                                                View Proof Image
                                            </a>
                                        </p>
                                    <?php } else { ?>
                                        <p><strong>Proof:</strong> Not provided</p>
                                    <?php } ?>
                                </div>
                            </div>

                        <?php } ?>

                    </div>
                </div>

        <?php
            }
        } else {
            if ($status_filter == 'All') {
                echo "<p style='text-align:center; color:#888; padding:50px; background:#fff; border-radius:10px;'>You don't have any borrowing records yet.</p>";
            } else {
                echo "<p style='text-align:center; color:#888; padding:50px; background:#fff; border-radius:10px;'>No borrowing records found for this status.</p>";
            }
        }
        ?>

        <?php if ($total_records > 0) { ?>
            <div class="pagination-info">
                Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> applications
            </div>

            <div class="pagination">
                <?php
                $base_params = [];

                if ($status_filter != 'All') {
                    $base_params['status'] = $status_filter;
                }

                if ($current_page > 1) {
                    $base_params['page'] = $current_page - 1;
                    echo '<a href="borrowing_status.php?' . http_build_query($base_params) . '">Previous</a>';
                } else {
                    echo '<span class="disabled-page">Previous</span>';
                }

                for ($i = 1; $i <= $total_pages; $i++) {
                    $base_params['page'] = $i;
                    $active_class = ($i == $current_page) ? 'active-page' : '';
                    echo '<a class="' . $active_class . '" href="borrowing_status.php?' . http_build_query($base_params) . '">' . $i . '</a>';
                }

                if ($current_page < $total_pages) {
                    $base_params['page'] = $current_page + 1;
                    echo '<a href="borrowing_status.php?' . http_build_query($base_params) . '">Next</a>';
                } else {
                    echo '<span class="disabled-page">Next</span>';
                }
                ?>
            </div>
        <?php } ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function toggleDetails(id) {
            var card = document.getElementById('card-' + id);
            var button = document.getElementById('btn-details-' + id);

            card.classList.toggle('open');

            if (card.classList.contains('open')) {
                button.innerText = 'Hide Details';
            } else {
                button.innerText = 'View Details';
            }
        }

        function toggleReturnForm(id) {
            var form = document.getElementById('form-' + id);

            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function validateReturnQuantities(formElement = null) {
            let allValid = true;
            const processedDetails = new Set();

            const scope = formElement ? formElement : document;
            const qtyInputs = scope.querySelectorAll('.condition-qty-input');

            qtyInputs.forEach(input => {
                const detailId = input.dataset.detail;

                if (processedDetails.has(detailId)) {
                    return;
                }

                processedDetails.add(detailId);

                const inputs = scope.querySelectorAll('.condition-qty-input[data-detail="' + detailId + '"]');
                const maxQty = parseInt(inputs[0].dataset.max);
                let total = 0;

                inputs.forEach(qtyInput => {
                    let value = parseInt(qtyInput.value);

                    if (isNaN(value) || value < 0) {
                        value = 0;

                        if (!qtyInput.readOnly) {
                            qtyInput.value = 0;
                        }
                    }

                    total += value;
                });

                const warning = document.getElementById('qty-warning-' + detailId);

                if (total !== maxQty) {
                    allValid = false;
                    if (warning) {
                        warning.style.display = 'block';
                    }
                } else {
                    if (warning) {
                        warning.style.display = 'none';
                    }
                }
            });

            return allValid;
        }

        function controlConditionInputs(changedInput) {
            const detailId = changedInput.dataset.detail;
            const inputs = document.querySelectorAll('.condition-qty-input[data-detail="' + detailId + '"]');

            if (!inputs || inputs.length === 0) {
                return;
            }

            const maxQty = parseInt(inputs[0].dataset.max);
            let total = 0;

            inputs.forEach(input => {
                let value = parseInt(input.value);

                if (isNaN(value) || value < 0) {
                    value = 0;
                    input.value = 0;
                }

                if (value > maxQty) {
                    value = maxQty;
                    input.value = maxQty;
                }

                total += value;
            });

            // Kalau total melebihi quantity pinjam, kurangkan balik input yang sedang ditaip
            if (total > maxQty) {
                let changedValue = parseInt(changedInput.value);

                if (isNaN(changedValue)) {
                    changedValue = 0;
                }

                const extra = total - maxQty;
                const newValue = Math.max(0, changedValue - extra);

                changedInput.value = newValue;
                total = maxQty;
            }

            // Bila total dah cukup, condition lain yang masih 0 akan dikosongkan dan disable
            if (total === maxQty) {
                inputs.forEach(input => {
                    const value = parseInt(input.value) || 0;

                    if (value === 0) {
                        input.value = "";
                        input.readOnly = true;
                        input.classList.add('locked-qty');
                    } else {
                        input.readOnly = false;
                        input.classList.remove('locked-qty');
                    }
                });
            } else {
                // Kalau belum cukup, semua condition boleh isi semula
                inputs.forEach(input => {
                    input.readOnly = false;
                    input.classList.remove('locked-qty');

                    if (input.value === "") {
                        input.value = 0;
                    }
                });
            }

            validateReturnQuantities(changedInput.closest('form'));
        }

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('condition-qty-input')) {
                controlConditionInputs(e.target);
            }
        });

        document.addEventListener('focusin', function(e) {
            if (e.target.classList.contains('condition-qty-input') && e.target.value === "0") {
                e.target.select();
            }
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.querySelector('.condition-qty-input')) {
                    if (!validateReturnQuantities(this)) {
                        e.preventDefault();

                        Swal.fire({
                            icon: 'warning',
                            title: 'Quantity Mismatch',
                            text: 'Good + Minor Damage + Damaged + Missing quantity must match the borrowed quantity.'
                        });
                    }
                }
            });
        });

    </script>

    <?php showSweetAlert(); ?>

</body>
</html>