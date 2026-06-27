<?php
// Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

try {
    require_once __DIR__ . '/includes/database.php';
    require_once __DIR__ . '/includes/helpers.php';
    $db = getDB();

    $packages = $db->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC")->fetchAll();
    $settings = getSettings();

    echo "<h1>Diagnostic Results</h1>";
    echo "<pre>";
    echo "Packages found: " . count($packages) . "\n";
    echo "Settings keys: " . implode(', ', array_keys($settings)) . "\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "</pre>";

} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}