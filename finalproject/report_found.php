<?php
// report_found.php
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
    <title>Report Found Item - Resort L&F</title>
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
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
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
            <div class="form-header text-white p-4">
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-hand-holding-heart me-2"></i>Report a Found Item</h4>
                <p class="small text-white opacity-75 mb-0">Found something on resort grounds? Submit details so our staff can check it into storage.</p>
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

                <form action="process_found_report.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <!-- Item Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label fw-semibold small">Item Title / Summary <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               placeholder="e.g., Sony Wireless Headphones, Ray-Ban Sunglasses" required>
                        <div class="invalid-feedback">Please enter a title for the found item.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Category -->
                        <div class="col-md-6">
                            <label for="category_id" class="form-label fw-semibold small">Item Classification <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="" selected disabled>-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id']; ?>">
                                        <?= htmlspecialchars($cat['category_name']); ?> (<?= $cat['retention_months']; ?> Months Storage)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select an item classification.</div>
                        </div>

                        <!-- Date Found -->
                        <div class="col-md-6">
                            <label for="date_found" class="form-label fw-semibold small">Date Found <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_found" name="date_found" 
                                   value="<?= date('Y-m-d'); ?>" max="<?= date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">Please select a valid date.</div>
                        </div>
                    </div>

                    <!-- Location Found -->
                    <div class="mb-3">
                        <label for="location_found" class="form-label fw-semibold small">Specific Location Found <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-location-dot text-muted"></i></span>
                            <input type="text" class="form-control" id="location_found" name="location_found" 
                                   placeholder="e.g., Lobby Restroom, Tennis Court Bench" required>
                        </div>
                        <div class="invalid-feedback">Please state where the item was found.</div>
                    </div>

                    <!-- Optional Photo Upload -->
                    <div class="mb-3">
                        <label for="item_image" class="form-label fw-semibold small">Item Photo (Optional but Recommended)</label>
                        <input type="file" class="form-control" id="item_image" name="item_image" accept="image/png, image/jpeg, image/webp">
                        <div class="form-text small">Accepted formats: JPG, PNG, WEBP (Max 5MB).</div>
                    </div>

                    <!-- Remarks -->
                    <div class="mb-4">
                        <label for="remarks" class="form-label fw-semibold small">Condition & Additional Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                  placeholder="Mention condition (e.g., scratched screen, wet, found in pouch)..."></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                        <i class="fa-solid fa-circle-check me-2"></i>Log Found Item
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