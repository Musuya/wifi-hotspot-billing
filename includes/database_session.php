<?php
/**
 * includes/database_session.php
 * Starts a PHP session once, with sane security flags.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // uncomment once you're running on HTTPS
    ]);
    session_start();
}
