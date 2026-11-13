<?php
session_start();
include 'connection.php';
include 'send_email.php';
include 'alerts.php';

// SECURITY CHECK: Prevent unauthorized access
if (!isset($_SESSION['admin_ID'])) {
    setSweetAlert('error', 'Access Denied!', 'Administrators only.');
    header("Location: signin.php");
    exit(); 
}

// --- APPROVAL & CONFIRMATION PROCESS ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $record_id = mysqli_real_escape_string($conn, $_GET['id']);
    $action = $_GET['action'];

    $admin_ID = isset($_SESSION['admin_ID']) ? $_SESSION['admin_ID'] : 2; 

    $collection_day = '';
    $collection_time = '';
    $collection_venue = '';

    if ($action == 'approve') {
        $collection_day = isset($_GET['collection_day']) ? trim($_GET['collection_day']) : '';
        $collection_time = isset($_GET['collection_time']) ? trim($_GET['collection_time']) : '';
        $collection_venue = isset($_GET['collection_venue']) ? trim($_GET['collection_venue']) : '';

        if ($collection_day == '' || $collection_time == '' || $collection_venue == '') {
            setSweetAlert('warning', 'Collection Details Required', 'Please enter collection day, time, and venue before approving the request.');
            header("Location: admin_borrowing_list.php");
            exit();
        }

        $collection_day = mysqli_real_escape_string($conn, $collection_day);
        $collection_time = mysqli_real_escape_string($conn, $collection_time);
        $collection_venue = mysqli_real_escape_string($conn, $collection_venue);
    }

    // GET STUDENT DATA
    $get_student = "SELECT student.name, student.email
                    FROM borrowing_record
                    JOIN student ON borrowing_record.Student_ID = student.Student_ID
                    WHERE borrowing_record.Record_ID = '$record_id'";
    $result_student = mysqli_query($conn, $get_student);

    $student_name = "";
    $student_email = "";

    if ($result_student && mysqli_num_rows($result_student) > 0) {
        $student_data = mysqli_fetch_assoc($result_student);
        $student_name = $student_data['name'];
        $student_email = $student_data['email'];
    }

    if ($action == 'approve') {
        mysqli_begin_transaction($conn);

        try {
            // Make sure this request is still Pending
            $check_record = mysqli_query($conn, "
                SELECT status, borrow_date 
                FROM borrowing_record 
                WHERE Record_ID = '$record_id' 
                FOR UPDATE
            ");

            if (!$check_record || mysqli_num_rows($check_record) == 0) {
                throw new Exception("Borrowing request not found.");
            }

            $record_data = mysqli_fetch_assoc($check_record);

            if ($record_data['status'] != 'Pending') {
                throw new Exception("This request is no longer pending.");
            }

            // Get requested items
            $query_get_items = "SELECT bd.Component_ID, bd.quantity_borrowed, c.name
                                FROM borrowing_detail bd
                                JOIN COMPONENT c ON bd.Component_ID = c.Component_ID
                                WHERE bd.Record_ID = '$record_id'";
            $result_items = mysqli_query($conn, $query_get_items);

            if (!$result_items || mysqli_num_rows($result_items) == 0) {
                throw new Exception("No components found for this request.");
            }

            $items_to_deduct = [];

            while ($item = mysqli_fetch_assoc($result_items)) {
                $comp_id = $item['Component_ID'];
                $qty_borrowed = (int)$item['quantity_borrowed'];
                $component_name = $item['name'];

                // Lock component row so two admins cannot approve at the same time
                $stock_check = mysqli_query($conn, "
                    SELECT total_quantity 
                    FROM COMPONENT 
                    WHERE Component_ID = '$comp_id' 
                    FOR UPDATE
                ");

                if (!$stock_check || mysqli_num_rows($stock_check) == 0) {
                    throw new Exception("Component not found: " . $component_name);
                }

                $stock_data = mysqli_fetch_assoc($stock_check);
                $current_stock = (int)$stock_data['total_quantity'];

                if ($qty_borrowed > $current_stock) {
                    throw new Exception(
                        "Cannot approve. " . $component_name . 
                        " only has " . $current_stock . 
                        " units left, but this request needs " . $qty_borrowed . " units."
                    );
                }

                $items_to_deduct[] = [
                    'Component_ID' => $comp_id,
                    'quantity_borrowed' => $qty_borrowed
                ];
            }

            // Deduct stock only after all items pass stock checking
            foreach ($items_to_deduct as $item) {
                $comp_id = $item['Component_ID'];
                $qty_borrowed = $item['quantity_borrowed'];

                $deduct_stock_query = "UPDATE COMPONENT 
                                       SET total_quantity = total_quantity - $qty_borrowed 
                                       WHERE Component_ID = '$comp_id'";
                mysqli_query($conn, $deduct_stock_query);
            }

            // Approve request
            $update_query = "UPDATE borrowing_record 
                             SET status = 'Approved', 
                                 admin_ID = '$admin_ID',
                                 collection_day = '$collection_day',
                                 collection_time = '$collection_time',
                                 collection_venue = '$collection_venue'
                             WHERE Record_ID = '$record_id'";

            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Failed to approve request.");
            }

            mysqli_commit($conn);

            // SEND APPROVAL EMAIL
            $email_sent = false;

            if (!empty($student_email)) {
                $subject = "TraceIoT Borrowing Request Approved";

                $collection_day_display = $collection_day;
                $collection_date_display = date('d-m-Y', strtotime($record_data['borrow_date']));

                $body = "
                    <h2>Your borrowing request has been approved!</h2>
                    <p>Hello <strong>$student_name</strong>,</p>
                    <p>Your borrowing request with Application ID <strong>#$record_id</strong> has been approved.</p>

                    <h3>Collection Details</h3>
                    <p><strong>Collection Day:</strong> $collection_day_display</p>
                    <p><strong>Collection Date:</strong> $collection_date_display</p>
                    <p><strong>Collection Time:</strong> $collection_time</p>
                    <p><strong>Venue:</strong> $collection_venue</p>

                    <p>Please collect your components according to the details above. Bring your student ID for verification.</p>
                    <br>
                    <p>Thank you,<br><strong>TraceIoT System</strong></p>
                ";

                $email_sent = sendEmail($student_email, $student_name, $subject, $body);
            }

            if ($email_sent) {
                setSweetAlert('success', 'Approved!', 'Request approved successfully. Email notification has been sent.');
            } else {
                setSweetAlert('warning', 'Approved!', 'Request approved successfully, but email notification failed to send.');
            }

            header("Location: admin_borrowing_list.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);

            setSweetAlert('error', 'Cannot Approve', $e->getMessage());
            header("Location: admin_borrowing_list.php");
            exit();
        }
    } 

        elseif ($action == 'reject') {
        $update_query = "UPDATE borrowing_record 
                         SET status = 'Rejected', admin_ID = '$admin_ID' 
                         WHERE Record_ID = '$record_id'";

        if (mysqli_query($conn, $update_query)) {

            // SEND REJECT EMAIL
            $email_sent = false;

            if (!empty($student_email)) {
                $subject = "TraceIoT Borrowing Request Rejected";

                $body = "
                    <h2>Your borrowing request has been rejected</h2>
                    <p>Hello <strong>$student_name</strong>,</p>
                    <p>Your borrowing request with Application ID <strong>#$record_id</strong> has been rejected.</p>
                    <p>Please contact the lab admin if you need more information.</p>
                    <br>
                    <p>Thank you,<br><strong>TraceIoT System</strong></p>
                ";

                $email_sent = sendEmail($student_email, $student_name, $subject, $body);
            }

            if ($email_sent) {
                setSweetAlert('success', 'Rejected!', 'Request rejected successfully. Email notification has been sent.');
            } else {
                setSweetAlert('warning', 'Rejected!', 'Request rejected successfully, but email notification failed to send.');
            }

            header("Location: admin_borrowing_list.php");
            exit();
        }
    }

    elseif ($action == 'reject_return') {
        mysqli_begin_transaction($conn);

        try {
            $check_record = mysqli_query($conn, "
                SELECT status 
                FROM borrowing_record 
                WHERE Record_ID = '$record_id' 
                FOR UPDATE
            ");

            if (!$check_record || mysqli_num_rows($check_record) == 0) {
                throw new Exception("Borrowing request not found.");
            }

            $record_data = mysqli_fetch_assoc($check_record);

            if ($record_data['status'] != 'Pending Return') {
                throw new Exception("Only pending return requests can be rejected.");
            }

            $reset_detail_query = "UPDATE borrowing_detail 
                                   SET return_condition = NULL,
                                       good_quantity = 0,
                                       minor_damage_quantity = 0,
                                       damaged_quantity = 0,
                                       missing_quantity = 0,
                                       return_remarks = NULL,
                                       return_proof = NULL
                                   WHERE Record_ID = '$record_id'";

            if (!mysqli_query($conn, $reset_detail_query)) {
                throw new Exception("Failed to reset return details.");
            }

            $update_query = "UPDATE borrowing_record 
                             SET status = 'On Hold', admin_ID = '$admin_ID'
                             WHERE Record_ID = '$record_id'";

            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Failed to reject return request.");
            }

            mysqli_commit($conn);

            $email_sent = false;

            if (!empty($student_email)) {
                $subject = "TraceIoT Return Request Rejected";

                $body = "
                    <h2>Your return request has been rejected</h2>
                    <p>Hello <strong>$student_name</strong>,</p>
                    <p>Your return application for Application ID <strong>#$record_id</strong> has been rejected because one or more items are damaged, missing, incomplete, or not accepted by the admin.</p>
                    <p>Please replace or complete the required item(s), then submit the return application again once all borrowed components are complete and in good condition.</p>
                    <p>Your borrowing status has been changed to <strong>On Hold</strong>. Please replace or complete the required item(s), then submit a new return application through TraceIoT System.</p>
                    <br>
                    <p>Thank you,<br><strong>TraceIoT System</strong></p>
                ";

                $email_sent = sendEmail($student_email, $student_name, $subject, $body);
            }

            if ($email_sent) {
                setSweetAlert('success', 'Return Rejected!', 'Return request rejected. Status changed to On Hold and student has been notified.');
            } else {
                setSweetAlert('warning', 'Return Rejected!', 'Return request rejected, but email notification failed to send.');
            }

            header("Location: admin_borrowing_list.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);

            setSweetAlert('error', 'Cannot Reject Return', $e->getMessage());
            header("Location: admin_borrowing_list.php");
            exit();
        }
    }

    elseif ($action == 'confirm_return') {
        mysqli_begin_transaction($conn);

        try {
            // Make sure this request is still Pending Return
            $check_record = mysqli_query($conn, "
                SELECT status 
                FROM borrowing_record 
                WHERE Record_ID = '$record_id' 
                FOR UPDATE
            ");

            if (!$check_record || mysqli_num_rows($check_record) == 0) {
                throw new Exception("Borrowing request not found.");
            }

            $record_data = mysqli_fetch_assoc($check_record);

            if ($record_data['status'] != 'Pending Return') {
                throw new Exception("This request is not waiting for return confirmation.");
            }

            // Restore stock based on quantity condition.
            // Only good_quantity will be added back to available stock.
            $query_get_items = "SELECT bd.Component_ID, 
                                       bd.quantity_borrowed,
                                       bd.good_quantity,
                                       bd.minor_damage_quantity,
                                       bd.damaged_quantity,
                                       bd.missing_quantity,
                                       bd.return_remarks,
                                       c.name
                                FROM borrowing_detail bd
                                JOIN COMPONENT c ON bd.Component_ID = c.Component_ID
                                WHERE bd.Record_ID = '$record_id'";
            $result_items = mysqli_query($conn, $query_get_items);

            if (!$result_items || mysqli_num_rows($result_items) == 0) {
                throw new Exception("No components found for this return.");
            }

            while ($item = mysqli_fetch_assoc($result_items)) {
                $comp_id = $item['Component_ID'];
                $borrowed_qty = (int)$item['quantity_borrowed'];
                $good_qty = (int)$item['good_quantity'];
                $minor_qty = (int)$item['minor_damage_quantity'];
                $damaged_qty = (int)$item['damaged_quantity'];
                $missing_qty = (int)$item['missing_quantity'];

                $total_return_qty = $good_qty + $minor_qty + $damaged_qty + $missing_qty;

                if ($total_return_qty != $borrowed_qty) {
                    throw new Exception("Return quantity mismatch for " . $item['name'] . ".");
                }

                if ($good_qty > 0) {
                    $restore_stock_query = "UPDATE COMPONENT 
                                            SET total_quantity = total_quantity + $good_qty 
                                            WHERE Component_ID = '$comp_id'";
                    mysqli_query($conn, $restore_stock_query);
                }

                // If any item is not in good condition, record it in damaged_item_report.
                // Admin can later replace it and add it back to stock from admin_damage_report.php.
                $remarks = isset($item['return_remarks']) ? mysqli_real_escape_string($conn, $item['return_remarks']) : '';

                if ($minor_qty > 0) {
                    $insert_damage = "INSERT INTO damaged_item_report 
                                      (Record_ID, Component_ID, issue_type, issue_quantity, remarks)
                                      VALUES 
                                      ('$record_id', '$comp_id', 'Minor Damage', '$minor_qty', '$remarks')";
                    mysqli_query($conn, $insert_damage);
                }

                if ($damaged_qty > 0) {
                    $insert_damage = "INSERT INTO damaged_item_report 
                                      (Record_ID, Component_ID, issue_type, issue_quantity, remarks)
                                      VALUES 
                                      ('$record_id', '$comp_id', 'Damaged', '$damaged_qty', '$remarks')";
                    mysqli_query($conn, $insert_damage);
                }

                if ($missing_qty > 0) {
                    $insert_damage = "INSERT INTO damaged_item_report 
                                      (Record_ID, Component_ID, issue_type, issue_quantity, remarks)
                                      VALUES 
                                      ('$record_id', '$comp_id', 'Missing', '$missing_qty', '$remarks')";
                    mysqli_query($conn, $insert_damage);
                }
            }

            $update_query = "UPDATE borrowing_record 
                             SET status = 'Returned', admin_ID = '$admin_ID' 
                             WHERE Record_ID = '$record_id'";

            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Failed to confirm return.");
            }

            mysqli_commit($conn);

            // SEND RETURN CONFIRMED EMAIL
            $email_sent = false;

            if (!empty($student_email)) {
                $subject = "TraceIoT Return Confirmed";

                $body = "
                    <h2>Your returned components have been confirmed</h2>
                    <p>Hello <strong>$student_name</strong>,</p>
                    <p>Your return for Application ID <strong>#$record_id</strong> has been verified and confirmed by the admin.</p>
                    <p>The borrowing record has now been marked as <strong>Returned</strong>.</p>
                    <p>Please note: This return was accepted by the admin. Only accepted good-condition items are added back to available inventory.</p>
                    <br>
                    <p>Thank you,<br><strong>TraceIoT System</strong></p>
                ";

                $email_sent = sendEmail($student_email, $student_name, $subject, $body);
            }

            if ($email_sent) {
                setSweetAlert('success', 'Return Confirmed!', 'Return confirmed successfully. Only Good condition items were added back to stock.');
            } else {
                setSweetAlert('warning', 'Return Confirmed!', 'Return confirmed successfully, but email notification failed to send. Only Good condition items were added back to stock.');
            }

            header("Location: admin_borrowing_list.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);

            setSweetAlert('error', 'Cannot Confirm Return', $e->getMessage());
            header("Location: admin_borrowing_list.php");
            exit();
        }
    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing List - Admin TraceIoT</title>

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
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
        }

        .page-header h1 { 
            font-size: 26px; 
            font-weight: 900; 
            color: #000; 
        }

        .status-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .status-tab {
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

        .status-tab:hover {
            transform: translateY(-2px);
            background: #f8fafc;
        }

        .status-tab.active {
            background: #0d3b66;
            color: #ffffff;
            border-color: #0d3b66;
        }

        .btn-clear-filter { 
            background: #fff; 
            border: 1px solid #000; 
            padding: 8px 18px; 
            border-radius: 20px; 
            text-decoration: none; 
            color: #000; 
            font-size: 13px; 
            font-weight: 700; 
            box-shadow: 2px 2px 0px #000; 
            transition: 0.2s; 
        }

        .btn-clear-filter:hover { 
            background: #eee; 
        }

        .search-container { 
            display: flex; 
            gap: 12px; 
            margin-bottom: 25px; 
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
            transition: border 0.2s; 
        }

        .search-input:focus { 
            border-color: #0d3b66; 
            box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1); 
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
            transition: background 0.2s; 
            box-shadow: 0 4px 12px rgba(13, 59, 102, 0.15); 
        }

        .btn-search:hover { 
            background-color: #143e70; 
        }

        .user-card { 
            border: 1px solid #444; 
            border-radius: 12px; 
            margin-bottom: 16px; 
            overflow: hidden; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.05); 
            background-color: #9aa89d; 
        }

        .card-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 18px 25px; 
            cursor: pointer; 
        }

        .card-header h2 { 
            font-size: 18px; 
            font-weight: 900; 
            color: #000; 
            text-transform: uppercase; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            flex-wrap: wrap;
        }

        .btn-toggle { 
            background-color: #fff; 
            border: 1px solid #000; 
            border-radius: 20px; 
            padding: 6px 15px; 
            font-size: 14px; 
            font-weight: 700; 
            cursor: pointer; 
            white-space: nowrap;
        }

        .card-body { 
            background-color: #e4e4e4; 
            border-top: 1px solid #444; 
            padding: 25px; 
            display: none; 
            gap: 40px; 
        }

        .user-card.active .card-body { 
            display: flex; 
        }

        .status-badge { 
            padding: 4px 12px; 
            border-radius: 15px; 
            font-size: 12px; 
            font-weight: 800; 
            border: 1px solid #000; 
            text-transform: capitalize;
        }

        .status-pending { 
            background-color: #ffb800; 
            color: #000; 
        }

        .status-approved { 
            background-color: #a3e635; 
            color: #000; 
        }

        .status-p-return { 
            background-color: #fdba74; 
            color: #000; 
        }

        .status-onhold {
            background-color: #fca5a5;
            color: #000;
        }

        .status-returned { 
            background-color: #cbd5e1; 
            color: #000; 
        }

        .status-rejected { 
            background-color: #f87171; 
            color: #000; 
        }

        .student-info { 
            flex: 1; 
            max-width: 400px; 
        }

        .id-badge { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            background-color: #fff; 
            color: #000; 
            font-weight: 800; 
            font-size: 14px; 
            padding: 8px 20px; 
            border-radius: 8px; 
            border: 1px solid #000; 
            box-shadow: 2px 2px 0px #000; 
            margin-bottom: 25px; 
        }

        .section-title { 
            font-size: 12px; 
            font-weight: 800; 
            color: #555; 
            text-transform: uppercase; 
            margin-bottom: 8px; 
            letter-spacing: 0.5px; 
        }

        .info-grid { 
            display: grid; 
            grid-template-columns: 110px 1fr; 
            row-gap: 15px; 
            font-size: 14px; 
            background: #fff; 
            padding: 20px; 
            border-radius: 10px; 
            border: 1px solid #ccc; 
        }

        .info-label { 
            font-weight: 800; 
            color: #000; 
        }

        .info-value { 
            font-weight: 600; 
            color: #444; 
        }

        .borrowing-table-container { 
            flex: 2; 
            display: flex; 
            flex-direction: column; 
        }

        .borrowing-table { 
            width: 100%; 
            border-collapse: collapse; 
        }

        .borrowing-table th { 
            text-align: left; 
            font-weight: 800; 
            color: #000; 
            padding-bottom: 15px; 
            border-bottom: 1px solid #bbb; 
        }

        .borrowing-table td { 
            padding: 12px 0; 
            font-weight: 600; 
            color: #444; 
            border-bottom: 1px dashed #ccc; 
            vertical-align: top;
        }

        .return-detail-box {
            margin-top: 10px;
            font-size: 13px;
            line-height: 1.7;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            max-width: 470px;
        }

        .return-detail-box div {
            margin-bottom: 5px;
        }

        .proof-img {
            width: 130px;
            height: 95px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #0284c7;
            margin-top: 6px;
            display: block;
        }

        .proof-small {
            color: #0284c7;
            font-weight: 700;
            font-size: 12px;
        }

        .action-btns { 
            margin-top: 20px; 
            display: flex; 
            gap: 10px; 
            justify-content: flex-end; 
        }

        .btn-action { 
            padding: 10px 25px; 
            border-radius: 8px; 
            border: 1px solid #000; 
            font-weight: 800; 
            font-size: 13px; 
            cursor: pointer; 
            text-decoration: none; 
            color: #000; 
            box-shadow: 2px 2px 0px #000; 
        }

        .btn-approve { 
            background-color: #4ade80; 
        }

        .btn-reject { 
            background-color: #f87171; 
        }

        .btn-confirm { 
            background-color: #38bdf8; 
        }

        .btn-reject-return {
            background-color: #fb7185;
        }

        @media (max-width: 900px) {
            .card-body {
                flex-direction: column;
            }

            .student-info {
                max-width: 100%;
            }

            .main-content {
                padding-left: 20px;
                padding-right: 20px;
            }

            .navbar {
                padding: 0 20px;
            }

            .nav-center {
                gap: 12px;
            }

            .nav-item {
                font-size: 12px;
            }

            .card-header {
                align-items: flex-start;
                gap: 12px;
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
                <a href="admin_damage_report.php" class="nav-item">Damaged Items</a>
                <span class="nav-text-divider">|</span>
                <a href="admin_borrowing_list.php" class="nav-item active">Booking Record</a>
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
            <h1>
                Borrowing Management
                <?php 
                $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
                $search_filter = isset($_GET['search']) ? $_GET['search'] : '';

                if ($status_filter != '') {
                    echo "<span style='font-size: 16px; color: #666; font-weight: 600; margin-left: 10px;'>(Filtering: " . htmlspecialchars($status_filter) . ")</span>";
                }

                if ($search_filter != '') {
                    echo "<span style='font-size: 16px; color: #0d3b66; font-weight: 600; margin-left: 10px;'>(Search: \"" . htmlspecialchars($search_filter) . "\")</span>";
                }
                ?>
            </h1>

            <?php if ($status_filter != '' || $search_filter != '') { ?>
                <a href="admin_borrowing_list.php" class="btn-clear-filter">Reset & Show All</a>
            <?php } ?>
        </div>

        <div class="status-tabs">
            <a href="admin_borrowing_list.php<?php echo ($search_filter != '') ? '?search=' . urlencode($search_filter) : ''; ?>" class="status-tab <?php echo ($status_filter == '') ? 'active' : ''; ?>">All</a>
            <a href="admin_borrowing_list.php?status=Pending<?php echo ($search_filter != '') ? '&search=' . urlencode($search_filter) : ''; ?>" class="status-tab <?php echo ($status_filter == 'Pending') ? 'active' : ''; ?>">Pending</a>
            <a href="admin_borrowing_list.php?status=Approved<?php echo ($search_filter != '') ? '&search=' . urlencode($search_filter) : ''; ?>" class="status-tab <?php echo ($status_filter == 'Approved') ? 'active' : ''; ?>">Approved</a>
            <a href="admin_borrowing_list.php?status=Pending Return<?php echo ($search_filter != '') ? '&search=' . urlencode($search_filter) : ''; ?>" class="status-tab <?php echo ($status_filter == 'Pending Return') ? 'active' : ''; ?>">Pending Return</a>
            <a href="admin_borrowing_list.php?status=On Hold<?php echo ($search_filter != '') ? '&search=' . urlencode($search_filter) : ''; ?>" class="status-tab <?php echo ($status_filter == 'On Hold') ? 'active' : ''; ?>">On Hold</a>
            <a href="admin_borrowing_list.php?status=Returned<?php echo ($search_filter != '') ? '&search=' . urlencode($search_filter) : ''; ?>" class="status-tab <?php echo ($status_filter == 'Returned') ? 'active' : ''; ?>">Returned</a>
            <a href="admin_borrowing_list.php?status=Rejected<?php echo ($search_filter != '') ? '&search=' . urlencode($search_filter) : ''; ?>" class="status-tab <?php echo ($status_filter == 'Rejected') ? 'active' : ''; ?>">Rejected</a>
        </div>

        <div class="search-container">
            <form action="admin_borrowing_list.php" method="GET" class="search-form">
                <?php if ($status_filter != '') { ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <?php } ?>

                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="Search by Student Name or Application ID (e.g. 18)..." 
                    value="<?php echo htmlspecialchars($search_filter); ?>"
                >

                <button type="submit" class="btn-search">Search 🔍</button>
            </form>
        </div>

        <?php
        $query_get_requests = "SELECT borrowing_record.*, 
                                      student.name AS student_name, 
                                      student.email AS student_email, 
                                      student.Student_ID AS matric_num, 
                                      student.phone_num, 
                                      student.faculty 
                               FROM borrowing_record 
                               JOIN student ON borrowing_record.Student_ID = student.Student_ID";

        $where_clauses = [];

        if ($status_filter != '') {
            $safe_status = mysqli_real_escape_string($conn, $status_filter);
            $where_clauses[] = "borrowing_record.status = '$safe_status'";
        }

        if ($search_filter != '') {
            $safe_search = mysqli_real_escape_string($conn, $search_filter);
            $where_clauses[] = "(student.name LIKE '%$safe_search%' OR borrowing_record.Record_ID LIKE '%$safe_search%')";
        }

        if (!empty($where_clauses)) {
            $query_get_requests .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $records_per_page = 10;
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        if ($current_page < 1) {
            $current_page = 1;
        }

        $count_query = "SELECT COUNT(*) AS total_records
                        FROM borrowing_record
                        JOIN student ON borrowing_record.Student_ID = student.Student_ID";

        if (!empty($where_clauses)) {
            $count_query .= " WHERE " . implode(" AND ", $where_clauses);
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

        $query_get_requests .= " ORDER BY FIELD(borrowing_record.status, 'Pending', 'Pending Return', 'On Hold', 'Approved', 'Returned', 'Rejected'), borrowing_record.Record_ID DESC
                                 LIMIT $records_per_page OFFSET $offset";

        $result_requests = mysqli_query($conn, $query_get_requests);

        if ($result_requests && mysqli_num_rows($result_requests) > 0) {
            while ($row = mysqli_fetch_assoc($result_requests)) {
                $b_id = $row['Record_ID']; 
                $status = $row['status'];

                $badge_class = 'status-pending';

                if ($status == 'Approved') {
                    $badge_class = 'status-approved';
                } elseif ($status == 'Pending Return') {
                    $badge_class = 'status-p-return';
                } elseif ($status == 'On Hold') {
                    $badge_class = 'status-onhold';
                } elseif ($status == 'Returned') {
                    $badge_class = 'status-returned';
                } elseif ($status == 'Rejected') {
                    $badge_class = 'status-rejected';
                }
        ?>

                <div class="user-card">
                    <div class="card-header">
                        <h2>
                            <?php echo htmlspecialchars($row['student_name']); ?> 
                            <span class="status-badge <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </h2>

                        <button class="btn-toggle">⌄ Open</button>
                    </div>

                    <div class="card-body">
                        <div class="student-info">
                            <div class="id-badge">
                                🪪 Application ID : #<?php echo $b_id; ?>
                            </div>

                            <div class="section-title">Student Details</div>

                            <div class="info-grid" style="margin-bottom: 20px;">
                                <span class="info-label">Student ID</span>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($row['matric_num']); ?>
                                </span>

                                <span class="info-label">Email</span>
                                <span class="info-value">
                                    <a href="mailto:<?php echo htmlspecialchars($row['student_email']); ?>" style="color: #0d3b66; text-decoration: none;">
                                        <?php echo htmlspecialchars($row['student_email']); ?>
                                    </a>
                                </span>

                                <span class="info-label">Phone</span>
                                <span class="info-value">
                                    <a href="https://wa.me/6<?php echo preg_replace('/[^0-9]/', '', $row['phone_num']); ?>" target="_blank" style="color: #0d3b66; text-decoration: none;">
                                        <?php echo htmlspecialchars($row['phone_num']); ?>
                                    </a>
                                </span>

                                <span class="info-label">Faculty</span>
                                <span class="info-value" style="text-transform: uppercase;">
                                    <?php echo !empty($row['faculty']) ? htmlspecialchars($row['faculty']) : 'N/A'; ?>
                                </span>
                            </div>

                            <div class="section-title">Borrowing Info</div>

                            <div class="info-grid">
                                <span class="info-label">Day</span>
                                <span class="info-value">
                                    <?php echo !empty($row['collection_day']) ? htmlspecialchars($row['collection_day']) : 'Not set'; ?>
                                </span>

                                <span class="info-label">Collect</span>
                                <span class="info-value">
                                    <?php echo date('d-m-Y', strtotime($row['borrow_date'])); ?>
                                </span>

                                <span class="info-label">Time</span>
                                <span class="info-value">
                                    <?php echo !empty($row['collection_time']) ? htmlspecialchars($row['collection_time']) : 'Not set'; ?>
                                </span>

                                <span class="info-label">Venue</span>
                                <span class="info-value">
                                    <?php echo !empty($row['collection_venue']) ? htmlspecialchars($row['collection_venue']) : 'Not set'; ?>
                                </span>

                                <span class="info-label">Return</span>
                                <span class="info-value">
                                    <?php echo !empty($row['return_date']) ? date('d-m-Y', strtotime($row['return_date'])) : 'N/A'; ?>
                                </span>

                                <span class="info-label">Reason</span>
                                <span class="info-value">
                                    "<?php echo htmlspecialchars($row['reason']); ?>"
                                </span>
                            </div>
                        </div>

                        <div class="borrowing-table-container">
                            <table class="borrowing-table">
                                <thead>
                                    <tr>
                                        <th>Component / Return Details</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php
                                    $query_items = "SELECT borrowing_detail.quantity_borrowed, 
                                                           borrowing_detail.return_condition,
                                                           borrowing_detail.good_quantity,
                                                           borrowing_detail.minor_damage_quantity,
                                                           borrowing_detail.damaged_quantity,
                                                           borrowing_detail.missing_quantity,
                                                           borrowing_detail.return_remarks,
                                                           borrowing_detail.return_proof,
                                                           COMPONENT.name 
                                                    FROM borrowing_detail 
                                                    JOIN COMPONENT ON borrowing_detail.Component_ID = COMPONENT.Component_ID 
                                                    WHERE borrowing_detail.Record_ID = '$b_id'";

                                    $result_items = mysqli_query($conn, $query_items);

                                    if ($result_items && mysqli_num_rows($result_items) > 0) {
                                        $shared_proof_file = "";

                                        while ($item_row = mysqli_fetch_assoc($result_items)) {
                                            if ($shared_proof_file == "" && !empty($item_row['return_proof'])) {
                                                $shared_proof_file = $item_row['return_proof'];
                                            }
                                    ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item_row['name']); ?></strong>

                                                    <?php if ($status == 'Pending Return' || $status == 'Returned') { ?>
                                                        <div class="return-detail-box">
                                                            <div>
                                                                <strong>Condition Summary:</strong>
                                                                <?php 
                                                                echo !empty($item_row['return_condition']) 
                                                                    ? htmlspecialchars($item_row['return_condition']) 
                                                                    : 'Not provided'; 
                                                                ?>
                                                            </div>

                                                            <div>
                                                                <strong>Return Quantity:</strong><br>
                                                                Good: <?php echo (int)$item_row['good_quantity']; ?> |
                                                                Minor Damage: <?php echo (int)$item_row['minor_damage_quantity']; ?> |
                                                                Damaged: <?php echo (int)$item_row['damaged_quantity']; ?> |
                                                                Missing: <?php echo (int)$item_row['missing_quantity']; ?>
                                                            </div>

                                                            <div>
                                                                <strong>Stock Action:</strong>
                                                                <span style="color:#166534; font-weight:800;">
                                                                    <?php echo (int)$item_row['good_quantity']; ?> units will be added back to stock
                                                                </span>
                                                            </div>

                                                            <div>
                                                                <strong>Remarks:</strong>
                                                                <?php 
                                                                echo !empty($item_row['return_remarks']) 
                                                                    ? htmlspecialchars($item_row['return_remarks']) 
                                                                    : 'Not provided'; 
                                                                ?>
                                                            </div>


                                                        </div>
                                                    <?php } ?>
                                                </td>

                                                <td>
                                                    <?php echo $item_row['quantity_borrowed']; ?> Units
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo "<tr><td colspan='2'>No components found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>

                            <?php if (($status == 'Pending Return' || $status == 'Returned' || $status == 'On Hold') && isset($shared_proof_file)) { ?>
                                <div class="return-detail-box" style="margin-top: 18px; max-width: 100%;">
                                    <strong>Proof Image Summary:</strong><br>

                                    <?php if (!empty($shared_proof_file)) { ?>
                                        <a href="uploads/proof/<?php echo htmlspecialchars($shared_proof_file); ?>" target="_blank">
                                            <img 
                                                src="uploads/proof/<?php echo htmlspecialchars($shared_proof_file); ?>" 
                                                class="proof-img"
                                                alt="Return Proof"
                                            >
                                        </a>
                                        <small class="proof-small">🔍 Click to enlarge</small>
                                    <?php } else { ?>
                                        Not uploaded
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <div class="action-btns">
                                <?php if ($status == 'Pending') { ?>
                                    <a 
                                        href="admin_borrowing_list.php?action=approve&id=<?php echo $b_id; ?>" 
                                        class="btn-action btn-approve" 
                                        onclick="confirmApproveWithDetails(event, <?php echo $b_id; ?>, '<?php echo date('d-m-Y', strtotime($row['borrow_date'])); ?>')"
                                    >
                                        Approve ✔
                                    </a>

                                    <a 
                                        href="admin_borrowing_list.php?action=reject&id=<?php echo $b_id; ?>" 
                                        class="btn-action btn-reject" 
                                        onclick="confirmAdminAction(event, this.href, 'Reject this request?', 'This borrowing request will be marked as rejected.')"
                                    >
                                        Reject ✖
                                    </a>

                                <?php } elseif ($status == 'Pending Return') { ?>
                                    <a 
                                        href="admin_borrowing_list.php?action=confirm_return&id=<?php echo $b_id; ?>" 
                                        class="btn-action btn-confirm" 
                                        onclick="confirmAdminAction(event, this.href, 'Confirm return?', 'Only confirm if all items are complete and accepted. Good quantity will be added back to stock.')"
                                    >
                                        Confirm Return 📦
                                    </a>

                                    <a 
                                        href="admin_borrowing_list.php?action=reject_return&id=<?php echo $b_id; ?>" 
                                        class="btn-action btn-reject-return" 
                                        onclick="confirmAdminAction(event, this.href, 'Reject return request?', 'Use this if any item is damaged, missing, incomplete, or must be replaced. The student will need to submit the return again after replacing the item.')"
                                    >
                                        Reject Return ✖
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

        <?php
            }
        } else {
            echo "<div style='text-align: center; margin-top: 50px; font-weight:600; background: white; padding: 40px; border-radius: 15px; border: 1px solid #eaeaea;'>
                    <h3>No matching borrowing records found.</h3>
                    <p style='color: #666; font-size:14px; font-weight: 500; margin-top: 5px;'>Try refining your keyword search or clear active query parameters.</p>
                  </div>";
        }
        ?>

        <?php if ($total_records > 0) { ?>
            <div class="pagination-info">
                Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> applications
            </div>

            <div class="pagination">
                <?php
                $base_params = [];

                if ($status_filter != '') {
                    $base_params['status'] = $status_filter;
                }

                if ($search_filter != '') {
                    $base_params['search'] = $search_filter;
                }

                if ($current_page > 1) {
                    $base_params['page'] = $current_page - 1;
                    echo '<a href="admin_borrowing_list.php?' . http_build_query($base_params) . '">Previous</a>';
                } else {
                    echo '<span class="disabled-page">Previous</span>';
                }

                for ($i = 1; $i <= $total_pages; $i++) {
                    $base_params['page'] = $i;
                    $active_class = ($i == $current_page) ? 'active-page' : '';
                    echo '<a class="' . $active_class . '" href="admin_borrowing_list.php?' . http_build_query($base_params) . '">' . $i . '</a>';
                }

                if ($current_page < $total_pages) {
                    $base_params['page'] = $current_page + 1;
                    echo '<a href="admin_borrowing_list.php?' . http_build_query($base_params) . '">Next</a>';
                } else {
                    echo '<span class="disabled-page">Next</span>';
                }
                ?>
            </div>
        <?php } ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.querySelectorAll('.card-header').forEach(header => {
            header.addEventListener('click', function() {
                const card = this.parentElement;
                const btn = this.querySelector('.btn-toggle');

                card.classList.toggle('active');

                if (card.classList.contains('active')) {
                    btn.innerHTML = '⌃ Close';
                } else {
                    btn.innerHTML = '⌄ Open';
                }
            });
        });

        function confirmApproveWithDetails(event, recordId, collectionDate) {
            event.preventDefault();

            Swal.fire({
                title: 'Approve Request?',
                html: `
                    <div style="text-align:left;">
                        <p style="font-size:13px; margin-bottom:12px;">
                            This will approve the borrowing request and deduct stock.
                        </p>

                        <label style="font-size:12px; font-weight:800;">Collection Day</label>
                        <select 
                            id="swal_collection_day" 
                            class="swal2-input"
                            style="margin:6px 0 12px 0; width:100%;"
                        >
                            <option value="">Select day...</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>

                        <label style="font-size:12px; font-weight:800;">Collection Date</label>
                        <input 
                            type="text" 
                            value="${collectionDate}" 
                            class="swal2-input" 
                            readonly
                            style="margin:6px 0 12px 0; width:100%;"
                        >

                        <label style="font-size:12px; font-weight:800;">Collection Time</label>
                        <input 
                            id="swal_collection_time" 
                            type="text" 
                            class="swal2-input" 
                            placeholder="Example: 10:00 AM - 12:00 PM"
                            style="margin:6px 0 12px 0; width:100%;"
                        >

                        <label style="font-size:12px; font-weight:800;">Collection Venue</label>
                        <input 
                            id="swal_collection_venue" 
                            type="text" 
                            class="swal2-input" 
                            placeholder="Example: IoT Laboratory, Block A"
                            style="margin:6px 0 0 0; width:100%;"
                        >
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Approve',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const collectionDay = document.getElementById('swal_collection_day').value.trim();
                    const collectionTime = document.getElementById('swal_collection_time').value.trim();
                    const collectionVenue = document.getElementById('swal_collection_venue').value.trim();

                    if (!collectionDay || !collectionTime || !collectionVenue) {
                        Swal.showValidationMessage('Please enter collection day, time, and venue.');
                        return false;
                    }

                    return {
                        collectionDay: collectionDay,
                        collectionTime: collectionTime,
                        collectionVenue: collectionVenue
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const collectionDay = encodeURIComponent(result.value.collectionDay);
                    const collectionTime = encodeURIComponent(result.value.collectionTime);
                    const collectionVenue = encodeURIComponent(result.value.collectionVenue);

                    window.location.href = 'admin_borrowing_list.php?action=approve&id=' + recordId + 
                                           '&collection_day=' + collectionDay +
                                           '&collection_time=' + collectionTime + 
                                           '&collection_venue=' + collectionVenue;
                }
            });
        }

        function confirmAdminAction(event, url, title, text) {
            event.preventDefault();

            Swal.fire({
                icon: 'question',
                title: title,
                text: text,
                showCancelButton: true,
                confirmButtonText: 'Yes',
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
