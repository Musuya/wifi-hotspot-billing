<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../mikrotik/HotspotManager.php';
requireLogin();

$db = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disconnect') {
    $sessionId = (int)$_POST['session_id'];
    $stmt = $db->prepare("SELECT s.*, r.* FROM sessions s JOIN routers r ON s.router_id = r.id WHERE s.id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if ($session) {
        $hotspot = new HotspotManager($session);
        $hotspot->removeUser($session['hotspot_username']);
        $hotspot->disconnectActiveSession($session['hotspot_username']);
        $db->prepare("UPDATE sessions SET status = 'disconnected' WHERE id = ?")->execute([$sessionId]);
        $message = 'User disconnected.';
    }
}

// Mark expired sessions (housekeeping - in production run this via a cron job too)
$db->exec("UPDATE sessions SET status = 'expired' WHERE status = 'active' AND expires_at <= NOW()");

$sessions = $db->query(
    "SELECT s.*, r.name as router_name FROM sessions s
     JOIN routers r ON s.router_id = r.id
     ORDER BY s.created_at DESC LIMIT 200"
)->fetchAll();

include __DIR__ . '/_nav.php';
?>
<link rel="stylesheet" href="assets/admin.css">

<h1>Sessions</h1>
<?php if ($message): ?><p class="flash"><?= htmlspecialchars($message) ?></p><?php endif; ?>

<div class="panel">
    <table>
        <thead><tr><th>Username</th><th>Router</th><th>MAC</th><th>Expires</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['hotspot_username']) ?></td>
                <td><?= htmlspecialchars($s['router_name']) ?></td>
                <td><?= htmlspecialchars($s['mac_address'] ?? '—') ?></td>
                <td><?= htmlspecialchars($s['expires_at']) ?></td>
                <td><span class="badge badge-<?= $s['status'] === 'active' ? 'completed' : 'pending' ?>"><?= $s['status'] ?></span></td>
                <td>
                    <?php if ($s['status'] === 'active'): ?>
                    <form method="POST" onsubmit="return confirm('Disconnect this user now?')">
                        <input type="hidden" name="action" value="disconnect">
                        <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn-danger">Disconnect</button>
                    </form>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</main></div>
