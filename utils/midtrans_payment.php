<?php
// utils/midtrans_payment.php - No output before header!

if (ob_get_level()) {
    ob_end_clean(); // Clear buffer to prevent premature output
}

session_start();
require_once '../config/database.php';
require_once '../config/midtrans_config.php';

header('Content-Type: application/json');

// Check if order_id is provided
if (!isset($_POST['order_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Order ID tidak ditemukan'
    ]);
    exit;
}

$order_id = $_POST['order_id'];

// Handle temp order from session
if (strpos($order_id, 'temp_') === 0) {
    if (!isset($_SESSION['pending_order']) || 
        !isset($_SESSION['pending_temp_order_id']) || 
        $_SESSION['pending_temp_order_id'] !== $order_id) {
        echo json_encode(['status' => 'error', 'message' => 'Order tidak ditemukan']);
        exit;
    }

    try {
        $pending = $_SESSION['pending_order'];
        
        // Format items for Midtrans
        $item_details = [];
        foreach ($pending['items'] as $item) {
            $item_details[] = [
                'id' => 'PROD-' . $item['product_id'],
                'price' => (int)$item['harga'],
                'quantity' => (int)$item['jumlah'],
                'name' => substr($item['nama_parfum'], 0, 50) // Remove brand reference
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
        
        // Build params for Midtrans
        $params = [
            'transaction_details' => $transaction_details,
            'item_details' => $item_details,
            'customer_details' => $customer_details,
            'enabled_payments' => ['credit_card', 'dana'],
            'callbacks' => [
                'finish' => 'https://c883c79de34c.ngrok-free.app/utils/finish_payment.php'
            ]
        ];
        
        // Request to Midtrans API
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
        
        // Log for debugging
        error_log('Midtrans create snap HTTP: ' . $http_code);
        error_log('Midtrans create snap response: ' . $response);
        
        if ($curl_error) {
            throw new Exception('CURL Error: ' . $curl_error);
        }
        
        $result = json_decode($response, true);
        
        if ($http_code == 201 && isset($result['token'])) {
            // Store snap token in session
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
        error_log('Midtrans payment error: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    // Handle real order from database
    try {
        // Fetch order
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order tidak ditemukan');
        }
        
        // Fetch order items
        $stmt = $pdo->prepare("
            SELECT oi.*, p.nama_parfum 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format items for Midtrans
        $item_details = [];
        foreach ($items as $item) {
            $item_details[] = [
                'id' => 'PROD-' . $item['product_id'],
                'price' => (int)$item['harga'],
                'quantity' => (int)$item['jumlah'],
                'name' => substr($item['nama_parfum'], 0, 50) // Remove brand reference
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
        
        // Build params for Midtrans
        $params = [
            'transaction_details' => $transaction_details,
            'item_details' => $item_details,
            'customer_details' => $customer_details,
            'enabled_payments' => ['credit_card', 'dana'],
            'callbacks' => [
                'finish' => 'https://c883c79de34c.ngrok-free.app/utils/finish_payment.php'
            ]
        ];
        
        // Request to Midtrans API
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
        
        // Log for debugging
        error_log('Midtrans create snap (real order) HTTP: ' . $http_code);
        error_log('Midtrans create snap response: ' . $response);
        
        if ($curl_error) {
            throw new Exception('CURL Error: ' . $curl_error);
        }
        
        $result = json_decode($response, true);
        
        if ($http_code == 201 && isset($result['token'])) {
            // Store snap token in database
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
        error_log('Midtrans payment (real order) error: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
?>
