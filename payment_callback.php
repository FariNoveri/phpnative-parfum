<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Terima data dari frontend setelah pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? null;
    $transaction_status = $_POST['transaction_status'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? null;
    
    if (!$order_id || !$transaction_status) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Data tidak lengkap'
        ]);
        exit;
    }
    
    try {
        // Update status pembayaran berdasarkan response dari Midtrans
        $payment_status = 'pending';
        $order_status = 'pending';
        
        switch ($transaction_status) {
            case 'capture':
            case 'settlement':
                $payment_status = 'paid';
                $order_status = 'confirmed';
                break;
            case 'pending':
                $payment_status = 'pending';
                $order_status = 'pending';
                break;
            case 'deny':
            case 'cancel':
            case 'expire':
                $payment_status = 'failed';
                $order_status = 'cancelled';
                break;
        }
        
        // Update database
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status_pembayaran = ?,
                status = ?,
                midtrans_transaction_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$payment_status, $order_status, $transaction_id, $order_id]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Status pembayaran berhasil diupdate',
            'payment_status' => $payment_status
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal update status: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}