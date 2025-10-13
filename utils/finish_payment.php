<?php
session_start();
require_once '../config/database.php';
require_once '../config/midtrans_config.php';

error_log('Finish payment accessed at ' . date('Y-m-d H:i:s'));
error_log('GET params: ' . print_r($_GET, true));
error_log('Session data: ' . print_r($_SESSION, true));

try {
    $midtrans_order_id = $_SESSION['midtrans_order_id'] ?? '';
    if (empty($midtrans_order_id)) {
        throw new Exception('Midtrans order ID tidak ditemukan di session.');
    }

    $temp_order_id = $_SESSION['pending_temp_order_id'] ?? '';
    if (empty($temp_order_id)) {
        throw new Exception('Temp order ID tidak ditemukan di session');
    }

    // Query API
    $api_url = 'https://api.sandbox.midtrans.com/v2/' . urlencode($midtrans_order_id) . '/status';
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    error_log('Midtrans API response: ' . $response . ' | HTTP: ' . $http_code);

    if ($curl_error) {
        throw new Exception('CURL Error: ' . $curl_error);
    }

    if ($http_code == 404) {
        // HACK SANDBOX: Force status untuk bypass 404 di simulasi DANA (hapus di production)
        error_log('SANDBOX HACK: 404 detected, forcing settlement for testing');
        $transaction_status = 'settlement';  // Atau 'pending' kalau mau simulasikan pending
        $transaction_id = 'forced-sandbox-' . time();
    } elseif ($http_code !== 200) {
        throw new Exception('Gagal query Midtrans: HTTP ' . $http_code . ' | Response: ' . $response);
    } else {
        $result = json_decode($response, true);
        $transaction_status = $result['transaction_status'] ?? '';
        $transaction_id = $result['transaction_id'] ?? '';
        if (empty($transaction_status)) {
            throw new Exception('Status kosong di response normal');
        }
    }

    // Skip kalau gagal (adjust hack kalau perlu)
    if (in_array($transaction_status, ['deny', 'cancel', 'expire', 'failure'])) {
        throw new Exception('Pembayaran gagal: ' . $transaction_status);
    }

    // Proses callback
    $_POST['order_id'] = $temp_order_id;
    $_POST['transaction_status'] = $transaction_status;
    $_POST['transaction_id'] = $transaction_id;

    ob_start();
    include 'utils/payment_callback.php';
    $callback_response = ob_get_clean();

    $callback_result = json_decode($callback_response, true);

    if ($callback_result['status'] === 'success' && isset($callback_result['redirect'])) {
        unset($_SESSION['pending_order'], $_SESSION['pending_temp_order_id'], $_SESSION['midtrans_order_id']);
        header('Location: ' . $callback_result['redirect']);
        exit;
    } else {
        throw new Exception('Callback gagal: ' . ($callback_result['message'] ?? $callback_response));
    }

} catch (Exception $e) {
    error_log('Finish payment error: ' . $e->getMessage());
    $_SESSION['message'] = 'Error: ' . $e->getMessage() . ' (Sandbox DANA unreliable, coba Credit Card)';
    $_SESSION['message_type'] = 'error';
    header('Location: index.php');
    exit;
}
?>