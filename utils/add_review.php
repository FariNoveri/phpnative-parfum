<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

if (!isLoggedIn()) {
    $_SESSION['message'] = 'Anda harus login untuk memberikan review.';
    $_SESSION['message_type'] = 'error';
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $review_title = trim($_POST['review_title'] ?? '');
    $review_text = trim($_POST['review_text'] ?? '');
    $user_id = getUserId();
    $customer_name = $_SESSION['user_name'] ?? 'Anonymous';

    $errors = [];

    if ($product_id <= 0) {
        $errors[] = 'ID produk tidak valid.';
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Rating harus antara 1 hingga 5.';
    }

    if (empty($review_title)) {
        $errors[] = 'Judul review wajib diisi.';
    }

    if (empty($review_text)) {
        $errors[] = 'Teks review wajib diisi.';
    }

    // Check if user already reviewed this product
    $check_stmt = $pdo->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
    $check_stmt->execute([$product_id, $user_id]);
    if ($check_stmt->fetch()) {
        $errors[] = 'Anda sudah memberikan review untuk produk ini.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, user_id, customer_name, rating, review_title, review_text, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        if ($stmt->execute([$product_id, $user_id, $customer_name, $rating, $review_title, $review_text])) {
            $_SESSION['message'] = 'Review berhasil dikirim dan akan diverifikasi oleh admin.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Gagal mengirim review. Silakan coba lagi.';
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = implode('<br>', $errors);
        $_SESSION['message_type'] = 'error';
    }
}

redirect("product_detail.php?id=$product_id");
?>