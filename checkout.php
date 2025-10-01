<?php
require_once 'config/database.php';
require_once 'config/midtrans_config.php'; // TAMBAHAN BARU

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
$success = '';

if ($_POST) {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);
    $notes = trim($_POST['notes']);
    
    if (empty($nama) || empty($email) || empty($telepon) || empty($alamat)) {
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
        
        // Return JSON untuk handle Midtrans
        if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'order_id' => $temp_order_id,
                'message' => 'Pesanan siap diproses'
            ]);
            exit;
        }
        
        $_SESSION['message'] = 'Data pesanan tersimpan. Silakan lanjutkan pembayaran.';
        $_SESSION['message_type'] = 'success';
        redirect('checkout.php'); // Atau halaman lain jika diperlukan
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Toko Parfum Premium</title>
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
        
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
        }
        
        .checkout-form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .order-summary {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        h2 {
            margin-bottom: 2rem;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
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
            margin-left: 1rem;
            color: #666;
        }
        
        .item-price {
            margin-left: 1rem;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .total-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #667eea;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .total-final {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
            width: 100%;
            text-align: center;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .btn-success {
            background: #27ae60;
            margin-top: 2rem;
        }
        
        .btn-success:hover:not(:disabled) {
            background: #229954;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 5px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .login-prompt {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .login-prompt a {
            color: #1976d2;
            text-decoration: none;
            font-weight: bold;
        }
        
        .login-prompt a:hover {
            text-decoration: underline;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .payment-info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
        }
        
        .payment-info h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 1rem;
        }
        
        .loading.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
                gap: 2rem;
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
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">🌸 Parfum Premium</div>
            <div class="nav-links">
                <a href="index.php">Beranda</a>
                <a href="cart.php">Keranjang</a>
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
            <h1>Checkout</h1>
            
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
                        
                        <button type="submit" class="btn btn-success" id="payButton">💳 Bayar Sekarang</button>
                        
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
                                <div class="item-brand"><?= htmlspecialchars($item['brand']) ?></div>
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

    <!-- Midtrans Snap JS -->
    <script src="<?php echo MIDTRANS_SNAP_URL; ?>" data-client-key="<?php echo MIDTRANS_CLIENT_KEY; ?>"></script>
    
    <script>
    let tempOrderId = null;
    
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const payButton = document.getElementById('payButton');
        const loading = document.getElementById('loading');
        
        // Disable button
        payButton.disabled = true;
        loading.classList.add('active');
        
        // Ambil form data
        const formData = new FormData(this);
        formData.append('ajax', '1');
        
        // Submit form untuk store pending order
        fetch('checkout.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                tempOrderId = data.order_id;
                // Request snap token dari Midtrans
                const midtransData = new FormData();
                midtransData.append('order_id', data.order_id);
                
                return fetch('midtrans_payment.php', {
                    method: 'POST',
                    body: midtransData
                })
                .then(response => response.json())
                .then(midtransResult => {
                    return {
                        order_id: data.order_id,
                        ...midtransResult
                    };
                });
            } else {
                throw new Error(data.message || 'Gagal memproses pesanan');
            }
        })
        .then(data => {
            if (data.status === 'success') {
                // Trigger Midtrans Snap popup
                snap.pay(data.snap_token, {
                    onSuccess: function(result) {
                        console.log('Payment success:', result);
                        // Update status di database (akan create order)
                        updatePaymentStatus(tempOrderId, 'settlement', result.transaction_id);
                    },
                    onPending: function(result) {
                        console.log('Payment pending:', result);
                        // Update status di database (akan create order dengan pending)
                        updatePaymentStatus(tempOrderId, 'pending', result.transaction_id);
                    },
                    onError: function(result) {
                        console.log('Payment error:', result);
                        alert('Pembayaran gagal! Silakan coba lagi.');
                        // Clear pending order
                        clearPendingOrder(tempOrderId);
                        payButton.disabled = false;
                        loading.classList.remove('active');
                    },
                    onClose: function() {
                        console.log('Payment popup closed');
                        // User tutup popup tanpa bayar
                        // Clear pending order
                        clearPendingOrder(tempOrderId);
                        alert('Pembayaran dibatalkan. Pesanan tidak diproses.');
                        payButton.disabled = false;
                        loading.classList.remove('active');
                    }
                });
            } else {
                throw new Error(data.message || 'Gagal memproses pembayaran');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
            // Clear pending jika ada error setelah store pending
            if (tempOrderId) {
                clearPendingOrder(tempOrderId);
            }
            payButton.disabled = false;
            loading.classList.remove('active');
        });
    });
    
    // Function untuk update payment status (akan create order jika temp)
    function updatePaymentStatus(orderId, transactionStatus, transactionId) {
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('transaction_status', transactionStatus);
        formData.append('transaction_id', transactionId || '');
        
        fetch('payment_callback.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                window.location.href = 'order_confirmation.php?id=' + data.order_id;
            } else {
                console.error('Failed to update payment status:', data.message);
                // Tetap redirect meskipun update gagal, gunakan order_id dari response jika ada
                const redirectId = data.order_id || orderId.replace('temp_', '');
                window.location.href = 'order_confirmation.php?id=' + redirectId;
            }
        })
        .catch(error => {
            console.error('Error updating payment status:', error);
            // Jika gagal create order, kembali ke cart
            window.location.href = 'cart.php';
        });
    }
    
    // Function untuk clear pending order
    function clearPendingOrder(orderId) {
        if (!orderId) return;
        
        const cancelData = new FormData();
        cancelData.append('order_id', orderId);
        cancelData.append('action', 'cancel');
        
        fetch('payment_callback.php', {
            method: 'POST',
            body: cancelData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Pending cleared:', data);
        })
        .catch(error => {
            console.error('Error clearing pending order:', error);
            // Ignore error, since it's just cleanup
        });
    }
    </script>
</body>
</html>