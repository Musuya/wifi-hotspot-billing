<?php
require_once __DIR__ . '/includes/header.php';

// Mark as read
if (isset($_GET['read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")
       ->execute([(int)$_GET['read']]);
}

if (isset($_GET['read_all'])) {
    $db->query("UPDATE notifications SET is_read = 1");
}

// Delete
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM notifications WHERE id = ?")
       ->execute([(int)$_GET['delete']]);
}

// Fetch notifications
$notifications = $db->query("
    SELECT * FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 100
")->fetchAll();

$unreadCount = $db->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Notifications</h1>
        <p>System alerts and messages</p>
    </div>
    <div class="d-flex gap-2">
        <a href="?read_all=1" class="btn btn-secondary">
            <i class="bi bi-check-all"></i> Mark All Read
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
        <div class="text-center py-5">
            <i class="bi bi-bell-slash" style="font-size:3rem;color:var(--text-muted);"></i>
            <p class="text-muted mt-3">No notifications yet</p>
        </div>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($notifications as $notif): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center p-4" style="background:<?= $notif['is_read'] ? 'var(--bg-card)' : 'var(--bg-hover)' ?>;border-bottom:1px solid var(--border-color);">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon <?= $notif['type'] == 'success' ? 'success' : ($notif['type'] == 'error' ? 'danger' : ($notif['type'] == 'warning' ? 'warning' : 'info')) ?>" style="width:44px;height:44px;font-size:1.125rem;">
                        <i class="bi bi-<?= $notif['type'] == 'success' ? 'check-circle' : ($notif['type'] == 'error' ? 'x-circle' : ($notif['type'] == 'warning' ? 'exclamation-triangle' : 'info-circle')) ?>"></i>
                    </div>
                    <div>
                        <div class="fw-semibold"><?= e($notif['title']) ?></div>
                        <p class="text-muted mb-0" style="font-size:0.875rem;"><?= e($notif['message']) ?></p>
                        <small class="text-muted"><?= date('M j, Y H:i', strtotime($notif['created_at'])) ?></small>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php if (!$notif['is_read']): ?>
                    <a href="?read=<?= $notif['id'] ?>" class="btn btn-sm btn-secondary" title="Mark as read">
                        <i class="bi bi-check"></i>
                    </a>
                    <?php endif; ?>
                    <a href="?delete=<?= $notif['id'] ?>" class="btn btn-sm btn-icon text-danger" onclick="return confirm('Delete this notification?')">
                        <i class="bi bi-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
