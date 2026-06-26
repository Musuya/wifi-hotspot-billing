<?php
/**
 * api/redeem_voucher.php
 * For customers who bought a printed/cash voucher instead of paying via M-Pesa.
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../mikrotik/HotspotManager.php';

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');
$mac = $input['mac'] ?? '';

if (!$code) {
    jsonResponse(['success' => false, 'message' => 'Enter a voucher code'], 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM vouchers WHERE code = ? LIMIT 1");
$stmt->execute([strtoupper($code)]);
$voucher = $stmt->fetch();

if (!$voucher) {
    jsonResponse(['success' => false, 'message' => 'Invalid voucher code']);
}
if ($voucher['status'] !== 'unused') {
    jsonResponse(['success' => false, 'message' => 'This voucher has already been used']);
}

$pkgStmt = $db->prepare("SELECT * FROM packages WHERE id = ?");
$pkgStmt->execute([$voucher['package_id']]);
$package = $pkgStmt->fetch();

$routerStmt = $db->query("SELECT * FROM routers WHERE is_active = 1 LIMIT 1");
$router = $routerStmt->fetch();

if (!$router) {
    jsonResponse(['success' => false, 'message' => 'No active router configured. Contact support.']);
}

$username = $voucher['code'];
$password = $voucher['code'];

$hotspot = new HotspotManager($router);
$success = $hotspot->createUser(
    $username,
    $password,
    (int)$package['duration_minutes'],
    $package['rate_limit'],
    $package['data_limit_mb']
);

if (!$success) {
    jsonResponse(['success' => false, 'message' => 'Could not connect you right now. Please ask staff for help.']);
}

$expiresAt = date('Y-m-d H:i:s', strtotime("+{$package['duration_minutes']} minutes"));

$db->prepare("UPDATE vouchers SET status = 'used', used_at = NOW() WHERE id = ?")
   ->execute([$voucher['id']]);

$db->prepare(
    "INSERT INTO sessions (voucher_id, hotspot_username, hotspot_password, router_id, mac_address, expires_at, status)
     VALUES (?, ?, ?, ?, ?, ?, 'active')"
)->execute([$voucher['id'], $username, $password, $router['id'], $mac, $expiresAt]);

jsonResponse([
    'success' => true,
    'username' => $username,
    'password' => $password,
    'expires_at' => $expiresAt,
]);
