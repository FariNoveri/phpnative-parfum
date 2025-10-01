<?php
require_once 'config/database.php';
require_once 'config/midtrans_config.php';

// Ambil notification dari Midtrans
$json_result = file_get_contents('php://input');
$result = json_decode($json_result, true);

// Verify signature key
$signatureKey = hash('sha512', $result['order_id'] . $result['status_code'] . $result['gross_amount'] . MIDTRANS_SERVER_KEY);

if ($signatureKey != $result['signature_key']) {
    http_response_code(403);
    exit('Invalid signature');
}

// Extract order ID (remove prefix)
$midtrans_order_id = $result['order_id'];
$transaction_status = $result['transaction_status'];
$fraud_status = $result['fraud_status'] ?? '';

// Update order status berdasarkan transaction status
$order_status = '';
if ($transaction_status == 'capture') {
    if ($fraud_status == 'challenge') {
        $order_status = 'pending';
    } else if ($fraud_status == 'accept') {
        $order_status = 'paid';
    }
} else if ($transaction_status == 'settlement') {
    $order_status = 'paid';
} else if ($transaction_status == 'cancel' || $transaction_status == 'deny' || $transaction_status == 'expire') {
    $order_status = 'cancelled';
} else if ($transaction_status == 'pending') {
    $order_status = 'pending';
}

// Update database
if (!empty($order_status)) {
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET payment_status = ?, 
            midtrans_transaction_id = ?,
            updated_at = NOW()
        WHERE midtrans_order_id = ?
    ");
    $stmt->execute([$order_status, $result['transaction_id'], $midtrans_order_id]);
}

http_response_code(200);
echo json_encode(['status' => 'success']);