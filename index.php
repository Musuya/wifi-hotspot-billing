<?php
// Show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ... rest of your index.php code
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/helpers.php';
$db = getDB();

$packages = $db->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC")->fetchAll();
$settings = $db->query("SELECT * FROM settings LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($settings['business_name'] ?? 'WiFi Hotspot') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .customer-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .customer-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2.5rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        [data-theme="dark"] .customer-card {
            background: rgba(30,41,59,0.95);
        }
        .package-option {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .package-option:hover, .package-option.selected {
            border-color: var(--primary);
            background: rgba(99,102,241,0.05);
        }
        .package-option.selected {
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
    </style>
</head>
<body>
    <div class="customer-page">
        <div class="customer-card">
            <div class="text-center mb-4">
                <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                    <i class="bi bi-wifi" style="font-size:1.75rem;color:white;"></i>
                </div>
                <h2 class="mb-1"><?= e($settings['business_name'] ?? 'WiFi Hotspot') ?></h2>
                <p class="text-muted">Choose a package to get connected</p>
            </div>

            <form action="pay.php" method="POST" id="packageForm">
                <div class="mb-4">
                    <label class="form-label">Select Package</label>
                    <?php foreach ($packages as $pkg): ?>
                    <div class="package-option" onclick="selectPackage(this, <?= $pkg['id'] ?>)">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><?= e($pkg['name']) ?></div>
                                <small class="text-muted"><?= e($pkg['duration_minutes'] ?? 'N/A') ?> min • <?= e($pkg['rate_limit'] ?? 'Unlimited') ?></small>
                            </div>
                            <div class="fw-bold" style="font-size:1.25rem;color:var(--primary);">Ksh <?= number_format($pkg['price']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <input type="hidden" name="package_id" id="selectedPackage" required>
                </div>

                <div class="form-group">
                    <label class="form-label">M-Pesa Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text" style="background:var(--bg-hover);border-color:var(--border-color);"><i class="bi bi-phone"></i></span>
                        <input type="tel" name="phone" class="form-control" placeholder="2547XXXXXXXX" pattern="2547[0-9]{8}" required>
                    </div>
                    <small class="text-muted">Format: 2547XXXXXXXX</small>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-4" style="padding:1rem;">
                    <i class="bi bi-credit-card me-2"></i>Pay with M-Pesa
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="voucher-redeem.php" class="text-decoration-none" style="color:var(--primary);">
                    <i class="bi bi-ticket-perforated me-1"></i>Have a voucher code?
                </a>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        function selectPackage(el, id) {
            document.querySelectorAll('.package-option').forEach(p => p.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('selectedPackage').value = id;
        }
    </script>
</body>
</html>
