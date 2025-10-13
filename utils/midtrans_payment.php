<?php
// utils/midtrans_payment.php - Pastikan file ini di folder utils/, dan ga ada output sebelum header!

if (ob_get_level()) {
    ob_clean();  // Bersihin buffer biar ga ada warning/output prematur
}

session_start();
require_once '../config/database.php';  // Path relatif dari utils/ ke root/config
require_once '../config/midtrans_config.php';

header('Content-Type: application/json');

// Cek apakah ada order_id
if (!isset($_POST['order_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Order ID tidak ditemukan'
    ]);
    exit;
}

$order_id = $_POST['order_id'];

// If temp order, use pending data from session
if (strpos($order_id, 'temp_') === 0) {
    if (!isset($_SESSION['pending_order']) || 
        !isset($_SESSION['pending_temp_order_id']) || 
        $_SESSION['pending_temp_order_id'] !== $order_id) {
        echo json_encode(['status' => 'error', 'message' => 'Order tidak ditemukan']);
        exit;
    }

    try {
        $pending = $_SESSION['pending_order'];
        
        // Format items untuk Midtrans
        $item_details = [];
        foreach ($pending['items'] as $item) {
            $item_details[] = [
                'id' => 'PROD-' . $item['product_id'],
                'price' => (int)$item['harga'],
                'quantity' => (int)$item['jumlah'],
                'name' => substr($item['nama_parfum'] . ' - ' . $item['brand'], 0, 50) // Max 50 karakter
            ];
        }
        
        // Transaction details
        $midtrans_order_id = 'ORDER-' . $order_id . '-' . time();
        
        $transaction_details = [
            'order_id' => $midtrans_order_id,
            'gross_amount' => (int)$pending['total']
        ];
        
        // Customer details
        $customer_details = [
            'first_name' => $pending['customer']['nama'],
            'email' => $pending['customer']['email'],
            'phone' => $pending['customer']['telepon']
        ];
        
        // Shipping address (optional)
        if (!empty($pending['customer']['alamat'])) {
            $customer_details['shipping_address'] = [
                'address' => $pending['customer']['alamat']
            ];
        }
        
        // Build params untuk Midtrans
        $params = [
            'transaction_details' => $transaction_details,
            'item_details' => $item_details,
            'customer_details' => $customer_details,
            'enabled_payments' => ['credit_card', 'dana'],  // Tambah DANA dan metode lain
            'callbacks' => [
                'finish' => 'https://9b383fac1ef4.ngrok-free.app/utils/finish_payment.php'  // Update ke ngrok URL lo
            ]
        ];
        
        // Request ke Midtrans API
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => MIDTRANS_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
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
        
        // Tambah logging untuk debug create snap
        error_log('Midtrans create snap HTTP: ' . $http_code);
        error_log('Midtrans create snap response: ' . $response);
        
        if ($curl_error) {
            throw new Exception('CURL Error: ' . $curl_error);
        }
        
        $result = json_decode($response, true);
        
        if ($http_code == 201 && isset($result['token'])) {
            // Simpan snap token ke session untuk temp order
            $_SESSION['snap_token'] = $result['token'];
            $_SESSION['midtrans_order_id'] = $midtrans_order_id;
            
            echo json_encode([
                'status' => 'success',
                'snap_token' => $result['token'],
                'order_id' => $order_id
            ]);
        } else {
            throw new Exception($result['error_messages'][0] ?? 'Gagal membuat transaksi: HTTP ' . $http_code);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    // Handle real order dari database
    try {
        // Ambil data order
        $stmt = $pdo->prepare("
            SELECT * FROM orders 
            WHERE id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order tidak ditemukan');
        }
        
        // Ambil items dari order
        $stmt = $pdo->prepare("
            SELECT oi.*, p.nama_parfum, p.brand 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format items untuk Midtrans
        $item_details = [];
        foreach ($items as $item) {
            $item_details[] = [
                'id' => 'PROD-' . $item['product_id'],
                'price' => (int)$item['harga'],
                'quantity' => (int)$item['jumlah'],
                'name' => substr($item['nama_parfum'] . ' - ' . $item['brand'], 0, 50) // Max 50 karakter
            ];
        }
        
        // Transaction details
        $midtrans_order_id = 'ORDER-' . $order_id . '-' . time();
        
        $transaction_details = [
            'order_id' => $midtrans_order_id,
            'gross_amount' => (int)$order['total_harga']
        ];
        
        // Customer details
        $customer_details = [
            'first_name' => $order['nama_customer'],
            'email' => $order['email_customer'],
            'phone' => $order['telepon_customer']
        ];
        
        // Shipping address (optional)
        if (!empty($order['alamat_customer'])) {
            $customer_details['shipping_address'] = [
                'address' => $order['alamat_customer']
            ];
        }
        
        // Build params untuk Midtrans
        $params = [
            'transaction_details' => $transaction_details,
            'item_details' => $item_details,
            'customer_details' => $customer_details,
            'enabled_payments' => ['credit_card', 'dana'],  // Tambah DANA
            'callbacks' => [
                'finish' => 'https://9b383fac1ef4.ngrok-free.app/utils/finish_payment.php'  // Update ke ngrok URL lo
            ]
        ];
        
        // Request ke Midtrans API
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => MIDTRANS_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
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
        
        // Logging debug
        error_log('Midtrans create snap (real order) HTTP: ' . $http_code);
        error_log('Midtrans create snap response: ' . $response);
        
        if ($curl_error) {
            throw new Exception('CURL Error: ' . $curl_error);
        }
        
        $result = json_decode($response, true);
        
        if ($http_code == 201 && isset($result['token'])) {
            // Simpan snap token ke database
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET snap_token = ?, midtrans_order_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$result['token'], $midtrans_order_id, $order_id]);
            
            echo json_encode([
                'status' => 'success',
                'snap_token' => $result['token'],
                'order_id' => $order_id
            ]);
        } else {
            throw new Exception($result['error_messages'][0] ?? 'Gagal membuat transaksi');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
?>