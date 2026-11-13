<?php
session_start();
include 'connection.php';
include 'alerts.php';

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: signin.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// --- UPDATE PROFILE PROCESS ---
if (isset($_POST['update_profile'])) {
    $new_phone   = mysqli_real_escape_string($conn, $_POST['phone_num']);
    $new_faculty = mysqli_real_escape_string($conn, $_POST['faculty']);
    
    $update_query = "UPDATE student 
                     SET phone_num = '$new_phone', faculty = '$new_faculty' 
                     WHERE Student_ID = '$student_id'";
    
    if (mysqli_query($conn, $update_query)) {
        setSweetAlert('success', 'Profile Updated!', 'Profile updated successfully.');
        header("Location: profile.php");
        exit();
    } else {
        setSweetAlert('error', 'Update Failed!', mysqli_error($conn));
    }
}

// --- GET STUDENT INFO ---
$query = "SELECT * FROM student WHERE Student_ID = '$student_id'";
$result = mysqli_query($conn, $query);
$user_data = mysqli_fetch_assoc($result);

// --- STATISTICS ---
$q_pending = "SELECT COUNT(*) AS total FROM borrowing_record WHERE Student_ID = '$student_id' AND status = 'Pending'";
$res_pending = mysqli_query($conn, $q_pending);
$count_pending = mysqli_fetch_assoc($res_pending)['total'];

$q_onloan = "SELECT COUNT(*) AS total FROM borrowing_record WHERE Student_ID = '$student_id' AND status IN ('Approved', 'Pending Return')";
$res_onloan = mysqli_query($conn, $q_onloan);
$count_onloan = mysqli_fetch_assoc($res_onloan)['total'];

$q_total_rec = "SELECT COUNT(*) AS total FROM borrowing_record WHERE Student_ID = '$student_id'";
$res_total_rec = mysqli_query($conn, $q_total_rec);
$count_total_rec = mysqli_fetch_assoc($res_total_rec)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TraceIoT System</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', sans-serif; 
        }

        body { 
            background: linear-gradient(135deg, #f6f8fb 0%, #e9edf3 100%); 
            min-height: 100vh; 
            overflow-x: hidden; 
            padding-bottom: 50px; 
        }

        /* --- NAVIGATION BAR --- */
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
            display: flex; 
            align-items: center; 
            gap: 5px; 
            cursor: pointer; 
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
            font-weight: 700; 
            font-size: 14px; 
            cursor: pointer; 
            text-decoration: none; 
            color: #0d3b66; 
            position: relative; 
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

        .profile-section::after { 
            content: ''; 
            position: absolute; 
            bottom: -28px; 
            left: 50%; 
            transform: translateX(-50%); 
            width: 50px; 
            height: 4px; 
            background-color: #0d3b66; 
            border-radius: 4px 4px 0 0; 
        }

        /* --- MAIN WRAPPER --- */
        .profile-wrapper { 
            max-width: 1250px; 
            margin: 120px auto 0; 
            display: flex; 
            flex-direction: column; 
            gap: 30px; 
        }

        /* --- PROFILE TOP BOX --- */
        .profile-top-box { 
            background-color: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.05); 
            padding: 40px; 
            display: flex; 
            gap: 40px; 
        }
        
        .profile-left { 
            flex: 1; 
            max-width: 450px; 
            display: flex; 
            flex-direction: column; 
            gap: 30px; 
        }

        .user-header { 
            display: flex; 
            gap: 20px; 
            align-items: center; 
        }

        .avatar-circle { 
            width: 100px; 
            height: 100px; 
            background-color: #6da5ff; 
            border-radius: 50%; 
            overflow: hidden; 
            flex-shrink: 0; 
        }

        .avatar-circle img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }

        .user-title h1 { 
            font-size: 22px; 
            font-weight: 900; 
            color: #000; 
            line-height: 1.2; 
            margin-bottom: 5px; 
            text-transform: uppercase; 
        }

        .user-title p { 
            font-size: 14px; 
            color: #666; 
            margin-bottom: 10px; 
        }

        .badges { 
            display: flex; 
            gap: 10px; 
        }

        .badge { 
            background-color: #9340ff; 
            color: white; 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            box-shadow: 0 2px 5px rgba(147, 64, 255, 0.3); 
        }
        
        .info-grid { 
            display: grid; 
            grid-template-columns: 120px 1fr; 
            row-gap: 25px; 
            font-size: 14px; 
        }

        .info-label { 
            font-weight: 800; 
            color: #000; 
        }

        .info-value { 
            font-weight: 600; 
            color: #444; 
        }

        .address-box { 
            background-color: #dcdcdc; 
            border: 1px solid #777; 
            border-radius: 10px; 
            padding: 15px 20px; 
        }

        .address-box h4 { 
            font-size: 14px; 
            font-weight: 800; 
            color: #000; 
            margin-bottom: 5px; 
        }

        .address-box p { 
            font-size: 13px; 
            font-weight: 600; 
            color: #444; 
            line-height: 1.5; 
        }

        .btn-edit-profile { 
            width: 100%; 
            background-color: #f0f4f8; 
            color: #0d3b66; 
            border: 2px dashed #cbd5e1; 
            padding: 15px; 
            border-radius: 10px; 
            font-size: 14px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.2s ease; 
            margin-top: -10px; 
        }

        .btn-edit-profile:hover { 
            background-color: #e2e8f0; 
            border-color: #0d3b66; 
        }

        /* --- RIGHT PANEL --- */
        .profile-right { 
            flex: 1.5; 
            display: flex; 
            flex-direction: column; 
        }

        .right-topbar { 
            display: flex; 
            justify-content: flex-end; 
            margin-bottom: 20px; 
        }

        .id-badge { 
            background-color: #ffc107; 
            color: #000; 
            padding: 8px 20px; 
            border-radius: 20px; 
            font-weight: 800; 
            font-size: 14px; 
            border: 1px solid #000; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            box-shadow: 2px 2px 0px #000; 
        }
        
        .dashboard-content { 
            display: flex; 
            gap: 20px; 
        }

        .tables-column { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            gap: 20px; 
        }
        
        .table-container { 
            border: 1px solid #333; 
            border-radius: 12px; 
            background-color: #e8e8e8; 
            overflow: hidden; 
        }

        .table-header { 
            background-color: #8fa096; 
            padding: 12px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #333; 
        }

        .table-header.pending-header { 
            background-color: #ffcc80; 
        }

        .table-header.history-header { 
            background-color: #cbd5e1; 
        }

        .table-header h3 { 
            font-size: 16px; 
            font-weight: 800; 
            color: #000; 
        }

        .btn-open-close { 
            background-color: #fff; 
            border: 1px solid #333; 
            border-radius: 15px; 
            padding: 4px 12px; 
            font-size: 12px; 
            font-weight: 700; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
        }

        .table-body {
            display: none;
            background-color: #e8e8e8;
        }

        .table-body.open {
            display: block;
        }
        
        .table-container table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left; 
            font-size: 12px; 
        }

        .table-container th { 
            padding: 10px 15px; 
            font-weight: 800; 
            color: #000; 
            border-bottom: 1px solid #ccc; 
            background-color: #d8d8d8; 
        }

        .table-container td { 
            padding: 10px 15px; 
            font-weight: 500; 
            color: #444; 
            border-bottom: 1px dashed #ccc; 
        }

        .table-container tr:last-child td { 
            border-bottom: none; 
        }

        .table-footer {
            padding: 12px 15px;
            border-top: 1px solid #ccc;
            background-color: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        .btn-view-all {
            text-decoration: none;
            background-color: #0d3b66;
            color: white;
            padding: 7px 14px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-view-all:hover {
            background-color: #092b4a;
        }

        /* --- STATS COLUMN --- */
        .stats-column { 
            width: 120px; 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
        }

        .stat-card { 
            background-color: #e0e0e0; 
            border: 1px solid #333; 
            border-radius: 20px; 
            padding: 15px 10px; 
            text-align: center; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            height: 105px; 
        }

        .stat-title { 
            font-size: 12px; 
            font-weight: 800; 
            color: #000; 
            margin-bottom: 5px; 
            line-height: 1.2; 
            border-bottom: 1px solid #aaa; 
            padding-bottom: 5px; 
            width: 80%; 
        }

        .stat-number { 
            font-size: 18px; 
            font-weight: 900; 
            color: #9340ff; 
        }

        /* --- PROFILE BOTTOM BOX --- */
        .profile-bottom-box { 
            background-color: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.05); 
            padding: 40px; 
        }

        /* --- MODAL --- */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5); 
            backdrop-filter: blur(4px); 
            justify-content: center; 
            align-items: center; 
        }

        .modal.show { 
            display: flex; 
        }

        .modal-content { 
            background-color: #fff; 
            padding: 30px 40px; 
            border-radius: 15px; 
            width: 90%; 
            max-width: 450px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
            position: relative; 
            animation: slideDown 0.3s ease-out; 
        }

        @keyframes slideDown { 
            from { 
                transform: translateY(-30px); 
                opacity: 0; 
            } 
            to { 
                transform: translateY(0); 
                opacity: 1; 
            } 
        }
        
        .close-modal { 
            position: absolute; 
            top: 20px; 
            right: 25px; 
            font-size: 28px; 
            font-weight: bold; 
            cursor: pointer; 
            color: #aaa; 
            transition: color 0.2s; 
        }

        .close-modal:hover { 
            color: #333; 
        }
        
        .modal-title { 
            font-size: 22px; 
            font-weight: 800; 
            color: #0d3b66; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #eee; 
            padding-bottom: 10px; 
        }

        .form-group { 
            margin-bottom: 15px; 
        }

        .form-group label { 
            display: block; 
            font-size: 12px; 
            font-weight: 700; 
            color: #555; 
            margin-bottom: 5px; 
        }

        .form-group input, 
        .form-group select { 
            width: 100%; 
            padding: 12px 12px; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            font-size: 14px; 
            outline: none; 
        }

        .form-group input:focus, 
        .form-group select:focus { 
            border-color: #0d3b66; 
        }

        .btn-submit { 
            width: 100%; 
            background-color: #0d3b66; 
            color: white; 
            border: none; 
            padding: 14px; 
            border-radius: 8px; 
            font-size: 15px; 
            font-weight: 700; 
            cursor: pointer; 
            margin-top: 15px; 
            transition: background 0.3s; 
        }

        .btn-submit:hover { 
            background-color: #1a5b9e; 
        }

        @media (max-width: 900px) {
            .profile-top-box {
                flex-direction: column;
            }

            .profile-left {
                max-width: 100%;
            }

            .dashboard-content {
                flex-direction: column;
            }

            .stats-column {
                width: 100%;
                flex-direction: row;
            }

            .stat-card {
                flex: 1;
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
                <img src="traceiotlogo.jpeg" alt="TraceIoT Logo">
            </div>

            <nav class="nav-center">
                <a href="home.php" class="nav-item">Home</a>
                <span class="nav-text-divider">|</span>
                <a href="components.php" class="nav-item">Components</a>
                <span class="nav-text-divider">|</span>
                <a href="borrowing_status.php" class="nav-item">My Borrowings</a>
            </nav>

            <div class="nav-right">
                <a href="profile.php" class="profile-section" style="text-decoration: none; color: inherit;">
                    <div class="profile-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    My Profile
                </a>

                <a href="logout.php" style="color: red; font-weight: 700; text-decoration: none; font-size: 14px;">
                    🚪 Logout
                </a>
            </div>
        </header>
    </div>

    <main class="profile-wrapper">
        
        <section class="profile-top-box">
            
            <div class="profile-left">
                <div class="user-header">
                    <div class="avatar-circle">
                        <img src="https://api.dicebear.com/7.x/bottts/svg?seed=<?php echo htmlspecialchars($user_data['Student_ID']); ?>" alt="User Avatar">
                    </div>

                    <div class="user-title">
                        <h1><?php echo strtoupper(htmlspecialchars($user_data['name'])); ?></h1>
                        <p>PAHANG, UiTM Raub Campus</p>

                        <div class="badges">
                            <span class="badge">🎓 Diploma</span>
                            <span class="badge">📖 Full Time</span>
                        </div>
                    </div>
                </div>

                <div class="info-grid">
                    <span class="info-label">Faculty</span>
                    <span class="info-value" style="text-transform: uppercase;">
                        <?php echo !empty($user_data['faculty']) ? htmlspecialchars($user_data['faculty']) : 'NOT SET'; ?>
                    </span>

                    <span class="info-label">Phone Number</span>
                    <span class="info-value">
                        <?php echo !empty($user_data['phone_num']) ? htmlspecialchars($user_data['phone_num']) : 'NOT SET'; ?>
                    </span>

                    <span class="info-label">Email</span>
                    <span class="info-value">
                        <?php echo htmlspecialchars($user_data['email']); ?>
                    </span>
                </div>

                <div class="address-box">
                    <h4>Address</h4>
                    <p>Residential College, UiTM Raub Campus, 27600 Raub, Pahang.</p>
                </div>

                <button id="openEditModal" class="btn-edit-profile">
                    ✏️ Edit Profile
                </button>
            </div>

            <div class="profile-right">
                <div class="right-topbar">
                    <div class="id-badge">
                        🪪 ID NO: <?php echo htmlspecialchars($user_data['Student_ID']); ?>
                    </div>
                </div>
                
                <div class="dashboard-content">
                    
                    <div class="tables-column">
                        
                        <!-- BORROW REQUEST TABLE -->
                        <div class="table-container">
                            <div class="table-header pending-header">
                                <h3>
                                    Borrow Request 
                                    <span style="font-size: 11px; font-weight:600; color:#555;">
                                        (Pending Approval)
                                    </span>
                                </h3>

                                <button class="btn-open-close" onclick="toggleTable('pending-table', this)">
                                    ⌄ Open
                                </button>
                            </div>

                            <div class="table-body" id="pending-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Borrow Date</th>
                                            <th>Return Date</th>
                                            <th>Components</th>
                                            <th>Quantity</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php
                                        $q_request = "SELECT br.borrow_date, br.return_date, c.name AS comp_name, bd.quantity_borrowed 
                                                      FROM borrowing_record br
                                                      JOIN borrowing_detail bd ON br.Record_ID = bd.Record_ID
                                                      JOIN COMPONENT c ON bd.Component_ID = c.Component_ID
                                                      WHERE br.Student_ID = '$student_id' AND br.status = 'Pending'
                                                      ORDER BY br.Record_ID DESC
                                                      LIMIT 5";
                                        $res_request = mysqli_query($conn, $q_request);

                                        if (mysqli_num_rows($res_request) > 0) {
                                            while ($row = mysqli_fetch_assoc($res_request)) {
                                                echo "<tr>
                                                        <td>" . date('d-m-Y', strtotime($row['borrow_date'])) . "</td>
                                                        <td>" . (!empty($row['return_date']) ? date('d-m-Y', strtotime($row['return_date'])) : 'N/A') . "</td>
                                                        <td>" . htmlspecialchars($row['comp_name']) . "</td>
                                                        <td>" . $row['quantity_borrowed'] . "</td>
                                                      </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4' style='text-align:center; color:#888;'>No active requests.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>

                                <div class="table-footer">
                                    <span>Showing latest 5 records only</span>
                                    <a href="borrowing_status.php?status=Pending" class="btn-view-all">View All</a>
                                </div>
                            </div>
                        </div>

                        <!-- BORROWING LIST TABLE -->
                        <div class="table-container">
                            <div class="table-header">
                                <h3>
                                    Borrowing List 
                                    <span style="font-size: 11px; font-weight:600; color:#555;">
                                        (Currently Borrowed)
                                    </span>
                                </h3>

                                <button class="btn-open-close" onclick="toggleTable('onloan-table', this)">
                                    ⌄ Open
                                </button>
                            </div>

                            <div class="table-body" id="onloan-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Borrow Date</th>
                                            <th>Return Date</th>
                                            <th>Components</th>
                                            <th>Quantity</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php
                                        $q_list = "SELECT br.borrow_date, br.return_date, c.name AS comp_name, bd.quantity_borrowed 
                                                   FROM borrowing_record br
                                                   JOIN borrowing_detail bd ON br.Record_ID = bd.Record_ID
                                                   JOIN COMPONENT c ON bd.Component_ID = c.Component_ID
                                                   WHERE br.Student_ID = '$student_id' AND br.status IN ('Approved', 'Pending Return')
                                                   ORDER BY br.Record_ID DESC
                                                   LIMIT 5";
                                        $res_list = mysqli_query($conn, $q_list);

                                        if (mysqli_num_rows($res_list) > 0) {
                                            while ($row = mysqli_fetch_assoc($res_list)) {
                                                echo "<tr>
                                                        <td>" . date('d-m-Y', strtotime($row['borrow_date'])) . "</td>
                                                        <td>" . (!empty($row['return_date']) ? date('d-m-Y', strtotime($row['return_date'])) : 'N/A') . "</td>
                                                        <td>" . htmlspecialchars($row['comp_name']) . "</td>
                                                        <td>" . $row['quantity_borrowed'] . "</td>
                                                      </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4' style='text-align:center; color:#888;'>No items currently on loan.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>

                                <div class="table-footer">
                                    <span>Showing latest 5 records only</span>
                                    <a href="borrowing_status.php?status=Approved" class="btn-view-all">View All</a>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="stats-column">
                        <div class="stat-card">
                            <div class="stat-title">Borrow<br>Request</div>
                            <div class="stat-number"><?php echo $count_pending; ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-title">On<br>Loan</div>
                            <div class="stat-number"><?php echo $count_onloan; ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-title">Loan<br>Record</div>
                            <div class="stat-number"><?php echo $count_total_rec; ?></div>
                        </div>
                    </div>

                </div>
            </div>

        </section>

        <!-- LOAN HISTORY -->
        <section class="profile-bottom-box">
            <div class="table-container">
                <div class="table-header history-header">
                    <h3>
                        Loan History 
                        <span style="font-size: 11px; font-weight:600; color:#555;">
                            (Return Records)
                        </span>
                    </h3>

                    <button class="btn-open-close" onclick="toggleTable('history-table', this)">
                        ⌄ Open
                    </button>
                </div>

                <div class="table-body" id="history-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Borrow Date</th>
                                <th>Return Date</th>
                                <th>Components</th>
                                <th>Quantity</th>
                                <th>Final Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $q_history = "SELECT br.borrow_date, br.return_date, br.status, c.name AS comp_name, bd.quantity_borrowed 
                                          FROM borrowing_record br
                                          JOIN borrowing_detail bd ON br.Record_ID = bd.Record_ID
                                          JOIN COMPONENT c ON bd.Component_ID = c.Component_ID
                                          WHERE br.Student_ID = '$student_id' AND br.status IN ('Returned', 'Rejected')
                                          ORDER BY br.Record_ID DESC
                                          LIMIT 5";
                            $res_history = mysqli_query($conn, $q_history);

                            if (mysqli_num_rows($res_history) > 0) {
                                while ($row = mysqli_fetch_assoc($res_history)) {
                                    $status_color = ($row['status'] == 'Returned') ? '#2ecc71' : '#ff4d4d';

                                    echo "<tr>
                                            <td>" . date('d-m-Y', strtotime($row['borrow_date'])) . "</td>
                                            <td>" . (!empty($row['return_date']) ? date('d-m-Y', strtotime($row['return_date'])) : 'N/A') . "</td>
                                            <td>" . htmlspecialchars($row['comp_name']) . "</td>
                                            <td>" . $row['quantity_borrowed'] . "</td>
                                            <td><strong style='color: " . $status_color . ";'>" . $row['status'] . "</strong></td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' style='text-align:center; color:#888; padding: 30px;'>No return history.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>

                    <div class="table-footer">
                        <span>Showing latest 5 records only</span>
                        <a href="borrowing_status.php" class="btn-view-all">View All</a>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- EDIT PROFILE MODAL -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>

            <h2 class="modal-title">Edit Profile</h2>

            <form action="profile.php" method="POST">
                
                <div class="form-group">
                    <label>PHONE NUMBER</label>
                    <input type="text" name="phone_num" value="<?php echo htmlspecialchars($user_data['phone_num']); ?>" required>
                </div>

                <div class="form-group">
                    <label>FACULTY</label>
                    <select name="faculty" required>
                        <option value="" disabled <?php echo empty($user_data['faculty']) ? 'selected' : ''; ?>>Select Faculty...</option>
                        <option value="FSKM" <?php echo ($user_data['faculty'] == 'FSKM') ? 'selected' : ''; ?>>FSKM (Sains Komputer dan Matematik)</option>
                        <option value="FSPPP" <?php echo ($user_data['faculty'] == 'FSPPP') ? 'selected' : ''; ?>>FSPPP (Sains Pentadbiran dan Pengajian Polisi)</option>
                        <option value="FPP" <?php echo ($user_data['faculty'] == 'FPP') ? 'selected' : ''; ?>>FPP (Pengurusan dan Perniagaan)</option>
                        <option value="CDCS110" <?php echo ($user_data['faculty'] == 'CDCS110') ? 'selected' : ''; ?>>CDCS110</option>
                    </select>
                </div>

                <button type="submit" name="update_profile" class="btn-submit">
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <script>
        // Edit Profile Modal
        var editModal = document.getElementById("editProfileModal");
        var btnOpen = document.getElementById("openEditModal");
        var spanClose = document.getElementsByClassName("close-modal")[0];

        btnOpen.onclick = function() {
            editModal.classList.add("show");
        }

        spanClose.onclick = function() {
            editModal.classList.remove("show");
        }

        window.onclick = function(event) {
            if (event.target == editModal) {
                editModal.classList.remove("show");
            }
        }

        // Table Open/Close
        function toggleTable(tableId, button) {
            const tableBody = document.getElementById(tableId);

            tableBody.classList.toggle('open');

            if (tableBody.classList.contains('open')) {
                button.innerHTML = '⌃ Close';
            } else {
                button.innerHTML = '⌄ Open';
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php showSweetAlert(); ?>

</body>
</html>