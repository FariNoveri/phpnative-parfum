<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    $_SESSION['message'] = 'Silakan login terlebih dahulu';
    $_SESSION['message_type'] = 'error';
    redirect('login.php');
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([getUserId()]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['message'] = 'Data user tidak ditemukan';
    $_SESSION['message_type'] = 'error';
    redirect('logout.php');
}

// Get user statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status IN ('confirmed', 'processing', 'shipped', 'delivered') THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status IN ('confirmed', 'processing', 'shipped', 'delivered') THEN total_harga ELSE 0 END) as total_spent,
        MAX(created_at) as last_order_date
    FROM orders WHERE user_id = ?
");
$stmt->execute([getUserId()]);
$stats = $stmt->fetch();

// Handle form submission for profile update
if ($_POST && isset($_POST['update_profile'])) {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $error = '';
    
    // Validate required fields
    if (empty($nama) || empty($email) || empty($telepon) || empty($alamat)) {
        $error = 'Semua field wajib harus diisi';
    } else {
        // Check if email is already used by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, getUserId()]);
        if ($stmt->fetch()) {
            $error = 'Email sudah digunakan oleh user lain';
        }
        
        // If changing password, validate it
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $error = 'Password lama harus diisi untuk mengubah password';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = 'Password lama tidak benar';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Konfirmasi password tidak sama';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password baru minimal 6 karakter';
            }
        }
        
        // Update profile if no errors
        if (empty($error)) {
            try {
                if (!empty($new_password)) {
                    // Update with new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ?, telepon = ?, alamat = ?, password = ? WHERE id = ?");
                    $stmt->execute([$nama, $email, $telepon, $alamat, $hashed_password, getUserId()]);
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ?, telepon = ?, alamat = ? WHERE id = ?");
                    $stmt->execute([$nama, $email, $telepon, $alamat, getUserId()]);
                }
                
                // Update session data
                $_SESSION['user_name'] = $nama;
                $_SESSION['user_email'] = $email;
                
                $_SESSION['message'] = 'Profile berhasil diupdate';
                $_SESSION['message_type'] = 'success';
                redirect('profile.php');
                
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan saat mengupdate profile';
            }
        }
    }
    
    if (!empty($error)) {
        $_SESSION['message'] = $error;
        $_SESSION['message_type'] = 'error';
    }
}

// Get recent orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([getUserId()]);
$recent_orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Saya - Toko Parfum Premium</title>
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
        
        .profile-header {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 1rem;
            font-weight: bold;
        }
        
        .profile-name {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .profile-email {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
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
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .card-content {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 1rem;
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
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
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
        
        .password-section {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .password-section h4 {
            margin-bottom: 1rem;
            color: #495057;
        }
        
        .empty-orders {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .nav-links {
                gap: 1rem;
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
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['nama'], 0, 2)) ?>
                </div>
                <h1 class="profile-name"><?= htmlspecialchars($user['nama']) ?></h1>
                <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['total_orders'] ?></div>
                        <div class="stat-label">Total Pesanan</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['completed_orders'] ?></div>
                        <div class="stat-label">Selesai</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['pending_orders'] ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= formatRupiah($stats['total_spent']) ?></div>
                        <div class="stat-label">Total Belanja</div>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="content-grid">
                <!-- Profile Form -->
                <div class="content-card">
                    <div class="card-header">✏️ Edit Profile</div>
                    <div class="card-content">
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nama">Nama Lengkap *</label>
                                    <input type="text" id="nama" name="nama" required 
                                           value="<?= htmlspecialchars($user['nama']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" required 
                                           value="<?= htmlspecialchars($user['email']) ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="telepon">No. Telepon *</label>
                                <input type="tel" id="telepon" name="telepon" required 
                                       value="<?= htmlspecialchars($user['telepon']) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="alamat">Alamat Lengkap *</label>
                                <textarea id="alamat" name="alamat" required><?= htmlspecialchars($user['alamat']) ?></textarea>
                            </div>
                            
                            <!-- Password Change Section -->
                            <div class="password-section">
                                <h4>🔐 Ubah Password (Opsional)</h4>
                                <div class="form-group">
                                    <label for="current_password">Password Lama</label>
                                    <input type="password" id="current_password" name="current_password" 
                                           placeholder="Kosongkan jika tidak ingin ubah password">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="new_password">Password Baru</label>
                                        <input type="password" id="new_password" name="new_password" 
                                               placeholder="Minimal 6 karakter">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password">Konfirmasi Password Baru</label>
                                        <input type="password" id="confirm_password" name="confirm_password" 
                                               placeholder="Ulangi password baru">
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                                <button type="submit" name="update_profile" class="btn btn-success">
                                    💾 Update Profile
                                </button>
                                <a href="index.php" class="btn">← Kembali ke Beranda</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="content-card">
                    <div class="card-header">📦 Pesanan Terbaru</div>
                    <div class="card-content">
                        <?php if (empty($recent_orders)): ?>
                            <div class="empty-orders">
                                <h3>Belum ada pesanan</h3>
                                <p>Mulai berbelanja sekarang!</p>
                                <br>
                                <a href="index.php" class="btn">🛍️ Mulai Belanja</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <h4>Pesanan #<?= $order['id'] ?></h4>
                                        <div class="order-meta">
                                            <?= formatRupiah($order['total_harga']) ?> • 
                                            <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="order-status status-<?= $order['status'] ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div style="text-align: center; margin-top: 2rem;">
                                <a href="orders.php" class="btn">📋 Lihat Semua Pesanan</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Show/hide password fields based on current password input
        document.getElementById('current_password').addEventListener('input', function() {
            const newPasswordField = document.getElementById('new_password');
            const confirmPasswordField = document.getElementById('confirm_password');
            
            if (this.value.length > 0) {
                newPasswordField.required = true;
                confirmPasswordField.required = true;
            } else {
                newPasswordField.required = false;
                confirmPasswordField.required = false;
                newPasswordField.value = '';
                confirmPasswordField.value = '';
            }
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword && confirmPassword.length > 0) {
                this.setCustomValidity('Password tidak sama');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>