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

// WhatsApp message
$wa_number = "6281234567890"; // Replace with store's WhatsApp number
$wa_message = "Halo Admin Parfum Premium,\n\nSaya ingin konfirmasi pembayaran untuk pesanan berikut:\n\n";
$wa_message .= "*ID Pesanan:* {$order['id']}\n";
$wa_message .= "*Nama Customer:* {$order['nama_customer']}\n";
$wa_message .= "*Email:* {$order['email_customer']}\n";
$wa_message .= "*Telepon:* {$order['telepon_customer']}\n";
$wa_message .= "*Total Bayar:* " . formatRupiah($order['total_harga']) . "\n\n";
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
            line-height: 1.4;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .header h1 {
            color: #27ae60;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .header .checkmark {
            font-size: 3rem;
            color: #27ae60;
            margin-bottom: 0.5rem;
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            text-align: center;
        }
        
        .order-id {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.3rem;
        }
        
        .order-status {
            background: rgba(255,255,255,0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        .order-details {
            padding: 1rem;
        }
        
        .detail-section {
            margin-bottom: 1rem;
        }
        
        .detail-section h3 {
            color: #333;
            margin-bottom: 0.5rem;
            padding-bottom: 0.3rem;
            border-bottom: 1px solid #667eea;
            font-size: 1.1rem;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
        }
        
        .info-item {
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 0.2rem;
        }
        
        .item-quantity {
            margin: 0 0.5rem;
            color: #666;
        }
        
        .item-price {
            font-weight: bold;
            color: #e74c3c;
        }
        
        .total-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #667eea;
            text-align: right;
        }
        
        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .payment-instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .payment-instructions h3 {
            color: #856404;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .bank-info {
            background: white;
            padding: 0.8rem;
            border-radius: 5px;
            margin: 0.8rem 0;
            border-left: 3px solid #667eea;
            font-size: 0.9rem;
        }
        
        .whatsapp-section {
            background: #e8f5e8;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            margin: 1rem 0;
        }
        
        .whatsapp-btn {
            background: #25d366;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(37, 211, 102, 0.2);
        }
        
        .whatsapp-btn:hover {
            background: #20b954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
        }
        
        .back-btn {
            background: #667eea;
            color: white;
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .back-btn:hover {
            background: #5a67d8;
        }
        
        .warning {
            background: #f8d7da;
            color: #721c24;
            padding: 0.8rem;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            margin: 0.8rem 0;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 0.8rem;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            margin: 0.8rem 0;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-weight: bold;
            color: white;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0.5rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .item-quantity,
            .item-price {
                margin-left: 0;
                margin-top: 0.3rem;
            }
            
            .total-section {
                text-align: center;
            }
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 1rem 0;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin: 0 0.5rem;
        }
        
        .step-number {
            background: #667eea;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.4rem;
            font-size: 0.9rem;
        }
        
        .step-text {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .debug-info {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 0.8rem;
            border-radius: 5px;
            margin: 0.8rem 0;
            font-family: monospace;
            font-size: 0.8rem;
            display: none;
        }
        
        .print-section {
            margin: 1rem 0;
            text-align: center;
        }
        
        .print-btn {
            background: #3498db;
            color: white;
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s;
            margin-right: 0.5rem;
        }
        
        .print-btn:hover {
            background: #2980b9;
        }
        
        .ss-instruction {
            background: #e8f5e8;
            padding: 0.8rem;
            border-radius: 6px;
            margin-top: 0.8rem;
            font-size: 0.85rem;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
                margin: 0;
                padding: 0;
                font-size: 10pt;
                line-height: 1.2;
            }
            .container {
                max-width: 100%;
                padding: 0.5cm;
                margin: 0;
            }
            .order-card {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
            .header {
                margin-bottom: 0.5cm;
            }
            .header h1 {
                font-size: 14pt;
            }
            .header .checkmark {
                font-size: 24pt;
            }
            .order-header {
                padding: 0.5cm;
            }
            .order-id {
                font-size: 12pt;
            }
            .order-status {
                padding: 0.3cm 0.6cm;
            }
            .order-details {
                padding: 0.5cm;
            }
            .detail-section {
                margin-bottom: 0.5cm;
            }
            .detail-section h3 {
                font-size: 12pt;
                padding-bottom: 0.2cm;
            }
            .customer-info {
                grid-template-columns: 1fr;
                gap: 0.3cm;
            }
            .info-item {
                font-size: 10pt;
            }
            .order-item {
                padding: 0.3cm 0;
                font-size: 10pt;
            }
            .total-section {
                margin-top: 0.5cm;
                padding-top: 0.5cm;
            }
            .total-amount {
                font-size: 12pt;
            }
            .payment-instructions, .whatsapp-section, .step-indicator, .ss-instruction, .print-section {
                display: none !important;
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
    </script>
</head>
<body>
    <div class="container">
        <!-- Debug Info (hidden by default) -->
        <div class="debug-info" style="display: none;">
            <strong>Debug Info:</strong><br>
            Order ID: <?= $order_id ?><br>
            Payment Status: <?= $payment_status ?><br>
            Transaction ID: <?= $order['midtrans_transaction_id'] ?? 'N/A' ?><br>
        </div>

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
                    <div class="info-item" style="margin-top: 0.5rem;">
                        <span class="info-label">Alamat:</span><br>
                        <?= nl2br(htmlspecialchars($order['alamat_customer'])) ?>
                    </div>
                    <?php if ($order['notes']): ?>
                        <div class="info-item" style="margin-top: 0.5rem;">
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
                <div class="step-text">Pembayaran</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-text">Konfirmasi</div>
            </div>
        </div>

        <?php if ($payment_status === 'settlement' || $payment_status === 'paid'): ?>
            <div class="success-message">
                ✅ <strong>Pembayaran Berhasil!</strong><br>
                Pesanan Anda sedang diproses. Kami akan mengirimkan konfirmasi melalui email.
            </div>
        <?php elseif ($payment_status === 'pending'): ?>
            <div class="payment-instructions">
                <h3>💳 Menunggu Pembayaran</h3>
                <p><strong>Status:</strong> Pembayaran sedang diproses</p>
                <p>Jika Anda belum menyelesaikan pembayaran, silakan cek email atau lakukan pembayaran sekarang.</p>
            </div>
            
            <div class="whatsapp-section">
                <h3>📱 Konfirmasi Pembayaran via WhatsApp</h3>
                <p>Klik tombol di bawah untuk mengirim konfirmasi otomatis ke admin kami:</p>
                <br>
                <a href="<?= $wa_link ?>" target="_blank" class="whatsapp-btn">
                    📱 Kirim Konfirmasi via WhatsApp
                </a>
                <p style="margin-top: 0.5rem; font-size: 0.85rem; color: #666;">
                    *Pastikan Anda melampirkan bukti transfer saat chat.
                </p>
            </div>
        <?php else: ?>
            <div class="warning">
                ⚠️ <strong>Pembayaran Gagal</strong><br>
                Status: <?= $status_display ?><br>
                Silakan coba lagi atau hubungi customer service.
            </div>
        <?php endif; ?>

        <!-- Instruksi SS Halaman ke WA -->
        <div class="ss-instruction no-print">
            <h3>📸 Cara Konfirmasi: Screenshot Halaman Ini!</h3>
            <p><strong>Langkah-langkah:</strong></p>
            <ol>
                <li>Screenshot seluruh halaman ini (gunakan tombol Print Screen di keyboard atau tool snipping di HP/PC).</li>
                <li>Buka WhatsApp, chat ke nomor admin: <?= $wa_number ?>.</li>
                <li>Kirim screenshot tersebut beserta bukti transfer pembayaran Anda.</li>
                <li>Admin akan verifikasi dan proses pesanan Anda segera.</li>
            </ol>
            <p><em>Tips: Gunakan tombol "Print Halaman Ini" di bawah untuk format cetak yang lebih rapi sebelum SS.</em></p>
        </div>

        <!-- Bagian Print & Screenshot -->
        <div class="print-section no-print">
            <button onclick="printPage()" class="print-btn">🖨️ Print Halaman Ini</button>
            <p class="ss-instruction">
                Print untuk simpan sebagai PDF atau kertas, lalu SS dan kirim ke WA admin untuk konfirmasi cepat.
            </p>
        </div>

        <div style="text-align: center;" class="no-print">
            <a href="index.php" class="back-btn">Kembali ke Beranda</a>
            <?php if (isLoggedIn()): ?>
                <a href="orders.php" class="back-btn">Lihat Semua Pesanan</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
