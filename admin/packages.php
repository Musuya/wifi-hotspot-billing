<?php
require_once __DIR__ . '/includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_package'])) {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
                'name' => $_POST['name'],
                'price' => (float)$_POST['price'],
                'duration' => $_POST['duration'] ?? '1 hour',
                'speed_limit' => $_POST['speed_limit'] ?? null,
                'data_cap_mb' => (int)($_POST['data_cap_mb'] ?? 0),
                'description' => $_POST['description'] ?? '',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $db->prepare("UPDATE packages SET name=?, price=?, duration=?, speed_limit=?, data_cap_mb=?, description=?, is_active=? WHERE id=?")
                    ->execute([$data['name'], $data['price'], $data['duration'], $data['speed_limit'], $data['data_cap_mb'], $data['description'], $data['is_active'], $id]);
            $success = "Package updated successfully!";
        } else {
            $db->prepare("INSERT INTO packages (name, price, duration, speed_limit, data_cap_mb, description, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())")
                    ->execute([$data['name'], $data['price'], $data['duration'], $data['speed_limit'], $data['data_cap_mb'], $data['description'], $data['is_active']]);
            $success = "Package created successfully!";
        }
    }

    if (isset($_POST['delete_package'])) {
        $id = (int)$_POST['delete_package'];
        $db->prepare("DELETE FROM packages WHERE id = ?")->execute([$id]);
        $success = "Package deleted.";
    }
}

// Fetch packages with sales stats
$packages = $db->query("
    SELECT p.*, COUNT(t.id) as total_sales, COALESCE(SUM(t.amount), 0) as total_revenue
    FROM packages p
    LEFT JOIN transactions t ON p.id = t.package_id AND t.status = 'completed'
    GROUP BY p.id
    ORDER BY p.price ASC
")->fetchAll();

// Stats
$totalPackages = count($packages);
$activePackages = count(array_filter($packages, fn($p) => $p['is_active']));
?>

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Packages</h1>
            <p>Manage internet packages and pricing</p>
        </div>
        <button class="btn btn-primary" onclick="openPackageModal()">
            <i class="bi bi-plus-lg"></i> Add Package
        </button>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon primary"><i class="bi bi-box-seam"></i></div>
                <div class="stat-value" data-count="<?= $totalPackages ?>">0</div>
                <div class="stat-label">Total Packages</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon success"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value" data-count="<?= $activePackages ?>">0</div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon warning"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-value" data-count="<?= array_sum(array_column($packages, 'total_revenue')) ?>">0</div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
    </div>

    <!-- Packages Grid -->
    <div class="row">
        <?php foreach ($packages as $pkg): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card" style="position:relative;overflow:hidden;">
                    <?php if (!$pkg['is_active']): ?>
                        <div style="position:absolute;top:1rem;right:1rem;">
                            <span class="badge badge-secondary">Inactive</span>
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1"><?= e($pkg['name']) ?></h5>
                                <p class="text-muted mb-0" style="font-size:0.875rem;"><?= e($pkg['description'] ?: 'No description') ?></p>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold" style="font-size:1.5rem;color:var(--primary);">Ksh <?= number_format($pkg['price']) ?></div>
                                <small class="text-muted"><?= e($pkg['duration'] ?? 'N/A') ?></small>
                            </div>
                        </div>

                        <div class="d-flex gap-3 mb-3" style="font-size:0.875rem;">
                            <?php if (!empty($pkg['speed_limit'])): ?>
                                <span class="text-muted"><i class="bi bi-speedometer2 me-1"></i><?= e($pkg['speed_limit']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($pkg['data_cap_mb'])): ?>
                                <span class="text-muted"><i class="bi bi-hdd me-1"></i><?= number_format($pkg['data_cap_mb']) ?> MB</span>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-3" style="border-top:1px solid var(--border-color);">
                            <div class="d-flex gap-2">
                                <span class="badge badge-info"><?= $pkg['total_sales'] ?> sales</span>
                                <span class="badge badge-success">Ksh <?= number_format($pkg['total_revenue']) ?></span>
                            </div>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-icon" onclick="editPackage(<?= htmlspecialchars(json_encode($pkg)) ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this package?')">
                                    <input type="hidden" name="delete_package" value="<?= $pkg['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-icon text-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Package Modal -->
    <div class="modal-overlay" id="packageModal">
        <div class="modal" style="max-width:600px;">
            <div class="modal-header">
                <h5 id="modalTitle"><i class="bi bi-box-seam me-2"></i>Add Package</h5>
                <button class="header-btn" onclick="closePackageModal()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="pkgId" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Package Name *</label>
                                <input type="text" name="name" id="pkgName" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Price (Ksh) *</label>
                                <input type="number" name="price" id="pkgPrice" class="form-control" min="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Duration *</label>
                                <input type="text" name="duration" id="pkgDuration" class="form-control" placeholder="e.g., 1 hour, 24 hours, 7 days" value="1 hour" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Speed Limit</label>
                                <input type="text" name="speed_limit" id="pkgSpeed" class="form-control" placeholder="e.g., 2Mbps/1Mbps">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Data Cap (MB)</label>
                                <input type="number" name="data_cap_mb" id="pkgDataCap" class="form-control" min="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <div class="form-check" style="padding-top:0.5rem;">
                                    <input type="checkbox" name="is_active" id="pkgActive" class="form-check-input" checked>
                                    <label class="form-check-label" for="pkgActive">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="pkgDesc" class="form-control" rows="2" placeholder="Brief description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePackageModal()">Cancel</button>
                    <button type="submit" name="save_package" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Package
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPackageModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-box-seam me-2"></i>Add Package';
            document.getElementById('pkgId').value = '';
            document.getElementById('pkgName').value = '';
            document.getElementById('pkgPrice').value = '';
            document.getElementById('pkgDuration').value = '1 hour';
            document.getElementById('pkgSpeed').value = '';
            document.getElementById('pkgDataCap').value = '0';
            document.getElementById('pkgDesc').value = '';
            document.getElementById('pkgActive').checked = true;
            document.getElementById('packageModal').classList.add('active');
        }

        function editPackage(pkg) {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Package';
            document.getElementById('pkgId').value = pkg.id;
            document.getElementById('pkgName').value = pkg.name;
            document.getElementById('pkgPrice').value = pkg.price;
            document.getElementById('pkgDuration').value = pkg.duration || '1 hour';
            document.getElementById('pkgSpeed').value = pkg.speed_limit || '';
            document.getElementById('pkgDataCap').value = pkg.data_cap_mb || 0;
            document.getElementById('pkgDesc').value = pkg.description || '';
            document.getElementById('pkgActive').checked = pkg.is_active == 1;
            document.getElementById('packageModal').classList.add('active');
        }

        function closePackageModal() {
            document.getElementById('packageModal').classList.remove('active');
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>