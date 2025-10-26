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

// Get cart count
$cart_count = 0;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM cart WHERE user_id = ?");
    $stmt->execute([getUserId()]);
} else {
    $stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM cart WHERE session_id = ?");
    $stmt->execute([$_SESSION['session_id']]);
}
$cart_result = $stmt->fetch();
$cart_count = $cart_result['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Parfum Refill Premium</title>
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
            background-color: #fafafa;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        .top-bar {
            background: #f8f8f8;
            padding: 8px 0;
            font-size: 12px;
            text-align: center;
            color: #666;
        }
        
        header {
            background: #fff;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: 2px;
            color: #2c2c2c;
            text-transform: uppercase;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 35px;
            align-items: center;
        }
        
        .nav-links a {
            color: #2c2c2c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: color 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .nav-links a:hover {
            color: #c41e3a;
        }
        
        .cart-icon {
            position: relative;
            cursor: pointer;
            font-size: 20px;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #c41e3a;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            padding: 60px 0;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 40px;
            text-align: center;
            color: #2c2c2c;
        }
        
        /* Profile Header */
        .profile-header {
            background: white;
            border-radius: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 40px;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #c41e3a 0%, #8b1429 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            font-weight: 300;
            letter-spacing: 2px;
        }
        
        .profile-name {
            font-size: 28px;
            font-weight: 300;
            margin-bottom: 8px;
            color: #2c2c2c;
            letter-spacing: 1px;
        }
        
        .profile-email {
            color: #999;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 40px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #f0f0f0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 300;
            color: #c41e3a;
            margin-bottom: 8px;
            display: block;
        }
        
        .stat-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .content-card {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .card-header {
            background: #2c2c2c;
            color: white;
            padding: 20px 30px;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card-content {
            padding: 30px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 0;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
            background: #fafafa;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #c41e3a;
            background: white;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            background: #c41e3a;
            color: white;
            padding: 14px 30px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        
        .btn:hover {
            background: #a01628;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(196, 30, 58, 0.3);
        }
        
        .btn-secondary {
            background: transparent;
            color: #666;
            border: 1px solid #e0e0e0;
        }
        
        .btn-secondary:hover {
            background: #2c2c2c;
            color: white;
            border-color: #2c2c2c;
        }
        
        /* Password Section */
        .password-section {
            background: #fafafa;
            border: 1px solid #e0e0e0;
            padding: 25px;
            margin-top: 30px;
        }
        
        .password-section h4 {
            margin-bottom: 20px;
            color: #2c2c2c;
            font-weight: 500;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Orders */
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-info h4 {
            margin-bottom: 6px;
            color: #2c2c2c;
            font-size: 14px;
            font-weight: 500;
        }
        
        .order-meta {
            font-size: 12px;
            color: #999;
        }
        
        .order-status {
            padding: 6px 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-orders h3 {
            font-size: 18px;
            font-weight: 300;
            margin-bottom: 10px;
            color: #2c2c2c;
        }
        
        .empty-orders p {
            font-size: 13px;
            margin-bottom: 25px;
        }
        
        /* Alert */
        .alert {
            padding: 15px;
            margin-bottom: 30px;
            border-left: 3px solid;
            font-size: 14px;
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
        
        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
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
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .nav-links {
                gap: 15px;
            }
            
            .nav-links a {
                font-size: 12px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        Welcome back, <?= htmlspecialchars($user['nama']) ?> 👋
    </div>

    <!-- Header -->
    <header>
        <nav class="container">
            <a href="index.php" class="logo">Parfum Refill</a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="profile.php">Account</a>
                <a href="orders.php">Orders</a>
                <a href="logout.php">Logout</a>
                <a href="cart.php" class="cart-icon">
                    🛒
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-count"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <h1 class="page-title">My Account</h1>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['nama'], 0, 2)) ?>
                </div>
                <h2 class="profile-name"><?= htmlspecialchars($user['nama']) ?></h2>
                <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= $stats['total_orders'] ?></span>
                        <span class="stat-label">Total Orders</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $stats['completed_orders'] ?></span>
                        <span class="stat-label">Completed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $stats['pending_orders'] ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= formatRupiah($stats['total_spent']) ?></span>
                        <span class="stat-label">Total Spent</span>
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
                    <div class="card-header">Personal Information</div>
                    <div class="card-content">
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nama">Full Name *</label>
                                    <input type="text" id="nama" name="nama" required 
                                           value="<?= htmlspecialchars($user['nama']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" required 
                                           value="<?= htmlspecialchars($user['email']) ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="telepon">Phone Number *</label>
                                <input type="tel" id="telepon" name="telepon" required 
                                       value="<?= htmlspecialchars($user['telepon']) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="alamat">Complete Address *</label>
                                <textarea id="alamat" name="alamat" required><?= htmlspecialchars($user['alamat']) ?></textarea>
                            </div>
                            
                            <!-- Password Change Section -->
                            <div class="password-section">
                                <h4>Change Password (Optional)</h4>
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" 
                                           placeholder="Leave blank if you don't want to change">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <input type="password" id="new_password" name="new_password" 
                                               placeholder="Min. 6 characters">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" 
                                               placeholder="Re-enter new password">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <button type="submit" name="update_profile" class="btn">
                                    Save Changes
                                </button>
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="content-card">
                    <div class="card-header">Recent Orders</div>
                    <div class="card-content">
                        <?php if (empty($recent_orders)): ?>
                            <div class="empty-orders">
                                <h3>No orders yet</h3>
                                <p>Start shopping now!</p>
                                <br>
                                <a href="index.php" class="btn">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <h4>Order #<?= $order['id'] ?></h4>
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
                            
                            <div style="text-align: center; margin-top: 30px;">
                                <a href="orders.php" class="btn">View All Orders</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About Us</h3>
                    <p>Premium refill perfumes with authentic quality and affordable prices.</p>
                </div>
                <div class="footer-section">
                    <h3>Customer Service</h3>
                    <a href="tel:+6281234567890">📞 +62812-3456-7890</a>
                    <a href="mailto:cs@parfumrefill.com">✉️ cs@parfumrefill.com</a>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="#">Track Order</a>
                    <a href="#">Shipping Info</a>
                    <a href="#">Return Policy</a>
                </div>
                <div class="footer-section">
                    <h3>Our Guarantee</h3>
                    <p>✅ 100% Original Scent</p>
                    <p>🛡️ Money Back Guarantee</p>
                    <p>🚚 Free Shipping</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Parfum Refill Premium. All rights reserved.</p>
            </div>
        </div>
    </footer>

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