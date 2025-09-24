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

// Get sales data by category for chart
$stmt = $pdo->prepare("SELECT kategori, COUNT(*) as count FROM products GROUP BY kategori");
$stmt->execute();
$category_sales = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get order trend (last 7 days)
$order_trend = [];
$today = new DateTime();
for ($i = 6; $i >= 0; $i--) {
    $date = clone $today;
    $date->modify("-$i days");
    $date_str = $date->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?");
    $stmt->execute([$date_str]);
    $order_trend[$date_str] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Toko Parfum Premium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem 1rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .admin-logo {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .admin-title {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .admin-name {
            font-size: 0.9rem;
            opacity: 0.7;
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
            color: white;
            text-decoration: none;
            padding: 1rem;
            border-radius: 10px;
            transition: all 0.3s;
            opacity: 0.8;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            opacity: 1;
            transform: translateX(5px);
        }
        
        .nav-icon {
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }
        
        .top-bar {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-size: 2rem;
            color: #333;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1e3c72;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            font-size: 1.3rem;
            font-weight: bold;
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
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-info h4 {
            margin-bottom: 0.25rem;
            color: #333;
        }
        
        .order-meta {
            font-size: 0.9rem;
            color: #666;
        }
        
        .order-status {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
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
            border-bottom: 1px solid #eee;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .product-brand {
            font-size: 0.9rem;
            color: #666;
        }
        
        .stock-count {
            font-weight: bold;
            color: #e74c3c;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            border: 1px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        
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
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="admin-logo">🌸</div>
                <div class="admin-title">Admin Panel</div>
                <div class="admin-name">👋 <?= $_SESSION['user_name'] ?></div>
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
                            <span class="nav-icon">🧴</span>
                            Kelola Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="orders.php" class="nav-link">
                            <span class="nav-icon">📦</span>
                            Kelola Pesanan
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
                    <div class="stat-icon">🧴</div>
                    <div class="stat-number"><?= $stats['total_products'] ?></div>
                    <div class="stat-label">Total Produk</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
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
                    <div class="stat-icon">💰</div>
                    <div class="stat-number"><?= formatRupiah($stats['total_revenue']) ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-number"><?= $stats['today_orders'] ?></div>
                    <div class="stat-label">Pesanan Hari Ini</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Charts -->
                <div class="section-card">
                    <div class="section-header">📊 Statistik Penjualan</div>
                    <div class="section-content">
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="orderTrendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="section-card">
                    <div class="section-header">📦 Pesanan Terbaru</div>
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
                                        <?= $order['status'] ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div class="quick-actions">
                            <a href="orders.php" class="btn">Lihat Semua Pesanan</a>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="section-card">
                    <div class="section-header">⚠️ Stok Menipis</div>
                    <div class="section-content">
                        <?php if (empty($low_stock)): ?>
                            <p style="text-align: center; color: #666; padding: 2rem;">
                                ✅ Semua produk stok aman
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
                                ⚠️ <strong>Perhatian:</strong> Ada produk dengan stok rendah yang perlu direstok segera.
                            </div>
                        <?php endif; ?>
                        
                        <div class="quick-actions">
                            <a href="products.php" class="btn btn-warning">Kelola Stok</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: 2rem;">
                <div class="section-card">
                    <div class="section-header">🚀 Aksi Cepat</div>
                    <div class="section-content">
                        <div class="quick-actions">
                            <a href="products.php?action=add" class="btn btn-success">➕ Tambah Produk</a>
                            <a href="orders.php?status=pending" class="btn btn-warning">⏳ Cek Pesanan Pending</a>
                            <a href="reports.php" class="btn">📊 Lihat Laporan</a>
                            <a href="../index.php" target="_blank" class="btn">🌐 Preview Website</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

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
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Distribusi Produk per Kategori' }
                }
            }
        });

        // Chart.js for Order Trend
        const ctxOrderTrend = document.getElementById('orderTrendChart').getContext('2d');
        new Chart(ctxOrderTrend, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($order_trend)) ?>,
                datasets: [{
                    label: 'Jumlah Pesanan',
                    data: <?= json_encode(array_values($order_trend)) ?>,
                    borderColor: '#36A2EB',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Tren Pesanan (7 Hari Terakhir)' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>