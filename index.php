<?php
require_once 'config/database.php';

// Advanced search parameters
$search = $_GET['search'] ?? '';
$kategori = $_GET['kategori'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$min_rating = $_GET['min_rating'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'newest';
$discount_only = isset($_GET['discount_only']);
$seasons = $_GET['seasons'] ?? [];
$occasions = $_GET['occasions'] ?? [];
$sillage = $_GET['sillage'] ?? '';
$min_longevity = $_GET['min_longevity'] ?? '';
$max_longevity = $_GET['max_longevity'] ?? '';

// Advanced SQL query building
$sql = "SELECT p.*, 
        GROUP_CONCAT(DISTINCT pi.image_url ORDER BY pi.sort_order SEPARATOR '|') as all_images,
        CASE 
            WHEN p.discount_percentage > 0 AND p.original_price IS NOT NULL 
            THEN p.original_price 
            ELSE p.harga 
        END as display_original_price,
        CASE 
            WHEN p.discount_percentage > 0 AND p.original_price IS NOT NULL
            THEN ROUND(p.original_price * (1 - p.discount_percentage/100), 0)
            ELSE p.harga
        END as final_price,
        (SELECT COUNT(*) FROM product_views WHERE product_id = p.id AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as views_today
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id 
        WHERE 1=1";

$params = [];

// Get statistics for hero banner
$stats_sql = "SELECT 
    (SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL AND status != 'cancelled') as total_customers,
    (SELECT COUNT(DISTINCT p.id) FROM products p WHERE p.stok > 0) as total_brands,
    (SELECT ROUND(AVG(rating_average), 1) FROM products WHERE rating_average > 0) as store_rating
";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Set default values if no data
$total_customers = $stats['total_customers'] ?? 0;
$total_brands = $stats['total_brands'] ?? 0;
$store_rating = $stats['store_rating'] ?? 0;

// Search functionality
if ($search) {
    $sql .= " AND (p.nama_parfum LIKE ? OR p.tags LIKE ? OR p.scent_notes LIKE ? OR p.season LIKE ? OR p.occasion LIKE ? OR p.sillage LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
}

// Category filter
if ($kategori) {
    $sql .= " AND p.kategori = ?";
    $params[] = $kategori;
}

// Price range filter
if ($min_price) {
    $sql .= " AND p.harga >= ?";
    $params[] = $min_price;
}
if ($max_price) {
    $sql .= " AND p.harga <= ?";
    $params[] = $max_price;
}

// Rating filter
if ($min_rating) {
    $sql .= " AND p.rating_average >= ?";
    $params[] = $min_rating;
}

// Discount only filter
if ($discount_only) {
    $sql .= " AND p.discount_percentage > 0";
}

// Season filter (multiple)
if (!empty($seasons)) {
    $sql .= " AND (0";
    foreach ($seasons as $season) {
        $sql .= " OR FIND_IN_SET(?, p.season)";
        $params[] = $season;
    }
    $sql .= ")";
}

// Occasion filter (multiple)
if (!empty($occasions)) {
    $sql .= " AND (0";
    foreach ($occasions as $occasion) {
        $sql .= " OR FIND_IN_SET(?, p.occasion)";
        $params[] = $occasion;
    }
    $sql .= ")";
}

// Sillage filter
if ($sillage) {
    $sql .= " AND p.sillage = ?";
    $params[] = $sillage;
}

// Longevity range filter
if ($min_longevity) {
    $sql .= " AND p.longevity_hours >= ?";
    $params[] = $min_longevity;
}
if ($max_longevity) {
    $sql .= " AND p.longevity_hours <= ?";
    $params[] = $max_longevity;
}

// Group by for JOIN
$sql .= " GROUP BY p.id";

// Sorting
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY final_price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY final_price DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY p.rating_average DESC, p.total_reviews DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY p.total_sold DESC";
        break;
    case 'discount':
        $sql .= " ORDER BY p.discount_percentage DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get available sillage options
$sillage_sql = "SELECT DISTINCT sillage FROM products ORDER BY FIELD(sillage, 'intimate', 'moderate', 'strong', 'enormous')";
$sillage_stmt = $pdo->prepare($sillage_sql);
$sillage_stmt->execute();
$available_sillage = $sillage_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get cart count
$cart_count = 0;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM cart WHERE user_id = ?");
    $stmt->execute([getUserId()]);
} else {
    $stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM cart WHERE session_id = ?");
    $stmt->execute([$_SESSION['session_id']]);
}
$cart_result = $stmt->fetch();
$cart_count = $cart_result['total'] ?? 0;

// Log search if there's a query
if ($search) {
    $log_sql = "INSERT INTO search_logs (user_id, search_query, results_count, filters_used, ip_address) VALUES (?, ?, ?, ?, ?)";
    $filters = json_encode($_GET);
    $user_id = isLoggedIn() ? getUserId() : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pdo->prepare($log_sql)->execute([$user_id, $search, count($products), $filters, $ip]);
}

function renderStars($rating, $size = 'sm') {
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5;
    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
    
    $stars = str_repeat('⭐', $full_stars);
    if ($half_star) $stars .= '⭐';
    $stars .= str_repeat('☆', $empty_stars);
    
    return $stars;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parfum Refill Premium - UniqThings</title>
    <meta name="description" content="Jual parfum refill berkualitas dengan aroma persis seperti original. Tom Ford, Dior, Chanel, dan brand premium lainnya.">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/logo.png">
    <link rel="shortcut icon" type="image/png" href="img/logo.png">
    <link rel="apple-touch-icon" href="img/logo.png">
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
            background-color: #fff;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        .top-bar {
            background: #f8f8f8;
            padding: 8px 0;
            font-size: 12px;
            text-align: center;
            color: #666;
        }

        .logo {
    font-size: 24px;
    font-weight: 300;
    letter-spacing: 2px;
    color: #2c2c2c;
    text-transform: uppercase;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.logo-img {
    height: 50px;
    width: auto;
    object-fit: contain;
}

@media (max-width: 768px) {
    .logo-img {
        height: 40px;
    }
}
        
        header {
            background: #fff;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: 2px;
            color: #2c2c2c;
            text-transform: uppercase;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 35px;
            align-items: center;
        }
        
        .nav-links a {
            color: #2c2c2c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: color 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .nav-links a:hover {
            color: #c41e3a;
        }
        
        .cart-icon {
            position: relative;
            cursor: pointer;
            font-size: 20px;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #c41e3a;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, #ffeef5 0%, #fff 100%);
            padding: 80px 0;
            text-align: center;
        }
        
        .hero-content h1 {
            font-size: 48px;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 20px;
            color: #2c2c2c;
        }
        
        .hero-content p {
            font-size: 18px;
            color: #666;
            max-width: 600px;
            margin: 0 auto 30px;
            line-height: 1.8;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-top: 50px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 300;
            color: #c41e3a;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Search Section */
        .search-section {
            background: #fff;
            padding: 40px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .search-bar {
            max-width: 800px;
            margin: 0 auto 30px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 1px solid #e0e0e0;
            border-radius: 0;
            font-size: 14px;
            background: #fafafa;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #c41e3a;
            background: #fff;
        }
        
        .search-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #666;
        }
        
        .filters-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-select, .filter-input {
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            background: #fff;
            font-size: 13px;
            color: #2c2c2c;
        }
        
        .filter-checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: #fafafa;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-checkbox-group:hover {
            background: #f0f0f0;
        }
        
        .filter-checkbox-group input {
            margin: 0;
        }
        
        .filter-checkbox-group label {
            font-size: 12px;
            cursor: pointer;
            margin: 0;
            text-transform: none;
        }
        
        .btn-primary {
            background: #c41e3a;
            color: white;
            padding: 12px 30px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary:hover {
            background: #a01628;
        }
        
        .btn-secondary {
            background: transparent;
            color: #666;
            padding: 12px 30px;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            border-color: #2c2c2c;
            color: #2c2c2c;
        }
        
        /* Trending Tags */
        .trending-section {
            text-align: center;
            margin-top: 30px;
        }
        
        .trending-title {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        
        .trending-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        
        .trending-tag {
            padding: 8px 20px;
            background: #fff;
            border: 1px solid #e0e0e0;
            text-decoration: none;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .trending-tag:hover {
            border-color: #c41e3a;
            color: #c41e3a;
        }
        
        /* Products Section */
        .products-section {
            padding: 60px 0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 1px;
            color: #2c2c2c;
        }
        
        .product-count {
            font-size: 13px;
            color: #999;
        }
        
        .sort-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .sort-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .product-card {
            background: #fff;
            transition: all 0.3s;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image-wrapper {
            position: relative;
            padding-bottom: 130%;
            background: #fafafa;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.08);
        }
        
        .product-badges {
            position: absolute;
            top: 15px;
            left: 15px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            z-index: 10;
        }
        
        .badge {
            padding: 5px 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }
        
        .badge-discount {
            background: #c41e3a;
        }
        
        .badge-new {
            background: #2c2c2c;
        }
        
        .badge-bestseller {
            background: #d4af37;
        }
        
        .wishlist-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 10;
            transition: all 0.3s;
        }
        
        .wishlist-btn:hover {
            background: #c41e3a;
            color: white;
        }
        
        .product-info {
            padding: 0 10px;
        }
        
        .product-name {
            font-size: 15px;
            font-weight: 400;
            margin-bottom: 10px;
            color: #2c2c2c;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 44px;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .stars {
            color: #d4af37;
            font-size: 12px;
        }
        
        .rating-text {
            font-size: 12px;
            color: #999;
        }
        
        .product-price {
            margin-bottom: 15px;
        }
        
        .price-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .current-price {
            font-size: 20px;
            font-weight: 400;
            color: #c41e3a;
        }
        
        .original-price {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
        }
        
        .discount-badge {
            background: #fff0f0;
            color: #c41e3a;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 11px;
            color: #999;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-view {
            flex: 1;
            background: #2c2c2c;
            color: white;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: block;
        }
        
        .btn-view:hover {
            background: #c41e3a;
        }
        
        /* No Products */
        .no-products {
            text-align: center;
            padding: 80px 20px;
        }
        
        .no-products h3 {
            font-size: 24px;
            font-weight: 300;
            margin-bottom: 15px;
            color: #2c2c2c;
        }
        
        .no-products p {
            font-size: 14px;
            color: #999;
            margin-bottom: 30px;
        }
        
        /* Footer */
        footer {
            background: #f8f8f8;
            padding: 60px 0 30px;
            margin-top: 80px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-section h3 {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            color: #2c2c2c;
        }
        
        .footer-section p,
        .footer-section a {
            font-size: 13px;
            color: #666;
            text-decoration: none;
            line-height: 2;
            display: block;
        }
        
        .footer-section a:hover {
            color: #c41e3a;
        }
        
        .footer-bottom {
            border-top: 1px solid #e0e0e0;
            padding-top: 30px;
            text-align: center;
        }
        
        .footer-bottom p {
            font-size: 12px;
            color: #999;
        }
        
        /* Alert */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-left: 3px solid;
            font-size: 14px;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #22c55e;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #ef4444;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 32px;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 30px;
            }
            
            .nav-links {
                gap: 15px;
            }
            
            .nav-links a {
                font-size: 12px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        🚚 Gratis Ongkir Min. Rp 500K | 💯 Garansi Puas atau Uang Kembali
    </div>

<!-- Header -->
<header>
    <nav class="container">
        <a href="index.php" class="logo">
            <img src="img/logo.png" alt="UniqThings Parfum Refill" class="logo-img">
        </a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <?php if (isLoggedIn()): ?>
                <a href="profile.php">Account</a>
                <a href="orders.php">Orders</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
            <a href="cart.php" class="cart-icon">
                🛒
                <?php if ($cart_count > 0): ?>
                    <span class="cart-count"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>
        </div>
    </nav>
</header>

    <!-- Hero Banner -->
    <section class="hero-banner">
        <div class="container">
            <div class="hero-content">
                <h1>UniqThings</h1>
                <p>Rasakan sensasi wangi elegan dari parfume refill kami — tahan lama dan memikat sepanjang hari.</p>
                
               <div class="hero-stats">
    <div class="stat-item">
        <span class="stat-number"><?= number_format($total_customers) ?>+</span>
        <span class="stat-label">Happy Customers</span>
    </div>
    <div class="stat-item">
        <span class="stat-number"><?= $total_brands ?>+</span>
        <span class="stat-label">Premium Products</span>
    </div>
    <div class="stat-item">
        <span class="stat-number"><?= number_format($store_rating, 1) ?>★</span>
        <span class="stat-label">Store Rating</span>
    </div>
</div>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <section class="search-section">
        <div class="container">
            <form method="GET" action="">
                <div class="search-bar">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Search for perfumes, brands, notes..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="search-btn">🔍</button>
                </div>
                
                <div class="filters-wrapper">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Category:</label>
                            <select name="kategori" class="filter-select">
                                <option value="">All</option>
                                <option value="pria" <?= $kategori == 'pria' ? 'selected' : '' ?>>Men</option>
                                <option value="wanita" <?= $kategori == 'wanita' ? 'selected' : '' ?>>Women</option>
                                <option value="unisex" <?= $kategori == 'unisex' ? 'selected' : '' ?>>Unisex</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Price:</label>
                            <input type="number" name="min_price" class="filter-input" 
                                   placeholder="Min" value="<?= htmlspecialchars($min_price) ?>" style="width: 90px;">
                            <span>-</span>
                            <input type="number" name="max_price" class="filter-input" 
                                   placeholder="Max" value="<?= htmlspecialchars($max_price) ?>" style="width: 90px;">
                        </div>
                        
                        <div class="filter-group">
                            <label>Rating:</label>
                            <select name="min_rating" class="filter-select">
                                <option value="">All</option>
                                <option value="4" <?= $min_rating == '4' ? 'selected' : '' ?>>4+ ⭐</option>
                                <option value="3" <?= $min_rating == '3' ? 'selected' : '' ?>>3+ ⭐</option>
                            </select>
                        </div>
                        
                        <div class="filter-checkbox-group">
                            <input type="checkbox" name="discount_only" id="discount" <?= $discount_only ? 'checked' : '' ?>>
                            <label for="discount">On Sale</label>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Season:</label>
                            <?php 
                            $season_options = ['spring' => 'Spring', 'summer' => 'Summer', 'fall' => 'Fall', 'winter' => 'Winter'];
                            foreach ($season_options as $key => $label): ?>
                                <div class="filter-checkbox-group">
                                    <input type="checkbox" name="seasons[]" value="<?= $key ?>" id="season_<?= $key ?>"
                                           <?= in_array($key, $seasons) ? 'checked' : '' ?>>
                                    <label for="season_<?= $key ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="filter-group">
                            <label>Occasion:</label>
                            <?php 
                            $occasion_options = ['casual' => 'Casual', 'office' => 'Office', 'evening' => 'Evening', 'special' => 'Special'];
                            foreach ($occasion_options as $key => $label): ?>
                                <div class="filter-checkbox-group">
                                    <input type="checkbox" name="occasions[]" value="<?= $key ?>" id="occasion_<?= $key ?>"
                                           <?= in_array($key, $occasions) ? 'checked' : '' ?>>
                                    <label for="occasion_<?= $key ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Sillage:</label>
                            <select name="sillage" class="filter-select">
                                <option value="">All</option>
                                <?php foreach ($available_sillage as $sil): ?>
                                    <option value="<?= $sil ?>" <?= $sillage == $sil ? 'selected' : '' ?>><?= ucfirst($sil) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Longevity:</label>
                            <input type="number" name="min_longevity" class="filter-input" 
                                   placeholder="Min hrs" value="<?= htmlspecialchars($min_longevity) ?>" style="width: 80px;">
                            <span>-</span>
                            <input type="number" name="max_longevity" class="filter-input" 
                                   placeholder="Max hrs" value="<?= htmlspecialchars($max_longevity) ?>" style="width: 80px;">
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="index.php" class="btn-secondary">Reset</a>
                    </div>
                </div>
                
                <div class="trending-section">
                    <div class="trending-title">Popular Searches</div>
                    <div class="trending-tags">
                        <a href="?search=tom+ford" class="trending-tag">Tom Ford</a>
                        <a href="?search=dior" class="trending-tag">Dior</a>
                        <a href="?search=chanel" class="trending-tag">Chanel</a>
                        <a href="?search=woody" class="trending-tag">Woody</a>
                        <a href="?search=fresh" class="trending-tag">Fresh</a>
                        <a href="?search=oriental" class="trending-tag">Oriental</a>
                        <a href="?search=spring" class="trending-tag">Spring</a>
                        <a href="?search=evening" class="trending-tag">Evening</a>
                        <a href="?discount_only=1" class="trending-tag">On Sale</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="container">
            <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                <?= $_SESSION['message'] ?>
            </div>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Products Section -->
    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Our Collection</h2>
                    <p class="product-count">
                        <?= count($products) ?> products found
                        <?php if ($search): ?>
                            for "<?= htmlspecialchars($search) ?>"
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="sort-wrapper">
                    <span class="sort-label">Sort by:</span>
                    <select class="filter-select" name="sort_by" onchange="updateSort(this.value)">
                        <option value="newest" <?= $sort_by == 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="popular" <?= $sort_by == 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="rating" <?= $sort_by == 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                        <option value="price_low" <?= $sort_by == 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort_by == 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="discount" <?= $sort_by == 'discount' ? 'selected' : '' ?>>Biggest Discount</option>
                    </select>
                </div>
            </div>
            
            <?php if (!empty($products)): ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $images = $product['all_images'] ? explode('|', $product['all_images']) : [];
                        $primary_image = $images[0] ?? $product['gambar'] ?? '';
                        $is_new = (strtotime($product['created_at']) > strtotime('-7 days'));
                        $is_bestseller = $product['total_sold'] > 100;
                        ?>
                        <div class="product-card">
                            <div class="product-image-wrapper">
                                <?php if ($primary_image): ?>
                                    <img src="<?= htmlspecialchars($primary_image) ?>" 
                                         alt="<?= htmlspecialchars($product['nama_parfum']) ?>"
                                         class="product-image"
                                         loading="lazy"
                                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 500%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22400%22 height=%22500%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2280%22%3E🧴%3C/text%3E%3C/svg%3E';">
                                <?php else: ?>
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 500'%3E%3Crect fill='%23f0f0f0' width='400' height='500'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' font-size='80'%3E🧴%3C/text%3E%3C/svg%3E" 
                                         alt="<?= htmlspecialchars($product['nama_parfum']) ?>"
                                         class="product-image">
                                <?php endif; ?>
                                
                                <div class="product-badges">
                                    <?php if ($product['discount_percentage'] > 0): ?>
                                        <span class="badge badge-discount">-<?= $product['discount_percentage'] ?>%</span>
                                    <?php endif; ?>
                                    <?php if ($is_new): ?>
                                        <span class="badge badge-new">New</span>
                                    <?php endif; ?>
                                    <?php if ($is_bestseller): ?>
                                        <span class="badge badge-bestseller">Bestseller</span>
                                    <?php endif; ?>
                                </div>
                                
                                <button class="wishlist-btn" onclick="toggleWishlist(<?= $product['id'] ?>)">♡</button>
                            </div>
                            
                            <div class="product-info">
                                <h3 class="product-name"><?= htmlspecialchars($product['nama_parfum']) ?></h3>
                                
                                <div class="product-rating">
                                    <div class="stars"><?= renderStars($product['rating_average']) ?></div>
                                    <span class="rating-text"><?= number_format($product['rating_average'], 1) ?> (<?= $product['total_reviews'] ?>)</span>
                                </div>
                                
                                <div class="product-price">
                                    <div class="price-wrapper">
                                        <span class="current-price"><?= formatRupiah($product['final_price']) ?></span>
                                        <?php if ($product['discount_percentage'] > 0 && $product['display_original_price']): ?>
                                            <span class="original-price"><?= formatRupiah($product['display_original_price']) ?></span>
                                            <span class="discount-badge">-<?= $product['discount_percentage'] ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="product-meta">
                                    <span>📦 <?= $product['total_sold'] ?> sold</span>
                                    <span>👁️ <?= $product['views_today'] ?> views</span>
                                </div>
                                
                                <div class="product-actions">
                                    <a href="product_detail.php?id=<?= $product['id'] ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-products">
                    <h3>🔍 No products found</h3>
                    <p>Try changing your search keywords or filters</p>
                    <div style="margin-top: 2rem;">
                        <a href="index.php" class="btn-primary">View All Products</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About Us</h3>
                    <p>Premium refill perfumes with authentic quality and affordable prices. 100% customer satisfaction guaranteed.</p>
                </div>
                <div class="footer-section">
                    <h3>Customer Service</h3>
                    <a href="tel:+6281234567890">📞 +62812-3456-7890</a>
                    <a href="mailto:cs@parfumrefill.com">✉️ cs@parfumrefill.com</a>
                    <p>🕒 Mon - Sat: 09:00 - 21:00</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="#">Track Order</a>
                    <a href="#">Shipping Info</a>
                    <a href="#">Return Policy</a>
                    <a href="#">FAQ</a>
                </div>
                <div class="footer-section">
                    <h3>Our Guarantee</h3>
                    <p>✅ 100% Original Scent</p>
                    <p>🛡️ Money Back Guarantee</p>
                    <p>🚚 Free Shipping (min. Rp 500K)</p>
                    <p>⭐ 4.8/5 Rating from 1000+ reviews</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Parfum Refill Premium. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function updateSort(value) {
            const url = new URL(window.location);
            url.searchParams.set('sort_by', value);
            window.location = url;
        }
        
        function toggleWishlist(productId) {
            alert('Wishlist feature coming soon!');
        }
        
        // Auto-submit form on filter change
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('select[name="kategori"], select[name="min_rating"], select[name="sillage"]');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });
            
            // Discount checkbox
            const discountCheckbox = document.querySelector('input[name="discount_only"]');
            if (discountCheckbox) {
                discountCheckbox.addEventListener('change', function() {
                    this.form.submit();
                });
            }
            
            // Season checkboxes
            const seasonCheckboxes = document.querySelectorAll('input[name="seasons[]"]');
            seasonCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    setTimeout(() => this.form.submit(), 100);
                });
            });

            // Occasion checkboxes
            const occasionCheckboxes = document.querySelectorAll('input[name="occasions[]"]');
            occasionCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    setTimeout(() => this.form.submit(), 100);
                });
            });
        });
    </script>
</body>
</html>