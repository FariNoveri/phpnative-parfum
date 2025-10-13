<?php
// delete_review.php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['message'] = 'Akses ditolak. Admin hanya.';
    $_SESSION['message_type'] = 'error';
    redirect("product_detail.php?id=" . ($_GET['product_id'] ?? 0));
}

$review_id = (int)($_GET['id'] ?? 0);
$product_id = (int)($_GET['product_id'] ?? 0);

if ($review_id <= 0 || $product_id <= 0) {
    $_SESSION['message'] = 'Parameter tidak valid.';
    $_SESSION['message_type'] = 'error';
    redirect("product_detail.php?id=$product_id");
}

try {
    // Delete review
    $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE id = ? AND product_id = ?");
    if ($stmt->execute([$review_id, $product_id])) {
        // Update product ratings if needed (optional: recalculate average)
        $update_stmt = $pdo->prepare("
            UPDATE products p
            SET 
                rating_average = COALESCE((
                    SELECT AVG(r.rating) 
                    FROM product_reviews r 
                    WHERE r.product_id = p.id AND r.status = 'approved'
                ), 0),
                total_reviews = COALESCE((
                    SELECT COUNT(*) 
                    FROM product_reviews r 
                    WHERE r.product_id = p.id AND r.status = 'approved'
                ), 0)
            WHERE p.id = ?
        ");
        $update_stmt->execute([$product_id]);

        $_SESSION['message'] = 'Review berhasil dihapus.';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Gagal menghapus review.';
        $_SESSION['message_type'] = 'error';
    }
} catch (Exception $e) {
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

redirect("product_detail.php?id=$product_id");
?>