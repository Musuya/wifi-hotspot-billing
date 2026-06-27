<?php
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_mpesa'])) {
        updateSetting('mpesa_consumer_key', $_POST['consumer_key'] ?? '');
        updateSetting('mpesa_consumer_secret', $_POST['consumer_secret'] ?? '');
        updateSetting('mpesa_shortcode', $_POST['shortcode'] ?? '174379');
        updateSetting('mpesa_passkey', $_POST['passkey'] ?? '');
        updateSetting('mpesa_env', $_POST['env'] ?? 'sandbox');
        updateSetting('callback_url', $_POST['callback_url'] ?? '');
        $success = "M-Pesa settings saved!";
    }

    if (isset($_POST['save_general'])) {
        updateSetting('business_name', $_POST['business_name'] ?? '');
        updateSetting('business_phone', $_POST['business_phone'] ?? '');
        updateSetting('business_email', $_POST['business_email'] ?? '');
        updateSetting('currency', $_POST['currency'] ?? 'Ksh');
        updateSetting('timezone', $_POST['timezone'] ?? 'Africa/Nairobi');
        $success = "General settings saved!";
    }
}

$settings = getSettings();
?>

    <div class="page-header">
        <h1>Settings</h1>
        <p>Configure your billing system preferences</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- General Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-shop me-2"></i>Business Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Business Name</label>
                                    <input type="text" name="business_name" class="form-control" value="<?= e($settings['business_name'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Business Phone</label>
                                    <input type="text" name="business_phone" class="form-control" value="<?= e($settings['business_phone'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Business Email</label>
                                    <input type="email" name="business_email" class="form-control" value="<?= e($settings['business_email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-control">
                                        <option value="Ksh" <?= ($settings['currency'] ?? '') == 'Ksh' ? 'selected' : '' ?>>Ksh (Kenya)</option>
                                        <option value="UGX" <?= ($settings['currency'] ?? '') == 'UGX' ? 'selected' : '' ?>>UGX (Uganda)</option>
                                        <option value="TZS" <?= ($settings['currency'] ?? '') == 'TZS' ? 'selected' : '' ?>>TZS (Tanzania)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Timezone</label>
                                    <select name="timezone" class="form-control">
                                        <option value="Africa/Nairobi" <?= ($settings['timezone'] ?? '') == 'Africa/Nairobi' ? 'selected' : '' ?>>Nairobi</option>
                                        <option value="Africa/Kampala" <?= ($settings['timezone'] ?? '') == 'Africa/Kampala' ? 'selected' : '' ?>>Kampala</option>
                                        <option value="Africa/Dar_es_Salaam" <?= ($settings['timezone'] ?? '') == 'Africa/Dar_es_Salaam' ? 'selected' : '' ?>>Dar es Salaam</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="save_general" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- M-Pesa Settings -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-phone me-2"></i>M-Pesa (Daraja) Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Consumer Key</label>
                                    <input type="text" name="consumer_key" class="form-control" value="<?= e($settings['mpesa_consumer_key'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Consumer Secret</label>
                                    <input type="password" name="consumer_secret" class="form-control" value="<?= e($settings['mpesa_consumer_secret'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Shortcode</label>
                                    <input type="text" name="shortcode" class="form-control" value="<?= e($settings['mpesa_shortcode'] ?? '174379') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Passkey</label>
                                    <input type="password" name="passkey" class="form-control" value="<?= e($settings['mpesa_passkey'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Environment</label>
                                    <select name="env" class="form-control">
                                        <option value="sandbox" <?= ($settings['mpesa_env'] ?? '') == 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                                        <option value="production" <?= ($settings['mpesa_env'] ?? '') == 'production' ? 'selected' : '' ?>>Production</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Callback URL</label>
                            <input type="url" name="callback_url" class="form-control" value="<?= e($settings['callback_url'] ?? '') ?>" placeholder="https://yourdomain.com/api/mpesa_callback.php">
                        </div>
                        <button type="submit" name="save_mpesa" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save M-Pesa Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-info-circle me-2"></i>System Info</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">PHP Version</small>
                        <span class="fw-semibold"><?= phpversion() ?></span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Database</small>
                        <span class="fw-semibold">MySQL</span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Server Time</small>
                        <span class="fw-semibold"><?= date('Y-m-d H:i:s') ?></span>
                    </div>
                    <div>
                        <small class="text-muted d-block">App Version</small>
                        <span class="fw-semibold">v2.0 Modern</span>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card" style="border-color:var(--danger);">
                <div class="card-header">
                    <h5 style="color:var(--danger);"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">These actions cannot be undone. Be careful!</p>
                    <button class="btn btn-danger w-100 mb-2" onclick="confirmAction('Clear all transaction history?', () => { window.location='settings.php?clear_transactions=1'; })">
                        <i class="bi bi-trash"></i> Clear Transactions
                    </button>
                    <button class="btn btn-danger w-100" onclick="confirmAction('Reset all settings to default?', () => { window.location='settings.php?reset=1'; })">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset All Settings
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>