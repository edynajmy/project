<?php

require_once 'db.php';

/*
|--------------------------------------------------------------------------
| REAL-TIME PUBLIC STATISTICS
|--------------------------------------------------------------------------
*/

if (isset($_GET['ajax']) && $_GET['ajax'] === 'report_stats') {

    header('Content-Type: application/json; charset=utf-8');

    try {

        /*
        |--------------------------------------------------------------------------
        | TOTAL LOST
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM lost_items
        ");

        $totalLost = (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | TOTAL FOUND
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM found_items
        ");

        $totalFound = (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | SUCCESSFULLY CLAIMED
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM claims
            WHERE status = 'Approved'
        ");

        $totalClaimed = (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | CURRENTLY IN STORAGE
        |--------------------------------------------------------------------------
        |
        | Stored and Claim Pending items are still physically in storage.
        |
        */

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM found_items
            WHERE status IN ('Stored', 'Claim Pending')
        ");

        $currentlyStored = (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | LAST 12 MONTHS
        |--------------------------------------------------------------------------
        */

        $labels = [];
        $lostData = [];
        $foundData = [];
        $claimedData = [];


        for ($i = 11; $i >= 0; $i--) {

            $monthStartDate = new DateTime(
                'first day of -' . $i . ' months'
            );

            $monthStart = $monthStartDate->format('Y-m-01');

            $monthEndDate = clone $monthStartDate;

            $monthEndDate->modify('+1 month');

            $monthEnd = $monthEndDate->format('Y-m-01');

            $labels[] = $monthStartDate->format('M Y');


            /*
            |--------------------------------------------------------------------------
            | LOST ITEMS BY MONTH
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM lost_items
                WHERE date_lost >= ?
                AND date_lost < ?
            ");

            $stmt->execute([
                $monthStart,
                $monthEnd
            ]);

            $lostData[] = (int)$stmt->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | FOUND ITEMS BY MONTH
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM found_items
                WHERE date_found >= ?
                AND date_found < ?
            ");

            $stmt->execute([
                $monthStart,
                $monthEnd
            ]);

            $foundData[] = (int)$stmt->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | CLAIMED ITEMS BY MONTH
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM claims
                WHERE status = 'Approved'
                AND date_processed >= ?
                AND date_processed < ?
            ");

            $stmt->execute([
                $monthStart,
                $monthEnd
            ]);

            $claimedData[] = (int)$stmt->fetchColumn();
        }


        /*
        |--------------------------------------------------------------------------
        | SEND JSON
        |--------------------------------------------------------------------------
        */

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

            'updated_at' => date('Y-m-d H:i:s'),
            'server_time' => time()
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Resort Lost & Found Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        .hero-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 60px 0;
        }
        .qr-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
        }
        .step-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #e7f1ff;
            color: #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
        }
        .chart-container {
            position: relative;
            min-height: 320px;
            width: 100%;
        }
    
        /* ==========================
           CUSTOMER SERVICE CHATBOT
           ========================== */
        .customer-service-btn {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 1050;
            width: 58px;
            height: 58px;
            border: 0;
            border-radius: 50%;
            background: #0d6efd;
            color: #fff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .customer-service-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);
        }

        .customer-service-panel {
            position: fixed;
            right: 24px;
            bottom: 94px;
            z-index: 1051;
            width: 360px;
            max-width: calc(100vw - 32px);
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
            border: 1px solid rgba(13, 110, 253, 0.12);
            display: none;
        }

        .customer-service-panel.show {
            display: block;
            animation: customerServiceOpen .18s ease-out;
        }

        @keyframes customerServiceOpen {
            from {
                opacity: 0;
                transform: translateY(10px) scale(.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .customer-service-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: #fff;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .customer-service-header .service-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .18);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .customer-service-close {
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 18px;
            padding: 4px 6px;
            cursor: pointer;
            opacity: .9;
        }

        .customer-service-body {
            padding: 18px;
            max-height: 500px;
            overflow-y: auto;
        }

        .customer-service-welcome {
            background: #f1f6ff;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            color: #334155;
            font-size: 14px;
        }

        .customer-service-question {
            width: 100%;
            text-align: left;
            border: 1px solid #dee2e6;
            background: #fff;
            color: #212529;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 8px;
            font-size: 13px;
            line-height: 1.35;
            transition: all .15s ease;
        }

        .customer-service-question:hover {
            border-color: #0d6efd;
            background: #f1f6ff;
            color: #0a58ca;
        }

        .customer-service-answer {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 13px 14px;
            color: #343a40;
            font-size: 14px;
            line-height: 1.55;
            margin-bottom: 12px;
        }

        .customer-service-answer-title {
            font-weight: 700;
            color: #0d6efd;
            margin-bottom: 6px;
        }

        .customer-service-back {
            width: 100%;
            border: 1px solid #0d6efd;
            background: #fff;
            color: #0d6efd;
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .customer-service-back:hover {
            background: #0d6efd;
            color: #fff;
        }

        @media (max-width: 576px) {
            .customer-service-btn {
                right: 16px;
                bottom: 16px;
            }

            .customer-service-panel {
                right: 16px;
                bottom: 84px;
                width: calc(100vw - 32px);
            }
        }

    
        /* Highlight the current front-page navigation section. */
        .main-nav .nav-link.active-section {
            font-weight: 700 !important;
            color: #ffffff !important;
        }

        .main-nav .nav-link.active-section::after {
            content: "";
            display: block;
            height: 2px;
            width: 100%;
            margin-top: 4px;
            background: #ffffff;
            border-radius: 2px;
        }

    
        /* Extra separation between Guidelines and Statistics. */
        .statistics-section-spaced {
            padding-top: 120px !important;
        }

        @media (max-width: 768px) {
            .statistics-section-spaced {
                padding-top: 90px !important;
            }
        }

    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top main-nav">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Resort L&F
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link active" href="#home" id="nav-home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about" id="nav-about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#guidelines" id="nav-guidelines">Guidelines</a></li>
                    <li class="nav-item"><a class="nav-link" href="#statistics" id="nav-statistics">Statistics</a></li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-outline-light btn-sm w-100 px-3" href="login.php">Login / Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- HERO & QUICK REPORT SECTION (WITH QR CODE) -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-7 text-center text-lg-start">
                    <span class="badge bg-warning text-dark mb-2 px-3 py-2 fw-semibold">Fast & Secure Recovery</span>
                    <h1 class="display-5 fw-bold mb-3">Misplaced Something at the Resort?</h1>
                    <p class="lead mb-4 opacity-90">Report lost belongings or register found items instantly. Our automated system helps return items safely to their rightful owners.</p>
                    
                    <!-- ACTION BUTTONS -->
                    <div class="d-grid gap-3 d-sm-flex justify-content-sm-center justify-content-lg-start">
                        <a href="login_required.php?redirect=report_lost.php" class="btn btn-warning">
                            <i class="fa-solid fa-magnifying-glass me-2"></i>
                            I Lost an Item
                        </a>

                        <a href="login_required.php?redirect=report_found.php" class="btn btn-light">
                            <i class="fa-solid fa-hand-holding-heart me-2"></i>
                            I Found an Item
                        </a>
                    </div>
                </div>

                <!-- QR CODE BOX -->
                <div class="col-lg-5">
                    <div class="qr-card p-4 text-center text-white">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-qrcode me-2"></i>Instant Mobile Access</h5>
                        <p class="small opacity-75 mb-3">Scan this QR code with your mobile camera to quickly open this report portal on the go.</p>
                        <div class="bg-white p-3 d-inline-block rounded-3 shadow-sm mb-3">
                            <!-- Dynamically generated QR Code using API -->
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=https://your-resort-domain.com" 
                                 alt="Scan to Report" class="img-fluid" width="160" height="160">
                        </div>
                        <div class="small fw-semibold opacity-90">Scan to Report Directly</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section id="about" class="py-5 bg-light">
        <div class="container py-3">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <div class="p-3">
                        <span class="text-primary fw-bold text-uppercase small">Purpose & Innovation</span>
                        <h2 class="fw-bold mb-3">About the Resort Lost & Found System</h2>
                        <p class="text-secondary">The Resort Lost & Found Management System was created to eliminate traditional paper logbooks and manual guest inquiries across resort premises.</p>
                        <p class="text-secondary">By connecting guests (Reporters) directly with facility staff (Recorders), the portal automates storage tracking, category-based retention management (6-month and 8-month rules), and provides total transparency through real-time item status tracking.</p>
                        <div class="row g-3 mt-2">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fa-solid fa-shield-halved text-primary fs-3 me-3"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0">Secure Handling</h6>
                                        <small class="text-muted">Verified claims only</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fa-solid fa-clock-rotate-left text-primary fs-3 me-3"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0">Retention Alerts</h6>
                                        <small class="text-muted">Automated expiry</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-layer-group text-primary me-2"></i>Item Retention Policies</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <strong class="d-block">Small Size Items</strong>
                                    <small class="text-muted">Keys, cards, accessories, glasses, etc</small>
                                </div>
                                <span class="badge bg-info text-dark rounded-pill">6 Month Period</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <strong class="d-block">Medium & Big Size Items</strong>
                                    <small class="text-muted">Clothing, backpacks, luggage, footwear, etc</small>
                                </div>
                                <span class="badge bg-info text-dark rounded-pill">6 Month Period</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <strong class="d-block">Valuable Items</strong>
                                    <small class="text-muted">Smartphones, laptops, jewelry, wallets, etc</small>
                                </div>
                                <span class="badge bg-warning text-dark rounded-pill">8 Month Period</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- INFO & GUIDELINES (USER MANUAL) -->
    <section id="guidelines" class="py-5">
        <div class="container py-3">
            <div class="text-center max-width-700 mx-auto mb-5">
                <span class="text-primary fw-bold text-uppercase small">Simple Process</span>
                <h2 class="fw-bold">How It Works: User Guidelines</h2>
                <p class="text-secondary">Follow these simple steps depending on your interaction with the system.</p>
            </div>

            <div class="row g-4">
                <!-- Step 1 -->
                <div class="col-md-4 text-center">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4">
                        <div class="step-icon">1</div>
                        <h5 class="fw-bold mb-2">Submit a Report</h5>
                        <p class="text-secondary small mb-0">Select <strong>"I Lost an Item"</strong> or <strong>"I Found an Item"</strong>. Provide basic details (Name, Phone, Email, Password). No private information needed.</p>
                    </div>
                </div>
                <!-- Step 2 -->
                <div class="col-md-4 text-center">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4">
                        <div class="step-icon">2</div>
                        <h5 class="fw-bold mb-2">Recorder Verification</h5>
                        <p class="text-secondary small mb-0">Resort <strong>Recorders</strong> check in physical items into secure storage lockers and verify claim requests against submitted lost reports.</p>
                    </div>
                </div>
                <!-- Step 3 -->
                <div class="col-md-4 text-center">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4">
                        <div class="step-icon">3</div>
                        <h5 class="fw-bold mb-2">Track & Reclaim</h5>
                        <p class="text-secondary small mb-0">Use your unique Reference ID to check recovery status. Present verification to staff at the resort front office to collect your item.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<!-- PUBLIC STATISTICAL CHART SECTION -->
<section id="statistics" class="py-5 bg-light statistics-section-spaced">
    <div class="container py-3">

        <!-- HEADER -->
        <div class="text-center mb-4">

            <span class="text-primary fw-bold text-uppercase small">
                Public Transparency
            </span>

            <h2 class="fw-bold">
                Lost, Found & Claimed Record Statistics
            </h2>

            <p class="text-secondary">
                Real-time overview of lost, found and successfully claimed items.
            </p>

            <!-- REAL-TIME STATUS -->
            <div class="mt-2">
                <span class="badge bg-success">
                    <i class="fa-solid fa-circle me-1"></i>
                    Live Data
                </span>

                <small
                    id="reportLastUpdated"
                    class="text-muted ms-2">
                    Updating...
                </small>
            </div>

        </div>


        <!-- CHART + SUMMARY -->
        <div class="row g-4 align-items-stretch">

            <!-- CHART -->
            <div class="col-lg-8">

                <div class="card border-0 shadow-sm p-4 rounded-4 h-100">

                    <div
                        class="chart-container"
                        style="height: 360px;">

                        <canvas id="publicStatsChart"></canvas>

                    </div>

                </div>

            </div>


            <!-- SUMMARY CARDS -->
            <div class="col-lg-4">

                <div class="row g-3 h-100">

                    <!-- TOTAL LOST -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 border-start border-primary border-4">

                            <span class="text-muted small">
                                Total Lost Items
                            </span>

                            <h3
                                id="totalLostItems"
                                class="fw-bold mb-0 text-primary">
                                0
                            </h3>

                        </div>
                    </div>


                    <!-- TOTAL FOUND -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 border-start border-success border-4">

                            <span class="text-muted small">
                                Total Found Items
                            </span>

                            <h3
                                id="totalFoundItems"
                                class="fw-bold mb-0 text-success">
                                0
                            </h3>

                        </div>
                    </div>


                    <!-- SUCCESSFULLY CLAIMED -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 border-start border-warning border-4">

                            <span class="text-muted small">
                                Successfully Claimed
                            </span>

                            <h3
                                id="totalClaimedItems"
                                class="fw-bold mb-0 text-warning">
                                0
                            </h3>

                        </div>
                    </div>


                    <!-- CURRENTLY IN STORAGE -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 border-start border-info border-4">

                            <span class="text-muted small">
                                Currently in Storage
                            </span>

                            <h3
                                id="currentlyStoredItems"
                                class="fw-bold mb-0 text-info">
                                0
                            </h3>

                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>
</section>


    <!-- CUSTOMER SERVICE CHATBOT -->
    <button
        type="button"
        id="customerServiceButton"
        class="customer-service-btn"
        aria-label="Open Customer Service"
        title="Customer Service">
        <i class="fa-solid fa-headset"></i>
    </button>

    <div
        id="customerServicePanel"
        class="customer-service-panel"
        role="dialog"
        aria-label="Resort Lost & Found Customer Service"
        aria-hidden="true">

        <div class="customer-service-header">
            <div class="d-flex align-items-center gap-2">
                <div class="service-icon">
                    <i class="fa-solid fa-headset"></i>
                </div>
                <div>
                    <div class="fw-bold">Customer Service</div>
                    <small class="opacity-75">Resort Lost &amp; Found</small>
                </div>
            </div>

            <button
                type="button"
                id="customerServiceClose"
                class="customer-service-close"
                aria-label="Close Customer Service">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="customer-service-body">
            <div id="customerServiceContent">
                <div class="customer-service-welcome">
                    <strong>Hi! How can we help?</strong><br>
                    Please select one of the questions below.
                    You cannot type a message in this chat.
                </div>

                <div id="customerServiceQuestions">
                    <button type="button" class="customer-service-question" data-question="lost">
                        <i class="fa-solid fa-magnifying-glass me-2 text-primary"></i>
                        How do I report a lost item?
                    </button>

                    <button type="button" class="customer-service-question" data-question="found">
                        <i class="fa-solid fa-hand-holding-heart me-2 text-primary"></i>
                        How do I report a found item?
                    </button>

                    <button type="button" class="customer-service-question" data-question="status">
                        <i class="fa-solid fa-list-check me-2 text-primary"></i>
                        How do I check my report status?
                    </button>

                    <button type="button" class="customer-service-question" data-question="claim">
                        <i class="fa-solid fa-file-circle-check me-2 text-primary"></i>
                        How do I claim a found item?
                    </button>

                    <button type="button" class="customer-service-question" data-question="bring-found">
                        <i class="fa-solid fa-location-dot me-2 text-primary"></i>
                        Where do I bring a found item?
                    </button>

                    <button type="button" class="customer-service-question" data-question="claim-process">
                        <i class="fa-solid fa-clock me-2 text-primary"></i>
                        What happens after I submit a claim?
                    </button>

                    <button type="button" class="customer-service-question" data-question="counter">
                        <i class="fa-solid fa-building me-2 text-primary"></i>
                        Where is the Lost &amp; Found Counter?
                    </button>

                    <button type="button" class="customer-service-question" data-question="staff">
                        <i class="fa-solid fa-user-tie me-2 text-primary"></i>
                        How can I contact resort staff?
                    </button>
                </div>

                <div id="customerServiceAnswerView" style="display:none;">
                    <div class="customer-service-answer">
                        <div id="customerServiceAnswerTitle" class="customer-service-answer-title"></div>
                        <div id="customerServiceAnswerText"></div>
                    </div>

                    <button type="button" id="customerServiceBack" class="customer-service-back">
                        <i class="fa-solid fa-arrow-left me-1"></i>
                        Back to Questions
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p class="mb-1 opacity-75">&copy; 2026 Resort Lost & Found Management System (RLFMS). All rights reserved.</p>
            <small class="opacity-50">Designed for Resort Facility Operations & Guest Recovery</small>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

document.addEventListener('DOMContentLoaded', function () {

    let publicChart = null;


    async function updateStatistics() {

        try {

            /*
             * Use the CURRENT page URL.
             *
             * This prevents problems if the file is renamed
             * from index(5).php to index.php.
             */
            const url =
                window.location.pathname +
                '?ajax=report_stats&_=' +
                Date.now();


            const response = await fetch(url, {
                method: 'GET',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });


            if (!response.ok) {

                throw new Error(
                    'HTTP Error: ' + response.status
                );

            }


            const data = await response.json();


            if (!data.success) {

                throw new Error(
                    data.message ||
                    'Statistics API returned an error.'
                );

            }


            /*
             * ==========================
             * UPDATE SUMMARY CARDS
             * ==========================
             */

            const lostElement =
                document.getElementById('totalLostItems');

            if (lostElement) {

                lostElement.textContent =
                    Number(data.totals.lost || 0).toLocaleString();

            }


            const foundElement =
                document.getElementById('totalFoundItems');

            if (foundElement) {

                foundElement.textContent =
                    Number(data.totals.found || 0).toLocaleString();

            }


            const claimedElement =
                document.getElementById('totalClaimedItems');

            if (claimedElement) {

                claimedElement.textContent =
                    Number(data.totals.claimed || 0).toLocaleString();

            }


            const storedElement =
                document.getElementById('currentlyStoredItems');

            if (storedElement) {

                storedElement.textContent =
                    Number(data.totals.stored || 0).toLocaleString();

            }


            /*
             * ==========================
             * UPDATE CHART
             * ==========================
             */

            const canvas =
                document.getElementById('publicStatsChart');


            if (!canvas) {

                throw new Error(
                    'publicStatsChart canvas not found.'
                );

            }


            const ctx =
                canvas.getContext('2d');


            /*
             * Destroy old chart before creating
             * a new real-time chart.
             */

            if (publicChart) {

                publicChart.destroy();

            }


            publicChart = new Chart(ctx, {

                type: 'bar',

                data: {

                    labels: data.labels,

                    datasets: [

                        {
                            label: 'Lost Items',

                            data: data.lost,

                            backgroundColor:
                                'rgba(13, 110, 253, 0.70)',

                            borderColor:
                                '#0d6efd',

                            borderWidth: 1,

                            borderRadius: 4
                        },


                        {
                            label: 'Found Items',

                            data: data.found,

                            backgroundColor:
                                'rgba(25, 135, 84, 0.70)',

                            borderColor:
                                '#198754',

                            borderWidth: 1,

                            borderRadius: 4
                        },


                        {
                            label: 'Claimed Items',

                            data: data.claimed,

                            backgroundColor:
                                'rgba(255, 193, 7, 0.80)',

                            borderColor:
                                '#ffc107',

                            borderWidth: 1,

                            borderRadius: 4
                        }

                    ]

                },


                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    animation: {
                        duration: 500
                    },


                    plugins: {

                        legend: {
                            position: 'top'
                        },


                        title: {

                            display: true,

                            text:
                                'Lost, Found & Claimed Items - Last 12 Months'

                        },


                        tooltip: {

                            mode: 'index',

                            intersect: false

                        }

                    },


                    scales: {

                        y: {

                            beginAtZero: true,

                            ticks: {

                                precision: 0

                            },

                            title: {

                                display: true,

                                text: 'Number of Items'

                            }

                        },


                        x: {

                            title: {

                                display: true,

                                text: 'Month'

                            }

                        }

                    }

                }

            });


            /*
             * ==========================
             * REAL-TIME TIME
             * ==========================
             */

            const timeElement =
                document.getElementById(
                    'reportLastUpdated'
                );


            if (timeElement) {

                const now =
                    new Date();

                timeElement.textContent =
                    'Last updated: ' +
                    now.toLocaleTimeString();

            }


        } catch (error) {

            console.error(
                'Real-time statistics error:',
                error
            );


            const timeElement =
                document.getElementById(
                    'reportLastUpdated'
                );


            if (timeElement) {

                timeElement.textContent =
                    'Unable to update data';

            }

        }

    }


    /*
     * ==========================
     * FIRST LOAD
     * ==========================
     */

    updateStatistics();


    /*
     * ==========================
     * REAL-TIME AUTO REFRESH
     * ==========================
     *
     * Checks database every 10 seconds.
     *
     * New lost item    -> updates
     * New found item   -> updates
     * Approved claim   -> updates
     * Storage change   -> updates
     *
     */

    setInterval(
        updateStatistics,
        10000
    );


    /*
     * ==========================
     * CUSTOMER SERVICE CHATBOT
     * ==========================
     *
     * This chatbot intentionally has NO text input.
     * Users must choose one of the predefined questions.
     */
    const customerServiceButton =
        document.getElementById('customerServiceButton');

    const customerServicePanel =
        document.getElementById('customerServicePanel');

    const customerServiceClose =
        document.getElementById('customerServiceClose');

    const customerServiceQuestions =
        document.getElementById('customerServiceQuestions');

    const customerServiceAnswerView =
        document.getElementById('customerServiceAnswerView');

    const customerServiceAnswerTitle =
        document.getElementById('customerServiceAnswerTitle');

    const customerServiceAnswerText =
        document.getElementById('customerServiceAnswerText');

    const customerServiceBack =
        document.getElementById('customerServiceBack');

    const customerServiceAnswers = {
        lost: {
            title: 'How do I report a lost item?',
            text: 'Click "I Lost an Item" on the front page and log in or register an account. Complete the required lost item information and submit the report. You can then track the report status through your account.'
        },
        found: {
            title: 'How do I report a found item?',
            text: 'Click "I Found an Item" on the front page and log in or register an account. Enter the found item details and submit the report. After submitting, please bring the physical item to the Resort Lost & Found Counter / Front Desk.'
        },
        status: {
            title: 'How do I check my report status?',
            text: 'Log in to your Reporter account and open your dashboard. Your submitted lost and found reports will show their current processing status.'
        },
        claim: {
            title: 'How do I claim a found item?',
            text: 'A claim can be submitted when a possible match is available. Log in to your Reporter account, open the relevant matched item and submit the required claim information and proof notes. The Recorder will verify the claim.'
        },
        'bring-found': {
            title: 'Where do I bring a found item?',
            text: 'Please bring the physical found item to the Resort Lost & Found Counter / Front Desk. Do not leave the item unattended or place it in a storage locker yourself. Resort staff will check in the item and assign a secure storage location.'
        },
        'claim-process': {
            title: 'What happens after I submit a claim?',
            text: 'Your claim is sent to the Recorder for verification. The claim may be approved or rejected. If approved, the item will be prepared for collection. You will receive the relevant claim status information through the system and email notifications when configured.'
        },
        counter: {
            title: 'Where is the Lost & Found Counter?',
            text: 'For found items and collection of approved claims, please go to the Resort Lost & Found Counter / Front Desk. Resort staff will assist with check-in and item collection.'
        },
        staff: {
            title: 'How can I contact resort staff?',
            text: 'For assistance with matters not covered by the available questions, please approach the resort staff at the Front Desk or Resort Lost & Found Counter for further assistance. Please note that this customer service chat only provides assistance for the predefined questions listed here.'
        }
    };

    function openCustomerService() {
        customerServicePanel.classList.add('show');
        customerServicePanel.setAttribute('aria-hidden', 'false');
        customerServiceButton.setAttribute('aria-expanded', 'true');
    }

    function closeCustomerService() {
        customerServicePanel.classList.remove('show');
        customerServicePanel.setAttribute('aria-hidden', 'true');
        customerServiceButton.setAttribute('aria-expanded', 'false');
    }

    function showCustomerServiceQuestions() {
        customerServiceQuestions.style.display = 'block';
        customerServiceAnswerView.style.display = 'none';
    }

    function showCustomerServiceAnswer(questionKey) {
        const answer = customerServiceAnswers[questionKey];

        if (!answer) {
            return;
        }

        customerServiceAnswerTitle.textContent = answer.title;
        customerServiceAnswerText.textContent = answer.text;

        customerServiceQuestions.style.display = 'none';
        customerServiceAnswerView.style.display = 'block';
    }

    if (customerServiceButton) {
        customerServiceButton.addEventListener('click', function () {
            if (customerServicePanel.classList.contains('show')) {
                closeCustomerService();
            } else {
                openCustomerService();
            }
        });
    }

    if (customerServiceClose) {
        customerServiceClose.addEventListener('click', closeCustomerService);
    }

    if (customerServiceQuestions) {
        customerServiceQuestions.addEventListener('click', function (event) {
            const questionButton =
                event.target.closest('[data-question]');

            if (!questionButton) {
                return;
            }

            showCustomerServiceAnswer(
                questionButton.getAttribute('data-question')
            );
        });
    }

    if (customerServiceBack) {
        customerServiceBack.addEventListener('click', showCustomerServiceQuestions);
    }


    /* ==========================
       ACTIVE FRONT-PAGE NAVIGATION
       ========================== */
    const mainNavLinks = Array.from(
        document.querySelectorAll('.main-nav .nav-link[href^="#"]')
    );

    const mainNavSections = mainNavLinks
        .map(function (link) {
            const targetId = link.getAttribute('href').substring(1);
            const target = document.getElementById(targetId);
            return target ? { link: link, target: target } : null;
        })
        .filter(Boolean);

    function setActiveNav(sectionId) {
        mainNavLinks.forEach(function (link) {
            link.classList.remove('active-section');
        });

        const active = mainNavSections.find(function (item) {
            return item.target.id === sectionId;
        });

        if (active) {
            active.link.classList.add('active-section');
        }
    }

    /*
     * The active item is based on the section nearest the top of the
     * visible page. This avoids the previous IntersectionObserver issue
     * where Guidelines could remain highlighted while Statistics was open.
     */
    function updateActiveNavOnScroll() {
        if (!mainNavSections.length) {
            return;
        }

        const nav = document.querySelector('.main-nav');
        const navHeight = nav ? nav.offsetHeight : 60;
        const marker = window.scrollY + navHeight + 80;

        let currentSection = mainNavSections[0].target.id;

        mainNavSections.forEach(function (item) {
            const sectionTop = item.target.getBoundingClientRect().top + window.scrollY;

            if (sectionTop <= marker) {
                currentSection = item.target.id;
            }
        });

        setActiveNav(currentSection);
    }

    mainNavLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            const targetId = link.getAttribute('href').substring(1);

            /* Highlight immediately when the user clicks the menu item. */
            setActiveNav(targetId);
        });
    });

    let activeNavScrollTimer = null;
    window.addEventListener('scroll', function () {
        if (activeNavScrollTimer !== null) {
            return;
        }

        activeNavScrollTimer = window.requestAnimationFrame(function () {
            updateActiveNavOnScroll();
            activeNavScrollTimer = null;
        });
    }, { passive: true });

    window.addEventListener('resize', updateActiveNavOnScroll);

    /* Set the correct item when the page first loads. */
    updateActiveNavOnScroll();


});

</script>

</body>
</html>