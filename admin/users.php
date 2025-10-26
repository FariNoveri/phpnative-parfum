<?php
require_once 'config/auth.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$user_id = (int)($_GET['id'] ?? 0);

// Handle AJAX load more
if (isset($_GET['ajax']) && $_GET['ajax'] === 'load_more') {
    $offset = (int)($_GET['offset'] ?? 0);
    $limit = 5;
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

    $sql .= " GROUP BY u.id ORDER BY u.$sort LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Output HTML rows
    foreach ($users as $user) {
        $lastOrder = $user['last_order'] ? date('d/m/Y H:i', strtotime($user['last_order'])) : 'Belum pernah order';
        $totalSpent = formatRupiah($user['total_spent'] ?? 0);
        echo "
        <tr data-user-id='{$user['id']}'>
            <td>
                <div class='user-info'>
                    <h4>{$user['nama']}</h4>
                    <p class='meta'>{$user['email']} • {$user['telepon']}</p>
                </div>
            </td>
            <td>{$user['total_orders']}</td>
            <td>{$totalSpent}</td>
            <td>{$lastOrder}</td>
            <td>" . date('d/m/Y H:i', strtotime($user['created_at'])) . "</td>
            <td>
                <button class='btn btn-secondary btn-sm view-details' data-user-id='{$user['id']}'>Details</button>
            </td>
        </tr>";
    }

    // Check if more users available
    $stmt = $pdo->prepare(str_replace('LIMIT ? OFFSET ?', '', $sql) . " LIMIT 1 OFFSET ?");
    $stmt->execute(array_merge(array_slice($params, 0, -2), [$offset + $limit]));
    $hasMore = $stmt->fetch() !== false;

    echo $hasMore ? '<script>hasMoreUsers = true;</script>' : '<script>hasMoreUsers = false;</script>';
    exit;
}

// Handle user actions
if ($_POST) {
    if ($action === 'toggle_status' && $user_id > 0) {
        // Implement toggle active/inactive if needed
        $_SESSION['message'] = 'Status user berhasil diupdate';
        $_SESSION['message_type'] = 'success';
        redirect('users.php');
    }
}

// Get initial users (first 5)
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

$sql .= " GROUP BY u.id ORDER BY u.$sort LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$initial_users = $stmt->fetchAll();

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
    <title>Kelola User - Parfum Refill Premium</title>
    <meta name="description" content="Kelola user di Toko Parfum Refill Premium.">
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
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        
        .sidebar-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .admin-logo {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: 2px;
            color: #2c2c2c;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
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
        
        .filter-form {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .form-group {
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .user-info h4 {
            margin-bottom: 0.25rem;
        }
        
        .meta {
            color: #999;
            font-size: 0.9rem;
        }
        
        .loading-indicator {
            text-align: center;
            padding: 1rem;
            color: #666;
            display: none;
        }
        
        .no-more {
            text-align: center;
            padding: 1rem;
            color: #666;
            font-style: italic;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            border-radius: 10px;
            padding: 2rem;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .user-details h2 {
            margin-bottom: 1rem;
        }
        
        .user-details p {
            margin-bottom: 0.5rem;
            color: #666;
        }
        
        .orders-list {
            margin-top: 2rem;
        }
        
        .order-item {
            background: #f8f8f8;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .order-item h4 {
            margin-bottom: 0.5rem;
        }
        
        .order-meta {
            color: #999;
            font-size: 0.9rem;
        }
        
        .vouchers-list {
            margin-top: 2rem;
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
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
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
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .filter-form {
                flex-direction: column;
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
                        <a href="users.php" class="nav-link active">
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
                <h1 class="page-title">Kelola User</h1>
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
                    <div class="stat-icon">📆</div>
                    <div class="stat-number"><?= $user_stats['new_month'] ?></div>
                    <div class="stat-label">User Baru Bulan Ini</div>
                </div>
            </div>

            <!-- Users List -->
            <div class="section-card">
                <div class="section-header">Daftar User</div>
                <div class="section-content">
                    <!-- Filters -->
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label>Cari</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama, email, telepon...">
                        </div>
                        <div class="form-group">
                            <label>Urutkan</label>
                            <select name="sort">
                                <option value="created_at DESC" <?= $sort == 'created_at DESC' ? 'selected' : '' ?>>Terbaru</option>
                                <option value="created_at ASC" <?= $sort == 'created_at ASC' ? 'selected' : '' ?>>Terlama</option>
                                <option value="nama ASC" <?= $sort == 'nama ASC' ? 'selected' : '' ?>>Nama A-Z</option>
                                <option value="total_orders DESC" <?= $sort == 'total_orders DESC' ? 'selected' : '' ?>>Order Terbanyak</option>
                                <option value="total_spent DESC" <?= $sort == 'total_spent DESC' ? 'selected' : '' ?>>Belanja Terbanyak</option>
                            </select>
                        </div>
                        <button type="submit" class="btn">Filter</button>
                    </form>

                    <!-- Table -->
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Total Order</th>
                                <th>Total Belanja</th>
                                <th>Terakhir Order</th>
                                <th>Daftar Pada</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody">
                            <?php foreach ($initial_users as $user): ?>
                                <?php 
                                $lastOrder = $user['last_order'] ? date('d/m/Y H:i', strtotime($user['last_order'])) : 'Belum pernah order';
                                $totalSpent = formatRupiah($user['total_spent'] ?? 0);
                                ?>
                                <tr data-user-id="<?= $user['id'] ?>">
                                    <td>
                                        <div class="user-info">
                                            <h4><?= htmlspecialchars($user['nama']) ?></h4>
                                            <p class="meta"><?= htmlspecialchars($user['email']) ?> • <?= htmlspecialchars($user['telepon']) ?></p>
                                        </div>
                                    </td>
                                    <td><?= $user['total_orders'] ?></td>
                                    <td><?= $totalSpent ?></td>
                                    <td><?= $lastOrder ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-secondary btn-sm view-details" data-user-id="<?= $user['id'] ?>">Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div id="loading" class="loading-indicator">Memuat...</div>
                    <div id="no-more" class="no-more" style="display: none;">Sudah tidak ada lagi</div>
                </div>
            </div>
        </main>
    </div>

    <!-- User Details Modal -->
    <div id="user-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div id="user-details-content"></div>
        </div>
    </div>

    <script>
        let offset = 5;
        let hasMoreUsers = true;
        let isLoading = false;
        const search = new URLSearchParams(window.location.search).get('search') || '';
        const sort = new URLSearchParams(window.location.search).get('sort') || 'created_at DESC';

        const tbody = document.getElementById('users-tbody');
        const loading = document.getElementById('loading');
        const noMore = document.getElementById('no-more');
        const modal = document.getElementById('user-modal');
        const closeModal = document.querySelector('.close-modal');
        const detailsContent = document.getElementById('user-details-content');

        // Automatic load on scroll
        window.addEventListener('scroll', () => {
            if (isLoading || !hasMoreUsers) return;

            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 200) {
                loadMoreUsers();
            }
        });

        function loadMoreUsers() {
            isLoading = true;
            loading.style.display = 'block';

            fetch(`users.php?ajax=load_more&offset=${offset}&search=${encodeURIComponent(search)}&sort=${encodeURIComponent(sort)}`)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const rows = doc.querySelectorAll('tr');
                    rows.forEach(row => tbody.appendChild(row.cloneNode(true)));
                    
                    const script = doc.querySelector('script');
                    if (script) eval(script.textContent);

                    offset += 5;
                    isLoading = false;
                    loading.style.display = 'none';

                    if (!hasMoreUsers) {
                        noMore.style.display = 'block';
                    }
                });
        }

        // View details
        document.addEventListener('click', e => {
            if (e.target.classList.contains('view-details')) {
                const userId = e.target.dataset.userId;
                fetchUserDetails(userId);
            }
        });

        function fetchUserDetails(userId) {
            // Mock for now
            detailsContent.innerHTML = `
                <div class="user-details">
                    <h2>User Details</h2>
                    <p><strong>Nama:</strong> ${tbody.querySelector(`tr[data-user-id="${userId}"] h4`).textContent}</p>
                    <p><strong>Email:</strong> example@email.com</p>
                    <p><strong>Telepon:</strong> 08123456789</p>
                    <p><strong>Alamat:</strong> Bandar Lampung</p>
                    <p><strong>Daftar Pada:</strong> ${tbody.querySelector(`tr[data-user-id="${userId}"] td:nth-child(5)`).textContent}</p>
                </div>
                
                <div class="orders-list">
                    <h3>History Pembelian</h3>
                    <div class="order-item">
                        <h4>Order #13 - 16/10/2025 02:12:08</h4>
                        <p class="order-meta">Items: Black Orchid x1, fgdgj x1 • Total: Rp 2.000 • Status: Confirmed</p>
                    </div>
                    <!-- More orders -->
                </div>
                
                <div class="vouchers-list">
                    <h3>Voucher Digunakan</h3>
                    <p>NEWBIE10 - Digunakan pada order #13</p>
                    <!-- More vouchers -->
                </div>
            `;
            modal.style.display = 'flex';
        }

        // Close modal
        closeModal.addEventListener('click', () => modal.style.display = 'none');
        window.addEventListener('click', e => {
            if (e.target === modal) modal.style.display = 'none';
        });
    </script>
</body>
</html>