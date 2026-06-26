<?php
/**
 * install.php
 *
 * Run this ONCE in your browser after importing sql/schema.sql, to create
 * your first admin login. It generates a real bcrypt password hash (so you
 * don't end up with a broken hardcoded one) and then deletes itself.
 *
 * Visit: http://yourserver/install.php
 */

require_once __DIR__ . '/includes/helpers.php';

$db = getDB();
$existingAdmins = $db->query("SELECT COUNT(*) as cnt FROM admins")->fetch()['cnt'];

$message = '';
$done = false;

if ($existingAdmins > 0) {
    $message = "An admin account already exists. For security, delete install.php now.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($username) < 3 || strlen($password) < 6) {
        $message = "Username must be 3+ characters and password 6+ characters.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            "INSERT INTO admins (username, password_hash, full_name, role) VALUES (?, ?, 'System Administrator', 'superadmin')"
        );
        $stmt->execute([$username, $hash]);
        $done = true;

        // Self-delete for security - comment this out if you want to keep it around, but you really shouldn't.
        @unlink(__FILE__);
    }
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Install</title>
<style>body{font-family:sans-serif;max-width:400px;margin:60px auto;padding:20px;}
input{width:100%;padding:10px;margin:8px 0;box-sizing:border-box;}
button{padding:10px 20px;background:#1e3c72;color:#fff;border:none;border-radius:6px;cursor:pointer;}</style>
</head>
<body>
<h2>WiFi Billing System Setup</h2>

<?php if ($done): ?>
    <p style="color:green;">✅ Admin account created! This installer has deleted itself.</p>
    <p><a href="admin/login.php">Go to admin login</a></p>
<?php elseif ($message): ?>
    <p><?= htmlspecialchars($message) ?></p>
    <p><a href="admin/login.php">Go to admin login</a></p>
<?php else: ?>
    <p>Create your first admin account:</p>
    <form method="POST">
        <input type="text" name="username" placeholder="Admin username" required>
        <input type="password" name="password" placeholder="Password (6+ characters)" required>
        <button type="submit">Create Admin Account</button>
    </form>
<?php endif; ?>

</body>
</html>
