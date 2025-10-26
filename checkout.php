<?php
require_once 'config/database.php';
require_once 'config/midtrans_config.php';

// Get cart items
if (isLoggedIn()) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nama_parfum, p.harga, p.stok 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([getUserId()]);
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nama_parfum, p.harga, p.stok 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.session_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['session_id']]);
}

$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    $_SESSION['message'] = 'Keranjang belanja kosong';
    $_SESSION['message_type'] = 'error';
    redirect('cart.php');
}

$total = 0;
foreach ($cart_items as $item) {
    $total += $item['harga'] * $item['jumlah'];
}

// Get user data if logged in
$user_data = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([getUserId()]);
    $user_data = $stmt->fetch();
}

$error = '';

// Handle AJAX form submission
if ($_POST) {
    // CRITICAL: Clean output buffer untuk hindari output sebelum JSON
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($nama) || empty($email) || empty($telepon) || empty($alamat)) {
        if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Semua field yang wajib harus diisi'
            ]);
            exit;
        }
        $error = 'Semua field yang wajib harus diisi';
    } else {
        // Store pending order data in session
        $_SESSION['pending_order'] = [
            'items' => $cart_items,
            'total' => $total,
            'customer' => [
                'nama' => $nama,
                'email' => $email,
                'telepon' => $telepon,
                'alamat' => $alamat,
                'notes' => $notes
            ],
            'user_id' => isLoggedIn() ? getUserId() : null,
            'session_id' => !isLoggedIn() ? ($_SESSION['session_id'] ?? null) : null
        ];
        
        $temp_order_id = 'temp_' . uniqid();
        $_SESSION['pending_temp_order_id'] = $temp_order_id;
        
        // Return JSON for AJAX request
        if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'order_id' => $temp_order_id,
                'message' => 'Pesanan siap diproses'
            ]);
            exit;
        }
    }
}

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
    <title>Checkout - Parfum Refill Premium</title>
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
            margin-bottom: 40px;
        }
        
        /* Alert */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-left: 3px solid;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #ef4444;
        }
        
        .login-prompt {
            background: #f0fdf4;
            border: 1px solid #22c55e;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            color: #166534;
        }
        
        .login-prompt a {
            color: #c41e3a;
            text-decoration: none;
            font-weight: bold;
        }
        
        .login-prompt a:hover {
            text-decoration: underline;
        }
        
        /* Checkout Container */
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
        }
        
        .checkout-form, .order-summary {
            background: #fff;
            padding: 30px;
            border-radius: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        h2 {
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            color: #2c2c2c;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 0;
            font-size: 14px;
            background: #fafafa;
            transition: all 0.3s;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #c41e3a;
            background: #fff;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .required {
            color: #c41e3a;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 400;
            margin-bottom: 5px;
            color: #2c2c2c;
        }
        
        .item-quantity {
            margin-left: 10px;
            color: #999;
            font-size: 13px;
        }
        
        .item-price {
            margin-left: 10px;
            font-weight: 600;
            color: #c41e3a;
            font-size: 16px;
        }
        
        .total-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .total-final {
            font-size: 20px;
            font-weight: 600;
            color: #2c2c2c;
        }
        
        .btn {
            background: #c41e3a;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 0;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .btn:hover:not(:disabled) {
            background: #a01628;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .payment-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        
        .payment-info h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #2c2c2c;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .payment-info p {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 15px;
            padding: 15px;
            background: #f8f8f8;
            color: #666;
        }
        
        .loading.active {
            display: block;
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
            .checkout-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .item-quantity,
            .item-price {
                margin-left: 0;
                margin-top: 5px;
            }
            
            .nav-links {
                gap: 15px;
            }
            
            .nav-links a {
                font-size: 12px;
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
            <a href="index.php" class="logo">Parfum Refill</a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="cart.php">Cart</a>
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
            <h1>Checkout</h1>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if (!isLoggedIn()): ?>
                <div class="login-prompt">
                    💡 <strong>Tips:</strong> <a href="login.php">Login</a> untuk checkout yang lebih cepat dengan data yang tersimpan
                </div>
            <?php endif; ?>

            <div class="checkout-container">
                <div class="checkout-form">
                    <h2>Data Pembeli</h2>
                    
                    <form method="POST" id="checkoutForm">
                        <div class="form-group">
                            <label for="nama">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" id="nama" name="nama" required 
                                   value="<?= htmlspecialchars($_POST['nama'] ?? $user_data['nama'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required 
                                   value="<?= htmlspecialchars($_POST['email'] ?? $user_data['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="telepon">No. Telepon <span class="required">*</span></label>
                            <input type="tel" id="telepon" name="telepon" required 
                                   value="<?= htmlspecialchars($_POST['telepon'] ?? $user_data['telepon'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="alamat">Alamat Pengiriman <span class="required">*</span></label>
                            <textarea id="alamat" name="alamat" required><?= htmlspecialchars($_POST['alamat'] ?? $user_data['alamat'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Catatan Pesanan (Opsional)</label>
                            <textarea id="notes" name="notes" placeholder="Catatan khusus untuk pesanan Anda..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn" id="payButton">💳 Bayar Sekarang</button>
                        
                        <div class="loading" id="loading">
                            <p>⏳ Memproses pembayaran...</p>
                        </div>
                    </form>
                </div>

                <div class="order-summary">
                    <h2>Ringkasan Pesanan</h2>
                    
                    <?php foreach ($cart_items as $item): ?>
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
                            <span><?= formatRupiah($total) ?></span>
                        </div>
                        <div class="total-row">
                            <span>Ongkos Kirim:</span>
                            <span>Gratis</span>
                        </div>
                        <div class="total-row total-final">
                            <span>Total:</span>
                            <span><?= formatRupiah($total) ?></span>
                        </div>
                    </div>
                    
                    <div class="payment-info">
                        <h3>💳 Metode Pembayaran</h3>
                        <p><strong>Midtrans Payment Gateway</strong></p>
                        <p>✅ Transfer Bank</p>
                        <p>✅ E-Wallet (GoPay, OVO, DANA)</p>
                        <p>✅ Kartu Kredit/Debit</p>
                        <p>✅ Alfamart/Indomaret</p>
                        <br>
                        <p><small>🔒 Pembayaran aman dengan Midtrans</small></p>
                    </div>
                </div>
            </div>
        </div>
    </main>

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

    <!-- Midtrans Snap JS -->
    <script src="<?php echo MIDTRANS_SNAP_URL; ?>" data-client-key="<?php echo MIDTRANS_CLIENT_KEY; ?>"></script>
    
    <script>
    // Verify Midtrans Snap loaded
    if (typeof window.snap === 'undefined') {
        console.error('CRITICAL: Midtrans Snap not loaded!');
        alert('Error: Payment gateway tidak tersedia. Silakan refresh halaman.');
    } else {
        console.log('Midtrans Snap loaded successfully');
    }
    
    let tempOrderId = null;

    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const payButton = document.getElementById('payButton');
        const loading = document.getElementById('loading');
        
        // Disable button and show loading
        payButton.disabled = true;
        loading.classList.add('active');
        
        // Get form data
        const formData = new FormData(this);
        formData.append('ajax', '1');
        
        // Step 1: Submit form to store pending order in session
        fetch('utils/checkout_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Debug: Log raw response
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response text:', text);
                    throw new Error('Server mengembalikan response yang tidak valid. Cek console untuk detail.');
                }
            });
        })
        .then(data => {
            if (data.status !== 'success') {
                throw new Error(data.message || 'Gagal menyimpan data pesanan');
            }
            
            tempOrderId = data.order_id;
            console.log('Pending order created:', tempOrderId);
            
            // Step 2: Request snap token from Midtrans
            const midtransData = new FormData();
            midtransData.append('order_id', tempOrderId);
            
            return fetch('utils/midtrans_payment.php', {
                method: 'POST',
                body: midtransData
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success' || !data.snap_token) {
                throw new Error(data.message || 'Gagal mendapatkan token pembayaran');
            }
            
            console.log('Snap token received:', data.snap_token);
            
            // Verify snap is available
            if (typeof window.snap === 'undefined') {
                throw new Error('Midtrans Snap belum di-load. Silakan refresh halaman.');
            }
            
            // Step 3: Open Midtrans Snap popup
            console.log('Opening Midtrans Snap popup...');
            
            window.snap.pay(data.snap_token, {
                onSuccess: function(result) {
                    console.log('=== MIDTRANS CALLBACK: SUCCESS ===');
                    console.log('Result:', JSON.stringify(result, null, 2));
                    
                    const transactionId = result.transaction_id || result.order_id || 'unknown';
                    console.log('Calling createRealOrder with:', tempOrderId, 'settlement', transactionId);
                    
                    createRealOrder(tempOrderId, 'settlement', transactionId);
                },
                
                onPending: function(result) {
                    console.log('=== MIDTRANS CALLBACK: PENDING ===');
                    console.log('Result:', JSON.stringify(result, null, 2));
                    
                    const transactionId = result.transaction_id || result.order_id || 'unknown';
                    console.log('Calling createRealOrder with:', tempOrderId, 'pending', transactionId);
                    
                    createRealOrder(tempOrderId, 'pending', transactionId);
                },
                
                onError: function(result) {
                    console.error('=== MIDTRANS CALLBACK: ERROR ===');
                    console.error('Result:', JSON.stringify(result, null, 2));
                    
                    alert('Pembayaran gagal!\n\n' + (result.status_message || 'Silakan coba lagi.'));
                    clearPendingOrder(tempOrderId);
                    resetForm();
                },
                
                onClose: function() {
                    console.log('=== MIDTRANS CALLBACK: CLOSED ===');
                    console.log('User closed the popup without completing payment');
                    
                    // Check payment status sebagai fallback
                    console.log('Checking payment status as fallback...');
                    checkPaymentStatus(tempOrderId);
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan: ' + error.message);
            
            if (tempOrderId) {
                clearPendingOrder(tempOrderId);
            }
            resetForm();
        });
    });

    // Create real order from pending data
    function createRealOrder(tempId, transactionStatus, transactionId) {
        console.log('=== CREATE REAL ORDER ===');
        console.log('Temp ID:', tempId);
        console.log('Transaction Status:', transactionStatus);
        console.log('Transaction ID:', transactionId);
        
        if (!tempId) {
            console.error('ERROR: tempId is empty!');
            alert('Error: Order ID kosong');
            return;
        }
        
        const formData = new FormData();
        formData.append('order_id', tempId);
        formData.append('transaction_status', transactionStatus);
        formData.append('transaction_id', transactionId || '');
        
        console.log('Sending to payment_callback.php...');
        
        fetch('utils/payment_callback.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed response:', data);
                
                if (data.status === 'success' && data.order_id) {
                    console.log('SUCCESS! Redirecting to order_confirmation.php?id=' + data.order_id);
                    window.location.href = 'order_confirmation.php?id=' + data.order_id;
                } else {
                    throw new Error(data.message || 'Gagal membuat pesanan');
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.error('Response text:', text);
                throw e;
            }
        })
        .catch(error => {
            console.error('Error creating order:', error);
            alert('Pembayaran berhasil, tapi terjadi kesalahan sistem.\n\nOrder ID: ' + tempId + '\n\nSilakan hubungi admin dengan ID tersebut.');
            // Jangan redirect, biarkan user screenshot error
        });
    }

    // Clear pending order from session
    function clearPendingOrder(tempId) {
        if (!tempId) return;
        
        console.log('Clearing pending order:', tempId);
        
        const formData = new FormData();
        formData.append('order_id', tempId);
        formData.append('action', 'cancel');
        
        fetch('utils/payment_callback.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Pending order cleared:', data);
        })
        .catch(error => {
            console.error('Error clearing pending order:', error);
        });
    }

    // Check payment status (fallback when popup closed)
    function checkPaymentStatus(tempId) {
        console.log('=== CHECK PAYMENT STATUS ===');
        console.log('Checking status for:', tempId);
        
        const formData = new FormData();
        formData.append('temp_order_id', tempId);
        
        fetch('utils/check_payment_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Payment status response:', data);
            
            if (data.status === 'success') {
                // Payment successful, redirect to confirmation
                alert('Pembayaran berhasil! Anda akan diarahkan ke halaman konfirmasi.');
                window.location.href = 'order_confirmation.php?id=' + data.order_id;
            } else if (data.status === 'failed') {
                // Payment failed
                alert('Pembayaran gagal atau dibatalkan.\n\nStatus: ' + data.transaction_status);
                clearPendingOrder(tempId);
                resetForm();
            } else {
                // Still pending or error
                const retry = confirm('Status pembayaran: ' + (data.transaction_status || 'Tidak diketahui') + 
                    '\n\nApakah Anda ingin:\n- OK: Cek status lagi\n- Cancel: Batalkan pesanan');
                
                if (retry) {
                    // Wait 3 seconds then check again
                    setTimeout(() => checkPaymentStatus(tempId), 3000);
                } else {
                    clearPendingOrder(tempId);
                    resetForm();
                }
            }
        })
        .catch(error => {
            console.error('Error checking payment status:', error);
            
            const retry = confirm('Gagal mengecek status pembayaran.\n\nApakah Anda ingin coba lagi?');
            if (retry) {
                setTimeout(() => checkPaymentStatus(tempId), 3000);
            } else {
                resetForm();
            }
        });
    }

    // Reset form state
    function resetForm() {
        const payButton = document.getElementById('payButton');
        const loading = document.getElementById('loading');
        
        payButton.disabled = false;
        loading.classList.remove('active');
    }
    </script>
</body>
</html>