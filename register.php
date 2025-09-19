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
    <title>Daftar - Toko Parfum Premium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .register-container {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        
        .logo {
            text-align: center;
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 2rem;
        }
        
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .btn {
            width: 100%;
            background: #667eea;
            color: white;
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
            margin-bottom: 1rem;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }
        
        .links {
            text-align: center;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 1rem;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .register-container {
                padding: 2rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">🌸 Parfum Premium</div>
        <h2>Daftar Akun Baru</h2>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="nama">Nama Lengkap:</label>
                <input type="text" id="nama" name="nama" required value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="telepon">No. Telepon:</label>
                <input type="tel" id="telepon" name="telepon" required value="<?= htmlspecialchars($_POST['telepon'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="alamat">Alamat:</label>
                <textarea id="alamat" name="alamat" required><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn">Daftar</button>
        </form>
        
        <div class="links">
            <a href="login.php">Sudah punya akun? Login</a>
            <br><br>
            <a href="index.php">Kembali ke Beranda</a>
        </div>
    </div>
</body>
</html>