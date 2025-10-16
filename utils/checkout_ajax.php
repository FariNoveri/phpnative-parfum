<?php
// NO WHITESPACE BEFORE THIS LINE!
require_once '../config/database.php';

// Clean any output buffer
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

try {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($nama) || empty($email) || empty($telepon) || empty($alamat)) {
        throw new Exception('Semua field yang wajib harus diisi');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Format email tidak valid');
    }
    
    // Get cart items
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("
            SELECT c.*, p.nama_parfum, p.harga, p.stok 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([getUserId()]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, p.nama_parfum, p.harga, p.stok 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.session_id = ?
        ");
        $stmt->execute([$_SESSION['session_id']]);
    }
    
    $cart_items = $stmt->fetchAll();
    
    if (empty($cart_items)) {
        throw new Exception('Keranjang belanja kosong');
    }
    
    $total = 0;
    foreach ($cart_items as $item) {
        $total += $item['harga'] * $item['jumlah'];
    }
    
    // Store pending order in session
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
    
    echo json_encode([
        'status' => 'success',
        'order_id' => $temp_order_id,
        'message' => 'Pesanan siap diproses'
    ]);
    
} catch (Exception $e) {
    error_log('Checkout AJAX error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
