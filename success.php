<?php
session_start();
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/helpers.php';

$code = $_GET['code'] ?? '';
$settings = getSettings();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - <?= e($settings['business_name'] ?? 'WiFi Hotspot') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .customer-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
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
            text-align: center;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2.5rem;
        }
        .voucher-code {
            background: var(--bg-hover);
            border: 2px dashed var(--primary);
            border-radius: 12px;
            padding: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: var(--primary);
            margin: 1rem 0;
        }
    </style>
</head>
<body>
<div class="customer-page">
    <div class="customer-card">
        <div class="success-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        <h2 class="mb-2">Payment Successful!</h2>
        <p class="text-muted mb-4">Your WiFi voucher is ready</p>

        <div class="voucher-code" id="voucherCode">
            <?= e($code) ?>
        </div>

        <p class="text-muted mb-4">Show this code to connect to WiFi</p>

        <button class="btn btn-primary w-100 mb-2" onclick="copyCode()" style="padding:0.875rem;">
            <i class="bi bi-clipboard me-2"></i>Copy Code
        </button>

        <a href="index.php" class="btn btn-secondary w-100" style="padding:0.875rem;">
            <i class="bi bi-arrow-left me-2"></i>Buy Another
        </a>
    </div>
</div>

<script>
    (function() {
        const theme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', theme);
    })();

    function copyCode() {
        const code = document.getElementById('voucherCode').textContent.trim();
        navigator.clipboard.writeText(code).then(() => {
            alert('Voucher code copied!');
        });
    }
</script>
</body>
</html>