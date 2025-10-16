<?php
session_start();
require_once '../config/database.php'; // Sesuaikan path ke config/database.php

// Fetch all reviews (pending, approved, rejected) untuk tampilan lengkap
$sql = "SELECT pr.*, p.nama_parfum 
        FROM product_reviews pr 
        LEFT JOIN products p ON pr.product_id = p.id 
        ORDER BY pr.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$reviews = $stmt->fetchAll();

// Handle actions: approve, reject, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $review_id = (int)($_POST['review_id'] ?? 0);
    
    if ($review_id && in_array($action, ['approve', 'reject', 'delete'])) {
        if ($action === 'delete') {
            // Delete review
            $delete_sql = "DELETE FROM product_reviews WHERE id = ?";
            $pdo->prepare($delete_sql)->execute([$review_id]);
            
            // Update product stats (kurangi total_reviews jika approved sebelumnya)
            $check_sql = "SELECT status, product_id FROM product_reviews WHERE id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$review_id]);
            $old_review = $check_stmt->fetch();
            
            if ($old_review && $old_review['status'] === 'approved') {
                updateProductRating($pdo, $old_review['product_id'], -1); // Kurangi rating
            }
        } else {
            // Get old status
            $check_sql = "SELECT status, product_id FROM product_reviews WHERE id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$review_id]);
            $old_review = $check_stmt->fetch();
            
            // Update status
            $update_sql = "UPDATE product_reviews SET status = ? WHERE id = ?";
            $pdo->prepare($update_sql)->execute([$action, $review_id]);
            
            // Jika approve, update rating average produk
            if ($action === 'approve') {
                updateProductRating($pdo, $old_review['product_id'], 1);
            } elseif ($action === 'reject' && $old_review['status'] === 'approved') {
                updateProductRating($pdo, $old_review['product_id'], -1);
            }
        }
        
        // Log admin action
        $log_sql = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)";
        $pdo->prepare($log_sql)->execute([$_SESSION['user_id'] ?? 1, strtoupper($action), "Review ID: $review_id"]);
        
        header("Location: reviews.php?success=" . urlencode($action));
        exit();
    }
}

// Function to update product rating average
function updateProductRating($pdo, $product_id, $delta) {
    // Update total_reviews
    $update_total = "UPDATE products SET total_reviews = total_reviews + ? WHERE id = ?";
    $pdo->prepare($update_total)->execute([$delta, $product_id]);
    
    // Recalculate average
    $avg_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM product_reviews WHERE product_id = ? AND status = 'approved'";
    $avg_stmt = $pdo->prepare($avg_sql);
    $avg_stmt->execute([$product_id]);
    $avg = $avg_stmt->fetch();
    
    $new_avg = $avg['total'] > 0 ? round($avg['avg_rating'], 2) : 0;
    $update_avg = "UPDATE products SET rating_average = ? WHERE id = ?";
    $pdo->prepare($update_avg)->execute([$new_avg, $product_id]);
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Review - Admin Panel</title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; background: #f8f9fa; }
        .admin-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 2rem 1rem; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { text-align: center; margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .admin-logo { font-size: 2rem; margin-bottom: 0.5rem; }
        .admin-title { font-size: 1.2rem; opacity: 0.9; }
        .admin-name { font-size: 0.9rem; opacity: 0.7; margin-top: 0.5rem; }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 0.5rem; }
        .nav-link { display: flex; align-items: center; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; transition: all 0.3s; opacity: 0.8; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); opacity: 1; transform: translateX(5px); }
        .nav-icon { margin-right: 1rem; font-size: 1.2rem; }
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; }
        .top-bar { background: white; padding: 1.5rem 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 2rem; color: #333; margin: 0; }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .logout-btn { background: #e74c3c; color: white; padding: 0.7rem 1.5rem; border: none; border-radius: 8px; text-decoration: none; transition: background 0.3s; }
        .logout-btn:hover { background: #c0392b; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        .status-pending { color: orange; font-weight: bold; }
        .status-approved { color: green; font-weight: bold; }
        .status-rejected { color: red; font-weight: bold; }
        .actions { display: flex; gap: 5px; }
        .btn { padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-approve { background: #27ae60; color: white; }
        .btn-reject { background: #e74c3c; color: white; }
        .btn-delete { background: #95a5a6; color: white; }
        .btn:hover { opacity: 0.8; }
        .success { color: green; text-align: center; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #667eea; text-decoration: none; }
        .review-text { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 1rem; } .sidebar { transform: translateX(-100%); } }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="admin-logo">🌸</div>
                <div class="admin-title">Admin Panel</div>
                <div class="admin-name">👋 <?= $_SESSION['user_name'] ?? 'Admin' ?></div>
            </div>
            
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <span class="nav-icon">📊</span> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link">
                            <span class="nav-icon">🧴</span> Kelola Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="orders.php" class="nav-link">
                            <span class="nav-icon">📦</span> Kelola Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reviews.php" class="nav-link active">
                            <span class="nav-icon">⭐</span> Kelola Review
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="nav-link">
                            <span class="nav-icon">👥</span> Kelola User
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
                            <span class="nav-icon">📈</span> Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link">
                            <span class="nav-icon">⚙️</span> Pengaturan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link" target="_blank">
                            <span class="nav-icon">🌐</span> Lihat Website
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Kelola Review</h1>
                <div class="user-info">
                    <span><?= $_SESSION['user_name'] ?? 'Admin' ?></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <div class="container">
                <a href="dashboard.php" class="back-link">← Kembali ke Dashboard</a>
                
                <?php if (isset($_GET['success'])): ?>
                    <p class="success">Review berhasil <?= $_GET['success'] ?>!</p>
                <?php endif; ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produk</th>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Judul</th>
                            <th>Review</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td><?= $review['id'] ?></td>
                                <td><?= htmlspecialchars($review['nama_parfum'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($review['customer_name']) ?></td>
                                <td><?= str_repeat('⭐', $review['rating']) ?></td>
                                <td><?= htmlspecialchars($review['review_title'] ?? 'N/A') ?></td>
                                <td class="review-text" title="<?= htmlspecialchars($review['review_text'] ?? '') ?>"><?= substr(htmlspecialchars($review['review_text'] ?? ''), 0, 100) ?>...</td>
                                <td class="status-<?= $review['status'] ?>"><?= ucfirst($review['status']) ?></td>
                                <td><?= date('d M Y H:i', strtotime($review['created_at'])) ?></td>
                                <td class="actions">
                                    <?php if ($review['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-approve" onclick="return confirm('Approve review ini?')">Approve</button>
                                            <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Reject review ini?')">Reject</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <button type="submit" name="action" value="delete" class="btn btn-delete" onclick="return confirm('Hapus review ini?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reviews)): ?>
                            <tr><td colspan="9" style="text-align: center; padding: 2rem;">Belum ada review.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
