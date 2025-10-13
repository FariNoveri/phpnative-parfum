<?php
// add_to_cart.php - Updated to handle volume selection
require_once '../config/database.php';

if (!$_POST) {
    redirect('index.php');
}

$product_id = (int)$_POST['product_id'];
$jumlah = (int)($_POST['quantity'] ?? $_POST['jumlah'] ?? 1);
$volume_selected = (int)($_POST['volume_selected'] ?? 100);

if ($product_id <= 0 || $jumlah <= 0) {
    $_SESSION['message'] = 'Data tidak valid';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

// Check product exists
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['message'] = 'Produk tidak ditemukan';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

// Check if volume price exists and get stock
$check_volume_sql = "SELECT stock, price FROM product_volume_prices 
                     WHERE product_id = ? AND volume_ml = ? AND is_available = 1";
$check_volume_stmt = $pdo->prepare($check_volume_sql);
$check_volume_stmt->execute([$product_id, $volume_selected]);
$volume_data = $check_volume_stmt->fetch();

if (!$volume_data) {
    // Fallback to default product if volume not found
    if ($product['stok'] < $jumlah) {
        $_SESSION['message'] = 'Stok tidak mencukupi';
        $_SESSION['message_type'] = 'error';
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
} else {
    // Check stock availability for selected volume
    if ($volume_data['stock'] < $jumlah) {
        $_SESSION['message'] = 'Stok tidak mencukupi. Stok tersedia: ' . $volume_data['stock'] . ' untuk ' . $volume_selected . 'ml';
        $_SESSION['message_type'] = 'error';
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

// Check if item already in cart (with same volume)
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ? AND volume_selected = ?");
    $stmt->execute([getUserId(), $product_id, $volume_selected]);
    $existing_cart = $stmt->fetch();
    
    if ($existing_cart) {
        // Update quantity
        $new_quantity = $existing_cart['jumlah'] + $jumlah;
        
        // Check stock for new quantity
        if ($volume_data) {
            $available_stock = $volume_data['stock'];
        } else {
            $available_stock = $product['stok'];
        }
        
        if ($new_quantity > $available_stock) {
            $_SESSION['message'] = 'Jumlah melebihi stok yang tersedia';
            $_SESSION['message_type'] = 'error';
            redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
        }
        
        $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE user_id = ? AND product_id = ? AND volume_selected = ?");
        $stmt->execute([$new_quantity, getUserId(), $product_id, $volume_selected]);
    } else {
        // Insert new cart item
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, jumlah, volume_selected) VALUES (?, ?, ?, ?)");
        $stmt->execute([getUserId(), $product_id, $jumlah, $volume_selected]);
    }
} else {
    // For guest users, use session_id
    $stmt = $pdo->prepare("SELECT * FROM cart WHERE session_id = ? AND product_id = ? AND volume_selected = ?");
    $stmt->execute([$_SESSION['session_id'], $product_id, $volume_selected]);
    $existing_cart = $stmt->fetch();
    
    if ($existing_cart) {
        // Update quantity
        $new_quantity = $existing_cart['jumlah'] + $jumlah;
        
        // Check stock for new quantity
        if ($volume_data) {
            $available_stock = $volume_data['stock'];
        } else {
            $available_stock = $product['stok'];
        }
        
        if ($new_quantity > $available_stock) {
            $_SESSION['message'] = 'Jumlah melebihi stok yang tersedia';
            $_SESSION['message_type'] = 'error';
            redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
        }
        
        $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE session_id = ? AND product_id = ? AND volume_selected = ?");
        $stmt->execute([$new_quantity, $_SESSION['session_id'], $product_id, $volume_selected]);
    } else {
        // Insert new cart item
        $stmt = $pdo->prepare("INSERT INTO cart (session_id, product_id, jumlah, volume_selected) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['session_id'], $product_id, $jumlah, $volume_selected]);
    }
}

$_SESSION['message'] = $product['nama_parfum'] . ' (' . $volume_selected . 'ml) berhasil ditambahkan ke keranjang';
$_SESSION['message_type'] = 'success';
redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
?>