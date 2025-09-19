<?php
require_once 'config/database.php';

// Get products
$kategori = $_GET['kategori'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($kategori) {
    $sql .= " AND kategori = ?";
    $params[] = $kategori;
}

if ($search) {
    $sql .= " AND (nama_parfum LIKE ? OR brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

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
    <title>Toko Parfum Premium</title>
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
        
        .cart-icon {
            position: relative;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem;
            border-radius: 50%;
        }
        
        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .hero {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 400"><rect fill="%23667eea" width="1000" height="400"/></svg>');
            background-size: cover;
            color: white;
            text-align: center;
            padding: 4rem 0;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        
        .search-filter {
            background: white;
            padding: 2rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-form input,
        .filter-form select {
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .products {
            padding: 3rem 0;
        }
        
        .products h2 {
            text-align: center;
            margin-bottom: 3rem;
            color: #333;
            font-size: 2.5rem;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 250px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        .product-info {
            padding: 1.5rem;
        }
        
        .product-brand {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .product-name {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .product-price {
            font-size: 1.4rem;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .product-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        
        .add-to-cart {
            width: 100%;
            background: #27ae60;
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }
        
        .add-to-cart:hover {
            background: #229954;
        }
        
        footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
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
                <?php if (isLoggedIn()): ?>
                    <a href="profile.php">Profil</a>
                    <a href="orders.php">Pesanan</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Daftar</a>
                <?php endif; ?>
                <a href="cart.php" class="cart-icon">
                    🛒
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-count"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </nav>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Koleksi Parfum Premium</h1>
            <p>Temukan aroma yang sempurna untuk kepribadian Anda</p>
        </div>
    </section>

    <section class="search-filter">
        <div class="container">
            <form class="filter-form" method="GET">
                <input type="text" name="search" placeholder="Cari parfum atau brand..." value="<?= htmlspecialchars($search) ?>">
                <select name="kategori">
                    <option value="">Semua Kategori</option>
                    <option value="pria" <?= $kategori == 'pria' ? 'selected' : '' ?>>Pria</option>
                    <option value="wanita" <?= $kategori == 'wanita' ? 'selected' : '' ?>>Wanita</option>
                    <option value="unisex" <?= $kategori == 'unisex' ? 'selected' : '' ?>>Unisex</option>
                </select>
                <button type="submit" class="btn">Cari</button>
                <a href="index.php" class="btn" style="background: #95a5a6;">Reset</a>
            </form>
        </div>
    </section>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="container">
            <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                <?= $_SESSION['message'] ?>
            </div>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <section class="products">
        <div class="container">
            <h2>Koleksi Parfum Kami</h2>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($product['gambar']): ?>
    <?php if (filter_var($product['gambar'], FILTER_VALIDATE_URL)): ?>
        <img src="<?= htmlspecialchars($product['gambar']) ?>" 
             alt="<?= htmlspecialchars($product['nama_parfum']) ?>" 
             style="width: 100%; height: 100%; object-fit: cover;"
             onerror="this.parentElement.innerHTML='🧴';">
    <?php else: ?>
        <!-- Fallback untuk file lokal jika ada -->
        <img src="assets/images/products/<?= htmlspecialchars($product['gambar']) ?>" 
             alt="<?= htmlspecialchars($product['nama_parfum']) ?>" 
             style="width: 100%; height: 100%; object-fit: cover;"
             onerror="this.parentElement.innerHTML='🧴';">
    <?php endif; ?>
<?php else: ?>
    🧴
<?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-brand"><?= htmlspecialchars($product['brand']) ?></div>
                            <h3 class="product-name"><?= htmlspecialchars($product['nama_parfum']) ?></h3>
                            <div class="product-price"><?= formatRupiah($product['harga']) ?></div>
                            <p class="product-description"><?= htmlspecialchars($product['deskripsi']) ?></p>
                            <?php if ($product['stok'] > 0): ?>
                                <form method="POST" action="add_to_cart.php">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="add-to-cart">Tambah ke Keranjang</button>
                                </form>
                            <?php else: ?>
                                <button class="add-to-cart" style="background: #95a5a6; cursor: not-allowed;" disabled>Stok Habis</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($products)): ?>
                <div style="text-align: center; padding: 3rem;">
                    <h3>Tidak ada produk ditemukan</h3>
                    <p>Coba ubah filter pencarian Anda</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2024 Toko Parfum Premium. Semua hak dilindungi.</p>
            <p>Hubungi kami di WhatsApp: <strong>+62812-3456-7890</strong></p>
        </div>
    </footer>
</body>
</html>