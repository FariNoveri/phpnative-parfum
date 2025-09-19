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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Toko Parfum Premium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 2rem;
            display: inline-block;
        }
        
        .logo {
            font-size: 2.5rem;
            color: #1e3c72;
            margin-bottom: 1rem;
        }
        
        h2 {
            color: #333;
            margin-bottom: 2rem;
            font-size: 1.8rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #fafafa;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #1e3c72;
            background: white;
            box-shadow: 0 0 10px rgba(30, 60, 114, 0.1);
        }
        
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 1.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(30, 60, 114, 0.3);
        }
        
        .error {
            background: #ff6b6b;
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: none;
        }
        
        .links {
            text-align: center;
            margin-top: 2rem;
        }
        
        .links a {
            color: #1e3c72;
            text-decoration: none;
            margin: 0 1rem;
            font-weight: 500;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .security-notice {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .logo {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="admin-badge">🔒 ADMIN AREA</div>
        <div class="logo">🌸 Parfum Premium</div>
        <h2>Admin Login</h2>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">📧 Email Admin:</label>
                <input type="email" id="email" name="email" required 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                       placeholder="admin@parfumpremium.com">
            </div>
            
            <div class="form-group">
                <label for="password">🔑 Password:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Masukkan password admin">
            </div>
            
            <button type="submit" class="btn">🚀 LOGIN ADMIN</button>
        </form>
        
        <div class="security-notice">
            🛡️ <strong>Area Admin:</strong> Akses terbatas hanya untuk administrator sistem. 
            Semua aktivitas akan dicatat dalam log sistem.
        </div>
        
        <div class="links">
            <a href="index.php">← Kembali ke Website</a>
        </div>
    </div>
</body>
</html>