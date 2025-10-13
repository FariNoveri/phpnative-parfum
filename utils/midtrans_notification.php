<?php
require_once '../config/database.php';
require_once '../config/midtrans_config.php';

// Log notification untuk debugging
error_log('=== MIDTRANS NOTIFICATION RECEIVED ===');
error_log('Time: ' . date('Y-m-d H:i:s'));

// Ambil notification dari Midtrans
$json_result = file_get_contents('php://input');
error_log('Raw notification: ' . $json_result);

$result = json_decode($json_result, true);

if (!$result) {
    error_log('ERROR: Invalid JSON');
    http_response_code(400);
    exit('Invalid JSON');
}

error_log('Parsed notification: ' . print_r($result, true));
    
// Verify signature key
$signatureKey = hash('sha512', 
    $result['order_id'] . 
    $result['status_code'] . 
    $result['gross_amount'] . 
    MIDTRANS_SERVER_KEY
);

if ($signatureKey != $result['signature_key']) {
    error_log('ERROR: Invalid signature!');
    http_response_code(403);
    exit('Invalid signature');
}

error_log('Signature verified successfully');

// Extract order ID
$midtrans_order_id = $result['order_id'];
$transaction_status = $result['transaction_status'];
$fraud_status = $result['fraud_status'] ?? '';
$transaction_id = $result['transaction_id'] ?? '';

error_log("Order ID: $midtrans_order_id");
error_log("Transaction Status: $transaction_status");
error_log("Fraud Status: $fraud_status");

// Update order status berdasarkan transaction status
$payment_status = '';
if ($transaction_status == 'capture') {
    if ($fraud_status == 'challenge') {
        $payment_status = 'pending';
    } else if ($fraud_status == 'accept') {
        $payment_status = 'settlement';
    }
} else if ($transaction_status == 'settlement') {
    $payment_status = 'settlement';
} else if ($transaction_status == 'cancel' || $transaction_status == 'deny' || $transaction_status == 'expire') {
    $payment_status = 'failed';
} else if ($transaction_status == 'pending') {
    $payment_status = 'pending';
}

error_log("Determined payment status: $payment_status");

// Update database
if (!empty($payment_status)) {
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status_pembayaran = ?, 
            midtrans_transaction_id = ?,
            updated_at = NOW()
        WHERE midtrans_order_id = ?
    ");
    
    $result_update = $stmt->execute([$payment_status, $transaction_id, $midtrans_order_id]);
    
    if ($result_update) {
        error_log("Order updated successfully. Rows affected: " . $stmt->rowCount());
    } else {
        error_log("ERROR: Failed to update order");
    }
}

error_log('=== NOTIFICATION PROCESSING COMPLETE ===');

http_response_code(200);
echo json_encode(['status' => 'success']);