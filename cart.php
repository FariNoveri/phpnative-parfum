<?php
// cart.php - Updated to display volume selection
require_once 'config/database.php';

// Fetch cart items with volume information
if (isLoggedIn()) {
    $sql = "SELECT c.*, p.nama_parfum, p.brand, p.harga, p.stok, p.kategori,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
            COALESCE(pvp.price, p.harga) as volume_price,
            COALESCE(pvp.stock, p.stok) as volume_stock
            FROM cart c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN product_volume_prices pvp ON p.id = pvp.product_id AND c.volume_selected = pvp.volume_ml
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([getUserId()]);
} else {
    $sql = "SELECT c.*, p.nama_parfum, p.brand, p.harga, p.stok, p.kategori,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
            COALESCE(pvp.price, p.harga) as volume_price,
            COALESCE(pvp.stock, p.stok) as volume_stock
            FROM cart c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN product_volume_prices pvp ON p.id = pvp.product_id AND c.volume_selected = pvp.volume_ml
            WHERE c.session_id = ?
            ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['session_id']]);
}

$cart_items = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['volume_price'] * $item['jumlah'];
}

$shipping = 0;
if ($subtotal > 0 && $subtotal < 500000) {
    $shipping = 0;
}

$total = $subtotal + $shipping;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Parfum Refill Premium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            padding: 0.5rem;
            border-radius: 5px;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }

        .cart-section {
            padding: 3rem 0;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .cart-header h1 {
            font-size: 2rem;
        }

        .continue-shopping {
            background: #6c757d;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .continue-shopping:hover {
            background: #5a6268;
        }

        .cart-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .cart-items {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }

        .item-details h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .item-brand {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .item-volume {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .item-price {
            color: #e74c3c;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-btn {
            background: #667eea;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .quantity-btn:hover {
            background: #764ba2;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 0.3rem;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .remove-btn:hover {
            background: #c0392b;
        }

        .cart-summary {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .cart-summary h2 {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }

        .summary-row.total {
            border-top: 2px solid #eee;
            padding-top: 1rem;
            margin-top: 1rem;
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
        }

        .checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            margin-top: 1rem;
            transition: all 0.3s;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .empty-cart-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }

        .empty-cart h2 {
            margin-bottom: 1rem;
            color: #666;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        footer {
            background: #2c3e50;
            color: white;
            padding: 3rem 0 1rem;
            margin-top: 4rem;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-section h3 {
            margin-bottom: 1rem;
            color: #ecf0f1;
        }
        
        .footer-section p, .footer-section a {
            color: #bdc3c7;
            text-decoration: none;
            line-height: 1.8;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #34495e;
            color: #bdc3c7;
        }

        @media (max-width: 768px) {
            .cart-content {
                grid-template-columns: 1fr;
            }

            .cart-item {
                grid-template-columns: 80px 1fr;
            }

            .item-actions {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .cart-summary {
                position: static;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">
                <span>🌸</span> Parfum Refill Premium
            </div>
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

    <section class="cart-section">
        <div class="container">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="message <?= $_SESSION['message_type'] ?? 'success' ?>">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="cart-header">
                <h1>🛒 Keranjang Belanja</h1>
                <a href="index.php" class="continue-shopping">← Lanjut Belanja</a>
            </div>

            <?php if (empty($cart_items)): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h2>Keranjang Anda Kosong</h2>
                    <p>Belum ada produk yang ditambahkan ke keranjang</p>
                    <a href="index.php" class="continue-shopping" style="display: inline-block; margin-top: 1rem;">Mulai Belanja</a>
                </div>
            <?php else: ?>
                <div class="cart-content">
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
                                <img src="<?= htmlspecialchars($item['primary_image'] ?? 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2NjYyIvPjwvc3ZnPg==') ?>" 
                                     alt="<?= htmlspecialchars($item['nama_parfum']) ?>" 
                                     class="item-image">
                                
                                <div class="item-details">
                                    <h3><?= htmlspecialchars($item['nama_parfum']) ?></h3>
                                    <div class="item-brand"><?= htmlspecialchars($item['brand']) ?></div>
                                    <div class="item-volume">📦 Volume: <?= $item['volume_selected'] ?> ml</div>
                                    <div class="item-price"><?= formatRupiah($item['volume_price']) ?></div>
                                    <?php if ($item['jumlah'] > $item['volume_stock']): ?>
                                        <div style="color: #e74c3c; font-size: 0.9rem; margin-top: 0.5rem;">
                                            ⚠️ Stok hanya tersedia: <?= $item['volume_stock'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="item-actions">
                                    <form method="POST" action="utils/update_cart.php" class="quantity-control">
                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="action" value="decrease" class="quantity-btn">−</button>
                                        <input type="number" name="jumlah" value="<?= $item['jumlah'] ?>" 
                                               min="1" max="<?= $item['volume_stock'] ?>" 
                                               class="quantity-input" readonly>
                                        <button type="submit" name="action" value="increase" class="quantity-btn">+</button>
                                    </form>
                                    
                                    <form method="POST" action="remove_from_cart.php">
                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="remove-btn" 
                                                onclick="return confirm('Hapus produk dari keranjang?')">
                                            🗑️ Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-summary">
                        <h2>Ringkasan Pesanan</h2>
                        
                        <div class="summary-row">
                            <span>Subtotal (<?= count($cart_items) ?> item)</span>
                            <span><?= formatRupiah($subtotal) ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Ongkos Kirim</span>
                            <span><?= $shipping > 0 ? formatRupiah($shipping) : 'GRATIS' ?></span>
                        </div>

                        <?php if ($subtotal < 1000 && $subtotal > 0): ?>
                            <div style="font-size: 0.9rem; color: #667eea; margin: 1rem 0;">
                                💡 Belanja Rp <?= formatRupiah(1000 - $subtotal) ?> lagi untuk gratis ongkir!
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Total</span>
                            <span><?= formatRupiah($total) ?></span>
                        </div>
                        
                        <a href="checkout.php">
                            <button class="checkout-btn">
                                Lanjut ke Pembayaran →
                            </button>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>🌸 Parfum Refill Premium</h3>
                    <p>Toko parfum refill terpercaya dengan kualitas original dan harga terjangkau.</p>
                </div>
                <div class="footer-section">
                    <h3>Layanan Pelanggan</h3>
                    <p><a href="tel:+6281234567890">📞 +62812-3456-7890</a></p>
                    <p><a href="mailto:cs@parfumrefill.com">✉️ cs@parfumrefill.com</a></p>
                </div>
                <div class="footer-section">
                    <h3>Jaminan Kualitas</h3>
                    <p>✅ 100% Aroma Original</p>
                    <p>🚚 Gratis Ongkir min. Rp 500K</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Parfum Refill Premium. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>