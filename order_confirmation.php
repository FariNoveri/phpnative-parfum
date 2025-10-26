<?php
require_once 'config/database.php';

$order_id = (int)($_GET['id'] ?? 0);

// Debug log
error_log('Order Confirmation - Requested ID: ' . $order_id);

if ($order_id <= 0) {
    error_log('Order Confirmation - Invalid ID, redirecting to index');
    $_SESSION['message'] = 'ID pesanan tidak valid';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

// Get order details
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    error_log('Order Confirmation - Order not found: ' . $order_id);
    
    // Check for pending order in session
    if (isset($_SESSION['pending_order'])) {
        error_log('Order Confirmation - Found pending order in session');
        $_SESSION['message'] = 'Pesanan belum selesai diproses. Silakan coba lagi atau hubungi admin.';
    } else {
        error_log('Order Confirmation - No pending order in session');
        $_SESSION['message'] = 'Pesanan tidak ditemukan';
    }
    
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

error_log('Order Confirmation - Order found: ' . $order_id . ', Status: ' . $order['status_pembayaran']);

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.nama_parfum 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

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

// WhatsApp message
$wa_number = "6281234567890"; // Replace with store's WhatsApp number
$wa_message = "Halo Admin Parfum Premium,\n\nSaya ingin konfirmasi pembayaran untuk pesanan berikut:\n\n";
$wa_message .= "*ID Pesanan:* {$order['id']}\n";
$wa_message .= "*Nama Customer:* {$order['nama_customer']}\n";
$wa_message .= "*Email:* {$order['email_customer']}\n";
$wa_message .= "*Telepon:* {$order['telepon_customer']}\n";
$wa_message .= "*Total Bayar:* " . formatRupiah($order['total_harga']) . "\n";
$wa_message .= "*Tanggal Transaksi:* " . date('d M Y H:i', strtotime($order['created_at'])) . "\n\n";
$wa_message .= "*Detail Items:*\n";
foreach ($order_items as $item) {
    $wa_message .= "• {$item['nama_parfum']} x {$item['jumlah']} = " . formatRupiah($item['harga'] * $item['jumlah']) . "\n";
}
$wa_message .= "\n*Alamat Pengiriman:*\n{$order['alamat_customer']}\n\n";
$wa_message .= "Terlampir screenshot halaman konfirmasi pesanan sebagai bukti. Mohon proses pesanan saya secepatnya. Terima kasih!";

$wa_link = "https://wa.me/{$wa_number}?text=" . urlencode($wa_message);

// Status display logic
$payment_status = $order['status_pembayaran'] ?? 'pending';
$status_display = ucfirst($payment_status);
$status_color = match($payment_status) {
    'settlement', 'paid' => '#27ae60',
    'pending' => '#f39c12',
    'failed', 'cancelled' => '#e74c3c',
    default => '#666'
};

// Fonnte integration for WhatsApp notification
$token = 'ChsQDEYhJLq1N8CnQEbS'; // Replace with your Fonnte API token

if (!isset($_SESSION['notif_sent_' . $order_id])) {
    $target = $order['telepon_customer'];
    $message = "Terima kasih telah memesan di Toko Parfum Premium.\nPesanan Anda #" . $order['id'] . " dengan total " . formatRupiah($order['total_harga']) . " sedang diproses.\nDetail pesanan:\n";

    foreach ($order_items as $item) {
        $message .= "- " . htmlspecialchars($item['nama_parfum']) . " x" . $item['jumlah'] . " = " . formatRupiah($item['harga'] * $item['jumlah']) . "\n";
    }

    $message .= "\nAlamat: " . htmlspecialchars($order['alamat_customer']) . "\n\nKami akan segera memproses pesanan Anda.";

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'target' => $target,
            'message' => $message,
            'countryCode' => '62',
        ),
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $token
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $_SESSION['notif_sent_' . $order_id] = true;
}

// Store details for print
$store_name = 'Parfum Refill Premium';
$store_address = 'Bandar Lampung, Lampung, Indonesia';
$store_phone = '+62 812-3456-7890';
$store_email = 'cs@parfumrefill.com';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - Parfum Refill Premium</title>
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
        
        /* Main Content */
        .main-content {
            padding: 60px 0;
        }
        
        h1 {
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 1px;
            color: #2c2c2c;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .success-icon {
            font-size: 64px;
            color: #27ae60;
            text-align: center;
            margin-bottom: 20px;
        }
        
        /* Alert */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-left: 3px solid;
            font-size: 14px;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #22c55e;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #f59e0b;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #ef4444;
        }
        
        /* Order Card */
        .order-card {
            background: #fff;
            border-radius: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .order-header {
            background: #2c2c2c;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .order-id {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 1px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .order-date {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
            background: rgba(255,255,255,0.2);
        }
        
        .order-details {
            padding: 30px;
        }
        
        .detail-section {
            margin-bottom: 30px;
        }
        
        .detail-section h3 {
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            color: #2c2c2c;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 8px;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: #fafafa;
            padding: 12px;
            border-radius: 0;
            border-left: 3px solid #c41e3a;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #2c2c2c;
            font-size: 14px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
            margin-bottom: 3px;
            color: #2c2c2c;
        }
        
        .item-quantity {
            color: #999;
            margin: 0 10px;
        }
        
        .item-price {
            font-weight: 600;
            color: #c41e3a;
        }
        
        .total-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #c41e3a;
            text-align: right;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .total-final {
            font-size: 20px;
            font-weight: 600;
            color: #2c2c2c;
        }
        
        /* Payment Instructions */
        .payment-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 0;
            padding: 20px;
            margin: 30px 0;
        }
        
        .payment-section h3 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .bank-info {
            background: white;
            padding: 15px;
            border-radius: 0;
            margin: 15px 0;
            border: 1px solid #e0e0e0;
            font-size: 13px;
        }
        
        /* WhatsApp Section */
        .whatsapp-section {
            background: #f0fdf4;
            border: 1px solid #22c55e;
            border-radius: 0;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        
        .whatsapp-btn {
            background: #25d366;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .whatsapp-btn:hover {
            background: #20b954;
            transform: translateY(-2px);
        }
        
        /* Step Indicator */
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 40px 0;
            gap: 20px;
        }
        
        .step {
            display: flex;
            align-items: center;
            text-align: center;
        }
        
        .step-number {
            background: #e0e0e0;
            color: #666;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 8px;
            font-size: 12px;
        }
        
        .step.active .step-number {
            background: #c41e3a;
            color: white;
        }
        
        .step-text {
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .step.active .step-text {
            color: #c41e3a;
        }
        
        /* Buttons */
        .btn {
            background: #c41e3a;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            margin: 5px;
        }
        
        .btn:hover {
            background: #a01628;
        }
        
        .print-section {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 0;
        }
        
        .print-btn {
            background: #2c2c2c;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .print-btn:hover {
            background: #c41e3a;
        }
        
        .ss-instruction {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 0;
            margin-top: 15px;
            font-size: 13px;
            border-left: 3px solid #22c55e;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                gap: 15px;
            }
            
            .nav-links a {
                font-size: 12px;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .item-quantity,
            .item-price {
                margin-left: 0;
                margin-top: 5px;
            }
            
            .customer-info {
                grid-template-columns: 1fr;
            }
            
            .step-indicator {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        /* Print Styles */
        @media print {
            .no-print, header, .top-bar, footer, .step-indicator, .whatsapp-section, .print-section {
                display: none !important;
            }
            
            body {
                background: white !important;
                margin: 0;
                padding: 0;
                font-size: 10pt;
                line-height: 1.3;
                color: #000;
            }
            
            .container {
                max-width: 100% !important;
                padding: 0.5cm !important;
                margin: 0 !important;
            }
            
            .main-content {
                padding: 0 !important;
            }
            
            h1 {
                font-size: 14pt !important;
                text-align: center !important;
                margin-bottom: 10pt !important;
            }
            
            .success-icon {
                font-size: 32pt !important;
                text-align: center !important;
                margin-bottom: 10pt !important;
            }
            
            .order-card {
                box-shadow: none !important;
                border: 1pt solid #000 !important;
                page-break-inside: avoid;
            }
            
            .order-header {
                background: #2c2c2c !important;
                color: white !important;
                padding: 0.5cm !important;
            }
            
            .order-id {
                font-size: 16pt !important;
                margin-bottom: 2pt !important;
            }
            
            .order-date {
                font-size: 11pt !important;
            }
            
            .status-badge {
                background: #c41e3a !important;
                color: white !important;
                padding: 4pt 8pt !important;
                font-size: 10pt !important;
                margin-top: 5pt !important;
            }
            
            .order-details {
                padding: 0.5cm !important;
            }
            
            .detail-section h3 {
                font-size: 12pt !important;
                border-bottom: 1pt solid #000 !important;
                padding-bottom: 5pt !important;
                margin-bottom: 10pt !important;
            }
            
            .customer-info {
                grid-template-columns: 1fr !important;
                gap: 5pt !important;
            }
            
            .info-item {
                background: #f8f8f8 !important;
                padding: 8pt !important;
                border-left: 2pt solid #c41e3a !important;
                margin-bottom: 5pt !important;
            }
            
            .info-label {
                font-size: 10pt !important;
                margin-bottom: 2pt !important;
            }
            
            .info-value {
                font-size: 11pt !important;
            }
            
            .order-item {
                padding: 5pt 0 !important;
                border-bottom: 0.5pt solid #000 !important;
                font-size: 11pt !important;
            }
            
            .order-item:last-child {
                border-bottom: none !important;
            }
            
            .item-name {
                font-weight: bold !important;
            }
            
            .item-price {
                font-weight: bold !important;
            }
            
            .total-section {
                margin-top: 10pt !important;
                padding-top: 10pt !important;
                border-top: 2pt solid #c41e3a !important;
            }
            
            .total-final {
                font-size: 16pt !important;
            }
            
            .payment-section {
                display: block !important;
                background: white !important;
                border: 1pt solid #000 !important;
                padding: 0.5cm !important;
                margin: 10pt 0 !important;
                page-break-inside: avoid;
            }
            
            .payment-section h3 {
                font-size: 12pt !important;
                border-bottom: 1pt solid #000 !important;
                padding-bottom: 5pt !important;
                margin-bottom: 10pt !important;
            }
            
            .bank-info {
                border: 1pt solid #000 !important;
                padding: 8pt !important;
                margin: 8pt 0 !important;
            }
            
            /* Store Header for Print */
            .store-header {
                text-align: center;
                margin-bottom: 20pt;
                padding-bottom: 10pt;
                border-bottom: 2pt solid #c41e3a;
            }
            
            .store-name {
                font-size: 18pt;
                font-weight: bold;
                margin-bottom: 2pt;
            }
            
            .store-details {
                font-size: 10pt;
                margin-bottom: 5pt;
            }
            
            .transaction-proof {
                text-align: center;
                margin: 10pt 0;
                font-size: 12pt;
                font-weight: bold;
                color: #c41e3a;
            }
            
            @page {
                margin: 0.5cm;
                size: A4;
            }
        }
    </style>
    <script>
        function printPage() {
            window.print();
        }
        
        // Step indicator logic
        document.addEventListener('DOMContentLoaded', function() {
            const steps = document.querySelectorAll('.step');
            steps.forEach((step, index) => {
                if (index < 2) { // First two steps completed
                    step.classList.add('active');
                }
            });
        });
    </script>
</head>
<body>
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

    <main class="main-content">
        <div class="container">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="success-icon">✅</div>
            <h1>Konfirmasi Pesanan</h1>
            <p style="text-align: center; color: #666; margin-bottom: 30px;">Terima kasih atas pesanan Anda. Silakan ikuti langkah berikut untuk menyelesaikan pembayaran.</p>

            <div class="order-card">
                <div class="order-header">
                    <div class="order-id">ID Pesanan: #<?= $order['id'] ?></div>
                    <div class="order-date">Tanggal Transaksi: <?= date('d M Y H:i', strtotime($order['created_at'])) ?></div>
                    <div class="status-badge" style="background-color: <?= $status_color ?>;">
                        <?= $status_display ?> Pembayaran
                    </div>
                </div>

                <div class="order-details">
                    <div class="detail-section">
                        <h3>👤 Data Pembeli</h3>
                        <div class="customer-info">
                            <div class="info-item">
                                <span class="info-label">Nama Lengkap</span>
                                <span class="info-value"><?= htmlspecialchars($order['nama_customer']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?= htmlspecialchars($order['email_customer']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Telepon</span>
                                <span class="info-value"><?= htmlspecialchars($order['telepon_customer']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Alamat Pengiriman</span>
                                <span class="info-value"><?= nl2br(htmlspecialchars($order['alamat_customer'])) ?></span>
                            </div>
                            <?php if ($order['notes']): ?>
                                <div class="info-item">
                                    <span class="info-label">Catatan Pesanan</span>
                                    <span class="info-value"><?= nl2br(htmlspecialchars($order['notes'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3>🛍️ Detail Pesanan</h3>
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item">
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($item['nama_parfum']) ?></div>
                                </div>
                                <div class="item-quantity">×<?= $item['jumlah'] ?></div>
                                <div class="item-price"><?= formatRupiah($item['harga'] * $item['jumlah']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="total-section">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span><?= formatRupiah($order['total_harga']) ?></span>
                            </div>
                            <div class="total-row">
                                <span>Ongkos Kirim:</span>
                                <span>Gratis</span>
                            </div>
                            <div class="total-row total-final">
                                <span>Total:</span>
                                <span><?= formatRupiah($order['total_harga']) ?></span>
                            </div>
                            <?php if ($order['midtrans_transaction_id']): ?>
                                <div class="total-row">
                                    <span>ID Transaksi Midtrans:</span>
                                    <span><?= htmlspecialchars($order['midtrans_transaction_id']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="step-indicator">
                <div class="step active">
                    <div class="step-number">1</div>
                    <div class="step-text">Pesanan Dibuat</div>
                </div>
                <div class="step active">
                    <div class="step-number">2</div>
                    <div class="step-text">Pembayaran</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-text">Konfirmasi</div>
                </div>
            </div>

            <?php if ($payment_status === 'settlement' || $payment_status === 'paid'): ?>
                <div class="alert alert-success">
                    ✅ <strong>Pembayaran Berhasil!</strong><br>
                    Pesanan Anda sedang diproses. Kami akan mengirimkan konfirmasi melalui email dan WhatsApp.
                </div>
            <?php elseif ($payment_status === 'pending'): ?>
                <div class="payment-section">
                    <h3>💳 Instruksi Pembayaran</h3>
                    <p><strong>Status:</strong> Menunggu Konfirmasi Pembayaran</p>
                    <p>Silakan lakukan pembayaran sebesar <?= formatRupiah($order['total_harga']) ?> ke rekening berikut:</p>
                    <div class="bank-info">
                        <strong>BCA</strong><br>
                        a/n: Parfum Refill Premium<br>
                        No. Rekening: 1234-5678-90<br>
                        Cabang: Bandar Lampung
                    </div>
                    <p><em>Transfer dari ATM/Mobile Banking/Internet Banking, lalu konfirmasi via WhatsApp.</em></p>
                </div>
                
                <div class="whatsapp-section">
                    <h3>📱 Konfirmasi via WhatsApp</h3>
                    <p>Kirim bukti transfer + screenshot halaman ini ke admin kami:</p>
                    <a href="<?= $wa_link ?>" target="_blank" class="whatsapp-btn">
                        📱 Kirim Konfirmasi
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-error">
                    ⚠️ <strong>Pembayaran Gagal</strong><br>
                    Status: <?= $status_display ?><br>
                    Silakan coba lagi atau hubungi customer service.
                </div>
            <?php endif; ?>

            <div class="ss-instruction no-print">
                <h3>📸 Bukti Pembayaran: Screenshot & Print</h3>
                <p><strong>Untuk Konfirmasi Cepat:</strong></p>
                <ol style="text-align: left; font-size: 13px;">
                    <li>Print halaman ini sebagai PDF (gunakan tombol di bawah).</li>
                    <li>Screenshot PDF/halaman sebagai bukti statement transaksi.</li>
                    <li>Kirim via WhatsApp beserta bukti transfer.</li>
                </ol>
                <p><em>Halaman print berisi detail lengkap sebagai bukti resmi transaksi.</em></p>
            </div>

            <div class="print-section no-print">
                <button onclick="printPage()" class="print-btn">🖨️ Print sebagai PDF (Bukti Transaksi)</button>
                <p style="font-size: 12px; color: #666; margin-top: 10px;">Simpan sebagai PDF untuk arsip & konfirmasi pembayaran.</p>
            </div>

            <div style="text-align: center; margin: 40px 0;" class="no-print">
                <a href="index.php" class="btn">Kembali ke Beranda</a>
                <?php if (isLoggedIn()): ?>
                    <a href="orders.php" class="btn">Lihat Pesanan Lain</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Print-only Store Header -->
    <div class="store-header" style="display: none;">
        <div class="store-name"><?= $store_name ?></div>
        <div class="store-details"><?= $store_address ?> | <?= $store_phone ?> | <?= $store_email ?></div>
        <div class="transaction-proof">BUKTI TRANSAKSI / STATEMENT PEMBELIAN</div>
    </div>

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
                    <a href="tel:<?= $store_phone ?>"><?= $store_phone ?></a>
                    <a href="mailto:<?= $store_email ?>"><?= $store_email ?></a>
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
                <p>&copy; 2025 Parfum Refill Premium. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>