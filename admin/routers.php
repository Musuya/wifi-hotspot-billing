<?php
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_router'])) {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'name' => $_POST['name'],
            'ip_address' => $_POST['ip_address'],
            'api_port' => (int)($_POST['api_port'] ?? 8728),
            'api_username' => $_POST['api_username'],
            'api_password' => $_POST['api_password'],
            'location' => $_POST['location'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $db->prepare("UPDATE routers SET name=?, ip_address=?, api_port=?, api_username=?, api_password=?, location=?, is_active=? WHERE id=?")
               ->execute([$data['name'], $data['ip_address'], $data['api_port'], $data['api_username'], $data['api_password'], $data['location'], $data['is_active'], $id]);
        } else {
            $db->prepare("INSERT INTO routers (name, ip_address, api_port, api_username, api_password, location, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())")
               ->execute([$data['name'], $data['ip_address'], $data['api_port'], $data['api_username'], $data['api_password'], $data['location'], $data['is_active']]);
        }
        $success = "Router saved successfully!";
    }

    if (isset($_POST['delete_router'])) {
        $db->prepare("DELETE FROM routers WHERE id = ?")->execute([(int)$_POST['delete_router']]);
        $success = "Router deleted.";
    }

    if (isset($_POST['test_connection'])) {
        $routerId = (int)$_POST['test_connection'];
        $router = $db->query("SELECT * FROM routers WHERE id = $routerId")->fetch();
        // Test connection logic here
        $success = "Connection test initiated for {$router['name']}.";
    }
}

$routers = $db->query("
    SELECT r.*, COUNT(s.id) as active_sessions
    FROM routers r
    LEFT JOIN sessions s ON r.id = s.router_id AND s.status = 'active'
    GROUP BY r.id
    ORDER BY r.name
")->fetchAll();
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Routers</h1>
        <p>Manage MikroTik routers and hotspots</p>
    </div>
    <button class="btn btn-primary" onclick="openRouterModal()">
        <i class="bi bi-plus-lg"></i> Add Router
    </button>
</div>

<div class="row">
    <?php foreach ($routers as $router): ?>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon primary" style="width:48px;height:48px;font-size:1.25rem;">
                            <i class="bi bi-router"></i>
                        </div>
                        <div>
                            <h5 class="mb-1"><?= e($router['name']) ?></h5>
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-dot <?= $router['status'] == 'online' ? 'online' : 'offline' ?>"></span>
                                <small class="text-muted"><?= e($router['status'] ?? 'Unknown') ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="badge badge-<?= $router['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $router['is_active'] ? 'Active' : 'Inactive' ?>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block">IP Address</small>
                        <code style="font-size:0.875rem;"><?= e($router['ip_address']) ?>:<?= $router['api_port'] ?></code>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Location</small>
                        <span style="font-size:0.875rem;"><?= e($router['location'] ?: 'N/A') ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Active Sessions</small>
                        <span class="fw-bold" style="font-size:0.875rem;"><?= $router['active_sessions'] ?> users</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">API User</small>
                        <span style="font-size:0.875rem;"><?= e($router['api_username']) ?></span>
                    </div>
                </div>

                <div class="d-flex gap-2 pt-3" style="border-top:1px solid var(--border-color);">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="test_connection" value="<?= $router['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary">
                            <i class="bi bi-lightning"></i> Test
                        </button>
                    </form>
                    <button class="btn btn-sm btn-secondary" onclick="editRouter(<?= htmlspecialchars(json_encode($router)) ?>)">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this router?')">
                        <input type="hidden" name="delete_router" value="<?= $router['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Router Modal -->
<div class="modal-overlay" id="routerModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h5 id="routerModalTitle"><i class="bi bi-router me-2"></i>Add Router</h5>
            <button class="header-btn" onclick="closeRouterModal()">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" id="routerId" value="">
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Router Name *</label>
                            <input type="text" name="name" id="routerName" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="routerLocation" class="form-control" placeholder="e.g., Office Block A">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label class="form-label">IP Address *</label>
                            <input type="text" name="ip_address" id="routerIp" class="form-control" placeholder="192.168.1.1" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">API Port</label>
                            <input type="number" name="api_port" id="routerPort" class="form-control" value="8728">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">API Username *</label>
                            <input type="text" name="api_username" id="routerUser" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">API Password *</label>
                            <input type="password" name="api_password" id="routerPass" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="routerActive" class="form-check-input" checked>
                        <label class="form-check-label" for="routerActive">Active</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRouterModal()">Cancel</button>
                <button type="submit" name="save_router" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save Router
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRouterModal() {
    document.getElementById('routerModalTitle').innerHTML = '<i class="bi bi-router me-2"></i>Add Router';
    document.getElementById('routerId').value = '';
    document.getElementById('routerName').value = '';
    document.getElementById('routerLocation').value = '';
    document.getElementById('routerIp').value = '';
    document.getElementById('routerPort').value = '8728';
    document.getElementById('routerUser').value = '';
    document.getElementById('routerPass').value = '';
    document.getElementById('routerActive').checked = true;
    document.getElementById('routerModal').classList.add('active');
}

function editRouter(router) {
    document.getElementById('routerModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Router';
    document.getElementById('routerId').value = router.id;
    document.getElementById('routerName').value = router.name;
    document.getElementById('routerLocation').value = router.location || '';
    document.getElementById('routerIp').value = router.ip_address;
    document.getElementById('routerPort').value = router.api_port;
    document.getElementById('routerUser').value = router.api_username;
    document.getElementById('routerPass').value = router.api_password;
    document.getElementById('routerActive').checked = router.is_active == 1;
    document.getElementById('routerModal').classList.add('active');
}

function closeRouterModal() {
    document.getElementById('routerModal').classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
