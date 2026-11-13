<?php
session_start();
include 'connection.php';
include 'alerts.php';

if (!isset($_SESSION['student_id'])) {
    setSweetAlert('error', 'Please Sign In', 'Please sign in as a student to access this page.');
    header("Location: signin.php");
    exit(); 
}

// Ensure student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: signin.php");
    exit();
}

$student_id = $_SESSION['student_id'];

$today_date = date('Y-m-d');
$max_return_date = date('Y-m-d', strtotime('+2 months'));

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}


// Get available stock after deducting all Pending requests
function getAvailableStock($conn, $comp_id) {
    $comp_id = mysqli_real_escape_string($conn, $comp_id);

    $query = "
        SELECT 
            c.Component_ID,
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

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }

    return null;
}

// --- QUANTITY CONTROL LOGIC (+, -, REMOVE) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $comp_id = mysqli_real_escape_string($conn, $_GET['id']);
    $action = $_GET['action'];

    if ($action == 'remove') {
        unset($_SESSION['cart'][$comp_id]);
        header("Location: borrowing.php");
        exit();

    } elseif ($action == 'add') {
        $stock_data = getAvailableStock($conn, $comp_id);
        $available_stock = $stock_data ? (int)$stock_data['available_quantity'] : 0;
        $component_name = $stock_data ? $stock_data['name'] : 'This component';

        if (isset($_SESSION['cart'][$comp_id]) && $_SESSION['cart'][$comp_id] < $available_stock) {
            $_SESSION['cart'][$comp_id]++;
            header("Location: borrowing.php");
            exit();
        } else {
            setSweetAlert(
                'warning',
                'Stock Limit',
                $component_name . ' only has ' . $available_stock . ' units available after pending requests.'
            );

            header("Location: borrowing.php");
            exit();
        }

    } elseif ($action == 'minus') {
        if (isset($_SESSION['cart'][$comp_id]) && $_SESSION['cart'][$comp_id] > 1) {
            $_SESSION['cart'][$comp_id]--;
        } else {
            unset($_SESSION['cart'][$comp_id]);
        }

        header("Location: borrowing.php");
        exit();
    }
}

// --- PROCESS SUBMISSION (APPLY) ---
if (isset($_POST['submit_borrow']) && !empty($_SESSION['cart'])) {
    $collection_date = mysqli_real_escape_string($conn, $_POST['collection_date']);
    $return_date     = mysqli_real_escape_string($conn, $_POST['return_date']);

    $reason_choice = isset($_POST['reason_choice']) ? $_POST['reason_choice'] : '';
    $reason_other  = isset($_POST['reason_other']) ? trim($_POST['reason_other']) : '';

    if ($reason_choice == 'Others') {
        $final_reason = $reason_other;
    } else {
        $final_reason = $reason_choice;
    }

    if (empty($final_reason)) {
        setSweetAlert('warning', 'Reason Required', 'Please select a reason for borrowing.');
        header("Location: borrowing.php");
        exit();
    }

    $reason = mysqli_real_escape_string($conn, $final_reason);

    $today_check = date('Y-m-d');
    $max_return_check = date('Y-m-d', strtotime($collection_date . ' +2 months'));

    if ($collection_date < $today_check) {
        setSweetAlert('warning', 'Invalid Collection Date', 'Collection date cannot be earlier than today.');
        header("Location: borrowing.php");
        exit();
    }

    if ($return_date < $collection_date) {
        setSweetAlert('warning', 'Invalid Return Date', 'Return date cannot be earlier than collection date.');
        header("Location: borrowing.php");
        exit();
    }

    if ($return_date > $max_return_check) {
        setSweetAlert('warning', 'Return Date Limit', 'Borrowing duration cannot be more than 2 months from the collection date.');
        header("Location: borrowing.php");
        exit();
    }

    mysqli_begin_transaction($conn);

    try {
        // Check available stock first before creating borrowing record
        foreach ($_SESSION['cart'] as $c_id => $qty) {
            $c_id = mysqli_real_escape_string($conn, $c_id);
            $qty = (int)$qty;

            $stock_data = getAvailableStock($conn, $c_id);

            if (!$stock_data) {
                throw new Exception("Component not found.");
            }

            $available_stock = (int)$stock_data['available_quantity'];
            $component_name = $stock_data['name'];

            if ($qty > $available_stock) {
                throw new Exception($component_name . " only has " . $available_stock . " units available after pending requests.");
            }
        }

        $query_record = "INSERT INTO borrowing_record (Student_ID, borrow_date, return_date, reason, status) 
                         VALUES ('$student_id', '$collection_date', '$return_date', '$reason', 'Pending')";
        mysqli_query($conn, $query_record);
        $new_record_id = mysqli_insert_id($conn);

        foreach ($_SESSION['cart'] as $c_id => $qty) {
            $c_id = mysqli_real_escape_string($conn, $c_id);
            $qty = (int)$qty;

            mysqli_query($conn, "INSERT INTO borrowing_detail (Record_ID, Component_ID, quantity_borrowed) VALUES ('$new_record_id', '$c_id', '$qty')");
        }

        mysqli_commit($conn);
        $_SESSION['cart'] = array(); 

        setSweetAlert('success', 'Submitted!', 'Request submitted successfully.');
        header("Location: borrowing_status.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);

        setSweetAlert('warning', 'Stock Not Available', $e->getMessage());
        header("Location: borrowing.php");
        exit();
    }
}

$total_items = count($_SESSION['cart']);
$cart_has_stock_problem = false;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $check_comp_id => $check_qty) {
        $check_stock_data = getAvailableStock($conn, $check_comp_id);
        $check_available = $check_stock_data ? (int)$check_stock_data['available_quantity'] : 0;

        if ((int)$check_qty > $check_available) {
            $cart_has_stock_problem = true;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Tracelot System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f6f8fb 0%, #e9edf3 100%); min-height: 100vh; padding-bottom: 50px; overflow-x: hidden; }

        /* --- NAVIGATION BAR --- */
        .navbar-container { width: 100%; display: flex; justify-content: center; position: fixed; top: 0; z-index: 100; }
        .navbar { width: 98%; max-width: 1400px; background-color: #ffffff; height: 80px; border-radius: 0 0 30px 30px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); display: flex; align-items: center; justify-content: space-between; padding: 0 40px; }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .nav-left img { height: 45px; object-fit: contain; }
        .nav-divider { width: 1px; height: 40px; background-color: #e0e0e0; }
        .nav-center { display: flex; align-items: center; gap: 30px; }
        .nav-item { text-decoration: none; color: #333; font-weight: 600; font-size: 14px; position: relative; display: flex; align-items: center; gap: 5px; cursor: pointer; }
        .nav-text-divider { color: #ccc; font-weight: 300; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .profile-section { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; color: inherit; }
        .profile-icon { width: 35px; height: 35px; background-color: #000; color: #fff; border-radius: 50%; display: flex; justify-content: center; align-items: center; }

        /* --- CART LAYOUT --- */
        .cart-wrapper { max-width: 1200px; margin: 130px auto 0; padding: 0 40px; }
        .cart-container { display: flex; gap: 30px; align-items: flex-start; }

        /* --- LEFT PANEL: BORROWING RECORD --- */
        .record-panel { flex: 2; background-color: #ffffff; border-radius: 15px; padding: 40px; box-shadow: 0 5px 20px rgba(0,0,0,0.02); }
        .record-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .record-header h2 { font-size: 24px; font-weight: 800; color: #000; }
        .record-header span { font-size: 18px; font-weight: 700; color: #000; }
        .record-subheader { display: flex; justify-content: space-between; color: #888; font-size: 14px; font-weight: 500; padding-bottom: 15px; border-bottom: 1px solid #eee; margin-bottom: 20px; }

        .item-row { display: flex; align-items: center; padding: 20px 0; border-bottom: 1px solid #f0f0f0; }
        .item-row:last-child { border-bottom: none; }
        .item-image { width: 100px; height: 100px; border: 1px solid #ddd; display: flex; justify-content: center; align-items: center; margin-right: 20px; padding: 10px; border-radius: 10px;}
        .item-image img { max-width: 100%; max-height: 100%; object-fit: contain; }
        
        .item-details { flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .item-title { font-size: 18px; font-weight: 800; color: #000; margin-bottom: 5px; }
        .item-category { font-size: 15px; color: #555; margin-bottom: 5px; }
        .item-stock { font-size: 13px; color: #666; margin-bottom: 12px; font-weight: 500; }
        .item-remove { font-size: 14px; color: #ff8a8a; font-weight: 500; text-decoration: none; cursor: pointer; transition: color 0.2s; width: fit-content; }
        .item-remove:hover { color: #ff3b30; }

        .item-quantity { display: flex; align-items: center; gap: 10px; }
        .qty-btn { background: none; border: none; font-size: 24px; font-weight: bold; cursor: pointer; color: #333; width: 25px; display: flex; justify-content: center; text-decoration: none; transition: color 0.2s;}
        .qty-btn:hover { color: #0d3b66; }
        .qty-input { width: 40px; height: 30px; border: 1px solid #ccc; text-align: center; font-size: 14px; font-weight: 600; outline: none; border-radius: 5px;}

        /* --- RIGHT PANEL: BORROWING SUMMARY --- */
        .summary-panel { flex: 1; background-color: #dfdfdf; border-radius: 15px; padding: 35px 30px; box-shadow: -5px 10px 30px rgba(0,0,0,0.1); position: sticky; top: 130px; }
        .summary-panel h2 { font-size: 22px; font-weight: 800; margin-bottom: 15px; border-bottom: 1px solid #ccc; padding-bottom: 15px; }
        .summary-panel h3 { font-size: 16px; font-weight: 800; margin-bottom: 20px; text-transform: uppercase; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 12px; font-weight: 800; color: #000; margin-bottom: 8px; }
        .asterisk { color: #ff3b30; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #bbb; border-radius: 5px; background-color: #fff; font-size: 14px; color: #333; outline: none; }
        .form-control:focus { border-color: #888; }
        .reason-other-box { display: none; margin-top: 10px; }
        .date-note { font-size: 11px; color: #555; line-height: 1.4; margin-top: 6px; }
        .terms-group { display: flex; align-items: flex-start; gap: 10px; margin-top: 25px; margin-bottom: 25px; }
        .terms-group input[type="checkbox"] { margin-top: 3px; width: 16px; height: 16px; }
        .terms-text { font-size: 11px; color: #444; line-height: 1.4; }
        .btn-apply { width: 100%; background-color: #ef3b36; color: #fff; border: none; padding: 12px; font-size: 16px; font-weight: 800; cursor: pointer; border-radius: 4px; transition: background 0.3s; }
        .btn-apply:hover { background-color: #d12f2a; }
        .btn-disabled { background-color: #aaa; cursor: not-allowed; }

        @media (max-width: 900px) {
            .cart-container { flex-direction: column; }
            .summary-panel { position: static; width: 100%; }
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

    <main class="cart-wrapper">
        <a href="components.php" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #555; font-weight: 600; margin-bottom: 20px; transition: color 0.2s;" onmouseover="this.style.color='#0d3b66'" onmouseout="this.style.color='#555'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to Components
        </a>

        <div class="cart-container">
            <section class="record-panel">
                <div class="record-header">
                    <h2>Borrowing Record</h2>
                    <span><?php echo $total_items; ?> Items</span>
                </div>
                
                <div class="record-subheader">
                    <span>Component Details</span>
                    <span>Quantity</span>
                </div>

                <?php
                if (!empty($_SESSION['cart'])) {
                    foreach ($_SESSION['cart'] as $comp_id => $qty) {
                        $query_get_item = "
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
                            WHERE c.Component_ID = '$comp_id'
                            GROUP BY c.Component_ID
                        ";
                        $result_item = mysqli_query($conn, $query_get_item);
                        
                        if ($row = mysqli_fetch_assoc($result_item)) {
                            $img = !empty($row['image']) ? $row['image'] : 'default.png';
                            $max_stock = (int)$row['available_quantity'];
                            $pending_qty = (int)$row['pending_quantity'];
                            ?>
                            
                            <div class="item-row">
                                <div class="item-image">
                                    <img src="uploads/<?php echo $img; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" onerror="this.src='https://via.placeholder.com/100';">
                                </div>
                                <div class="item-details">
                                    <h3 class="item-title"><?php echo htmlspecialchars($row['name']); ?></h3>
                                    <p class="item-category"><?php echo $row['category']; ?></p>
                                    
                                    <p class="item-stock">
                                        Available Stock: <strong style="color: green;"><?php echo $max_stock; ?> units</strong>
                                    </p>

                                    <?php if ($pending_qty > 0) { ?>
                                        <p class="item-stock" style="color:#f97316; margin-top:-8px;">
                                            <?php echo $pending_qty; ?> units currently reserved by pending requests
                                        </p>
                                    <?php } ?>

                                    <?php if ($qty > $max_stock) { ?>
                                        <p class="item-stock" style="color:#dc2626; font-weight:700; margin-top:-8px;">
                                            Your cart quantity is higher than available stock. Please reduce the quantity.
                                        </p>
                                    <?php } ?>

                                    <a href="borrowing.php?action=remove&id=<?php echo $comp_id; ?>" class="item-remove">Remove</a>
                                </div>
                                <div class="item-quantity">
                                    <a href="borrowing.php?action=minus&id=<?php echo $comp_id; ?>" class="qty-btn">-</a>
                                    <input type="text" value="<?php echo $qty; ?>" class="qty-input" readonly>
                                    <a href="borrowing.php?action=add&id=<?php echo $comp_id; ?>" class="qty-btn">+</a>
                                </div>
                            </div>

                            <?php
                        }
                    }
                } else {
                    echo "<div style='text-align: center; padding: 40px; color: #888;'>
                            <p>No items in borrowing list.</p>
                            <br>
                            <a href='components.php' style='color: #0d3b66; font-weight: bold; text-decoration: none;'>← Back to find components</a>
                          </div>";
                }
                ?>
            </section>

            <aside class="summary-panel">
                <h2>Borrowing Summary</h2>
                <h3>ITEMS <?php echo $total_items; ?></h3>

                <form action="borrowing.php" method="POST">
                    <div class="form-group">
                        <label>COLLECTION DATE<span class="asterisk">*</span></label>
                        <input 
                            type="date" 
                            name="collection_date" 
                            id="collection_date"
                            class="form-control" 
                            min="<?php echo $today_date; ?>" 
                            required 
                            <?php echo ($total_items == 0) ? 'disabled' : ''; ?>
                        >
                    </div>

                    <div class="form-group">
                        <label>RETURN DATE<span class="asterisk">*</span></label>
                        <input 
                            type="date" 
                            name="return_date" 
                            id="return_date"
                            class="form-control" 
                            min="<?php echo $today_date; ?>" 
                            max="<?php echo $max_return_date; ?>"
                            required 
                            <?php echo ($total_items == 0) ? 'disabled' : ''; ?>
                        >
                        <div class="date-note">
                            * Borrowing duration is limited to maximum 2 months from collection date.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>REASON FOR BORROWING<span class="asterisk">*</span></label>
                        <select 
                            name="reason_choice" 
                            id="reason_choice" 
                            class="form-control" 
                            required 
                            <?php echo ($total_items == 0) ? 'disabled' : ''; ?>
                        >
                            <option value="" disabled selected>Select reason...</option>
                            <option value="Assignment / Lab Work">Assignment / Lab Work</option>
                            <option value="Final Year Project">Final Year Project</option>
                            <option value="Research">Research</option>
                            <option value="Competition / Event">Competition / Event</option>
                            <option value="Personal Learning">Personal Learning</option>
                            <option value="Others">Others</option>
                        </select>

                        <div class="reason-other-box" id="reason_other_box">
                            <input 
                                type="text" 
                                name="reason_other" 
                                id="reason_other" 
                                placeholder="Please specify your reason..." 
                                class="form-control"
                                <?php echo ($total_items == 0) ? 'disabled' : ''; ?>
                            >
                        </div>
                    </div>

                    <div class="terms-group">
                        <input type="checkbox" id="terms" required <?php echo ($total_items == 0) ? 'disabled' : ''; ?>>
                        <label for="terms" class="terms-text">
                            By checking this box, I agree to use the requested components responsibly for academic purposes or external <span class="asterisk">*</span>
                        </label>
                    </div>

                    <button type="submit" name="submit_borrow" class="btn-apply <?php echo ($total_items == 0 || $cart_has_stock_problem) ? 'btn-disabled' : ''; ?>" <?php echo ($total_items == 0 || $cart_has_stock_problem) ? 'disabled' : ''; ?>>APPLY</button>
                </form>
            </aside>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <script>
        const collectionDate = document.getElementById('collection_date');
        const returnDate = document.getElementById('return_date');
        const reasonChoice = document.getElementById('reason_choice');
        const reasonOtherBox = document.getElementById('reason_other_box');
        const reasonOther = document.getElementById('reason_other');

        function addTwoMonths(dateString) {
            const date = new Date(dateString);
            date.setMonth(date.getMonth() + 2);

            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        if (collectionDate && returnDate) {
            collectionDate.addEventListener('change', function() {
                if (this.value) {
                    const maxDate = addTwoMonths(this.value);

                    returnDate.min = this.value;
                    returnDate.max = maxDate;

                    if (returnDate.value && (returnDate.value < this.value || returnDate.value > maxDate)) {
                        returnDate.value = '';
                    }
                }
            });
        }

        if (reasonChoice && reasonOtherBox && reasonOther) {
            reasonChoice.addEventListener('change', function() {
                if (this.value === 'Others') {
                    reasonOtherBox.style.display = 'block';
                    reasonOther.required = true;
                } else {
                    reasonOtherBox.style.display = 'none';
                    reasonOther.required = false;
                    reasonOther.value = '';
                }
            });
        }
    </script>

    <?php showSweetAlert(); ?>
</body>
</html>