<?php
/**
 * index.php
 * This is the page MikroTik's Hotspot redirects customers to.
 *
 * In MikroTik Hotspot setup, you point the "login page" (or a link on it)
 * to this URL, passing along the MAC address and other params, e.g.:
 *   http://yourserver/index.php?mac=$(mac)&ip=$(ip)
 *
 * Customer flow:
 *   1. Connects to WiFi -> redirected here
 *   2. Picks a package
 *   3. Enters M-Pesa phone number -> STK push sent
 *   4. Pays -> system creates hotspot user -> customer is auto-logged into hotspot
 */

require_once __DIR__ . '/includes/helpers.php';

$db = getDB();
$packages = $db->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$settings = getSettings();

$mac = $_GET['mac'] ?? '';
$clientIp = $_GET['ip'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($settings['site_name'] ?? 'WiFi Hotspot') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <h1><?= htmlspecialchars($settings['site_name'] ?? 'WiFi Hotspot') ?></h1>
        <p>Choose a package to get connected</p>
    </div>

    <div class="packages-grid" id="packages">
        <?php foreach ($packages as $pkg): ?>
        <div class="package-card" data-id="<?= $pkg['id'] ?>" data-price="<?= $pkg['price'] ?>" data-name="<?= htmlspecialchars($pkg['name']) ?>">
            <h3><?= htmlspecialchars($pkg['name']) ?></h3>
            <p class="package-desc"><?= htmlspecialchars($pkg['description']) ?></p>
            <div class="package-price"><?= formatMoney($pkg['price']) ?></div>
            <button class="btn-select" onclick="selectPackage(this)">Select</button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Payment modal -->
    <div id="payModal" class="modal hidden">
        <div class="modal-content">
            <h2 id="selectedPackageName"></h2>
            <p id="selectedPackagePrice"></p>
            <label for="phoneInput">M-Pesa Phone Number</label>
            <input type="tel" id="phoneInput" placeholder="07XXXXXXXX" autocomplete="tel">
            <button class="btn-pay" id="payButton" onclick="initiatePayment()">Pay Now</button>
            <p id="payStatus"></p>
            <button class="btn-cancel" onclick="closeModal()">Cancel</button>
        </div>
    </div>

    <!-- Have a voucher already? -->
    <div class="voucher-box">
        <p>Already have a voucher?</p>
        <input type="text" id="voucherInput" placeholder="Enter voucher code">
        <button onclick="redeemVoucher()">Connect</button>
        <p id="voucherStatus"></p>
    </div>
</div>

<input type="hidden" id="clientMac" value="<?= htmlspecialchars($mac) ?>">
<input type="hidden" id="clientIp" value="<?= htmlspecialchars($clientIp) ?>">

<script src="assets/js/app.js"></script>
</body>
</html>
