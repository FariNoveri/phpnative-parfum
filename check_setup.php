<?php
// check_setup.php - Script untuk mengecek setup sistem
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Setup Check - Toko Parfum Premium</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #f8f9fa;
            line-height: 1.6;
        }
        
        .header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        
        .check-item {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .status {
            font-size: 2rem;
        }
        
        .details {
            flex: 1;
        }
        
        .check-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .check-desc {
            color: #666;
            font-size: 0.9rem;
        }
        
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
        
        .action-btn {
            background: #667eea;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .action-btn:hover {
            background: #5a67d8;
        }
        
        .fix-btn {
            background: #27ae60;
        }
        
        .fix-btn:hover {
            background: #229954;
        }
        
        .summary {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔧 Setup System Check</h1>
        <p>Toko Parfum Premium - Diagnostic Tool</p>
    </div>

    <?php
    $checks = [];
    $total_issues = 0;

    // 1. Check database connection
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=toko_parfum;charset=utf8", 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $checks[] = [
            'title' => 'Database Connection',
            'desc' => 'Koneksi ke database toko_parfum berhasil',
            'status' => '✅',
            'type' => 'success'
        ];
        
        // Check admin user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        $admin_count = $stmt->fetchColumn();
        
        if ($admin_count > 0) {
            $checks[] = [
                'title' => 'Admin User',
                'desc' => "Ditemukan $admin_count admin user",
                'status' => '✅',
                'type' => 'success'
            ];
        } else {
            $checks[] = [
                'title' => 'Admin User',
                'desc' => 'Tidak ada admin user. Jalankan fix_admin_password.php',
                'status' => '⚠️',
                'type' => 'warning'
            ];
            $total_issues++;
        }
        
    } catch (PDOException $e) {
        $checks[] = [
            'title' => 'Database Connection',
            'desc' => 'Gagal connect ke database: ' . $e->getMessage(),
            'status' => '❌',
            'type' => 'error'
        ];
        $total_issues++;
    }

    // 2. Check upload directory
    $upload_dir = 'assets/images/products/';
    if (!is_dir($upload_dir)) {
        if (mkdir($upload_dir, 0755, true)) {
            $checks[] = [
                'title' => 'Upload Directory',
                'desc' => 'Folder upload berhasil dibuat: ' . $upload_dir,
                'status' => '✅',
                'type' => 'success'
            ];
        } else {
            $checks[] = [
                'title' => 'Upload Directory',
                'desc' => 'Gagal membuat folder upload: ' . $upload_dir,
                'status' => '❌',
                'type' => 'error'
            ];
            $total_issues++;
        }
    } else {
        if (is_writable($upload_dir)) {
            $checks[] = [
                'title' => 'Upload Directory',
                'desc' => 'Folder upload sudah ada dan writable',
                'status' => '✅',
                'type' => 'success'
            ];
        } else {
            $checks[] = [
                'title' => 'Upload Directory',
                'desc' => 'Folder upload ada tapi tidak writable. Chmod 755 atau 775',
                'status' => '⚠️',
                'type' => 'warning'
            ];
            $total_issues++;
        }
    }

    // 3. Check PHP extensions
    $required_extensions = ['pdo', 'pdo_mysql', 'gd', 'fileinfo'];
    foreach ($required_extensions as $ext) {
        if (extension_loaded($ext)) {
            $checks[] = [
                'title' => "PHP Extension: $ext",
                'desc' => 'Extension sudah aktif',
                'status' => '✅',
                'type' => 'success'
            ];
        } else {
            $checks[] = [
                'title' => "PHP Extension: $ext",
                'desc' => 'Extension tidak aktif. Aktifkan di php.ini',
                'status' => '❌',
                'type' => 'error'
            ];
            $total_issues++;
        }
    }

    // 4. Check PHP settings
    $upload_max = ini_get('upload_max_filesize');
    $post_max = ini_get('post_max_size');
    
    if (return_bytes($upload_max) >= 5*1024*1024 && return_bytes($post_max) >= 5*1024*1024) {
        $checks[] = [
            'title' => 'PHP Upload Settings',
            'desc' => "upload_max_filesize: $upload_max, post_max_size: $post_max",
            'status' => '✅',
            'type' => 'success'
        ];
    } else {
        $checks[] = [
            'title' => 'PHP Upload Settings',
            'desc' => "Terlalu kecil untuk upload 5MB. Set minimal 8M di php.ini",
            'status' => '⚠️',
            'type' => 'warning'
        ];
        $total_issues++;
    }

    // 5. Check important files
    $important_files = [
        'config/database.php' => 'Database config',
        'admin/dashboard.php' => 'Admin dashboard',
        'admin/products.php' => 'Product management',
        'admin/upload_handler.php' => 'Upload handler',
        'index.php' => 'Main website'
    ];

    foreach ($important_files as $file => $desc) {
        if (file_exists($file)) {
            $checks[] = [
                'title' => $desc,
                'desc' => "File $file ada",
                'status' => '✅',
                'type' => 'success'
            ];
        } else {
            $checks[] = [
                'title' => $desc,
                'desc' => "File $file tidak ditemukan",
                'status' => '❌',
                'type' => 'error'
            ];
            $total_issues++;
        }
    }

    function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }

    // Display results
    foreach ($checks as $check) {
        echo "<div class='check-item'>";
        echo "<div class='status'>{$check['status']}</div>";
        echo "<div class='details'>";
        echo "<div class='check-title {$check['type']}'>{$check['title']}</div>";
        echo "<div class='check-desc'>{$check['desc']}</div>";
        echo "</div>";
        echo "</div>";
    }
    ?>

    <div class="summary">
        <?php if ($total_issues == 0): ?>
            <h2 style="color: #27ae60;">🎉 Sistem Siap Digunakan!</h2>
            <p>Semua komponen sistem berfungsi dengan baik.</p>
            <br>
            <a href="index.php" class="action-btn">🌐 Buka Website</a>
            <a href="admin_login.php" class="action-btn" style="background: #f39c12;">🔐 Admin Login</a>
        <?php else: ?>
            <h2 style="color: #e74c3c;">⚠️ Ditemukan <?= $total_issues ?> Issue</h2>
            <p>Perbaiki issue di atas sebelum menggunakan sistem.</p>
            <br>
            <a href="fix_admin_password.php" class="action-btn fix-btn">🔧 Fix Admin Password</a>
        <?php endif; ?>
        
        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee; font-size: 0.9rem; color: #666;">
            <p><strong>Tips:</strong></p>
            <ul style="text-align: left; display: inline-block;">
                <li>Hapus file ini setelah setup selesai</li>
                <li>Backup database secara berkala</li>
                <li>Monitor folder upload dari file mencurigakan</li>
                <li>Update password admin secara berkala</li>
            </ul>
        </div>
    </div>
</body>
</html>