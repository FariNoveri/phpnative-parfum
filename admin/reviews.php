<?php
// Prevent any output before JSON response
ob_start();

session_start();
require_once '../config/database.php';

$action = $_GET['action'] ?? '';
$review_id = (int)($_GET['id'] ?? 0);

// Handle AJAX load more
if (isset($_GET['ajax']) && $_GET['ajax'] === 'load_more') {
    header('Content-Type: application/json');
    
    $offset = (int)($_GET['offset'] ?? 0);
    $limit = 5;
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'created_at DESC';

    $sql = "SELECT pr.*, p.nama_parfum 
            FROM product_reviews pr 
            LEFT JOIN products p ON pr.product_id = p.id 
            WHERE 1=1";
    $params = [];

    if ($status_filter) {
        $sql .= " AND pr.status = ?";
        $params[] = $status_filter;
    }

    if ($search) {
        $sql .= " AND (pr.customer_name LIKE ? OR pr.review_text LIKE ? OR p.nama_parfum LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY pr.$sort LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();

    // Build HTML rows
    $html = '';
    foreach ($reviews as $review) {
        $statusClass = $review['status'];
        $statusText = ucfirst($review['status']);
        $productName = htmlspecialchars($review['nama_parfum'] ?? 'N/A');
        $customerName = htmlspecialchars($review['customer_name']);
        $rating = str_repeat('⭐', $review['rating']);
        $title = htmlspecialchars($review['review_title'] ?? 'N/A');
        $reviewTextFull = htmlspecialchars($review['review_text'] ?? '');
        $text = substr($reviewTextFull, 0, 100) . (strlen($reviewTextFull) > 100 ? '...' : '');
        $date = date('d/m/Y H:i', strtotime($review['created_at']));
        
        // All statuses can be changed
        $actions = '';
        if ($review['status'] !== 'approved') {
            $actions .= '<button class="btn btn-approve approve-review" data-review-id="' . $review['id'] . '">✅</button>';
        }
        if ($review['status'] !== 'pending') {
            $actions .= '<button class="btn btn-pending pending-review" data-review-id="' . $review['id'] . '">⏳</button>';
        }
        if ($review['status'] !== 'rejected') {
            $actions .= '<button class="btn btn-reject reject-review" data-review-id="' . $review['id'] . '">❌</button>';
        }
        $actions .= '<button class="btn btn-delete delete-review" data-review-id="' . $review['id'] . '">🗑️</button>';
        
        $html .= "<tr data-review-id='{$review['id']}'>
            <td>{$review['id']}</td>
            <td>{$productName}</td>
            <td>{$customerName}</td>
            <td>{$rating}</td>
            <td>{$title}</td>
            <td class='review-text' title='{$reviewTextFull}'>{$text}</td>
            <td class='status-{$statusClass}'>{$statusText}</td>
            <td>{$date}</td>
            <td class='actions'>{$actions}</td>
        </tr>";
    }

    // Check if more
    $checkSql = str_replace('LIMIT ? OFFSET ?', 'LIMIT 1 OFFSET ?', $sql);
    $checkParams = array_slice($params, 0, -2);
    $checkParams[] = $offset + $limit;
    
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute($checkParams);
    $hasMore = $stmt->fetch() !== false;

    // Return JSON
    echo json_encode([
        'success' => true,
        'html' => $html,
        'hasMore' => $hasMore,
        'count' => count($reviews)
    ]);
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $review_id = (int)($_POST['review_id'] ?? 0);
    
    if ($review_id) {
        if ($action === 'delete') {
            $stmt = $pdo->prepare("SELECT status, product_id FROM product_reviews WHERE id = ?");
            $stmt->execute([$review_id]);
            $old_review = $stmt->fetch();
            
            if ($old_review && $old_review['status'] === 'approved') {
                updateProductRating($pdo, $old_review['product_id'], -1);
            }
            
            $pdo->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$review_id]);
            
            // Log admin action
            $log_sql = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)";
            $pdo->prepare($log_sql)->execute([$_SESSION['user_id'] ?? 1, 'DELETE_REVIEW', "Menghapus review ID: $review_id"]);
            
            echo json_encode(['success' => true, 'message' => 'Review berhasil dihapus']);
        } elseif ($action === 'approve' || $action === 'reject' || $action === 'pending') {
            $new_status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending');
            
            $stmt = $pdo->prepare("SELECT status, product_id FROM product_reviews WHERE id = ?");
            $stmt->execute([$review_id]);
            $old_review = $stmt->fetch();
            
            $pdo->prepare("UPDATE product_reviews SET status = ? WHERE id = ?")->execute([$new_status, $review_id]);
            
            $delta = 0;
            if ($new_status === 'approved' && $old_review['status'] !== 'approved') {
                $delta = 1;
            } elseif ($new_status !== 'approved' && $old_review['status'] === 'approved') {
                $delta = -1;
            }
            
            if ($delta !== 0) {
                updateProductRating($pdo, $old_review['product_id'], $delta);
            }
            
            // Log admin action
            $log_sql = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)";
            $pdo->prepare($log_sql)->execute([$_SESSION['user_id'] ?? 1, strtoupper($action), "Review ID: $review_id"]);
            
            echo json_encode(['success' => true, 'message' => 'Status berhasil diubah menjadi ' . $new_status, 'new_status' => $new_status]);
        }
        exit;
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

// Get initial reviews
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at DESC';

$sql = "SELECT pr.*, p.nama_parfum 
        FROM product_reviews pr 
        LEFT JOIN products p ON pr.product_id = p.id 
        WHERE 1=1";
$params = [];

if ($status_filter) {
    $sql .= " AND pr.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $sql .= " AND (pr.customer_name LIKE ? OR pr.review_text LIKE ? OR p.nama_parfum LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY pr.$sort LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$initial_reviews = $stmt->fetchAll();

// Stats
$stats_sql = "SELECT 
    COUNT(*) as total,
    COUNT(IF(status = 'pending', 1, NULL)) as pending,
    COUNT(IF(status = 'approved', 1, NULL)) as approved,
    COUNT(IF(status = 'rejected', 1, NULL)) as rejected
    FROM product_reviews";
$stats = $pdo->query($stats_sql)->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Review - UniqThings</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../img/logo.png">
    <link rel="shortcut icon" type="image/png" href="../img/logo.png">
    <link rel="apple-touch-icon" href="../img/logo.png">
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
            background-color: #f5f5f5;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: #fff;
            color: #2c2c2c;
            padding: 2rem 1rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .sidebar-header {
    text-align: center;
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #f0f0f0;
}

.sidebar-logo {
    height: 60px;
    width: auto;
    margin-bottom: 15px;
    object-fit: contain;
}

.admin-logo {
    font-size: 24px;
    font-weight: 300;
    letter-spacing: 2px;
    color: #2c2c2c;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}
        
        .admin-name {
            font-size: 0.9rem;
            color: #999;
            margin-top: 0.5rem;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            color: #2c2c2c;
            text-decoration: none;
            padding: 1rem;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: #ffeef5;
            opacity: 1;
            color: #c41e3a;
        }
        
        .nav-icon {
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }
        
        .top-bar {
            background: #fff;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 10px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 1px;
            color: #2c2c2c;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logout-btn {
            background: #c41e3a;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .logout-btn:hover {
            background: #a01628;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #c41e3a;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 400;
            color: #c41e3a;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #c41e3a 0%, #a01628 100%);
            color: white;
            padding: 1.5rem 2rem;
            font-size: 1.3rem;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        .section-content {
            padding: 2rem;
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .form-group {
            min-width: 200px;
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.95rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #c41e3a;
        }
        
        .reviews-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reviews-table th,
        .reviews-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .reviews-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .review-text {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: help;
        }
        
        .status-pending { color: orange; font-weight: bold; }
        .status-approved { color: green; font-weight: bold; }
        .status-rejected { color: red; font-weight: bold; }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn {
            background: #c41e3a;
            color: white;
            padding: 0.5rem 0.8rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-approve { background: #28a745; }
        .btn-approve:hover { background: #218838; }
        
        .btn-pending { background: #fd7e14; }
        .btn-pending:hover { background: #e56b0e; }
        
        .btn-reject { background: #ffc107; color: #000; }
        .btn-reject:hover { background: #e0a800; }
        
        .btn-delete { background: #dc3545; }
        .btn-delete:hover { background: #c82333; }
        
        .loading-indicator {
            text-align: center;
            padding: 2rem;
            color: #999;
            display: none;
            font-style: italic;
        }
        
        .no-more {
            text-align: center;
            padding: 2rem;
            color: #999;
            font-style: italic;
            display: none;
        }
        
        .reviews-table tr {
            transition: opacity 0.3s ease;
        }
        
        .reviews-table tr.removing {
            opacity: 0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <div class="admin-container">
        <aside class="sidebar">
    <div class="sidebar-header">
        <img src="../img/logo.png" alt="UniqThings" class="sidebar-logo">
        <div class="admin-logo">Parfum Refill</div>
        <div class="admin-title">Admin Panel</div>
        <div class="admin-name"><?= $_SESSION['user_name'] ?></div>
    </div>
            
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <span class="nav-icon">📊</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link">
                            <span class="nav-icon">📦</span>
                            Kelola Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="orders.php" class="nav-link">
                            <span class="nav-icon">🛒</span>
                            Kelola Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reviews.php" class="nav-link active">
                            <span class="nav-icon">⭐</span>
                            Kelola Review
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="nav-link">
                            <span class="nav-icon">👥</span>
                            Kelola User
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
                            <span class="nav-icon">📈</span>
                            Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link">
                            <span class="nav-icon">⚙️</span>
                            Pengaturan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link" target="_blank">
                            <span class="nav-icon">🌐</span>
                            Lihat Website
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Kelola Review</h1>
                <div class="user-info">
                    <span>Selamat datang, <strong><?= $_SESSION['user_name'] ?></strong></span>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Review</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-number"><?= $stats['pending'] ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-number"><?= $stats['approved'] ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">❌</div>
                    <div class="stat-number"><?= $stats['rejected'] ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">Daftar Review</div>
                <div class="section-content">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label>Cari</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama, produk, teks...">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">Semua</option>
                                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Urutkan</label>
                            <select name="sort">
                                <option value="created_at DESC" <?= $sort == 'created_at DESC' ? 'selected' : '' ?>>Terbaru</option>
                                <option value="created_at ASC" <?= $sort == 'created_at ASC' ? 'selected' : '' ?>>Terlama</option>
                                <option value="rating DESC" <?= $sort == 'rating DESC' ? 'selected' : '' ?>>Rating Tertinggi</option>
                                <option value="rating ASC" <?= $sort == 'rating ASC' ? 'selected' : '' ?>>Rating Terendah</option>
                            </select>
                        </div>
                        <button type="submit" class="btn">Filter</button>
                    </form>

                    <div style="overflow-x: auto;">
                        <table class="reviews-table">
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
                            <tbody id="reviews-tbody">
                                <?php foreach ($initial_reviews as $review): 
                                    $reviewTextFull = htmlspecialchars($review['review_text'] ?? '');
                                    $reviewTextShort = substr($reviewTextFull, 0, 100) . (strlen($reviewTextFull) > 100 ? '...' : '');
                                ?>
                                    <tr data-review-id="<?= $review['id'] ?>">
                                        <td><?= $review['id'] ?></td>
                                        <td><?= htmlspecialchars($review['nama_parfum'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($review['customer_name']) ?></td>
                                        <td><?= str_repeat('⭐', $review['rating']) ?></td>
                                        <td><?= htmlspecialchars($review['review_title'] ?? 'N/A') ?></td>
                                        <td class="review-text" title="<?= $reviewTextFull ?>"><?= $reviewTextShort ?></td>
                                        <td class="status-<?= $review['status'] ?>"><?= ucfirst($review['status']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($review['created_at'])) ?></td>
                                        <td class="actions">
                                            <?php if ($review['status'] !== 'approved'): ?>
                                                <button class="btn btn-approve approve-review" data-review-id="<?= $review['id'] ?>">✅</button>
                                            <?php endif; ?>
                                            <?php if ($review['status'] !== 'pending'): ?>
                                                <button class="btn btn-pending pending-review" data-review-id="<?= $review['id'] ?>">⏳</button>
                                            <?php endif; ?>
                                            <?php if ($review['status'] !== 'rejected'): ?>
                                                <button class="btn btn-reject reject-review" data-review-id="<?= $review['id'] ?>">❌</button>
                                            <?php endif; ?>
                                            <button class="btn btn-delete delete-review" data-review-id="<?= $review['id'] ?>">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="loading" class="loading-indicator">
                        ⏳ Memuat review...
                    </div>
                    <div id="no-more" class="no-more">
                        ✓ Semua review telah dimuat
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let offset = 5;
        let hasMoreReviews = true;
        let isLoading = false;
        const search = new URLSearchParams(window.location.search).get('search') || '';
        const status_filter = new URLSearchParams(window.location.search).get('status') || '';
        const sort = new URLSearchParams(window.location.search).get('sort') || 'created_at DESC';

        const tbody = document.getElementById('reviews-tbody');
        const loadingEl = document.getElementById('loading');
        const noMoreEl = document.getElementById('no-more');

        // Scroll event for infinite loading
        window.addEventListener('scroll', () => {
            if (isLoading || !hasMoreReviews) return;

            const scrollPosition = window.innerHeight + window.scrollY;
            const triggerPoint = document.body.offsetHeight - 300;

            if (scrollPosition >= triggerPoint) {
                loadMoreReviews();
            }
        });

        function loadMoreReviews() {
            if (isLoading || !hasMoreReviews) return;
            
            isLoading = true;
            loadingEl.style.display = 'block';

            fetch(`reviews_ajax.php?offset=${offset}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status_filter)}&sort=${encodeURIComponent(sort)}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        
                        if (!data.success) {
                            throw new Error(data.error || 'Failed to load');
                        }
                        
                        if (data.html) {
                            // Create temp container
                            const temp = document.createElement('tbody');
                            temp.innerHTML = data.html;
                            const rows = temp.querySelectorAll('tr[data-review-id]');
                            
                            // Append rows
                            rows.forEach(row => tbody.appendChild(row));
                            
                            hasMoreReviews = data.hasMore;
                            offset += 5;
                        }
                        
                        isLoading = false;
                        loadingEl.style.display = 'none';

                        if (!hasMoreReviews) {
                            noMoreEl.style.display = 'block';
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                        throw new Error('Invalid JSON response');
                    }
                })
                .catch(err => {
                    console.error('Load error:', err);
                    isLoading = false;
                    loadingEl.style.display = 'none';
                    showNotification('❌ Gagal memuat data: ' + err.message, 'error');
                });
        }

        // Action handlers
        document.addEventListener('click', e => {
            const target = e.target;
            
            if (target.classList.contains('approve-review') || 
                target.classList.contains('reject-review') || 
                target.classList.contains('pending-review')) {
                
                let action = '';
                if (target.classList.contains('approve-review')) action = 'approve';
                else if (target.classList.contains('reject-review')) action = 'reject';
                else if (target.classList.contains('pending-review')) action = 'pending';
                
                const reviewId = target.dataset.reviewId;
                
                // Disable button
                target.disabled = true;
                const originalText = target.innerHTML;
                target.innerHTML = '⏳';
                
                fetch('reviews.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}&review_id=${reviewId}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const row = document.querySelector(`tr[data-review-id="${reviewId}"]`);
                        
                        // Update status cell
                        const statusCell = row.cells[6];
                        statusCell.className = 'status-' + data.new_status;
                        statusCell.textContent = data.new_status.charAt(0).toUpperCase() + data.new_status.slice(1);
                        
                        // Update action buttons
                        const actionsCell = row.querySelector('.actions');
                        let newButtons = '';
                        
                        if (data.new_status !== 'approved') {
                            newButtons += '<button class="btn btn-approve approve-review" data-review-id="' + reviewId + '">✅</button>';
                        }
                        if (data.new_status !== 'pending') {
                            newButtons += '<button class="btn btn-pending pending-review" data-review-id="' + reviewId + '">⏳</button>';
                        }
                        if (data.new_status !== 'rejected') {
                            newButtons += '<button class="btn btn-reject reject-review" data-review-id="' + reviewId + '">❌</button>';
                        }
                        newButtons += '<button class="btn btn-delete delete-review" data-review-id="' + reviewId + '">🗑️</button>';
                        
                        actionsCell.innerHTML = newButtons;
                        
                        // Show notification
                        showNotification('✓ ' + data.message, 'success');
                    } else {
                        throw new Error(data.message || 'Operasi gagal');
                    }
                })
                .catch(err => {
                    console.error('Action error:', err);
                    showNotification('❌ ' + err.message, 'error');
                    target.disabled = false;
                    target.innerHTML = originalText;
                });
            } 
            
            else if (target.classList.contains('delete-review')) {
                const reviewId = target.dataset.reviewId;
                
                if (!confirm('⚠️ Yakin ingin menghapus review ini?')) {
                    return;
                }
                
                target.disabled = true;
                target.innerHTML = '⏳';
                
                fetch('reviews.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&review_id=${reviewId}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const row = document.querySelector(`tr[data-review-id="${reviewId}"]`);
                        row.classList.add('removing');
                        
                        setTimeout(() => {
                            row.remove();
                            showNotification('✓ ' + data.message, 'success');
                        }, 300);
                    } else {
                        throw new Error(data.message || 'Gagal menghapus');
                    }
                })
                .catch(err => {
                    console.error('Delete error:', err);
                    showNotification('❌ ' + err.message, 'error');
                    target.disabled = false;
                    target.innerHTML = '🗑️';
                });
            }
        });

        // Notification system
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
                color: white;
                padding: 1rem 2rem;
                border-radius: 5px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                z-index: 9999;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Add animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>