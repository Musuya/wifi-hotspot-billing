<?php
/**
 * includes/auth.php
 * Simple session-based admin authentication.
 */

require_once __DIR__ . '/database_session.php'; // starts session safely
require_once __DIR__ . '/database.php';

function attemptLogin(string $username, string $password): bool {
    $stmt = getDB()->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];

        $upd = getDB()->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
        $upd->execute([$admin['id']]);
        return true;
    }
    return false;
}

function isLoggedIn(): bool {
    return isset($_SESSION['admin_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}

function currentAdmin(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'role' => $_SESSION['admin_role'],
    ];
}
