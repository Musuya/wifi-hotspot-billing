<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$db = getDB();
$status = $_GET['status'] ?? '';

$sql = "SELECT t.*, p.name as package_name FROM transactions t LEFT JOIN packages p ON t.package_id = p.id";
$params = [];
if ($status) {
    $sql .= " WHERE t.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY t.created_at DESC LIMIT 300";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

include __DIR__ . '/_nav.php';
?>
<link rel="stylesheet" href="assets/admin.css">

<h1>Transactions</h1>

<div class="panel">
    <form method="GET" style="margin-bottom:12px;">
        <select name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['pending','completed','failed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <table>
        <thead><tr><th>Date</th><th>Phone</th><th>Package</th><th>Amount</th><th>Status</th><th>M-Pesa Receipt</th><th>MAC</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $tx): ?>
            <tr>
                <td><?= htmlspecialchars($tx['created_at']) ?></td>
                <td><?= htmlspecialchars($tx['phone_number']) ?></td>
                <td><?= htmlspecialchars($tx['package_name'] ?? '—') ?></td>
                <td><?= formatMoney($tx['amount']) ?></td>
                <td><span class="badge badge-<?= $tx['status'] ?>"><?= $tx['status'] ?></span></td>
                <td><?= htmlspecialchars($tx['mpesa_receipt_number'] ?? '—') ?></td>
                <td><?= htmlspecialchars($tx['mac_address'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</main></div>
