<?php
/** admin/_nav.php - included at the top of every admin page */
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="admin-shell">
<nav class="admin-nav">
    <div class="nav-brand">WiFi Billing</div>
    <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="packages.php" class="<?= $current === 'packages.php' ? 'active' : '' ?>">Packages</a>
    <a href="vouchers.php" class="<?= $current === 'vouchers.php' ? 'active' : '' ?>">Vouchers</a>
    <a href="transactions.php" class="<?= $current === 'transactions.php' ? 'active' : '' ?>">Transactions</a>
    <a href="sessions.php" class="<?= $current === 'sessions.php' ? 'active' : '' ?>">Active Sessions</a>
    <a href="routers.php" class="<?= $current === 'routers.php' ? 'active' : '' ?>">Routers</a>
    <a href="settings.php" class="<?= $current === 'settings.php' ? 'active' : '' ?>">Settings</a>
    <a href="logout.php" class="logout">Logout</a>
</nav>
<main class="admin-content">
