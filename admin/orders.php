<?php
require_once 'config/auth.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$order_id = (int)($_GET['id'] ?? 0);

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
        logAdminActivity('UPDATE_ORDER_STATUS', "Mengubah status pesanan #$order_id ($customer_name) menjadi $new_status");
        $_SESSION['message'] = "Status pesanan berhasil diubah menjadi " . ucfirst($new_status);
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Gagal mengubah status pesanan';
        $_SESSION['message_type'] = 'error';
    }
    redirect('orders.php');
}

// Get orders with filters
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

$sql .= " GROUP BY o.id ORDER BY o.$sort";

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
            SELECT oi.*, p.nama_parfum, p.brand 
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
    <title>Kelola Pesanan - Admin</title>
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .status-pending .stat-number { color: #f39c12; }
        .status-confirmed .stat-number { color: #27ae60; }
        .status-processing .stat-number { color: #3498db; }
        .status-shipped .stat-number { color: #9b59b6; }
        .status-delivered .stat-number { color: #2ecc71; }
        .status-cancelled .stat-number { color: #e74c3c; }
        
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
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
            text-align: center;
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
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
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
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .orders-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #555;
        }
        
        .orders-table tr:hover {
            background: #f8f9fa;
        }
        
        .order-id {
            font-weight: bold;
            color: #1e3c72;
        }
        
        .customer-info {
            margin-bottom: 0.25rem;
        }
        
        .customer-name {
            font-weight: bold;
        }
        
        .customer-contact {
            font-size: 0.9rem;
            color: #666;
        }
        
        .order-status {
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
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
            gap: 0.5rem;
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
            background: white;
            margin: 0 auto;
            border-radius: 15px;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }
        
        .order-detail-section {
            margin-bottom: 2rem;
        }
        
        .order-detail-section h4 {
            margin-bottom: 1rem;
            color: #333;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
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
            font-weight: bold;
            color: #555;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            color: #333;
        }
        
        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .order-items-table th,
        .order-items-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .order-items-table th {
            background: #f8f9fa;
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
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
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
                padding: 0.5rem;
                font-size: 0.9rem;
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
                        <a href="orders.php" class="nav-link active">
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
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">📦 Kelola Pesanan</h1>
                <div class="user-info">
                    <span><?= $_SESSION['user_name'] ?></span>
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
                <div class="stat-card status-pending">
                    <div class="stat-number"><?= $status_stats['pending'] ?? 0 ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card status-confirmed">
                    <div class="stat-number"><?= $status_stats['confirmed'] ?? 0 ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card status-processing">
                    <div class="stat-number"><?= $status_stats['processing'] ?? 0 ?></div>
                    <div class="stat-label">Processing</div>
                </div>
                <div class="stat-card status-shipped">
                    <div class="stat-number"><?= $status_stats['shipped'] ?? 0 ?></div>
                    <div class="stat-label">Shipped</div>
                </div>
                <div class="stat-card status-delivered">
                    <div class="stat-number"><?= $status_stats['delivered'] ?? 0 ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
                <div class="stat-card status-cancelled">
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
                    <form method="GET" class="filter-form">
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
                            <a href="orders.php" class="btn btn-warning">🔄 Reset</a>
                        </div>
                    </form>
                </div>
                
                <!-- Orders Table -->
                <div class="table-container">
                    <?php if (empty($orders)): ?>
                        <div style="text-align: center; padding: 3rem; color: #666;">
                            <h3>Tidak ada pesanan ditemukan</h3>
                            <p>Belum ada pesanan yang masuk atau sesuai dengan filter yang dipilih</p>
                        </div>
                    <?php else: ?>
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
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <div class="order-id">#<?= $order['id'] ?></div>
                                        </td>
                                        <td>
                                            <div class="customer-info">
                                                <div class="customer-name"><?= htmlspecialchars($order['nama_customer']) ?></div>
                                                <div class="customer-contact">
                                                    📧 <?= htmlspecialchars($order['email_customer']) ?><br>
                                                    📱 <?= htmlspecialchars($order['telepon_customer']) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?= $order['total_items'] ?> item<?= $order['total_items'] > 1 ? 's' : '' ?>
                                        </td>
                                        <td>
                                            <strong><?= formatRupiah($order['total_harga']) ?></strong>
                                        </td>
                                        <td>
                                            <div class="order-status status-<?= $order['status'] ?>">
                                                <?= $order['status'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button onclick="viewOrder(<?= $order['id'] ?>)" class="btn btn-sm">
                                                    👁️ Lihat
                                                </button>
                                                <button onclick="updateStatus(<?= $order['id'] ?>, '<?= $order['status'] ?>')" class="btn btn-sm btn-warning">
                                                    ✏️ Status
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 2rem; text-align: center; color: #666;">
                            Total: <?= count($orders) ?> pesanan
                        </div>
                    <?php endif; ?>
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
                        <button type="button" onclick="closeStatusModal()" class="btn">Batal</button>
                        <button type="submit" class="btn btn-success">💾 Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
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