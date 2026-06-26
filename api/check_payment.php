<?php
/**
 * api/check_payment.php
 * Frontend polls this every few seconds after STK push to see if payment landed.
 * Once 'completed', also returns the hotspot login credentials.
 */

require_once __DIR__ . '/../includes/helpers.php';

$transactionId = (int)($_GET['transaction_id'] ?? 0);

$db = getDB();
$stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$transactionId]);
$tx = $stmt->fetch();

if (!$tx) {
    jsonResponse(['status' => 'not_found'], 404);
}

$response = ['status' => $tx['status']];

if ($tx['status'] === 'completed') {
    $sessStmt = $db->prepare("SELECT * FROM sessions WHERE transaction_id = ? ORDER BY id DESC LIMIT 1");
    $sessStmt->execute([$tx['id']]);
    $session = $sessStmt->fetch();

    if ($session) {
        $response['username'] = $session['hotspot_username'];
        $response['password'] = $session['hotspot_password'];
        $response['expires_at'] = $session['expires_at'];
        $response['connected'] = ($session['status'] === 'active');
    }
}

jsonResponse($response);
