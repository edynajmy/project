<?php
session_start();
include 'connection.php';
include 'alerts.php';

// SECURITY CHECK: Prevent unauthorized access
if (!isset($_SESSION['admin_ID'])) {
    setSweetAlert('error', 'Access Denied!', 'Administrators only.');
    header("Location: signin.php");
    exit(); 
}

// Check if admin is logged in
$admin_ID = isset($_SESSION['admin_ID']) ? $_SESSION['admin_ID'] : 1;

// Fetch admin data from ADMIN table
$query_admin = "SELECT * FROM ADMIN WHERE admin_ID = '$admin_ID'"; 
$result_admin = mysqli_query($conn, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);

// Database-driven values with fallback
$admin_name_display = !empty($admin_data['name']) ? $admin_data['name'] : 'UiTM Lab Admin';
$admin_ID_display   = isset($admin_data['admin_ID']) ? $admin_data['admin_ID'] : $admin_ID;
$email_display      = isset($admin_data['email']) ? $admin_data['email'] : 'admin@uitm.edu.my';

$position_display  = !empty($admin_data['position']) ? $admin_data['position'] : 'Laboratory Officer';
$location_display  = !empty($admin_data['office_location']) ? $admin_data['office_location'] : 'IoT Laboratory, Block A';
$hours_display     = !empty($admin_data['operating_hours']) ? $admin_data['operating_hours'] : '8:00 AM - 5:00 PM (Mon - Fri)';

// --- LIVE SYSTEM STATISTICS ---
$q_pending = mysqli_query($conn, "SELECT COUNT(*) AS total FROM borrowing_record WHERE status = 'Pending'");
$count_pending = mysqli_fetch_assoc($q_pending)['total'];

$q_onloan = mysqli_query($conn, "SELECT COUNT(*) AS total FROM borrowing_record WHERE status IN ('Approved', 'Pending Return')");
$count_onloan = mysqli_fetch_assoc($q_onloan)['total'];

$q_components = mysqli_query($conn, "SELECT COUNT(*) AS total FROM COMPONENT");
$count_components = mysqli_fetch_assoc($q_components)['total'];

$q_returned = mysqli_query($conn, "SELECT COUNT(*) AS total FROM borrowing_record WHERE status = 'Returned'");
$count_returned = mysqli_fetch_assoc($q_returned)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - TraceIoT System</title>

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
            flex-wrap: nowrap; 
            white-space: nowrap; 
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

        /* --- MAIN LAYOUT --- */
        .main-container { 
            max-width: 1200px; 
            margin: 140px auto 0; 
            padding: 0 40px; 
            display: flex; 
            flex-direction: column; 
            gap: 30px; 
        }

        .profile-wrapper { 
            background-color: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.05); 
            padding: 40px; 
            display: flex; 
            gap: 40px; 
        }

        .profile-left { 
            flex: 1.2; 
            display: flex; 
            flex-direction: column; 
            gap: 25px; 
            border-right: 1px solid #eee; 
            padding-right: 40px; 
        }

        .admin-header { 
            display: flex; 
            gap: 20px; 
            align-items: center; 
        }

        .avatar-circle { 
            width: 90px; 
            height: 90px; 
            background-color: #143e70; 
            border-radius: 50%; 
            overflow: hidden; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-size: 35px; 
        }

        .admin-title h1 { 
            font-size: 24px; 
            font-weight: 900; 
            color: #143e70; 
            margin-bottom: 5px; 
            text-transform: uppercase; 
        }

        .admin-badge { 
            display: inline-block; 
            background-color: #9340ff; 
            color: white; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        .info-box { 
            background: #f8fafc; 
            border: 1px solid #e2e8f0; 
            padding: 25px; 
            border-radius: 12px; 
            margin-top: 10px; 
        }

        .info-row { 
            display: grid; 
            grid-template-columns: 140px 1fr; 
            padding: 15px 0; 
            border-bottom: 1px dashed #e2e8f0; 
            font-size: 14px; 
        }

        .info-row:last-child { 
            border-bottom: none; 
        }

        .info-label { 
            font-weight: 800; 
            color: #1e293b; 
        }

        .info-value { 
            font-weight: 600; 
            color: #475569; 
        }

        .profile-right { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            gap: 20px; 
        }

        .summary-title { 
            font-size: 14px; 
            font-weight: 800; 
            color: #143e70; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            margin-bottom: 5px; 
        }

        .stat-card { 
            background-color: #f1f5f9; 
            border: 1px solid #cbd5e1; 
            border-radius: 15px; 
            padding: 22px 25px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            transition: transform 0.2s, background 0.2s; 
            text-decoration: none;
        }

        .stat-card:hover { 
            transform: translateX(5px); 
            background: #eaf1f8;
        }

        .stat-label { 
            font-weight: 800; 
            color: #1e293b; 
            font-size: 14px; 
        }

        .stat-number { 
            font-size: 26px; 
            font-weight: 900; 
            color: #9340ff; 
        }

        /* --- RETURN HISTORY --- */
        .history-card { 
            background-color: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.05); 
            padding: 35px; 
            border: 1px solid #e2e8f0; 
        }

        .history-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background-color: #94a3b8; 
            padding: 15px 25px; 
            border-radius: 12px; 
            border: 1px solid #475569; 
            cursor: pointer;
        }

        .history-header h3 { 
            font-size: 16px; 
            font-weight: 900; 
            color: #0f172a; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        .btn-open-close {
            background: #ffffff;
            border: 1px solid #334155;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .history-body {
            display: none;
            margin-top: 20px;
        }

        .history-body.open {
            display: block;
        }

        .history-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }

        .history-table th { 
            text-align: left; 
            font-weight: 800; 
            font-size: 13px; 
            color: #1e293b; 
            padding: 12px 15px; 
            border-bottom: 2px solid #cbd5e1; 
            text-transform: uppercase; 
            background-color: #f8fafc; 
        }

        .history-table td { 
            padding: 14px 15px; 
            font-size: 14px; 
            font-weight: 600; 
            color: #475569; 
            border-bottom: 1px dashed #e2e8f0; 
        }

        .history-table tr:hover td { 
            background-color: #f8fafc; 
        }

        .status-pill-returned { 
            background-color: #dcfce7; 
            color: #16803d; 
            padding: 4px 12px; 
            border-radius: 12px; 
            font-size: 12px; 
            font-weight: 800; 
            display: inline-block; 
            text-transform: uppercase; 
            border: 1px solid #bbf7d0; 
        }

        .history-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 18px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }

        .btn-view-all {
            background: #0d3b66;
            color: #ffffff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 800;
        }

        .btn-view-all:hover {
            background: #092b4a;
        }

        @media (max-width: 900px) {
            .main-container {
                padding: 0 20px;
            }

            .profile-wrapper {
                flex-direction: column;
            }

            .profile-left {
                border-right: none;
                padding-right: 0;
                border-bottom: 1px solid #eee;
                padding-bottom: 30px;
            }

            .history-table {
                font-size: 12px;
            }

            .history-header {
                gap: 10px;
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
                <a href="admin_home.php" class="nav-item">Dashboard</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_components.php" class="nav-item">Components</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_damage_report.php" class="nav-item">Damaged Items</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_borrowing_list.php" class="nav-item">Booking Record</a>
            </nav>

            <div class="nav-right">
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

    <main class="main-container">

        <div class="profile-wrapper">
            <section class="profile-left">
                <div class="admin-header">
                    <div class="avatar-circle">👨‍💻</div>

                    <div class="admin-title">
                        <h1><?php echo htmlspecialchars($admin_name_display); ?></h1>
                        <span class="admin-badge">System Overseer</span>
                    </div>
                </div>

                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">ADMIN ID</span>
                        <span class="info-value" style="font-family: monospace; font-size: 15px; font-weight:700; color:#0f172a;">
                            <?php echo htmlspecialchars($admin_ID_display); ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Email Address</span>
                        <span class="info-value" style="color:#0d3b66; font-weight:700;">
                            <?php echo htmlspecialchars($email_display); ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Position</span>
                        <span class="info-value"><?php echo htmlspecialchars($position_display); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Office Location</span>
                        <span class="info-value"><?php echo htmlspecialchars($location_display); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Operating Hours</span>
                        <span class="info-value"><?php echo htmlspecialchars($hours_display); ?></span>
                    </div>
                </div>
            </section>

            <section class="profile-right">
                <div class="summary-title">System Overview Dashboard</div>

                <a href="admin_borrowing_list.php?status=Pending" class="stat-card">
                    <span class="stat-label">📥 Incoming Requests Pending</span>
                    <span class="stat-number" style="color: #ff9800;"><?php echo $count_pending; ?></span>
                </a>

                <a href="admin_borrowing_list.php?status=Approved" class="stat-card">
                    <span class="stat-label">📤 Components Currently On Loan</span>
                    <span class="stat-number" style="color: #2ecc71;"><?php echo $count_onloan; ?></span>
                </a>

                <a href="admin_components.php" class="stat-card">
                    <span class="stat-label">📦 Total Registered Components</span>
                    <span class="stat-number" style="color: #0d3b66;"><?php echo $count_components; ?></span>
                </a>

                <a href="admin_borrowing_list.php?status=Returned" class="stat-card">
                    <span class="stat-label">✅ Completed Return Records</span>
                    <span class="stat-number" style="color: #16803d;"><?php echo $count_returned; ?></span>
                </a>
            </section>
        </div>

        <div class="history-card">
            <div class="history-header" onclick="toggleHistory()">
                <h3>Components Return History</h3>
                <button type="button" class="btn-open-close" id="historyBtn">⌄ Open</button>
            </div>

            <div class="history-body" id="historyBody">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Student</th>
                            <th style="width: 20%;">Return Date</th>
                            <th style="width: 25%;">Components</th>
                            <th style="width: 15%;">Quantity</th>
                            <th style="width: 15%;">Final Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $history_query = "SELECT br.return_date, 
                                                 s.name AS student_name, 
                                                 c.name AS component_name, 
                                                 bd.quantity_borrowed 
                                          FROM borrowing_record br
                                          JOIN student s ON br.Student_ID = s.Student_ID
                                          JOIN borrowing_detail bd ON br.Record_ID = bd.Record_ID
                                          JOIN COMPONENT c ON bd.Component_ID = c.Component_ID
                                          WHERE br.status = 'Returned'
                                          ORDER BY br.return_date DESC, br.Record_ID DESC
                                          LIMIT 5";

                        $history_result = mysqli_query($conn, $history_query);

                        if ($history_result && mysqli_num_rows($history_result) > 0) {
                            while ($row = mysqli_fetch_assoc($history_result)) {
                                $formatted_date = !empty($row['return_date']) ? date('d-m-Y', strtotime($row['return_date'])) : 'N/A';
                                ?>
                                <tr>
                                    <td style="color: #0f172a; font-weight: 700;">
                                        <?php echo htmlspecialchars($row['student_name']); ?>
                                    </td>

                                    <td>
                                        <?php echo $formatted_date; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['component_name']); ?>
                                    </td>

                                    <td style="font-weight: 700;">
                                        <?php echo $row['quantity_borrowed']; ?> Units
                                    </td>

                                    <td>
                                        <span class="status-pill-returned">Returned</span>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align: center; padding: 40px; color: #888; font-weight:600;'>No campus return records found yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <div class="history-footer">
                    <span>Showing latest 5 return records only</span>
                    <a href="admin_borrowing_list.php?status=Returned" class="btn-view-all">View All Return Records</a>
                </div>
            </div>
        </div>

    </main>

    <script>
        function toggleHistory() {
            const historyBody = document.getElementById('historyBody');
            const historyBtn = document.getElementById('historyBtn');

            historyBody.classList.toggle('open');

            if (historyBody.classList.contains('open')) {
                historyBtn.innerHTML = '⌃ Close';
            } else {
                historyBtn.innerHTML = '⌄ Open';
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php showSweetAlert(); ?>

</body>
</html>
