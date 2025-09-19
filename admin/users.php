<?php
require_once 'config/auth.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$user_id = (int)($_GET['id'] ?? 0);

// Handle user actions
if ($_POST) {
    if ($action === 'toggle_status' && $user_id > 0) {
        // For future implementation - activate/deactivate user
        $_SESSION['message'] = 'Fitur akan segera tersedia';
        $_SESSION['message_type'] = 'info';
        redirect('users.php');
    }
}

// Get users with filters
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at DESC';

$sql = "SELECT u.*, 
               COUNT(DISTINCT o.id) as total_orders,
               SUM(o.total_harga) as total_spent,
               MAX(o.created_at) as last_order
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id 
        WHERE u.role = 'user'";
$params = [];

if ($search) {
    $sql .= " AND (u.nama LIKE ? OR u.email LIKE ? OR u.telepon LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY u.id ORDER BY u.$sort";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_today,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_week,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_month
    FROM users WHERE role = 'user'
");
$user_stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin</title>
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
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .table-container {
            padding: 2rem;
            overflow-x: auto;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .users-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #555;
        }
        
        .users-table tr:hover {
            background: #f8f9fa;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .user-info-cell {
            display: flex;
            align-items: center;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .user-email {
            color: #666;
            font-size: 0.9rem;
        }
        
        .user-phone {
            color: #666;
            font-size: 0.9rem;
        }
        
        .user-stats {
            text-align: center;
        }
        
        .stat-value {
            font-weight: bold;
            color: #1e3c72;
        }
        
        .stat-desc {
            font-size: 0.8rem;
            color: #666;
        }
        
        .last-seen {
            font-size: 0.9rem;
            color: #666;
        }
        
        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .badge-new {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-active {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-vip {
            background: #fff3cd;
            color: #856404;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
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
            
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-container {
                padding: 1rem;
            }
            
            .users-table th,
            .users-table td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
            
            .user-info-cell {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .user-avatar {
                margin-bottom: 0.5rem;
                margin-right: 0;
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
                        <a href="orders.php" class="nav-link">
                            <span class="nav-icon">📦</span>
                            Kelola Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="nav-link active">
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
                <h1 class="page-title">👥 Kelola User</h1>
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

            <!-- User Statistics -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?= $user_stats['total_users'] ?></div>
                    <div class="stat-label">Total User</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🆕</div>
                    <div class="stat-number"><?= $user_stats['new_today'] ?></div>
                    <div class="stat-label">User Baru Hari Ini</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-number"><?= $user_stats['new_week'] ?></div>
                    <div class="stat-label">User Baru Minggu Ini</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-number"><?= $user_stats['new_month'] ?></div>
                    <div class="stat-label">User Baru Bulan Ini</div>
                </div>
            </div>

            <!-- Users List -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">Daftar User</div>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label>Cari User</label>
                            <input type="text" name="search" placeholder="Nama, email, atau telepon..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Urutkan</label>
                            <select name="sort">
                                <option value="created_at DESC" <?= $sort === 'created_at DESC' ? 'selected' : '' ?>>Terbaru Daftar</option>
                                <option value="created_at ASC" <?= $sort === 'created_at ASC' ? 'selected' : '' ?>>Terlama Daftar</option>
                                <option value="nama ASC" <?= $sort === 'nama ASC' ? 'selected' : '' ?>>Nama A-Z</option>
                                <option value="total_orders DESC" <?= $sort === 'total_orders DESC' ? 'selected' : '' ?>>Paling Sering Order</option>
                                <option value="total_spent DESC" <?= $sort === 'total_spent DESC' ? 'selected' : '' ?>>Pengeluaran Tertinggi</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn">🔍 Cari</button>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <a href="users.php" class="btn btn-warning">🔄 Reset</a>
                        </div>
                    </form>
                </div>
                
                <!-- Users Table -->
                <div class="table-container">
                    <?php if (empty($users)): ?>
                        <div style="text-align: center; padding: 3rem; color: #666;">
                            <h3>Tidak ada user ditemukan</h3>
                            <p>Belum ada user yang terdaftar atau sesuai dengan filter yang dipilih</p>
                        </div>
                    <?php else: ?>
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Total Order</th>
                                    <th>Total Belanja</th>
                                    <th>Terakhir Order</th>
                                    <th>Tgl Daftar</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $isNew = (strtotime($user['created_at']) > strtotime('-7 days'));
                                    $isVIP = ($user['total_spent'] > 5000000); // VIP if spent > 5 juta
                                    $lastOrder = $user['last_order'] ? date('d/m/Y', strtotime($user['last_order'])) : 'Belum pernah';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="user-info-cell">
                                                <div class="user-avatar">
                                                    <?= strtoupper(substr($user['nama'], 0, 2)) ?>
                                                </div>
                                                <div class="user-details">
                                                    <div class="user-name"><?= htmlspecialchars($user['nama']) ?></div>
                                                    <div class="user-email">📧 <?= htmlspecialchars($user['email']) ?></div>
                                                    <div class="user-phone">📱 <?= htmlspecialchars($user['telepon']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="user-stats">
                                                <div class="stat-value"><?= $user['total_orders'] ?></div>
                                                <div class="stat-desc">pesanan</div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="user-stats">
                                                <div class="stat-value"><?= formatRupiah($user['total_spent'] ?? 0) ?></div>
                                                <div class="stat-desc">total belanja</div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="last-seen"><?= $lastOrder ?></div>
                                        </td>
                                        <td>
                                            <div class="last-seen"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                <?php if ($isNew): ?>
                                                    <span class="badge badge-new">🆕 Baru</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($isVIP): ?>
                                                    <span class="badge badge-vip">⭐ VIP</span>
                                                <?php endif; ?>
                                                
                                                <?php if (!$isNew && !$isVIP): ?>
                                                    <span class="badge badge-active">✅ Aktif</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 2rem; text-align: center; color: #666;">
                            Total: <?= count($users) ?> user
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>