<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$db = getDB();
$message = '';

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $duration = (int)$_POST['duration_minutes'];
        $rateLimit = trim($_POST['rate_limit']) ?: null;
        $dataLimit = !empty($_POST['data_limit_mb']) ? (int)$_POST['data_limit_mb'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0) {
            $stmt = $db->prepare(
                "UPDATE packages SET name=?, description=?, price=?, duration_minutes=?, rate_limit=?, data_limit_mb=?, is_active=? WHERE id=?"
            );
            $stmt->execute([$name, $description, $price, $duration, $rateLimit, $dataLimit, $isActive, $id]);
            $message = 'Package updated.';
        } else {
            $stmt = $db->prepare(
                "INSERT INTO packages (name, description, price, duration_minutes, rate_limit, data_limit_mb, is_active) VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->execute([$name, $description, $price, $duration, $rateLimit, $dataLimit, $isActive]);
            $message = 'Package created.';
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM packages WHERE id = ?")->execute([$id]);
        $message = 'Package deleted.';
    }
}

$packages = $db->query("SELECT * FROM packages ORDER BY sort_order ASC, id ASC")->fetchAll();

include __DIR__ . '/_nav.php';
?>
<link rel="stylesheet" href="assets/admin.css">

<h1>Packages</h1>
<?php if ($message): ?><p class="flash"><?= htmlspecialchars($message) ?></p><?php endif; ?>

<div class="panel">
    <h2>Add / Edit Package</h2>
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="editId" value="">

        <div><label>Name</label><input type="text" name="name" id="editName" required></div>
        <div><label>Description</label><input type="text" name="description" id="editDescription"></div>
        <div><label>Price (KES)</label><input type="number" step="0.01" name="price" id="editPrice" required></div>
        <div><label>Duration (minutes)</label><input type="number" name="duration_minutes" id="editDuration" required></div>
        <div><label>Rate Limit (e.g. 2M/2M)</label><input type="text" name="rate_limit" id="editRateLimit" placeholder="optional"></div>
        <div><label>Data Limit (MB)</label><input type="number" name="data_limit_mb" id="editDataLimit" placeholder="leave blank = unlimited"></div>
        <div class="checkbox-row"><label><input type="checkbox" name="is_active" id="editActive" checked> Active</label></div>

        <div class="form-actions">
            <button type="submit">Save Package</button>
            <button type="button" onclick="resetForm()">Clear / New</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Existing Packages</h2>
    <table>
        <thead>
            <tr><th>Name</th><th>Price</th><th>Duration</th><th>Rate Limit</th><th>Data Cap</th><th>Active</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($packages as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= formatMoney($p['price']) ?></td>
                <td><?= $p['duration_minutes'] ?> min</td>
                <td><?= htmlspecialchars($p['rate_limit'] ?? '—') ?></td>
                <td><?= $p['data_limit_mb'] ? $p['data_limit_mb'] . ' MB' : 'Unlimited' ?></td>
                <td><?= $p['is_active'] ? 'Yes' : 'No' ?></td>
                <td>
                    <button onclick='editPackage(<?= json_encode($p) ?>)'>Edit</button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this package?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function editPackage(p) {
    document.getElementById('editId').value = p.id;
    document.getElementById('editName').value = p.name;
    document.getElementById('editDescription').value = p.description || '';
    document.getElementById('editPrice').value = p.price;
    document.getElementById('editDuration').value = p.duration_minutes;
    document.getElementById('editRateLimit').value = p.rate_limit || '';
    document.getElementById('editDataLimit').value = p.data_limit_mb || '';
    document.getElementById('editActive').checked = p.is_active == 1;
    window.scrollTo(0, 0);
}
function resetForm() {
    document.getElementById('editId').value = '';
    document.querySelector('.form-grid').reset();
}
</script>

</main></div>
