<?php
session_start();
// product_detail.php - Updated with volume selection and review form
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

// Fetch reviews
$reviews_sql = "SELECT * FROM product_reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC";
$reviews_stmt = $pdo->prepare($reviews_sql);
$reviews_stmt->execute([$product_id]);
$reviews = $reviews_stmt->fetchAll();

// Log view
$log_sql = "INSERT INTO product_views (product_id, user_id, ip_address, session_id, viewed_at) VALUES (?, ?, ?, ?, NOW())";
$user_id = isLoggedIn() ? getUserId() : null;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$session_id = session_id();
$pdo->prepare($log_sql)->execute([$product_id, $user_id, $ip, $session_id]);

// Fetch similar products
$similar_sql = "SELECT p.*, 
                (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
                FROM products p 
                WHERE p.id != ? AND (p.kategori = ? OR p.brand = ?) 
                ORDER BY RAND() LIMIT 4";
$similar_stmt = $pdo->prepare($similar_sql);
$similar_stmt->execute([$product_id, $product['kategori'], $product['brand']]);
$similar_products = $similar_stmt->fetchAll();

function renderStars($rating) {
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5;
    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
    
    $stars = str_repeat('⭐', $full_stars);
    if ($half_star) $stars .= '½';
    $stars .= str_repeat('☆', $empty_stars);
    
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

        .product-detail {
            padding: 3rem 0;
        }
        
        .product-header {
            display: flex;
            gap: 3rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }
        
        .product-images {
            flex: 1;
            min-width: 400px;
        }
        
        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .thumbnail-gallery {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            overflow-x: auto;
        }
        
        .thumbnail {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s;
            border: 2px solid transparent;
        }
        
        .thumbnail:hover {
            transform: scale(1.05);
            border-color: #667eea;
        }
        
        .product-info {
            flex: 1;
            min-width: 300px;
        }
        
        .product-title {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .product-brand {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1rem;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .product-price {
            font-size: 2rem;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .original-price {
            font-size: 1.2rem;
            color: #999;
            text-decoration: line-through;
            margin-left: 1rem;
        }
        
        .discount {
            background: #e74c3c;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 1rem;
            margin-left: 1rem;
        }
        
        .product-meta {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            color: #666;
        }

        /* Volume Selection Styles */
        .volume-selection {
            margin-bottom: 2rem;
        }

        .volume-label {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: block;
        }

        .volume-options {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
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
            padding: 1rem 1.5rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            min-width: 120px;
            text-align: center;
        }

        .volume-option input[type="radio"]:checked + label {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .volume-option label:hover {
            border-color: #667eea;
            transform: translateY(-1px);
        }

        .volume-ml {
            display: block;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.3rem;
        }

        .volume-price {
            display: block;
            font-size: 0.95rem;
        }

        .volume-stock {
            display: block;
            font-size: 0.85rem;
            margin-top: 0.3rem;
            opacity: 0.8;
        }

        .volume-option.out-of-stock label {
            background: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .volume-option.out-of-stock input[type="radio"] {
            cursor: not-allowed;
        }

        /* Quantity Selection */
        .quantity-selection {
            margin-bottom: 2rem;
        }

        .quantity-label {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: block;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quantity-btn {
            background: #667eea;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s;
        }

        .quantity-btn:hover {
            background: #764ba2;
            transform: scale(1.1);
        }

        .quantity-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: scale(1);
        }

        .quantity-input {
            width: 80px;
            text-align: center;
            font-size: 1.2rem;
            padding: 0.5rem;
            border: 2px solid #ddd;
            border-radius: 8px;
        }
        
        .add-to-cart {
            background: #27ae60;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s;
            width: 100%;
        }
        
        .add-to-cart:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .add-to-cart:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        
        .product-description {
            margin-bottom: 3rem;
        }
        
        .product-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 3rem;
        }
        
        .spec-item {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .spec-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #667eea;
        }
        
        .reviews-section {
            margin-bottom: 3rem;
        }
        
        .review {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            position: relative;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .review-author {
            font-weight: bold;
        }
        
        .review-date {
            color: #999;
            font-size: 0.9rem;
        }

        .review-rating {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .review-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .review-form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .review-form h3 {
            margin-bottom: 1rem;
            color: #667eea;
        }

        .form-group-review {
            margin-bottom: 1rem;
        }

        .form-group-review label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .form-group-review input,
        .form-group-review textarea,
        .form-group-review select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group-review textarea {
            min-height: 100px;
            resize: vertical;
        }

        .stars-container {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .star {
            font-size: 2rem;
            cursor: pointer;
            color: #ddd;
            transition: color 0.3s;
        }

        .star.selected {
            color: #ffd700;
        }

        .submit-review {
            background: #667eea;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .submit-review:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .similar-products {
            margin-top: 3rem;
        }
        
        .similar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .similar-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s;
        }

        .similar-card:hover {
            transform: translateY(-5px);
        }
        
        .similar-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .similar-info {
            padding: 1rem;
        }
        
        .similar-name {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .similar-price {
            color: #e74c3c;
            font-weight: bold;
        }

        .back-button {
            background: #6c757d;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }

        .back-button:hover {
            background: #5a6268;
            transform: translateY(-1px);
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

        @media (max-width: 768px) {
            .product-header {
                flex-direction: column;
            }
            
            .main-image {
                height: 300px;
            }

            .volume-options {
                flex-direction: column;
            }

            .volume-option label {
                width: 100%;
            }

            .review-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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

    <section class="product-detail">
        <div class="container">
            <a href="index.php" class="back-button">← Kembali</a>
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
                    <div class="product-brand">Refill Premium</div>
                    
                    <div class="product-rating">
                        <?= renderStars($product['rating_average']) ?>
                        <span>(<?= $product['total_reviews'] ?> reviews)</span>
                    </div>
                    
                    <div class="product-price" id="displayPrice">
                        <?php if (!empty($volume_options)): ?>
                            <?= formatRupiah($volume_options[0]['price']) ?>
                        <?php else: ?>
                            <?= formatRupiah($product['final_price']) ?>
                            <?php if ($product['discount_percentage'] > 0): ?>
                                <span class="original-price"><?= formatRupiah($product['display_original_price']) ?></span>
                                <span class="discount"><?= $product['discount_percentage'] ?>% OFF</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-meta">
                        <span id="stockDisplay">
                            Stok: <?= !empty($volume_options) ? $volume_options[0]['stock'] : $product['stok'] ?>
                        </span>
                        <span>Terjual: <?= $product['total_sold'] ?></span>
                        <span>Dilihat hari ini: <?= $product['views_today'] ?></span>
                    </div>
                    
                    <form method="POST" action="utils/add_to_cart.php" id="cartForm">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        
                        <?php if (!empty($volume_options)): ?>
                            <div class="volume-selection">
                                <label class="volume-label">Pilih Volume:</label>
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
                                                    <?= $volume['stock'] > 0 ? 'Stok: ' . $volume['stock'] : 'Stok Habis' ?>
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
                            <label for="quantity" class="quantity-label">Jumlah:</label>
                            <div class="quantity-control">
                                <button type="button" class="quantity-btn" onclick="decreaseQuantity()">−</button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= !empty($volume_options) ? $volume_options[0]['stock'] : $product['stok'] ?>" class="quantity-input" readonly>
                                <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                            </div>
                        </div>
                        
                        <button type="submit" class="add-to-cart" id="addToCartBtn">
                            🛒 Tambah ke Keranjang
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="product-description">
                <h2>Deskripsi</h2>
                <p><?= nl2br(htmlspecialchars($product['deskripsi'])) ?></p>
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
                    <p><?= $product['longevity_hours'] ?> hours</p>
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
            </div>
            <?php endif; ?>
            
            <div class="reviews-section">
                <h2>Tulis Review</h2>
                <?php if (isLoggedIn()): ?>
                    <div class="review-form">
                        <form method="POST" action="utils/add_review.php">
                            <input type="hidden" name="product_id" value="<?= $product_id ?>">
                            <div class="form-group-review">
                                <label for="rating">Rating:</label>
                                <div class="stars-container">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <span class="star" onclick="setRating(<?= $i ?>)">⭐</span>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" id="rating" name="rating" value="0" required>
                            </div>
                            <div class="form-group-review">
                                <label for="review_title">Judul Review:</label>
                                <input type="text" id="review_title" name="review_title" required>
                            </div>
                            <div class="form-group-review">
                                <label for="review_text">Review:</label>
                                <textarea id="review_text" name="review_text" required></textarea>
                            </div>
                            <button type="submit" class="submit-review">Kirim Review</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p>Login untuk menulis review.</p>
                <?php endif; ?>

                <h2>Reviews (<?= count($reviews) ?>)</h2>
                <?php if (empty($reviews)): ?>
                    <p>Belum ada review untuk produk ini.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review">
                            <div class="review-header">
                                <div>
                                    <span class="review-author"><?= htmlspecialchars($review['customer_name']) ?></span>
                                    <span class="review-date"><?= date('d M Y', strtotime($review['created_at'])) ?></span>
                                </div>
                                <?php if ($is_admin): ?>
                                    <div class="review-actions">
                                        <a href="utils/delete_review.php?id=<?= $review['id'] ?>&product_id=<?= $product_id ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('Yakin ingin menghapus review ini?')">🗑️ Hapus</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="review-rating"><?= renderStars($review['rating']) ?></div>
                            <?php if ($review['review_title']): ?>
                                <h3><?= htmlspecialchars($review['review_title']) ?></h3>
                            <?php endif; ?>
                            <p><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($similar_products)): ?>
            <div class="similar-products">
                <h2>Produk Serupa</h2>
                <div class="similar-grid">
                    <?php foreach ($similar_products as $similar): ?>
                        <div class="similar-card">
                            <a href="product_detail.php?id=<?= $similar['id'] ?>">
                                <img src="<?= htmlspecialchars($similar['primary_image'] ?? 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2NjYyIvPjx0ZXh0IHg9IjEwMCIgeT0iMTAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiNjY2MiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj4rPC90ZXh0Pjwvc3ZnPg==') ?>" alt="<?= htmlspecialchars($similar['nama_parfum']) ?>" class="similar-image">
                            </a>
                            <div class="similar-info">
                                <h3 class="similar-name">
                                    <a href="product_detail.php?id=<?= $similar['id'] ?>" style="text-decoration: none; color: inherit;">
                                        <?= htmlspecialchars($similar['nama_parfum']) ?>
                                    </a>
                                </h3>
                                <p class="similar-price"><?= formatRupiah($similar['harga']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
        function changeMainImage(src) {
            document.getElementById('mainImage').src = src;
        }

        function updatePrice(radio) {
            const price = parseFloat(radio.dataset.price);
            const stock = parseInt(radio.dataset.stock);
            
            // Update price display
            const priceDisplay = document.getElementById('displayPrice');
            priceDisplay.innerHTML = formatRupiah(price);
            
            // Update stock display
            const stockDisplay = document.getElementById('stockDisplay');
            stockDisplay.textContent = 'Stok: ' + stock;
            
            // Update quantity max
            const quantityInput = document.getElementById('quantity');
            quantityInput.max = stock;
            if (parseInt(quantityInput.value) > stock) {
                quantityInput.value = stock;
            }
            
            // Update button state
            updateButtonState(stock);
        }

        function updateButtonState(stock) {
            const addToCartBtn = document.getElementById('addToCartBtn');
            const quantity = parseInt(document.getElementById('quantity').value);
            
            if (stock <= 0) {
                addToCartBtn.disabled = true;
                addToCartBtn.textContent = '❌ Stok Habis';
            } else if (quantity > stock) {
                addToCartBtn.disabled = true;
                addToCartBtn.textContent = '⚠️ Jumlah Melebihi Stok';
            } else {
                addToCartBtn.disabled = false;
                addToCartBtn.textContent = '🛒 Tambah ke Keranjang';
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

        // Initialize button state on load
        document.addEventListener('DOMContentLoaded', function() {
            const selectedRadio = document.querySelector('input[name="volume_selected"]:checked');
            if (selectedRadio) {
                const stock = parseInt(selectedRadio.dataset.stock);
                updateButtonState(stock);
            }
        });

        // Listen to quantity input changes
        document.getElementById('quantity').addEventListener('input', function() {
            const selectedRadio = document.querySelector('input[name="volume_selected"]:checked');
            if (selectedRadio) {
                const stock = parseInt(selectedRadio.dataset.stock);
                updateButtonState(stock);
            }
        });

        // Review stars
        function setRating(rating) {
            const stars = document.querySelectorAll('.star');
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