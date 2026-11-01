<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require_once 'db.php';
require_once 'auth_check.php';

checkAccess(['Admin']);

$section = $_GET['section'] ?? '';
$period  = $_GET['period'] ?? '';

$validSections = ['lost', 'found', 'claimed'];
$validPeriods = ['daily', 'weekly', 'monthly', 'yearly', 'overall'];

if (!in_array($section, $validSections, true) || !in_array($period, $validPeriods, true)) {
    http_response_code(400);
    exit('Invalid report selection.');
}

$today = new DateTime('today');
$start = null;
$end = null;

switch ($period) {
    case 'daily':
        $start = clone $today;
        $end = (clone $today)->modify('+1 day');
        break;
    case 'weekly':
        $start = (clone $today)->modify('monday this week');
        $end = (clone $start)->modify('+7 days');
        break;
    case 'monthly':
        $start = new DateTime($today->format('Y-m-01'));
        $end = (clone $start)->modify('+1 month');
        break;
    case 'yearly':
        $start = new DateTime($today->format('Y-01-01'));
        $end = (clone $start)->modify('+1 year');
        break;
    case 'overall':
        break;
}

$labels = [
    'daily' => 'Daily Report',
    'weekly' => 'Weekly Report',
    'monthly' => 'Monthly Report',
    'yearly' => 'Yearly Report',
    'overall' => 'Overall Report'
];

$rows = [];
$title = '';

if ($section === 'lost') {
    $title = 'Lost Items Report';
    $sql = "
        SELECT l.report_id, l.item_title, l.date_lost, l.status,
               c.category_name, u.full_name AS reporter_name
        FROM lost_items l
        LEFT JOIN categories c ON l.category_id = c.category_id
        LEFT JOIN users u ON l.reporter_id = u.user_id
    ";
    $params = [];
    if ($start && $end) {
        $sql .= " WHERE l.date_lost >= ? AND l.date_lost < ? ";
        $params = [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
    $sql .= " ORDER BY l.date_lost DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($section === 'found') {
    $title = 'Found Items Report';
    $sql = "
        SELECT f.item_id, f.item_code, f.title, f.date_found, f.status,
               c.category_name, u.full_name AS reporter_name
        FROM found_items f
        LEFT JOIN categories c ON f.category_id = c.category_id
        LEFT JOIN users u ON f.reporter_id = u.user_id
    ";
    $params = [];
    if ($start && $end) {
        $sql .= " WHERE f.date_found >= ? AND f.date_found < ? ";
        $params = [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
    $sql .= " ORDER BY f.date_found DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    $title = 'Claimed Items Report';
    $sql = "
        SELECT cl.claim_id, cl.date_processed, f.item_code, f.title,
               c.category_name, u.full_name AS claimant_name
        FROM claims cl
        INNER JOIN found_items f ON cl.found_item_id = f.item_id
        LEFT JOIN categories c ON f.category_id = c.category_id
        LEFT JOIN users u ON cl.claimant_id = u.user_id
        WHERE cl.status = 'Approved'
    ";
    $params = [];
    if ($start && $end) {
        $sql .= " AND cl.date_processed >= ? AND cl.date_processed < ? ";
        $params = [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
    $sql .= " ORDER BY cl.date_processed DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$periodLabel = $labels[$period];
$generatedAt = date('d M Y, h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif}
.page{max-width:1100px;margin:0 auto;padding:28px}
.toolbar{margin-bottom:20px}
.toolbar button{padding:9px 16px;border:1px solid #222;border-radius:6px;background:#222;color:#fff;cursor:pointer}
.header{border-bottom:2px solid #111;padding-bottom:14px;margin-bottom:18px}
.header h1{margin:0 0 5px;font-size:24px}
.header p{margin:3px 0;color:#555;font-size:13px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #aaa;padding:8px 10px;text-align:left;font-size:12px}
th{background:#f0f0f0}
.empty{text-align:center;padding:45px;color:#666}
.footer{margin-top:18px;font-size:11px;color:#666}
@media print{
  @page{size:A4 portrait;margin:12mm}
  .no-print{display:none!important}
  .page{max-width:none;padding:0}
  th,td{font-size:10.5px}
}
</style>
</head>
<body>
<div class="page">
    <div class="toolbar no-print">
        <button onclick="window.print()">🖨 Print Report</button>
        <button onclick="window.close()" style="background:#fff;color:#222;margin-left:6px">Close</button>
    </div>

    <div class="header">
        <h1>Resort Lost &amp; Found Management System</h1>
        <p><strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <p>Period: <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Generated: <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>

    <?php if (!$rows): ?>
        <div class="empty">No records found for this reporting period.</div>
    <?php elseif ($section === 'lost'): ?>
        <table>
            <thead><tr>
                <th>#</th><th>Lost Item</th><th>Category</th>
                <th>Reported By</th><th>Date Lost</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $row): ?>
                <tr>
                    <td><?= $i + 1; ?></td>
                    <td><strong><?= htmlspecialchars($row['item_title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><?= htmlspecialchars($row['category_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($row['reporter_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= date('d M Y', strtotime($row['date_lost'])); ?></td>
                    <td><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($section === 'found'): ?>
        <table>
            <thead><tr>
                <th>#</th><th>Item Code</th><th>Found Item</th><th>Category</th>
                <th>Reported By</th><th>Date Found</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $row): ?>
                <tr>
                    <td><?= $i + 1; ?></td>
                    <td><?= htmlspecialchars($row['item_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><strong><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><?= htmlspecialchars($row['category_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($row['reporter_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= date('d M Y', strtotime($row['date_found'])); ?></td>
                    <td><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table>
            <thead><tr>
                <th>#</th><th>Item Code</th><th>Claimed Item</th><th>Category</th>
                <th>Claimant</th><th>Date Claimed</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $row): ?>
                <tr>
                    <td><?= $i + 1; ?></td>
                    <td><?= htmlspecialchars($row['item_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><strong><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><?= htmlspecialchars($row['category_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($row['claimant_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= !empty($row['date_processed']) ? date('d M Y, h:i A', strtotime($row['date_processed'])) : 'N/A'; ?></td>
                    <td>Approved / Claimed</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        Resort Lost &amp; Found Management System • Administrative Report
    </div>
</div>

<script>
window.addEventListener('load', function(){
    setTimeout(function(){ window.print(); }, 400);
});
</script>
</body>
</html>
