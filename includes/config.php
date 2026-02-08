<?php
session_start();
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'maslahastore_db');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper: Nigerian Naira format
/*function format_naira($amount) {
    return '₦' . number_format($amount, 2);
}
*/
// Check role permission
function require_role($roles) {
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], (array)$roles)) {
        header('Location: login.php');
        exit;
    }
}