# 🌸 TOKO PARFUM PREMIUM - Panduan Instalasi

Website toko parfum lengkap dengan sistem admin dan integrasi WhatsApp untuk konfirmasi pembayaran.

## 🚀 Fitur Utama

### Frontend (Customer):
- ✅ **Landing Page** dengan katalog produk
- ✅ **System Login & Register** 
- ✅ **Shopping Cart** dengan session management
- ✅ **Checkout** (bisa login atau guest)
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

## 📁 Struktur File

```
htdocs/toko-parfum/
├── config/
│   └── database.php              # Konfigurasi database
├── admin/
│   ├── config/
│   │   └── auth.php             # Admin authentication
│   ├── dashboard.php            # Admin dashboard
│   ├── products.php             # Kelola produk
│   ├── orders.php               # Kelola pesanan
│   ├── users.php                # Kelola user
│   └── get_order_details.php    # AJAX detail pesanan
├── index.php                    # Homepage/katalog
├── login.php                    # Login customer
├── register.php                 # Register customer
├── cart.php                     # Shopping cart
├── checkout.php                 # Checkout process
├── order_confirmation.php       # Konfirmasi setelah checkout
├── orders.php                   # Daftar pesanan customer
├── order_detail.php             # Detail pesanan customer
├── add_to_cart.php              # Add to cart handler
├── admin_login.php              # Login admin
├── logout.php                   # Logout handler
└── database.sql                 # Database schema
```

## 🛠️ Cara Install

### 1. Persiapkan Environment
- Install **XAMPP** (PHP 7.4+ & MySQL)
- Start **Apache** dan **MySQL** di XAMPP Control Panel

### 2. Setup Database
1. Buka **phpMyAdmin** (http://localhost/phpmyadmin)
2. Create database baru: `toko_parfum`
3. Import/jalankan script SQL untuk membuat tabel:

```sql
-- Database untuk Toko Parfum
CREATE DATABASE toko_parfum;
USE toko_parfum;

-- Tabel users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    telepon VARCHAR(20),
    alamat TEXT,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel produk parfum
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_parfum VARCHAR(100) NOT NULL,
    brand VARCHAR(50) NOT NULL,
    harga DECIMAL(10,2) NOT NULL,
    stok INT NOT NULL,
    deskripsi TEXT,
    gambar VARCHAR(255),
    kategori ENUM('pria', 'wanita', 'unisex') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel keranjang
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 1,
    session_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabel orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    nama_customer VARCHAR(100) NOT NULL,
    email_customer VARCHAR(100) NOT NULL,
    telepon_customer VARCHAR(20) NOT NULL,
    alamat_customer TEXT NOT NULL,
    total_harga DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_proof VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabel detail order
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    jumlah INT NOT NULL,
    harga DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabel admin logs
CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert admin user (password: admin123)
INSERT INTO users (nama, email, password, telepon, alamat, role) VALUES 
('Administrator', 'admin@parfumpremium.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567890', 'Kantor Toko Parfum Premium', 'admin');

-- Insert sample products
INSERT INTO products (nama_parfum, brand, harga, stok, deskripsi, kategori) VALUES
('Black Orchid', 'Tom Ford', 2500000.00, 10, 'Parfum mewah dengan aroma oriental yang eksotis', 'unisex'),
('Sauvage', 'Dior', 1800000.00, 15, 'Fresh dan maskulin, cocok untuk pria modern', 'pria'),
('Chanel No. 5', 'Chanel', 2200000.00, 8, 'Parfum ikonik untuk wanita elegan', 'wanita'),
('Acqua di Gio', 'Giorgio Armani', 1600000.00, 12, 'Aroma segar seperti laut mediterania', 'pria'),
('Miss Dior', 'Dior', 1900000.00, 20, 'Floral dan feminin untuk wanita', 'wanita'),
('One Million', 'Paco Rabanne', 1400000.00, 18, 'Parfum berani dan memikat', 'pria');
```

### 3. Setup Files
1. Copy semua file ke folder `htdocs/toko-parfum/`
2. Edit `config/database.php` jika perlu (sesuaikan dengan setting MySQL):

```php
$host = 'localhost';
$dbname = 'toko_parfum';
$username = 'root';
$password = ''; // Kosongkan jika default XAMPP
```

### 4. Konfigurasi WhatsApp
Edit nomor WhatsApp di file-file berikut:
- `order_confirmation.php` (line ~89)
- `orders.php` (line ~67)
- `admin/get_order_details.php` (line ~34)

Ganti `6281234567890` dengan nomor WA toko Anda.

## 🔑 Login Credentials

### Admin Login:
- **URL**: `http://localhost/toko-parfum/admin_login.php`
- **Email**: `admin@parfumpremium.com`
- **Password**: `admin123`

### Customer:
- Daftar langsung di website atau buat manual di database

## 🌐 URL Akses

- **Website**: `http://localhost/toko-parfum/`
- **Admin Panel**: `http://localhost/toko-parfum/admin_login.php`

## ⚙️ Kustomisasi

### 1. Ganti Logo & Brand
Edit di file `index.php`, `admin/dashboard.php`, etc:
```php
<div class="logo">🌸 Parfum Premium</div>
```

### 2. Ubah Warna Tema
Edit CSS di setiap file, cari:
```css
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

### 3. Tambah Metode Pembayaran
Edit di `checkout.php` section payment instructions.

### 4. Kustomisasi Status Pesanan
Edit enum di database dan update status handling di file terkait.

## 🚨 Security Notes

1. **Ganti Password Admin** setelah install
2. **Gunakan HTTPS** di production
3. **Update PHP** ke versi terbaru
4. **Backup Database** secara berkala
5. **Validasi Input** sudah ada, tapi bisa diperkuat

## 📱 WhatsApp Integration

Sistem menggunakan WhatsApp Web API (`wa.me`) untuk:
- Konfirmasi pembayaran otomatis
- Customer support
- Update status pesanan

Format pesan otomatis sudah diatur untuk memberikan informasi lengkap pesanan.

## 🎯 Fitur Advanced (Optional)

Anda bisa menambah:
- Upload gambar produk
- Payment gateway integration
- Email notifications
- SMS gateway
- Multi-store support
- Analytics dashboard
- Export reports
- Inventory alerts

## 🐛 Troubleshooting

### Database Connection Error:
- Check XAMPP MySQL service
- Verify database name and credentials

### Session Issues:
- Check PHP session configuration
- Clear browser cookies

### WhatsApp Not Working:
- Verify phone number format
- Test manually with `wa.me/6281234567890`

### Admin Can't Login:
- Check user role in database
- Reset password via database

## 💡 Tips Penggunaan

1. **Backup** database sebelum update besar
2. **Test** fitur di localhost dulu sebelum production
3. **Monitor** log aktivitas admin
4. **Update** stok produk secara berkala
5. **Response** cepat konfirmasi WA customer

---

## 🎉 Selamat!

Website toko parfum Anda sudah siap digunakan! 

**Happy Selling!** 🌸💰

---

*Dibuat dengan ❤️ untuk bisnis parfum Indonesia*
