<?php
require_once __DIR__ . '/includes/header.php';

// Filters
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if ($statusFilter) {
    $where[] = "t.status = ?";
    $params[] = $statusFilter;
}
if ($dateFrom) {
    $where[] = "DATE(t.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = "DATE(t.created_at) <= ?";
    $params[] = $dateTo;
}
if ($search) {
    $where[] = "(t.phone_number LIKE ? OR p.name LIKE ? OR t.mpesa_receipt_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Fetch transactions
$transactions = $db->prepare("
    SELECT t.*, p.name as package_name, p.duration_minutes
    FROM transactions t
    LEFT JOIN packages p ON t.package_id = p.id
    WHERE $whereClause
    ORDER BY t.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$transactions->execute($params);
$transactionList = $transactions->fetchAll();

// Total count
$totalCount = $db->prepare("SELECT COUNT(*) FROM transactions t LEFT JOIN packages p ON t.package_id = p.id WHERE $whereClause");
$totalCount->execute($params);
$total = $totalCount->fetchColumn();
$totalPages = ceil($total / $perPage);

// Stats
$stats = [
        'today' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetchColumn(),
        'week' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'completed'")->fetchColumn(),
        'month' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'completed'")->fetchColumn(),
        'total' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed'")->fetchColumn(),
];

// Payment methods breakdown
$methods = $db->query("
    SELECT 'mpesa' as payment_method, COUNT(*) as count, SUM(amount) as total
    FROM transactions
    WHERE status = 'completed' AND mpesa_receipt_number IS NOT NULL
    UNION ALL
    SELECT 'voucher' as payment_method, COUNT(*) as count, SUM(amount) as total
    FROM transactions
    WHERE status = 'completed' AND voucher_code IS NOT NULL
")->fetchAll();
?>

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Transactions</h1>
            <p>View and manage all payment transactions</p>
        </div>
        <button class="btn btn-primary" onclick="exportTransactions()">
            <i class="bi bi-download"></i> Export CSV
        </button>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="card stat-card">
                <div class="stat-icon primary"><i class="bi bi-calendar-day"></i></div>
                <div class="stat-value" data-count="<?= $stats['today'] ?>">0</div>
                <div class="stat-label">Today</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card">
                <div class="stat-icon success"><i class="bi bi-calendar-week"></i></div>
                <div class="stat-value" data-count="<?= $stats['week'] ?>">0</div>
                <div class="stat-label">This Week</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card">
                <div class="stat-icon warning"><i class="bi bi-calendar-month"></i></div>
                <div class="stat-value" data-count="<?= $stats['month'] ?>">0</div>
                <div class="stat-label">This Month</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card">
                <div class="stat-icon info"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-value" data-count="<?= $stats['total'] ?>">0</div>
                <div class="stat-label">All Time</div>
            </div>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="row mb-4">
        <?php foreach ($methods as $method): ?>
            <div class="col-md-3 col-6">
                <div class="card" style="padding:1rem;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small"><?= e(ucfirst($method['payment_method'] ?? 'Unknown')) ?></div>
                            <td class="fw-bold">Ksh <?= number_format((float)($tx['amount'] ?? 0)) ?></td>
                        </div>
                        <div class="badge badge-info"><?= $method['count'] ?> txns</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row align-items-end gap-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Phone, receipt, package..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="completed" <?= $statusFilter == 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="failed" <?= $statusFilter == 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header">
            <h5>Transaction History (<?= $total ?> records)</h5>
        </div>
        <div class="card-body p-0">
            <table class="data-table w-100" id="transactionsTable">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Receipt</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($transactionList as $tx): ?>
                    <tr>
                        <td>#<?= $tx['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;">
                                    <?= strtoupper(substr($tx['phone_number'] ?? '??', -2)) ?>
                                </div>
                                <span><?= e($tx['phone_number'] ?? 'N/A') ?></span>
                            </div>
                        </td>
                        <td><?= e($tx['package_name'] ?? 'N/A') ?></td>
                        <td class="fw-bold">Ksh <?= number_format($tx['amount']) ?></td>
                        <td>
                        <span class="badge badge-secondary">
                            <i class="bi bi-<?= !empty($tx['mpesa_receipt_number']) ? 'phone' : 'ticket-perforated' ?>"></i>
                            <?= !empty($tx['mpesa_receipt_number']) ? 'M-Pesa' : 'Voucher' ?>
                        </span>
                        </td>
                        <td><small class="text-muted"><?= e($tx['mpesa_receipt_number'] ?? 'N/A') ?></small></td>
                        <td>
                        <span class="badge badge-<?= $tx['status'] == 'completed' ? 'success' : ($tx['status'] == 'pending' ? 'warning' : ($tx['status'] == 'failed' ? 'danger' : 'secondary')) ?>">
                            <span class="status-dot <?= $tx['status'] == 'completed' ? 'online' : ($tx['status'] == 'pending' ? 'pending' : 'offline') ?>" style="width:6px;height:6px;"></span>
                            <?= e($tx['status']) ?>
                        </span>
                        </td>
                        <td><small class="text-muted"><?= date('M j, Y H:i', strtotime($tx['created_at'])) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="p-3 border-top d-flex justify-content-end" style="border-color:var(--border-color);">
                <nav>
                    <div class="d-flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&status=<?= e($statusFilter) ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&search=<?= e($search) ?>" class="btn btn-sm btn-secondary">&laquo;</a>
                        <?php endif; ?>
                        <span class="btn btn-sm btn-secondary">Page <?= $page ?> of <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page+1 ?>&status=<?= e($statusFilter) ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&search=<?= e($search) ?>" class="btn btn-sm btn-secondary">&raquo;</a>
                        <?php endif; ?>
                    </div>
                </nav>
            </div>
        </div>
    </div>

    <script>
        function exportTransactions() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = `transactions.php?${params.toString()}`;
        }

        TableManager.init('transactionsTable');
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>