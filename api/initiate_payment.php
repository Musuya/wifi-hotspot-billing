<?php
/**
 * api/initiate_payment.php
 * Called via AJAX when customer clicks "Pay Now".
 * Creates a `pending` transaction record and triggers an STK push.
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../mpesa/Mpesa.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$packageId = (int)($input['package_id'] ?? 0);
$phoneRaw  = $input['phone'] ?? '';
$mac       = $input['mac'] ?? '';

$phone = normalizePhone($phoneRaw);
if (!$phone) {
    jsonResponse(['success' => false, 'message' => 'Invalid phone number format'], 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM packages WHERE id = ? AND is_active = 1");
$stmt->execute([$packageId]);
$package = $stmt->fetch();

if (!$package) {
    jsonResponse(['success' => false, 'message' => 'Package not found'], 404);
}

// Create a pending transaction record first
$insert = $db->prepare(
    "INSERT INTO transactions (package_id, phone_number, amount, mac_address, status)
     VALUES (?, ?, ?, ?, 'pending')"
);
$insert->execute([$packageId, $phone, $package['price'], $mac]);
$transactionId = $db->lastInsertId();

// Build callback URL - MUST be a publicly reachable HTTPS URL in production
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$callbackUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/api/mpesa_callback.php';

$settings = getSettings();
$mpesa = new Mpesa($settings, $callbackUrl);

$result = $mpesa->stkPush(
    $phone,
    (float)$package['price'],
    'WIFI-' . $transactionId,
    $package['name']
);

if ($result['success']) {
    $upd = $db->prepare("UPDATE transactions SET mpesa_checkout_request_id = ? WHERE id = ?");
    $upd->execute([$result['checkout_request_id'], $transactionId]);

    jsonResponse([
        'success' => true,
        'message' => $result['message'],
        'transaction_id' => $transactionId,
    ]);
} else {
    $upd = $db->prepare("UPDATE transactions SET status = 'failed' WHERE id = ?");
    $upd->execute([$transactionId]);

    jsonResponse(['success' => false, 'message' => $result['message']], 500);
}
