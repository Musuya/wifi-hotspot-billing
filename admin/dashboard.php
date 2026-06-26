<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$db = getDB();

$todayRevenue = $db->query(
    "SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE status='completed' AND DATE(completed_at) = CURDATE()"
)->fetch()['total'];

$monthRevenue = $db->query(
    "SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE status='completed' AND MONTH(completed_at) = MONTH(CURDATE()) AND YEAR(completed_at) = YEAR(CURDATE())"
)->fetch()['total'];

$activeSessions = $db->query(
    "SELECT COUNT(*) as cnt FROM sessions WHERE status='active' AND expires_at > NOW()"
)->fetch()['cnt'];

$todayTxCount = $db->query(
    "SELECT COUNT(*) as cnt FROM transactions WHERE status='completed' AND DATE(completed_at) = CURDATE()"
)->fetch()['cnt'];

$recentTx = $db->query(
    "SELECT t.*, p.name as package_name FROM transactions t
     LEFT JOIN packages p ON t.package_id = p.id
     ORDER BY t.created_at DESC LIMIT 10"
)->fetchAll();

$topPackages = $db->query(
    "SELECT p.name, COUNT(*) as sales, SUM(t.amount) as revenue
     FROM transactions t JOIN packages p ON t.package_id = p.id
     WHERE t.status = 'completed'
     GROUP BY p.id ORDER BY sales DESC LIMIT 5"
)->fetchAll();

include __DIR__ . '/_nav.php';
?>
<link rel="stylesheet" href="assets/admin.css">

<h1>Dashboard</h1>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Today's Revenue</span>
        <span class="stat-value"><?= formatMoney($todayRevenue) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">This Month</span>
        <span class="stat-value"><?= formatMoney($monthRevenue) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Active Sessions</span>
        <span class="stat-value"><?= $activeSessions ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Sales Today</span>
        <span class="stat-value"><?= $todayTxCount ?></span>
    </div>
</div>

<div class="panel">
    <h2>Top Packages</h2>
    <table>
        <thead><tr><th>Package</th><th>Sales</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($topPackages as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= $p['sales'] ?></td>
                <td><?= formatMoney($p['revenue']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Recent Transactions</h2>
    <table>
        <thead><tr><th>Time</th><th>Phone</th><th>Package</th><th>Amount</th><th>Status</th><th>M-Pesa Receipt</th></tr></thead>
        <tbody>
        <?php foreach ($recentTx as $tx): ?>
            <tr>
                <td><?= htmlspecialchars($tx['created_at']) ?></td>
                <td><?= htmlspecialchars($tx['phone_number']) ?></td>
                <td><?= htmlspecialchars($tx['package_name'] ?? '—') ?></td>
                <td><?= formatMoney($tx['amount']) ?></td>
                <td><span class="badge badge-<?= $tx['status'] ?>"><?= $tx['status'] ?></span></td>
                <td><?= htmlspecialchars($tx['mpesa_receipt_number'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</main></div>
