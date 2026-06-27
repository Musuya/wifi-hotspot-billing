<?php
require_once __DIR__ . '/includes/header.php';

$period = $_GET['period'] ?? 'month';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Revenue by day
$dailyRevenue = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as transactions, SUM(amount) as revenue
    FROM transactions
    WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY date
");
$dailyRevenue->execute([$dateFrom, $dateTo]);
$dailyData = $dailyRevenue->fetchAll();

// Top packages
$topPackages = $db->prepare("
    SELECT p.name, COUNT(t.id) as sales, SUM(t.amount) as revenue
    FROM packages p
    LEFT JOIN transactions t ON p.id = t.package_id AND t.status = 'completed' AND DATE(t.created_at) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY sales DESC
    LIMIT 10
");
$topPackages->execute([$dateFrom, $dateTo]);
$packageData = $topPackages->fetchAll();

// Hourly distribution
$hourly = $db->prepare("
    SELECT HOUR(created_at) as hour, COUNT(*) as count
    FROM transactions
    WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
    GROUP BY HOUR(created_at)
    ORDER BY hour
");
$hourly->execute([$dateFrom, $dateTo]);
$hourlyData = $hourly->fetchAll();

// Summary
$summary = $db->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(amount) as total_revenue,
        AVG(amount) as avg_transaction,
        COUNT(DISTINCT phone_number) as unique_customers
    FROM transactions
    WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
");
$summary->execute([$dateFrom, $dateTo]);
$summaryData = $summary->fetch();
?>

    <div class="page-header">
        <h1>Reports & Analytics</h1>
        <p>Detailed insights into your business performance</p>
    </div>

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row align-items-end gap-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-graph-up"></i> Generate
                    </button>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-secondary w-100" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon primary"><i class="bi bi-receipt"></i></div>
                <div class="stat-value"><?= number_format((int)$summaryData['total_transactions']) ?></div>
                <div class="stat-label">Transactions</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon success"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-value">Ksh <?= number_format((float)$summaryData['total_revenue']) ?></div>
                <div class="stat-label">Revenue</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon warning"><i class="bi bi-calculator"></i></div>
                <div class="stat-value">Ksh <?= number_format((float)$summaryData['avg_transaction']) ?></div>
                <div class="stat-label">Avg Transaction</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon info"><i class="bi bi-people"></i></div>
                <div class="stat-value"><?= number_format((int)$summaryData['unique_customers']) ?></div>
                <div class="stat-label">Unique Customers</div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-graph-up-arrow me-2"></i>Daily Revenue</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-clock me-2"></i>Peak Hours</h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Packages Table -->
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-trophy me-2"></i>Top Performing Packages</h5>
        </div>
        <div class="card-body p-0">
            <table class="data-table w-100">
                <thead>
                <tr>
                    <th>Package</th>
                    <th>Sales</th>
                    <th>Revenue</th>
                    <th>Performance</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($packageData as $pkg): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($pkg['name']) ?></td>
                        <td><?= number_format((int)$pkg['sales']) ?></td>
                        <td class="fw-bold">Ksh <?= number_format((float)$pkg['revenue']) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="flex:1;height:6px;background:var(--border-color);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;width:<?= min(($pkg['sales'] / max(array_column($packageData, 'sales'))) * 100, 100) ?>%;background:linear-gradient(90deg,var(--primary),var(--secondary));border-radius:3px;"></div>
                                </div>
                                <small class="text-muted"><?= $pkg['sales'] ?></small>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Daily Revenue Chart
        new Chart(document.getElementById('dailyChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($d) => date('M j', strtotime($d['date'])), $dailyData)) ?>,
                datasets: [{
                    label: 'Revenue (Ksh)',
                    data: <?= json_encode(array_map(fn($d) => (float)$d['revenue'], $dailyData)) ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.8)',
                    borderRadius: 6,
                    borderSkipped: false
                }, {
                    label: 'Transactions',
                    data: <?= json_encode(array_map(fn($d) => (int)$d['transactions'] * 50, $dailyData)) ?>,
                    type: 'line',
                    borderColor: '#06b6d4',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    pointRadius: 3,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top', align: 'end' } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: 'var(--border-color)' }, ticks: { callback: v => 'Ksh ' + (v >= 1000 ? (v/1000).toFixed(1) + 'k' : v) } },
                    y1: { position: 'right', grid: { display: false }, ticks: { display: false } }
                }
            }
        });

        // Hourly Chart
        new Chart(document.getElementById('hourlyChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map(fn($d) => $d['hour'] . ':00', $hourlyData)) ?>,
                datasets: [{
                    data: <?= json_encode(array_map(fn($d) => (int)$d['count'], $hourlyData)) ?>,
                    backgroundColor: [
                        '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
                        '#ec4899', '#f43f5e', '#f97316', '#f59e0b',
                        '#eab308', '#84cc16', '#22c55e', '#10b981'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>