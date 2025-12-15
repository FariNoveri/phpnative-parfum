<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_POST) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nama'];
            $_SESSION['user_email'] = $user['email'];
            
            // Transfer cart from session to user
            $stmt = $pdo->prepare("UPDATE cart SET user_id = ?, session_id = NULL WHERE session_id = ?");
            $stmt->execute([$user['id'], $_SESSION['session_id']]);
            
            $_SESSION['message'] = 'Login berhasil! Selamat datang ' . $user['nama'];
            $_SESSION['message_type'] = 'success';
            redirect('index.php');
        } else {
            $error = 'Email atau password salah';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Uni</title>
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
        
        .login-wrapper {
            display: flex;
            max-width: 900px;
            width: 100%;
            background: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .login-banner {
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
    height: 120px;
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
        
        .login-container {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
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
            margin-bottom: 2.5rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
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
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.9rem;
            border: 1px solid #e0e0e0;
            border-radius: 0;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s;
            background: #fafafa;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #c41e3a;
            background: white;
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
            margin: 1.5rem 0 1rem;
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
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
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
            margin-top: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
            }
            
            .login-banner {
                padding: 2rem;
            }
            
            .banner-title {
                font-size: 1.5rem;
            }
            
            .login-container {
                padding: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }
            
            .login-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-banner">
    <div class="banner-logo">
        <img src="img/logo.png" alt="UniqThings Parfum Refill" class="banner-logo-img">
    </div>
    <p class="banner-text">Experience luxury fragrances at affordable prices. Authentic scents, premium quality.</p>
</div>
        
        <div class="login-container">
            <div class="logo">Welcome Back</div>
            <h2>Sign in to your account</h2>
            
            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="your@email.com">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn">Sign In</button>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <div class="links">
                <p>Don't have an account?</p>
                <a href="register.php">Create New Account</a>
                
                <p class="back-link">
                    <a href="index.php">← Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>