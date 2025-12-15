<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_POST) {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);
    
    if (empty($nama) || empty($email) || empty($password) || empty($telepon) || empty($alamat)) {
        $error = 'Semua field harus diisi';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak sama';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar';
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nama, email, password, telepon, alamat) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$nama, $email, $hashed_password, $telepon, $alamat])) {
                $success = 'Registrasi berhasil! Silakan login';
            } else {
                $error = 'Terjadi kesalahan saat mendaftar';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UniqThings</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/logo.png">
    <link rel="shortcut icon" type="image/png" href="img/logo.png">
    <link rel="apple-touch-icon" href="img/logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ffeef5 0%, #fff 50%, #f0f0f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .register-wrapper {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .register-banner {
    flex: 1;
    background: linear-gradient(135deg, #c41e3a 0%, #8b1429 100%);
    padding: 3rem;
    color: white;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
}

.banner-logo {
    margin-bottom: 2rem;
}

.banner-logo-img {
    height: 100px;
    width: auto;
    object-fit: contain;
    filter: brightness(0) invert(1); /* Makes logo white */
}

.banner-icon {
    font-size: 4rem;
    margin-bottom: 2rem;
}

.banner-title {
    font-size: 2rem;
    font-weight: 300;
    letter-spacing: 2px;
    margin-bottom: 1rem;
    text-transform: uppercase;
}
        
        .banner-text {
            font-size: 0.95rem;
            line-height: 1.8;
            opacity: 0.9;
            max-width: 300px;
        }
        
        .benefits {
            margin-top: 2rem;
            text-align: left;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .benefit-item::before {
            content: '✓';
            background: rgba(255,255,255,0.2);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .register-container {
            flex: 1.2;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .logo {
            text-align: center;
            font-size: 1.8rem;
            font-weight: 300;
            letter-spacing: 2px;
            color: #2c2c2c;
            margin-bottom: 1rem;
            text-transform: uppercase;
        }
        
        h2 {
            text-align: center;
            color: #666;
            font-weight: 300;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 0.9rem;
            border: 1px solid #e0e0e0;
            border-radius: 0;
            font-size: 0.95rem;
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
            min-height: 80px;
            resize: vertical;
        }
        
        .btn {
            width: 100%;
            background: #c41e3a;
            color: white;
            padding: 1rem;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin: 1rem 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        
        .btn:hover {
            background: #a01628;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(196, 30, 58, 0.3);
        }
        
        .error {
            background: #fef2f2;
            color: #991b1b;
            padding: 0.9rem;
            margin-bottom: 1.5rem;
            border-left: 3px solid #ef4444;
            font-size: 0.9rem;
        }
        
        .success {
            background: #f0fdf4;
            color: #166534;
            padding: 0.9rem;
            margin-bottom: 1.5rem;
            border-left: 3px solid #22c55e;
            font-size: 0.9rem;
        }
        
        .divider {
            text-align: center;
            margin: 1rem 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e0e0e0;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
            position: relative;
            color: #999;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .links {
            text-align: center;
        }
        
        .links a {
            color: #c41e3a;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .links a:hover {
            color: #a01628;
            text-decoration: underline;
        }
        
        .links p {
            margin: 0.5rem 0;
            color: #666;
            font-size: 0.85rem;
        }
        
        .back-link {
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .register-wrapper {
                flex-direction: column;
            }
            
            .register-banner {
                padding: 2rem;
            }
            
            .banner-title {
                font-size: 1.5rem;
            }
            
            .register-container {
                padding: 2rem;
                max-height: none;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }
            
            .register-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <div class="register-banner">
    <div class="banner-logo">
        <img src="img/logo.png" alt="UniqThings Parfum Refill" class="banner-logo-img">
    </div>
    <p class="banner-text">Join thousands of satisfied customers enjoying premium fragrances.</p>
    
    <div class="benefits">
        <div class="benefit-item">Exclusive member discounts</div>
        <div class="benefit-item">Early access to new arrivals</div>
        <div class="benefit-item">Free shipping on first order</div>
        <div class="benefit-item">Birthday special offers</div>
    </div>
</div>
        
        <div class="register-container">
            <div class="logo">Create Account</div>
            <h2>Join our community today</h2>
            
            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?= $success ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nama">Full Name</label>
                        <input type="text" id="nama" name="nama" required 
                               value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                               placeholder="John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="your@email.com">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="telepon">Phone Number</label>
                        <input type="tel" id="telepon" name="telepon" required 
                               value="<?= htmlspecialchars($_POST['telepon'] ?? '') ?>"
                               placeholder="+62 812-3456-7890">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required
                               placeholder="Min. 6 characters">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           placeholder="Re-enter your password">
                </div>
                
                <div class="form-group full-width">
                    <label for="alamat">Complete Address</label>
                    <textarea id="alamat" name="alamat" required 
                              placeholder="Street, City, Province, Postal Code"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="btn">Create Account</button>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <div class="links">
                <p>Already have an account?</p>
                <a href="login.php">Sign In</a>
                
                <p class="back-link">
                    <a href="index.php">← Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>