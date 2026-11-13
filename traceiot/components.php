<?php
session_start();
include 'connection.php';
include 'alerts.php';

if (!isset($_SESSION['student_id'])) {
    setSweetAlert('error', 'Please Sign In', 'Please sign in as a student to access this page.');
    header("Location: signin.php");
    exit(); 
}

// Check if student is not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: signin.php");
    exit();
}

$user_id = $_SESSION['student_id'];

// --- 1. CART SYSTEM ---
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// If student clicks "ADD TO CART"
if (isset($_POST['add_multiple_to_cart'])) {
    $items_added = 0;
    $stock_error = "";

    if (isset($_POST['borrow_qty']) && is_array($_POST['borrow_qty'])) {
        foreach ($_POST['borrow_qty'] as $comp_id => $qty) {
            $comp_id = mysqli_real_escape_string($conn, $comp_id);
            $qty = (int)$qty;

            if ($qty > 0) {
                // Check available stock after deducting all Pending requests
                $check_stock_query = "
                    SELECT 
                        c.name,
                        c.total_quantity,
                        COALESCE(SUM(
                            CASE 
                                WHEN br.status = 'Pending' THEN bd.quantity_borrowed
                                ELSE 0
                            END
                        ), 0) AS pending_quantity,
                        (
                            c.total_quantity - COALESCE(SUM(
                                CASE 
                                    WHEN br.status = 'Pending' THEN bd.quantity_borrowed
                                    ELSE 0
                                END
                            ), 0)
                        ) AS available_quantity
                    FROM COMPONENT c
                    LEFT JOIN borrowing_detail bd ON c.Component_ID = bd.Component_ID
                    LEFT JOIN borrowing_record br ON bd.Record_ID = br.Record_ID
                    WHERE c.Component_ID = '$comp_id'
                    GROUP BY c.Component_ID
                ";

                $check_stock_result = mysqli_query($conn, $check_stock_query);
                $stock_data = mysqli_fetch_assoc($check_stock_result);

                if (!$stock_data) {
                    $stock_error = "Component not found.";
                    break;
                }

                $available_quantity = (int)$stock_data['available_quantity'];
                $current_cart_qty = isset($_SESSION['cart'][$comp_id]) ? (int)$_SESSION['cart'][$comp_id] : 0;
                $new_total_cart_qty = $current_cart_qty + $qty;

                if ($new_total_cart_qty > $available_quantity) {
                    $stock_error = $stock_data['name'] . " only has " . $available_quantity . " units available after pending requests.";
                    break;
                }

                $_SESSION['cart'][$comp_id] = $new_total_cart_qty;
                $items_added++;
            }
        }
    }

    if ($stock_error != "") {
        setSweetAlert('warning', 'Stock Limit', $stock_error);
        header("Location: components.php");
        exit();
    }

    if ($items_added > 0) {
        setSweetAlert('success', 'Added!', 'Successfully added to cart!');
        header("Location: components.php");
        exit();
    } else {
        setSweetAlert('warning', 'Warning!', 'Please increase the quantity of at least one item before adding to cart.');
        header("Location: components.php");
        exit();
    }
}

$total_cart_items = count($_SESSION['cart']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Equipment - Tracelot System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f6f8fb 0%, #e9edf3 100%); min-height: 100vh; overflow-x: hidden; padding-bottom: 50px; }

        /* --- NAVIGATION BAR --- */
        .navbar-container { width: 100%; display: flex; justify-content: center; position: fixed; top: 0; z-index: 100; }
        .navbar { width: 98%; max-width: 1400px; background-color: #ffffff; height: 80px; border-radius: 0 0 30px 30px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); display: flex; align-items: center; justify-content: space-between; padding: 0 40px; }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .nav-left img { height: 45px; object-fit: contain; }
        .nav-divider { width: 1px; height: 40px; background-color: #e0e0e0; }
        .nav-center { display: flex; align-items: center; gap: 30px; }
        .nav-item { text-decoration: none; color: #333; font-weight: 600; font-size: 14px; position: relative; }
        .nav-item.active { color: #0d3b66; }
        .nav-item.active::after { content: ''; position: absolute; bottom: -28px; left: 50%; transform: translateX(-50%); width: 40px; height: 4px; background-color: #0d3b66; border-radius: 4px 4px 0 0; }
        .nav-text-divider { color: #ccc; font-weight: 300; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        
        .profile-section { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; color: inherit; }
        .profile-icon { width: 35px; height: 35px; background-color: #000; color: #fff; border-radius: 50%; display: flex; justify-content: center; align-items: center; }

        /* --- MAIN LAYOUT --- */
        .main-content { max-width: 1300px; margin: 0 auto; padding-top: 130px; padding-left: 40px; padding-right: 40px; }

        /* --- CATEGORY FILTERS --- */
        .filters-container { display: flex; align-items: center; gap: 20px; margin-bottom: 40px; flex-wrap: wrap; }
        .filter-btn { display: flex; align-items: center; gap: 15px; background-color: #ffffff; padding: 15px 25px; border-radius: 15px; border: none; box-shadow: 0 10px 20px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.3s ease; min-width: 180px; }
        .filter-btn.active { background-color: #9340ff; color: white; box-shadow: 0 10px 25px rgba(147, 64, 255, 0.3); }
        .filter-icon { width: 35px; height: 35px; border-radius: 50%; display: flex; justify-content: center; align-items: center; border: 1px solid #ccc; font-weight: bold; }
        .filter-btn.active .filter-icon { border-color: rgba(255,255,255,0.5); }
        .filter-text { text-align: left; }
        .filter-text h4 { font-size: 16px; font-weight: 700; margin-bottom: 2px; color: inherit; }
        .filter-text span { font-size: 12px; color: #888; font-weight: 500; }
        .filter-btn.active .filter-text span { color: rgba(255,255,255,0.8); }

        /* --- CART BUTTON --- */
        .btn-cart { background-color: #fff000; color: white; margin-left: auto; border: none; padding: 15px 25px; border-radius: 15px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 20px rgba(255, 152, 0, 0.2); transition: transform 0.2s; }
        .btn-cart:hover { transform: translateY(-3px); }

        /* --- PRODUCT GRID --- */
        .products-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .product-card { background-color: #ffffff; border-radius: 20px; padding: 30px 25px; border: 1px solid #eaeaea; box-shadow: 0 10px 30px rgba(0,0,0,0.02); display: flex; flex-direction: column; transition: transform 0.2s; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.08); }
        .product-image { width: 100%; height: 140px; object-fit: contain; margin-bottom: 25px; }
        .product-title { font-size: 22px; font-weight: 800; color: #000; margin-bottom: 2px; min-height: 55px; }
        .product-category { font-size: 18px; color: #888; font-weight: 400; margin-bottom: 20px; }
        .product-availability { font-size: 14px; color: #666; margin-bottom: 15px; font-weight: 500; }
        
        .student-controls { margin-top: auto; display: flex; flex-direction: column; gap: 15px; }
        .qty-controls { display: flex; gap: 10px; width: 100%; align-items: center; justify-content: space-between; background: #f9f9f9; padding: 5px; border-radius: 30px; border: 2px solid #eee; }
        .btn-qty { width: 40px; height: 40px; border: none; border-radius: 50%; font-size: 20px; font-weight: 600; color: white; cursor: pointer; transition: opacity 0.2s; display: flex; justify-content: center; align-items: center; }
        .btn-qty:hover { opacity: 0.8; }
        .btn-minus { background-color: #ff4d4d; } 
        .btn-plus { background-color: #68a568; } 
        .qty-input { width: 50px; text-align: center; font-size: 18px; font-weight: 700; border: none; background: transparent; outline: none; }
        
        /* --- BIG SUBMIT BUTTON --- */
        .btn-submit-all { background-color: #0d3b66; color: white; border: none; border-radius: 15px; padding: 20px; font-size: 18px; font-weight: 800; cursor: pointer; transition: background 0.3s; width: 100%; box-shadow: 0 10px 30px rgba(13, 59, 102, 0.2); }
        .btn-submit-all:hover { background-color: #1a5b9e; transform: translateY(-2px); }
        .submit-container { width: 100%; max-width: 600px; margin: 0 auto 50px auto; display: block; }
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
                <a href="home.php" class="nav-item">Home</a>
                <span class="nav-text-divider">|</span>
                <a href="components.php" class="nav-item active">Components</a>
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
        
        <div class="filters-container">
            <?php 
            $current_cat = isset($_GET['category']) ? $_GET['category'] : ''; 
            ?>
            
            <button class="filter-btn <?php echo ($current_cat == '') ? 'active' : ''; ?>" onclick="window.location.href='components.php'">
                <div class="filter-icon">🛍️</div>
                <div class="filter-text">
                    <h4>All Items</h4>
                    <span>Available</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'Sensor') ? 'active' : ''; ?>" onclick="window.location.href='components.php?category=Sensor'">
                <div class="filter-icon">📁</div>
                <div class="filter-text">
                    <h4>Sensor</h4>
                    <span>Category</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'SoC') ? 'active' : ''; ?>" onclick="window.location.href='components.php?category=SoC'">
                <div class="filter-icon">💻</div>
                <div class="filter-text">
                    <h4>SoC (System on Chip)</h4>
                    <span>Category</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'LED') ? 'active' : ''; ?>" onclick="window.location.href='components.php?category=LED'">
                <div class="filter-icon">💡</div>
                <div class="filter-text">
                    <h4>LED</h4>
                    <span>Category</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'Motor') ? 'active' : ''; ?>" onclick="window.location.href='components.php?category=Motor'">
                <div class="filter-icon">⚙️</div>
                <div class="filter-text">
                    <h4>Motor / Actuator</h4>
                    <span>Category</span>
                </div>
            </button>

            <button class="filter-btn <?php echo ($current_cat == 'Others') ? 'active' : ''; ?>" onclick="window.location.href='components.php?category=Others'">
                <div class="filter-icon">📦</div>
                <div class="filter-text">
                    <h4>Others</h4>
                    <span>Category</span>
                </div>
            </button>

            <a href="borrowing.php" style="text-decoration: none; margin-left: auto;">
                <button class="btn-cart">
                    🛒 Cart (<?php echo $total_cart_items; ?>)
                </button>
            </a>
        </div>

        <form action="components.php?<?php echo $_SERVER['QUERY_STRING']; ?>" method="POST">
            <div class="products-grid">
                <?php
                $search_keyword = isset($_GET['search']) ? $_GET['search'] : '';

                // Available stock = real stock - all Pending request quantities
                $query_komponen = "
                    SELECT 
                        c.*,
                        COALESCE(SUM(
                            CASE 
                                WHEN br.status = 'Pending' THEN bd.quantity_borrowed
                                ELSE 0
                            END
                        ), 0) AS pending_quantity,
                        (
                            c.total_quantity - COALESCE(SUM(
                                CASE 
                                    WHEN br.status = 'Pending' THEN bd.quantity_borrowed
                                    ELSE 0
                                END
                            ), 0)
                        ) AS available_quantity
                    FROM COMPONENT c
                    LEFT JOIN borrowing_detail bd ON c.Component_ID = bd.Component_ID
                    LEFT JOIN borrowing_record br ON bd.Record_ID = br.Record_ID
                    WHERE CAST(c.total_quantity AS UNSIGNED) > 0
                ";

                if (!empty($current_cat)) {
                    $safe_cat = mysqli_real_escape_string($conn, $current_cat);
                    $query_komponen .= " AND c.category = '$safe_cat'";
                }

                if (!empty($search_keyword)) {
                    $safe_search = mysqli_real_escape_string($conn, $search_keyword);
                    $query_komponen .= " AND c.name LIKE '%$safe_search%'";
                }

                $query_komponen .= "
                    GROUP BY c.Component_ID
                    HAVING available_quantity > 0
                    ORDER BY c.name ASC
                ";

                $result_komponen = mysqli_query($conn, $query_komponen);

                if (mysqli_num_rows($result_komponen) > 0) {
                    $has_items = true;
                    while ($row = mysqli_fetch_assoc($result_komponen)) {
                        $comp_id = $row['Component_ID'];
                        $max_qty = (int)$row['available_quantity'];
                        $pending_qty = (int)$row['pending_quantity'];
                        $gambar = !empty($row['image']) ? $row['image'] : 'default.png';
                        ?>
                        <div class="product-card">
                            <img src="uploads/<?php echo $gambar; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="product-image" onerror="this.src='https://via.placeholder.com/200x150.png?text=No+Image';">
                            <h3 class="product-title"><?php echo htmlspecialchars($row['name']); ?></h3>
                            <span class="product-category"><?php echo htmlspecialchars($row['category']); ?></span>
                            <p class="product-availability">
                                Available Stock: <strong><?php echo $max_qty; ?> units</strong>
                            </p>

                            <?php if ($pending_qty > 0) { ?>
                                <p class="product-availability" style="font-size: 12px; margin-top:-10px; color: #f97316;">
                                    <?php echo $pending_qty; ?> units currently reserved by pending requests
                                </p>
                            <?php } ?>
                            <p class="product-availability" style="font-size: 12px; margin-top:-10px; color: #999;">
                                Location: <?php echo htmlspecialchars($row['cabinet_num']); ?>
                            </p>
                            <div class="student-controls">
                                <div class="qty-controls">
                                    <button type="button" class="btn-qty btn-minus" onclick="updateQty('input_<?php echo $comp_id; ?>', -1, <?php echo $max_qty; ?>)">−</button>
                                    <input type="number" id="input_<?php echo $comp_id; ?>" name="borrow_qty[<?php echo $comp_id; ?>]" class="qty-input" value="0" min="0" max="<?php echo $max_qty; ?>" readonly>
                                    <button type="button" class="btn-qty btn-plus" onclick="updateQty('input_<?php echo $comp_id; ?>', 1, <?php echo $max_qty; ?>)">+</button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    $has_items = false;
                    echo "<div style='grid-column: 1 / -1; text-align: center; padding: 40px; color: #888;'><h3>No components available.</h3></div>";
                }
                ?>
            </div>

            <?php if(isset($has_items) && $has_items == true) { ?>
                <div class="submit-container">
                    <button type="submit" name="add_multiple_to_cart" class="btn-submit-all">➕ ADD TO CART</button>
                </div>
            <?php } ?>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function updateQty(inputId, change, maxQty) {
        var inputElement = document.getElementById(inputId);
        var currentVal = parseInt(inputElement.value);
        var newVal = currentVal + change;

        if (newVal >= 0 && newVal <= maxQty) {
            inputElement.value = newVal;
        } else if (newVal > maxQty) {
            Swal.fire({
                icon: 'warning',
                title: 'Stock Limit',
                text: 'Sorry, quantity exceeds available stock (' + maxQty + ' units).',
                confirmButtonText: 'OK'
            });
        }
    }
</script>

    <?php showSweetAlert(); ?>
</body>
</html>