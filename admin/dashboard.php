<?php
require_once 'config/auth.php';
requireAdmin();

$stats = getStatistics();

// Get recent orders
$stmt = $pdo->prepare("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id GROUP BY o.id ORDER BY o.created_at DESC LIMIT 5");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Get low stock products
$stmt = $pdo->prepare("SELECT * FROM products WHERE stok <= 5 ORDER BY stok ASC LIMIT 5");
$stmt->execute();
$low_stock = $stmt->fetchAll();

// Get total reviews and pending reviews
$stmt_reviews = $pdo->prepare("SELECT COUNT(*) as total FROM product_reviews WHERE status = 'approved'");
$stmt_reviews->execute();
$total_reviews = $stmt_reviews->fetchColumn() ?? 0;

$stmt_pending = $pdo->prepare("SELECT COUNT(*) as pending FROM product_reviews WHERE status = 'pending'");
$stmt_pending->execute();
$pending_reviews = $stmt_pending->fetchColumn() ?? 0;

// Get sales data by category for chart
$stmt = $pdo->prepare("
    SELECT p.kategori, SUM(oi.jumlah * oi.harga) as total_sales 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.status != 'cancelled' 
    GROUP BY p.kategori
");
$stmt->execute();
$category_sales = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get top products overall
$stmt = $pdo->prepare("
    SELECT p.nama_parfum, SUM(oi.jumlah) as total_sold 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.status != 'cancelled' 
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 10
");
$stmt->execute();
$top_products = $stmt->fetchAll();
$top_labels = [];
$top_data = [];
foreach ($top_products as $tp) {
    $top_labels[] = $tp['nama_parfum'];
    $top_data[] = $tp['total_sold'];
}

// Get top for wanita
$stmt = $pdo->prepare("
    SELECT p.nama_parfum, SUM(oi.jumlah) as total_sold 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.status != 'cancelled' AND p.kategori = 'wanita'
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 10
");
$stmt->execute();
$top_wanita = $stmt->fetchAll();
$top_wanita_labels = [];
$top_wanita_data = [];
foreach ($top_wanita as $tp) {
    $top_wanita_labels[] = $tp['nama_parfum'];
    $top_wanita_data[] = $tp['total_sold'];
}

// Get top for pria
$stmt = $pdo->prepare("
    SELECT p.nama_parfum, SUM(oi.jumlah) as total_sold 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.status != 'cancelled' AND p.kategori = 'pria'
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 10
");
$stmt->execute();
$top_pria = $stmt->fetchAll();
$top_pria_labels = [];
$top_pria_data = [];
foreach ($top_pria as $tp) {
    $top_pria_labels[] = $tp['nama_parfum'];
    $top_pria_data[] = $tp['total_sold'];
}

// Get top for unisex
$stmt = $pdo->prepare("
    SELECT p.nama_parfum, SUM(oi.jumlah) as total_sold 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.status != 'cancelled' AND p.kategori = 'unisex'
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 10
");
$stmt->execute();
$top_unisex = $stmt->fetchAll();
$top_unisex_labels = [];
$top_unisex_data = [];
foreach ($top_unisex as $tp) {
    $top_unisex_labels[] = $tp['nama_parfum'];
    $top_unisex_data[] = $tp['total_sold'];
}

// Get revenue trend (last 30 days)
$order_trend = [];
$today = new DateTime();
for ($i = 29; $i >= 0; $i--) {
    $date = clone $today;
    $date->modify("-$i days");
    $date_str = $date->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT SUM(total_harga) FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'");
    $stmt->execute([$date_str]);
    $order_trend[$date_str] = $stmt->fetchColumn() ?? 0;
}

// Get revenue breakdowns
$stmt = $pdo->prepare("SELECT SUM(total_harga) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'");
$stmt->execute();
$revenue_today = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(total_harga) FROM orders WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE()) AND status != 'cancelled'");
$stmt->execute();
$revenue_week = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(total_harga) FROM orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status != 'cancelled'");
$stmt->execute();
$revenue_month = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(total_harga) FROM orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND status != 'cancelled'");
$stmt->execute();
$revenue_year = $stmt->fetchColumn() ?? 0;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UniqThings</title>
    <meta name="description" content="Dashboard administrator UniqThings.">
    <link rel="icon" href="../img/logo.png" type="image/png">
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
            background-color: #fff;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #fff;
            color: #2c2c2c;
            padding: 2rem 1rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .sidebar-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .admin-logo {
            max-width: 80px;
            margin: 0 auto 0.5rem;
            display: block;
        }
        
        .admin-title {
            font-size: 1.2rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .admin-name {
            font-size: 0.9rem;
            color: #999;
            margin-top: 0.5rem;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            color: #2c2c2c;
            text-decoration: none;
            padding: 1rem;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: #ffeef5;
            opacity: 1;
            color: #c41e3a;
        }
        
        .nav-icon {
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }
        
        /* Top Bar */
        .top-bar {
            background: #fff;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 1px;
            color: #2c2c2c;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logout-btn {
            background: #c41e3a;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .logout-btn:hover {
            background: #a01628;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #c41e3a;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 400;
            color: #c41e3a;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .section-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #c41e3a 0%, #a01628 100%);
            color: white;
            padding: 1.5rem 2rem;
            font-size: 1.3rem;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        .section-content {
            padding: 2rem;
        }
        
        .chart-container {
            height: 300px;
            width: 100%;
            margin-bottom: 1rem;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-info h4 {
            margin-bottom: 0.25rem;
            color: #2c2c2c;
        }
        
        .order-meta {
            font-size: 0.9rem;
            color: #999;
        }
        
        .order-status {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #e2e3e5; color: #383d41; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-name {
            font-weight: 400;
            margin-bottom: 0.25rem;
            color: #2c2c2c;
        }
        
        .product-brand {
            font-size: 0.9rem;
            color: #999;
        }
        
        .stock-count {
            font-weight: 400;
            color: #c41e3a;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-left: 3px solid;
            font-size: 14px;
            border-radius: 5px;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #22c55e;
        }
        
        .alert-warning {
            background: #fef2f2;
            color: #991b1b;
            border-color: #ef4444;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            background: #c41e3a;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            cursor: pointer;
            display: inline-block;
        }
        
        .btn:hover {
            background: #a01628;
        }
        
        .btn-secondary {
            background: transparent;
            color: #666;
            border: 1px solid #e0e0e0;
        }
        
        .btn-secondary:hover {
            border-color: #c41e3a;
            color: #c41e3a;
        }
        
        /* Top Bar */
        .top-bar-global {
            background: #f8f8f8;
            padding: 8px 0;
            font-size: 12px;
            text-align: center;
            color: #666;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .chart-container {
                height: 200px;
            }
            
            .top-bar-global {
                font-size: 11px;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../img/logo.png" alt="UniqThings" class="admin-logo">
                <div class="admin-title">Admin Panel</div>
                <div class="admin-name"><?= $_SESSION['user_name'] ?></div>
            </div>
            
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <span class="nav-icon">📊</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link">
                            <span class="nav-icon">📦</span>
                            Kelola Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="orders.php" class="nav-link">
                            <span class="nav-icon">🛒</span>
                            Kelola Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reviews.php" class="nav-link">
                            <span class="nav-icon">⭐</span>
                            Kelola Review
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="nav-link">
                            <span class="nav-icon">👥</span>
                            Kelola User
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
                            <span class="nav-icon">📈</span>
                            Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link">
                            <span class="nav-icon">⚙️</span>
                            Pengaturan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link" target="_blank">
                            <span class="nav-icon">🌐</span>
                            Lihat Website
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Dashboard</h1>
                <div class="user-info">
                    <span>Selamat datang, <strong><?= $_SESSION['user_name'] ?></strong></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-number"><?= $stats['total_products'] ?></div>
                    <div class="stat-label">Total Produk</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-number"><?= $stats['total_orders'] ?></div>
                    <div class="stat-label">Total Pesanan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?= $stats['total_users'] ?></div>
                    <div class="stat-label">Total User</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-number"><?= $stats['pending_orders'] ?></div>
                    <div class="stat-label">Pesanan Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-number"><?= $total_reviews ?></div>
                    <div class="stat-label">Total Review</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-number"><?= $pending_reviews ?></div>
                    <div class="stat-label">Review Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number"><?= formatRupiah($stats['total_revenue']) ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-number"><?= $stats['today_orders'] ?></div>
                    <div class="stat-label">Pesanan Hari Ini</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-number"><?= formatRupiah($revenue_today) ?></div>
                    <div class="stat-label">Revenue Hari Ini</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-number"><?= formatRupiah($revenue_week) ?></div>
                    <div class="stat-label">Revenue Minggu Ini</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📉</div>
                    <div class="stat-number"><?= formatRupiah($revenue_month) ?></div>
                    <div class="stat-label">Revenue Bulan Ini</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-number"><?= formatRupiah($revenue_year) ?></div>
                    <div class="stat-label">Revenue Tahun Ini</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Charts -->
                <div class="section-card">
                    <div class="section-header"> Statistik Penjualan</div>
                    <div class="section-content">
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="orderTrendChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="topProductsChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="topWanitaChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="topPriaChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="topUnisexChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="section-card">
                    <div class="section-header"> Stok Menipis</div>
                    <div class="section-content">
                        <?php if (empty($low_stock)): ?>
                            <p style="text-align: center; color: #666; padding: 2rem;">
                                 Semua produk stok aman
                            </p>
                        <?php else: ?>
                            <?php foreach ($low_stock as $product): ?>
                                <div class="product-item">
                                    <div>
                                        <div class="product-name"><?= htmlspecialchars($product['nama_parfum']) ?></div>
                                        <div class="product-brand"><?= htmlspecialchars($product['brand']) ?></div>
                                    </div>
                                    <div class="stock-count"><?= $product['stok'] ?> tersisa</div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="alert alert-warning" style="margin-top: 1rem;">
                                 <strong>Perhatian:</strong> Ada produk dengan stok rendah yang perlu direstok segera.
                            </div>
                        <?php endif; ?>
                        
                        <div class="quick-actions">
                            <a href="products.php" class="btn btn-secondary">Kelola Stok</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="section-card">
                    <div class="section-header"> Pesanan Terbaru</div>
                    <div class="section-content">
                        <?php if (empty($recent_orders)): ?>
                            <p style="text-align: center; color: #666; padding: 2rem;">
                                Belum ada pesanan
                            </p>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <h4>Pesanan #<?= $order['id'] ?></h4>
                                        <div class="order-meta">
                                            <?= $order['nama_customer'] ?> • 
                                            <?= $order['item_count'] ?> item • 
                                            <?= formatRupiah($order['total_harga']) ?> •
                                            <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="order-status status-<?= $order['status'] ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div class="quick-actions">
                            <a href="orders.php" class="btn">Lihat Semua Pesanan</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: 2rem;">
                <div class="section-card">
                    <div class="section-header"> Aksi Cepat</div>
                    <div class="section-content">
                        <div class="quick-actions">
                            <a href="products.php?action=add" class="btn"> Tambah Produk</a>
                            <a href="orders.php?status=pending" class="btn btn-secondary"> Cek Pesanan Pending</a>
                            <a href="reviews.php" class="btn"> Kelola Review</a>
                            <a href="reports.php" class="btn btn-secondary"> Lihat Laporan</a>
                            <a href="../index.php" target="_blank" class="btn"> Preview Website</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About Us</h3>
                    <p>Premium refill perfumes with authentic quality and affordable prices. 100% customer satisfaction guaranteed.</p>
                </div>
                <div class="footer-section">
                    <h3>Customer Service</h3>
                    <a href="tel:+6281234567890">📞 +62812-3456-7890</a>
                    <a href="mailto:cs@parfumrefill.com">✉️ cs@parfumrefill.com</a>
                    <p>🕒 Mon - Sat: 09:00 - 21:00</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="#">Track Order</a>
                    <a href="#">Shipping Info</a>
                    <a href="#">Return Policy</a>
                    <a href="#">FAQ</a>
                </div>
                <div class="footer-section">
                    <h3>Our Guarantee</h3>
                    <p>✅ 100% Original Scent</p>
                    <p>🛡️ Money Back Guarantee</p>
                    <p>🚚 Free Shipping (min. Rp 500K)</p>
                    <p>⭐ 4.8/5 Rating from 1000+ reviews</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 UniqThings. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Chart.js for Category Sales
        const ctxCategory = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctxCategory, {
            type: 'pie',
            data: {
                labels: ['Pria', 'Wanita', 'Unisex'],
                datasets: [{
                    data: [
                        <?= $category_sales['pria'] ?? 0 ?>,
                        <?= $category_sales['wanita'] ?? 0 ?>,
                        <?= $category_sales['unisex'] ?? 0 ?>
                    ],
                    backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Penjualan per Kategori' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Chart.js for Revenue Trend
        const ctxOrderTrend = document.getElementById('orderTrendChart').getContext('2d');
        new Chart(ctxOrderTrend, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($order_trend)) ?>,
                datasets: [{
                    label: 'Pendapatan Harian',
                    data: <?= json_encode(array_values($order_trend)) ?>,
                    borderColor: '#c41e3a',
                    backgroundColor: 'rgba(196, 30, 58, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Tren Pendapatan (30 Hari Terakhir)' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Chart.js for Top Products Overall
        const ctxTop = document.getElementById('topProductsChart').getContext('2d');
        new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: <?= json_encode($top_labels) ?>,
                datasets: [{
                    label: 'Unit Terjual',
                    data: <?= json_encode($top_data) ?>,
                    backgroundColor: '#c41e3a'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Top 10 Produk Terlaris Overall' }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Chart.js for Top Wanita
        const ctxWanita = document.getElementById('topWanitaChart').getContext('2d');
        new Chart(ctxWanita, {
            type: 'bar',
            data: {
                labels: <?= json_encode($top_wanita_labels) ?>,
                datasets: [{
                    label: 'Unit Terjual',
                    data: <?= json_encode($top_wanita_data) ?>,
                    backgroundColor: '#FF6384'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Top 10 Produk Wanita' }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Chart.js for Top Pria
        const ctxPria = document.getElementById('topPriaChart').getContext('2d');
        new Chart(ctxPria, {
            type: 'bar',
            data: {
                labels: <?= json_encode($top_pria_labels) ?>,
                datasets: [{
                    label: 'Unit Terjual',
                    data: <?= json_encode($top_pria_data) ?>,
                    backgroundColor: '#36A2EB'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Top 10 Produk Pria' }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Chart.js for Top Unisex
        const ctxUnisex = document.getElementById('topUnisexChart').getContext('2d');
        new Chart(ctxUnisex, {
            type: 'bar',
            data: {
                labels: <?= json_encode($top_unisex_labels) ?>,
                datasets: [{
                    label: 'Unit Terjual',
                    data: <?= json_encode($top_unisex_data) ?>,
                    backgroundColor: '#FFCE56'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Top 10 Produk Unisex' }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>