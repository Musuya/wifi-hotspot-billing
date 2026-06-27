<?php
session_start();
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/helpers.php';

$packageId = (int)($_POST['package_id'] ?? 0);
$phone = $_POST['phone'] ?? '';

if (!$packageId || empty($phone)) {
    redirect('index.php');
}

$db = getDB();

// Get package details
$pkg = $db->prepare("SELECT * FROM packages WHERE id = ? AND is_active = 1");
$pkg->execute([$packageId]);
$package = $pkg->fetch();

if (!$package) {
    redirect('index.php');
}

$normalizedPhone = normalizePhone($phone);
if (!$normalizedPhone) {
    $error = "Invalid phone number format. Use 07XXXXXXXX or 2547XXXXXXXX";
}

$settings = getSettings();

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // In production, this would call M-Pesa STK Push API
    // For now, simulate a successful payment
    $transactionId = uniqid('TXN');

    $db->prepare("INSERT INTO transactions (phone_number, package_id, amount, status, mpesa_receipt_number, created_at) VALUES (?, ?, ?, 'completed', ?, NOW())")
        ->execute([$normalizedPhone, $packageId, $package['price'], $transactionId]);

    // Generate voucher code
    $voucherCode = generateVoucherCode(8);
    $db->prepare("INSERT INTO vouchers (code, package_id, status, created_at) VALUES (?, ?, 'unused', NOW())")
        ->execute([$voucherCode, $packageId]);

    $_SESSION['success'] = "Payment successful! Your voucher code is: $voucherCode";
    redirect('success.php?code=' . urlencode($voucherCode));
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Payment - <?= e($settings['business_name'] ?? 'WiFi Hotspot') ?></title>
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
    </style>
</head>
<body>
<div class="customer-page">
    <div class="customer-card">
        <div class="text-center mb-4">
            <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                <i class="bi bi-credit-card" style="font-size:1.75rem;color:white;"></i>
            </div>
            <h2 class="mb-1">Confirm Payment</h2>
            <p class="text-muted">Review your order before paying</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4" style="background:var(--bg-hover);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Package</span>
                    <span class="fw-semibold"><?= e($package['name']) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Duration</span>
                    <span class="fw-semibold"><?= e($package['duration_minutes'] ?? 'N/A') ?> minutes</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Phone</span>
                    <span class="fw-semibold"><?= e($normalizedPhone ?? $phone) ?></span>
                </div>
                <div class="border-top pt-2 mt-2" style="border-color:var(--border-color);">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Total</span>
                        <span class="fw-bold" style="font-size:1.25rem;color:var(--primary);">Ksh <?= number_format((float)$package['price']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="package_id" value="<?= $packageId ?>">
            <input type="hidden" name="phone" value="<?= e($phone) ?>">
            <button type="submit" name="confirm_payment" class="btn btn-primary w-100" style="padding:1rem;">
                <i class="bi bi-credit-card me-2"></i>Pay with M-Pesa
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none" style="color:var(--text-muted);">
                <i class="bi bi-arrow-left me-1"></i>Back to packages
            </a>
        </div>
    </div>
</div>

<script>
    (function() {
        const theme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
</body>
</html>