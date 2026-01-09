<?php
session_start();
require_once 'config/database.php';

$product_id = (int)($_GET['id'] ?? 0);

if (!$product_id) {
    header("Location: index.php");
    exit();
}

// Fetch product details with volume options
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
        WHERE p.id = ?
        GROUP BY p.id";

$stmt = $pdo->prepare($sql);
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: index.php");
    exit();
}

// Fetch volume options
$volume_sql = "SELECT * FROM product_volume_prices WHERE product_id = ? AND is_available = 1 ORDER BY volume_ml ASC";
$volume_stmt = $pdo->prepare($volume_sql);
$volume_stmt->execute([$product_id]);
$volume_options = $volume_stmt->fetchAll();

// Parse images
$images = explode('|', $product['all_images'] ?? '');
$main_image = $images[0] ?? 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI2NjYyIvPjx0ZXh0IHg9IjIwMCIgeT0iMjAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiNjY2MiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj4rPC90ZXh0Pjwvc3ZnPg==';

// Fetch reviews (approved for all, plus all reviews for the current user)
$reviews_sql = "SELECT * FROM product_reviews WHERE product_id = ? AND (status = 'approved' OR user_id = ?) ORDER BY created_at DESC";
$reviews_stmt = $pdo->prepare($reviews_sql);
$user_id = isLoggedIn() ? getUserId() : null;
$reviews_stmt->execute([$product_id, $user_id]);
$reviews = $reviews_stmt->fetchAll();

// Log view
if (!isset($_SESSION['viewed_products'][$product_id])) {
    $log_sql = "INSERT INTO product_views (product_id, user_id, ip_address, session_id, viewed_at) VALUES (?, ?, ?, ?, NOW())";
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $session_id = session_id();
    $pdo->prepare($log_sql)->execute([$product_id, $user_id, $ip, $session_id]);
    $_SESSION['viewed_products'][$product_id] = true;
}

// Fetch similar products
$similar_sql = "SELECT p.*, 
                (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
                CASE 
                    WHEN p.discount_percentage > 0 AND p.original_price IS NOT NULL
                    THEN ROUND(p.original_price * (1 - p.discount_percentage/100), 0)
                    ELSE p.harga
                END as final_price
                FROM products p 
                WHERE p.id != ? AND p.kategori = ?
                ORDER BY RAND() LIMIT 4";
$similar_stmt = $pdo->prepare($similar_sql);
$similar_stmt->execute([$product_id, $product['kategori']]);
$similar_products = $similar_stmt->fetchAll();

function renderStars($rating) {
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5;
    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
    
    $color = '#d4af37'; // Gold color for all stars
    
    $stars = '';
    for ($i = 0; $i < $full_stars; $i++) {
        $stars .= "<span class='star full'>★</span>";
    }
    if ($half_star) {
        $stars .= "<span class='star half'>★</span>";
    }
    for ($i = 0; $i < $empty_stars; $i++) {
        $stars .= "<span class='star empty'>☆</span>";
    }
    
    return $stars;
}

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

$is_admin = isLoggedIn() && ($_SESSION['role'] ?? '' ) === 'admin';

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['nama_parfum']) ?> - Parfum Refill Premium</title>
    <meta name="description" content="<?= htmlspecialchars(substr($product['deskripsi'], 0, 160)) ?>">
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
        
        /* Top Bar */
        .top-bar {
            background: #f8f8f8;
            padding: 8px 0;
            font-size: 12px;
            text-align: center;
            color: #666;
        }
        
        /* Header */
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

        .back-button {
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
            margin: 30px 0 20px;
        }

        .back-button:hover {
            border-color: #2c2c2c;
            color: #2c2c2c;
        }

        .product-detail {
            padding: 30px 0 60px;
        }
        
        .product-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 60px;
        }
        
        .product-images {
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .main-image {
            width: 100%;
            height: 600px;
            object-fit: cover;
            background: #fafafa;
            margin-bottom: 20px;
        }
        
        .thumbnail-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
        }
        
        .thumbnail {
            width: 100%;
            height: 100px;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            background: #fafafa;
        }
        
        .thumbnail:hover {
            border-color: #c41e3a;
        }
        
        .product-info {
            padding-top: 20px;
        }
        
        .product-title {
            font-size: 32px;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 10px;
            color: #2c2c2c;
        }
        
        .product-brand {
            font-size: 14px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .stars {
            color: #d4af37;
            font-size: 16px;
        }
        
        .star {
            color: #d4af37;
            position: relative;
            display: inline-block;
        }

        .star.full {
            color: #d4af37;
        }

        .star.half {
            color: #ddd;
        }

        .star.half::before {
            content: '★';
            position: absolute;
            left: 0;
            top: 0;
            width: 50%;
            overflow: hidden;
            color: #d4af37;
        }

        .star.empty {
            color: #ddd;
        }

        .rating-text {
            color: #999;
            font-size: 13px;
        }
        
        .product-price {
            margin-bottom: 25px;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
            padding: 25px 0;
        }

        .price-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .current-price {
            font-size: 32px;
            font-weight: 400;
            color: #c41e3a;
        }
        
        .original-price {
            font-size: 18px;
            color: #999;
            text-decoration: line-through;
        }
        
        .discount-badge {
            background: #fff0f0;
            color: #c41e3a;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .product-meta {
            display: flex;
            gap: 25px;
            font-size: 13px;
            color: #666;
        }

        .volume-selection {
            margin-bottom: 30px;
        }

        .volume-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #2c2c2c;
            font-weight: 600;
            margin-bottom: 15px;
            display: block;
        }

        .volume-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
        }

        .volume-option {
            position: relative;
        }

        .volume-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .volume-option label {
            display: block;
            padding: 20px 15px;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            text-align: center;
        }

        .volume-option input[type="radio"]:checked + label {
            border-color: #2c2c2c;
            background: #2c2c2c;
            color: white;
        }

        .volume-option label:hover {
            border-color: #2c2c2c;
        }

        .volume-ml {
            display: block;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .volume-price {
            display: block;
            font-size: 14px;
        }

        .volume-stock {
            display: block;
            font-size: 11px;
            margin-top: 8px;
            opacity: 0.7;
        }

        .volume-option.out-of-stock label {
            background: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .volume-option.out-of-stock input[type="radio"] {
            cursor: not-allowed;
        }

        .quantity-selection {
            margin-bottom: 30px;
        }

        .quantity-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #2c2c2c;
            font-weight: 600;
            margin-bottom: 15px;
            display: block;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .quantity-btn {
            background: white;
            color: #2c2c2c;
            border: 1px solid #e0e0e0;
            width: 45px;
            height: 45px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
            font-weight: 300;
        }

        .quantity-btn:hover:not(:disabled) {
            background: #2c2c2c;
            color: white;
            border-color: #2c2c2c;
        }

        .quantity-btn:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .quantity-input {
            width: 80px;
            text-align: center;
            font-size: 16px;
            padding: 12px;
            border: 1px solid #e0e0e0;
            background: #fafafa;
        }
        
        .add-to-cart {
            background: #2c2c2c;
            color: white;
            border: none;
            padding: 18px 40px;
            cursor: pointer;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            width: 100%;
            font-weight: 600;
        }
        
        .add-to-cart:hover:not(:disabled) {
            background: #c41e3a;
        }

        .add-to-cart:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .product-description {
            margin-bottom: 60px;
        }

        .product-description h2 {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 20px;
            color: #2c2c2c;
            text-transform: uppercase;
        }

        .product-description p {
            font-size: 14px;
            line-height: 1.8;
            color: #666;
        }
        
        .product-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 60px;
        }
        
        .spec-item {
            background: #fafafa;
            padding: 25px;
            border: 1px solid #f0f0f0;
        }
        
        .spec-title {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c2c2c;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .spec-item p {
            font-size: 14px;
            color: #666;
        }
        
        .reviews-section {
            margin-bottom: 60px;
        }

        .reviews-section h2 {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 30px;
            color: #2c2c2c;
            text-transform: uppercase;
        }
        
        .review {
            background: #fafafa;
            padding: 30px;
            border: 1px solid #f0f0f0;
            margin-bottom: 20px;
            position: relative;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .review-author {
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .review-date {
            color: #999;
            font-size: 12px;
        }

        .review-rating {
            margin-bottom: 15px;
            font-size: 14px;
        }

        .review h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c2c2c;
        }

        .review p {
            font-size: 14px;
            line-height: 1.8;
            color: #666;
        }

        .review-status {
            color: #e67e22;
            font-style: italic;
            font-size: 12px;
            margin-top: 15px;
        }

        .review-actions {
            display: flex;
            gap: 10px;
        }

        .btn-delete {
            background: #c41e3a;
            color: white;
            border: none;
            padding: 8px 15px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }

        .btn-delete:hover {
            background: #a01628;
        }

        .review-form {
            background: #fafafa;
            padding: 40px;
            border: 1px solid #f0f0f0;
            margin-bottom: 40px;
        }

        .review-form h3 {
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 25px;
            color: #2c2c2c;
        }

        .form-group-review {
            margin-bottom: 25px;
        }

        .form-group-review label {
            display: block;
            margin-bottom: 10px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2c2c2c;
            font-weight: 600;
        }

        .form-group-review input,
        .form-group-review textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #e0e0e0;
            font-size: 14px;
            background: white;
            font-family: inherit;
        }

        .form-group-review input:focus,
        .form-group-review textarea:focus {
            outline: none;
            border-color: #2c2c2c;
        }

        .form-group-review textarea {
            min-height: 120px;
            resize: vertical;
        }

        .stars-input {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .star-input {
            font-size: 28px;
            cursor: pointer;
            color: #ddd;
            transition: color 0.3s;
        }

        .star-input:hover,
        .star-input.selected {
            color: #d4af37;
        }

        .submit-review {
            background: #2c2c2c;
            color: white;
            border: none;
            padding: 15px 40px;
            cursor: pointer;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            font-weight: 600;
        }

        .submit-review:hover {
            background: #c41e3a;
        }
        
        .similar-products {
            margin-top: 80px;
        }

        .similar-products h2 {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 40px;
            color: #2c2c2c;
            text-transform: uppercase;
        }
        
        .similar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .similar-card {
            background: #fff;
            transition: all 0.3s;
            position: relative;
        }

        .similar-card:hover {
            transform: translateY(-5px);
        }
        
        .similar-image-wrapper {
            position: relative;
            padding-bottom: 130%;
            background: #fafafa;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .similar-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .similar-card:hover .similar-image {
            transform: scale(1.08);
        }
        
        .similar-info {
            padding: 0 10px;
        }
        
        .similar-name {
            font-size: 15px;
            font-weight: 400;
            margin-bottom: 10px;
            color: #2c2c2c;
            min-height: 44px;
        }

        .similar-name a {
            text-decoration: none;
            color: inherit;
        }

        .similar-name a:hover {
            color: #c41e3a;
        }
        
        .similar-price {
            font-size: 20px;
            font-weight: 400;
            color: #c41e3a;
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
            padding: 15px 20px;
            margin-bottom: 30px;
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

        @media (max-width: 1024px) {
            .product-header {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .product-images {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                gap: 15px;
            }
            
            .nav-links a {
                font-size: 12px;
            }

            .product-title {
                font-size: 24px;
            }

            .current-price {
                font-size: 24px;
            }

            .main-image {
                height: 400px;
            }

            .volume-options {
                grid-template-columns: 1fr 1fr;
            }

            .similar-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
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

    <?php if (isset($_SESSION['message'])): ?>
        <div class="container">
            <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                <?= $_SESSION['message'] ?>
            </div>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <section class="product-detail">
        <div class="container">
            <a href="index.php" class="back-button">← Back to Products</a>
            
            <div class="product-header">
                <div class="product-images">
                    <img src="<?= htmlspecialchars($main_image) ?>" alt="<?= htmlspecialchars($product['nama_parfum']) ?>" class="main-image" id="mainImage">
                    <?php if (count($images) > 1): ?>
                        <div class="thumbnail-gallery">
                            <?php foreach ($images as $img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" class="thumbnail" onclick="changeMainImage('<?= htmlspecialchars($img) ?>')">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="product-info">
                    <h1 class="product-title"><?= htmlspecialchars($product['nama_parfum']) ?></h1>
                    
                    <div class="product-rating">
                        <div class="stars"><?= renderStars($product['rating_average']) ?></div>
                        <span class="rating-text"><?= number_format($product['rating_average'], 1) ?> (<?= $product['total_reviews'] ?> reviews)</span>
                    </div>
                    
                    <div class="product-price">
                        <div class="price-wrapper" id="displayPrice">
                            <?php if (!empty($volume_options)): ?>
                                <span class="current-price"><?= formatRupiah($volume_options[0]['price']) ?></span>
                            <?php else: ?>
                                <span class="current-price"><?= formatRupiah($product['final_price']) ?></span>
                                <?php if ($product['discount_percentage'] > 0): ?>
                                    <span class="original-price"><?= formatRupiah($product['display_original_price']) ?></span>
                                    <span class="discount-badge">-<?= $product['discount_percentage'] ?>%</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-meta">
                            <span id="stockDisplay">📦 <?= !empty($volume_options) ? $volume_options[0]['stock'] : $product['stok'] ?> in stock</span>
                            <span>🔥 <?= $product['total_sold'] ?> sold</span>
                            <span>👁️ <?= $product['views_today'] ?> views today</span>
                        </div>
                    </div>
                    
                    <form method="POST" action="utils/add_to_cart.php" id="cartForm">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        
                        <?php if (!empty($volume_options)): ?>
                            <div class="volume-selection">
                                <label class="volume-label">Select Volume:</label>
                                <div class="volume-options">
                                    <?php foreach ($volume_options as $index => $volume): ?>
                                        <div class="volume-option <?= $volume['stock'] <= 0 ? 'out-of-stock' : '' ?>">
                                            <input 
                                                type="radio" 
                                                name="volume_selected" 
                                                id="volume_<?= $volume['volume_ml'] ?>" 
                                                value="<?= $volume['volume_ml'] ?>"
                                                data-price="<?= $volume['price'] ?>"
                                                data-stock="<?= $volume['stock'] ?>"
                                                <?= $index === 0 ? 'checked' : '' ?>
                                                <?= $volume['stock'] <= 0 ? 'disabled' : '' ?>
                                                onchange="updatePrice(this)"
                                            >
                                            <label for="volume_<?= $volume['volume_ml'] ?>">
                                                <span class="volume-ml"><?= $volume['volume_ml'] ?> ml</span>
                                                <span class="volume-price"><?= formatRupiah($volume['price']) ?></span>
                                                <span class="volume-stock">
                                                    <?= $volume['stock'] > 0 ? $volume['stock'] . ' in stock' : 'Out of Stock' ?>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="volume_selected" value="<?= $product['volume_ml'] ?>">
                        <?php endif; ?>

                        <div class="quantity-selection">
                            <label for="quantity" class="quantity-label">Quantity:</label>
                            <div class="quantity-control">
                                <button type="button" class="quantity-btn" onclick="decreaseQuantity()">−</button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= !empty($volume_options) ? $volume_options[0]['stock'] : $product['stok'] ?>" class="quantity-input" readonly>
                                <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                            </div>
                        </div>
                        
                        <button type="submit" class="add-to-cart" id="addToCartBtn">
                            Add to Cart
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="product-description">
                <h2>Description</h2>
                <p><?= nl2br(htmlspecialchars($product['deskripsi'])) ?></p>
                <p>Long-lasting fragrance that stays with you all day.</p>
            </div>
            
            <?php if (!empty($product['scent_notes'])): ?>
            <div class="product-specs">
                <div class="spec-item">
                    <div class="spec-title">🌺 Scent Notes</div>
                    <p><?= htmlspecialchars($product['scent_notes']) ?></p>
                </div>
                <?php if ($product['longevity_hours']): ?>
                <div class="spec-item">
                    <div class="spec-title">⏱️ Longevity</div>
                    <p>Up to <?= $product['longevity_hours'] ?> hours</p>
                </div>
                <?php endif; ?>
                <div class="spec-item">
                    <div class="spec-title">💨 Sillage</div>
                    <p><?= ucfirst($product['sillage']) ?></p>
                </div>
                <?php if ($product['season']): ?>
                <div class="spec-item">
                    <div class="spec-title">🌤️ Season</div>
                    <p><?= implode(', ', array_map('ucfirst', explode(',', $product['season']))) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($product['occasion']): ?>
                <div class="spec-item">
                    <div class="spec-title">🎉 Occasion</div>
                    <p><?= implode(', ', array_map('ucfirst', explode(',', $product['occasion']))) ?></p>
                </div>
                <?php endif; ?>
                <div class="spec-item">
                    <div class="spec-title">📏 Projection</div>
                    <p>Up to 6 hours</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="reviews-section">
                <h2>Write a Review</h2>
                <?php if (isLoggedIn()): ?>
                    <div class="review-form">
                        <form method="POST" action="utils/add_review.php">
                            <input type="hidden" name="product_id" value="<?= $product_id ?>">
                            <div class="form-group-review">
                                <label for="rating">Rating:</label>
                                <div class="stars-input">
                                    <span class="star-input" data-rating="1" onclick="setRating(1)">★</span>
                                    <span class="star-input" data-rating="2" onclick="setRating(2)">★</span>
                                    <span class="star-input" data-rating="3" onclick="setRating(3)">★</span>
                                    <span class="star-input" data-rating="4" onclick="setRating(4)">★</span>
                                    <span class="star-input" data-rating="5" onclick="setRating(5)">★</span>
                                </div>
                                <input type="hidden" id="rating" name="rating" value="0" required>
                            </div>
                            <div class="form-group-review">
                                <label for="review_title">Review Title:</label>
                                <input type="text" id="review_title" name="review_title" required>
                            </div>
                            <div class="form-group-review">
                                <label for="review_text">Your Review:</label>
                                <textarea id="review_text" name="review_text" required></textarea>
                            </div>
                            <button type="submit" class="submit-review">Submit Review</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="padding: 30px; background: #fafafa; text-align: center; color: #666;">Please <a href="login.php" style="color: #c41e3a; text-decoration: none;">login</a> to write a review.</p>
                <?php endif; ?>

                <h2 style="margin-top: 60px;">Customer Reviews (<?= count($reviews) ?>)</h2>
                <?php if (empty($reviews)): ?>
                    <p style="padding: 30px; background: #fafafa; text-align: center; color: #666;">No reviews yet. Be the first to review this product!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review">
                            <div class="review-header">
                                <div>
                                    <span class="review-author"><?= htmlspecialchars($review['customer_name']) ?></span>
                                    <span class="review-date"> • <?= date('d M Y', strtotime($review['created_at'])) ?></span>
                                </div>
                                <?php if ($is_admin): ?>
                                    <div class="review-actions">
                                        <a href="utils/delete_review.php?id=<?= $review['id'] ?>&product_id=<?= $product_id ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this review?')">Delete</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="review-rating"><?= renderStars($review['rating']) ?></div>
                            <?php if ($review['review_title']): ?>
                                <h3><?= htmlspecialchars($review['review_title']) ?></h3>
                            <?php endif; ?>
                            <p><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                            <?php if ($review['status'] === 'pending'): ?>
                                <p class="review-status">Pending Admin Approval</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($similar_products)): ?>
            <div class="similar-products">
                <h2>Similar Products</h2>
                <div class="similar-grid">
                    <?php foreach ($similar_products as $similar): ?>
                        <div class="similar-card">
                            <div class="similar-image-wrapper">
                                <a href="product_detail.php?id=<?= $similar['id'] ?>">
                                    <img src="<?= htmlspecialchars($similar['primary_image'] ?? 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 500%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22400%22 height=%22500%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2280%22%3E🧴%3C/text%3E%3C/svg%3E') ?>" 
                                         alt="<?= htmlspecialchars($similar['nama_parfum']) ?>" 
                                         class="similar-image"
                                         loading="lazy">
                                </a>
                            </div>
                            <div class="similar-info">
                                <h3 class="similar-name">
                                    <a href="product_detail.php?id=<?= $similar['id'] ?>">
                                        <?= htmlspecialchars($similar['nama_parfum']) ?>
                                    </a>
                                </h3>
                                <p class="similar-price"><?= formatRupiah($similar['final_price']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
        function changeMainImage(src) {
            document.getElementById('mainImage').src = src;
        }

        function updatePrice(radio) {
            const price = parseFloat(radio.dataset.price);
            const stock = parseInt(radio.dataset.stock);
            
            const priceDisplay = document.getElementById('displayPrice');
            priceDisplay.innerHTML = '<span class="current-price">' + formatRupiah(price) + '</span>';
            
            const stockDisplay = document.getElementById('stockDisplay');
            stockDisplay.textContent = '📦 ' + stock + ' in stock';
            
            const quantityInput = document.getElementById('quantity');
            quantityInput.max = stock;
            if (parseInt(quantityInput.value) > stock) {
                quantityInput.value = stock;
            }
            
            updateButtonState(stock);
        }

        function updateButtonState(stock) {
            const addToCartBtn = document.getElementById('addToCartBtn');
            const quantity = parseInt(document.getElementById('quantity').value);
            
            if (stock <= 0) {
                addToCartBtn.disabled = true;
                addToCartBtn.textContent = 'Out of Stock';
            } else if (quantity > stock) {
                addToCartBtn.disabled = true;
                addToCartBtn.textContent = 'Quantity Exceeds Stock';
            } else {
                addToCartBtn.disabled = false;
                addToCartBtn.textContent = 'Add to Cart';
            }
        }

        function increaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            const max = parseInt(quantityInput.max);
            const current = parseInt(quantityInput.value);
            
            if (current < max) {
                quantityInput.value = current + 1;
                updateButtonState(max);
            }
        }

        function decreaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            const current = parseInt(quantityInput.value);
            const max = parseInt(quantityInput.max);
            
            if (current > 1) {
                quantityInput.value = current - 1;
                updateButtonState(max);
            }
        }

        function formatRupiah(number) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const selectedRadio = document.querySelector('input[name="volume_selected"]:checked');
            if (selectedRadio) {
                const stock = parseInt(selectedRadio.dataset.stock);
                updateButtonState(stock);
            }
        });

        document.getElementById('quantity').addEventListener('input', function() {
            const selectedRadio = document.querySelector('input[name="volume_selected"]:checked');
            if (selectedRadio) {
                const stock = parseInt(selectedRadio.dataset.stock);
                updateButtonState(stock);
            }
        });

        // Rating input system - fixed to show only selected stars
        function setRating(rating) {
            const stars = document.querySelectorAll('.star-input');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('selected');
                } else {
                    star.classList.remove('selected');
                }
            });
            document.getElementById('rating').value = rating;
        }
    </script>
</body>
</html>