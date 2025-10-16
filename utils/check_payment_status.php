<?php
// check_payment_status.php - No whitespace or output before this line!

if (ob_get_level()) {
    ob_end_clean();
}

session_start();
require_once '../config/database.php';
require_once '../config/midtrans_config.php';

header('Content-Type: application/json');

try {
    $temp_order_id = $_POST['temp_order_id'] ?? '';
    
    if (empty($temp_order_id)) {
        throw new Exception('Temp order ID tidak ditemukan');
    }
    
    error_log("Checking payment status for: $temp_order_id");
    
    // Get midtrans order ID from session
    if (!isset($_SESSION['midtrans_order_id'])) {
        throw new Exception('Midtrans order ID tidak ditemukan di session');
    }
    
    $midtrans_order_id = $_SESSION['midtrans_order_id'];
    error_log("Midtrans Order ID: $midtrans_order_id");
    
    // Check status from Midtrans API
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.sandbox.midtrans.com/v2/' . $midtrans_order_id . '/status',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
        ],
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($curl_error) {
        throw new Exception('CURL Error: ' . $curl_error);
    }
    
    $result = json_decode($response, true);
    error_log("Midtrans status response: " . print_r($result, true));
    
    if ($http_code !== 200) {
        throw new Exception('Gagal mengecek status pembayaran: ' . ($result['status_message'] ?? 'Unknown error'));
    }
    
    $transaction_status = $result['transaction_status'] ?? 'pending';
    $transaction_id = $result['transaction_id'] ?? '';
    
    error_log("Transaction status: $transaction_status");
    
    // Determine payment status
    $payment_status = 'pending';
    if (in_array($transaction_status, ['capture', 'settlement'])) {
        $payment_status = 'settlement';
    } elseif (in_array($transaction_status, ['deny', 'cancel', 'expire'])) {
        $payment_status = 'failed';
    }
    
    // If payment successful or pending, create/update order
    if (in_array($payment_status, ['settlement', 'pending'])) {
        // Create order via payment_callback
        if (!isset($_SESSION['pending_order'])) {
            throw new Exception('Pending order data tidak ditemukan');
        }
        
        // Simulate POST to payment_callback.php
        $_POST['order_id'] = $temp_order_id;
        $_POST['transaction_status'] = $transaction_status;
        $_POST['transaction_id'] = $transaction_id;
        
        // Include payment_callback logic
        ob_start();
        include 'payment_callback.php';
        $callback_response = ob_get_clean();
        
        $callback_result = json_decode($callback_response, true);
        
        if ($callback_result && $callback_result['status'] === 'success') {
            echo json_encode([
                'status' => 'success',
                'payment_status' => $payment_status,
                'transaction_status' => $transaction_status,
                'order_id' => $callback_result['order_id'],
                'message' => 'Pembayaran berhasil diverifikasi'
            ]);
        } else {
            throw new Exception($callback_result['message'] ?? 'Gagal membuat order');
        }
    } else {
        echo json_encode([
            'status' => 'failed',
            'payment_status' => $payment_status,
            'transaction_status' => $transaction_status,
            'message' => 'Pembayaran gagal atau dibatalkan'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Error in check_payment_status: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
