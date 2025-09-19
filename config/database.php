<?php
// config/database.php
$host = 'localhost';
$dbname = 'toko_parfum';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate session ID if not exists
if (!isset($_SESSION['session_id'])) {
    $_SESSION['session_id'] = session_id();
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function redirect($url) {
    header("Location: $url");
    exit();
}
?>