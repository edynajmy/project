<?php
// admin_reports.php

session_start();

date_default_timezone_set('Asia/Kuala_Lumpur');

require_once 'db.php';
require_once 'auth_check.php';

/* =========================================================
   REAL-TIME REPORT DATA API
   ========================================================= */

if (isset($_GET['ajax']) && $_GET['ajax'] === 'report_stats') {

    header('Content-Type: application/json; charset=utf-8');

    try {

        /*
         * We show the last 12 months.
         * The current month is included automatically.
         */
        $months = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = new DateTime("first day of -$i months");

            $months[] = [
                'key'   => $date->format('Y-m'),
                'label' => $date->format('M Y')
            ];
        }

        $labels = [];
        $lostData = [];
        $foundData = [];
        $claimedData = [];

        foreach ($months as $month) {

            $labels[] = $month['label'];

            $startDate = $month['key'] . '-01';

            $start = new DateTime($startDate);

            $end = clone $start;
            $end->modify('+1 month');

            $endDate = $end->format('Y-m-d');

            /* =========================
               LOST ITEMS
               ========================= */
            $stmtLost = $pdo->prepare("
                SELECT COUNT(*) 
                FROM lost_items
                WHERE date_lost >= ?
                  AND date_lost < ?
            ");

            $stmtLost->execute([
                $startDate,
                $endDate
            ]);

            $lostData[] = (int)$stmtLost->fetchColumn();


            /* =========================
               FOUND ITEMS
               ========================= */
            $stmtFound = $pdo->prepare("
                SELECT COUNT(*)
                FROM found_items
                WHERE date_found >= ?
                  AND date_found < ?
            ");

            $stmtFound->execute([
                $startDate,
                $endDate
            ]);

            $foundData[] = (int)$stmtFound->fetchColumn();


            /* =========================
               CLAIMED ITEMS
               =========================
               Only APPROVED claims count
               as successfully claimed.
            */
            $stmtClaimed = $pdo->prepare("
                SELECT COUNT(*)
                FROM claims
                WHERE status = 'Approved'
                  AND date_processed >= ?
                  AND date_processed < ?
            ");

            $stmtClaimed->execute([
                $startDate,
                $endDate
            ]);

            $claimedData[] = (int)$stmtClaimed->fetchColumn();
        }


        /* =========================
           TOTALS
           ========================= */

        $totalLost = (int)$pdo->query("
            SELECT COUNT(*)
            FROM lost_items
        ")->fetchColumn();

        $totalFound = (int)$pdo->query("
            SELECT COUNT(*)
            FROM found_items
        ")->fetchColumn();

        $totalClaimed = (int)$pdo->query("
            SELECT COUNT(*)
            FROM claims
            WHERE status = 'Approved'
        ")->fetchColumn();

        $currentlyStored = (int)$pdo->query("
            SELECT COUNT(*)
            FROM found_items
            WHERE status IN ('Stored', 'Claim Pending')
        ")->fetchColumn();


        echo json_encode([
            'success' => true,

            'labels' => $labels,

            'lost' => $lostData,
            'found' => $foundData,
            'claimed' => $claimedData,

            'totals' => [
                'lost' => $totalLost,
                'found' => $totalFound,
                'claimed' => $totalClaimed,
                'stored' => $currentlyStored
            ],

            'updated_at' => date('Y-m-d H:i:s')
        ]);

    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

// Admin only
checkAccess(['Admin']);

$admin_name = $_SESSION['user_name'] ?? 'Administrator';

/*
|--------------------------------------------------------------------------
| REAL-TIME CLOCK
|--------------------------------------------------------------------------
*/

$current_date_time = date('d M Y, h:i:s A');

/*
|--------------------------------------------------------------------------
| CURRENT DATE / PERIODS
|--------------------------------------------------------------------------
*/

$today = new DateTime('today');

$today_start = $today->format('Y-m-d');
$tomorrow = (clone $today)->modify('+1 day')->format('Y-m-d');

$week_start = (clone $today)->modify('monday this week');
$week_end = (clone $week_start)->modify('+7 days');

$month_start = $today->format('Y-m-01');
$month_end = (clone $today)->modify('first day of next month');

$year_start = $today->format('Y-01-01');
$year_end = (clone $today)->modify('+1 year')->format('Y-01-01');


/*
|--------------------------------------------------------------------------
| REPORT QUERY FUNCTION
|--------------------------------------------------------------------------
|
| Lost:
|   lost_items.date_lost
|
| Found:
|   found_items.date_found
|
| Claimed:
|   claims.date_processed
|   Only APPROVED claims are counted.
|
*/

function getReportCounts(PDO $pdo, string $start, string $end): array
{
    // Lost items
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM lost_items
        WHERE date_lost >= ?
          AND date_lost < ?
    ");

    $stmt->execute([$start, $end]);
    $lost = (int)$stmt->fetchColumn();


    // Found items
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM found_items
        WHERE date_found >= ?
          AND date_found < ?
    ");

    $stmt->execute([$start, $end]);
    $found = (int)$stmt->fetchColumn();


    // Claimed items
    // Only approved claims represent successfully completed claims.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM claims
        WHERE status = 'Approved'
          AND date_processed >= ?
          AND date_processed < ?
    ");

    $stmt->execute([$start, $end]);
    $claimed = (int)$stmt->fetchColumn();


    return [
        'lost' => $lost,
        'found' => $found,
        'claimed' => $claimed
    ];
}


/*
|--------------------------------------------------------------------------
| GENERATE REAL-TIME REPORTS
|--------------------------------------------------------------------------
*/

$daily = getReportCounts(
    $pdo,
    $today_start,
    $tomorrow
);

$weekly = getReportCounts(
    $pdo,
    $week_start->format('Y-m-d'),
    $week_end->format('Y-m-d')
);

$monthly = getReportCounts(
    $pdo,
    $month_start,
    $month_end->format('Y-m-d')
);

$yearly = getReportCounts(
    $pdo,
    $year_start,
    $year_end
);


/*
|--------------------------------------------------------------------------
| OVERALL SYSTEM TOTALS
|--------------------------------------------------------------------------
*/

$total_lost = (int)$pdo->query("
    SELECT COUNT(*)
    FROM lost_items
")->fetchColumn();

$total_found = (int)$pdo->query("
    SELECT COUNT(*)
    FROM found_items
")->fetchColumn();

$total_claimed = (int)$pdo->query("
    SELECT COUNT(*)
    FROM claims
    WHERE status = 'Approved'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| REAL-TIME ITEM LISTS
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| LOST ITEMS
|--------------------------------------------------------------------------
*/

$lost_items_report = $pdo->query("
    SELECT
        l.report_id,
        l.item_title,
        l.date_lost,
        l.status,
        c.category_name,
        u.full_name AS reporter_name
    FROM lost_items l
    LEFT JOIN categories c
        ON l.category_id = c.category_id
    LEFT JOIN users u
        ON l.reporter_id = u.user_id
    ORDER BY l.date_lost DESC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| FOUND ITEMS
|--------------------------------------------------------------------------
*/

$found_items_report = $pdo->query("
    SELECT
        f.item_id,
        f.item_code,
        f.title,
        f.date_found,
        f.status,
        c.category_name,
        u.full_name AS reporter_name
    FROM found_items f
    LEFT JOIN categories c
        ON f.category_id = c.category_id
    LEFT JOIN users u
        ON f.reporter_id = u.user_id
    ORDER BY f.date_found DESC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| CLAIMED ITEMS
|--------------------------------------------------------------------------
*/

$claimed_items_report = $pdo->query("
    SELECT
        cl.claim_id,
        cl.found_item_id,
        cl.date_processed,
        cl.status AS claim_status,
        f.item_code,
        f.title,
        c.category_name,
        u.full_name AS claimant_name
    FROM claims cl
    INNER JOIN found_items f
        ON cl.found_item_id = f.item_id
    LEFT JOIN categories c
        ON f.category_id = c.category_id
    LEFT JOIN users u
        ON cl.claimant_id = u.user_id
    WHERE cl.status = 'Approved'
    ORDER BY cl.date_processed DESC
")->fetchAll();

/*
|--------------------------------------------------------------------------
| REPORT DATE LABELS
|--------------------------------------------------------------------------
*/

$daily_label = $today->format('d M Y');

$weekly_label =
    $week_start->format('d M Y') .
    ' - ' .
    $week_end->modify('-1 day')->format('d M Y');

$monthly_label = $today->format('F Y');

$yearly_label = $today->format('Y');

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Administrative Reports - Resort Lost & Found
    </title>


    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        rel="stylesheet"
    >


    <style>

        body {
            background: #f4f6f9;
            color: #212529;
        }

        .report-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .report-header {
            background: #0d6efd;
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .summary-card {
            border-radius: 12px;
            border: none;
        }

        .summary-number {
            font-size: 2rem;
            font-weight: 700;
        }

        /* Admin report table filters */
        .report-filter-bar{
            background:#f8fafc;
            border:1px solid #e5eaf0;
            border-radius:12px;
            padding:12px;
            margin-bottom:16px;
        }
        .report-filter-bar .form-control,
        .report-filter-bar .form-select{
            min-height:42px;
            border-color:#dce3eb;
            font-size:.85rem;
        }
        .report-filter-bar .filter-label{
            font-size:.72rem;
            font-weight:700;
            color:#667085;
            margin-bottom:5px;
        }
        .filter-result-count{
            font-size:.75rem;
            color:#667085;
            font-weight:600;
        }
        @media print {
            body.printing-filtered-report > * {
                display: none !important;
            }
            body.printing-filtered-report #report-section-lost,
            body.printing-filtered-report #report-section-found,
            body.printing-filtered-report #report-section-claimed {
                display: none !important;
            }
            body.printing-filtered-report[data-print-section="lost"] #report-section-lost,
            body.printing-filtered-report[data-print-section="found"] #report-section-found,
            body.printing-filtered-report[data-print-section="claimed"] #report-section-claimed {
                display: block !important;
            }
            body.printing-filtered-report .no-print {
                display: none !important;
            }
            body.printing-filtered-report .report-filter-no-match {
                display: none !important;
            }
            body.printing-filtered-report .report-card {
                box-shadow: none !important;
                border: 0 !important;
            }
        }

        .report-filter-bar .filter-date{
            min-height:42px;
            border-color:#dce3eb;
            font-size:.85rem;
        }
        .report-filter-print .dropdown-menu{
            min-width:210px;
        }
        .report-filter-print .dropdown-item{
            padding:.55rem .85rem;
        }
        .report-filter-print .dropdown-item.current-filter{
            font-weight:700;
        }

        .filter-empty-row td{
            padding:30px 12px!important;
            color:#6c757d;
            text-align:center;
        }

        .report-table th,
        .report-table td {
            vertical-align: middle;
        }

        .report-title {
            font-weight: 700;
        }

        .generated-time {
            font-size: 0.85rem;
            color: #6c757d;
        }


        /*
        |--------------------------------------------------------------------------
        | PRINT STYLING
        |--------------------------------------------------------------------------
        */

        @media print {

            @page {
                size: A4 portrait;
                margin: 12mm;
            }

            body {
                background: white !important;
                color: black !important;
            }

            .no-print {
                display: none !important;
            }

            .report-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }

            .report-header {
                background: white !important;
                color: black !important;
                border-bottom: 2px solid black;
            }

            .table {
                font-size: 12px;
            }

            .summary-card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }

            .page-break {
                page-break-before: always;
            }

            .report-section {
                break-inside: avoid;
            }

            @media print {

            body {
                background: white !important;
            }

            .navbar,
            .no-print {
                display: none !important;
            }

            .report-card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }

            .report-section {
                page-break-inside: avoid;
            }

            table {
                width: 100% !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            .badge {
                border: 1px solid #999 !important;
                color: black !important;
                background: white !important;
            }

        }

        }

    </style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar navbar-dark bg-dark no-print">

    <div class="container-fluid px-4">

        <a
            href="admin_dashboard.php"
            class="navbar-brand fw-bold"
        >

            <i class="fa-solid fa-user-shield text-primary me-2"></i>

            Admin Control Center

        </a>


        <div class="d-flex align-items-center gap-2">

            <span class="text-white small">

                <i class="fa-solid fa-user-gear text-warning me-1"></i>

                <?= htmlspecialchars(
                    $admin_name,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>

                (Admin)

            </span>


            <a
                href="admin_dashboard.php"
                class="btn btn-outline-light btn-sm"
            >

                <i class="fa-solid fa-arrow-left me-1"></i>

                Dashboard

            </a>
</div>

    </div>

</nav>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="container-fluid px-4 py-4">


    <!-- REPORT HEADER -->

    <div class="card report-card mb-4">

        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-start">

                <div>

                    <h2 class="report-title mb-1">

                        <i class="fa-solid fa-chart-column text-primary me-2"></i>

                        Resort Lost & Found Management System

                    </h2>

                    <h4 class="mb-2">

                        Administrative Summary Report

                    </h4>

                    <div class="text-muted">

                        Lost Items • Found Items • Claimed Items

                    </div>

                </div>


                <div class="text-end">

                    <div class="generated-time">

                        Report generated:

                    </div>

                    <strong>

                        <span id="liveDateTime">
                            <?= htmlspecialchars(
                                $current_date_time,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                    </strong>

                    <div class="small text-muted mt-1">

                        Real-time database report

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         OVERALL TOTALS
    ====================================================== -->

    <div class="row g-3 mb-4">


        <!-- LOST -->

        <div class="col-md-4">

            <div class="card summary-card bg-warning text-dark shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-uppercase small fw-bold">

                                Total Lost Items

                            </div>

                            <div class="summary-number">

                                <?= $total_lost; ?>

                            </div>

                        </div>

                        <i class="fa-solid fa-magnifying-glass fs-1 opacity-50"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- FOUND -->

        <div class="col-md-4">

            <div class="card summary-card bg-primary text-white shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-uppercase small fw-bold">

                                Total Found Items

                            </div>

                            <div class="summary-number">

                                <?= $total_found; ?>

                            </div>

                        </div>

                        <i class="fa-solid fa-hand-holding-heart fs-1 opacity-50"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- CLAIMED -->

        <div class="col-md-4">

            <div class="card summary-card bg-success text-white shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-uppercase small fw-bold">

                                Total Claimed Items

                            </div>

                            <div class="summary-number">

                                <?= $total_claimed; ?>

                            </div>

                        </div>

                        <i class="fa-solid fa-circle-check fs-1 opacity-50"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         PERIOD SUMMARY
    ====================================================== -->

    <div class="card report-card report-section">

        <div class="card-header report-header p-3">

            <h5 class="mb-0 fw-bold">

                <i class="fa-solid fa-chart-line me-2"></i>

                Lost & Found Activity Summary

            </h5>

        </div>


        <div class="card-body p-4">


            <div class="table-responsive">

                <table class="table table-bordered table-hover report-table">

                    <thead class="table-light">

                        <tr>

                            <th style="width:20%;">

                                Reporting Period

                            </th>

                            <th>

                                Date / Period

                            </th>

                            <th class="text-center">

                                Lost Items

                            </th>

                            <th class="text-center">

                                Found Items

                            </th>

                            <th class="text-center">

                                Claimed Items

                            </th>

                            <th class="text-center">

                                Total Activity

                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <!-- DAILY -->

                        <tr>

                            <td>

                                <strong>

                                    <i class="fa-solid fa-calendar-day text-primary me-2"></i>

                                    Daily

                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $daily_label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-warning text-dark fs-6">

                                    <?= $daily['lost']; ?>

                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-primary fs-6">

                                    <?= $daily['found']; ?>

                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-success fs-6">

                                    <?= $daily['claimed']; ?>

                                </span>

                            </td>

                            <td class="text-center fw-bold">

                                <?= $daily['lost']
                                    + $daily['found']
                                    + $daily['claimed']; ?>

                            </td>

                        </tr>


                        <!-- WEEKLY -->

                        <tr>

                            <td>

                                <strong>

                                    <i class="fa-solid fa-calendar-week text-primary me-2"></i>

                                    Weekly

                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $weekly_label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-warning text-dark fs-6">

                                    <?= $weekly['lost']; ?>

                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-primary fs-6">

                                    <?= $weekly['found']; ?>

                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-success fs-6">

                                    <?= $weekly['claimed']; ?>

                                </span>

                            </td>

                            <td class="text-center fw-bold">

                                <?= $weekly['lost']
                                    + $weekly['found']
                                    + $weekly['claimed']; ?>

                            </td>

                        </tr>


                        <!-- MONTHLY -->

                        <tr>

                            <td>

                                <strong>

                                    <i class="fa-solid fa-calendar-days text-primary me-2"></i>

                                    Monthly

                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $monthly_label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-warning text-dark fs-6">

                                    <?= $monthly['lost']; ?>

                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-primary fs-6">

                                    <?= $monthly['found']; ?>

                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-success fs-6">

                                    <?= $monthly['claimed']; ?>

                                </span>

                            </td>

                            <td class="text-center fw-bold">

                                <?= $monthly['lost']
                                    + $monthly['found']
                                    + $monthly['claimed']; ?>

                            </td>

                        </tr>


                        <!-- YEARLY -->

                        <tr>

                            <td>

                                <strong>

                                    <i class="fa-solid fa-calendar text-primary me-2"></i>

                                    Yearly

                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $yearly_label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-warning text-dark fs-6">

                                    <?= $yearly['lost']; ?>

                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-primary fs-6">

                                    <?= $yearly['found']; ?>

                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-success fs-6">

                                    <?= $yearly['claimed']; ?>

                                </span>

                            </td>

                            <td class="text-center fw-bold">

                                <?= $yearly['lost']
                                    + $yearly['found']
                                    + $yearly['claimed']; ?>

                            </td>

                        </tr>


                    </tbody>

                </table>

            </div>


            <!-- LEGEND -->

            <div class="mt-3 small text-muted">

                <span class="badge bg-warning text-dark me-2">
                    Lost
                </span>

                <span class="badge bg-primary me-2">
                    Found
                </span>

                <span class="badge bg-success">
                    Claimed
                </span>

                <div class="mt-2">

                    <i class="fa-solid fa-circle-info me-1"></i>

                    Claimed Items are counted when a claim is
                    <strong>Approved</strong> and processed.

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         REPORT SUMMARY CARDS
    ====================================================== -->

    <div class="row g-3 mt-4 report-section">


        <!-- DAILY -->

        <div class="col-lg-3 col-md-6">

            <div class="card report-card h-100">

                <div class="card-body">

                    <h6 class="fw-bold text-primary">

                        <i class="fa-solid fa-calendar-day me-2"></i>

                        Today

                    </h6>

                    <div class="small text-muted mb-3">

                        <?= htmlspecialchars(
                            $daily_label,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Lost</span>

                        <strong>
                            <?= $daily['lost']; ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Found</span>

                        <strong>
                            <?= $daily['found']; ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Claimed</span>

                        <strong>
                            <?= $daily['claimed']; ?>
                        </strong>

                    </div>

                </div>

            </div>

        </div>


        <!-- WEEKLY -->

        <div class="col-lg-3 col-md-6">

            <div class="card report-card h-100">

                <div class="card-body">

                    <h6 class="fw-bold text-primary">

                        <i class="fa-solid fa-calendar-week me-2"></i>

                        This Week

                    </h6>

                    <div class="small text-muted mb-3">

                        <?= htmlspecialchars(
                            $weekly_label,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Lost</span>

                        <strong>
                            <?= $weekly['lost']; ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Found</span>

                        <strong>
                            <?= $weekly['found']; ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Claimed</span>

                        <strong>
                            <?= $weekly['claimed']; ?>
                        </strong>

                    </div>

                </div>

            </div>

        </div>


        <!-- MONTHLY -->

        <div class="col-lg-3 col-md-6">

            <div class="card report-card h-100">

                <div class="card-body">

                    <h6 class="fw-bold text-primary">

                        <i class="fa-solid fa-calendar-days me-2"></i>

                        This Month

                    </h6>

                    <div class="small text-muted mb-3">

                        <?= htmlspecialchars(
                            $monthly_label,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Lost</span>

                        <strong>
                            <?= $monthly['lost']; ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Found</span>

                        <strong>
                            <?= $monthly['found']; ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Claimed</span>

                        <strong>
                            <?= $monthly['claimed']; ?>
                        </strong>

                    </div>

                </div>

            </div>

        </div>


        <!-- YEARLY -->

        <div class="col-lg-3 col-md-6">

            <div class="card report-card h-100">

                <div class="card-body">

                    <h6 class="fw-bold text-primary">

                        <i class="fa-solid fa-calendar me-2"></i>

                        This Year

                    </h6>

                    <div class="small text-muted mb-3">

                        <?= htmlspecialchars(
                            $yearly_label,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Lost</span>

                        <strong>
                            <?= $yearly['lost']; ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Found</span>

                        <strong>
                            <?= $yearly['found']; ?>
                        </strong>

                    </div>

                    <div class="d-flex justify-content-between">

                        <span>Claimed</span>

                        <strong>
                            <?= $yearly['claimed']; ?>
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- =====================================================
     LOST ITEMS TRACKING
===================================================== -->

<div class="card report-card mt-4 report-section">

    <div class="card-header bg-warning text-dark p-3">

        <div class="d-flex justify-content-between align-items-center">

            <h5 class="mb-0 fw-bold">

                <i class="fa-solid fa-magnifying-glass me-2"></i>

                Lost Items

            </h5>

            <span class="badge bg-dark">

                <?= count($lost_items_report); ?> Items

            </span>

        </div>

    </div>


    <div class="card-body p-4">

        <?php if (empty($lost_items_report)): ?>

            <div class="text-center py-4 text-muted">

                <i class="fa-solid fa-circle-check fs-2 mb-2"></i>

                <div>

                    No lost item records found.

                </div>

            </div>

        <?php else: ?>

                        <div class="report-filter-bar no-print" data-filter-group="lost">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="filter-label"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</div>
                        <input type="text" class="form-control report-filter-search"
                               placeholder="Search item, reporter, category..." autocomplete="off">
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Category</div>
                        <select class="form-select report-filter-category">
                            <option value="">All Categories</option>
                            <option value="Small Size Item">Small Size Item</option>
                            <option value="Medium Size Item">Medium Size Item</option>
                            <option value="Big Size Item">Big Size Item</option>
                            <option value="Valuable Item">Valuable Item</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Status</div>
                        <select class="form-select report-filter-status">
                            <option value="">All Statuses</option>
                            <option value="Searching">Searching</option>
                            <option value="Matched">Matched</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Date</div>
                        <input type="date" class="form-control filter-date report-filter-date">
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Period</div>
                        <select class="form-select report-filter-period">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                            <option value="overall">Overall</option>
                        </select>
                    </div>

                    <div class="col-xl-1 col-lg-1 col-md-6">
                        <button type="button" class="btn btn-outline-secondary w-100 report-filter-reset">
                            <i class="fa-solid fa-rotate-left me-1"></i>Reset
                        </button>
                    </div>

                    <div class="col-xl-1 col-lg-1 col-md-6">
                        <div class="dropdown report-filter-print w-100">
                            <button type="button"
                                    class="btn btn-dark dropdown-toggle fw-semibold w-100"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                <i class="fa-solid fa-print me-1"></i>Print
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li>
                                    <button type="button" class="dropdown-item current-filter report-print-current">
                                        <i class="fa-solid fa-print me-2"></i>Print Current Filter
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Lost Items Report</h6></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=lost&period=daily" target="_blank">
                                    <i class="fa-solid fa-calendar-day me-2"></i>Daily Report
                                </a></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=lost&period=weekly" target="_blank">
                                    <i class="fa-solid fa-calendar-week me-2"></i>Weekly Report
                                </a></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=lost&period=monthly" target="_blank">
                                    <i class="fa-solid fa-calendar-days me-2"></i>Monthly Report
                                </a></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=lost&period=yearly" target="_blank">
                                    <i class="fa-solid fa-calendar me-2"></i>Yearly Report
                                </a></li>
                                <li><a class="dropdown-item fw-semibold" href="admin_report_print.php?section=lost&period=overall" target="_blank">
                                    <i class="fa-solid fa-chart-column me-2"></i>Overall Report
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mt-2 filter-result-count">
                    Showing <span class="filter-visible-count">0</span> of <span class="filter-total-count">0</span> items
                </div>
            </div>

            <div class="table-responsive">

                <table id="lostReportTable" class="table table-bordered table-hover report-table">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Lost Item</th>

                            <th>Category</th>

                            <th>Reported By</th>

                            <th>Date Lost</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody class="report-filter-body">

                        <?php foreach ($lost_items_report as $index => $item): ?>

                            <tr class="report-data-row"
                                data-category="<?= htmlspecialchars($item['category_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>"
                                data-status="<?= htmlspecialchars($item['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-date="<?= htmlspecialchars(substr($item['date_lost'], 0, 10), ENT_QUOTES, 'UTF-8'); ?>">

                                <td class="report-row-number">

                                    <?= $index + 1; ?>

                                </td>

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $item['item_title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </strong>

                                </td>

                                <td>

                                    <span class="badge bg-info text-dark">

                                        <?= htmlspecialchars(
                                            $item['category_name'] ?? 'N/A',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </span>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $item['reporter_name'] ?? 'Unknown',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </td>

                                <td>

                                    <?= date(
                                        'd M Y',
                                        strtotime($item['date_lost'])
                                    ); ?>

                                </td>

                                <td>

                                    <?php if ($item['status'] === 'Searching'): ?>

                                        <span class="badge bg-warning text-dark">

                                            Searching

                                        </span>

                                    <?php elseif ($item['status'] === 'Matched'): ?>

                                        <span class="badge bg-primary">

                                            Matched

                                        </span>

                                    <?php elseif ($item['status'] === 'Resolved'): ?>

                                        <span class="badge bg-success">

                                            Resolved

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            <?= htmlspecialchars(
                                                $item['status'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>                    <tbody class="report-filter-no-match" style="display:none;">
                        <tr class="filter-empty-row">
                            <td colspan="6">
                                <i class="fa-solid fa-filter-circle-xmark fs-4 mb-2"></i>
                                <div>No matching lost item records found.</div>
                            </td>
                        </tr>
                    </tbody>


                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<!-- =====================================================
     FOUND ITEMS TRACKING
===================================================== -->

<div class="card report-card mt-4 report-section">

    <div class="card-header bg-primary text-white p-3">

        <div class="d-flex justify-content-between align-items-center">

            <h5 class="mb-0 fw-bold">

                <i class="fa-solid fa-hand-holding-heart me-2"></i>

                Found Items

            </h5>

            <span class="badge bg-light text-primary">

                <?= count($found_items_report); ?> Items

            </span>

        </div>

    </div>


    <div class="card-body p-4">

        <?php if (empty($found_items_report)): ?>

            <div class="text-center py-4 text-muted">

                <i class="fa-solid fa-circle-check fs-2 mb-2"></i>

                <div>

                    No found item records found.

                </div>

            </div>

        <?php else: ?>

                        <div class="report-filter-bar no-print" data-filter-group="found">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="filter-label"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</div>
                        <input type="text" class="form-control report-filter-search"
                               placeholder="Search item, reporter, category..." autocomplete="off">
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Category</div>
                        <select class="form-select report-filter-category">
                            <option value="">All Categories</option>
                            <option value="Small Size Item">Small Size Item</option>
                            <option value="Medium Size Item">Medium Size Item</option>
                            <option value="Big Size Item">Big Size Item</option>
                            <option value="Valuable Item">Valuable Item</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Status</div>
                        <select class="form-select report-filter-status">
                            <option value="">All Statuses</option>
                            <option value="Pending Check-in">Pending Check-in</option>
                            <option value="Stored">Stored</option>
                            <option value="Claim Pending">Claim Pending</option>
                            <option value="Claimed">Claimed</option>
                            <option value="Expired">Expired</option>
                            <option value="Disposed">Disposed</option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Date</div>
                        <input type="date" class="form-control filter-date report-filter-date">
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Period</div>
                        <select class="form-select report-filter-period">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                            <option value="overall">Overall</option>
                        </select>
                    </div>

                    <div class="col-xl-1 col-lg-1 col-md-6">
                        <button type="button" class="btn btn-outline-secondary w-100 report-filter-reset">
                            <i class="fa-solid fa-rotate-left me-1"></i>Reset
                        </button>
                    </div>

                    <div class="col-xl-1 col-lg-1 col-md-6">
                        <div class="dropdown report-filter-print w-100">
                            <button type="button"
                                    class="btn btn-dark dropdown-toggle fw-semibold w-100"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                <i class="fa-solid fa-print me-1"></i>Print
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li>
                                    <button type="button" class="dropdown-item current-filter report-print-current">
                                        <i class="fa-solid fa-print me-2"></i>Print Current Filter
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Found Items Report</h6></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=found&period=daily" target="_blank">
                                    <i class="fa-solid fa-calendar-day me-2"></i>Daily Report
                                </a></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=found&period=weekly" target="_blank">
                                    <i class="fa-solid fa-calendar-week me-2"></i>Weekly Report
                                </a></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=found&period=monthly" target="_blank">
                                    <i class="fa-solid fa-calendar-days me-2"></i>Monthly Report
                                </a></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=found&period=yearly" target="_blank">
                                    <i class="fa-solid fa-calendar me-2"></i>Yearly Report
                                </a></li>
                                <li><a class="dropdown-item fw-semibold" href="admin_report_print.php?section=found&period=overall" target="_blank">
                                    <i class="fa-solid fa-chart-column me-2"></i>Overall Report
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mt-2 filter-result-count">
                    Showing <span class="filter-visible-count">0</span> of <span class="filter-total-count">0</span> items
                </div>
            </div>

            <div class="table-responsive">

                <table id="foundReportTable" class="table table-bordered table-hover report-table">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Item Code</th>

                            <th>Found Item</th>

                            <th>Category</th>

                            <th>Reported By</th>

                            <th>Date Found</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody class="report-filter-body">

                        <?php foreach ($found_items_report as $index => $item): ?>

                            <tr class="report-data-row"
                                data-category="<?= htmlspecialchars($item['category_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>"
                                data-status="<?= htmlspecialchars($item['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-date="<?= htmlspecialchars(substr($item['date_found'], 0, 10), ENT_QUOTES, 'UTF-8'); ?>">

                                <td class="report-row-number">

                                    <?= $index + 1; ?>

                                </td>

                                <td>

                                    <span class="badge bg-secondary font-monospace">

                                        <?= htmlspecialchars(
                                            $item['item_code'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </span>

                                </td>

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $item['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </strong>

                                </td>

                                <td>

                                    <span class="badge bg-info text-dark">

                                        <?= htmlspecialchars(
                                            $item['category_name'] ?? 'N/A',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </span>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $item['reporter_name'] ?? 'Unknown',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </td>

                                <td>

                                    <?= date(
                                        'd M Y',
                                        strtotime($item['date_found'])
                                    ); ?>

                                </td>

                                <td>

                                    <?php if ($item['status'] === 'Stored'): ?>

                                        <span class="badge bg-success">

                                            Stored

                                        </span>

                                    <?php elseif ($item['status'] === 'Claim Pending'): ?>

                                        <span class="badge bg-warning text-dark">

                                            Claim Pending

                                        </span>

                                    <?php elseif ($item['status'] === 'Claimed'): ?>

                                        <span class="badge bg-primary">

                                            Claimed

                                        </span>

                                    <?php elseif ($item['status'] === 'Disposed'): ?>

                                        <span class="badge bg-danger">

                                            Disposed

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            <?= htmlspecialchars(
                                                $item['status'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>                    <tbody class="report-filter-no-match" style="display:none;">
                        <tr class="filter-empty-row">
                            <td colspan="6">
                                <i class="fa-solid fa-filter-circle-xmark fs-4 mb-2"></i>
                                <div>No matching found item records found.</div>
                            </td>
                        </tr>
                    </tbody>


                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<!-- =====================================================
     CLAIMED ITEMS TRACKING
===================================================== -->

<div class="card report-card mt-4 report-section">

    <div class="card-header bg-success text-white p-3">

        <div class="d-flex justify-content-between align-items-center">

            <h5 class="mb-0 fw-bold">

                <i class="fa-solid fa-circle-check me-2"></i>

                Claimed Items

            </h5>

            <span class="badge bg-light text-success">

                <?= count($claimed_items_report); ?> Items

            </span>

        </div>

    </div>


    <div class="card-body p-4">

        <?php if (empty($claimed_items_report)): ?>

            <div class="text-center py-4 text-muted">

                <i class="fa-solid fa-inbox fs-2 mb-2"></i>

                <div>

                    No claimed items found.

                </div>

            </div>

        <?php else: ?>

                        <div class="report-filter-bar no-print" data-filter-group="claimed">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="filter-label"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</div>
                        <input type="text" class="form-control report-filter-search"
                               placeholder="Search item, reporter, category..." autocomplete="off">
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Category</div>
                        <select class="form-select report-filter-category">
                            <option value="">All Categories</option>
                            <option value="Small Size Item">Small Size Item</option>
                            <option value="Medium Size Item">Medium Size Item</option>
                            <option value="Big Size Item">Big Size Item</option>
                            <option value="Valuable Item">Valuable Item</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Status</div>
                        <select class="form-select report-filter-status">
                            <option value="">All Statuses</option>
                            <option value="Approved / Claimed">Approved / Claimed</option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Date</div>
                        <input type="date" class="form-control filter-date report-filter-date">
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6">
                        <div class="filter-label">Period</div>
                        <select class="form-select report-filter-period">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                            <option value="overall">Overall</option>
                        </select>
                    </div>

                    <div class="col-xl-1 col-lg-1 col-md-6">
                        <button type="button" class="btn btn-outline-secondary w-100 report-filter-reset">
                            <i class="fa-solid fa-rotate-left me-1"></i>Reset
                        </button>
                    </div>

                    <div class="col-xl-1 col-lg-1 col-md-6">
                        <div class="dropdown report-filter-print w-100">
                            <button type="button"
                                    class="btn btn-dark dropdown-toggle fw-semibold w-100"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                <i class="fa-solid fa-print me-1"></i>Print
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li>
                                    <button type="button" class="dropdown-item current-filter report-print-current">
                                        <i class="fa-solid fa-print me-2"></i>Print Current Filter
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Claimed Items Report</h6></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=claimed&period=daily" target="_blank">
                                    <i class="fa-solid fa-calendar-day me-2"></i>Daily Report
                                </a></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=claimed&period=weekly" target="_blank">
                                    <i class="fa-solid fa-calendar-week me-2"></i>Weekly Report
                                </a></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=claimed&period=monthly" target="_blank">
                                    <i class="fa-solid fa-calendar-days me-2"></i>Monthly Report
                                </a></li>
                                <li><a class="dropdown-item" href="admin_report_print.php?section=claimed&period=yearly" target="_blank">
                                    <i class="fa-solid fa-calendar me-2"></i>Yearly Report
                                </a></li>
                                <li><a class="dropdown-item fw-semibold" href="admin_report_print.php?section=claimed&period=overall" target="_blank">
                                    <i class="fa-solid fa-chart-column me-2"></i>Overall Report
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mt-2 filter-result-count">
                    Showing <span class="filter-visible-count">0</span> of <span class="filter-total-count">0</span> items
                </div>
            </div>

            <div class="table-responsive">

                <table id="claimedReportTable" class="table table-bordered table-hover report-table">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>Item Code</th>

                            <th>Claimed Item</th>

                            <th>Category</th>

                            <th>Claimant</th>

                            <th>Date Claimed</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody class="report-filter-body">

                        <?php foreach ($claimed_items_report as $index => $item): ?>

                            <tr class="report-data-row"
                                data-category="<?= htmlspecialchars($item['category_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>"
                                data-status="Approved / Claimed"
                                data-date="<?= htmlspecialchars(substr($item['date_processed'], 0, 10), ENT_QUOTES, 'UTF-8'); ?>">

                                <td class="report-row-number">

                                    <?= $index + 1; ?>

                                </td>

                                <td>

                                    <span class="badge bg-secondary font-monospace">

                                        <?= htmlspecialchars(
                                            $item['item_code'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </span>

                                </td>

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $item['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </strong>

                                </td>

                                <td>

                                    <span class="badge bg-info text-dark">

                                        <?= htmlspecialchars(
                                            $item['category_name'] ?? 'N/A',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </span>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $item['claimant_name'] ?? 'Unknown',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </td>

                                <td>

                                    <?php if (!empty($item['date_processed'])): ?>

                                        <?= date(
                                            'd M Y, h:i A',
                                            strtotime($item['date_processed'])
                                        ); ?>

                                    <?php else: ?>

                                        N/A

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <span class="badge bg-success">

                                        Approved / Claimed

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>                    <tbody class="report-filter-no-match" style="display:none;">
                        <tr class="filter-empty-row">
                            <td colspan="7">
                                <i class="fa-solid fa-filter-circle-xmark fs-4 mb-2"></i>
                                <div>No matching claimed item records found.</div>
                            </td>
                        </tr>
                    </tbody>


                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <div class="text-center text-muted small mt-4">

        Resort Lost & Found Management System

        <br>

        Administrative Report • Generated in real time

    </div>


</div>


<script>
/*
|--------------------------------------------------------------------------
| ADMIN REPORT TABLE FILTERS
|--------------------------------------------------------------------------
*/

function toLocalDateString(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

function setupReportFilters() {
    document.querySelectorAll('.report-filter-bar').forEach(function(filterBar) {
        const group = filterBar.dataset.filterGroup;
        const table = document.getElementById(group + 'ReportTable');
        const section = document.getElementById('report-section-' + group);
        if (!table) return;

        const rows = Array.from(table.querySelectorAll('tbody.report-filter-body tr.report-data-row'));
        const searchInput = filterBar.querySelector('.report-filter-search');
        const categorySelect = filterBar.querySelector('.report-filter-category');
        const statusSelect = filterBar.querySelector('.report-filter-status');
        const dateInput = filterBar.querySelector('.report-filter-date');
        const periodSelect = filterBar.querySelector('.report-filter-period');
        const resetButton = filterBar.querySelector('.report-filter-reset');
        const printCurrentButton = filterBar.querySelector('.report-print-current');
        const visibleCount = filterBar.querySelector('.filter-visible-count');
        const totalCount = filterBar.querySelector('.filter-total-count');
        const noMatchBody = table.querySelector('tbody.report-filter-no-match');

        totalCount.textContent = rows.length;

        function applyFilters() {
            const search = (searchInput.value || '').trim().toLowerCase();
            const category = (categorySelect.value || '').toLowerCase();
            const status = (statusSelect.value || '').toLowerCase();
            const selectedDate = dateInput.value || '';
            const period = periodSelect.value || 'daily';
            let periodStart = '';
            let periodEnd = '';
            let shown = 0;

            if (selectedDate && period !== 'overall') {
                const base = new Date(selectedDate + 'T00:00:00');

                if (period === 'daily') {
                    periodStart = selectedDate;
                    periodEnd = selectedDate;
                } else if (period === 'weekly') {
                    const day = base.getDay();
                    const mondayOffset = day === 0 ? -6 : 1 - day;
                    const start = new Date(base);
                    start.setDate(base.getDate() + mondayOffset);
                    const end = new Date(start);
                    end.setDate(start.getDate() + 6);
                    periodStart = toLocalDateString(start);
                    periodEnd = toLocalDateString(end);
                } else if (period === 'monthly') {
                    const start = new Date(base.getFullYear(), base.getMonth(), 1);
                    const end = new Date(base.getFullYear(), base.getMonth() + 1, 0);
                    periodStart = toLocalDateString(start);
                    periodEnd = toLocalDateString(end);
                } else if (period === 'yearly') {
                    periodStart = base.getFullYear() + '-01-01';
                    periodEnd = base.getFullYear() + '-12-31';
                }
            }

            rows.forEach(function(row) {
                const rowText = row.textContent.toLowerCase();
                const rowCategory = (row.dataset.category || '').toLowerCase();
                const rowStatus = (row.dataset.status || '').toLowerCase();
                const rowDate = row.dataset.date || '';

                const matchesSearch = !search || rowText.includes(search);
                const matchesCategory = !category || rowCategory === category;
                const matchesStatus = !status || rowStatus === status;
                const matchesPeriod = period === 'overall' || !selectedDate ||
                    (rowDate >= periodStart && rowDate <= periodEnd);

                const show = matchesSearch &&
                             matchesCategory &&
                             matchesStatus &&
                             matchesPeriod;

                row.style.display = show ? '' : 'none';

                if (show) {
                    shown++;
                    const numberCell = row.querySelector('.report-row-number');
                    if (numberCell) numberCell.textContent = shown;
                }
            });

            visibleCount.textContent = shown;

            if (noMatchBody) {
                noMatchBody.style.display = shown === 0 && rows.length > 0 ? '' : 'none';
            }
        }

        searchInput.addEventListener('input', applyFilters);
        categorySelect.addEventListener('change', applyFilters);
        statusSelect.addEventListener('change', applyFilters);
        dateInput.addEventListener('change', applyFilters);
        periodSelect.addEventListener('change', applyFilters);

        resetButton.addEventListener('click', function() {
            searchInput.value = '';
            categorySelect.value = '';
            statusSelect.value = '';
            dateInput.value = '';
            periodSelect.value = 'daily';
            applyFilters();
        });

        printCurrentButton.addEventListener('click', function() {
            applyFilters();

            document.body.classList.add('printing-filtered-report');
            document.body.setAttribute('data-print-section', group);

            window.setTimeout(function() {
                window.print();

                window.setTimeout(function() {
                    document.body.classList.remove('printing-filtered-report');
                    document.body.removeAttribute('data-print-section');
                }, 300);
            }, 50);
        });

        applyFilters();
    });
}

document.addEventListener('DOMContentLoaded', setupReportFilters);




/*
|--------------------------------------------------------------------------
| REAL-TIME CLOCK
|--------------------------------------------------------------------------
*/

function updateRealTimeClock() {

    const now = new Date();

    const options = {
        timeZone: 'Asia/Kuala_Lumpur',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };

    const formatted = new Intl.DateTimeFormat(
        'en-MY',
        options
    ).format(now);

    const clock = document.getElementById('liveDateTime');

    if (clock) {
        clock.textContent = formatted;
    }
}

updateRealTimeClock();

setInterval(updateRealTimeClock, 1000);


/*
|--------------------------------------------------------------------------
| AUTO REFRESH REPORT EVERY 60 SECONDS
|--------------------------------------------------------------------------
*/

setInterval(function () {

    window.location.reload();

}, 60000);

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>