<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../mikrotik/HotspotManager.php';
requireLogin();

$db = getDB();
$message = '';
$testResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $ip = trim($_POST['ip_address']);
        $port = (int)$_POST['api_port'];
        $user = trim($_POST['api_username']);
        $pass = $_POST['api_password'];
        $hotspotServer = trim($_POST['hotspot_server']) ?: 'all';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0) {
            // Only update password if a new one was entered
            if ($pass) {
                $stmt = $db->prepare("UPDATE routers SET name=?, ip_address=?, api_port=?, api_username=?, api_password=?, hotspot_server=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $ip, $port, $user, $pass, $hotspotServer, $isActive, $id]);
            } else {
                $stmt = $db->prepare("UPDATE routers SET name=?, ip_address=?, api_port=?, api_username=?, hotspot_server=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $ip, $port, $user, $hotspotServer, $isActive, $id]);
            }
            $message = 'Router updated.';
        } else {
            $stmt = $db->prepare("INSERT INTO routers (name, ip_address, api_port, api_username, api_password, hotspot_server, is_active) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$name, $ip, $port, $user, $pass, $hotspotServer, $isActive]);
            $message = 'Router added.';
        }
    } elseif ($action === 'test') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("SELECT * FROM routers WHERE id = ?");
        $stmt->execute([$id]);
        $router = $stmt->fetch();
        if ($router) {
            $hotspot = new HotspotManager($router);
            $testResult = $hotspot->testConnection()
                ? "✅ Successfully connected to {$router['name']}."
                : "❌ Could not connect to {$router['name']}. Check IP/credentials and that the API service is enabled on the router.";
        }
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM routers WHERE id = ?")->execute([(int)$_POST['id']]);
        $message = 'Router removed.';
    }
}

$routers = $db->query("SELECT * FROM routers ORDER BY id")->fetchAll();

include __DIR__ . '/_nav.php';
?>
<link rel="stylesheet" href="assets/admin.css">

<h1>Routers</h1>
<?php if ($message): ?><p class="flash"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($testResult): ?><p class="flash"><?= htmlspecialchars($testResult) ?></p><?php endif; ?>

<div class="panel">
    <h2>Add / Edit Router</h2>
    <p class="hint">
        On the MikroTik router, make sure the API service is enabled:
        <code>IP &gt; Services &gt; api</code> (default port 8728). The user you enter here needs
        "api" and "read/write" permissions in <code>System &gt; Users</code>.
    </p>
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="rEditId" value="">
        <div><label>Name</label><input type="text" name="name" id="rEditName" required></div>
        <div><label>IP Address</label><input type="text" name="ip_address" id="rEditIp" placeholder="192.168.88.1" required></div>
        <div><label>API Port</label><input type="number" name="api_port" id="rEditPort" value="8728" required></div>
        <div><label>API Username</label><input type="text" name="api_username" id="rEditUser" required></div>
        <div><label>API Password <?= '' ?></label><input type="password" name="api_password" id="rEditPass" placeholder="leave blank to keep existing"></div>
        <div><label>Hotspot Server Name</label><input type="text" name="hotspot_server" id="rEditServer" value="all"></div>
        <div class="checkbox-row"><label><input type="checkbox" name="is_active" id="rEditActive" checked> Active</label></div>
        <div class="form-actions">
            <button type="submit">Save Router</button>
            <button type="button" onclick="resetRouterForm()">Clear / New</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Configured Routers</h2>
    <table>
        <thead><tr><th>Name</th><th>IP</th><th>Port</th><th>Hotspot Server</th><th>Active</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($routers as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['ip_address']) ?></td>
                <td><?= $r['api_port'] ?></td>
                <td><?= htmlspecialchars($r['hotspot_server']) ?></td>
                <td><?= $r['is_active'] ? 'Yes' : 'No' ?></td>
                <td>
                    <button onclick='editRouter(<?= json_encode($r) ?>)'>Edit</button>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="test">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit">Test Connection</button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Remove this router?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function editRouter(r) {
    document.getElementById('rEditId').value = r.id;
    document.getElementById('rEditName').value = r.name;
    document.getElementById('rEditIp').value = r.ip_address;
    document.getElementById('rEditPort').value = r.api_port;
    document.getElementById('rEditUser').value = r.api_username;
    document.getElementById('rEditPass').value = '';
    document.getElementById('rEditServer').value = r.hotspot_server;
    document.getElementById('rEditActive').checked = r.is_active == 1;
    window.scrollTo(0, 0);
}
function resetRouterForm() {
    document.getElementById('rEditId').value = '';
    document.querySelector('.form-grid').reset();
}
</script>

</main></div>
