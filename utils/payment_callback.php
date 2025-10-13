<?php
// Pastiin ga ada spasi/comment sebelum <?php ! File harus mulai langsung begini.

if (ob_get_level()) {
    ob_clean();  // Bersihin buffer biar ga ada output prematur
}

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Log untuk debug
error_log('payment_callback POST: ' . print_r($_POST, true));
error_log('payment_callback Session: ' . print_r($_SESSION, true));

try {
    // Handle cancel
    if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        if (isset($_SESSION['pending_order'])) {
            unset($_SESSION['pending_order'], $_SESSION['pending_temp_order_id'], $_SESSION['midtrans_order_id']);
        }
        echo json_encode(['status' => 'success', 'message' => 'Pending order cleared']);
        exit;
    }
    
    $order_id = $_POST['order_id'] ?? null;
    $transaction_status = $_POST['transaction_status'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? '';
    
    if (!$order_id || !$transaction_status) {
        throw new Exception('Data tidak lengkap');
    }
    
    $midtrans_order_id = $_SESSION['midtrans_order_id'] ?? null;
    if (!$midtrans_order_id) {
        throw new Exception('Midtrans order ID tidak ditemukan');
    }
    
    // Status mapping
    $payment_status = 'pending';
    $order_status = 'pending';
    switch ($transaction_status) {
        case 'capture': case 'settlement':
            $payment_status = 'settlement'; $order_status = 'confirmed'; break;
        case 'pending':
            $payment_status = 'pending'; $order_status = 'pending'; break;
        case 'deny': case 'cancel': case 'expire':
            $payment_status = 'failed'; $order_status = 'cancelled'; break;
    }
    
    // Check existing
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE midtrans_order_id = ?");
    $stmt->execute([$midtrans_order_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $real_order_id = $existing['id'];
        $stmt = $pdo->prepare("UPDATE orders SET status_pembayaran = ?, status = ?, midtrans_transaction_id = ? WHERE id = ?");
        $stmt->execute([$payment_status, $order_status, $transaction_id, $real_order_id]);
        unset($_SESSION['pending_order'], $_SESSION['pending_temp_order_id'], $_SESSION['midtrans_order_id']);
        echo json_encode(['status' => 'success', 'order_id' => $real_order_id, 'redirect' => 'order_confirmation.php?id=' . $real_order_id]);
        exit;
    }
    
    if ($payment_status === 'failed') {
        unset($_SESSION['pending_order'], $_SESSION['pending_temp_order_id'], $_SESSION['midtrans_order_id']);
        echo json_encode(['status' => 'failed', 'message' => 'Pembayaran gagal']);
        exit;
    }
    
    // Create new
    if (!isset($_SESSION['pending_order'])) throw new Exception('Pending order tidak ditemukan');
    $pending = $_SESSION['pending_order'];
    
    $pdo->beginTransaction();
    
    // Insert order (adjust kolom dengan schema orders dari SQL dump)
    $stmt = $pdo->prepare("
        INSERT INTO orders 
        (user_id, nama_customer, email_customer, telepon_customer, alamat_customer, notes, total_harga, 
         status_pembayaran, status, midtrans_order_id, midtrans_transaction_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $pending['user_id'], $pending['customer']['nama'], $pending['customer']['email'],
        $pending['customer']['telepon'], $pending['customer']['alamat'], $pending['customer']['notes'] ?? '',
        $pending['total'], $payment_status, $order_status, $midtrans_order_id, $transaction_id
    ]);
    $real_order_id = $pdo->lastInsertId();
    
    // Insert order_items (SESUAI SCHEMA: tanpa nama_parfum & brand, tambah volume_selected dari cart)
    $stmt_item = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, jumlah, volume_selected, harga) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt_stock = $pdo->prepare("UPDATE products SET stok = stok - ? WHERE id = ? AND stok >= ?");
    
    foreach ($pending['items'] as $item) {
        $volume = $item['volume_selected'] ?? 100;  // Dari cart, default 100
        $stmt_item->execute([$real_order_id, $item['product_id'], $item['jumlah'], $volume, $item['harga']]);
        
        // Update stok di products (pastikan stok cukup)
        $stmt_stock->execute([$item['jumlah'], $item['product_id'], $item['jumlah']]);
        if ($stmt_stock->rowCount() === 0) {
            throw new Exception('Stok tidak cukup untuk product ID: ' . $item['product_id']);
        }
    }
    
    // Clear cart
    if ($pending['user_id']) {
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$pending['user_id']]);
    } elseif ($pending['session_id']) {
        $pdo->prepare("DELETE FROM cart WHERE session_id = ?")->execute([$pending['session_id']]);
    }
    
    $pdo->commit();
    unset($_SESSION['pending_order'], $_SESSION['pending_temp_order_id'], $_SESSION['midtrans_order_id']);
    
    echo json_encode([
        'status' => 'success',
        'order_id' => $real_order_id,
        'redirect' => 'order_confirmation.php?id=' . $real_order_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Callback error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>