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
    SELECT oi.*, p.nama_parfum, p.brand 
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?= $order['id'] ?> - Toko Parfum Premium</title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .header h1 {
            color: #333;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-size: 1.1rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .order-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .order-id {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .order-date {
            opacity: 0.9;
            margin-bottom: 1rem;
        }
        
        .order-status {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            display: inline-block;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .order-details {
            padding: 2rem;
        }
        
        .detail-section {
            margin-bottom: 2.5rem;
        }
        
        .detail-section h3 {
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #333;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 1.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 1.5rem;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }
        
        .item-brand {
            color: #666;
            font-size: 0.9rem;
        }
        
        .item-quantity {
            margin: 0 1rem;
            color: #666;
            font-size: 1.1rem;
        }
        
        .item-price {
            font-weight: bold;
            color: #e74c3c;
            font-size: 1.1rem;
        }
        
        .total-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 3px solid #667eea;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .total-final {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 2rem;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            margin: 0 0.5rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-whatsapp {
            background: #25d366;
        }
        
        .btn-whatsapp:hover {
            background: #20b954;
        }
        
        .btn-print {
            background: #f39c12;
        }
        
        .btn-print:hover {
            background: #e67e22;
        }
        
        .status-timeline {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            position: relative;
        }
        
        .status-timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .timeline-item {
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
            margin: 0 auto 0.5rem;
            color: white;
            font-weight: bold;
        }
        
        .timeline-item.active .timeline-icon {
            background: #27ae60;
        }
        
        .timeline-item.current .timeline-icon {
            background: #f39c12;
        }
        
        .timeline-text {
            font-size: 0.9rem;
            color: #666;
        }
        
        .timeline-item.active .timeline-text {
            color: #333;
            font-weight: bold;
        }
        
        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #333;
        }
        
        .print-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        .print-header p {
            color: #666;
            margin: 0.125rem 0;
            font-size: 0.9rem;
        }
        
        /* Print Styles */
        @media print {
            body {
                background-color: white !important;
                color: #000 !important;
                font-size: 10pt !important;
                line-height: 1.2 !important;
                margin: 0.25in !important;
                padding: 0 !important;
            }
            
            .container {
                max-width: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .header {
                display: none !important;
            }
            
            .print-header {
                display: block !important;
            }
            
            .order-card {
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                page-break-inside: avoid !important;
                border: none !important;
            }
            
            .order-header {
                background: #f8f9fa !important;
                color: #000 !important;
                border: 1px solid #333 !important;
                padding: 0.5rem !important;
                margin-bottom: 0.5rem !important;
                page-break-after: avoid !important;
            }
            
            .order-id {
                font-size: 1.2rem !important;
            }
            
            .order-date {
                font-size: 0.9rem !important;
            }
            
            .order-status {
                background: #e9ecef !important;
                color: #000 !important;
                border: 1px solid #333 !important;
                padding: 0.25rem 0.75rem !important;
                font-size: 0.9rem !important;
            }
            
            .order-details {
                padding: 0.5rem !important;
            }
            
            .detail-section {
                margin-bottom: 1rem !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
            }
            
            .detail-section h3 {
                border-bottom: 1px solid #333 !important;
                color: #000 !important;
                font-size: 1rem !important;
                margin-bottom: 0.75rem !important;
                padding-bottom: 0.25rem !important;
            }
            
            .customer-info {
                grid-template-columns: 1fr !important;
                gap: 0.5rem !important;
            }
            
            .info-item {
                margin-bottom: 0.5rem !important;
            }
            
            .info-label {
                font-size: 0.9rem !important;
            }
            
            .info-value {
                font-size: 0.9rem !important;
            }
            
            .order-item {
                display: flex !important;
                align-items: flex-start !important;
                padding: 0.75rem 0 !important;
                border-bottom: 1px solid #eee !important;
                page-break-inside: avoid !important;
                page-break-after: auto !important;
                font-size: 0.9rem !important;
            }
            
            .product-image {
                width: 40px !important;
                height: 40px !important;
                font-size: 1rem !important;
                margin-right: 0.75rem !important;
                flex-shrink: 0 !important;
            }
            
            .item-info {
                flex: 1 !important;
            }
            
            .item-name {
                font-size: 1rem !important;
                margin-bottom: 0.125rem !important;
            }
            
            .item-brand {
                font-size: 0.8rem !important;
            }
            
            .item-quantity {
                margin: 0 0.5rem !important;
                font-size: 1rem !important;
                flex-shrink: 0 !important;
            }
            
            .item-price {
                font-size: 1rem !important;
                flex-shrink: 0 !important;
                white-space: nowrap !important;
            }
            
            .total-section {
                margin-top: 1rem !important;
                padding-top: 1rem !important;
                border-top: 1px solid #333 !important;
                page-break-before: avoid !important;
            }
            
            .total-row {
                font-size: 1rem !important;
                margin-bottom: 0.25rem !important;
                justify-content: space-between !important;
            }
            
            .total-final {
                font-size: 1.2rem !important;
                padding-top: 0.5rem !important;
                border-top: 1px solid #333 !important;
            }
            
            .action-buttons {
                display: none !important;
            }
            
            .back-link {
                display: none !important;
            }
            
            .status-timeline {
                display: none !important;
            }
            
            /* Ensure single page */
            @page {
                size: A4;
                margin: 0.5cm;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .product-image {
                align-self: center;
                margin-bottom: 1rem;
                margin-right: 0;
            }
            
            .item-quantity,
            .item-price {
                margin-left: 0;
                margin-top: 0.5rem;
            }
            
            .customer-info {
                grid-template-columns: 1fr;
            }
            
            .status-timeline {
                flex-direction: column;
                gap: 1rem;
            }
            
            .status-timeline::before {
                display: none;
            }
            
            .btn {
                display: block;
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print Header (hanya muncul saat print) -->
        <div class="print-header">
            <h1>TOKO PARFUM PREMIUM</h1>
            <p>Jl. Contoh No. 123, Kota ABC</p>
            <p>Telp: (021) 12345678 | Email: info@tokoperfume.com</p>
            <p>Website: www.tokoperfume.com</p>
        </div>
        
        <div class="header">
            <a href="orders.php" class="back-link">← Kembali ke Daftar Pesanan</a>
            <h1>Detail Pesanan</h1>
        </div>

        <div class="order-card">
            <div class="order-header">
                <div class="order-id">Pesanan #<?= $order['id'] ?></div>
                <div class="order-date">Dibuat pada: <?= date('d F Y, H:i', strtotime($order['created_at'])) ?></div>
                <div class="order-status">Status: <?= ucfirst($order['status']) ?></div>
            </div>

            <div class="order-details">
                <!-- Status Timeline -->
                <div class="detail-section">
                    <h3>📋 Status Pesanan</h3>
                    <div class="status-timeline">
                        <div class="timeline-item <?= in_array($order['status'], ['pending', 'confirmed', 'processing', 'shipped', 'delivered']) ? 'active' : '' ?>">
                            <div class="timeline-icon">1</div>
                            <div class="timeline-text">Pending</div>
                        </div>
                        <div class="timeline-item <?= in_array($order['status'], ['confirmed', 'processing', 'shipped', 'delivered']) ? 'active' : ($order['status'] == 'pending' ? 'current' : '') ?>">
                            <div class="timeline-icon">2</div>
                            <div class="timeline-text">Confirmed</div>
                        </div>
                        <div class="timeline-item <?= in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'active' : ($order['status'] == 'confirmed' ? 'current' : '') ?>">
                            <div class="timeline-icon">3</div>
                            <div class="timeline-text">Processing</div>
                        </div>
                        <div class="timeline-item <?= in_array($order['status'], ['shipped', 'delivered']) ? 'active' : ($order['status'] == 'processing' ? 'current' : '') ?>">
                            <div class="timeline-icon">4</div>
                            <div class="timeline-text">Shipped</div>
                        </div>
                        <div class="timeline-item <?= $order['status'] == 'delivered' ? 'active' : ($order['status'] == 'shipped' ? 'current' : '') ?>">
                            <div class="timeline-icon">5</div>
                            <div class="timeline-text">Delivered</div>
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="detail-section">
                    <h3>👤 Data Pembeli</h3>
                    <div class="customer-info">
                        <div class="info-item">
                            <div class="info-label">Nama:</div>
                            <div class="info-value"><?= htmlspecialchars($order['nama_customer']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?= htmlspecialchars($order['email_customer']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Telepon:</div>
                            <div class="info-value"><?= htmlspecialchars($order['telepon_customer']) ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Alamat Pengiriman:</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($order['alamat_customer'])) ?></div>
                    </div>
                    <?php if ($order['notes']): ?>
                        <div class="info-item">
                            <div class="info-label">Catatan:</div>
                            <div class="info-value"><?= nl2br(htmlspecialchars($order['notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Order Items -->
                <div class="detail-section">
                    <h3>🛍️ Detail Produk</h3>
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <div class="product-image">🧴</div>
                            <div class="item-info">
                                <div class="item-name"><?= htmlspecialchars($item['nama_parfum']) ?></div>
                                <div class="item-brand"><?= htmlspecialchars($item['brand']) ?></div>
                                <div style="font-size: 0.9rem; color: #666; margin-top: 0.25rem;">
                                    <?= formatRupiah($item['harga']) ?> per item
                                </div>
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
                            <span>Total Pembayaran:</span>
                            <span><?= formatRupiah($order['total_harga']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-print">
                🖨️ Print Pesanan
            </button>
            <?php if ($order['status'] == 'pending'): ?>
                <a href="<?= $wa_link ?>" target="_blank" class="btn btn-whatsapp">
                    📱 Konfirmasi Pembayaran
                </a>
            <?php else: ?>
                <a href="<?= $wa_link ?>" target="_blank" class="btn btn-whatsapp">
                    📱 Hubungi Penjual
                </a>
            <?php endif; ?>
            <a href="orders.php" class="btn">Kembali ke Daftar Pesanan</a>
        </div>
    </div>

    <script>
        // Print function with better formatting
        function printOrder() {
            // Optional: Add print timestamp
            const printTime = new Date().toLocaleString('id-ID');
            const originalTitle = document.title;
            document.title = `Pesanan #<?= $order['id'] ?> - Dicetak ${printTime}`;
            
            window.print();
            
            // Restore original title after print
            setTimeout(() => {
                document.title = originalTitle;
            }, 1000);
        }
        
        // Add keyboard shortcut for printing (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printOrder();
            }
        });
    </script>
</body>
</html>