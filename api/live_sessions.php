<?php
require_once __DIR__ . '/../includes/database.php';
header('Content-Type: application/json');

$db = getDB();

$sessions = $db->query("
    SELECT s.id, s.username, s.phone, s.data_used_mb, r.name as router_name, p.name as package_name,
           TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as minutes_online
    FROM sessions s
    LEFT JOIN routers r ON s.router_id = r.id
    LEFT JOIN packages p ON s.package_id = p.id
    WHERE s.status = 'active'
    ORDER BY s.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['sessions' => $sessions, 'count' => count($sessions)]);
