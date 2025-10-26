<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    $_SESSION['message'] = 'Silakan login terlebih dahulu';
    $_SESSION['message_type'] = 'error';
    redirect('login.php');
}

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Get user orders with filters
$sql = "SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.jumlah) as total_items 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.user_id = ?";
$params = [getUserId()];

if ($status_filter) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $sql .= " AND (o.id LIKE ? OR o.nama_customer LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get order statistics
$stmt = $pdo->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        SUM(total_harga) as total_amount
    FROM orders 
    WHERE user_id = ? 
    GROUP BY status
");
$stmt->execute([getUserId()]);
$stats_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$order_stats = [];
foreach ($stats_raw as $stat) {
    $order_stats[$stat['status']] = $stat;
}

// Get cart count
$cart_count = 0;
$stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM cart WHERE user_id = ?");
$stmt->execute([getUserId()]);
$cart_result = $stmt->fetch();
$cart_count = $cart_result['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Parfum Refill Premium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #2c2c2c;
            background-color: #fafafa;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        .top-bar {
            background: #f8f8f8;
            padding: 8px 0;
            font-size: 12px;
            text-align: center;
            color: #666;
        }
        
        header {
            background: #fff;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: 2px;
            color: #2c2c2c;
            text-transform: uppercase;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 35px;
            align-items: center;
        }
        
        .nav-links a {
            color: #2c2c2c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: color 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .nav-links a:hover {
            color: #c41e3a;
        }
        
        .cart-icon {
            position: relative;
            cursor: pointer;
            font-size: 20px;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #c41e3a;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            padding: 60px 0;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 40px;
            text-align: center;
            color: #2c2c2c;
        }
        
        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 300;
            margin-bottom: 8px;
            display: block;
        }
        
        .stat-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-pending .stat-number { color: #f39c12; }
        .status-confirmed .stat-number { color: #27ae60; }
        .status-processing .stat-number { color: #3498db; }
        .status-shipped .stat-number { color: #9b59b6; }
        .status-delivered .stat-number { color: #2ecc71; }
        .status-cancelled .stat-number { color: #e74c3c; }
        
        /* Filters */
        .filters-card {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .filters-title {
            font-size: 14px;
            margin-bottom: 20px;
            color: #2c2c2c;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px;
            border: 1px solid #e0e0e0;
            font-size: 14px;
            transition: border-color 0.3s;
            background: #fafafa;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #c41e3a;
            background: white;
        }
        
        .btn {
            background: #c41e3a;
            color: white;
            padding: 12px 30px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        
        .btn:hover {
            background: #a01628;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(196, 30, 58, 0.3);
        }
        
        .btn-secondary {
            background: transparent;
            color: #666;
            border: 1px solid #e0e0e0;
        }
        
        .btn-secondary:hover {
            background: #2c2c2c;
            color: white;
            border-color: #2c2c2c;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        /* Orders */
        .orders-container {
            display: grid;
            gap: 20px;
        }
        
        .order-card {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .order-header {
            background: #2c2c2c;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-id {
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 1px;
        }
        
        .order-date {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 4px;
        }
        
        .order-status {
            padding: 6px 14px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-processing {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-shipped {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-body {
            padding: 30px;
        }
        
        .order-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            display: flex;
            flex-direction: column;
        }
        
        .summary-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-value {
            color: #2c2c2c;
            font-size: 14px;
        }
        
        .order-total {
            font-size: 20px;
            font-weight: 500;
            color: #c41e3a;
        }
        
        .order-notes {
            background: #fafafa;
            padding: 15px;
            margin-top: 15px;
            border-left: 3px solid #e0e0e0;
        }
        
        .notes-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        
        .notes-text {
            font-size: 14px;
            color: #666;
        }
        
        .order-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .empty-orders {
            text-align: center;
            padding: 80px 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .empty-orders h3 {
            font-size: 24px;
            font-weight: 300;
            margin-bottom: 15px;
            color: #2c2c2c;
        }
        
        .empty-orders p {
            font-size: 14px;
            color: #999;
            margin-bottom: 30px;
        }
        
        /* Alert */
        .alert {
            padding: 15px;
            margin-bottom: 30px;
            border-left: 3px solid;
            font-size: 14px;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #22c55e;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #ef4444;
        }
        
        /* Footer */
        footer {
            background: #f8f8f8;
            padding: 60px 0 30px;
            margin-top: 80px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-section h3 {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            color: #2c2c2c;
        }
        
        .footer-section p,
        .footer-section a {
            font-size: 13px;
            color: #666;
            text-decoration: none;
            line-height: 2;
            display: block;
        }
        
        .footer-section a:hover {
            color: #c41e3a;
        }
        
        .footer-bottom {
            border-top: 1px solid #e0e0e0;
            padding-top: 30px;
            text-align: center;
        }
        
        .footer-bottom p {
            font-size: 12px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .order-summary {
                grid-template-columns: 1fr;
            }
            
            .order-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
            
            .nav-links {
                gap: 15px;
            }
            
            .nav-links a {
                font-size: 12px;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        Track your orders • Fast & Secure Delivery
    </div>

    <!-- Header -->
    <header>
        <nav class="container">
            <a href="index.php" class="logo">Parfum Refill</a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="profile.php">Account</a>
                <a href="orders.php">Orders</a>
                <a href="logout.php">Logout</a>
                <a href="cart.php" class="cart-icon">
                    🛒
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-count"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <h1 class="page-title">My Orders</h1>

            <!-- Statistics -->
            <div class="stats-grid">
                <?php
                $all_count = array_sum(array_column($stats_raw, 'count'));
                $statuses = [
                    ['key' => 'all', 'label' => 'All Orders', 'count' => $all_count],
                    ['key' => 'pending', 'label' => 'Pending', 'count' => $order_stats['pending']['count'] ?? 0],
                    ['key' => 'confirmed', 'label' => 'Confirmed', 'count' => $order_stats['confirmed']['count'] ?? 0],
                    ['key' => 'processing', 'label' => 'Processing', 'count' => $order_stats['processing']['count'] ?? 0],
                    ['key' => 'shipped', 'label' => 'Shipped', 'count' => $order_stats['shipped']['count'] ?? 0],
                    ['key' => 'delivered', 'label' => 'Delivered', 'count' => $order_stats['delivered']['count'] ?? 0],
                ];
                
                foreach ($statuses as $status):
                    if ($status['count'] > 0 || $status['key'] == 'all'):
                ?>
                    <div class="stat-card status-<?= $status['key'] ?>">
                        <span class="stat-number"><?= $status['count'] ?></span>
                        <span class="stat-label"><?= $status['label'] ?></span>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-card">
                <div class="filters-title">Filter Orders</div>
                <form class="filter-form" method="GET">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" 
                               placeholder="Order ID or Name" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $status_filter == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="processing" <?= $status_filter == 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= $status_filter == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= $status_filter == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Apply</button>
                    <a href="orders.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>

            <!-- Orders List -->
            <div class="orders-container">
                <?php if (empty($orders)): ?>
                    <div class="empty-orders">
                        <h3>No orders found</h3>
                        <p>You haven't placed any orders yet. Start shopping now!</p>
                        <a href="index.php" class="btn">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        // Generate WhatsApp link
                        $wa_number = "6281234567890";
                        $wa_message = "Halo, saya ingin menanyakan status pesanan #" . $order['id'] . " atas nama " . $order['nama_customer'] . ". Terima kasih!";
                        $wa_link = "https://wa.me/{$wa_number}?text=" . urlencode($wa_message);
                        ?>
                        
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <div class="order-id">Order #<?= $order['id'] ?></div>
                                    <div class="order-date"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?></div>
                                </div>
                                <div class="order-status status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </div>
                            </div>
                            
                            <div class="order-body">
                                <div class="order-summary">
                                    <div class="summary-item">
                                        <span class="summary-label">Total Payment</span>
                                        <span class="summary-value order-total"><?= formatRupiah($order['total_harga']) ?></span>
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span class="summary-label">Total Items</span>
                                        <span class="summary-value"><?= $order['total_items'] ?> products (<?= $order['item_count'] ?> types)</span>
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span class="summary-label">Delivery Address</span>
                                        <span class="summary-value"><?= htmlspecialchars(substr($order['alamat_customer'], 0, 50)) ?><?= strlen($order['alamat_customer']) > 50 ? '...' : '' ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($order['notes']): ?>
                                    <div class="order-notes">
                                        <div class="notes-label">Notes</div>
                                        <div class="notes-text"><?= htmlspecialchars($order['notes']) ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="order-actions">
                                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn">View Details</a>
                                    
                                    <?php if ($order['status'] == 'pending'): ?>
                                        <a href="<?= $wa_link ?>" target="_blank" class="btn btn-success">
                                            Confirm Payment
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($order['status'], ['confirmed', 'processing', 'shipped'])): ?>
                                        <a href="<?= $wa_link ?>" target="_blank" class="btn btn-secondary">
                                            Ask Status
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About Us</h3>
                    <p>Premium refill perfumes with authentic quality and affordable prices.</p>
                </div>
                <div class="footer-section">
                    <h3>Customer Service</h3>
                    <a href="tel:+6281234567890">📞 +62812-3456-7890</a>
                    <a href="mailto:cs@parfumrefill.com">✉️ cs@parfumrefill.com</a>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="#">Track Order</a>
                    <a href="#">Shipping Info</a>
                    <a href="#">Return Policy</a>
                </div>
                <div class="footer-section">
                    <h3>Our Guarantee</h3>
                    <p>✅ 100% Original Scent</p>
                    <p>🛡️ Money Back Guarantee</p>
                    <p>🚚 Free Shipping</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Parfum Refill Premium. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>