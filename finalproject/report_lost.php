<?php
// report_lost.php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
checkAccess(['Reporter']);

// Fetch categories from DB for dynamic dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Lost Item - Resort L&F</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-card {
            max-width: 650px;
            margin: 40px auto;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .form-header {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border-radius: 12px 12px 0 0;
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Resort L&F
            </a>
            <a href="reporter_dashboard.php" class="btn btn-outline-light">
             <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>
    </nav>

    <div class="container py-3">
        <div class="card form-card">
            <!-- Header -->
            <div class="form-header text-dark p-4">
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-magnifying-glass me-2"></i>Report a Lost Item</h4>
                <p class="small text-dark opacity-75 mb-0">Fill in details about the item you misplaced to help staff locate it.</p>
            </div>

            <div class="card-body p-4">
                <!-- Session Alerts -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-1"></i>
                        <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="process_lost_report.php" method="POST" class="needs-validation" novalidate>
                    <!-- Item Title -->
                    <div class="mb-3">
                        <label for="item_title" class="form-label fw-semibold small">Item Title / Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="item_title" name="item_title" 
                               placeholder="e.g., Black Leather Wallet, iPhone 14 Pro" required>
                        <div class="invalid-feedback">Please enter the name of the lost item.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Category -->
                        <div class="col-md-6">
                            <label for="category_id" class="form-label fw-semibold small">Item Size / Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="" selected disabled>-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id']; ?>">
                                        <?= htmlspecialchars($cat['category_name']); ?> (<?= $cat['retention_months']; ?> Months)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category.</div>
                        </div>

                        <!-- Date Lost -->
                        <div class="col-md-6">
                            <label for="date_lost" class="form-label fw-semibold small">Date Lost <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_lost" name="date_lost" 
                                   max="<?= date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">Please select a valid date.</div>
                        </div>
                    </div>

                    <!-- Location Lost -->
                    <div class="mb-3">
                        <label for="location_lost" class="form-label fw-semibold small">Estimated Location Lost <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-location-dot text-muted"></i></span>
                            <input type="text" class="form-control" id="location_lost" name="location_lost" 
                                   placeholder="e.g., Main Pool Deck, Beach Cafe, Room 302" required>
                        </div>
                        <div class="invalid-feedback">Please specify where you think it was lost.</div>
                    </div>

                    <!-- Item Description -->
                    <div class="mb-4">
                        <label for="description" class="form-label fw-semibold small">Detailed Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Describe distinguishing marks, color, brand, stickers, or internal contents..." required></textarea>
                        <div class="invalid-feedback">Please enter a detailed description to assist identification.</div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow-sm">
                        <i class="fa-solid fa-paper-plane me-2"></i>Submit Lost Report
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Form Validation Script -->
    <script>
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>