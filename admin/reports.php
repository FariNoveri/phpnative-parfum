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

// Product Performance (per volume)
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.nama_parfum,
        p.kategori,
        pv.volume_ml,
        pv.stock AS stok_volume,
        COALESCE(SUM(oi.jumlah), 0) AS total_sold_volume,
        COALESCE(SUM(oi.jumlah * oi.harga), 0) AS revenue_volume
    FROM products p
    JOIN product_volume_prices pv ON p.id = pv.product_id
    LEFT JOIN order_items oi ON oi.product_id = p.id AND oi.volume_selected = pv.volume_ml
    LEFT JOIN orders o ON oi.order_id = o.id 
        AND o.status IN ('confirmed', 'processing', 'shipped', 'delivered')
        AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id, pv.id
    ORDER BY p.nama_parfum ASC, pv.volume_ml ASC
");
$stmt->execute([$date_from, $date_to]);
$raw_performance = $stmt->fetchAll();

// Group and calculate totals
$perf = [];
foreach ($raw_performance as $row) {
    $pid = $row['id'];
    if (!isset($perf[$pid])) {
        $perf[$pid] = [
            'nama' => $row['nama_parfum'],
            'kategori' => $row['kategori'],
            'total_sold' => 0,
            'total_revenue' => 0,
            'volumes' => []
        ];
    }
    $perf[$pid]['volumes'][] = [
        'volume' => $row['volume_ml'],
        'sold' => (int)$row['total_sold_volume'],
        'revenue' => (float)$row['revenue_volume'],
        'stok' => (int)$row['stok_volume']
    ];
    $perf[$pid]['total_sold'] += (int)$row['total_sold_volume'];
    $perf[$pid]['total_revenue'] += (float)$row['revenue_volume'];
}

// Sort products by total sold desc
uasort($perf, function($a, $b) {
    if ($b['total_sold'] == $a['total_sold']) {
        return strcmp($a['nama'], $b['nama']);
    }
    return $b['total_sold'] <=> $a['total_sold'];
});

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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan & Analytics - Admin</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../img/logo.png">
    <link rel="shortcut icon" type="image/png" href="../img/logo.png">
    <link rel="apple-touch-icon" href="../img/logo.png">
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

.sidebar-logo {
    height: 60px;
    width: auto;
    margin-bottom: 15px;
    object-fit: contain;
}

.admin-logo {
    font-size: 24px;
    font-weight: 300;
    letter-spacing: 2px;
    color: #2c2c2c;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
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
        
        /* Adjusted styles from original reports */
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
            border-color: #c41e3a;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
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
            .filter-form {
                flex-direction: column;
                align-items: stretch;
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
        <img src="../img/logo.png" alt="UniqThings" class="sidebar-logo">
        <div class="admin-logo">Parfum Refill</div>
        <div class="admin-title">Admin Panel</div>
        <div class="admin-name"><?= $_SESSION['user_name'] ?></div>
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
                    <span>Selamat datang, <strong><?= $_SESSION['user_name'] ?></strong></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <!-- Filter -->
            <div class="section-card">
                <div class="section-header">📅 Filter Laporan</div>
                
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
                    </form>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="section-card">
                <div class="section-header">📊 Ringkasan Periode <?= date('d/m/Y', strtotime($date_from)) ?> - <?= date('d/m/Y', strtotime($date_to)) ?></div>
                
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
                <div class="section-card">
                    <div class="section-header">📈 Penjualan Harian</div>
                    
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
                <div class="section-card">
                    <div class="section-header">👑 Top Customers</div>
                    
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
            <div class="section-card">
                <div class="section-header">🧴 Performa Produk per Volume</div>
                
                <div class="report-section">
                    <?php if (empty($perf)): ?>
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
                                    <th>Volume (ml)</th>
                                    <th>Terjual</th>
                                    <th>Revenue</th>
                                    <th>Sisa Stok</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($perf as $p): ?>
                                    <?php 
                                    usort($p['volumes'], function($aa, $bb) {
                                        return $aa['volume'] <=> $bb['volume'];
                                    });
                                    $count = count($p['volumes']);
                                    $i = 0;
                                    ?>
                                    <?php foreach ($p['volumes'] as $v): ?>
                                        <tr>
                                            <?php if ($i === 0): ?>
                                                <td rowspan="<?= $count ?>">
                                                    <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                                        <?= htmlspecialchars($p['nama']) ?>
                                                    </div>
                                                </td>
                                                <td rowspan="<?= $count ?>"><?= ucfirst($p['kategori']) ?></td>
                                            <?php endif; ?>
                                            <td><?= $v['volume'] ?></td>
                                            <td><strong><?= $v['sold'] ?></strong> unit</td>
                                            <td><strong><?= formatRupiah($v['revenue']) ?></strong></td>
                                            <td>
                                                <span style="color: <?= $v['stok'] <= 5 ? '#e74c3c' : '#27ae60' ?>;">
                                                    <?= $v['stok'] ?> unit
                                                </span>
                                            </td>
                                        </tr>
                                        <?php $i++; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>