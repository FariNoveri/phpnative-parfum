<?php
require_once 'config/database.php';

if (!$_POST) {
    redirect('index.php');
}

$product_id = (int)$_POST['product_id'];
$jumlah = (int)($_POST['jumlah'] ?? 1);

if ($product_id <= 0 || $jumlah <= 0) {
    $_SESSION['message'] = 'Data tidak valid';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

// Check product exists and has stock
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['message'] = 'Produk tidak ditemukan';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

if ($product['stok'] < $jumlah) {
    $_SESSION['message'] = 'Stok tidak mencukupi';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

// Check if item already in cart
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([getUserId(), $product_id]);
    $existing_cart = $stmt->fetch();
    
    if ($existing_cart) {
        // Update quantity
        $new_quantity = $existing_cart['jumlah'] + $jumlah;
        
        if ($new_quantity > $product['stok']) {
            $_SESSION['message'] = 'Jumlah melebihi stok yang tersedia';
            $_SESSION['message_type'] = 'error';
            redirect('index.php');
        }
        
        $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$new_quantity, getUserId(), $product_id]);
    } else {
        // Insert new cart item
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, jumlah) VALUES (?, ?, ?)");
        $stmt->execute([getUserId(), $product_id, $jumlah]);
    }
} else {
    // For guest users, use session_id
    $stmt = $pdo->prepare("SELECT * FROM cart WHERE session_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['session_id'], $product_id]);
    $existing_cart = $stmt->fetch();
    
    if ($existing_cart) {
        // Update quantity
        $new_quantity = $existing_cart['jumlah'] + $jumlah;
        
        if ($new_quantity > $product['stok']) {
            $_SESSION['message'] = 'Jumlah melebihi stok yang tersedia';
            $_SESSION['message_type'] = 'error';
            redirect('index.php');
        }
        
        $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE session_id = ? AND product_id = ?");
        $stmt->execute([$new_quantity, $_SESSION['session_id'], $product_id]);
    } else {
        // Insert new cart item
        $stmt = $pdo->prepare("INSERT INTO cart (session_id, product_id, jumlah) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['session_id'], $product_id, $jumlah]);
    }
}

$_SESSION['message'] = $product['nama_parfum'] . ' berhasil ditambahkan ke keranjang';
$_SESSION['message_type'] = 'success';
redirect('index.php');
?>