<?php
require_once __DIR__ . '/../includes/database.php';
header('Content-Type: application/json');

$db = getDB();

echo json_encode([
    'today_revenue' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetchColumn(),
    'month_revenue' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'completed'")->fetchColumn(),
    'active_sessions' => (int)$db->query("SELECT COUNT(*) FROM sessions WHERE status = 'active'")->fetchColumn(),
    'total_customers' => (int)$db->query("SELECT COUNT(DISTINCT phone) FROM transactions WHERE status = 'completed'")->fetchColumn(),
    'timestamp' => time(),
]);
