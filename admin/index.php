<?php
require_once __DIR__ . '/includes/header.php';

// Fetch all dashboard stats
$stats = [
        'today_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetchColumn(),
        'month_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'completed'")->fetchColumn(),
        'active_sessions' => $db->query("SELECT COUNT(*) FROM sessions WHERE status = 'active'")->fetchColumn(),
        'total_customers' => $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'completed'")->fetchColumn(),
        'vouchers_today' => $db->query("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetchColumn(),
        'total_packages' => $db->query("SELECT COUNT(*) FROM packages WHERE is_active = 1")->fetchColumn(),
];

// Recent transactions
$recentTransactions = $db->query("
    SELECT t.*, p.name as package_name 
    FROM transactions t 
    LEFT JOIN packages p ON t.package_id = p.id 
    ORDER BY t.created_at DESC 
    LIMIT 8
")->fetchAll();

// Revenue chart data (last 7 days)
$chartData = $db->query("
    SELECT DATE(created_at) as date, COALESCE(SUM(amount), 0) as total, COUNT(*) as count
    FROM transactions 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY date
")->fetchAll();

// Popular packages
$popularPackages = $db->query("
    SELECT p.name, p.price, COUNT(t.id) as sales_count, SUM(t.amount) as total_revenue
    FROM packages p
    LEFT JOIN transactions t ON p.id = t.package_id AND t.status = 'completed'
    GROUP BY p.id
    ORDER BY sales_count DESC
    LIMIT 5
")->fetchAll();

// Online users by router
$routerStats = $db->query("
    SELECT r.name, r.ip_address, COUNT(s.id) as online_count
    FROM routers r
    LEFT JOIN sessions s ON r.id = s.router_id AND s.status = 'active'
    GROUP BY r.id
    ORDER BY online_count DESC
")->fetchAll();
?>

    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Dashboard</h1>
            <p>Welcome back! Here's what's happening with your WiFi business today.</p>
        </div>
        <a href="packages.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span class="d-none d-md-inline">New Package</span>
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon primary">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <span class="stat-change positive">
                    <i class="bi bi-arrow-up-short"></i>Today
                </span>
                </div>
                <div class="stat-value" data-count="<?= $stats['today_revenue'] ?>">0</div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon success">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <span class="stat-change positive">
                    <i class="bi bi-calendar-month"></i>This Month
                </span>
                </div>
                <div class="stat-value" data-count="<?= $stats['month_revenue'] ?>">0</div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon warning">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <span class="stat-change positive">
                    <i class="bi bi-wifi"></i>Online
                </span>
                </div>
                <div class="stat-value" data-count="<?= $stats['active_sessions'] ?>">0</div>
                <div class="stat-label">Active Sessions</div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon info">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <span class="stat-change positive">
                    <i class="bi bi-ticket-perforated"></i><?= $stats['vouchers_today'] ?> Today
                </span>
                </div>
                <div class="stat-value" data-count="<?= $stats['total_customers'] ?>">0</div>
                <div class="stat-label">Total Transactions</div>
            </div>
        </div>
    </div>

    <!-- Charts & Tables Row -->
    <div class="row mt-4">
        <!-- Revenue Chart -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-bar-chart-line me-2"></i>Revenue Trend (Last 7 Days)</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-secondary" onclick="updateChart('week')">Week</button>
                        <button class="btn btn-sm btn-secondary" onclick="updateChart('month')">Month</button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="280"></canvas>
                </div>
            </div>
        </div>

        <!-- Router Status -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-router me-2"></i>Router Status</h5>
                    <a href="routers.php" class="text-decoration-none small" style="color:var(--primary);">Manage</a>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($routerStats as $router): ?>
                        <div class="d-flex align-items-center p-3 border-bottom" style="border-color:var(--border-color);">
                            <div class="flex-shrink-0">
                                <span class="status-dot online"></span>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <div class="fw-semibold"><?= e($router['name']) ?></div>
                                <small class="text-muted"><?= e($router['ip_address']) ?></small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?= $router['online_count'] ?></div>
                                <small class="text-muted">online</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row -->
    <div class="row mt-4">
        <!-- Recent Transactions -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
                    <a href="transactions.php" class="text-decoration-none small" style="color:var(--primary);">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="data-table w-100">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentTransactions as $tx): ?>
                            <tr>
                                <td>#<?= $tx['id'] ?></td>
                                <td><?= e($tx['package_name'] ?? 'N/A') ?></td>
                                <td class="fw-bold">Ksh <?= number_format($tx['amount']) ?></td>
                                <td>
                                <span class="badge badge-<?= $tx['status'] == 'completed' ? 'success' : ($tx['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                    <span class="status-dot <?= $tx['status'] == 'completed' ? 'online' : ($tx['status'] == 'pending' ? 'pending' : 'offline') ?>" style="width:6px;height:6px;"></span>
                                    <?= e($tx['status']) ?>
                                </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Popular Packages -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-trophy me-2"></i>Popular Packages</h5>
                    <a href="packages.php" class="text-decoration-none small" style="color:var(--primary);">Manage</a>
                </div>
                <div class="card-body">
                    <?php foreach ($popularPackages as $pkg): ?>
                        <div class="d-flex align-items-center mb-3 p-3 rounded" style="background:var(--bg-hover);">
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($pkg['name']) ?></div>
                                <small class="text-muted">Ksh <?= number_format($pkg['price']) ?> &bull; <?= $pkg['sales_count'] ?> sales</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">Ksh <?= number_format($pkg['total_revenue'] ?? 0) ?></div>
                                <small class="text-muted">revenue</small>
                            </div>
                            <div class="ms-3" style="width:100px;">
                                <div style="height:6px;background:var(--border-color);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;width:<?= min(($pkg['sales_count'] / max(array_column($popularPackages, 'sales_count'))) * 100, 100) ?>%;background:linear-gradient(90deg,var(--primary),var(--secondary));border-radius:3px;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(fn($d) => date('D', strtotime($d['date'])), $chartData)) ?>,
                datasets: [{
                    label: 'Revenue (Ksh)',
                    data: <?= json_encode(array_map(fn($d) => $d['total'], $chartData)) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    borderWidth: 3
                }, {
                    label: 'Transactions',
                    data: <?= json_encode(array_map(fn($d) => $d['count'] * 50, $chartData)) ?>,
                    borderColor: '#06b6d4',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.4,
                    pointRadius: 0,
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, pointStyle: 'circle', padding: 20, font: { family: 'Inter', size: 12 } } },
                    tooltip: {
                        backgroundColor: 'var(--bg-card)', titleColor: 'var(--text-primary)', bodyColor: 'var(--text-secondary)',
                        borderColor: 'var(--border-color)', borderWidth: 1, padding: 12, displayColors: true,
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.label === 'Revenue (Ksh)') return 'Revenue: Ksh ' + context.parsed.y.toLocaleString();
                                return 'Transactions: ' + Math.round(context.parsed.y / 50);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: 'var(--text-muted)', font: { family: 'Inter', size: 11 } } },
                    y: { grid: { color: 'var(--border-color)', drawBorder: false }, ticks: { color: 'var(--text-muted)', font: { family: 'Inter', size: 11 }, callback: function(value) { return 'Ksh ' + (value >= 1000 ? (value/1000).toFixed(1) + 'k' : value); } } },
                    y1: { position: 'right', grid: { display: false }, ticks: { display: false } }
                }
            }
        });

        async function updateChart(period) {
            try {
                const data = await API.get('../api/dashboard.php?period=' + period);
                revenueChart.data.labels = data.labels;
                revenueChart.data.datasets[0].data = data.revenue;
                revenueChart.data.datasets[1].data = data.transactions.map(t => t * 50);
                revenueChart.update();
                Notifications.success('Showing ' + period + 'ly data');
            } catch (e) { console.error('Failed to update chart:', e); }
        }

        Realtime.start('../api/live_stats.php', function(data) {
            document.querySelectorAll('.stat-value').forEach(function(el, i) {
                var keys = ['today_revenue', 'month_revenue', 'active_sessions', 'total_customers'];
                if (data[keys[i]] !== undefined) el.textContent = data[keys[i]].toLocaleString();
            });
        }, 30000);
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?><?php
require_once __DIR__ . '/includes/header.php';

// Fetch all dashboard stats
$stats = [
        'today_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetchColumn(),
        'month_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'completed'")->fetchColumn(),
        'active_sessions' => $db->query("SELECT COUNT(*) FROM sessions WHERE status = 'active'")->fetchColumn(),
        'total_customers' => $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'completed'")->fetchColumn(),
        'vouchers_today' => $db->query("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetchColumn(),
        'total_packages' => $db->query("SELECT COUNT(*) FROM packages WHERE is_active = 1")->fetchColumn(),
];

// Recent transactions
$recentTransactions = $db->query("
    SELECT t.*, p.name as package_name 
    FROM transactions t 
    LEFT JOIN packages p ON t.package_id = p.id 
    ORDER BY t.created_at DESC 
    LIMIT 8
")->fetchAll();

// Revenue chart data (last 7 days)
$chartData = $db->query("
    SELECT DATE(created_at) as date, COALESCE(SUM(amount), 0) as total, COUNT(*) as count
    FROM transactions 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY date
")->fetchAll();

// Popular packages
$popularPackages = $db->query("
    SELECT p.name, p.price, COUNT(t.id) as sales_count, SUM(t.amount) as total_revenue
    FROM packages p
    LEFT JOIN transactions t ON p.id = t.package_id AND t.status = 'completed'
    GROUP BY p.id
    ORDER BY sales_count DESC
    LIMIT 5
")->fetchAll();

// Online users by router
$routerStats = $db->query("
    SELECT r.name, r.ip_address, COUNT(s.id) as online_count
    FROM routers r
    LEFT JOIN sessions s ON r.id = s.router_id AND s.status = 'active'
    GROUP BY r.id
    ORDER BY online_count DESC
")->fetchAll();
?>

    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Dashboard</h1>
            <p>Welcome back! Here's what's happening with your WiFi business today.</p>
        </div>
        <a href="packages.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span class="d-none d-md-inline">New Package</span>
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon primary">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <span class="stat-change positive">
                    <i class="bi bi-arrow-up-short"></i>Today
                </span>
                </div>
                <div class="stat-value" data-count="<?= $stats['today_revenue'] ?>">0</div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon success">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <span class="stat-change positive">
                    <i class="bi bi-calendar-month"></i>This Month
                </span>
                </div>
                <div class="stat-value" data-count="<?= $stats['month_revenue'] ?>">0</div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon warning">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <span class="stat-change positive">
                    <i class="bi bi-wifi"></i>Online
                </span>
                </div>
                <div class="stat-value" data-count="<?= $stats['active_sessions'] ?>">0</div>
                <div class="stat-label">Active Sessions</div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon info">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <span class="stat-change positive">
                    <i class="bi bi-ticket-perforated"></i><?= $stats['vouchers_today'] ?> Today
                </span>
                </div>
                <div class="stat-value" data-count="<?= $stats['total_customers'] ?>">0</div>
                <div class="stat-label">Total Transactions</div>
            </div>
        </div>
    </div>

    <!-- Charts & Tables Row -->
    <div class="row mt-4">
        <!-- Revenue Chart -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-bar-chart-line me-2"></i>Revenue Trend (Last 7 Days)</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-secondary" onclick="updateChart('week')">Week</button>
                        <button class="btn btn-sm btn-secondary" onclick="updateChart('month')">Month</button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="280"></canvas>
                </div>
            </div>
        </div>

        <!-- Router Status -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-router me-2"></i>Router Status</h5>
                    <a href="routers.php" class="text-decoration-none small" style="color:var(--primary);">Manage</a>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($routerStats as $router): ?>
                        <div class="d-flex align-items-center p-3 border-bottom" style="border-color:var(--border-color);">
                            <div class="flex-shrink-0">
                                <span class="status-dot online"></span>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <div class="fw-semibold"><?= e($router['name']) ?></div>
                                <small class="text-muted"><?= e($router['ip_address']) ?></small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?= $router['online_count'] ?></div>
                                <small class="text-muted">online</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row -->
    <div class="row mt-4">
        <!-- Recent Transactions -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
                    <a href="transactions.php" class="text-decoration-none small" style="color:var(--primary);">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="data-table w-100">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentTransactions as $tx): ?>
                            <tr>
                                <td>#<?= $tx['id'] ?></td>
                                <td><?= e($tx['package_name'] ?? 'N/A') ?></td>
                                <td class="fw-bold">Ksh <?= number_format($tx['amount']) ?></td>
                                <td>
                                <span class="badge badge-<?= $tx['status'] == 'completed' ? 'success' : ($tx['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                    <span class="status-dot <?= $tx['status'] == 'completed' ? 'online' : ($tx['status'] == 'pending' ? 'pending' : 'offline') ?>" style="width:6px;height:6px;"></span>
                                    <?= e($tx['status']) ?>
                                </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Popular Packages -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-trophy me-2"></i>Popular Packages</h5>
                    <a href="packages.php" class="text-decoration-none small" style="color:var(--primary);">Manage</a>
                </div>
                <div class="card-body">
                    <?php foreach ($popularPackages as $pkg): ?>
                        <div class="d-flex align-items-center mb-3 p-3 rounded" style="background:var(--bg-hover);">
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($pkg['name']) ?></div>
                                <small class="text-muted">Ksh <?= number_format($pkg['price']) ?> &bull; <?= $pkg['sales_count'] ?> sales</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">Ksh <?= number_format($pkg['total_revenue'] ?? 0) ?></div>
                                <small class="text-muted">revenue</small>
                            </div>
                            <div class="ms-3" style="width:100px;">
                                <div style="height:6px;background:var(--border-color);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;width:<?= min(($pkg['sales_count'] / max(array_column($popularPackages, 'sales_count'))) * 100, 100) ?>%;background:linear-gradient(90deg,var(--primary),var(--secondary));border-radius:3px;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(fn($d) => date('D', strtotime($d['date'])), $chartData)) ?>,
                datasets: [{
                    label: 'Revenue (Ksh)',
                    data: <?= json_encode(array_map(fn($d) => $d['total'], $chartData)) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    borderWidth: 3
                }, {
                    label: 'Transactions',
                    data: <?= json_encode(array_map(fn($d) => $d['count'] * 50, $chartData)) ?>,
                    borderColor: '#06b6d4',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.4,
                    pointRadius: 0,
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, pointStyle: 'circle', padding: 20, font: { family: 'Inter', size: 12 } } },
                    tooltip: {
                        backgroundColor: 'var(--bg-card)', titleColor: 'var(--text-primary)', bodyColor: 'var(--text-secondary)',
                        borderColor: 'var(--border-color)', borderWidth: 1, padding: 12, displayColors: true,
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.label === 'Revenue (Ksh)') return 'Revenue: Ksh ' + context.parsed.y.toLocaleString();
                                return 'Transactions: ' + Math.round(context.parsed.y / 50);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: 'var(--text-muted)', font: { family: 'Inter', size: 11 } } },
                    y: { grid: { color: 'var(--border-color)', drawBorder: false }, ticks: { color: 'var(--text-muted)', font: { family: 'Inter', size: 11 }, callback: function(value) { return 'Ksh ' + (value >= 1000 ? (value/1000).toFixed(1) + 'k' : value); } } },
                    y1: { position: 'right', grid: { display: false }, ticks: { display: false } }
                }
            }
        });

        async function updateChart(period) {
            try {
                const data = await API.get('../api/dashboard.php?period=' + period);
                revenueChart.data.labels = data.labels;
                revenueChart.data.datasets[0].data = data.revenue;
                revenueChart.data.datasets[1].data = data.transactions.map(t => t * 50);
                revenueChart.update();
                Notifications.success('Showing ' + period + 'ly data');
            } catch (e) { console.error('Failed to update chart:', e); }
        }

        Realtime.start('../api/live_stats.php', function(data) {
            document.querySelectorAll('.stat-value').forEach(function(el, i) {
                var keys = ['today_revenue', 'month_revenue', 'active_sessions', 'total_customers'];
                if (data[keys[i]] !== undefined) el.textContent = data[keys[i]].toLocaleString();
            });
        }, 30000);
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>