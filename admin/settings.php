<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['site_name','mpesa_shortcode','mpesa_passkey','mpesa_consumer_key','mpesa_consumer_secret','mpesa_env','currency'];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            updateSetting($f, trim($_POST[$f]));
        }
    }
    $message = 'Settings saved.';
}

$settings = getSettings();
include __DIR__ . '/_nav.php';
?>
<link rel="stylesheet" href="assets/admin.css">

<h1>Settings</h1>
<?php if ($message): ?><p class="flash"><?= htmlspecialchars($message) ?></p><?php endif; ?>

<div class="panel">
    <h2>General</h2>
    <form method="POST" class="form-grid">
        <div><label>Site Name</label><input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>"></div>
        <div><label>Currency</label><input type="text" name="currency" value="<?= htmlspecialchars($settings['currency'] ?? 'KES') ?>"></div>

        <h3 style="grid-column:1/-1; margin-top:10px;">M-Pesa Daraja Credentials</h3>
        <p class="hint" style="grid-column:1/-1;">
            Get these from <a href="https://developer.safaricom.co.ke" target="_blank">developer.safaricom.co.ke</a>
            after creating an app. Use "sandbox" while testing, switch to "production" when you have a paybill/till
            number approved for Lipa Na M-Pesa Online.
        </p>
        <div><label>Environment</label>
            <select name="mpesa_env">
                <option value="sandbox" <?= ($settings['mpesa_env'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                <option value="production" <?= ($settings['mpesa_env'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option>
            </select>
        </div>
        <div><label>Shortcode (Paybill/Till)</label><input type="text" name="mpesa_shortcode" value="<?= htmlspecialchars($settings['mpesa_shortcode'] ?? '') ?>"></div>
        <div><label>Passkey</label><input type="text" name="mpesa_passkey" value="<?= htmlspecialchars($settings['mpesa_passkey'] ?? '') ?>"></div>
        <div><label>Consumer Key</label><input type="text" name="mpesa_consumer_key" value="<?= htmlspecialchars($settings['mpesa_consumer_key'] ?? '') ?>"></div>
        <div><label>Consumer Secret</label><input type="text" name="mpesa_consumer_secret" value="<?= htmlspecialchars($settings['mpesa_consumer_secret'] ?? '') ?>"></div>

        <div class="form-actions"><button type="submit">Save Settings</button></div>
    </form>
</div>

</main></div>
