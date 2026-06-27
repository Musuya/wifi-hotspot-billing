<?php
/**
 * includes/helpers.php
 * Shared utility functions.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Fetch all settings as an associative array, e.g. $settings['site_name'].
 */
function getSettings(): array {
    static $cache = null;
    if ($cache === null) {
        $stmt = getDB()->query("SELECT setting_key, setting_value FROM settings");
        $cache = [];
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache;
}

function updateSetting(string $key, string $value): void {
    $stmt = getDB()->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = ?"
    );
    $stmt->execute([$key, $value, $value]);
}

/**
 * Generate a random voucher code, e.g. AB12CD34.
 * Avoids ambiguous characters (0/O, 1/I) to make codes easy to read off a printed receipt.
 */
function generateVoucherCode(int $length = 8): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Normalize a Kenyan phone number to the 2547XXXXXXXX format M-Pesa expects.
 * Accepts 07XXXXXXXX, 7XXXXXXXX, 2547XXXXXXXX, +2547XXXXXXXX.
 */
function normalizePhone(string $phone): ?string {
    $phone = preg_replace('/\\s+/', '', $phone);
    $phone = ltrim($phone, '+');

    if (preg_match('/^0(7|1)\\d{8}$/', $phone)) {
        return '254' . substr($phone, 1);
    }
    if (preg_match('/^(7|1)\\d{8}$/', $phone)) {
        return '254' . $phone;
    }
    if (preg_match('/^254(7|1)\\d{8}$/', $phone)) {
        return $phone;
    }
    return null; // invalid format
}

function formatMoney(float $amount): string {
    $settings = getSettings();
    $currency = $settings['currency'] ?? 'KES';
    return $currency . ' ' . number_format($amount, 2);
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Check if user is logged in as admin
 */
function is_admin_logged_in(): bool {
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

/**
 * Escape HTML output
 */
function e(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}