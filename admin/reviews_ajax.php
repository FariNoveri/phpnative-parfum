<?php
// reviews_ajax.php - File terpisah untuk handle AJAX
error_reporting(0); // Suppress all PHP errors
ini_set('display_errors', 0);

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $offset = (int)($_GET['offset'] ?? 0);
    $limit = 5;
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $sort_param = $_GET['sort'] ?? 'created_at DESC';
    
    // Validate sort parameter
    $allowed_sorts = [
        'created_at DESC' => 'created_at DESC',
        'created_at ASC' => 'created_at ASC',
        'rating DESC' => 'rating DESC',
        'rating ASC' => 'rating ASC'
    ];
    
    $sort = $allowed_sorts[$sort_param] ?? 'created_at DESC';

    // Build base query
    $sql = "SELECT pr.*, p.nama_parfum 
            FROM product_reviews pr 
            LEFT JOIN products p ON pr.product_id = p.id 
            WHERE 1=1";
    $params = [];

    // Add filters
    if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
        $sql .= " AND pr.status = ?";
        $params[] = $status_filter;
    }

    if ($search) {
        $sql .= " AND (pr.customer_name LIKE ? OR pr.review_text LIKE ? OR p.nama_parfum LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Add order and limit (not as parameters)
    $sql .= " ORDER BY pr.$sort LIMIT $limit OFFSET $offset";

    // Execute main query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build HTML rows
    $html = '';
    foreach ($reviews as $review) {
        $statusClass = htmlspecialchars($review['status']);
        $statusText = ucfirst(htmlspecialchars($review['status']));
        $productName = htmlspecialchars($review['nama_parfum'] ?? 'N/A');
        $customerName = htmlspecialchars($review['customer_name']);
        $rating = str_repeat('⭐', (int)$review['rating']);
        $title = htmlspecialchars($review['review_title'] ?? 'N/A');
        $reviewTextFull = htmlspecialchars($review['review_text'] ?? '');
        $text = mb_substr($reviewTextFull, 0, 100) . (mb_strlen($reviewTextFull) > 100 ? '...' : '');
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
        
        $html .= "<tr data-review-id='" . $review['id'] . "'>
            <td>" . $review['id'] . "</td>
            <td>" . $productName . "</td>
            <td>" . $customerName . "</td>
            <td>" . $rating . "</td>
            <td>" . $title . "</td>
            <td class='review-text' title='" . $reviewTextFull . "'>" . $text . "</td>
            <td class='status-" . $statusClass . "'>" . $statusText . "</td>
            <td>" . $date . "</td>
            <td class='actions'>" . $actions . "</td>
        </tr>";
    }

    // Check if there are more reviews
    $nextOffset = $offset + $limit;
    $checkSql = "SELECT pr.id 
                 FROM product_reviews pr 
                 LEFT JOIN products p ON pr.product_id = p.id 
                 WHERE 1=1";
    
    $checkParams = [];
    
    if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
        $checkSql .= " AND pr.status = ?";
        $checkParams[] = $status_filter;
    }

    if ($search) {
        $checkSql .= " AND (pr.customer_name LIKE ? OR pr.review_text LIKE ? OR p.nama_parfum LIKE ?)";
        $search_term = "%$search%";
        $checkParams[] = $search_term;
        $checkParams[] = $search_term;
        $checkParams[] = $search_term;
    }
    
    $checkSql .= " ORDER BY pr.$sort LIMIT 1 OFFSET $nextOffset";
    
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute($checkParams);
    $hasMore = $checkStmt->fetch() !== false;

    // Return JSON
    echo json_encode([
        'success' => true,
        'html' => $html,
        'hasMore' => $hasMore,
        'count' => count($reviews)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}