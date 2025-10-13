<?php
require_once 'config/database.php';

// Advanced search parameters
$search = $_GET['search'] ?? '';
$kategori = $_GET['kategori'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$min_rating = $_GET['min_rating'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'newest';
$brands = $_GET['brands'] ?? [];
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

// Search functionality
if ($search) {
    $sql .= " AND (p.nama_parfum LIKE ? OR p.brand LIKE ? OR p.tags LIKE ? OR p.scent_notes LIKE ? OR p.season LIKE ? OR p.occasion LIKE ? OR p.sillage LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
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

// Brand filter
if (!empty($brands)) {
    $brand_placeholders = str_repeat('?,', count($brands) - 1) . '?';
    $sql .= " AND p.brand IN ($brand_placeholders)";
    $params = array_merge($params, $brands);
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

// Get available brands for filter
$brand_sql = "SELECT DISTINCT brand FROM products ORDER BY brand";
$brand_stmt = $pdo->prepare($brand_sql);
$brand_stmt->execute();
$available_brands = $brand_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get seasons and occasions for filters
$season_sql = "SELECT DISTINCT season FROM products";
$season_stmt = $pdo->prepare($season_sql);
$season_stmt->execute();
$all_seasons_raw = $season_stmt->fetchAll(PDO::FETCH_COLUMN);
$all_seasons = array_unique(array_merge(...array_map(function($s) { return explode(',', $s); }, $all_seasons_raw)));

$occasion_sql = "SELECT DISTINCT occasion FROM products";
$occasion_stmt = $pdo->prepare($occasion_sql);
$occasion_stmt->execute();
$all_occasions_raw = $occasion_stmt->fetchAll(PDO::FETCH_COLUMN);
$all_occasions = array_unique(array_merge(...array_map(function($o) { return explode(',', $o); }, $all_occasions_raw)));

// Get sillage options
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
    <title>Toko Parfum Refill Premium - Kualitas Original, Harga Terjangkau</title>
    <meta name="description" content="Jual parfum refill berkualitas dengan aroma persis seperti original. Tom Ford, Dior, Chanel, dan brand premium lainnya.">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
            padding: 0.5rem;
            border-radius: 5px;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .cart-icon {
            position: relative;
            background: rgba(255,255,255,0.2);
            padding: 0.8rem;
            border-radius: 50%;
            transition: background 0.3s;
        }
        
        .cart-icon:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .hero {
            background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), 
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 400"><defs><linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" style="stop-color:%23667eea;stop-opacity:1" /><stop offset="100%" style="stop-color:%23764ba2;stop-opacity:1" /></linearGradient></defs><rect fill="url(%23grad1)" width="1000" height="400"/></svg>');
            color: white;
            text-align: center;
            padding: 4rem 0;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 2rem;
        }
        
        .hero-stat {
            text-align: center;
        }
        
        .hero-stat .number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        
        .hero-stat .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .search-section {
            background: white;
            padding: 2rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .search-form {
            display: grid;
            gap: 1rem;
        }
        
        .search-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-select, .filter-input {
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            background: white;
        }
        
        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .filter-checkbox:hover {
            background: #e9ecef;
        }
        
        .filter-checkbox input {
            margin: 0;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .products {
            padding: 3rem 0;
        }
        
        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .products-title {
            font-size: 2.5rem;
            color: #333;
        }
        
        .products-info {
            display: flex;
            gap: 2rem;
            align-items: center;
            font-size: 0.9rem;
            color: #666;
        }
        
        .sort-options {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }
        
        .product-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 280px;
            position: relative;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-badges {
            position: absolute;
            top: 15px;
            left: 15px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-discount {
            background: #e74c3c;
            color: white;
        }
        
        .badge-new {
            background: #27ae60;
            color: white;
        }
        
        .badge-bestseller {
            background: #f39c12;
            color: white;
        }
        
        .wishlist-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            font-size: 1.2rem;
        }
        
        .wishlist-btn:hover {
            background: white;
            transform: scale(1.1);
        }
        
        .product-info {
            padding: 1.5rem;
        }
        
        .product-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            color: #333;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
        }
        
        .stars {
            color: #ffd700;
            font-size: 0.9rem;
        }
        
        .rating-text {
            font-size: 0.85rem;
            color: #666;
        }
        
        .product-price {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
        }
        
        .current-price {
            font-size: 1.4rem;
            color: #e74c3c;
            font-weight: bold;
        }
        
        .original-price {
            font-size: 1rem;
            color: #999;
            text-decoration: line-through;
        }
        
        .discount-percentage {
            background: #e74c3c;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 0.8rem;
            color: #666;
        }
        
        .product-actions {
            display: flex;
            gap: 0.8rem;
        }
        
        .add-to-cart {
            flex: 1;
            background: #27ae60;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .add-to-cart:hover {
            background: #229954;
            transform: translateY(-1px);
        }
        
        .add-to-cart:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        
        .view-detail {
            background: #667eea;
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .view-detail:hover {
            background: #5a67d8;
        }
        
        .no-products {
            text-align: center;
            padding: 4rem 0;
            color: #666;
        }
        
        .no-products h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .trending-searches {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
        }
        
        .trending-title {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .trending-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .trending-tag {
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            color: #667eea;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .trending-tag:hover {
            background: #667eea;
            color: white;
        }
        
        footer {
            background: #2c3e50;
            color: white;
            padding: 3rem 0 1rem;
            margin-top: 4rem;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-section h3 {
            margin-bottom: 1rem;
            color: #ecf0f1;
        }
        
        .footer-section p, .footer-section a {
            color: #bdc3c7;
            text-decoration: none;
            line-height: 1.8;
        }
        
        .footer-section a:hover {
            color: #ecf0f1;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #34495e;
            color: #bdc3c7;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                min-width: unset;
            }
            
            .nav-links {
                gap: 1rem;
            }
            
            .products-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .product-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">
                <span>🌸</span> Parfum Refill Premium
            </div>
            <div class="nav-links">
                <a href="index.php">Beranda</a>
                <?php if (isLoggedIn()): ?>
                    <a href="profile.php">Profil</a>
                    <a href="orders.php">Pesanan</a>
                    <a href="wishlist.php">Wishlist</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Daftar</a>
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

    <section class="hero">
        <div class="container">
            <h1>Parfum Refill Premium</h1>
            <p>Kualitas Original, Aroma Persis Sama, Harga Terjangkau - Garansi 100% Puas atau Uang Kembali</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="number">1000+</span>
                    <span class="label">Pelanggan Puas</span>
                </div>
                <div class="hero-stat">
                    <span class="number">50+</span>
                    <span class="label">Brand Premium</span>
                </div>
                <div class="hero-stat">
                    <span class="number">4.8★</span>
                    <span class="label">Rating Toko</span>
                </div>
            </div>
        </div>
    </section>

    <section class="search-section">
        <div class="container">
            <form class="search-form" method="GET" action="">
                <div class="search-row">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Cari parfum, brand, aroma, musim, kesempatan (contoh: Tom Ford, woody, fresh, spring, evening)..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn">🔍 Cari</button>
                </div>
                
                <div class="search-row">
                    <div class="filter-group">
                        <label>Kategori:</label>
                        <select name="kategori" class="filter-select">
                            <option value="">Semua</option>
                            <option value="pria" <?= $kategori == 'pria' ? 'selected' : '' ?>>Pria</option>
                            <option value="wanita" <?= $kategori == 'wanita' ? 'selected' : '' ?>>Wanita</option>
                            <option value="unisex" <?= $kategori == 'unisex' ? 'selected' : '' ?>>Unisex</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Harga:</label>
                        <input type="number" name="min_price" class="filter-input" 
                               placeholder="Min" value="<?= htmlspecialchars($min_price) ?>" style="width: 100px;">
                        <span>-</span>
                        <input type="number" name="max_price" class="filter-input" 
                               placeholder="Max" value="<?= htmlspecialchars($max_price) ?>" style="width: 100px;">
                    </div>
                    
                    <div class="filter-group">
                        <label>Rating min:</label>
                        <select name="min_rating" class="filter-select">
                            <option value="">Semua</option>
                            <option value="4" <?= $min_rating == '4' ? 'selected' : '' ?>>4+ ⭐</option>
                            <option value="3" <?= $min_rating == '3' ? 'selected' : '' ?>>3+ ⭐</option>
                        </select>
                    </div>
                    
                    <label class="filter-checkbox">
                        <input type="checkbox" name="discount_only" <?= $discount_only ? 'checked' : '' ?>>
                        <span>Hanya Diskon</span>
                    </label>
                </div>
                
                <?php if (!empty($available_brands)): ?>
                <div class="search-row">
                    <div class="filter-group">
                        <label>Brand:</label>
                        <?php foreach (array_slice($available_brands, 0, 6) as $brand): ?>
                            <label class="filter-checkbox">
                                <input type="checkbox" name="brands[]" value="<?= $brand ?>" 
                                       <?= in_array($brand, $brands) ? 'checked' : '' ?>>
                                <span><?= $brand ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="search-row">
                    <div class="filter-group">
                        <label>Musim:</label>
                        <?php 
                        $season_options = ['spring' => 'Musim Semi', 'summer' => 'Musim Panas', 'fall' => 'Musim Gugur', 'winter' => 'Musim Dingin'];
                        foreach ($season_options as $key => $label): ?>
                            <label class="filter-checkbox">
                                <input type="checkbox" name="seasons[]" value="<?= $key ?>" 
                                       <?= in_array($key, $seasons) ? 'checked' : '' ?>>
                                <span><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="filter-group">
                        <label>Kesempatan:</label>
                        <?php 
                        $occasion_options = ['casual' => 'Santai', 'office' => 'Kantor', 'evening' => 'Malam', 'special' => 'Spesial'];
                        foreach ($occasion_options as $key => $label): ?>
                            <label class="filter-checkbox">
                                <input type="checkbox" name="occasions[]" value="<?= $key ?>" 
                                       <?= in_array($key, $occasions) ? 'checked' : '' ?>>
                                <span><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="filter-group">
                        <label>Sillage:</label>
                        <select name="sillage" class="filter-select">
                            <option value="">Semua</option>
                            <?php foreach ($available_sillage as $sil): ?>
                                <option value="<?= $sil ?>" <?= $sillage == $sil ? 'selected' : '' ?>><?= ucfirst($sil) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Ketahanan:</label>
                        <input type="number" name="min_longevity" class="filter-input" 
                               placeholder="Min jam" value="<?= htmlspecialchars($min_longevity) ?>" style="width: 80px;">
                        <span>-</span>
                        <input type="number" name="max_longevity" class="filter-input" 
                               placeholder="Max jam" value="<?= htmlspecialchars($max_longevity) ?>" style="width: 80px;">
                    </div>
                </div>
                
                <div class="search-row">
                    <a href="index.php" class="btn btn-secondary">Reset Filter</a>
                </div>
            </form>
            
            <div class="trending-searches">
                <div class="trending-title">🔥 Pencarian Trending:</div>
                <div class="trending-tags">
                    <a href="?search=tom+ford" class="trending-tag">Tom Ford</a>
                    <a href="?search=dior" class="trending-tag">Dior</a>
                    <a href="?search=chanel" class="trending-tag">Chanel</a>
                    <a href="?search=woody" class="trending-tag">Woody</a>
                    <a href="?search=fresh" class="trending-tag">Fresh</a>
                    <a href="?search=oriental" class="trending-tag">Oriental</a>
                    <a href="?search=spring" class="trending-tag">Musim Semi</a>
                    <a href="?search=evening" class="trending-tag">Malam Hari</a>
                    <a href="?discount_only=1" class="trending-tag">Sedang Diskon</a>
                </div>
            </div>
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

    <section class="products">
        <div class="container">
            <div class="products-header">
                <div>
                    <h2 class="products-title">Koleksi Parfum Refill</h2>
                    <div class="products-info">
                        <span><?= count($products) ?> produk ditemukan</span>
                        <?php if ($search): ?>
                            <span>untuk "<?= htmlspecialchars($search) ?>"</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="sort-options">
                    <label>Urutkan:</label>
                    <select name="sort_by" class="filter-select" onchange="updateSort(this.value)">
                        <option value="newest" <?= $sort_by == 'newest' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="popular" <?= $sort_by == 'popular' ? 'selected' : '' ?>>Terpopuler</option>
                        <option value="rating" <?= $sort_by == 'rating' ? 'selected' : '' ?>>Rating Tertinggi</option>
                        <option value="price_low" <?= $sort_by == 'price_low' ? 'selected' : '' ?>>Harga Terendah</option>
                        <option value="price_high" <?= $sort_by == 'price_high' ? 'selected' : '' ?>>Harga Tertinggi</option>
                        <option value="discount" <?= $sort_by == 'discount' ? 'selected' : '' ?>>Diskon Terbesar</option>
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
                            <a href="product_detail.php?id=<?= $product['id'] ?>">
                                <div class="product-image">
                                    <?php if ($primary_image): ?>
                                        <img src="<?= htmlspecialchars($primary_image) ?>" 
                                             alt="<?= htmlspecialchars($product['nama_parfum']) ?>"
                                             loading="lazy"
                                             onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 200%22><rect fill=%22%23f8f9fa%22 width=%22200%22 height=%22200%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2240%22>🧴</text></svg>';">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: linear-gradient(45deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">🧴</div>
                                    <?php endif; ?>
                                    
                                    <div class="product-badges">
                                        <?php if ($product['discount_percentage'] > 0): ?>
                                            <span class="badge badge-discount">-<?= $product['discount_percentage'] ?>%</span>
                                        <?php endif; ?>
                                        <?php if ($is_new): ?>
                                            <span class="badge badge-new">Baru</span>
                                        <?php endif; ?>
                                        <?php if ($is_bestseller): ?>
                                            <span class="badge badge-bestseller">Terlaris</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button class="wishlist-btn" onclick="event.preventDefault(); toggleWishlist(<?= $product['id'] ?>)">
                                        ♡
                                    </button>
                                </div>
                            </a>
                            
                            <div class="product-info">
                                <h3 class="product-name"><?= htmlspecialchars($product['nama_parfum']) ?></h3>
                                
                                <div class="product-rating">
                                    <div class="stars"><?= renderStars($product['rating_average']) ?></div>
                                    <span class="rating-text"><?= number_format($product['rating_average'], 1) ?> (<?= $product['total_reviews'] ?>)</span>
                                </div>
                                
                                <div class="product-price">
                                    <span class="current-price"><?= formatRupiah($product['final_price']) ?></span>
                                    <?php if ($product['discount_percentage'] > 0 && $product['display_original_price']): ?>
                                        <span class="original-price"><?= formatRupiah($product['display_original_price']) ?></span>
                                        <span class="discount-percentage">-<?= $product['discount_percentage'] ?>%</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-meta">
                                    <span>📦 <?= $product['total_sold'] ?> terjual</span>
                                    <span>👁️ <?= $product['views_today'] ?> dilihat</span>
                                </div>
                                
                                <div class="product-actions">
                                    <a href="product_detail.php?id=<?= $product['id'] ?>" class="view-detail">
                                        Lihat Detail
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-products">
                    <h3>🔍 Tidak ada produk ditemukan</h3>
                    <p>Coba ubah kata kunci atau filter pencarian Anda</p>
                    <div style="margin-top: 2rem;">
                        <a href="index.php" class="btn">Lihat Semua Produk</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>🌸 Parfum Refill Premium</h3>
                    <p>Toko parfum refill terpercaya dengan kualitas original dan harga terjangkau. Kami menjamin 100% kepuasan pelanggan.</p>
                </div>
                <div class="footer-section">
                    <h3>Layanan Pelanggan</h3>
                    <p><a href="tel:+6281234567890">📞 +62812-3456-7890</a></p>
                    <p><a href="mailto:cs@parfumrefill.com">✉️ cs@parfumrefill.com</a></p>
                    <p>🕒 Senin - Sabtu: 09:00 - 21:00</p>
                </div>
                <div class="footer-section">
                    <h3>Jaminan Kualitas</h3>
                    <p>✅ 100% Aroma Original</p>
                    <p>🛡️ Garansi Puas atau Uang Kembali</p>
                    <p>🚚 Gratis Ongkir min. Rp 500K</p>
                    <p>⭐ Rating 4.8/5 dari 1000+ review</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Parfum Refill Premium. Semua hak dilindungi undang-undang.</p>
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
            alert('Fitur wishlist akan segera tersedia!');
        }
        
        // Auto-submit form on filter change
        document.addEventListener('DOMContentLoaded', function() {
            const filterInputs = document.querySelectorAll('select[name="kategori"], select[name="min_rating"], select[name="sillage"], input[name="min_longevity"], input[name="max_longevity"], input[name="discount_only"]');
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    this.form.submit();
                });
            });
            
            // Brand checkboxes
            const brandCheckboxes = document.querySelectorAll('input[name="brands[]"]');
            brandCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    setTimeout(() => this.form.submit(), 100);
                });
            });

            // Season and occasion checkboxes
            const seasonCheckboxes = document.querySelectorAll('input[name="seasons[]"]');
            seasonCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    setTimeout(() => this.form.submit(), 100);
                });
            });

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