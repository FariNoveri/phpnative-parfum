🌸 TOKO PARFUM PREMIUM - Panduan Instalasi & Penggunaan (Update 2025)

Website e-commerce parfum premium dengan sistem admin lengkap, integrasi **Midtrans Payment Gateway** (sandbox/production), dan WhatsApp untuk konfirmasi. Update ini termasuk fitur payment gateway, struktur folder baru (utils/), dan fix bug umum.

## 🚀 Fitur Utama (Update)

### Frontend (Customer):
- ✅ **Landing Page** dengan katalog produk & pencarian
- ✅ **Login/Register** dengan session management
- ✅ **Shopping Cart** (user/guest via session_id)
- ✅ **Checkout** dengan form data & AJAX validation
- ✅ **Midtrans Integration**: Pembayaran via Credit Card, DANA, dll (sandbox test)
- ✅ **Order Tracking** dengan status timeline
- ✅ **WhatsApp Integration** untuk konfirmasi pembayaran
- ✅ **Responsive Design** (mobile-friendly)

### Backend (Admin):
- ✅ **Admin Dashboard** dengan statistik lengkap
- ✅ **Manajemen Produk** (CRUD produk)
- ✅ **Manajemen Pesanan** (update status, detail order)
- ✅ **Manajemen User** dengan analytics
- ✅ **Admin Activity Logs**
- ✅ **WhatsApp Integration** untuk komunikasi dengan customer

## 📁 Struktur File (Update dengan folder utils/)
```
htdocs/toko-parfum/
├── config/
│   ├── database.php              # Konfigurasi database
│   └── midtrans_config.php       # Midtrans keys & URL (sandbox/production)
├── admin/
│   ├── config/
│   │   └── auth.php              # Admin authentication
│   ├── dashboard.php             # Admin dashboard
│   ├── products.php              # Kelola produk
│   ├── orders.php                # Kelola pesanan
│   ├── users.php                 # Kelola user
│   └── get_order_details.php     # AJAX detail pesanan
├── utils/                        # Folder baru untuk AJAX & payment handlers
│   ├── checkout_ajax.php         # Simpan pending order ke session
│   ├── midtrans_payment.php      # Generate Midtrans snap token
│   ├── payment_callback.php      # Create real order setelah payment
│   ├── check_payment_status.php  # Fallback check status
│   ├── finish_payment.php        # Handle redirect finish dari Midtrans
│   └── midtrans_notification.php # Webhook notification dari Midtrans (opsional)
├── index.php                     # Homepage/katalog
├── login.php                     # Login customer
├── register.php                  # Register customer
├── cart.php                      # Shopping cart
├── checkout.php                  # Checkout process dengan Midtrans JS
├── order_confirmation.php        # Konfirmasi setelah checkout
├── orders.php                    # Daftar pesanan customer
├── order_detail.php              # Detail pesanan customer
├── add_to_cart.php               # Add to cart handler
├── admin_login.php               # Login admin
├── logout.php                    # Logout handler
└── toko_parfum.sql               # Database schema (update dengan kolom Midtrans)
```
## 🛠️ Cara Install

### 1. Persiapkan Environment
- Install **XAMPP** (PHP 8.2+ & MariaDB/MySQL)
- Start **Apache** dan **MySQL** di XAMPP Control Panel
- Install **Composer** kalau perlu extension (opsional)
- Untuk test online: Install **ngrok** (`ngrok http 80`) untuk expose localhost ke internet (untuk Midtrans callbacks).

### 2. Setup Database
1. Buka **phpMyAdmin** (http://localhost/phpmyadmin)
2. Create database baru: `toko_parfum`
3. Import script SQL `toko_parfum.sql` untuk membuat tabel (sudah include kolom midtrans_order_id, snap_token, dll di table orders).

### 3. Setup Files
1. Copy semua file ke folder `htdocs/toko-parfum/`
2. Edit `config/database.php` (sesuaikan DB credentials):
   ```
   $host = 'localhost';
   $dbname = 'toko_parfum';
   $username = 'root';
   $password = ''; // Kosongkan jika default XAMPP
   ```
3. Edit `config/midtrans_config.php` (dari dashboard Midtrans sandbox):
   ```
   define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-XXXXXXXXXXXXXXXXXXXX');
   define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-XXXXXXXXXXXXXXXXXXXX');
   define('MIDTRANS_API_URL', 'https://api.sandbox.midtrans.com/v2/charge'); // Ganti ke production nanti
   define('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/snap.js'); // Sandbox
   ```

### 4. Konfigurasi Midtrans
- Daftar di https://dashboard.sandbox.midtrans.com (sandbox mode).
- Copy Client & Server Key ke config.
- Di checkout.php, Midtrans Snap JS otomatis load dengan client key.
- Untuk production: Ganti URL & key ke production, enable HTTPS.

### 5. Konfigurasi WhatsApp
Edit nomor WhatsApp di file-file berikut:
- `order_confirmation.php` (line ~89)
- `orders.php` (line ~67)
- `admin/get_order_details.php` (line ~34)
Ganti `6281234567890` dengan nomor WA toko Anda (format internasional tanpa +).

## 🔑 Login Credentials

### Admin Login:
- **URL**: `http://localhost/toko-parfum/admin_login.php`
- **Email**: `admin@parfumpremium.com`
- **Password**: `admin123`

### Customer:
- Daftar langsung di website atau buat manual di database.

## 🌐 URL Akses

- **Website**: `http://localhost/toko-parfum/`
- **Admin Panel**: `http://localhost/toko-parfum/admin_login.php`
- **Ngrok Test**: `https://your-ngrok-id.ngrok-free.app/` (untuk Midtrans callbacks).

## ⚙️ Kustomisasi

### 1. Ganti Logo & Brand
Edit di file `index.php`, `admin/dashboard.php`, dll:
```
<div class="logo">🌸 Parfum Premium</div>
```

### 2. Ubah Warna Tema
Edit CSS di setiap file, cari:
```
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

### 3. Tambah Metode Pembayaran
Edit di `utils/midtrans_payment.php` bagian enabled_payments: `['credit_card', 'dana', 'gopay', 'bank_transfer']`.

### 4. Kustomisasi Status Pesanan
Edit enum di database dan update status handling di `utils/payment_callback.php`.

## 🚨 Security Notes

1. **Ganti Password Admin** setelah install.
2. **Gunakan HTTPS** di production (SSL certificate).
3. **Update PHP** ke versi terbaru.
4. **Backup Database** secara berkala.
5. **Validasi Input** sudah ada, tapi bisa diperkuat dengan CSRF token.
6. **Midtrans Keys**: Jangan commit ke Git, gunakan .env kalau perlu.

## 📱 WhatsApp Integration

Sistem menggunakan WhatsApp Web API (`wa.me`) untuk:
- Konfirmasi pembayaran otomatis.
- Customer support.
- Update status pesanan.

Format pesan otomatis sudah diatur untuk memberikan informasi lengkap pesanan.

## 🎯 Fitur Advanced (Optional)

Anda bisa menambah:
- Upload gambar produk (product_images table).
- Email notifications (PHPMailer).
- SMS gateway.
- Multi-language support.
- Inventory alerts via email.
- Export reports PDF/Excel.
- Midtrans Webhook untuk update status otomatis (utils/midtrans_notification.php).

## 🐛 Troubleshooting

### Database Connection Error:
- Check XAMPP MySQL service.
- Verify database name and credentials di config/database.php.

### Session Issues:
- Check PHP session configuration (php.ini session.save_path writable).
- Clear browser cookies atau gunakan incognito.

### Midtrans Snap Error:
- Console "snap undefined": Cek client key benar & internet.
- 404 di midtrans_payment.php: Pastiin path utils/ benar, require ../config/.
- JSON Parse Error: Tambah ob_clean() di file utils/, matikan display_errors = Off di php.ini.
- 404 Transaction: Gunakan Credit Card untuk test sandbox (bypass DANA bug).

### WhatsApp Not Working:
- Verify phone number format (wa.me/628...).
- Test manual dengan link wa.me.

### Admin Can't Login:
- Check user role in database (role = 'admin').
- Reset password via database (hash dengan password_hash).

### Payment Flow Gagal:
- Cek error_log PHP (XAMPP/logs/php_error.log).
- Gunakan ngrok untuk callbacks, update URL di midtrans_payment.php.
- Test Credit Card: Card 4811 1111 1111 1114, expiry 12/25, CVV 123 → Success di 3DS.

## 💡 Tips Penggunaan

1. **Backup** database sebelum update besar.
2. **Test** fitur di localhost/sandbox dulu sebelum production.
3. **Monitor** log aktivitas admin & error_log.
4. **Update** stok produk secara berkala.
5. **Response** cepat konfirmasi WA customer.
6. **Production Switch**: Ganti MIDTRANS_API_URL & SNAP_URL ke production, ganti key, enable webhook.

## 🎉 Selamat!

Website toko parfum Anda sudah siap digunakan dengan payment gateway! 

**Happy Selling!** 🌸💰

---

*Dibuat dengan ❤️ untuk bisnis parfum Indonesia - Update dengan Midtrans & Utils Folder*
