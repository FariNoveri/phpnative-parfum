<?php
require_once 'config/auth.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$order_id = (int)($_GET['id'] ?? 0);
$load_more = $_GET['load_more'] ?? 0;
$offset = (int)($_GET['offset'] ?? 0);
$limit = 5;

// Handle status update
if ($_POST && $action === 'update_status') {
    $new_status = $_POST['status'];
    $order_id = (int)$_POST['order_id'];
    
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $order_id])) {
        // Get order info for logging
        $stmt = $pdo->prepare("SELECT nama_customer FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $customer_name = $stmt->fetch()['nama_customer'] ?? '';
        
        logAdminActivity('UPDATE_ORDER_STATUS', "Mengubah status pesanan #$order_id ($customer_name) menjadi $new_status");
        $_SESSION['message'] = "Status pesanan berhasil diubah menjadi " . ucfirst($new_status);
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Gagal mengubah status pesanan';
        $_SESSION['message_type'] = 'error';
    }
    redirect('orders.php');
}

// Handle AJAX load more
if ($load_more) {
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $sort = $_GET['sort'] ?? 'created_at DESC';

    $sql = "SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.jumlah) as total_items 
            FROM orders o 
            LEFT JOIN order_items oi ON o.id = oi.order_id 
            WHERE 1=1";
    $params = [];

    if ($status_filter) {
        $sql .= " AND o.status = ?";
        $params[] = $status_filter;
    }

    if ($search) {
        $sql .= " AND (o.nama_customer LIKE ? OR o.email_customer LIKE ? OR o.id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($date_from) {
        $sql .= " AND DATE(o.created_at) >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $sql .= " AND DATE(o.created_at) <= ?";
        $params[] = $date_to;
    }

    $sql .= " GROUP BY o.id ORDER BY o.$sort LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    $html = '';
    foreach ($orders as $order) {
        $html .= '<tr>
                    <td><div class="order-id">#'. $order['id'] .'</div></td>
                    <td>
                        <div class="customer-info">
                            <div class="customer-name">'. htmlspecialchars($order['nama_customer']) .'</div>
                            <div class="customer-contact">
                                📧 '. htmlspecialchars($order['email_customer']) .'<br>
                                📱 '. htmlspecialchars($order['telepon_customer']) .'
                            </div>
                        </div>
                    </td>
                    <td>'. $order['total_items'] .' item'. ($order['total_items'] > 1 ? 's' : '') .'</td>
                    <td><strong>'. formatRupiah($order['total_harga']) .'</strong></td>
                    <td><div class="order-status status-'. $order['status'] .'">'. $order['status'] .'</div></td>
                    <td>'. date('d/m/Y H:i', strtotime($order['created_at'])) .'</td>
                    <td>
                        <div class="actions">
                            <button onclick="viewOrder('. $order['id'] .')" class="btn btn-sm">👁️ Lihat</button>
                            <button onclick="updateStatus('. $order['id'] .', \''. $order['status'] .'\')" class="btn btn-sm btn-warning">✏️ Status</button>
                        </div>
                    </td>
                  </tr>';
    }

    header('Content-Type: application/json');
    echo json_encode(['html' => $html, 'has_more' => count($orders) == $limit]);
    exit;
}

// Get orders with filters for initial load
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'created_at DESC';

$sql = "SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.jumlah) as total_items 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE 1=1";
$params = [];

if ($status_filter) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $sql .= " AND (o.nama_customer LIKE ? OR o.email_customer LIKE ? OR o.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_from) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " GROUP BY o.id ORDER BY o.$sort LIMIT $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get order detail if viewing specific order
$order_detail = null;
$order_items = [];
if ($action === 'view' && $order_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order_detail = $stmt->fetch();
    
    if ($order_detail) {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.nama_parfum 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll();
    }
}

// Statistics
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - UniqThings</title>
    <meta name="description" content="Kelola pesanan pelanggan di panel admin UniqThings.">
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 400;
            color: #c41e3a;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .content-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #c41e3a 0%, #a01628 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        .filters {
            padding: 2rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 400;
            margin-bottom: 0.5rem;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input,
        .form-group select {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            background: #fafafa;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #c41e3a;
            background: #fff;
        }
        
        .btn {
            background: #c41e3a;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 400;
        }
        
        .btn:hover {
            background: #a01628;
        }
        
        .btn-success {
            background: #c41e3a;
        }
        
        .btn-success:hover {
            background: #a01628;
        }
        
        .btn-warning {
            background: #c41e3a;
        }
        
        .btn-warning:hover {
            background: #a01628;
        }
        
        .btn-danger {
            background: #c41e3a;
        }
        
        .btn-danger:hover {
            background: #a01628;
        }
        
        .btn-sm {
            padding: 8px 20px;
            font-size: 12px;
        }
        
        .table-container {
            padding: 2rem;
            overflow-x: auto;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 15px 10px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .orders-table th {
            background: #fafafa;
            font-weight: 400;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .orders-table tr:hover {
            background: #ffeef5;
        }
        
        .order-id {
            font-weight: 400;
            color: #c41e3a;
        }
        
        .customer-info {
            margin-bottom: 5px;
        }
        
        .customer-name {
            font-weight: 400;
            color: #2c2c2c;
        }
        
        .customer-contact {
            font-size: 12px;
            color: #999;
        }
        
        .order-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            min-width: 80px;
            display: inline-block;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #e2e3e5; color: #383d41; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            padding: 2rem;
        }
        
        .modal-content {
            background: #fff;
            margin: 0 auto;
            border-radius: 10px;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #c41e3a 0%, #a01628 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: #ffeef5;
        }
        
        .order-detail-section {
            margin-bottom: 2rem;
        }
        
        .order-detail-section h4 {
            margin-bottom: 1rem;
            color: #2c2c2c;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e0e0e0;
            font-size: 16px;
            font-weight: 400;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .detail-item {
            margin-bottom: 0.5rem;
        }
        
        .detail-label {
            font-weight: 400;
            color: #666;
            margin-bottom: 0.25rem;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            color: #2c2c2c;
            font-size: 14px;
        }
        
        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .order-items-table th,
        .order-items-table td {
            padding: 15px 10px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-items-table th {
            background: #fafafa;
            font-weight: 400;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #ef4444;
        }
        
        .loading-indicator {
            text-align: center;
            padding: 2rem;
            color: #999;
            display: none;
        }
        
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #c41e3a;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .no-more {
            text-align: center;
            padding: 2rem;
            color: #999;
            font-style: italic;
            display: none;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .top-bar {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            .filter-form {
                grid-template-columns: 1fr;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .table-container {
                padding: 1rem;
            }
            .orders-table th,
            .orders-table td {
                padding: 10px 5px;
                font-size: 12px;
            }
        }
    </style>
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
                        <a href="dashboard.php" class="nav-link">
                            <span class="nav-icon">📊</span> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link">
                            <span class="nav-icon">🧴</span> Kelola Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="orders.php" class="nav-link active">
                            <span class="nav-icon">🛒</span> Kelola Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reviews.php" class="nav-link">
                            <span class="nav-icon">⭐</span> Kelola Review
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="nav-link">
                            <span class="nav-icon">👥</span> Kelola User
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
                            <span class="nav-icon">📈</span> Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link">
                            <span class="nav-icon">⚙️</span> Pengaturan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link" target="_blank">
                            <span class="nav-icon">🌐</span> Lihat Website
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <div style="display: flex; align-items: center;">
                    <h1 class="page-title">📦 Kelola Pesanan</h1>
                </div>
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

            <!-- Status Statistics -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?= $status_stats['pending'] ?? 0 ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $status_stats['confirmed'] ?? 0 ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $status_stats['processing'] ?? 0 ?></div>
                    <div class="stat-label">Processing</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $status_stats['shipped'] ?? 0 ?></div>
                    <div class="stat-label">Shipped</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $status_stats['delivered'] ?? 0 ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $status_stats['cancelled'] ?? 0 ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>

            <!-- Orders List -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">Daftar Pesanan</div>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <form method="GET" class="filter-form" id="filterForm">
                        <div class="form-group">
                            <label>Cari Pesanan</label>
                            <input type="text" name="search" placeholder="ID, nama, atau email..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">Semua Status</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Dari Tanggal</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Sampai Tanggal</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Urutkan</label>
                            <select name="sort">
                                <option value="created_at DESC" <?= $sort === 'created_at DESC' ? 'selected' : '' ?>>Terbaru</option>
                                <option value="created_at ASC" <?= $sort === 'created_at ASC' ? 'selected' : '' ?>>Terlama</option>
                                <option value="total_harga DESC" <?= $sort === 'total_harga DESC' ? 'selected' : '' ?>>Nilai Tertinggi</option>
                                <option value="total_harga ASC" <?= $sort === 'total_harga ASC' ? 'selected' : '' ?>>Nilai Terendah</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn">🔍 Cari</button>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <a href="orders.php" class="btn btn-secondary">🔄 Reset</a>
                        </div>
                    </form>
                </div>
                
                <!-- Orders Table -->
                <div class="table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>ID Pesanan</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><div class="order-id">#<?= $order['id'] ?></div></td>
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-name"><?= htmlspecialchars($order['nama_customer']) ?></div>
                                            <div class="customer-contact">
                                                📧 <?= htmlspecialchars($order['email_customer']) ?><br>
                                                📱 <?= htmlspecialchars($order['telepon_customer']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $order['total_items'] ?> item<?= $order['total_items'] > 1 ? 's' : '' ?></td>
                                    <td><strong><?= formatRupiah($order['total_harga']) ?></strong></td>
                                    <td><div class="order-status status-<?= $order['status'] ?>"><?= $order['status'] ?></div></td>
                                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <div class="actions">
                                            <button onclick="viewOrder(<?= $order['id'] ?>)" class="btn btn-sm">👁️ Lihat</button>
                                            <button onclick="updateStatus(<?= $order['id'] ?>, '<?= $order['status'] ?>')" class="btn btn-sm btn-secondary">✏️ Status</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div id="loading" class="loading-indicator">
                        <div class="loading-spinner"></div>
                        Memuat...
                    </div>
                    
                    <div id="no-more" class="no-more">
                        Sudah tidak ada lagi
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Order Detail Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Detail Pesanan</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Status Pesanan</h3>
                <button class="close-modal" onclick="closeStatusModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="orders.php?action=update_status">
                    <input type="hidden" name="order_id" id="statusOrderId">
                    
                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label for="statusSelect">Status Baru:</label>
                        <select name="status" id="statusSelect" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" onclick="closeStatusModal()" class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-success">💾 Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentOffset = <?= $limit ?>;
        let isLoading = false;
        let hasMore = true;
        let filterParams = new URLSearchParams(window.location.search);

        const tbody = document.getElementById('ordersTableBody');
        const loadingEl = document.getElementById('loading');
        const noMoreEl = document.getElementById('no-more');

        function loadMoreOrders() {
            if (isLoading || !hasMore) return;
            
            isLoading = true;
            loadingEl.style.display = 'block';
            
            const url = `orders.php?load_more=1&offset=${currentOffset}&${filterParams.toString()}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    isLoading = false;
                    loadingEl.style.display = 'none';
                    
                    if (data.html) {
                        tbody.insertAdjacentHTML('beforeend', data.html);
                        currentOffset += <?= $limit ?>;
                    }
                    
                    hasMore = data.has_more;
                    if (!hasMore) {
                        noMoreEl.style.display = 'block';
                    }
                })
                .catch(error => {
                    isLoading = false;
                    loadingEl.style.display = 'none';
                    console.error('Error:', error);
                });
        }
        
        // Infinite scroll
        window.addEventListener('scroll', () => {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
                loadMoreOrders();
            }
        });
        
        // Filter form
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            filterParams = new URLSearchParams(new FormData(this));
            currentOffset = <?= $limit ?>;
            hasMore = true;
            noMoreEl.style.display = 'none';
            tbody.innerHTML = '';
            loadMoreOrders();
        });
        
        function viewOrder(orderId) {
            document.getElementById('orderModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Detail Pesanan #' + orderId;
            
            // Load order details via AJAX (simplified version)
            fetch('get_order_details.php?id=' + orderId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalBody').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('modalBody').innerHTML = '<p>Gagal memuat detail pesanan</p>';
                });
        }
        
        function updateStatus(orderId, currentStatus) {
            document.getElementById('statusModal').style.display = 'block';
            document.getElementById('statusOrderId').value = orderId;
            document.getElementById('statusSelect').value = currentStatus;
        }
        
        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const orderModal = document.getElementById('orderModal');
            const statusModal = document.getElementById('statusModal');
            
            if (event.target == orderModal) {
                orderModal.style.display = 'none';
            }
            if (event.target == statusModal) {
                statusModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>