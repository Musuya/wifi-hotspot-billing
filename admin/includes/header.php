<?php
session_start();
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

$page = basename($_SERVER['PHP_SELF'], '.php');
$db = getDB();

// Fetch unread notifications count
$notifCount = $db->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();

// Fetch admin info
$admin = $db->query("SELECT * FROM admins WHERE id = " . (int)$_SESSION['admin_id'])->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Billing - <?= ucfirst($page) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        // Set theme immediately before any rendering
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-wifi"></i>
        </div>
        <span class="brand-text">WiFi Billing</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <a href="index.php" class="nav-link <?= $page == 'index' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="packages.php" class="nav-link <?= $page == 'packages' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i>
                <span>Packages</span>
            </a>
            <a href="vouchers.php" class="nav-link <?= $page == 'vouchers' ? 'active' : '' ?>">
                <i class="bi bi-ticket-perforated"></i>
                <span>Vouchers</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Sales</div>
            <a href="transactions.php" class="nav-link <?= $page == 'transactions' ? 'active' : '' ?>">
                <i class="bi bi-credit-card"></i>
                <span>Transactions</span>
            </a>
            <a href="sessions.php" class="nav-link <?= $page == 'sessions' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Sessions</span>
                <span class="badge"><?= $db->query("SELECT COUNT(*) FROM sessions WHERE status = 'active'")->fetchColumn() ?></span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <a href="routers.php" class="nav-link <?= $page == 'routers' ? 'active' : '' ?>">
                <i class="bi bi-router"></i>
                <span>Routers</span>
            </a>
            <a href="reports.php" class="nav-link <?= $page == 'reports' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports</span>
            </a>
            <a href="notifications.php" class="nav-link <?= $page == 'notifications' ? 'active' : '' ?>">
                <i class="bi bi-bell"></i>
                <span>Notifications</span>
                <?php if ($notifCount > 0): ?>
                    <span class="badge"><?= $notifCount ?></span>
                <?php endif; ?>
            </a>
            <a href="settings.php" class="nav-link <?= $page == 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link text-danger" style="margin:0;">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
        <button class="sidebar-toggle mt-2" id="sidebarToggle">
            <i class="bi bi-chevron-left"></i>
            <span>Collapse</span>
        </button>
    </div>
</aside>

<!-- Main Wrapper -->
<div class="main-wrapper" id="mainWrapper">
    <!-- Top Header -->
    <header class="top-header">
        <div class="d-flex align-items-center gap-3">
            <button class="header-btn d-lg-none" id="mobileMenuToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="header-search d-none d-md-block">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Search transactions, vouchers...">
            </div>
        </div>

        <div class="header-actions">
            <button class="header-btn" id="themeToggle" title="Toggle theme">
                <i class="bi bi-moon"></i>
            </button>
            <a href="notifications.php" class="header-btn" title="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="notification-dot"></span>
                <?php endif; ?>
            </a>
            <div class="user-menu">
                <div class="user-avatar">
                    <?= strtoupper(substr($admin['username'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="user-info d-none d-md-block">
                    <div class="user-name"><?= e($admin['username'] ?? 'Admin') ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="content-area">