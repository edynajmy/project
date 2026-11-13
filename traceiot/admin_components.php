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


// --- DELETE COMPONENT PROCESS ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = mysqli_real_escape_string($conn, $_GET['id']);

    // Check if component exists
    $check_component = mysqli_query($conn, "SELECT name, image FROM COMPONENT WHERE Component_ID = '$delete_id'");

    if ($check_component && mysqli_num_rows($check_component) > 0) {
        $component_data = mysqli_fetch_assoc($check_component);
        $component_name = $component_data['name'];
        $component_image = $component_data['image'];

        // Do not delete if component is still in active borrowing records
        $check_active = mysqli_query($conn, "
            SELECT bd.detail_id
            FROM borrowing_detail bd
            JOIN borrowing_record br ON bd.Record_ID = br.Record_ID
            WHERE bd.Component_ID = '$delete_id'
            AND br.status IN ('Pending', 'Approved', 'Pending Return')
            LIMIT 1
        ");

        if ($check_active && mysqli_num_rows($check_active) > 0) {
            setSweetAlert('warning', 'Cannot Delete!', 'This component is currently used in an active borrowing request.');
            header("Location: admin_components.php");
            exit();
        }

        $delete_query = "DELETE FROM COMPONENT WHERE Component_ID = '$delete_id'";

        if (mysqli_query($conn, $delete_query)) {
            // Delete image file only if it is not default image
            if (!empty($component_image) && $component_image != 'default.png') {
                $image_path = "uploads/" . $component_image;

                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }

            setSweetAlert('success', 'Deleted!', 'Component ' . $component_name . ' has been deleted successfully.');
            header("Location: admin_components.php");
            exit();
        } else {
            setSweetAlert('error', 'Delete Failed!', mysqli_error($conn));
            header("Location: admin_components.php");
            exit();
        }
    } else {
        setSweetAlert('error', 'Not Found!', 'Component not found.');
        header("Location: admin_components.php");
        exit();
    }
}

// --- ADD COMPONENT PROCESS ---
if (isset($_POST['add_component'])) {
    $name           = mysqli_real_escape_string($conn, $_POST['name']);
    $category       = mysqli_real_escape_string($conn, $_POST['category']);
    $total_quantity = mysqli_real_escape_string($conn, $_POST['total_quantity']);
    $cabinet_num    = mysqli_real_escape_string($conn, $_POST['cabinet_num']);
    
    // --- IMAGE PROCESS ---
    $final_image = "default.png";

    if (isset($_FILES['component_image']) && $_FILES['component_image']['error'] == 0) {

        $target_dir = "uploads/";

        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $image_name = basename($_FILES['component_image']['name']);
        $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

        // Only allow PNG, JPG, JPEG
        $allowed_ext = ['png', 'jpg', 'jpeg'];

        if (!in_array($image_ext, $allowed_ext)) {
            setSweetAlert('error', 'Invalid File Type!', 'Only PNG, JPG, and JPEG files are allowed.');
            header("Location: admin_components.php");
            exit();
        }

        // Make sure uploaded file is really an image
        $check_image = getimagesize($_FILES['component_image']['tmp_name']);

        if ($check_image === false) {
            setSweetAlert('error', 'Invalid Image!', 'The selected file is not a valid image.');
            header("Location: admin_components.php");
            exit();
        }

        // Rename file to avoid duplicate filename problem
        $new_image_name = time() . "_" . uniqid() . "." . $image_ext;
        $target_file = $target_dir . $new_image_name;

        if (move_uploaded_file($_FILES['component_image']['tmp_name'], $target_file)) {
            $final_image = $new_image_name;
        } else {
            setSweetAlert('error', 'Upload Failed!', 'Failed to upload component image.');
            header("Location: admin_components.php");
            exit();
        }
    }

    // Insert into database
    $sql = "INSERT INTO COMPONENT (name, image, category, total_quantity, cabinet_num) 
            VALUES ('$name', '$final_image', '$category', '$total_quantity', '$cabinet_num')";
    
    if (mysqli_query($conn, $sql)) {
        setSweetAlert('success', 'Component Added!', 'Component ' . $name . ' successfully added to the system.');
        header("Location: admin_components.php");
        exit();
    } else {
        setSweetAlert('error', 'Database Error!', mysqli_error($conn));
    }
}

// --- LIVE EDIT COMPONENT PROCESS ---
if (isset($_POST['edit_component'])) {
    $edit_id       = mysqli_real_escape_string($conn, $_POST['edit_id']);
    $edit_quantity = mysqli_real_escape_string($conn, $_POST['edit_quantity']);
    $edit_location = mysqli_real_escape_string($conn, $_POST['edit_location']);

    $update_sql = "UPDATE COMPONENT SET total_quantity = '$edit_quantity', cabinet_num = '$edit_location' WHERE Component_ID = '$edit_id'";
    
    if (mysqli_query($conn, $update_sql)) {
        setSweetAlert('success', 'Updated!', 'Component successfully updated.');
        header("Location: admin_components.php");
        exit();
    } else {
        setSweetAlert('error', 'Update Failed!', mysqli_error($conn));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Components - Tracelot System</title>

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

        /* --- MAIN LAYOUT --- */
        .main-content { 
            max-width: 1300px; 
            margin: 0 auto; 
            padding-top: 130px; 
            padding-left: 40px; 
            padding-right: 40px; 
        }

        /* --- CATEGORY FILTERS --- */
        .filters-container { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            margin-bottom: 40px; 
            flex-wrap: wrap; 
        }

        .filter-btn { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            background-color: #ffffff; 
            padding: 15px 25px; 
            border-radius: 15px; 
            border: none; 
            box-shadow: 0 10px 20px rgba(0,0,0,0.05); 
            cursor: pointer; 
            transition: all 0.3s ease; 
            min-width: 180px; 
        }

        .filter-btn.active { 
            background-color: #9340ff; 
            color: white; 
            box-shadow: 0 10px 25px rgba(147, 64, 255, 0.3); 
        }

        .filter-icon { 
            width: 35px; 
            height: 35px; 
            border-radius: 50%; 
            background-color: transparent; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            border: 1px solid #ccc; 
            font-weight: bold; 
        }

        .filter-btn.active .filter-icon { 
            border-color: rgba(255,255,255,0.5); 
        }

        .filter-text { 
            text-align: left; 
        }

        .filter-text h4 { 
            font-size: 16px; 
            font-weight: 700; 
            margin-bottom: 2px; 
            color: inherit; 
        }

        .filter-text span { 
            font-size: 12px; 
            color: #888; 
            font-weight: 500; 
        }

        .filter-btn.active .filter-text span { 
            color: rgba(255,255,255,0.8); 
        }

        .btn-add-item { 
            background-color: #0d3b66; 
            color: white; 
            margin-left: auto; 
            border: none; 
            padding: 15px 25px; 
            border-radius: 15px; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            cursor: pointer; 
            box-shadow: 0 10px 20px rgba(13, 59, 102, 0.2); 
            transition: all 0.3s ease; 
        }

        .btn-add-item:hover { 
            background-color: #1a5b9e; 
            transform: translateY(-2px); 
        }

        .btn-add-item .filter-icon { 
            border: 1px solid rgba(255,255,255,0.5); 
            font-size: 16px; 
            color: white; 
        }

        /* --- PRODUCT GRID --- */
        .products-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); 
            gap: 30px; 
            margin-bottom: 50px; 
        }

        .product-card { 
            background-color: #ffffff; 
            border-radius: 20px; 
            padding: 30px 25px; 
            border: 1px solid #eaeaea; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.02); 
            display: flex; 
            flex-direction: column; 
            transition: transform 0.2s; 
        }

        .product-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 15px 35px rgba(0,0,0,0.08); 
        }

        .product-image { 
            width: 100%; 
            height: 140px; 
            object-fit: contain; 
            margin-bottom: 25px; 
        }

        .product-title { 
            font-size: 22px; 
            font-weight: 800; 
            color: #000; 
            margin-bottom: 2px; 
            min-height: 55px; 
        }

        .product-category { 
            font-size: 18px; 
            color: #888; 
            font-weight: 400; 
            margin-bottom: 25px; 
        }

        .product-availability { 
            font-size: 14px; 
            color: #666; 
            margin-bottom: 15px; 
            font-weight: 500; 
        }
        
        .admin-controls { 
            margin-top: auto; 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
            align-items: center; 
        }

        .btn-verify { 
            background-color: #a855f7; 
            color: white; 
            border: none; 
            border-radius: 20px; 
            padding: 10px 40px; 
            font-size: 13px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: background 0.3s; 
            width: 100%; 
            text-align: center; 
        }

        .btn-verify:hover { 
            background-color: #9333ea; 
        }


        .btn-delete {
            background-color: #ef4444;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 10px 40px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
            text-align: center;
        }

        .btn-delete:hover {
            background-color: #dc2626;
        }

        /* --- MODAL FORM --- */
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
            max-width: 500px; 
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
            padding: 10px 12px; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            font-size: 14px; 
            outline: none; 
        }

        .form-group input:focus, 
        .form-group select:focus { 
            border-color: #0d3b66; 
        }

        .file-note-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-note-row input[type="file"] {
            flex: 1;
        }

        .file-note {
            font-size: 12px;
            color: #dc2626;
            font-style: italic;
            white-space: nowrap;
        }

        .btn-submit { 
            width: 100%; 
            background-color: #0d3b66; 
            color: white; 
            border: none; 
            padding: 12px; 
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

        .btn-update { 
            background-color: #a855f7; 
        }

        .btn-update:hover { 
            background-color: #9333ea; 
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
                padding-left: 20px;
                padding-right: 20px;
            }

            .file-note-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .file-note {
                white-space: normal;
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
                <a href="admin_home.php" class="nav-item">Dashboard</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_components.php" class="nav-item active">Components</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_damage_report.php" class="nav-item">Damaged Items</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_borrowing_list.php" class="nav-item">Booking Record</a>
            </nav>

            <div class="nav-right" style="display: flex; align-items: center; gap: 20px; flex-wrap: nowrap; white-space: nowrap;">
                <a href="admin_profile.php" class="profile-section" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: #333; font-weight: 600;">
                    <div class="profile-icon">
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
        
        <div class="filters-container">
            <?php 
            $current_cat = isset($_GET['category']) ? $_GET['category'] : ''; 
            ?>
            
            <button class="filter-btn <?php echo ($current_cat == '') ? 'active' : ''; ?>" onclick="window.location.href='admin_components.php'">
                <div class="filter-icon">🛍️</div>
                <div class="filter-text">
                    <h4>All Components</h4>
                    <span>List</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'Sensor') ? 'active' : ''; ?>" onclick="window.location.href='admin_components.php?category=Sensor'">
                <div class="filter-icon">📁</div>
                <div class="filter-text">
                    <h4>Sensor</h4>
                    <span>Category</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'SoC') ? 'active' : ''; ?>" onclick="window.location.href='admin_components.php?category=SoC'">
                <div class="filter-icon">💻</div>
                <div class="filter-text">
                    <h4>SoC (System on Chip)</h4>
                    <span>Category</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'LED') ? 'active' : ''; ?>" onclick="window.location.href='admin_components.php?category=LED'">
                <div class="filter-icon">💡</div>
                <div class="filter-text">
                    <h4>LED</h4>
                    <span>Category</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'Motor') ? 'active' : ''; ?>" onclick="window.location.href='admin_components.php?category=Motor'">
                <div class="filter-icon">⚙️</div>
                <div class="filter-text">
                    <h4>Motor / Actuator</h4>
                    <span>Category</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'Others') ? 'active' : ''; ?>" onclick="window.location.href='admin_components.php?category=Others'">
                <div class="filter-icon">📦</div>
                <div class="filter-text">
                    <h4>Others</h4>
                    <span>Category</span>
                </div>
            </button>
            
            <button id="openModalBtn" class="btn-add-item">
                <div class="filter-icon">+</div>
                <div class="filter-text">
                    <h4 style="color: white;">Add Component</h4>
                    <span style="color: rgba(255,255,255,0.8);">Register New</span>
                </div>
            </button>
        </div>

        <div class="products-grid">
            <?php
            $query_komponen = "SELECT * FROM COMPONENT";
            
            if (!empty($current_cat)) {
                $safe_cat = mysqli_real_escape_string($conn, $current_cat);
                $query_komponen .= " WHERE category = '$safe_cat'";
            }
            
            $result_komponen = mysqli_query($conn, $query_komponen);

            if ($result_komponen && mysqli_num_rows($result_komponen) > 0) {
                while ($row = mysqli_fetch_assoc($result_komponen)) {
                    $gambar = !empty($row['image']) ? $row['image'] : 'default.png';
                    ?>
                    
                    <div class="product-card">
                        <img 
                            src="uploads/<?php echo htmlspecialchars($gambar); ?>" 
                            alt="<?php echo htmlspecialchars($row['name']); ?>" 
                            class="product-image" 
                            onerror="this.src='https://via.placeholder.com/200x150.png?text=No+Image';"
                        >
                        
                        <h3 class="product-title">
                            <?php echo htmlspecialchars($row['name']); ?>
                        </h3>

                        <span class="product-category">
                            <?php echo htmlspecialchars($row['category']); ?>
                        </span>
                        
                        <p class="product-availability">
                            Total Quantity: <strong><?php echo $row['total_quantity']; ?></strong>
                        </p>

                        <p class="product-availability" style="font-size: 12px; margin-top:-10px; color: #999;">
                            Location: <?php echo htmlspecialchars($row['cabinet_num']); ?>
                        </p>
                        
                        <div class="admin-controls">
                            <button 
                                type="button" 
                                class="btn-verify" 
                                onclick="openEditModal(
                                    '<?php echo $row['Component_ID']; ?>', 
                                    '<?php echo addslashes(htmlspecialchars($row['name'])); ?>', 
                                    '<?php echo $row['total_quantity']; ?>', 
                                    '<?php echo addslashes(htmlspecialchars($row['cabinet_num'])); ?>'
                                )"
                            >
                                Edit Item
                            </button>

                            <button 
                                type="button" 
                                class="btn-delete" 
                                onclick="confirmDeleteComponent(
                                    '<?php echo $row['Component_ID']; ?>',
                                    '<?php echo addslashes(htmlspecialchars($row['name'])); ?>'
                                )"
                            >
                                Delete Item
                            </button>
                        </div>
                    </div>

                    <?php
                }
            } else {
                echo "<div style='grid-column: 1 / -1; text-align: center; padding: 40px; color: #888;'>
                        <h3>No components found.</h3>
                        <p>Please click the '+ Add Component' button to start adding inventory.</p>
                      </div>";
            }
            ?>
        </div>
    </main>

    <!-- ADD COMPONENT MODAL -->
    <div id="addComponentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal close-add-modal">&times;</span>

            <h2 class="modal-title">Register New Component</h2>

            <form action="admin_components.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>COMPONENT IMAGE</label>

                    <div class="file-note-row">
                        <input 
                            type="file" 
                            name="component_image" 
                            accept=".png,.jpg,.jpeg" 
                            style="padding: 5px;"
                        >

                        <small class="file-note">
                            * Only JPG, JPEG, and PNG files are allowed.
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label>COMPONENT NAME</label>
                    <input type="text" name="name" placeholder="E.g.: NodeMCU ESP8266" required>
                </div>

                <div class="form-group">
                    <label>CATEGORY</label>
                    <select name="category" required>
                        <option value="" disabled selected>Select Category...</option>
                        <option value="SoC">SoC (System on Chip)</option>
                        <option value="Sensor">Sensor</option>
                        <option value="LED">LED</option>
                        <option value="Motor">Motor / Actuator</option>
                        <option value="Others">Others</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>TOTAL QUANTITY</label>
                    <input type="number" name="total_quantity" placeholder="E.g.: 15" required>
                </div>

                <div class="form-group">
                    <label>CABINET NUMBER (LOCATION)</label>
                    <input type="text" name="cabinet_num" placeholder="E.g.: Cabinet A-02" required>
                </div>

                <button type="submit" name="add_component" class="btn-submit">
                    Save to Inventory
                </button>
            </form>
        </div>
    </div>

    <!-- EDIT COMPONENT MODAL -->
    <div id="editComponentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal close-edit-modal">&times;</span>

            <h2 class="modal-title">Edit Item Details</h2>

            <form action="admin_components.php" method="POST">
                
                <input type="hidden" id="edit_id" name="edit_id">

                <div class="form-group">
                    <label>COMPONENT NAME</label>
                    <input type="text" id="edit_name" readonly style="background-color: #f5f5f5; color: #777; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label>TOTAL QUANTITY</label>
                    <input type="number" id="edit_quantity" name="edit_quantity" required>
                </div>

                <div class="form-group">
                    <label>CABINET NUMBER (LOCATION)</label>
                    <input type="text" id="edit_location" name="edit_location" required>
                </div>

                <button type="submit" name="edit_component" class="btn-submit btn-update">
                    Update Component
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        var addModal  = document.getElementById("addComponentModal");
        var editModal = document.getElementById("editComponentModal");
        
        var openAddBtn = document.getElementById("openModalBtn");
        
        var closeAddSpan  = document.querySelector(".close-add-modal");
        var closeEditSpan = document.querySelector(".close-edit-modal");

        openAddBtn.onclick = function() {
            addModal.classList.add("show");
        }

        function openEditModal(id, name, qty, location) {
            document.getElementById("edit_id").value = id;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_quantity").value = qty;
            document.getElementById("edit_location").value = location;
            
            editModal.classList.add("show");
        }

        closeAddSpan.onclick = function() {
            addModal.classList.remove("show");
        }

        closeEditSpan.onclick = function() {
            editModal.classList.remove("show");
        }


        function confirmDeleteComponent(id, name) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Component?',
                text: 'Are you sure you want to delete ' + name + '? This action cannot be undone.',
                showCancelButton: true,
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#ef4444'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'admin_components.php?action=delete&id=' + id;
                }
            });
        }

        window.onclick = function(event) {
            if (event.target == addModal) {
                addModal.classList.remove("show");
            }

            if (event.target == editModal) {
                editModal.classList.remove("show");
            }
        }
    </script>

    <?php showSweetAlert(); ?>

</body>
</html>