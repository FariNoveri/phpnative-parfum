<?php
require_once 'config/database.php';

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    redirect('index.php');
}

// Get order details
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['message'] = 'Pesanan tidak ditemukan';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
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

// WhatsApp message
$wa_number = "6281234567890"; // Ganti dengan nomor WA toko
$wa_message = "Halo, saya ingin konfirmasi pembayaran untuk:\n\n";
$wa_message .= "*ID Pesanan:* {$order['id']}\n";
$wa_message .= "*Nama:* {$order['nama_customer']}\n";
$wa_message .= "*Total:* " . formatRupiah($order['total_harga']) . "\n\n";
$wa_message .= "*Detail Pesanan:*\n";

foreach ($order_items as $item) {
    $wa_message .= "• {$item['nama_parfum']} ({$item['brand']}) x{$item['jumlah']} = " . formatRupiah($item['harga'] * $item['jumlah']) . "\n";
}

$wa_message .= "\nSaya akan segera mengirim bukti pembayaran. Terima kasih!";
$wa_link = "https://wa.me/{$wa_number}?text=" . urlencode($wa_message);

// Status display logic
$payment_status = $order['status_pembayaran'] ?? 'pending';
$status_display = ucfirst($payment_status);
$status_color = match($payment_status) {
    'settlement' => '#27ae60',
    'pending' => '#f39c12',
    'failed', 'cancelled' => '#e74c3c',
    default => '#666'
};
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - Toko Parfum Premium</title>
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
            color: #27ae60;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .header .checkmark {
            font-size: 4rem;
            color: #27ae60;
            margin-bottom: 1rem;
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
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .order-status {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            margin-top: 1rem;
        }
        
        .order-details {
            padding: 2rem;
        }
        
        .detail-section {
            margin-bottom: 2rem;
        }
        
        .detail-section h3 {
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .item-brand {
            color: #666;
            font-size: 0.9rem;
        }
        
        .item-quantity {
            margin: 0 1rem;
            color: #666;
        }
        
        .item-price {
            font-weight: bold;
            color: #e74c3c;
        }
        
        .total-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #667eea;
            text-align: right;
        }
        
        .total-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .payment-instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .payment-instructions h3 {
            color: #856404;
            margin-bottom: 1rem;
        }
        
        .bank-info {
            background: white;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #667eea;
        }
        
        .whatsapp-section {
            background: #e8f5e8;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            margin: 2rem 0;
        }
        
        .whatsapp-btn {
            background: #25d366;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1.2rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
        }
        
        .whatsapp-btn:hover {
            background: #20b954;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
        }
        
        .back-btn {
            background: #667eea;
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
            margin-top: 2rem;
        }
        
        .back-btn:hover {
            background: #5a67d8;
        }
        
        .warning {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            margin: 1rem 0;
            text-align: center;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            color: white;
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
            
            .item-quantity,
            .item-price {
                margin-left: 0;
                margin-top: 0.5rem;
            }
            
            .total-section {
                text-align: center;
            }
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin: 0 1rem;
        }
        
        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        
        .step-text {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="checkmark">✅</div>
            <h1>Pesanan Berhasil Dibuat!</h1>
            <p>Terima kasih atas pesanan Anda. Silakan ikuti langkah berikut untuk menyelesaikan pembayaran.</p>
        </div>

        <div class="order-card">
            <div class="order-header">
                <div class="order-id">ID Pesanan: #<?= $order['id'] ?></div>
                <div class="order-status">
                    <span class="status-badge" style="background-color: <?= $status_color ?>;">
                        <?= $status_display ?> Pembayaran
                    </span>
                </div>
            </div>

            <div class="order-details">
                <div class="detail-section">
                    <h3>👤 Data Pembeli</h3>
                    <div class="customer-info">
                        <div class="info-item">
                            <span class="info-label">Nama:</span>
                            <?= htmlspecialchars($order['nama_customer']) ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <?= htmlspecialchars($order['email_customer']) ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Telepon:</span>
                            <?= htmlspecialchars($order['telepon_customer']) ?>
                        </div>
                    </div>
                    <div class="info-item" style="margin-top: 1rem;">
                        <span class="info-label">Alamat:</span><br>
                        <?= nl2br(htmlspecialchars($order['alamat_customer'])) ?>
                    </div>
                    <?php if ($order['notes']): ?>
                        <div class="info-item" style="margin-top: 1rem;">
                            <span class="info-label">Catatan:</span><br>
                            <?= nl2br(htmlspecialchars($order['notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="detail-section">
                    <h3>🛍️ Detail Pesanan</h3>
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <div class="item-info">
                                <div class="item-name"><?= htmlspecialchars($item['nama_parfum']) ?></div>
                                <div class="item-brand"><?= htmlspecialchars($item['brand']) ?></div>
                            </div>
                            <div class="item-quantity">×<?= $item['jumlah'] ?></div>
                            <div class="item-price"><?= formatRupiah($item['harga'] * $item['jumlah']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="total-section">
                        <div class="total-amount">Total: <?= formatRupiah($order['total_harga']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="step-indicator">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-text">Pesanan Dibuat</div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-text">Transfer Pembayaran</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-text">Konfirmasi via WhatsApp</div>
            </div>
        </div>

        <div class="payment-instructions">
            <h3>💳 Instruksi Pembayaran</h3>
            <p><strong>Silakan transfer ke salah satu rekening berikut:</strong></p>
            
            <div class="bank-info">
                <strong>Bank BCA</strong><br>
                No. Rekening: <strong>1234567890</strong><br>
                a.n. <strong>Toko Parfum Premium</strong>
            </div>
            
            <div class="bank-info">
                <strong>Bank Mandiri</strong><br>
                No. Rekening: <strong>0987654321</strong><br>
                a.n. <strong>Toko Parfum Premium</strong>
            </div>
            
            <div class="warning">
                ⚠️ <strong>PENTING:</strong> Pesanan akan diproses setelah pembayaran dikonfirmasi. 
                Status pembayaran saat ini: <strong><?= $status_display ?></strong>
                <?php if ($payment_status === 'settlement'): ?>
                    <br>Pembayaran berhasil! Pesanan sedang diproses.
                <?php elseif ($payment_status === 'pending'): ?>
                    <br>Silakan segera lakukan pembayaran dan konfirmasi.
                <?php elseif (in_array($payment_status, ['failed', 'cancelled'])): ?>
                    <br>Pembayaran gagal. Silakan coba lagi atau hubungi customer service.
                <?php endif; ?>
            </div>
        </div>

        <?php if ($payment_status !== 'settlement'): ?>
        <div class="whatsapp-section">
            <h3>📱 Langkah Terakhir</h3>
            <p>Setelah melakukan transfer, klik tombol di bawah untuk konfirmasi pembayaran via WhatsApp:</p>
            <br>
            <a href="<?= $wa_link ?>" target="_blank" class="whatsapp-btn">
                📱 Konfirmasi via WhatsApp
            </a>
            <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                *Jangan lupa sertakan screenshot bukti transfer
            </p>
        </div>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="index.php" class="back-btn">Kembali ke Beranda</a>
            <?php if (isLoggedIn()): ?>
                <a href="orders.php" class="back-btn">Lihat Semua Pesanan</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>