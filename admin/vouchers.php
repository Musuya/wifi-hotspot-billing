<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$db = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $packageId = (int)$_POST['package_id'];
    $quantity = max(1, min(500, (int)$_POST['quantity']));
    $batchLabel = trim($_POST['batch_label']) ?: ('Batch ' . date('Y-m-d H:i'));

    $stmt = $db->prepare("INSERT INTO vouchers (code, package_id, batch_label) VALUES (?, ?, ?)");
    $generated = [];
    for ($i = 0; $i < $quantity; $i++) {
        // Retry on the rare collision since code is UNIQUE
        $attempts = 0;
        while ($attempts < 5) {
            $code = generateVoucherCode(8);
            try {
                $stmt->execute([$code, $packageId, $batchLabel]);
                $generated[] = $code;
                break;
            } catch (PDOException $e) {
                $attempts++;
            }
        }
    }
    $message = count($generated) . " vouchers generated in batch \"$batchLabel\".";
}

$packages = $db->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

$filterBatch = $_GET['batch'] ?? '';
$sql = "SELECT v.*, p.name as package_name FROM vouchers v JOIN packages p ON v.package_id = p.id";
$params = [];
if ($filterBatch) {
    $sql .= " WHERE v.batch_label = ?";
    $params[] = $filterBatch;
}
$sql .= " ORDER BY v.id DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$vouchers = $stmt->fetchAll();

$batches = $db->query("SELECT DISTINCT batch_label FROM vouchers ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/_nav.php';
?>
<link rel="stylesheet" href="assets/admin.css">

<h1>Vouchers</h1>
<?php if ($message): ?><p class="flash"><?= htmlspecialchars($message) ?></p><?php endif; ?>

<div class="panel">
    <h2>Generate Vouchers</h2>
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="generate">
        <div>
            <label>Package</label>
            <select name="package_id" required>
                <?php foreach ($packages as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> - <?= formatMoney($p['price']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>Quantity</label><input type="number" name="quantity" value="10" min="1" max="500" required></div>
        <div><label>Batch Label (optional)</label><input type="text" name="batch_label" placeholder="e.g. June printed batch"></div>
        <div class="form-actions"><button type="submit">Generate</button></div>
    </form>
</div>

<div class="panel">
    <h2>Voucher List</h2>
    <form method="GET" style="margin-bottom:12px;">
        <select name="batch" onchange="this.form.submit()">
            <option value="">All batches</option>
            <?php foreach ($batches as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>" <?= $filterBatch === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <table>
        <thead><tr><th>Code</th><th>Package</th><th>Batch</th><th>Status</th><th>Used At</th></tr></thead>
        <tbody>
        <?php foreach ($vouchers as $v): ?>
            <tr>
                <td><code><?= htmlspecialchars($v['code']) ?></code></td>
                <td><?= htmlspecialchars($v['package_name']) ?></td>
                <td><?= htmlspecialchars($v['batch_label'] ?? '—') ?></td>
                <td><span class="badge badge-<?= $v['status'] === 'unused' ? 'completed' : 'pending' ?>"><?= $v['status'] ?></span></td>
                <td><?= htmlspecialchars($v['used_at'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="hint">Showing latest 200 vouchers. Use the batch filter to find a specific print run, or export from the database for printing onto physical cards.</p>
</div>

</main></div>
