# 📷 Product Images Directory

Folder ini digunakan untuk menyimpan gambar produk yang diupload melalui admin panel.

## 📁 Struktur Folder
```
assets/
└── images/
    └── products/
        ├── .htaccess (security)
        ├── README.md (file ini)
        └── product_*.jpg/png/gif/webp (gambar produk)
```

## ⚙️ Konfigurasi Upload
- **Ukuran maksimal**: 5MB per file
- **Format yang diizinkan**: JPG, PNG, GIF, WEBP
- **Auto resize**: Gambar akan diresize ke maksimal 800x800px
- **Nama file**: Otomatis generate dengan format `product_timestamp_random.ext`

## 🔒 Keamanan
- File PHP tidak bisa dieksekusi di folder ini
- Directory browsing dinonaktifkan
- Hanya file gambar yang bisa diakses
- Cache headers diset untuk performa optimal

## 🚀 Penggunaan
1. Login sebagai admin
2. Masuk ke **Kelola Produk**
3. Tambah/Edit produk
4. Upload gambar di form produk
5. Gambar akan otomatis disimpan di folder ini

## 🐛 Troubleshooting
Jika upload gagal, pastikan:
- Folder ini writable (chmod 755 atau 775)
- PHP GD extension aktif untuk resize
- `upload_max_filesize` dan `post_max_size` di php.ini cukup besar
- Folder assets/images/products/ sudah ada

---
*Auto-generated folder untuk sistem toko parfum premium*