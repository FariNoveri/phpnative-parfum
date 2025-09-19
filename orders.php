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
$order_stats = $stmt->fetchAll(PDO::FETCH_GROUP);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Toko Parfum Premium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .main-content {
            padding: 3rem 0;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 3rem;
            color: #333;
            font-size: 2.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .status-pending .stat-number { color: #f39c12; }
        .status-confirmed .stat-number { color: #27ae60; }
        .status-processing .stat-number { color: #3498db; }
        .status-shipped .stat-number { color: #9b59b6; }
        .status-delivered .stat-number { color: #2ecc71; }
        .status-cancelled .stat-number { color: #e74c3c; }
        
        .filters-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .filters-title {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            padding: 0.7rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            min-width: 200px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-warning {
            background: #f39c12;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .orders-container {
            display: grid;
            gap: 1.5rem;
        }
        
        .order-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-id {
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .order-date {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .order-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            background: rgba(255,255,255,0.2);
        }
        
        .order-body {
            padding: 2rem;
        }
        
        .order-summary {
            display: grid;
            gri<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    $_SESSION['message'] = 'Silakan login terlebih dahulu';
    $_SESSION['message_type'] = 'error';
    redirect('login.php');
}

// Get user orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([getUserId()]);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Toko Parfum Premium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .main-content {
            padding: 3rem 0;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 3rem;
            color: #333;
            font-size: 2.5rem;
        }
        
        .orders-container {
            display: grid;
            gap: 2rem;
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-id {
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .order-date {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .order-body {
            padding: 1.5rem;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #333;
        }
        
        .order-total {
            font-size: 1.3rem;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .order-status {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-processing {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #99d6ff;
        }
        
        .status-shipped {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .order-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .empty-orders {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .empty-orders h3 {
            margin-bottom: 1rem;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .order-info {
                grid-template-columns: 1fr;
            }
            
            .order-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">🌸 Parfum Premium</div>
            <div class="nav-links">
                <a href="index.php">Beranda</a>
                <a href="cart.php">Keranjang</a>
                <a href="orders.php">Pesanan</a>
                <a href="profile.php">Profil</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <h1>Pesanan Saya</h1>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="orders-container">
                <?php if (empty($orders)): ?>
                    <div class="empty-orders">
                        <h3>Belum ada pesanan</h3>
                        <p>Anda belum memiliki pesanan. Mulai berbelanja sekarang!</p>
                        <br>
                        <a href="index.php" class="btn">Mulai Belanja</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        // Get order items count
                        $stmt = $pdo->prepare("SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?");
                        $stmt->execute([$order['id']]);
                        $item_count = $stmt->fetch()['item_count'];
                        
                        // Generate WhatsApp link for pending orders
                        $wa_number = "6281234567890";
                        $wa_message = "Halo, saya ingin menanyakan status pesanan #" . $order['id'] . " atas nama " . $order['nama_customer'] . ". Terima kasih!";
                        $wa_link = "https://wa.me/{$wa_number}?text=" . urlencode($wa_message);
                        ?>
                        
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <div class="order-id">Pesanan #<?= $order['id'] ?></div>
                                    <div class="order-date"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?></div>
                                </div>
                                <div class="order-status status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </div>
                            </div>
                            
                            <div class="order-body">
                                <div class="order-info">
                                    <div class="info-item">
                                        <span class="info-label">Total Pembayaran</span>
                                        <span class="info-value order-total"><?= formatRupiah($order['total_harga']) ?></span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-label">Jumlah Item</span>
                                        <span class="info-value"><?= $item_count ?> produk</span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-label">Alamat Pengiriman</span>
                                        <span class="info-value"><?= htmlspecialchars(substr($order['alamat_customer'], 0, 50)) ?><?= strlen($order['alamat_customer']) > 50 ? '...' : '' ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($order['notes']): ?>
                                    <div class="info-item" style="margin-top: 1rem;">
                                        <span class="info-label">Catatan</span>
                                        <span class="info-value"><?= htmlspecialchars($order['notes']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="order-actions">
                                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn">Lihat Detail</a>
                                    
                                    <?php if ($order['status'] == 'pending'): ?>
                                        <a href="<?= $wa_link ?>" target="_blank" class="btn btn-success">
                                            📱 Konfirmasi Pembayaran
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($order['status'], ['confirmed', 'processing', 'shipped'])): ?>
                                        <a href="<?= $wa_link ?>" target="_blank" class="btn">
                                            📱 Tanya Status
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
</body>
</html>