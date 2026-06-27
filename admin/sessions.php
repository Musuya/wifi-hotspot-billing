<?php
require_once __DIR__ . '/includes/header.php';

// Handle actions
if (isset($_GET['kick'])) {
    $sessionId = (int)$_GET['kick'];
    $db->prepare("UPDATE sessions SET status = 'expired', expires_at = NOW() WHERE id = ?")
            ->execute([$sessionId]);
    $success = "Session terminated.";
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$routerFilter = $_GET['router'] ?? '';
$search = $_GET['search'] ?? '';

$where = ['1=1'];
$params = [];

if ($statusFilter) {
    $where[] = "s.status = ?";
    $params[] = $statusFilter;
}
if ($routerFilter) {
    $where[] = "s.router_id = ?";
    $params[] = $routerFilter;
}
if ($search) {
    $where[] = "(s.hotspot_username LIKE ? OR s.mac_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$sessions = $db->prepare("
    SELECT s.*, r.name as router_name
    FROM sessions s
    LEFT JOIN routers r ON s.router_id = r.id
    WHERE $whereClause
    ORDER BY s.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$sessions->execute($params);
$sessionList = $sessions->fetchAll();

$totalCount = $db->prepare("SELECT COUNT(*) FROM sessions s LEFT JOIN routers r ON s.router_id = r.id WHERE $whereClause");
$totalCount->execute($params);
$total = $totalCount->fetchColumn();
$totalPages = ceil($total / $perPage);

// Routers for filter
$routers = $db->query("SELECT id, name FROM routers ORDER BY name")->fetchAll();

// Stats
$stats = [
        'active' => $db->query("SELECT COUNT(*) FROM sessions WHERE status = 'active'")->fetchColumn(),
        'expired' => $db->query("SELECT COUNT(*) FROM sessions WHERE status = 'expired'")->fetchColumn(),
        'today' => $db->query("SELECT COUNT(*) FROM sessions WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
];

// Live active sessions for real-time
$activeSessions = $db->query("
    SELECT s.*, r.name as router_name, 
           TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as minutes_online
    FROM sessions s
    LEFT JOIN routers r ON s.router_id = r.id
    WHERE s.status = 'active'
    ORDER BY s.created_at DESC
    LIMIT 50
")->fetchAll();
?>

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Sessions</h1>
            <p>Monitor and manage active user sessions</p>
        </div>
        <div class="d-flex gap-2">
        <span class="badge badge-success" style="font-size:1rem;padding:0.5rem 1rem;">
            <span class="status-dot online"></span>
            <?= $stats['active'] ?> Online
        </span>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4 col-6">
            <div class="card stat-card">
                <div class="stat-icon success"><i class="bi bi-wifi"></i></div>
                <div class="stat-value" data-count="<?= $stats['active'] ?>">0</div>
                <div class="stat-label">Active Now</div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card stat-card">
                <div class="stat-icon secondary"><i class="bi bi-clock-history"></i></div>
                <div class="stat-value" data-count="<?= $stats['expired'] ?>">0</div>
                <div class="stat-label">Expired</div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card stat-card">
                <div class="stat-icon primary"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-value" data-count="<?= $stats['today'] ?>">0</div>
                <div class="stat-label">Today</div>
            </div>
        </div>
    </div>

    <!-- Active Sessions Live Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-broadcast me-2"></i>Live Active Sessions</h5>
            <div class="d-flex gap-2">
            <span class="badge badge-success animate-pulse">
                <span class="status-dot online"></span> Live
            </span>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="data-table w-100" id="liveSessionsTable">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Router</th>
                    <th>MAC Address</th>
                    <th>IP Address</th>
                    <th>Online For</th>
                    <th>Expires</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody id="liveSessionsBody">
                <?php foreach ($activeSessions as $s): ?>
                    <tr data-session-id="<?= $s['id'] ?>">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;background:var(--success);">
                                    <i class="bi bi-person"></i>
                                </div>
                                <span class="fw-semibold"><?= e($s['hotspot_username'] ?? 'User') ?></span>
                            </div>
                        </td>
                        <td>
                        <span class="badge badge-secondary">
                            <i class="bi bi-router me-1"></i><?= e($s['router_name'] ?? 'N/A') ?>
                        </span>
                        </td>
                        <td><code style="font-size:0.8125rem;"><?= e($s['mac_address'] ?? 'N/A') ?></code></td>
                        <td><code style="font-size:0.8125rem;"><?= e($s['ip_address'] ?? 'N/A') ?></code></td>
                        <td>
                        <span class="badge badge-info">
                            <?= $s['minutes_online'] ?> min
                        </span>
                        </td>
                        <td><small class="text-muted"><?= date('M j, H:i', strtotime($s['expires_at'])) ?></small></td>
                        <td>
                            <a href="?kick=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Kick this user?')">
                                <i class="bi bi-x-lg"></i> Kick
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- All Sessions -->
    <div class="card">
        <div class="card-header">
            <h5>All Sessions</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row align-items-end gap-3 mb-4">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search username, MAC..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="expired" <?= $statusFilter == 'expired' ? 'selected' : '' ?>>Expired</option>
                        <option value="disconnected" <?= $statusFilter == 'disconnected' ? 'selected' : '' ?>>Disconnected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="router" class="form-control" onchange="this.form.submit()">
                        <option value="">All Routers</option>
                        <?php foreach ($routers as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $routerFilter == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </form>

            <table class="data-table w-100" id="sessionsTable">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Router</th>
                    <th>MAC</th>
                    <th>IP</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Expires</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sessionList as $s): ?>
                    <tr>
                        <td>#<?= $s['id'] ?></td>
                        <td><?= e($s['hotspot_username'] ?? 'N/A') ?></td>
                        <td><?= e($s['router_name'] ?? 'N/A') ?></td>
                        <td><code style="font-size:0.75rem;"><?= e($s['mac_address'] ?? 'N/A') ?></code></td>
                        <td><code style="font-size:0.75rem;"><?= e($s['ip_address'] ?? 'N/A') ?></code></td>
                        <td>
                        <span class="badge badge-<?= $s['status'] == 'active' ? 'success' : ($s['status'] == 'expired' ? 'secondary' : 'warning') ?>">
                            <span class="status-dot <?= $s['status'] == 'active' ? 'online' : 'offline' ?>" style="width:6px;height:6px;"></span>
                            <?= e($s['status']) ?>
                        </span>
                        </td>
                        <td><small class="text-muted"><?= date('M j, H:i', strtotime($s['created_at'])) ?></small></td>
                        <td><small class="text-muted"><?= date('M j, H:i', strtotime($s['expires_at'])) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-3 d-flex justify-content-end">
                <nav>
                    <div class="d-flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&status=<?= e($statusFilter) ?>&router=<?= e($routerFilter) ?>&search=<?= e($search) ?>" class="btn btn-sm btn-secondary">&laquo;</a>
                        <?php endif; ?>
                        <span class="btn btn-sm btn-secondary">Page <?= $page ?> of <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page+1 ?>&status=<?= e($statusFilter) ?>&router=<?= e($routerFilter) ?>&search=<?= e($search) ?>" class="btn btn-sm btn-secondary">&raquo;</a>
                        <?php endif; ?>
                    </div>
                </nav>
            </div>
        </div>
    </div>

    <script>
        Realtime.start('../api/live_sessions.php', function(data) {
            // Update logic here
        }, 15000);

        TableManager.init('sessionsTable');
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>