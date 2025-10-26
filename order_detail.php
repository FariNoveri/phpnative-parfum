<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    $_SESSION['message'] = 'Silakan login terlebih dahulu';
    $_SESSION['message_type'] = 'error';
    redirect('login.php');
}

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    redirect('orders.php');
}

// Get order details - make sure it belongs to current user
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, getUserId()]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['message'] = 'Pesanan tidak ditemukan';
    $_SESSION['message_type'] = 'error';
    redirect('orders.php');
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.nama_parfum 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// WhatsApp link
$wa_number = "6281234567890";
$wa_message = "Halo, saya ingin menanyakan status pesanan #" . $order['id'] . " atas nama " . $order['nama_customer'] . ". Terima kasih!";
$wa_link = "https://wa.me/{$wa_number}?text=" . urlencode($wa_message);

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
    <title>Detail Pesanan #<?= $order['id'] ?> - Parfum Refill Premium</title>
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
        
        /* Breadcrumb */
        .breadcrumb {
            padding: 30px 0 20px;
            font-size: 13px;
            color: #666;
        }
        
        .breadcrumb a {
            color: #666;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .breadcrumb a:hover {
            color: #c41e3a;
        }
        
        .breadcrumb span {
            margin: 0 10px;
        }
        
        /* Order Section */
        .order-section {
            padding: 20px 0 60px;
        }
        
        .order-header {
            background: #f8f8f8;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .order-title {
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 1px;
            color: #2c2c2c;
        }
        
        .order-id-badge {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .order-status-badge {
            background: #c41e3a;
            color: white;
            padding: 8px 20px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .order-status-badge.pending {
            background: #f39c12;
        }
        
        .order-status-badge.confirmed {
            background: #3498db;
        }
        
        .order-status-badge.processing {
            background: #9b59b6;
        }
        
        .order-status-badge.shipped {
            background: #1abc9c;
        }
        
        .order-status-badge.delivered {
            background: #27ae60;
        }
        
        .order-status-badge.cancelled {
            background: #e74c3c;
        }
        
        .order-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        /* Main Content */
        .order-main {
            background: #fff;
            border: 1px solid #e0e0e0;
            padding: 0;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
            background: #fafafa;
        }
        
        .order-items {
            padding: 30px;
        }
        
        .order-item {
            display: flex;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            background: #f8f8f8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            flex-shrink: 0;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-size: 15px;
            font-weight: 400;
            margin-bottom: 5px;
            color: #2c2c2c;
        }
        
        .item-price-info {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .item-quantity {
            font-size: 14px;
            color: #666;
            margin-right: 30px;
        }
        
        .item-total {
            font-size: 16px;
            font-weight: 600;
            color: #2c2c2c;
            text-align: right;
        }
        
        /* Sidebar */
        .order-sidebar {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .sidebar-card {
            background: #fff;
            border: 1px solid #e0e0e0;
        }
        
        .sidebar-card-content {
            padding: 30px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            color: #666;
        }
        
        .info-value {
            color: #2c2c2c;
            font-weight: 400;
            text-align: right;
        }
        
        .total-row {
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: 600;
        }
        
        .total-amount {
            color: #c41e3a;
        }
        
        .customer-info {
            font-size: 14px;
            line-height: 1.8;
        }
        
        .customer-info p {
            margin-bottom: 10px;
        }
        
        .customer-info strong {
            display: block;
            color: #2c2c2c;
            margin-bottom: 3px;
        }
        
        .address-text {
            color: #666;
            line-height: 1.6;
        }
        
        .notes-text {
            background: #fffbea;
            padding: 15px;
            border-left: 3px solid #f39c12;
            margin-top: 15px;
            font-size: 13px;
            color: #666;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn {
            padding: 15px;
            text-align: center;
            text-decoration: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: block;
        }
        
        .btn-primary {
            background: #2c2c2c;
            color: white;
        }
        
        .btn-primary:hover {
            background: #c41e3a;
        }
        
        .btn-whatsapp {
            background: #25d366;
            color: white;
        }
        
        .btn-whatsapp:hover {
            background: #20b954;
        }
        
        .btn-secondary {
            background: transparent;
            color: #666;
            border: 1px solid #e0e0e0;
        }
        
        .btn-secondary:hover {
            border-color: #2c2c2c;
            color: #2c2c2c;
        }
        
        /* Status Timeline */
        .timeline {
            padding: 30px;
            background: #fafafa;
        }
        
        .timeline-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        
        .timeline-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 40px;
            right: 40px;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .timeline-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        
        .timeline-step.completed .timeline-icon {
            background: #27ae60;
        }
        
        .timeline-step.current .timeline-icon {
            background: #f39c12;
        }
        
        .timeline-label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .timeline-step.completed .timeline-label,
        .timeline-step.current .timeline-label {
            color: #2c2c2c;
            font-weight: 600;
        }
        
        /* Print Styles */
        .print-only {
            display: none;
        }
        
        @media print {
            body {
                background: white;
                font-size: 11pt;
            }
            
            .top-bar,
            header,
            .breadcrumb,
            .action-buttons,
            .timeline {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            .container {
                max-width: none;
                padding: 0;
            }
            
            .print-header {
                text-align: center;
                padding: 20px 0;
                border-bottom: 2px solid #2c2c2c;
                margin-bottom: 30px;
            }
            
            .print-logo {
                font-size: 24px;
                font-weight: 300;
                letter-spacing: 3px;
                text-transform: uppercase;
                margin-bottom: 10px;
            }
            
            .print-company-info {
                font-size: 10pt;
                color: #666;
                line-height: 1.6;
            }
            
            .order-section {
                padding: 0;
            }
            
            .order-header {
                background: white;
                border: 2px solid #2c2c2c;
                padding: 20px;
                margin-bottom: 20px;
                page-break-after: avoid;
            }
            
            .order-title {
                font-size: 20pt;
            }
            
            .order-status-badge {
                border: 2px solid #2c2c2c;
                color: #2c2c2c;
                background: white;
            }
            
            .order-content {
                display: block;
            }
            
            .order-main {
                border: 1px solid #2c2c2c;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            
            .section-title {
                background: white;
                border-bottom: 1px solid #2c2c2c;
            }
            
            .order-items {
                padding: 20px;
            }
            
            .order-item {
                padding: 15px 0;
                page-break-inside: avoid;
            }
            
            .item-image {
                background: #f8f8f8;
                border: 1px solid #e0e0e0;
            }
            
            .order-sidebar {
                margin-top: 0;
            }
            
            .sidebar-card {
                border: 1px solid #2c2c2c;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            
            .sidebar-card-content {
                padding: 20px;
            }
            
            .total-row {
                border-top: 2px solid #2c2c2c;
                font-size: 16pt;
            }
            
            .print-footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #e0e0e0;
                text-align: center;
                font-size: 9pt;
                color: #999;
            }
            
            @page {
                margin: 1.5cm;
                size: A4;
            }
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .order-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                gap: 15px;
            }
            
            .nav-links a {
                font-size: 12px;
            }
            
            .order-header {
                padding: 20px;
            }
            
            .order-title {
                font-size: 20px;
            }
            
            .order-items {
                padding: 20px;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .item-image {
                align-self: center;
            }
            
            .item-total {
                text-align: left;
                margin-top: 10px;
            }
            
            .timeline-steps {
                flex-direction: column;
                gap: 20px;
            }
            
            .timeline-steps::before {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Print Only Header -->
    <div class="print-only">
        <div class="print-header">
            <div class="print-logo">PARFUM REFILL PREMIUM</div>
            <div class="print-company-info">
                <p>Jl. Contoh No. 123, Jakarta Pusat 10110</p>
                <p>Telp: (021) 1234-5678 | Email: cs@parfumrefill.com</p>
                <p>Website: www.parfumrefill.com</p>
            </div>
        </div>
    </div>

    <!-- Top Bar -->
    <div class="top-bar">
        🚚 Gratis Ongkir Min. Rp 500K | 💯 Garansi Puas atau Uang Kembali
    </div>

    <!-- Header -->
    <header>
        <nav class="container">
            <a href="index.php" class="logo">Parfum Refill</a>
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

    <!-- Breadcrumb -->
    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <span>›</span>
            <a href="orders.php">My Orders</a>
            <span>›</span>
            <span>Order #<?= $order['id'] ?></span>
        </div>
    </div>

    <!-- Order Section -->
    <section class="order-section">
        <div class="container">
            <!-- Order Header -->
            <div class="order-header">
                <div>
                    <h1 class="order-title">Order Details</h1>
                    <div class="order-id-badge">
                        Order #<?= $order['id'] ?> • <?= date('d F Y, H:i', strtotime($order['created_at'])) ?>
                    </div>
                </div>
                <div class="order-status-badge <?= $order['status'] ?>">
                    <?= ucfirst($order['status']) ?>
                </div>
            </div>

            <!-- Order Content -->
            <div class="order-content">
                <!-- Main Content -->
                <div>
                    <!-- Status Timeline -->
                    <div class="sidebar-card timeline">
                        <div class="timeline-steps">
                            <?php
                            $statuses = [
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered'
                            ];
                            
                            $status_order = array_keys($statuses);
                            $current_index = array_search($order['status'], $status_order);
                            
                            foreach ($statuses as $key => $label):
                                $step_index = array_search($key, $status_order);
                                $class = '';
                                if ($step_index < $current_index || $order['status'] == 'delivered') {
                                    $class = 'completed';
                                } elseif ($step_index == $current_index) {
                                    $class = 'current';
                                }
                            ?>
                                <div class="timeline-step <?= $class ?>">
                                    <div class="timeline-icon">✓</div>
                                    <div class="timeline-label"><?= $label ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="order-main">
                        <div class="section-title">Order Items</div>
                        <div class="order-items">
                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item">
                                    <div class="item-image">🧴</div>
                                    <div class="item-details">
                                        <div class="item-name"><?= htmlspecialchars($item['nama_parfum']) ?></div>
                                        <div class="item-price-info">
                                            <?= formatRupiah($item['harga']) ?> × <?= $item['jumlah'] ?>
                                        </div>
                                    </div>
                                    <div class="item-total">
                                        <?= formatRupiah($item['harga'] * $item['jumlah']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="order-sidebar">
                    <!-- Order Summary -->
                    <div class="sidebar-card">
                        <div class="section-title">Order Summary</div>
                        <div class="sidebar-card-content">
                            <div class="info-row">
                                <span class="info-label">Subtotal</span>
                                <span class="info-value"><?= formatRupiah($order['total_harga']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Shipping</span>
                                <span class="info-value">Free</span>
                            </div>
                            <div class="total-row">
                                <span>Total</span>
                                <span class="total-amount"><?= formatRupiah($order['total_harga']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="sidebar-card">
                        <div class="section-title">Customer Information</div>
                        <div class="sidebar-card-content customer-info">
                            <p>
                                <strong>Name</strong>
                                <?= htmlspecialchars($order['nama_customer']) ?>
                            </p>
                            <p>
                                <strong>Email</strong>
                                <?= htmlspecialchars($order['email_customer']) ?>
                            </p>
                            <p>
                                <strong>Phone</strong>
                                <?= htmlspecialchars($order['telepon_customer']) ?>
                            </p>
                            <p>
                                <strong>Shipping Address</strong>
                                <span class="address-text"><?= nl2br(htmlspecialchars($order['alamat_customer'])) ?></span>
                            </p>
                            <?php if ($order['notes']): ?>
                                <div class="notes-text">
                                    <strong>Notes:</strong><br>
                                    <?= nl2br(htmlspecialchars($order['notes'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button onclick="window.print()" class="btn btn-primary">
                            🖨️ Print Order
                        </button>
                        <?php if ($order['status'] == 'pending'): ?>
                            <a href="<?= $wa_link ?>" target="_blank" class="btn btn-whatsapp">
                                📱 Confirm Payment
                            </a>
                        <?php else: ?>
                            <a href="<?= $wa_link ?>" target="_blank" class="btn btn-whatsapp">
                                📱 Contact Seller
                            </a>
                        <?php endif; ?>
                        <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Print Footer -->
    <div class="print-only">
        <div class="container">
            <div class="print-footer">
                <p>Thank you for your purchase!</p>
                <p>For questions or concerns, please contact us at cs@parfumrefill.com or call (021) 1234-5678</p>
                <p style="margin-top: 10px;">This is a computer-generated document. No signature required.</p>
            </div>
        </div>
    </div>

    <script>
        // Keyboard shortcut for printing (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>