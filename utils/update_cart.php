<?php
// update_cart.php - Handle cart quantity updates with volume
require_once '../config/database.php';

if (!$_POST) {
    redirect('cart.php');
}

$action = $_POST['action'] ?? '';
$cart_id = (int)($_POST['cart_id'] ?? 0);

if ($cart_id <= 0) {
    $_SESSION['message'] = 'Data tidak valid';
    $_SESSION['message_type'] = 'error';
    redirect('cart.php');
}

// Get cart item with volume info
if (isLoggedIn()) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nama_parfum, p.stok,
        COALESCE(pvp.stock, p.stok) as volume_stock
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        LEFT JOIN product_volume_prices pvp ON p.id = pvp.product_id AND c.volume_selected = pvp.volume_ml
        WHERE c.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$cart_id, getUserId()]);
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nama_parfum, p.stok,
        COALESCE(pvp.stock, p.stok) as volume_stock
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        LEFT JOIN product_volume_prices pvp ON p.id = pvp.product_id AND c.volume_selected = pvp.volume_ml
        WHERE c.id = ? AND c.session_id = ?
    ");
    $stmt->execute([$cart_id, $_SESSION['session_id']]);
}

$cart_item = $stmt->fetch();

if (!$cart_item) {
    $_SESSION['message'] = 'Item tidak ditemukan';
    $_SESSION['message_type'] = 'error';
    redirect('cart.php');
}

// Handle increase quantity
if ($action === 'increase') {
    $new_quantity = $cart_item['jumlah'] + 1;
    
    if ($new_quantity > $cart_item['volume_stock']) {
        $_SESSION['message'] = 'Stok tidak mencukupi. Stok tersedia: ' . $cart_item['volume_stock'];
        $_SESSION['message_type'] = 'error';
        redirect('cart.php');
    }
    
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_quantity, $cart_id, getUserId()]);
    } else {
        $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE id = ? AND session_id = ?");
        $stmt->execute([$new_quantity, $cart_id, $_SESSION['session_id']]);
    }
    
    $_SESSION['message'] = 'Jumlah berhasil ditambah';
    $_SESSION['message_type'] = 'success';
}

// Handle decrease quantity
elseif ($action === 'decrease') {
    $new_quantity = $cart_item['jumlah'] - 1;
    
    if ($new_quantity <= 0) {
        // Remove item if quantity becomes 0
        if (isLoggedIn()) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, getUserId()]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND session_id = ?");
            $stmt->execute([$cart_id, $_SESSION['session_id']]);
        }
        
        $_SESSION['message'] = 'Item dihapus dari keranjang';
        $_SESSION['message_type'] = 'success';
    } else {
        if (isLoggedIn()) {
            $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$new_quantity, $cart_id, getUserId()]);
        } else {
            $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE id = ? AND session_id = ?");
            $stmt->execute([$new_quantity, $cart_id, $_SESSION['session_id']]);
        }
        
        $_SESSION['message'] = 'Jumlah berhasil dikurangi';
        $_SESSION['message_type'] = 'success';
    }
}

// Handle manual update
elseif ($action === 'update') {
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    
    if ($jumlah <= 0) {
        // Remove item if quantity is 0 or less
        if (isLoggedIn()) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, getUserId()]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND session_id = ?");
            $stmt->execute([$cart_id, $_SESSION['session_id']]);
        }
        
        $_SESSION['message'] = 'Item dihapus dari keranjang';
        $_SESSION['message_type'] = 'success';
    } else {
        // Check stock availability
        if ($jumlah > $cart_item['volume_stock']) {
            $_SESSION['message'] = 'Jumlah melebihi stok yang tersedia. Stok tersedia: ' . $cart_item['volume_stock'];
            $_SESSION['message_type'] = 'error';
            redirect('cart.php');
        }
        
        if (isLoggedIn()) {
            $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$jumlah, $cart_id, getUserId()]);
        } else {
            $stmt = $pdo->prepare("UPDATE cart SET jumlah = ? WHERE id = ? AND session_id = ?");
            $stmt->execute([$jumlah, $cart_id, $_SESSION['session_id']]);
        }
        
        $_SESSION['message'] = 'Keranjang berhasil diupdate';
        $_SESSION['message_type'] = 'success';
    }
}

redirect('cart.php');
?>