<?php
// cart.php - Updated to display volume selection
require_once 'config/database.php';

// Ambil settings ongkir dari database
$settings_sql = "SELECT `key`, `value` FROM settings WHERE `key` IN ('shipping_rate', 'free_shipping_threshold')";
$settings_stmt = $pdo->query($settings_sql);
$settings = [];
while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}
$shipping_rate = (int)($settings['shipping_rate'] ?? 15000);
$free_shipping_threshold = (int)($settings['free_shipping_threshold'] ?? 500000);

// Fetch cart items with volume information
if (isLoggedIn()) {
    $sql = "SELECT c.*, p.nama_parfum, p.harga, p.stok, p.kategori,
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
    $sql = "SELECT c.*, p.nama_parfum, p.harga, p.stok, p.kategori,
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
if ($subtotal > 0 && $subtotal < $free_shipping_threshold) {
    $shipping = $shipping_rate;
}

$total = $subtotal + $shipping;

// Get cart count
$cart_count = array_sum(array_column($cart_items, 'jumlah'));

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Parfum Refill Premium</title>
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
            line-height: 1.6;
            color: #2c2c2c;
            background-color: #fff;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
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
        
        /* Top Bar */
        .top-bar {
            background: #f8f8f8;
            padding: 8px 0;
            font-size: 12px;
            text-align: center;
            color: #666;
        }
        
        /* Header */
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

        .cart-section {
            padding: 60px 0;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .cart-header h1 {
            font-size: 32px;
            font-weight: 300;
            letter-spacing: 1px;
            color: #2c2c2c;
            text-transform: uppercase;
        }

        .continue-shopping {
            background: transparent;
            color: #666;
            padding: 12px 30px;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .continue-shopping:hover {
            border-color: #2c2c2c;
            color: #2c2c2c;
        }

        .cart-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }

        .cart-items {
            background: #fafafa;
            border: 1px solid #f0f0f0;
            padding: 30px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 25px;
            padding: 25px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .cart-item:first-child {
            padding-top: 0;
        }

        .cart-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            background: white;
        }

        .item-details h3 {
            margin-bottom: 10px;
            font-size: 16px;
            font-weight: 600;
            color: #2c2c2c;
        }

        .item-volume {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .item-price {
            color: #c41e3a;
            font-weight: 400;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .item-stock-warning {
            color: #c41e3a;
            font-size: 12px;
            margin-top: 8px;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 15px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quantity-btn {
            background: white;
            color: #2c2c2c;
            border: 1px solid #e0e0e0;
            width: 35px;
            height: 35px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            font-weight: 300;
        }

        .quantity-btn:hover:not(:disabled) {
            background: #2c2c2c;
            color: white;
            border-color: #2c2c2c;
        }

        .quantity-btn:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 8px;
            border: 1px solid #e0e0e0;
            background: white;
            font-size: 14px;
        }

        .remove-btn {
            background: transparent;
            color: #999;
            border: 1px solid #e0e0e0;
            padding: 8px 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .remove-btn:hover {
            background: #c41e3a;
            color: white;
            border-color: #c41e3a;
        }

        .cart-summary {
            background: #fafafa;
            border: 1px solid #f0f0f0;
            padding: 30px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .cart-summary h2 {
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
            color: #2c2c2c;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }

        .summary-row span:last-child {
            font-weight: 500;
            color: #2c2c2c;
        }

        .summary-row.total {
            border-top: 1px solid #e0e0e0;
            padding-top: 20px;
            margin-top: 20px;
            font-size: 18px;
            font-weight: 600;
            color: #2c2c2c;
        }

        .free-shipping-info {
            font-size: 12px;
            color: #2c2c2c;
            background: white;
            padding: 12px 15px;
            margin: 20px 0;
            border-left: 3px solid #2c2c2c;
        }

        .checkout-btn {
            width: 100%;
            background: #2c2c2c;
            color: white;
            border: none;
            padding: 18px 40px;
            cursor: pointer;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            font-weight: 600;
        }

        .checkout-btn:hover:not(:disabled) {
            background: #c41e3a;
        }

        .checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .empty-cart {
            text-align: center;
            padding: 80px 40px;
            background: #fafafa;
            border: 1px solid #f0f0f0;
        }

        .empty-cart-icon {
            font-size: 80px;
            margin-bottom: 25px;
            opacity: 0.3;
        }

        .empty-cart h2 {
            font-size: 24px;
            font-weight: 300;
            margin-bottom: 15px;
            color: #2c2c2c;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .empty-cart p {
            font-size: 14px;
            color: #999;
            margin-bottom: 30px;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            margin-bottom: 30px;
            border-left: 3px solid;
            font-size: 14px;
        }

        .alert.success {
            background: #f0fdf4;
            color: #166534;
            border-color: #22c55e;
        }

        .alert.error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #ef4444;
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

        @media (max-width: 1024px) {
            .cart-content {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                gap: 15px;
            }
            
            .nav-links a {
                font-size: 12px;
            }

            .cart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .cart-header h1 {
                font-size: 24px;
            }

            .cart-item {
                grid-template-columns: 80px 1fr;
            }

            .item-image {
                width: 80px;
                height: 80px;
            }

            .item-actions {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                margin-top: 15px;
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
        <a href="index.php" class="logo">
            <img src="img/logo.png" alt="UniqThings Parfum Refill" class="logo-img">
        </a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <?php if (isLoggedIn()): ?>
                <a href="profile.php">Account</a>
                <a href="orders.php">Orders</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
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

    <section class="cart-section">
        <div class="container">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert <?= $_SESSION['message_type'] ?? 'success' ?>">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="cart-header">
                <h1>Shopping Cart</h1>
                <a href="index.php" class="continue-shopping">← Continue Shopping</a>
            </div>

            <?php if (empty($cart_items)): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h2>Your Cart is Empty</h2>
                    <p>No products added to cart yet</p>
                    <a href="index.php" class="continue-shopping" style="display: inline-block; margin-top: 1rem;">Start Shopping</a>
                </div>
            <?php else: ?>
                <div class="cart-content">
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
                                <img src="<?= htmlspecialchars($item['primary_image'] ?? 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 120 120%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22120%22 height=%22120%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2260%22%3E🧴%3C/text%3E%3C/svg%3E') ?>" 
                                     alt="<?= htmlspecialchars($item['nama_parfum']) ?>" 
                                     class="item-image">
                                
                                <div class="item-details">
                                    <h3><?= htmlspecialchars($item['nama_parfum']) ?></h3>
                                    <div class="item-volume">Volume: <?= $item['volume_selected'] ?> ml</div>
                                    <div class="item-price"><?= formatRupiah($item['volume_price']) ?></div>
                                    <?php if ($item['jumlah'] > $item['volume_stock']): ?>
                                        <div class="item-stock-warning">
                                            ⚠️ Stock available: <?= $item['volume_stock'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="item-actions">
                                    <form method="POST" action="utils/update_cart.php" class="quantity-control">
                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="action" value="decrease" class="quantity-btn" 
                                                <?= $item['jumlah'] <= 1 ? 'disabled' : '' ?>>−</button>
                                        <input type="number" name="jumlah" value="<?= $item['jumlah'] ?>" 
                                               min="1" max="<?= $item['volume_stock'] ?>" 
                                               class="quantity-input" readonly>
                                        <button type="submit" name="action" value="increase" class="quantity-btn"
                                                <?= $item['jumlah'] >= $item['volume_stock'] ? 'disabled' : '' ?>>+</button>
                                    </form>
                                    
                                    <form method="POST" action="remove_from_cart.php">
                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="remove-btn" 
                                                onclick="return confirm('Remove product from cart?')">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-summary">
                        <h2>Order Summary</h2>
                        
                        <div class="summary-row">
                            <span>Subtotal (<?= count($cart_items) ?> items)</span>
                            <span><?= formatRupiah($subtotal) ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span><?= $shipping > 0 ? formatRupiah($shipping) : 'FREE' ?></span>
                        </div>

                        <?php if ($subtotal < $free_shipping_threshold && $subtotal > 0): ?>
                            <div class="free-shipping-info">
                                Shop Rp <?= formatRupiah($free_shipping_threshold - $subtotal) ?> more for free shipping!
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Total</span>
                            <span><?= formatRupiah($total) ?></span>
                        </div>
                        
                        <a href="checkout.php">
                            <button class="checkout-btn">
                                Proceed to Checkout
                            </button>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
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