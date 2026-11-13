<?php
// reminder_return.php
// This file sends return reminder emails to students 7 days before their return date.
// Run this file once per day using browser or Windows Task Scheduler.

include 'connection.php';
include 'send_email.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

$today = date('Y-m-d');
$reminder_date = date('Y-m-d', strtotime('+7 days'));

echo "<h2>TraceIoT Return Reminder Check</h2>";
echo "<p>Today: <strong>$today</strong></p>";
echo "<p>Checking return date: <strong>$reminder_date</strong></p>";
echo "<hr>";

$query = "
    SELECT 
        br.Record_ID,
        br.borrow_date,
        br.return_date,
        br.reason,
        br.reminder_sent,
        s.name AS student_name,
        s.email AS student_email
    FROM borrowing_record br
    JOIN student s ON br.Student_ID = s.Student_ID
    WHERE br.status = 'Approved'
    AND br.return_date = '$reminder_date'
    AND br.reminder_sent = 0
";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo "<p style='color:red; font-weight:bold;'>Database Error: " . mysqli_error($conn) . "</p>";
    exit();
}

if (mysqli_num_rows($result) == 0) {
    echo "<p>No return reminders to send today.</p>";
    exit();
}

$sent_count = 0;
$failed_count = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $record_id = $row['Record_ID'];
    $student_name = $row['student_name'];
    $student_email = $row['student_email'];
    $borrow_date = date('d-m-Y', strtotime($row['borrow_date']));
    $return_date = date('d-m-Y', strtotime($row['return_date']));
    $reason = $row['reason'];

    // Get borrowed items for this application
    $items_query = "
        SELECT 
            c.name,
            bd.quantity_borrowed
        FROM borrowing_detail bd
        JOIN COMPONENT c ON bd.Component_ID = c.Component_ID
        WHERE bd.Record_ID = '$record_id'
    ";

    $items_result = mysqli_query($conn, $items_query);

    $items_html = "<ul>";

    if ($items_result && mysqli_num_rows($items_result) > 0) {
        while ($item = mysqli_fetch_assoc($items_result)) {
            $item_name = htmlspecialchars($item['name']);
            $item_qty = (int)$item['quantity_borrowed'];

            $items_html .= "<li><strong>$item_name</strong> - $item_qty unit(s)</li>";
        }
    } else {
        $items_html .= "<li>No component details found.</li>";
    }

    $items_html .= "</ul>";

    $subject = "TraceIoT Return Reminder - Due in 7 Days";

    $body = "
        <h2>Return Reminder</h2>

        <p>Hello <strong>$student_name</strong>,</p>

        <p>This is a reminder that your borrowed components for Application ID 
        <strong>#$record_id</strong> are due for return in <strong>7 days</strong>.</p>

        <h3>Borrowing Details</h3>
        <p><strong>Application ID:</strong> #$record_id</p>
        <p><strong>Borrow Date:</strong> $borrow_date</p>
        <p><strong>Return Due Date:</strong> $return_date</p>
        <p><strong>Reason:</strong> $reason</p>

        <h3>Borrowed Components</h3>
        $items_html

        <p>Please make sure to return the components on or before the due date.</p>
        <p>When returning the components, please submit your return request through TraceIoT System and upload the required proof image.</p>

        <br>
        <p>Thank you,<br><strong>TraceIoT System</strong></p>
    ";

    $email_sent = false;

    if (!empty($student_email)) {
        $email_sent = sendEmail($student_email, $student_name, $subject, $body);
    }

    if ($email_sent) {
        $update_query = "
            UPDATE borrowing_record
            SET reminder_sent = 1
            WHERE Record_ID = '$record_id'
        ";

        mysqli_query($conn, $update_query);

        echo "<p style='color:green; font-weight:bold;'>Reminder sent to $student_email for Application ID #$record_id.</p>";
        $sent_count++;
    } else {
        echo "<p style='color:red; font-weight:bold;'>Failed to send reminder to $student_email for Application ID #$record_id.</p>";
        $failed_count++;
    }
}

echo "<hr>";
echo "<p><strong>Total Sent:</strong> $sent_count</p>";
echo "<p><strong>Total Failed:</strong> $failed_count</p>";
?>
