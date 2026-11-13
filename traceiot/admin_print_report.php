<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'connection.php';

if (!isset($_SESSION['admin_ID'])) {
    echo "<script>alert('Access Denied! Administrators only.'); window.location.href='signin.php';</script>";
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : 'inventory';

$title = 'Inventory Stock Report';
if ($type == 'borrowing') {
    $title = 'Borrowing Summary Report';
} elseif ($type == 'damage') {
    $title = 'Damaged / Missing Items Report';
}

function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo e($title); ?> - TraceIoT</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box;font-family:'Inter',sans-serif}
body{margin:0;background:#f1f5f9;color:#0f172a}
.page{max-width:1100px;margin:30px auto;background:white;padding:35px;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.08)}
.header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #e2e8f0;padding-bottom:20px;margin-bottom:20px}
.logo{display:flex;gap:18px;align-items:center}
.logo img{height:50px;object-fit:contain}
h1{margin:0;color:#0d3b66;font-size:26px}
.small{color:#64748b;font-weight:600;font-size:13px;margin-top:6px}
.actions{display:flex;gap:10px;justify-content:flex-end;margin-bottom:20px}
.btn{border:0;padding:10px 18px;border-radius:8px;font-weight:800;text-decoration:none;cursor:pointer;font-size:13px}
.btn-print{background:#0d3b66;color:white}.btn-back{background:#e2e8f0;color:#0f172a}
.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:25px}
.card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px}
.card h3{margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase}.card p{margin:0;font-size:24px;font-weight:900;color:#0d3b66}
table{width:100%;border-collapse:collapse;margin-top:12px}th{background:#0d3b66;color:white;text-align:left;padding:12px;font-size:12px;text-transform:uppercase}td{padding:12px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:600;vertical-align:top}tr:nth-child(even) td{background:#f8fafc}
.badge{display:inline-block;padding:5px 9px;border-radius:12px;font-size:11px;font-weight:900}.low,.need{background:#fee2e2;color:#991b1b}.ok,.replaced{background:#dcfce7;color:#166534}.pending{background:#fef3c7;color:#92400e}.return{background:#ffedd5;color:#9a3412}
.footer{margin-top:25px;text-align:center;color:#64748b;font-size:12px;border-top:1px solid #e2e8f0;padding-top:15px}
@media print{body{background:white}.page{margin:0;max-width:100%;box-shadow:none;border-radius:0;padding:20px}.actions{display:none}th{background:#e2e8f0!important;color:#000!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}tr:nth-child(even) td{background:#f8fafc!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="logo">
            <img src="uitmlogo.png" alt="UiTM">
            <img src="traceiotlogo.jpeg" alt="TraceIoT">
            <div>
                <h1><?php echo e($title); ?></h1>
                <div class="small">TraceIoT System | Generated on <?php echo date('d-m-Y h:i A'); ?></div>
            </div>
        </div>
    </div>

    <div class="actions">
        <a href="admin_home.php" class="btn btn-back">← Back</a>
        <button onclick="window.print()" class="btn btn-print">Print / Save PDF</button>
    </div>

<?php if ($type == 'inventory') { ?>
    <?php
    $total_components = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM COMPONENT"))['total'];
    $total_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(CAST(total_quantity AS UNSIGNED)),0) AS total FROM COMPONENT"))['total'];
    $low_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM COMPONENT WHERE CAST(total_quantity AS UNSIGNED) <= 3"))['total'];
    $categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT category) AS total FROM COMPONENT"))['total'];
    $items = mysqli_query($conn, "SELECT name, category, total_quantity, cabinet_num FROM COMPONENT ORDER BY category, name");
    ?>
    <div class="summary">
        <div class="card"><h3>Total Components</h3><p><?php echo $total_components; ?></p></div>
        <div class="card"><h3>Total Stock Units</h3><p><?php echo $total_stock; ?></p></div>
        <div class="card"><h3>Low Stock Items</h3><p><?php echo $low_stock; ?></p></div>
        <div class="card"><h3>Categories</h3><p><?php echo $categories; ?></p></div>
    </div>
    <table>
        <thead><tr><th>No.</th><th>Component</th><th>Category</th><th>Quantity</th><th>Cabinet</th><th>Status</th></tr></thead>
        <tbody>
        <?php $no=1; while($row=mysqli_fetch_assoc($items)){ $qty=(int)$row['total_quantity']; ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo e($row['name']); ?></td>
                <td><?php echo e($row['category']); ?></td>
                <td><?php echo $qty; ?> unit(s)</td>
                <td><?php echo e($row['cabinet_num']); ?></td>
                <td><?php echo ($qty<=3) ? "<span class='badge low'>Low Stock</span>" : "<span class='badge ok'>Sufficient</span>"; ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

<?php } elseif ($type == 'borrowing') { ?>
    <?php
    $pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM borrowing_record WHERE status='Pending'"))['total'];
    $pending_return = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM borrowing_record WHERE status='Pending Return'"))['total'];
    $approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM borrowing_record WHERE status='Approved'"))['total'];
    $returned = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM borrowing_record WHERE status='Returned'"))['total'];
    $records = mysqli_query($conn, "SELECT br.Record_ID, br.borrow_date, br.return_date, br.reason, br.status, student.name AS student_name, student.Student_ID FROM borrowing_record br JOIN student ON br.Student_ID=student.Student_ID ORDER BY br.Record_ID DESC LIMIT 50");
    ?>
    <div class="summary">
        <div class="card"><h3>Pending</h3><p><?php echo $pending; ?></p></div>
        <div class="card"><h3>Pending Return</h3><p><?php echo $pending_return; ?></p></div>
        <div class="card"><h3>Currently Borrowed</h3><p><?php echo $approved; ?></p></div>
        <div class="card"><h3>Returned</h3><p><?php echo $returned; ?></p></div>
    </div>
    <table>
        <thead><tr><th>Application ID</th><th>Student</th><th>Borrow Date</th><th>Return Date</th><th>Reason</th><th>Status</th></tr></thead>
        <tbody>
        <?php while($row=mysqli_fetch_assoc($records)){ $cls='pending'; if($row['status']=='Pending Return'){$cls='return';} elseif($row['status']=='Approved'||$row['status']=='Returned'){$cls='ok';} ?>
            <tr>
                <td>#<?php echo $row['Record_ID']; ?></td>
                <td><?php echo e($row['student_name']); ?><br><small><?php echo e($row['Student_ID']); ?></small></td>
                <td><?php echo date('d-m-Y', strtotime($row['borrow_date'])); ?></td>
                <td><?php echo !empty($row['return_date']) ? date('d-m-Y', strtotime($row['return_date'])) : 'N/A'; ?></td>
                <td><?php echo e($row['reason']); ?></td>
                <td><span class="badge <?php echo $cls; ?>"><?php echo e($row['status']); ?></span></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

<?php } elseif ($type == 'damage') { ?>
    <?php
    $need = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(issue_quantity),0) AS total FROM damaged_item_report WHERE report_status='Need Replacement'"))['total'];
    $replaced = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(issue_quantity),0) AS total FROM damaged_item_report WHERE report_status='Replaced'"))['total'];
    $damaged = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(issue_quantity),0) AS total FROM damaged_item_report WHERE issue_type='Damaged' AND report_status='Need Replacement'"))['total'];
    $missing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(issue_quantity),0) AS total FROM damaged_item_report WHERE issue_type='Missing' AND report_status='Need Replacement'"))['total'];
    $records = mysqli_query($conn, "SELECT dir.*, br.Record_ID, student.name AS student_name, student.Student_ID, c.name AS component_name FROM damaged_item_report dir JOIN borrowing_record br ON dir.Record_ID=br.Record_ID JOIN student ON br.Student_ID=student.Student_ID JOIN COMPONENT c ON dir.Component_ID=c.Component_ID ORDER BY FIELD(dir.report_status,'Need Replacement','Replaced'), dir.report_id DESC");
    ?>
    <div class="summary">
        <div class="card"><h3>Need Replacement</h3><p><?php echo $need; ?></p></div>
        <div class="card"><h3>Replaced</h3><p><?php echo $replaced; ?></p></div>
        <div class="card"><h3>Damaged</h3><p><?php echo $damaged; ?></p></div>
        <div class="card"><h3>Missing</h3><p><?php echo $missing; ?></p></div>
    </div>
    <table>
        <thead><tr><th>Application ID</th><th>Student</th><th>Component</th><th>Issue</th><th>Qty</th><th>Remarks</th><th>Status</th></tr></thead>
        <tbody>
        <?php while($row=mysqli_fetch_assoc($records)){ ?>
            <tr>
                <td>#<?php echo $row['Record_ID']; ?></td>
                <td><?php echo e($row['student_name']); ?><br><small><?php echo e($row['Student_ID']); ?></small></td>
                <td><?php echo e($row['component_name']); ?></td>
                <td><?php echo e($row['issue_type']); ?></td>
                <td><?php echo (int)$row['issue_quantity']; ?> unit(s)</td>
                <td><?php echo !empty($row['remarks']) ? e($row['remarks']) : 'No remarks'; ?></td>
                <td><?php echo ($row['report_status']=='Replaced') ? "<span class='badge replaced'>Replaced</span>" : "<span class='badge need'>Need Replacement</span>"; ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
<?php } else { ?>
    <p>Invalid report type.</p>
<?php } ?>

    <div class="footer">This report is generated automatically by TraceIoT System.</div>
</div>
</body>
</html>
