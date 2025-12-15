<?php
require_once '../config/database.php';

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    redirect('dashboard.php');
}

$error = '';

if ($_POST) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_name'] = $admin['nama'];
            $_SESSION['user_email'] = $admin['email'];
            $_SESSION['user_role'] = $admin['role'];
            
            // Log admin login
            $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)");
            $stmt->execute([$admin['id'], 'LOGIN', 'Admin login ke sistem']);
            
            $_SESSION['message'] = 'Login admin berhasil! Selamat datang ' . $admin['nama'];
            $_SESSION['message_type'] = 'success';
            redirect('dashboard.php');
        } else {
            $error = 'Email atau password admin salah';
        }
    }
}

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
    <title>Admin Login - UniqThings</title>
    <meta name="description" content="Login area untuk administrator Toko Parfum Refill Premium.">
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
    display: flex;
    align-items: center;
}

.logo-img {
    height: 50px;
    width: auto;
    object-fit: contain;
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
        
        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, #ffeef5 0%, #fff 100%);
            padding: 80px 0;
            text-align: center;
        }
        
        .hero-content h1 {
            font-size: 48px;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 20px;
            color: #2c2c2c;
        }
        
        .hero-content p {
            font-size: 18px;
            color: #666;
            max-width: 600px;
            margin: 0 auto 30px;
            line-height: 1.8;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-top: 50px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 300;
            color: #c41e3a;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Login Section */
        .login-section {
            background: #fff;
            padding: 40px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .login-wrapper {
            max-width: 450px;
            margin: 0 auto;
        }
        
        .admin-badge {
            background: #c41e3a;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .login-title {
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 1px;
            color: #2c2c2c;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 0;
            font-size: 14px;
            background: #fafafa;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #c41e3a;
            background: #fff;
        }
        
        .btn-primary {
            background: #c41e3a;
            color: white;
            padding: 15px 30px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            width: 100%;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: #a01628;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 3px solid #ef4444;
            padding: 15px;
            border-radius: 0;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .security-notice {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            color: #0369a1;
            padding: 15px;
            border-radius: 0;
            margin-top: 20px;
            font-size: 13px;
            text-align: left;
        }
        
        .links {
            margin-top: 20px;
            text-align: center;
        }
        
        .links a {
            color: #c41e3a;
            text-decoration: none;
            font-size: 13px;
        }
        
        .links a:hover {
            text-decoration: underline;
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
            .hero-content h1 {
                font-size: 32px;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 30px;
            }
            
            .nav-links {
                gap: 15px;
            }
            
            .nav-links a {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        🚚 Gratis Ongkir Min. Rp 500K | 💯 Garansi Puas atau Uang Kembali
    </div>

<!-- Header -->
<header>
    <nav class="container">
        <a href="../index.php" class="logo">
            <img src="../img/logo.png" alt="UniqThings Parfum Refill" class="logo-img">
        </a>
        <div class="nav-links">
            <a href="../index.php">Home</a>
            <?php if (isLoggedIn()): ?>
                <a href="../profile.php">Account</a>
                <a href="../orders.php">Orders</a>
                <a href="../logout.php">Logout</a>
            <?php else: ?>
                <a href="../login.php">Login</a>
                <a href="../register.php">Register</a>
            <?php endif; ?>
            <a href="../cart.php" class="cart-icon">
                🛒
                <?php if ($cart_count > 0): ?>
                    <span class="cart-count"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>
        </div>
    </nav>
</header>

    <!-- Hero Banner -->
    <section class="hero-banner">
        <div class="container">
            <div class="hero-content">
                <h1>Luxury Fragrances</h1>
                <p>Rasakan sensasi wangi elegan dari parfume refill kami — tahan lama dan memikat sepanjang hari.</p>
                
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number">1000+</span>
                        <span class="stat-label">Happy Customers</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">50+</span>
                        <span class="stat-label">Premium Brands</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">4.8★</span>
                        <span class="stat-label">Store Rating</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Section -->
    <section class="login-section">
        <div class="container">
            <div class="login-wrapper">
                <div class="admin-badge">🔒 ADMIN AREA</div>
                <h2 class="login-title">Admin Login</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="email">📧 Email Admin:</label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="admin@parfumrefill.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">🔑 Password:</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Masukkan password admin">
                    </div>
                    
                    <button type="submit" class="btn-primary">🚀 LOGIN ADMIN</button>
                </form>
                
                <div class="security-notice">
                    🛡️ <strong>Area Admin:</strong> Akses terbatas hanya untuk administrator sistem. 
                    Semua aktivitas akan dicatat dalam log sistem.
                </div>
                
                <div class="links">
                    <a href="../index.php">← Kembali ke Website</a>
                </div>
            </div>
        </div>
    </section>

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
                <p>&copy; 2024 Parfum Refill Premium. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>