<?php
require_once 'config/auth.php';
requireAdmin();

$action = $_GET['action'] ?? '';
$product_id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_POST) {
    $nama_parfum = trim($_POST['nama_parfum']);
    $brand = trim($_POST['brand']);
    $harga = (float)$_POST['harga'];
    $stok = (int)$_POST['stok'];
    $deskripsi = trim($_POST['deskripsi']);
    $kategori = $_POST['kategori'];
    $gambar = $_POST['gambar_filename'] ?? '';
    
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO products (nama_parfum, brand, harga, stok, deskripsi, kategori, gambar) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$nama_parfum, $brand, $harga, $stok, $deskripsi, $kategori, $gambar])) {
            logAdminActivity('ADD_PRODUCT', "Menambah produk: $nama_parfum");
            $_SESSION['message'] = 'Produk berhasil ditambahkan';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Gagal menambahkan produk';
            $_SESSION['message_type'] = 'error';
        }
        redirect('products.php');
    }
    
    if ($action === 'edit' && $product_id > 0) {
        $stmt = $pdo->prepare("UPDATE products SET nama_parfum = ?, brand = ?, harga = ?, stok = ?, deskripsi = ?, kategori = ?, gambar = ? WHERE id = ?");
        if ($stmt->execute([$nama_parfum, $brand, $harga, $stok, $deskripsi, $kategori, $gambar, $product_id])) {
            logAdminActivity('EDIT_PRODUCT', "Mengedit produk: $nama_parfum (ID: $product_id)");
            $_SESSION['message'] = 'Produk berhasil diupdate';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Gagal mengupdate produk';
            $_SESSION['message_type'] = 'error';
        }
        redirect('products.php');
    }
}

// Handle delete
if ($action === 'delete' && $product_id > 0) {
    $stmt = $pdo->prepare("SELECT nama_parfum FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product_name = $stmt->fetch()['nama_parfum'] ?? '';
    
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$product_id])) {
        logAdminActivity('DELETE_PRODUCT', "Menghapus produk: $product_name (ID: $product_id)");
        $_SESSION['message'] = 'Produk berhasil dihapus';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Gagal menghapus produk';
        $_SESSION['message_type'] = 'error';
    }
    redirect('products.php');
}

// Get product for editing
$product = null;
if ($action === 'edit' && $product_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        redirect('products.php');
    }
}

// Get all products
$search = $_GET['search'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$sort = $_GET['sort'] ?? 'created_at DESC';

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (nama_parfum LIKE ? OR brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($kategori_filter) {
    $sql .= " AND kategori = ?";
    $params[] = $kategori_filter;
}

$sql .= " ORDER BY $sort";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Admin</title>
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
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
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
        
        .form-container {
            padding: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #555;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 0.8rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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
        
        .table-container {
            padding: 2rem;
            overflow-x: auto;
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .products-table th,
        .products-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .products-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #555;
        }
        
        .products-table tr:hover {
            background: #f8f9fa;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .product-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .product-brand {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stock-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .stock-high {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .stock-low {
            background: #f8d7da;
            color: #721c24;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 15px;
            max-width: 500px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .top-bar {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .table-container {
                padding: 1rem;
            }
        }
        
        .image-upload-container {
            border: 2px dashed #e1e1e1;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        
        .current-image {
            margin-bottom: 1rem;
        }
        
        .no-image {
            padding: 2rem;
            color: #666;
        }
        
        .upload-controls {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .upload-methods {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            justify-content: center;
        }
        
        .upload-method {
            padding: 0.5rem 1rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-method.active {
            border-color: #667eea;
            background: #f0f4ff;
            color: #667eea;
        }
        
        .url-input-container {
            display: none;
            margin-top: 1rem;
            gap: 0.5rem;
        }
        
        .url-input-container.active {
            display: flex;
        }
        
        .url-input {
            flex: 1;
            padding: 0.8rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .upload-progress {
            margin-top: 1rem;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s;
        }
        
        .progress-text {
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="admin-container">
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
                        <a href="products.php" class="nav-link active">
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
                        <a href="users.php" class="nav-link">
                            <span class="nav-icon">👥</span>
                            Kelola User
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">
                    <?php if ($action === 'add'): ?>
                        ➕ Tambah Produk
                    <?php elseif ($action === 'edit'): ?>
                        ✏️ Edit Produk
                    <?php else: ?>
                        🧴 Kelola Produk
                    <?php endif; ?>
                </h1>
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

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="content-card">
                    <div class="card-header">
                        <div class="card-title">
                            <?= $action === 'add' ? 'Tambah Produk Baru' : 'Edit Produk' ?>
                        </div>
                        <a href="products.php" class="btn">← Kembali</a>
                    </div>
                    
                    <div class="form-container">
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nama_parfum">Nama Parfum *</label>
                                    <input type="text" id="nama_parfum" name="nama_parfum" required
                                           value="<?= htmlspecialchars($product['nama_parfum'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="brand">Brand *</label>
                                    <input type="text" id="brand" name="brand" required
                                           value="<?= htmlspecialchars($product['brand'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="harga">Harga *</label>
                                    <input type="number" id="harga" name="harga" required min="0" step="0.01"
                                           value="<?= $product['harga'] ?? '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="stok">Stok *</label>
                                    <input type="number" id="stok" name="stok" required min="0"
                                           value="<?= $product['stok'] ?? '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="kategori">Kategori *</label>
                                    <select id="kategori" name="kategori" required>
                                        <option value="">Pilih Kategori</option>
                                        <option value="pria" <?= ($product['kategori'] ?? '') === 'pria' ? 'selected' : '' ?>>Pria</option>
                                        <option value="wanita" <?= ($product['kategori'] ?? '') === 'wanita' ? 'selected' : '' ?>>Wanita</option>
                                        <option value="unisex" <?= ($product['kategori'] ?? '') === 'unisex' ? 'selected' : '' ?>>Unisex</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="deskripsi">Deskripsi</label>
                                <textarea id="deskripsi" name="deskripsi"><?= htmlspecialchars($product['deskripsi'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="gambar">Gambar Produk</label>
                                <input type="hidden" name="gambar_filename" id="gambar_filename" value="<?= htmlspecialchars($product['gambar'] ?? '') ?>">
                                
                                <div class="image-upload-container">
                                    <div class="current-image" id="currentImage">
                                    <?php if (!empty($product['gambar'])): ?>
                                            <?php if (filter_var($product['gambar'], FILTER_VALIDATE_URL)): ?>
                                                <img src="<?= htmlspecialchars($product['gambar']) ?>" 
                                                     alt="Current image" style="max-width: 200px; max-height: 200px; object-fit: cover; border-radius: 8px;">
                                            <?php else: ?>
                                                <div class="no-image">
                                                    <div style="font-size: 4rem; color: #ccc;">🧴</div>
                                                    <p>Gambar tidak valid</p>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="no-image">
                                                <div style="font-size: 4rem; color: #ccc;">🧴</div>
                                                <p>Belum ada gambar</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="upload-methods">
                                        <div class="upload-method active" onclick="switchUploadMethod('file')" id="fileMethod">
                                            📁 Upload File
                                        </div>
                                        <div class="upload-method" onclick="switchUploadMethod('url')" id="urlMethod">
                                            🔗 Dari URL
                                        </div>
                                    </div>
                                    
                                    <div class="upload-controls" id="fileControls">
                                        <input type="file" id="gambar" name="gambar" accept="image/*" style="display: none;">
                                        <button type="button" onclick="document.getElementById('gambar').click()" class="btn btn-sm">
                                            📷 Pilih Gambar
                                        </button>
                                        <?php if (!empty($product['gambar'])): ?>
                                            <button type="button" onclick="removeImage()" class="btn btn-sm btn-danger">
                                                🗑️ Hapus
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="url-input-container" id="urlControls">
                                        <input type="url" class="url-input" id="imageUrl" placeholder="Masukkan URL gambar (https://...)">
                                        <button type="button" onclick="loadImageFromUrl()" class="btn btn-sm">
                                            🔗 Muat
                                        </button>
                                    </div>
                                    
                                    <div class="upload-progress" id="uploadProgress" style="display: none;">
                                        <div class="progress-bar">
                                            <div class="progress-fill" id="progressFill"></div>
                                        </div>
                                        <div class="progress-text" id="progressText">Processing...</div>
                                    </div>
                                </div>
                                
                                <small style="color: #666; margin-top: 0.5rem; display: block;">
                                    Format: JPG, PNG, GIF, WEBP. Maksimal 5MB untuk file upload.
                                    <br>Untuk URL: pastikan link mengarah langsung ke file gambar.
                                </small>
                            </div>
                            
                            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                                <button type="submit" class="btn btn-success">
                                    <?= $action === 'add' ? '➕ Tambah Produk' : '💾 Update Produk' ?>
                                </button>
                                <a href="products.php" class="btn">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: ?>
                <div class="content-card">
                    <div class="card-header">
                        <div class="card-title">Daftar Produk</div>
                        <a href="products.php?action=add" class="btn btn-success">➕ Tambah Produk</a>
                    </div>
                    
                    <div class="filters">
                        <form method="GET" class="filter-form">
                            <div class="form-group">
                                <label>Cari Produk</label>
                                <input type="text" name="search" placeholder="Nama atau brand..."
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Kategori</label>
                                <select name="kategori">
                                    <option value="">Semua Kategori</option>
                                    <option value="pria" <?= $kategori_filter === 'pria' ? 'selected' : '' ?>>Pria</option>
                                    <option value="wanita" <?= $kategori_filter === 'wanita' ? 'selected' : '' ?>>Wanita</option>
                                    <option value="unisex" <?= $kategori_filter === 'unisex' ? 'selected' : '' ?>>Unisex</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Urutkan</label>
                                <select name="sort">
                                    <option value="created_at DESC" <?= $sort === 'created_at DESC' ? 'selected' : '' ?>>Terbaru</option>
                                    <option value="nama_parfum ASC" <?= $sort === 'nama_parfum ASC' ? 'selected' : '' ?>>Nama A-Z</option>
                                    <option value="harga ASC" <?= $sort === 'harga ASC' ? 'selected' : '' ?>>Harga Terendah</option>
                                    <option value="harga DESC" <?= $sort === 'harga DESC' ? 'selected' : '' ?>>Harga Tertinggi</option>
                                    <option value="stok ASC" <?= $sort === 'stok ASC' ? 'selected' : '' ?>>Stok Terendah</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn">🔍 Cari</button>
                            </div>
                            
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <a href="products.php" class="btn btn-warning">🔄 Reset</a>
                            </div>
                        </form>
                    </div>
                    
                    <div class="table-container">
                        <?php if (empty($products)): ?>
                            <div style="text-align: center; padding: 3rem; color: #666;">
                                <h3>Tidak ada produk ditemukan</h3>
                                <p>Silakan tambah produk baru atau ubah filter pencarian</p>
                                <br>
                                <a href="products.php?action=add" class="btn btn-success">➕ Tambah Produk Pertama</a>
                            </div>
                        <?php else: ?>
                            <table class="products-table">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Harga</th>
                                        <th>Stok</th>
                                        <th>Kategori</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 1rem;">
                                                    <div class="product-image">
                                                        <?php if ($product['gambar']): ?>
                                                            <?php if (filter_var($product['gambar'], FILTER_VALIDATE_URL)): ?>
                                                                <img src="<?= htmlspecialchars($product['gambar']) ?>" 
                                                                     alt="<?= htmlspecialchars($product['nama_parfum']) ?>" 
                                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;"
                                                                     onerror="this.parentElement.innerHTML='🧴';">
                                                            <?php else: ?>
                                                                🧴
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            🧴
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="product-name"><?= htmlspecialchars($product['nama_parfum']) ?></div>
                                                        <div class="product-brand"><?= htmlspecialchars($product['brand']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= formatRupiah($product['harga']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="stock-badge <?= $product['stok'] <= 5 ? 'stock-low' : ($product['stok'] <= 15 ? 'stock-medium' : 'stock-high') ?>">
                                                    <?= $product['stok'] ?> unit
                                                </span>
                                            </td>
                                            <td><?= ucfirst($product['kategori']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($product['created_at'])) ?></td>
                                            <td>
                                                <div class="actions">
                                                    <a href="products.php?action=edit&id=<?= $product['id'] ?>" 
                                                       class="btn btn-sm">✏️ Edit</a>
                                                    <a href="products.php?action=delete&id=<?= $product['id'] ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Yakin ingin menghapus produk ini?')">🗑️ Hapus</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div style="margin-top: 2rem; text-align: center; color: #666;">
                                Total: <?= count($products) ?> produk
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        let currentUploadMethod = 'file';
        
        function switchUploadMethod(method) {
            currentUploadMethod = method;
            
            // Update UI
            document.getElementById('fileMethod').classList.toggle('active', method === 'file');
            document.getElementById('urlMethod').classList.toggle('active', method === 'url');
            
            document.getElementById('fileControls').style.display = method === 'file' ? 'flex' : 'none';
            document.getElementById('urlControls').classList.toggle('active', method === 'url');
        }
        
        // File upload handler
        document.getElementById('gambar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Tipe file tidak diizinkan. Hanya JPG, PNG, GIF, WEBP yang diperbolehkan.');
                this.value = '';
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran file terlalu besar. Maksimal 5MB.');
                this.value = '';
                return;
            }
            
            uploadFile(file);
        });
        
        function uploadFile(file) {
            document.getElementById('uploadProgress').style.display = 'block';
            document.getElementById('progressText').textContent = 'Mengupload ke ImgBB...';
            
            const formData = new FormData();
            formData.append('gambar', file);
            
            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('uploadProgress').style.display = 'none';
                
                if (data.success) {
                    // Simpan URL ImgBB langsung ke database
                    document.getElementById('gambar_filename').value = data.url;
                    updateImagePreview(data.url);
                    
                    alert('✅ Gambar berhasil diupload ke ImgBB!\n🔗 URL: ' + data.url);
                    
                    // Show additional info
                    console.log('ImgBB Response:', {
                        url: data.url,
                        display_url: data.display_url,
                        thumb: data.thumb
                    });
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('uploadProgress').style.display = 'none';
                alert('❌ Terjadi kesalahan saat mengupload gambar');
                console.error('Error:', error);
            });
        }
        
        function loadImageFromUrl() {
            const url = document.getElementById('imageUrl').value.trim();
            
            if (!url) {
                alert('Silakan masukkan URL gambar');
                return;
            }
            
            if (!isValidImageUrl(url)) {
                alert('URL tidak valid. Pastikan URL mengarah ke file gambar (jpg, png, gif, webp)');
                return;
            }
            
            document.getElementById('uploadProgress').style.display = 'block';
            document.getElementById('progressText').textContent = 'Memvalidasi URL gambar...';
            
            // Test if image can be loaded
            const img = new Image();
            img.onload = function() {
                document.getElementById('uploadProgress').style.display = 'none';
                
                // Langsung simpan URL ke database
                document.getElementById('gambar_filename').value = url;
                updateImagePreview(url);
                document.getElementById('imageUrl').value = '';
                
                alert('✅ Gambar berhasil dimuat dari URL!\n🔗 Disimpan langsung ke database');
            };
            
            img.onerror = function() {
                document.getElementById('uploadProgress').style.display = 'none';
                alert('❌ Gagal memuat gambar dari URL. Pastikan URL valid dan dapat diakses.');
            };
            
            img.src = url;
        }
        
        function isValidImageUrl(url) {
            try {
                const urlObj = new URL(url);
                const pathname = urlObj.pathname.toLowerCase();
                const validExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
                
                return validExtensions.some(ext => pathname.endsWith(ext)) || 
                       url.includes('imgur.com') || 
                       url.includes('cloudinary.com') ||
                       url.includes('unsplash.com') ||
                       url.includes('pexels.com');
            } catch {
                return false;
            }
        }
        
        function updateImagePreview(src) {
            document.getElementById('currentImage').innerHTML = `
                <img src="${src}" 
                     alt="Product image" 
                     style="max-width: 200px; max-height: 200px; object-fit: cover; border-radius: 8px;">
            `;
        }
        
        function removeImage() {
            if (confirm('Yakin ingin menghapus gambar?')) {
                document.getElementById('gambar_filename').value = '';
                document.getElementById('currentImage').innerHTML = `
                    <div class="no-image">
                        <div style="font-size: 4rem; color: #ccc;">🧴</div>
                        <p>Belum ada gambar</p>
                    </div>
                `;
                document.getElementById('gambar').value = '';
                document.getElementById('imageUrl').value = '';
            }
        }
        
        // Initialize upload method on page load
        document.addEventListener('DOMContentLoaded', function() {
            switchUploadMethod('file');
        });
    </script>
</body>
</html>