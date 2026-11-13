<?php
session_start();

// SECURITY CHECK: Prevent unauthorized access (Students or non-logged users)
if (!isset($_SESSION['admin_ID'])) {
    // Force redirect to sign in page with an alert
    echo "<script>alert('Access Denied! Administrators only.'); window.location.href='signin.php';</script>";
    exit(); // Stop the rest of the page from loading
}

include 'connection.php'; 

// Fetch the logged-in admin's ID securely
$admin_ID = $_SESSION['admin_ID'];

// --- FETCH STATISTICAL DATA FROM DATABASE (LIVE DATA) ---

// 1. Count New Requests
$query_pending = mysqli_query($conn, "SELECT COUNT(*) as pending_count FROM borrowing_record WHERE status = 'Pending'");
$pending_count = mysqli_fetch_assoc($query_pending)['pending_count'];

// 2. Count Return Requests
$query_return = mysqli_query($conn, "SELECT COUNT(*) as return_count FROM borrowing_record WHERE status = 'Pending Return'");
$return_count = mysqli_fetch_assoc($query_return)['return_count'];

// 3. Count Active Loans (Students currently borrowing items)
$query_active = mysqli_query($conn, "SELECT COUNT(*) as active_count FROM borrowing_record WHERE status = 'Approved'");
$active_borrow_count = mysqli_fetch_assoc($query_active)['active_count'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Tracelot System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        body {
            background: linear-gradient(135deg, #f6f8fb 0%, #e9edf3 100%);
            min-height: 100vh;
            overflow-x: hidden;
            padding-bottom: 50px;
        }

        /* --- NAVIGATION BAR --- */
        .navbar-container { width: 100%; display: flex; justify-content: center; position: fixed; top: 0; z-index: 100; }
        .navbar { width: 98%; max-width: 1400px; background-color: #ffffff; height: 80px; border-radius: 0 0 30px 30px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); display: flex; align-items: center; justify-content: space-between; padding: 0 40px; }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .nav-left img { height: 45px; object-fit: contain; }
        .nav-divider { width: 1px; height: 40px; background-color: #e0e0e0; }
        
        .nav-center { display: flex; align-items: center; gap: 30px; }
        .nav-item { text-decoration: none; color: #333; font-weight: 600; font-size: 14px; position: relative; display: flex; align-items: center; gap: 5px; cursor: pointer; }
        .nav-item.active { color: #0d3b66; }
        .nav-item.active::after { content: ''; position: absolute; bottom: -28px; left: 50%; transform: translateX(-50%); width: 40px; height: 4px; background-color: #0d3b66; border-radius: 4px 4px 0 0; }
        .nav-text-divider { color: #ccc; font-weight: 300; }
        
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .profile-section { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 14px; cursor: default; text-decoration: none; color: inherit; }
        .profile-icon { width: 35px; height: 35px; background-color: #000; color: #fff; border-radius: 50%; display: flex; justify-content: center; align-items: center; }

        /* --- DASHBOARD CONTENT --- */
        .main-content { max-width: 1300px; margin: 0 auto; padding-top: 150px; padding-left: 40px; padding-right: 40px; }
        
        .page-header { margin-bottom: 40px; }
        .page-header h1 { font-family: 'Playfair Display', serif; font-size: 42px; color: #143e70; margin-bottom: 10px; }
        .page-header h1 span { color: #9340ff; }
        .page-header p { font-size: 16px; color: #555; }

        /* --- STAT CARDS (UPDATED FOR CLICKABLE LINKS) --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin-bottom: 40px; }
        
        .stat-card { 
            background-color: #ffffff; 
            padding: 30px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03); 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            transition: transform 0.3s, box-shadow 0.3s; 
            border: 1px solid #eaeaea; 
            text-decoration: none; /* Remove link underline */
            color: inherit; /* Keep text color black */
            cursor: pointer;
        }
        
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 15px 35px rgba(0,0,0,0.06); 
        }
        
        .stat-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; justify-content: center; align-items: center; font-size: 28px; }
        
        /* New Icon Colors */
        .icon-purple { background: rgba(147, 64, 255, 0.1); color: #9340ff; }
        .icon-orange { background: rgba(255, 152, 0, 0.1); color: #ff9800; } 
        .icon-red { background: rgba(255, 77, 77, 0.1); color: #ff4d4d; }
        
        .stat-info h3 { font-size: 36px; font-weight: 800; color: #000; margin-bottom: 5px; }
        .stat-info span { font-size: 14px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        /* --- BOTTOM PANELS --- */
        .dashboard-panels { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .panel { background-color: #ffffff; padding: 35px 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03); border: 1px solid #eaeaea; }
        .panel-title { font-size: 20px; font-weight: 800; color: #143e70; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Table Styling */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 10px; font-size: 14px; color: #888; border-bottom: 2px solid #f0f0f0; }
        td { padding: 18px 10px; border-bottom: 1px solid #f0f0f0; font-size: 15px; font-weight: 500; color: #333; }
        .qty-badge { background-color: #ffe6e6; color: #e60000; padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 13px; }

        /* Action Buttons */
        .action-list { display: flex; flex-direction: column; gap: 15px; }
        .btn-action { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 18px; border-radius: 15px; text-decoration: none; color: #333; font-weight: 700; display: flex; align-items: center; gap: 15px; transition: 0.3s; }
        .btn-action:hover { background-color: #143e70; color: white; border-color: #143e70; }
        .btn-action .emoji { font-size: 22px; }

        @media print {
            .navbar-container,
            .btn-action,
            .action-list,
            .nav-right {
                display: none !important;
            }

            body {
                background: #ffffff !important;
                padding: 0;
            }

            .main-content {
                padding-top: 20px !important;
                padding-left: 20px !important;
                padding-right: 20px !important;
                max-width: 100%;
            }

            .page-header h1 {
                font-size: 30px;
            }

            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }

            .dashboard-panels {
                grid-template-columns: 1fr;
            }

            .panel,
            .stat-card {
                box-shadow: none !important;
                border: 1px solid #ccc;
            }

            .panel-title {
                color: #000;
            }

            a {
                text-decoration: none !important;
                color: inherit !important;
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
                <img src="traceiotlogo.jpeg" alt="Tracelot System">
            </div>

            <nav class="nav-center">
                <a href="admin_home.php" class="nav-item active">Dashboard</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_components.php" class="nav-item">Components</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_damage_report.php" class="nav-item">Damaged Items</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_borrowing_list.php" class="nav-item">Booking Record</a>
            </nav>

            <div class="nav-right" style="display: flex; align-items: center; gap: 20px; flex-wrap: nowrap; white-space: nowrap;">
                <a href="admin_profile.php" class="profile-section" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: #333; font-weight: 600;">
                    <div class="profile-icon" style="width: 35px; height: 35px; background: #000; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    Admin Profile
                </a>
                
                <div style="width: 1px; height: 25px; background-color: #ddd;"></div> 
                
                <a href="admin_logout.php" style="color: #ff4d4d; font-weight: 700; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 5px;">
                    🚪 Logout
                </a>
            </div>
        </header>
    </div>

    <main class="main-content">
        
        <div class="page-header">
            <h1>Admin <span>Dashboard</span></h1>
            <p>Monitor laboratory inventory and manage student borrowing requests.</p>
        </div>

        <div class="stats-grid">
            
            <a href="admin_borrowing_list.php?status=Pending" class="stat-card">
                <div class="stat-icon icon-purple">📥</div>
                <div class="stat-info">
                    <h3><?php echo $pending_count; ?></h3>
                    <span>New Requests</span>
                </div>
            </a>
            
            <a href="admin_borrowing_list.php?status=Pending Return" class="stat-card">
                <div class="stat-icon icon-orange">🔄</div>
                <div class="stat-info">
                    <h3><?php echo $return_count; ?></h3>
                    <span>Pending Return</span>
                </div>
            </a>

            <a href="admin_borrowing_list.php?status=Approved" class="stat-card">
                <div class="stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">📤</div>
                <div class="stat-info">
                    <h3><?php echo $active_borrow_count; ?></h3>
                    <span>Currently Borrowed</span>
                </div>
            </a>

        </div>

        <div class="dashboard-panels">
            
            <div class="panel">
                <div class="panel-title">
                    Low Stock Alert
                    <span style="font-size: 12px; color: #ff4d4d; background: rgba(255, 77, 77, 0.1); padding: 5px 12px; border-radius: 12px; font-weight: 700;">Please Restock</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Component Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query_table = mysqli_query($conn, "SELECT name, category, total_quantity FROM COMPONENT WHERE CAST(total_quantity AS UNSIGNED) <= 3 ORDER BY CAST(total_quantity AS UNSIGNED) ASC LIMIT 4");
                        
                        if(mysqli_num_rows($query_table) > 0) {
                            while($row = mysqli_fetch_assoc($query_table)) {
                                echo "<tr>";
                                echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                                echo "<td>" . htmlspecialchars($row['category']) . "</td>";
                                echo "<td><span class='qty-badge'>" . $row['total_quantity'] . " Units</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' style='text-align: center; color: #888; padding: 30px;'>All equipment stocks are at sufficient levels.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="panel">
                <div class="panel-title">Quick Actions</div>
                <div class="action-list">
                    <a href="admin_components.php" class="btn-action">
                        <span class="emoji">➕</span> Add New Component
                    </a>
                    <a href="admin_borrowing_list.php" class="btn-action">
                        <span class="emoji">📋</span> Review Requests
                    </a>
                    <a href="admin_damage_report.php" class="btn-action">
                        <span class="emoji">🛠️</span> Damaged Items
                    </a>
                    <a href="javascript:void(0)" class="btn-action" onclick="openReportPopup()">
                        <span class="emoji">🖨️</span> Print Report
                    </a>
                </div>
            </div>

        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function openReportPopup() {
            Swal.fire({
                title: 'Select Report to Print',
                text: 'Choose the report type.',
                icon: 'question',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: 'Inventory Report',
                denyButtonText: 'Borrowing Report',
                cancelButtonText: 'Damaged Items Report',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'admin_print_report.php?type=inventory';
                } else if (result.isDenied) {
                    window.location.href = 'admin_print_report.php?type=borrowing';
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    window.location.href = 'admin_print_report.php?type=damage';
                }
            });
        }
    </script>

</body>
</html>