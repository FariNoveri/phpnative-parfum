<?php
// ajax_endpoints.php - Complete AJAX endpoints for modern e-commerce features
session_start();
require_once '../config/database.php';
require_once 'functions.php'; // Assuming the additional functions are added here

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'search_suggestions':
            handleSearchSuggestions();
            break;
        
        case 'toggle_wishlist':
            handleToggleWishlist();
            break;
        
        case 'quick_view':
            handleQuickView();
            break;
        
        case 'validate_discount':
            handleValidateDiscount();
            break;
        
        case 'load_more_products':
            handleLoadMoreProducts();
            break;
        
        case 'submit_review':
            handleSubmitReview();
            break;
        
        case 'mark_review_helpful':
            handleMarkReviewHelpful();
            break;
        
        case 'check_stock':
            handleCheckStock();
            break;
        
        case 'get_delivery_estimate':
            handleDeliveryEstimate();
            break;
        
        case 'get_cart_count':
            handleGetCartCount();
            break;
        
        case 'update_cart_quantity':
            handleUpdateCartQuantity();
            break;
        
        case 'remove_cart_item':
            handleRemoveCartItem();
            break;
        
        case 'get_product_variants':
            handleGetProductVariants();
            break;
        
        case 'compare_products':
            handleCompareProducts();
            break;
        
        case 'get_similar_products':
            handleGetSimilarProducts();
            break;
        
        case 'track_product_view':
            handleTrackProductView();
            break;
        
        case 'get_trending_products':
            handleGetTrendingProducts();
            break;
        
        case 'search_filters':
            handleGetSearchFilters();
            break;
        
        case 'newsletter_subscribe':
            handleNewsletterSubscribe();
            break;
        
        case 'contact_form':
            handleContactForm();
            break;
        
        case 'get_user_recommendations':
            handleGetUserRecommendations();
            break;
        
        case 'rate_product':
            handleRateProduct();
            break;
        
        case 'report_review':
            handleReportReview();
            break;
        
        case 'get_product_faqs':
            handleGetProductFAQs();
            break;
        
        case 'apply_bulk_discount':
            handleApplyBulkDiscount();
            break;
        
        case 'check_coupon_validity':
            handleCheckCouponValidity();
            break;
        
        case 'get_shipping_options':
            handleGetShippingOptions();
            break;
        
        case 'calculate_shipping':
            handleCalculateShipping();
            break;
        
        case 'save_for_later':
            handleSaveForLater();
            break;
        
        case 'get_recently_viewed':
            handleGetRecentlyViewed();
            break;
        
        case 'clear_recently_viewed':
            handleClearRecentlyViewed();
            break;
        
        case 'export_wishlist':
            handleExportWishlist();
            break;

        case 'get_popular_searches':
            handleGetPopularSearches();
            break;

        case 'log_activity':
            handleLogActivity();
            break;

        case 'get_user_stats':
            handleGetUserStats();
            break;

        case 'add_product_question':
            handleAddProductQuestion();
            break;

        case 'get_product_questions':
            handleGetProductQuestions();
            break;

        case 'subscribe_product_notification':
            handleSubscribeProductNotification();
            break;

        case 'get_cart_summary':
            handleGetCartSummary();
            break;

        case 'get_order_tracking':
            handleGetOrderTracking();
            break;
        
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("AJAX Error: " . $e->getMessage());
    echo json_encode(['error' => 'Terjadi kesalahan sistem']);
}

function handleSearchSuggestions() {
    global $pdo;
    
    $query = $_GET['q'] ?? '';
    if (strlen($query) < 2) {
        echo json_encode(['suggestions' => []]);
        return;
    }
    
    $suggestions = getSearchSuggestions($query, 8, $pdo);
    
    echo json_encode(['suggestions' => $suggestions]);
}

function handleToggleWishlist() {
    global $pdo;
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Silakan login terlebih dahulu']);
        return;
    }
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $user_id = getUserId();
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    // Check if already in wishlist
    if (isInWishlist($user_id, $product_id, $pdo)) {
        $success = removeFromWishlist($user_id, $product_id, $pdo);
        $message = $success ? 'Dihapus dari wishlist' : 'Gagal menghapus dari wishlist';
        $in_wishlist = !$success;
    } else {
        $success = addToWishlist($user_id, $product_id, $pdo);
        $message = $success ? 'Ditambahkan ke wishlist' : 'Gagal menambahkan ke wishlist';
        $in_wishlist = $success;
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'in_wishlist' => $in_wishlist
    ]);
}

function handleQuickView() {
    global $pdo;
    
    $product_id = (int)($_GET['product_id'] ?? 0);
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    $sql = "SELECT p.*, 
            GROUP_CONCAT(DISTINCT pi.image_url ORDER BY pi.sort_order SEPARATOR '|') as all_images,
            CASE 
                WHEN p.discount_percentage > 0 AND p.original_price IS NOT NULL 
                THEN ROUND(p.original_price * (1 - p.discount_percentage/100), 0)
                ELSE p.harga
            END as final_price
            FROM products p
            LEFT JOIN product_images pi ON p.id = pi.product_id 
            WHERE p.id = ?
            GROUP BY p.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        return;
    }
    
    // Get recent reviews
    $reviews_sql = "SELECT pr.*, u.nama as user_name
                   FROM product_reviews pr
                   LEFT JOIN users u ON pr.user_id = u.id
                   WHERE pr.product_id = ? AND pr.status = 'approved'
                   ORDER BY pr.created_at DESC
                   LIMIT 3";
    $reviews_stmt = $pdo->prepare($reviews_sql);
    $reviews_stmt->execute([$product_id]);
    $reviews = $reviews_stmt->fetchAll();
    
    // Track view
    $user_id = isLoggedIn() ? getUserId() : null;
    trackProductView($product_id, $user_id, $pdo);
    
    // Check if in wishlist
    $in_wishlist = isInWishlist($user_id, $product_id, $pdo);
    
    echo json_encode([
        'product' => $product,
        'reviews' => $reviews,
        'in_wishlist' => $in_wishlist
    ]);
}

function handleValidateDiscount() {
    global $pdo;
    
    $code = $_POST['discount_code'] ?? '';
    $cart_total = (float)($_POST['cart_total'] ?? 0);
    $user_id = isLoggedIn() ? getUserId() : null;
    
    if (!$code) {
        echo json_encode(['valid' => false, 'message' => 'Kode diskon tidak boleh kosong']);
        return;
    }
    
    $result = validateDiscountCode($code, $cart_total, $user_id, $pdo);
    echo json_encode($result);
}

function handleLoadMoreProducts() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    // Apply same filters as main page
    $search = $_GET['search'] ?? '';
    $kategori = $_GET['kategori'] ?? '';
    $min_price = $_GET['min_price'] ?? '';
    $max_price = $_GET['max_price'] ?? '';
    $min_rating = $_GET['min_rating'] ?? '';
    $sort_by = $_GET['sort_by'] ?? 'newest';
    $brands = $_GET['brands'] ?? [];
    $volume = $_GET['volume'] ?? '';
    $discount_only = isset($_GET['discount_only']);
    
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
    
    // Apply filters (same logic as main page)
    if ($search) {
        $sql .= " AND (p.nama_parfum LIKE ? OR p.brand LIKE ? OR p.tags LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
    
    if ($kategori) {
        $sql .= " AND p.kategori = ?";
        $params[] = $kategori;
    }
    
    if ($min_price) {
        $sql .= " AND p.harga >= ?";
        $params[] = $min_price;
    }
    
    if ($max_price) {
        $sql .= " AND p.harga <= ?";
        $params[] = $max_price;
    }
    
    if ($min_rating) {
        $sql .= " AND p.rating_average >= ?";
        $params[] = $min_rating;
    }
    
    if (!empty($brands)) {
        $brand_placeholders = str_repeat('?,', count($brands) - 1) . '?';
        $sql .= " AND p.brand IN ($brand_placeholders)";
        $params = array_merge($params, $brands);
    }
    
    if ($volume) {
        $sql .= " AND p.volume_ml = ?";
        $params[] = $volume;
    }
    
    if ($discount_only) {
        $sql .= " AND p.discount_percentage > 0";
    }
    
    $sql .= " GROUP BY p.id";
    
    // Apply sorting
    switch ($sort_by) {
        case 'price_low':
            $sql .= " ORDER BY final_price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY final_price DESC";
            break;
        case 'rating':
            $sql .= " ORDER BY p.rating_average DESC";
            break;
        case 'popular':
            $sql .= " ORDER BY p.total_sold DESC";
            break;
        case 'discount':
            $sql .= " ORDER BY p.discount_percentage DESC";
            break;
        default:
            $sql .= " ORDER BY p.created_at DESC";
            break;
    }
    
    $sql .= " LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    echo json_encode(['products' => $products]);
}

function handleSubmitReview() {
    global $pdo;
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Silakan login terlebih dahulu']);
        return;
    }
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $review_title = trim($_POST['review_title'] ?? '');
    $review_text = trim($_POST['review_text'] ?? '');
    $user_id = getUserId();
    
    if (!$product_id || $rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'Data review tidak valid']);
        return;
    }
    
    // Check if user already reviewed this product
    $check_sql = "SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$product_id, $user_id]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(['error' => 'Anda sudah memberikan review untuk produk ini']);
        return;
    }
    
    // Check if user has purchased this product
    $purchase_sql = "SELECT 1 FROM orders o 
                     JOIN order_items oi ON o.id = oi.order_id 
                     WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('delivered', 'confirmed')";
    $purchase_stmt = $pdo->prepare($purchase_sql);
    $purchase_stmt->execute([$user_id, $product_id]);
    $is_verified = $purchase_stmt->fetch() ? 1 : 0;
    
    // Get user info
    $user_sql = "SELECT nama, email FROM users WHERE id = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    // Insert review
    $insert_sql = "INSERT INTO product_reviews (product_id, user_id, customer_name, customer_email, rating, review_title, review_text, is_verified_purchase, status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $insert_stmt = $pdo->prepare($insert_sql);
    $success = $insert_stmt->execute([$product_id, $user_id, $user['nama'], $user['email'], $rating, $review_title, $review_text, $is_verified]);
    
    if ($success) {
        // Update product rating
        updateProductRating($product_id, $pdo);
        
        echo json_encode(['success' => true, 'message' => 'Review berhasil dikirim dan sedang menunggu persetujuan']);
    } else {
        echo json_encode(['error' => 'Gagal mengirim review']);
    }
}

function handleMarkReviewHelpful() {
    global $pdo;
    
    $review_id = (int)($_POST['review_id'] ?? 0);
    
    if (!$review_id) {
        echo json_encode(['error' => 'Review ID required']);
        return;
    }
    
    $sql = "UPDATE product_reviews SET helpful_count = helpful_count + 1 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$review_id]);
    
    if ($success) {
        $count_sql = "SELECT helpful_count FROM product_reviews WHERE id = ?";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute([$review_id]);
        $helpful_count = $count_stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'helpful_count' => $helpful_count]);
    } else {
        echo json_encode(['error' => 'Gagal menandai review sebagai helpful']);
    }
}

function handleCheckStock() {
    global $pdo;
    
    $product_id = (int)($_GET['product_id'] ?? 0);
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    $sql = "SELECT stok, nama_parfum FROM products WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        return;
    }
    
    echo json_encode([
        'stock' => $product['stok'],
        'available' => $product['stok'] > 0,
        'product_name' => $product['nama_parfum']
    ]);
}

function handleDeliveryEstimate() {
    $city = $_GET['city'] ?? 'Jakarta';
    $estimate = calculateDeliveryEstimate($city);
    
    echo json_encode(['estimate' => $estimate]);
}

function handleGetCartCount() {
    global $pdo;
    
    $cart_count = 0;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM cart WHERE user_id = ?");
        $stmt->execute([getUserId()]);
    } else {
        $stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM cart WHERE session_id = ?");
        $stmt->execute([session_id()]);
    }
    
    $result = $stmt->fetch();
    $cart_count = $result['total'] ?? 0;
    
    echo json_encode(['count' => $cart_count]);
}

function handleUpdateCartQuantity() {
    global $pdo;
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if (!$product_id || $quantity < 0) {
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    if ($quantity == 0) {
        handleRemoveCartItem();
        return;
    }
    
    // Check stock
    $stock_sql = "SELECT stok FROM products WHERE id = ?";
    $stock_stmt = $pdo->prepare($stock_sql);
    $stock_stmt->execute([$product_id]);
    $stock = $stock_stmt->fetchColumn();
    
    if ($quantity > $stock) {
        echo json_encode(['error' => "Stok tidak mencukupi. Stok tersedia: $stock"]);
        return;
    }
    
    if (isLoggedIn()) {
        $sql = "UPDATE cart SET jumlah = ? WHERE user_id = ? AND product_id = ?";
        $params = [$quantity, getUserId(), $product_id];
    } else {
        $sql = "UPDATE cart SET jumlah = ? WHERE session_id = ? AND product_id = ?";
        $params = [$quantity, session_id(), $product_id];
    }
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute($params);
    
    if ($success) {
        // Get updated cart count
        handleGetCartCount();
    } else {
        echo json_encode(['error' => 'Gagal update keranjang']);
    }
}

function handleRemoveCartItem() {
    global $pdo;
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    if (isLoggedIn()) {
        $sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
        $params = [getUserId(), $product_id];
    } else {
        $sql = "DELETE FROM cart WHERE session_id = ? AND product_id = ?";
        $params = [session_id(), $product_id];
    }
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute($params);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Item dihapus dari keranjang']);
    } else {
        echo json_encode(['error' => 'Gagal menghapus item']);
    }
}

function handleGetProductVariants() {
    global $pdo;
    
    $product_id = (int)($_GET['product_id'] ?? 0);
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    // Get different volumes for same product
    $sql = "SELECT id, volume_ml, harga, stok, 
            CASE 
                WHEN discount_percentage > 0 AND original_price IS NOT NULL
                THEN ROUND(original_price * (1 - discount_percentage/100), 0)
                ELSE harga
            END as final_price
            FROM products 
            WHERE brand = (SELECT brand FROM products WHERE id = ?) 
            AND nama_parfum = (SELECT nama_parfum FROM products WHERE id = ?)
            ORDER BY volume_ml ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id, $product_id]);
    $variants = $stmt->fetchAll();
    
    echo json_encode(['variants' => $variants]);
}

function handleCompareProducts() {
    global $pdo;
    
    $product_ids = $_POST['product_ids'] ?? [];
    
    if (empty($product_ids) || count($product_ids) > 3) {
        echo json_encode(['error' => 'Select 2-3 products to compare']);
        return;
    }
    
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $sql = "SELECT p.*, 
            GROUP_CONCAT(DISTINCT pi.image_url ORDER BY pi.sort_order SEPARATOR '|') as all_images,
            CASE 
                WHEN p.discount_percentage > 0 AND p.original_price IS NOT NULL
                THEN ROUND(p.original_price * (1 - p.discount_percentage/100), 0)
                ELSE p.harga
            END as final_price
            FROM products p
            LEFT JOIN product_images pi ON p.id = pi.product_id 
            WHERE p.id IN ($placeholders)
            GROUP BY p.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll();
    
    echo json_encode(['products' => $products]);
}

function handleGetSimilarProducts() {
    global $pdo;
    
    $product_id = (int)($_GET['product_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 4);
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    // Get product details
    $product_sql = "SELECT kategori, brand FROM products WHERE id = ?";
    $product_stmt = $pdo->prepare($product_sql);
    $product_stmt->execute([$product_id]);
    $product = $product_stmt->fetch();
    
    if ($product) {
        $similar = getSimilarProducts($product_id, $product['kategori'], $product['brand'], $limit, $pdo);
        echo json_encode(['products' => $similar]);
    } else {
        echo json_encode(['error' => 'Product not found']);
    }
}

function handleTrackProductView() {
    global $pdo;
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    $user_id = isLoggedIn() ? getUserId() : null;
    trackProductView($product_id, $user_id, $pdo);
    
    echo json_encode(['success' => true]);
}

function handleGetTrendingProducts() {
    global $pdo;
    
    $limit = (int)($_GET['limit'] ?? 6);
    $trending = getTrendingProducts($limit, $pdo);
    
    echo json_encode(['products' => $trending]);
}

function handleGetSearchFilters() {
    global $pdo;
    
    // Get available brands
    $brands_sql = "SELECT DISTINCT brand FROM products WHERE stok > 0 ORDER BY brand";
    $brands_stmt = $pdo->prepare($brands_sql);
    $brands_stmt->execute();
    $brands = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get price range
    $price_sql = "SELECT MIN(harga) as min_price, MAX(harga) as max_price FROM products WHERE stok > 0";
    $price_stmt = $pdo->prepare($price_sql);
    $price_stmt->execute();
    $price_range = $price_stmt->fetch();
    
    // Get available volumes
    $volumes_sql = "SELECT DISTINCT volume_ml FROM products WHERE stok > 0 ORDER BY volume_ml";
    $volumes_stmt = $pdo->prepare($volumes_sql);
    $volumes_stmt->execute();
    $volumes = $volumes_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'brands' => $brands,
        'price_range' => $price_range,
        'volumes' => $volumes
    ]);
}

function handleNewsletterSubscribe() {
    global $pdo;
    
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        echo json_encode(['error' => 'Email tidak valid']);
        return;
    }
    
    // Check if already subscribed
    $check_sql = "SELECT id FROM newsletter_subscribers WHERE email = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$email]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(['error' => 'Email sudah terdaftar']);
        return;
    }
    
    // Insert subscription
    $insert_sql = "INSERT INTO newsletter_subscribers (email, subscribed_at) VALUES (?, NOW())";
    $insert_stmt = $pdo->prepare($insert_sql);
    $success = $insert_stmt->execute([$email]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Berhasil berlangganan newsletter']);
    } else {
        echo json_encode(['error' => 'Gagal berlangganan']);
    }
}

function handleContactForm() {
    $name = trim($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (!$name || !$email || !$subject || !$message) {
        echo json_encode(['error' => 'Semua field wajib diisi']);
        return;
    }
    
    // Send email (implement your email sending logic here)
    // For now, just log it
    error_log("Contact Form: $name ($email) - $subject: $message");
    
    echo json_encode(['success' => true, 'message' => 'Pesan berhasil dikirim']);
}

function handleGetUserRecommendations() {
    global $pdo;
    
    if (!isLoggedIn()) {
        echo json_encode(['products' => []]);
        return;
    }
    
    $user_id = getUserId();
    $limit = (int)($_GET['limit'] ?? 6);
    
    $recommendations = getProductRecommendations($user_id, $limit, $pdo);
    
    echo json_encode(['products' => $recommendations]);
}

function handleRateProduct() {
    global $pdo;
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Login required']);
        return;
    }
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    
    if (!$product_id || $rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'Invalid rating']);
        return;
    }
    
    // This would be similar to handleSubmitReview but just for rating
    // Implementation depends on your rating system design
    echo json_encode(['success' => true, 'message' => 'Rating saved']);
}

function handleReportReview() {
    global $pdo;
    
    $review_id = (int)($_POST['review_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if (!$review_id || !$reason) {
        echo json_encode(['error' => 'Review ID and reason required']);
        return;
    }
    
    $user_id = isLoggedIn() ? getUserId() : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $sql = "INSERT INTO review_reports (review_id, user_id, reason, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$review_id, $user_id, $reason, $ip_address]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Review dilaporkan']);
    } else {
        echo json_encode(['error' => 'Gagal melaporkan review']);
    }
}

function handleGetProductFAQs() {
    global $pdo;
    
    $product_id = (int)($_GET['product_id'] ?? 0);
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    $sql = "SELECT question, answer FROM product_faqs WHERE product_id = ? AND is_active = 1 ORDER BY sort_order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $faqs = $stmt->fetchAll();
    
    echo json_encode(['faqs' => $faqs]);
}

function handleApplyBulkDiscount() {
    global $pdo;
    
    $product_ids = $_POST['product_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    
    if (empty($product_ids) || count($product_ids) != count($quantities)) {
        echo json_encode(['error' => 'Invalid bulk data']);
        return;
    }
    
    $total_items = array_sum($quantities);
    $bulk_discount = 0;
    
    // Apply bulk discount logic
    if ($total_items >= 10) {
        $bulk_discount = 15; // 15% discount for 10+ items
    } elseif ($total_items >= 5) {
        $bulk_discount = 10; // 10% discount for 5+ items
    }
    
    echo json_encode([
        'bulk_discount' => $bulk_discount,
        'message' => $bulk_discount > 0 ? "Diskon bulk $bulk_discount% diterapkan!" : 'Tidak ada diskon bulk'
    ]);
}

function handleCheckCouponValidity() {
    global $pdo;
    
    $code = $_GET['code'] ?? '';
    
    if (!$code) {
        echo json_encode(['valid' => false, 'message' => 'Kode tidak boleh kosong']);
        return;
    }
    
    $sql = "SELECT * FROM discounts WHERE code = ? AND is_active = 1 AND valid_from <= NOW() AND valid_until >= NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$code]);
    $discount = $stmt->fetch();
    
    if ($discount) {
        echo json_encode([
            'valid' => true,
            'discount' => $discount,
            'message' => 'Kode valid'
        ]);
    } else {
        echo json_encode([
            'valid' => false,
            'message' => 'Kode tidak valid atau sudah expired'
        ]);
    }
}

function handleGetShippingOptions() {
    $options = [
        [
            'courier' => 'JNE Regular',
            'cost' => 15000,
            'estimate' => '2-3 hari'
        ],
        [
            'courier' => 'JNE YES',
            'cost' => 25000,
            'estimate' => '1-2 hari'
        ],
        [
            'courier' => 'Sicepat Regular',
            'cost' => 12000,
            'estimate' => '2-4 hari'
        ],
        [
            'courier' => 'AnterAja Same Day',
            'cost' => 35000,
            'estimate' => 'Hari ini'
        ]
    ];
    
    echo json_encode(['shipping_options' => $options]);
}

function handleCalculateShipping() {
    $destination = $_POST['destination'] ?? 'Jakarta';
    $weight = (float)($_POST['weight'] ?? 0.5); // in kg
    $courier = $_POST['courier'] ?? 'JNE Regular';
    
    // Mock shipping calculation
    $base_cost = 15000;
    $weight_multiplier = ($weight > 1) ? ($weight - 1) * 5000 : 0;
    $distance_multiplier = ($destination != 'Jakarta') ? 10000 : 0;
    
    $total_cost = $base_cost + $weight_multiplier + $distance_multiplier;
    
    echo json_encode([
        'shipping_cost' => $total_cost,
        'courier' => $courier,
        'estimate' => calculateDeliveryEstimate($destination)
    ]);
}

function handleSaveForLater() {
    global $pdo;
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Login required']);
        return;
    }
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $user_id = getUserId();
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    // Remove from cart and add to wishlist
    $delete_sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([$user_id, $product_id]);
    
    // Add to wishlist
    $success = addToWishlist($user_id, $product_id, $pdo);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Item dipindah ke wishlist']);
    } else {
        echo json_encode(['error' => 'Gagal menyimpan item']);
    }
}

function handleGetRecentlyViewed() {
    global $pdo;
    
    $limit = (int)($_GET['limit'] ?? 8);
    
    if (isLoggedIn()) {
        $sql = "SELECT DISTINCT p.*, 
                GROUP_CONCAT(DISTINCT pi.image_url ORDER BY pi.sort_order SEPARATOR '|') as all_images,
                CASE 
                    WHEN p.discount_percentage > 0 AND p.original_price IS NOT NULL
                    THEN ROUND(p.original_price * (1 - p.discount_percentage/100), 0)
                    ELSE p.harga
                END as final_price,
                pv.viewed_at
                FROM products p
                LEFT JOIN product_images pi ON p.id = pi.product_id
                JOIN product_views pv ON p.id = pv.product_id
                WHERE pv.user_id = ?
                GROUP BY p.id
                ORDER BY pv.viewed_at DESC
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([getUserId(), $limit]);
    } else {
        $sql = "SELECT DISTINCT p.*, 
                GROUP_CONCAT(DISTINCT pi.image_url ORDER BY pi.sort_order SEPARATOR '|') as all_images,
                CASE 
                    WHEN p.discount_percentage > 0 AND p.original_price IS NOT NULL
                    THEN ROUND(p.original_price * (1 - p.discount_percentage/100), 0)
                    ELSE p.harga
                END as final_price,
                pv.viewed_at
                FROM products p
                LEFT JOIN product_images pi ON p.id = pi.product_id
                JOIN product_views pv ON p.id = pv.product_id
                WHERE pv.session_id = ? AND pv.viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY p.id
                ORDER BY pv.viewed_at DESC
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([session_id(), $limit]);
    }
    
    $products = $stmt->fetchAll();
    echo json_encode(['products' => $products]);
}

function handleClearRecentlyViewed() {
    global $pdo;
    
    if (isLoggedIn()) {
        $sql = "DELETE FROM product_views WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([getUserId()]);
    } else {
        $sql = "DELETE FROM product_views WHERE session_id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([session_id()]);
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Recently viewed cleared']);
    } else {
        echo json_encode(['error' => 'Failed to clear history']);
    }
}

function handleExportWishlist() {
    global $pdo;
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Login required']);
        return;
    }
    
    $user_id = getUserId();
    
    $sql = "SELECT p.nama_parfum, p.brand, p.harga, p.volume_ml, w.created_at
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $wishlist = $stmt->fetchAll();
    
    if (empty($wishlist)) {
        echo json_encode(['error' => 'Wishlist kosong']);
        return;
    }
    
    // Convert to CSV format
    $csv_data = "Product Name,Brand,Price,Volume,Date Added\n";
    foreach ($wishlist as $item) {
        $csv_data .= '"' . $item['nama_parfum'] . '","' . $item['brand'] . '","' . 
                    $item['harga'] . '","' . $item['volume_ml'] . 'ml","' . 
                    $item['created_at'] . "\"\n";
    }
    
    echo json_encode([
        'success' => true,
        'data' => $csv_data,
        'filename' => 'wishlist_' . date('Y-m-d') . '.csv'
    ]);
}

// Additional utility endpoints

function handleGetPopularSearches() {
    global $pdo;
    
    $limit = (int)($_GET['limit'] ?? 10);
    $popular = getPopularSearchTerms($limit, $pdo);
    
    echo json_encode(['searches' => $popular]);
}

function handleLogActivity() {
    global $pdo;
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $activity = $_POST['activity'] ?? '';
    $details = $_POST['details'] ?? null;
    
    if (!$activity) {
        echo json_encode(['error' => 'Activity required']);
        return;
    }
    
    logUserActivity(getUserId(), $activity, $details, $pdo);
    echo json_encode(['success' => true]);
}

function handleGetUserStats() {
    global $pdo;
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $user_id = getUserId();
    $stats = getUserAnalytics($user_id, $pdo);
    
    echo json_encode(['stats' => $stats]);
}

function handleAddProductQuestion() {
    global $pdo;
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $customer_name = trim($_POST['name'] ?? '');
    $customer_email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if (!$product_id || !$question || !$customer_name || !$customer_email) {
        echo json_encode(['error' => 'Semua field wajib diisi']);
        return;
    }
    
    $sql = "INSERT INTO product_questions (product_id, customer_name, customer_email, question, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$product_id, $customer_name, $customer_email, $question]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Pertanyaan berhasil dikirim']);
    } else {
        echo json_encode(['error' => 'Gagal mengirim pertanyaan']);
    }
}

function handleGetProductQuestions() {
    global $pdo;
    
    $product_id = (int)($_GET['product_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 5);
    
    if (!$product_id) {
        echo json_encode(['error' => 'Product ID required']);
        return;
    }
    
    $sql = "SELECT pq.*, 
            (SELECT answer FROM product_question_answers pqa WHERE pqa.question_id = pq.id ORDER BY pqa.created_at DESC LIMIT 1) as answer
            FROM product_questions pq
            WHERE pq.product_id = ? AND pq.status = 'approved'
            ORDER BY pq.created_at DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id, $limit]);
    $questions = $stmt->fetchAll();
    
    echo json_encode(['questions' => $questions]);
}

function handleSubscribeProductNotification() {
    global $pdo;
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $notification_type = $_POST['type'] ?? 'back_in_stock'; // back_in_stock, price_drop
    
    if (!$product_id || !$email) {
        echo json_encode(['error' => 'Product ID and email required']);
        return;
    }
    
    // Check if already subscribed
    $check_sql = "SELECT id FROM product_notifications WHERE product_id = ? AND email = ? AND notification_type = ? AND is_active = 1";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$product_id, $email, $notification_type]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(['error' => 'Sudah berlangganan notifikasi untuk produk ini']);
        return;
    }
    
    $sql = "INSERT INTO product_notifications (product_id, email, notification_type, is_active, created_at) VALUES (?, ?, ?, 1, NOW())";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$product_id, $email, $notification_type]);
    
    if ($success) {
        $message = $notification_type === 'back_in_stock' ? 
                  'Anda akan diberitahu saat produk tersedia kembali' : 
                  'Anda akan diberitahu saat ada penurunan harga';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['error' => 'Gagal berlangganan notifikasi']);
    }
}

function handleGetCartSummary() {
    global $pdo;
    
    $cart_items = [];
    $total = 0;
    
    if (isLoggedIn()) {
        $sql = "SELECT c.*, p.nama_parfum, p.brand, p.harga, p.stok, p.gambar, 
                CASE 
                    WHEN p.discount_percentage > 0 AND p.original_price IS NOT NULL
                    THEN ROUND(p.original_price * (1 - p.discount_percentage/100), 0)
                    ELSE p.harga
                END as final_price
                FROM cart c 
                JOIN products p ON c.product_id = p.id 
                WHERE c.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([getUserId()]);
    } else {
        $sql = "SELECT c.*, p.nama_parfum, p.brand, p.harga, p.stok, p.gambar,
                CASE 
                    WHEN p.discount_percentage > 0 AND p.original_price IS NOT NULL
                    THEN ROUND(p.original_price * (1 - p.discount_percentage/100), 0)
                    ELSE p.harga
                END as final_price
                FROM cart c 
                JOIN products p ON c.product_id = p.id 
                WHERE c.session_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([session_id()]);
    }
    
    $cart_items = $stmt->fetchAll();
    
    foreach ($cart_items as $item) {
        $total += $item['final_price'] * $item['jumlah'];
    }
    
    echo json_encode([
        'items' => $cart_items,
        'total' => $total,
        'count' => array_sum(array_column($cart_items, 'jumlah'))
    ]);
}

function handleGetOrderTracking() {
    global $pdo;
    
    $order_id = (int)($_GET['order_id'] ?? 0);
    
    if (!$order_id) {
        echo json_encode(['error' => 'Order ID required']);
        return;
    }
    
    if (isLoggedIn()) {
        $sql = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order_id, getUserId()]);
    } else {
        $email = $_GET['email'] ?? '';
        if (!$email) {
            echo json_encode(['error' => 'Email required for guest tracking']);
            return;
        }
        
        $sql = "SELECT * FROM orders WHERE id = ? AND email_customer = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order_id, $email]);
    }
    
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['error' => 'Order not found']);
        return;
    }
    
    // Get order items
    $items_sql = "SELECT oi.*, p.nama_parfum, p.brand FROM order_items oi 
                  JOIN products p ON oi.product_id = p.id 
                  WHERE oi.order_id = ?";
    $items_stmt = $pdo->prepare($items_sql);
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll();
    
    // Mock tracking status
    $tracking_steps = [
        ['status' => 'confirmed', 'label' => 'Pesanan Dikonfirmasi', 'completed' => true, 'date' => $order['created_at']],
        ['status' => 'processing', 'label' => 'Sedang Diproses', 'completed' => in_array($order['status'], ['processing', 'shipped', 'delivered']), 'date' => null],
        ['status' => 'shipped', 'label' => 'Dikirim', 'completed' => in_array($order['status'], ['shipped', 'delivered']), 'date' => null],
        ['status' => 'delivered', 'label' => 'Diterima', 'completed' => $order['status'] === 'delivered', 'date' => null]
    ];
    
    echo json_encode([
        'order' => $order,
        'items' => $items,
        'tracking' => $tracking_steps
    ]);
}

// Helper functions untuk AJAX
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Create missing tables if they don't exist
function createMissingTables() {
    global $pdo;
    
    // Newsletter subscribers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `email` varchar(255) NOT NULL,
        `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `is_active` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // Review reports table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `review_reports` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `review_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `reason` text NOT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `review_id` (`review_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // Product FAQs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_faqs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `product_id` int(11) NOT NULL,
        `question` text NOT NULL,
        `answer` text NOT NULL,
        `sort_order` int(11) DEFAULT 0,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // Product questions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_questions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `product_id` int(11) NOT NULL,
        `customer_name` varchar(100) NOT NULL,
        `customer_email` varchar(100) NOT NULL,
        `question` text NOT NULL,
        `status` enum('pending','approved','rejected') DEFAULT 'pending',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // Product question answers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_question_answers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `question_id` int(11) NOT NULL,
        `admin_id` int(11) NOT NULL,
        `answer` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `question_id` (`question_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // Product notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `product_id` int(11) NOT NULL,
        `email` varchar(255) NOT NULL,
        `notification_type` enum('back_in_stock','price_drop') NOT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `notified_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // User activity logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_activity_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `activity` varchar(100) NOT NULL,
        `details` text DEFAULT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

// Initialize missing tables when this file is first loaded
try {
    createMissingTables();
} catch (Exception $e) {
    error_log("Failed to create missing tables: " . $e->getMessage());
}
?>