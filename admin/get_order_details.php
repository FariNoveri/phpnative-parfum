<?php
require_once 'config/auth.php';
requireAdmin();

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    echo '<p>Order ID tidak valid</p>';
    exit;
}

// Get order details
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    echo '<p>Pesanan tidak ditemukan</p>';
    exit;
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.nama_parfum, p.brand 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// WhatsApp link
$wa_number = "6281234567890";
$wa_message = "Halo, ini update untuk pesanan #" . $order['id'] . " atas nama " . $order['nama_customer'] . ". Status saat ini: " . ucfirst($order['status']) . ". Terima kasih!";
$wa_link = "https://wa.me/{$wa_number}?text=" . urlencode($wa_message);
?>

<div class="order-detail-section">
    <h4>📋 Informasi Pesanan</h4>
    <div class="detail-grid">
        <div class="detail-item">
            <div class="detail-label">ID Pesanan:</div>
            <div class="detail-value">#<?= $order['id'] ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Status:</div>
            <div class="detail-value">
                <span class="order-status status-<?= $order['status'] ?>">
                    <?= ucfirst($order['status']) ?>
                </span>
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Tanggal Pesanan:</div>
            <div class="detail-value"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Total Pembayaran:</div>
            <div class="detail-value"><strong><?= formatRupiah($order['total_harga']) ?></strong></div>
        </div>
    </div>
</div>

<div class="order-detail-section">
    <h4>👤 Data Customer</h4>
    <div class="detail-grid">
        <div class="detail-item">
            <div class="detail-label">Nama:</div>
            <div class="detail-value"><?= htmlspecialchars($order['nama_customer']) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Email:</div>
            <div class="detail-value"><?= htmlspecialchars($order['email_customer']) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Telepon:</div>
            <div class="detail-value"><?= htmlspecialchars($order['telepon_customer']) ?></div>
        </div>
    </div>
    <div class="detail-item" style="margin-top: 1rem;">
        <div class="detail-label">Alamat Pengiriman:</div>
        <div class="detail-value"><?= nl2br(htmlspecialchars($order['alamat_customer'])) ?></div>
    </div>
    <?php if ($order['notes']): ?>
        <div class="detail-item" style="margin-top: 1rem;">
            <div class="detail-label">Catatan:</div>
            <div class="detail-value"><?= nl2br(htmlspecialchars($order['notes'])) ?></div>
        </div>
    <?php endif; ?>
</div>

<div class="order-detail-section">
    <h4>🛍️ Detail Produk</h4>
    <table class="order-items-table">
        <thead>
            <tr>
                <th>Produk</th>
                <th>Harga</th>
                <th>Qty</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order_items as $item): ?>
                <tr>
                    <td>
                        <div>
                            <div style="font-weight: bold;"><?= htmlspecialchars($item['nama_parfum']) ?></div>
                            <div style="color: #666; font-size: 0.9rem;"><?= htmlspecialchars($item['brand']) ?></div>
                        </div>
                    </td>
                    <td><?= formatRupiah($item['harga']) ?></td>
                    <td><?= $item['jumlah'] ?></td>
                    <td><strong><?= formatRupiah($item['harga'] * $item['jumlah']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f8f9fa; font-weight: bold;">
                <td colspan="3">TOTAL:</td>
                <td><?= formatRupiah($order['total_harga']) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
    <button onclick="updateStatus(<?= $order['id'] ?>, '<?= $order['status'] ?>')" class="btn btn-warning">
        ✏️ Update Status
    </button>
    <a href="<?= $wa_link ?>" target="_blank" class="btn btn-success">
        📱 Hubungi Customer
    </a>
    <button onclick="window.print()" class="btn">
        🖨️ Print
    </button>
</div>

<style>
.order-detail-section {
    margin-bottom: 2rem;
}

.order-detail-section h4 {
    margin-bottom: 1rem;
    color: #333;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #667eea;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.detail-item {
    margin-bottom: 0.5rem;
}

.detail-label {
    font-weight: bold;
    color: #555;
    margin-bottom: 0.25rem;
}

.detail-value {
    color: #333;
}

.order-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.order-items-table th,
.order-items-table td {
    padding: 0.8rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.order-items-table th {
    background: #f8f9fa;
}

.order-status {
    padding: 0.4rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
    text-align: center;
    display: inline-block;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-processing { background: #cce5ff; color: #004085; }
.status-shipped { background: #e2e3e5; color: #383d41; }
.status-delivered { background: #d1ecf1; color: #0c5460; }
.status-cancelled { background: #f8d7da; color: #721c24; }

.btn {
    background: #667eea;
    color: white;
    padding: 0.8rem 1.5rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
    font-weight: 500;
    text-align: center;
}

.btn:hover {
    background: #5a67d8;
    transform: translateY(-1px);
}

.btn-success { background: #27ae60; }
.btn-success:hover { background: #229954; }

.btn-warning { background: #f39c12; }
.btn-warning:hover { background: #e67e22; }
</style>