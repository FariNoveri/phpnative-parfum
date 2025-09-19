<?php
// admin/config/auth.php
require_once '../config/database.php';

function isAdmin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireAdmin() {
    if (!isAdmin()) {
        $_SESSION['message'] = 'Akses ditolak. Anda harus login sebagai admin.';
        $_SESSION['message_type'] = 'error';
        redirect('../admin_login.php');
    }
}

function getAdminId() {
    return $_SESSION['user_id'] ?? null;
}

function logAdminActivity($action, $description = '') {
    global $pdo;
    if (isAdmin()) {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)");
        $stmt->execute([getAdminId(), $action, $description]);
    }
}

function getStatistics() {
    global $pdo;
    
    $stats = [];
    
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $stats['total_products'] = $stmt->fetch()['total'];
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $stats['total_orders'] = $stmt->fetch()['total'];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    // Pending orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
    $stats['pending_orders'] = $stmt->fetch()['total'];
    
    // Total revenue
    $stmt = $pdo->query("SELECT SUM(total_harga) as total FROM orders WHERE status IN ('confirmed', 'processing', 'shipped', 'delivered')");
    $stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;
    
    // Today's orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
    $stats['today_orders'] = $stmt->fetch()['total'];
    
    return $stats;
}
?>