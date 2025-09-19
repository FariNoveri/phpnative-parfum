<?php
require_once 'config/database.php';

// Handle cart updates
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    
    if ($action == 'update' && $cart_id > 0) {
        $jumlah = (int)$_POST['jumlah'];
        
        if ($jumlah <= 0) {
            // Remove item if quantity is 0 or less
            if (isLoggedIn()) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, getUserId()]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND session_id = ?");
                $stmt->execute([$cart_id, $_SESSION['session_id']]);
            }
            $_SESSION['message'] = 'Item dihapus dari keranjang';
            $_SESSION['message_type'] = 'success';
        } else {
            // Check stock availability
            if (isLoggedIn()) {
                $stmt = $pdo->prepare("SELECT c.*, p.stok, p.nama_parfum FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?");
                $stmt->execute([$cart_id, getUserId()]);
            } else {
                $stmt = $pdo->prepare("SELECT c.*, p.stok, p.nama_parfum FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.session_id = ?");
                $stmt->execute([$cart_id, $_SESSION['session_id']]);
            }
            
            $cart_item = $stmt->fetch();
            
            if ($cart_item && $jumlah <= $cart_item['stok']) {
                if (isLoggedIn()) {
                    $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$jumlah, $cart_id, getUserId()]);
                } else {
                    $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE id = ? AND session_id = ?");
                    $stmt->execute([$jumlah, $cart_id, $_SESSION['session_id']]);
                }
                $_SESSION['message'] = 'Keranjang berhasil diupdate';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Jumlah melebihi stok yang tersedia';
                $_SESSION['message_type'] = 'error';
            }
        }
        redirect('cart.php');
    }
    
    if ($action == 'remove' && $cart_id > 0) {
        if (isLoggedIn()) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, getUserId()]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND session_id = ?");
            $stmt->execute([$cart_id, $_SESSION['session_id']]);
        }
        $_SESSION['message'] = 'Item dihapus dari keranjang';
        $_SESSION['message_type'] = 'success';
        redirect('cart.php');
    }
}

// Get cart items
if (isLoggedIn()) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nama_parfum, p.brand, p.harga, p.stok 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([getUserId()]);
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nama_parfum, p.brand, p.harga, p.stok 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.session_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['session_id']]);
}

$cart_items = $stmt->fetchAll();
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['harga'] * $item['jumlah'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Toko Parfum Premium</title>
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
        
        h1 {
            text-align: center;
            margin-bottom: 3rem;
            color: #333;
            font-size: 2.5rem;
        }
        
        .cart-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 2rem;
            border-bottom: 1px solid #eee;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-right: 1.5rem;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .product-brand {
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .product-price {
            font-size: 1.2rem;
            color: #e74c3c;
            font-weight: bold;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-right: 2rem;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 0.5rem 1rem;
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
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .cart-summary {
            background: #f8f9fa;
            padding: 2rem;
        }
        
        .total-price {
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
        }
        
        .checkout-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 2rem;
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
        
        .empty-cart {
            text-align: center;
            padding: 5rem 2rem;
        }
        
        .empty-cart h3 {
            margin-bottom: 1rem;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .product-image {
                align-self: center;
                margin-bottom: 1rem;
                margin-right: 0;
            }
            
            .quantity-controls {
                justify-content: center;
                margin: 1rem 0;
            }
            
            .checkout-actions {
                flex-direction: column;
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
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <h1>Keranjang Belanja</h1>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <?php if (empty($cart_items)): ?>
                <div class="cart-container">
                    <div class="empty-cart">
                        <h3>Keranjang belanja Anda kosong</h3>
                        <p>Silakan pilih produk untuk ditambahkan ke keranjang</p>
                        <br>
                        <a href="index.php" class="btn">Mulai Belanja</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="cart-container">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="product-image">🧴</div>
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($item['nama_parfum']) ?></div>
                                <div class="product-brand"><?= htmlspecialchars($item['brand']) ?></div>
                                <div class="product-price"><?= formatRupiah($item['harga']) ?></div>
                                <small>Stok tersedia: <?= $item['stok'] ?></small>
                            </div>
                            <div class="quantity-controls">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <input type="number" name="jumlah" value="<?= $item['jumlah'] ?>" 
                                           min="0" max="<?= $item['stok'] ?>" class="quantity-input">
                                    <button type="submit" class="btn">Update</button>
                                </form>
                            </div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Hapus item ini?')">Hapus</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="cart-summary">
                        <div class="total-price">
                            Total: <?= formatRupiah($total) ?>
                        </div>
                        <div class="checkout-actions">
                            <a href="index.php" class="btn">Lanjut Belanja</a>
                            <a href="checkout.php" class="btn btn-success">Checkout</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>