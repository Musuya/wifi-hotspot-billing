<?php
require_once __DIR__ . '/../includes/database.php';
header('Content-Type: application/json');

$db = getDB();
$period = $_GET['period'] ?? 'week';

if ($period == 'month') {
    $revenueData = $db->query("
        SELECT DATE(created_at) as date, COALESCE(SUM(amount), 0) as total, COUNT(*) as count
        FROM transactions 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date
    ")->fetchAll();
} else {
    $revenueData = $db->query("
        SELECT DATE(created_at) as date, COALESCE(SUM(amount), 0) as total, COUNT(*) as count
        FROM transactions 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date
    ")->fetchAll();
}

echo json_encode([
    'labels' => array_map(fn($d) => date('D', strtotime($d['date'])), $revenueData),
    'revenue' => array_map(fn($d) => (float)$d['total'], $revenueData),
    'transactions' => array_map(fn($d) => (int)$d['count'], $revenueData),
]);
