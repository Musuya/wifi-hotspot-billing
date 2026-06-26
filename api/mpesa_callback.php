<?php
/**
 * api/mpesa_callback.php
 *
 * Safaricom calls THIS URL automatically after the customer enters their
 * M-Pesa PIN (success or failure). This is the most important file in the
 * system: it's what actually marks the payment complete AND creates the
 * MikroTik hotspot user so the customer gets internet access.
 *
 * IMPORTANT: This URL must be publicly reachable over HTTPS for Safaricom
 * to call it. During development, use a tool like ngrok to expose your
 * local server, and register that ngrok URL in your Daraja app config.
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../mikrotik/HotspotManager.php';

// Always log the raw callback for debugging - M-Pesa callbacks can be finicky.
$raw = file_get_contents('php://input');
error_log('M-Pesa Callback: ' . $raw);

$data = json_decode($raw, true);
$stkCallback = $data['Body']['stkCallback'] ?? null;

if (!$stkCallback) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback format']);
    exit;
}

$checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
$resultCode = $stkCallback['ResultCode'] ?? 1;

$db = getDB();
$stmt = $db->prepare("SELECT * FROM transactions WHERE mpesa_checkout_request_id = ? LIMIT 1");
$stmt->execute([$checkoutRequestId]);
$transaction = $stmt->fetch();

if (!$transaction) {
    error_log('M-Pesa callback: no matching transaction for ' . $checkoutRequestId);
    http_response_code(200); // still ack so Safaricom doesn't retry forever
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

if ((int)$resultCode !== 0) {
    // Payment failed or was cancelled by the customer
    $upd = $db->prepare("UPDATE transactions SET status = 'failed' WHERE id = ?");
    $upd->execute([$transaction['id']]);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

// --- Payment succeeded ---
$callbackMetadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
$mpesaReceipt = null;
foreach ($callbackMetadata as $item) {
    if ($item['Name'] === 'MpesaReceiptNumber') {
        $mpesaReceipt = $item['Value'];
    }
}

$stmt = $db->prepare("SELECT * FROM packages WHERE id = ?");
$stmt->execute([$transaction['package_id']]);
$package = $stmt->fetch();

// Generate hotspot credentials - use phone number as username for easy lookup,
// and a short random password.
$username = $transaction['phone_number'];
$password = generateVoucherCode(6);

// Get the active router (in a multi-router setup, you'd pick based on
// which router sent the request - e.g. matched by mac_address/router_id)
$routerStmt = $db->query("SELECT * FROM routers WHERE is_active = 1 LIMIT 1");
$router = $routerStmt->fetch();

$mikrotikSuccess = false;
if ($router) {
    $hotspot = new HotspotManager($router);
    $mikrotikSuccess = $hotspot->createUser(
        $username,
        $password,
        (int)$package['duration_minutes'],
        $package['rate_limit'],
        $package['data_limit_mb']
    );
}

// Update transaction record
$upd = $db->prepare(
    "UPDATE transactions
     SET status = 'completed', mpesa_receipt_number = ?, completed_at = NOW()
     WHERE id = ?"
);
$upd->execute([$mpesaReceipt, $transaction['id']]);

// Log a session record regardless of MikroTik success, so it shows on the dashboard.
// If MikroTik creation failed, flag it so staff can manually fix/refund.
if ($router) {
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$package['duration_minutes']} minutes"));
    $sessionInsert = $db->prepare(
        "INSERT INTO sessions (transaction_id, hotspot_username, hotspot_password, router_id, mac_address, expires_at, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $sessionInsert->execute([
        $transaction['id'],
        $username,
        $password,
        $router['id'],
        $transaction['mac_address'],
        $expiresAt,
        $mikrotikSuccess ? 'active' : 'disconnected',
    ]);
}

if (!$mikrotikSuccess) {
    error_log("WARNING: Payment {$transaction['id']} completed but MikroTik user creation FAILED. Manual intervention needed.");
}

// Always acknowledge to Safaricom - if you don't return 200 with this shape,
// it will retry the callback repeatedly.
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
