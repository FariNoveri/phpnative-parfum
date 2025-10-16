<?php
require_once 'config/auth.php';
requireAdmin();

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Sales Report
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as tanggal,
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status IN ('confirmed', 'processing', 'shipped', 'delivered') THEN 1 ELSE 0 END) as success_orders,
        SUM(CASE WHEN status IN ('confirmed', 'processing', 'shipped', 'delivered') THEN total_harga ELSE 0 END) as revenue,
        SUM(total_harga) as gross_revenue
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) DESC
");
$stmt->execute([$date_from, $date_to]);
$daily_sales = $stmt->fetchAll();

// Product Performance
$stmt = $pdo->prepare("
    SELECT 
        p.nama_parfum,
        p.kategori,
        p.harga,
        p.stok,
        SUM(oi.jumlah) as total_sold,
        SUM(oi.jumlah * oi.harga) as total_revenue,
        COUNT(DISTINCT o.id) as total_transactions
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status IN ('confirmed', 'processing', 'shipped', 'delivered')
    WHERE (o.created_at IS NULL OR DATE(o.created_at) BETWEEN ? AND ?)
    GROUP BY p.id
    ORDER BY total_sold DESC
");
$stmt->execute([$date_from, $date_to]);
$product_performance = $stmt->fetchAll();

// Customer Analytics
$stmt = $pdo->prepare("
    SELECT 
        u.nama,
        u.email,
        COUNT(o.id) as total_orders,
        SUM(CASE WHEN o.status IN ('confirmed', 'processing', 'shipped', 'delivered') THEN o.total_harga ELSE 0 END) as total_spent,
        MAX(o.created_at) as last_order
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE u.role = 'user' AND (o.created_at IS NULL OR DATE(o.created_at) BETWEEN ? AND ?)
    GROUP BY u.id
    HAVING total_orders > 0
    ORDER BY total_spent DESC
    LIMIT 20
");
$stmt->execute([$date_from, $date_to]);
$top_customers = $stmt->fetchAll();

// Summary Statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status IN ('confirmed', 'processing', 'shipped', 'delivered') THEN 1 ELSE 0 END) as success_orders,
        SUM(CASE WHEN status IN ('confirmed', 'processing', 'shipped', 'delivered') THEN total_harga ELSE 0 END) as total_revenue,
        AVG(CASE WHEN status IN ('confirmed', 'processing', 'shipped', 'delivered') THEN total_harga ELSE NULL END) as avg_order_value
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$date_from, $date_to]);
$summary = $stmt->fetch();

// Calculate totals for charts
$total_revenue = array_sum(array_column($daily_sales, 'revenue'));
$total_orders = array_sum(array_column($daily_sales, 'total_orders'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan & Analytics - Admin</title>
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
        
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .filters {
            padding: 2rem;
            border-bottom: 1px solid #eee;
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
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #555;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select {
            padding: 0.7rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
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
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .report-section {
            padding: 2rem;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .report-table th,
        .report-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .report-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #555;
        }
        
        .report-table tr:hover {
            background: #f8f9fa;
        }
        
        .chart-container {
            padding: 2rem;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #666;
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                padding: 1rem;
            }
            
            .report-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                        <a href="dashboard.php" class="nav-link">
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
                        <a href="reports.php" class="nav-link active">
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
                <h1 class="page-title">📈 Laporan & Analytics</h1>
                <div class="user-info">
                    <span><?= $_SESSION['user_name'] ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <!-- Filter -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">📅 Filter Laporan</div>
                </div>
                
                <div class="filters">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label>Dari Tanggal</label>
                            <input type="date" name="date_from" value="<?= $date_from ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Sampai Tanggal</label>
                            <input type="date" name="date_to" value="<?= $date_to ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn">📊 Generate Laporan</button>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" onclick="exportReport()" class="btn btn-success">📁 Export Excel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">📊 Ringkasan Periode <?= date('d/m/Y', strtotime($date_from)) ?> - <?= date('d/m/Y', strtotime($date_to)) ?></div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $summary['total_orders'] ?></div>
                        <div class="stat-label">Total Pesanan</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?= $summary['success_orders'] ?></div>
                        <div class="stat-label">Pesanan Berhasil</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?= formatRupiah($summary['total_revenue']) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?= formatRupiah($summary['avg_order_value'] ?? 0) ?></div>
                        <div class="stat-label">Rata-rata Order</div>
                    </div>
                </div>
            </div>

            <div class="report-grid">
                <!-- Daily Sales Report -->
                <div class="content-card">
                    <div class="card-header">
                        <div class="card-title">📈 Penjualan Harian</div>
                    </div>
                    
                    <div class="report-section">
                        <?php if (empty($daily_sales)): ?>
                            <div class="no-data">
                                <h3>Tidak ada data penjualan</h3>
                                <p>Belum ada transaksi dalam periode ini</p>
                            </div>
                        <?php else: ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Orders</th>
                                        <th>Sukses</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daily_sales as $day): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($day['tanggal'])) ?></td>
                                            <td><?= $day['total_orders'] ?></td>
                                            <td><?= $day['success_orders'] ?></td>
                                            <td><strong><?= formatRupiah($day['revenue']) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Customers -->
                <div class="content-card">
                    <div class="card-header">
                        <div class="card-title">👑 Top Customers</div>
                    </div>
                    
                    <div class="report-section">
                        <?php if (empty($top_customers)): ?>
                            <div class="no-data">
                                <p>Belum ada customer dengan transaksi</p>
                            </div>
                        <?php else: ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Orders</th>
                                        <th>Total Belanja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($top_customers, 0, 10) as $customer): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                                    <?= htmlspecialchars($customer['nama']) ?>
                                                </div>
                                                <div style="font-size: 0.9rem; color: #666;">
                                                    <?= htmlspecialchars($customer['email']) ?>
                                                </div>
                                            </td>
                                            <td><?= $customer['total_orders'] ?></td>
                                            <td><strong><?= formatRupiah($customer['total_spent']) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Product Performance -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">🧴 Performa Produk</div>
                </div>
                
                <div class="report-section">
                    <?php if (empty($product_performance)): ?>
                        <div class="no-data">
                            <h3>Tidak ada data produk</h3>
                            <p>Belum ada penjualan produk dalam periode ini</p>
                        </div>
                    <?php else: ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Kategori</th>
                                    <th>Terjual</th>
                                    <th>Revenue</th>
                                    <th>Sisa Stok</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($product_performance as $product): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                                <?= htmlspecialchars($product['nama_parfum']) ?>
                                            </div>
                                        </td>
                                        <td><?= ucfirst($product['kategori']) ?></td>
                                        <td>
                                            <strong><?= $product['total_sold'] ?? 0 ?></strong> unit
                                        </td>
                                        <td>
                                            <strong><?= formatRupiah($product['total_revenue'] ?? 0) ?></strong>
                                        </td>
                                        <td>
                                            <span style="color: <?= $product['stok'] <= 5 ? '#e74c3c' : '#27ae60' ?>;">
                                                <?= $product['stok'] ?> unit
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function exportReport() {
            // Simple CSV export
            const dateFrom = '<?= $date_from ?>';
            const dateTo = '<?= $date_to ?>';
            
            alert('Fitur export akan segera tersedia!\n\nUntuk sementara, Anda bisa:\n1. Copy data dari tabel\n2. Paste ke Excel\n3. Atau screenshot laporan ini');
            
            // TODO: Implement proper CSV/Excel export
        }

        // Auto refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
