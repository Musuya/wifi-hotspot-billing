<?php
require_once __DIR__ . '/includes/header.php';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate'])) {
        $packageId = (int)$_POST['package_id'];
        $quantity = min((int)$_POST['quantity'], 1000);
        $batchLabel = $_POST['batch_label'] ?: date('Y-m-d H:i');

        $pkg = $db->prepare("SELECT * FROM packages WHERE id = ?");
        $pkg->execute([$packageId]);
        $package = $pkg->fetch();

        if ($package) {
            $stmt = $db->prepare("INSERT INTO vouchers (code, package_id, batch_label, status, created_at) VALUES (?, ?, ?, 'unused', NOW())");
            for ($i = 0; $i < $quantity; $i++) {
                $code = strtoupper(substr(md5(uniqid() . $i), 0, 10));
                $stmt->execute([$code, $packageId, $batchLabel]);
            }
            $success = "$quantity vouchers generated successfully!";
        }
    }

    if (isset($_POST['delete_selected']) && !empty($_POST['selected'])) {
        $ids = implode(',', array_map('intval', $_POST['selected']));
        $db->query("DELETE FROM vouchers WHERE id IN ($ids) AND status = 'unused'");
        $success = "Selected unused vouchers deleted.";
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$batchFilter = $_GET['batch'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if ($statusFilter) {
    $where[] = "v.status = ?";
    $params[] = $statusFilter;
}
if ($batchFilter) {
    $where[] = "v.batch_label = ?";
    $params[] = $batchFilter;
}
if ($search) {
    $where[] = "(v.code LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Fetch vouchers
$vouchers = $db->prepare("
    SELECT v.*, p.name as package_name, p.price
    FROM vouchers v
    LEFT JOIN packages p ON v.package_id = p.id
    WHERE $whereClause
    ORDER BY v.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$vouchers->execute($params);
$voucherList = $vouchers->fetchAll();

// Total count
$totalCount = $db->prepare("SELECT COUNT(*) FROM vouchers v LEFT JOIN packages p ON v.package_id = p.id WHERE $whereClause");
$totalCount->execute($params);
$total = $totalCount->fetchColumn();
$totalPages = ceil($total / $perPage);

// Batches for filter
$batches = $db->query("SELECT batch_label FROM vouchers GROUP BY batch_label ORDER BY MAX(created_at) DESC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);

// Packages for generation
$packages = $db->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC")->fetchAll();

// Stats
$stats = [
        'total' => $db->query("SELECT COUNT(*) FROM vouchers")->fetchColumn(),
        'unused' => $db->query("SELECT COUNT(*) FROM vouchers WHERE status = 'unused'")->fetchColumn(),
        'used' => $db->query("SELECT COUNT(*) FROM vouchers WHERE status = 'used'")->fetchColumn(),
        'today' => $db->query("SELECT COUNT(*) FROM vouchers WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
];
?>

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Vouchers</h1>
            <p>Generate, manage, and track voucher codes</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('generateModal').classList.add('active')">
            <i class="bi bi-plus-lg"></i> Generate Vouchers
        </button>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="card stat-card">
                <div class="stat-icon primary"><i class="bi bi-ticket-perforated"></i></div>
                <div class="stat-value" data-count="<?= $stats['total'] ?>">0</div>
                <div class="stat-label">Total Vouchers</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card">
                <div class="stat-icon success"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value" data-count="<?= $stats['unused'] ?>">0</div>
                <div class="stat-label">Available</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card">
                <div class="stat-icon warning"><i class="bi bi-person-check"></i></div>
                <div class="stat-value" data-count="<?= $stats['used'] ?>">0</div>
                <div class="stat-label">Used</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card">
                <div class="stat-icon info"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-value" data-count="<?= $stats['today'] ?>">0</div>
                <div class="stat-label">Generated Today</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row align-items-end gap-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Code or package..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="unused" <?= $statusFilter == 'unused' ? 'selected' : '' ?>>Unused</option>
                        <option value="used" <?= $statusFilter == 'used' ? 'selected' : '' ?>>Used</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Batch</label>
                    <select name="batch" class="form-control" onchange="this.form.submit()">
                        <option value="">All Batches</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?= e($batch) ?>" <?= $batchFilter == $batch ? 'selected' : '' ?>><?= e($batch) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="vouchers.php" class="btn btn-secondary w-100">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Vouchers Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Voucher List (<?= $total ?> total)</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-secondary" onclick="exportVouchers()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <form method="POST" id="bulkForm">
                <table class="data-table w-100" id="vouchersTable">
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></th>
                        <th>Code</th>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Batch</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($voucherList as $v): ?>
                        <tr>
                            <td><input type="checkbox" name="selected[]" value="<?= $v['id'] ?>" class="voucher-check"></td>
                            <td>
                                <code style="background:var(--bg-hover);padding:0.25rem 0.5rem;border-radius:4px;font-size:0.875rem;"><?= e($v['code']) ?></code>
                                <button type="button" class="btn btn-sm btn-icon" onclick="copyCode('<?= e($v['code']) ?>')" title="Copy">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </td>
                            <td><?= e($v['package_name'] ?? 'N/A') ?></td>
                            <td>Ksh <?= number_format($v['price'] ?? 0) ?></td>
                            <td>
                            <span class="badge badge-<?= $v['status'] == 'unused' ? 'success' : 'secondary' ?>">
                                <span class="status-dot <?= $v['status'] == 'unused' ? 'online' : 'offline' ?>" style="width:6px;height:6px;"></span>
                                <?= e($v['status']) ?>
                            </span>
                            </td>
                            <td><small class="text-muted"><?= e($v['batch_label']) ?></small></td>
                            <td><small class="text-muted"><?= date('M j, Y', strtotime($v['created_at'])) ?></small></td>
                            <td>
                                <?php if ($v['status'] == 'unused'): ?>
                                    <button type="button" class="btn btn-sm btn-icon text-danger" onclick="deleteVoucher(<?= $v['id'] ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Bulk Actions -->
                <div class="p-3 border-top d-flex justify-content-between align-items-center" style="border-color:var(--border-color);">
                    <div class="d-flex gap-2">
                        <button type="submit" name="delete_selected" class="btn btn-sm btn-danger" onclick="return confirm('Delete selected unused vouchers?')">
                            <i class="bi bi-trash"></i> Delete Selected
                        </button>
                    </div>

                    <!-- Pagination -->
                    <nav>
                        <div class="d-flex gap-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page-1 ?>&status=<?= e($statusFilter) ?>&batch=<?= e($batchFilter) ?>&search=<?= e($search) ?>" class="btn btn-sm btn-secondary">&laquo;</a>
                            <?php endif; ?>
                            <span class="btn btn-sm btn-secondary">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page+1 ?>&status=<?= e($statusFilter) ?>&batch=<?= e($batchFilter) ?>&search=<?= e($search) ?>" class="btn btn-sm btn-secondary">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </div>
            </form>
        </div>
    </div>

    <!-- Generate Modal -->
    <div class="modal-overlay" id="generateModal">
        <div class="modal">
            <div class="modal-header">
                <h5><i class="bi bi-plus-circle me-2"></i>Generate Vouchers</h5>
                <button class="header-btn" onclick="document.getElementById('generateModal').classList.remove('active')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Package</label>
                        <select name="package_id" class="form-control" required>
                            <option value="">Select a package...</option>
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>"><?= e($pkg['name']) ?> - Ksh <?= number_format($pkg['price']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" min="1" max="1000" value="10" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batch Label (optional)</label>
                        <input type="text" name="batch_label" class="form-control" placeholder="e.g., Weekend Promo">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('generateModal').classList.remove('active')">Cancel</button>
                    <button type="submit" name="generate" class="btn btn-primary">
                        <i class="bi bi-magic"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSelectAll() {
            const checked = document.getElementById('selectAll').checked;
            document.querySelectorAll('.voucher-check').forEach(cb => cb.checked = checked);
        }

        function copyCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                Notifications.success('Voucher code copied!');
            });
        }

        function deleteVoucher(id) {
            confirmAction('Delete this unused voucher?', () => {
                window.location.href = `vouchers.php?delete=${id}`;
            });
        }

        function exportVouchers() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = `vouchers.php?${params.toString()}`;
        }

        TableManager.init('vouchersTable');
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>