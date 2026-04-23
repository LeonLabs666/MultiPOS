# MultiPOS

**MultiPOS** adalah aplikasi **Point of Sale (POS) berbasis web** yang digunakan untuk mengelola transaksi penjualan, produk, stok barang, resep, pesanan dapur, serta laporan penjualan dalam satu sistem terintegrasi.

Aplikasi ini dirancang untuk membantu **toko, warung, cafe, atau usaha kecil** dalam mengelola operasional penjualan dengan lebih mudah, rapi, dan efisien.

---

# Fitur Utama

## Sistem Kasir (POS)

- Transaksi penjualan
- Pencarian produk cepat
- Cetak struk pembelian
- Manajemen pelanggan
- Shift kasir
- Riwayat transaksi
- Pengaturan struk dan kasir

## Manajemen Produk

- Tambah produk
- Edit produk
- Hapus produk
- Kategori produk
- Paket produk
- Manajemen supplier

## Manajemen Stok

- Persediaan barang
- Persediaan bahan baku
- Stok masuk dan keluar
- Riwayat stok
- Stock opname
- Konversi unit
- Perhitungan ulang inventory metrics

## Sistem Dapur

- Ticket pesanan
- Dashboard dapur
- Manajemen resep / BOM
- Persediaan dapur
- Produksi dapur
- Riwayat aktivitas dapur

## Laporan

- Laporan penjualan
- Detail transaksi
- Laporan shift kasir
- Export data
- Activity logs

## Multi Role User

Sistem mendukung beberapa level pengguna:

| Role      | Akses |
|-----------|-------|
| Admin     | Mengelola produk, stok, resep, laporan, pengguna kasir |
| Kasir     | Melakukan transaksi penjualan dan mengelola shift |
| Dapur     | Mengelola ticket pesanan, produksi, dan resep |
| Developer | Mengelola sistem, toko, admin, dan monitoring aktivitas |

---

# Teknologi yang Digunakan

- **PHP**
- **MySQL / MariaDB**
- **HTML**
- **CSS**
- **JavaScript**
- **Bootstrap**

---

# Struktur Project

```bash
MultiPOS/
│
├── advance/                 # Modul utama mode advance
│   ├── admin_*.php
│   ├── kasir_*.php
│   ├── dapur_*.php
│   ├── dev_*.php
│   └── tools/
│
├── basic/                   # Modul mode basic
│   ├── admin_*.php
│   ├── kasir_*.php
│   └── partials/
│
├── config/                  # Konfigurasi aplikasi
│   ├── auth.php
│   ├── db.php
│   ├── inventory.php
│   └── store.php
│
├── publik/                  # Asset dan partial tampilan
│   ├── assets/
│   └── partials/
│
├── sql/
│   └── multipos_db.sql      # File database SQL
│
├── upload/                  # Folder upload logo dan gambar produk
│
├── index.php
├── home.php
├── login.php
├── register.php
└── logout.php
Instalasi
1. Clone Repository
git clone https://github.com/leonardo666oz/MUltiPOS.git

Atau download ZIP lalu ekstrak.

2. Pindahkan ke Web Server

Jika menggunakan XAMPP, pindahkan folder project ke:

htdocs/MultiPOS

Jika menggunakan Laragon, pindahkan folder project ke:

www/MultiPOS
3. Buat Database

Buka phpMyAdmin, lalu buat database baru dengan nama:

multipos_db

Nama database ini harus sesuai dengan konfigurasi pada file config/db.php.

4. Import Database

Masuk ke database multipos_db, lalu import file berikut:

sql/multipos_db.sql

Langkah import:

Buka phpMyAdmin
Pilih database multipos_db
Klik menu Import
Pilih file sql/multipos_db.sql
Klik Go / Import
5. Konfigurasi Database

Edit file berikut:

config/db.php

Contoh konfigurasi default:

<?php
declare(strict_types=1);

$DB_HOST = '127.0.0.1';
$DB_NAME = 'multipos_db';
$DB_USER = 'root';
$DB_PASS = '';

Sesuaikan dengan database di komputer atau hosting Anda.

Contoh jika menggunakan hosting:

$DB_HOST = 'localhost';
$DB_NAME = 'nama_database';
$DB_USER = 'nama_user';
$DB_PASS = 'password_database';
6. Pastikan Web Server dan MySQL Aktif

Jika menggunakan XAMPP:

Jalankan Apache
Jalankan MySQL

Jika menggunakan Laragon:

Start Apache / Nginx
Start MySQL
7. Jalankan Aplikasi

Buka browser dan akses:

http://localhost/MultiPOS

Jika project berada dalam folder lain, sesuaikan URL-nya.

Contoh:

http://localhost/nama-folder-project
Tata Cara Penggunaan Aplikasi
1. Akses Halaman Awal

Saat aplikasi dijalankan, pengguna akan diarahkan ke halaman awal / home, kemudian login melalui:

http://localhost/MultiPOS/login.php

Setelah berhasil login, pengguna akan diarahkan otomatis sesuai role masing-masing.

2. Login Sesuai Role

Aplikasi mendukung beberapa jenis pengguna:

Developer
Admin
Kasir
Dapur

Setiap role memiliki dashboard dan hak akses yang berbeda.

3. Alur Penggunaan untuk Admin

Role Admin digunakan untuk mengatur data utama toko.

Yang bisa dilakukan admin:
Mengelola kategori produk
Menambah, mengedit, dan menghapus produk
Mengelola persediaan barang dan bahan baku
Mengelola resep / BOM
Mengelola supplier
Mengelola stok masuk/keluar
Melakukan stock opname
Melihat laporan penjualan
Melihat laporan shift kasir
Melihat riwayat stok
Mengatur data toko
Membuat akun kasir
Urutan penggunaan yang disarankan untuk Admin:
Login sebagai Admin
Atur pengaturan toko
Tambahkan kategori produk
Tambahkan produk
Jika menggunakan bahan baku, input persediaan bahan
Jika produk menggunakan resep, buat resep / BOM
Tambahkan supplier jika diperlukan
Atur stok awal atau lakukan stok masuk
Buat akun kasir
Pantau transaksi dan laporan secara berkala
4. Alur Penggunaan untuk Kasir

Role Kasir digunakan untuk proses transaksi penjualan harian.

Yang bisa dilakukan kasir:
Membuka shift kasir
Melakukan transaksi penjualan
Mencari produk dengan cepat
Mengelola pelanggan
Memproses checkout
Mencetak struk
Melihat riwayat transaksi
Menutup shift kasir
Mengatur preferensi struk
Urutan penggunaan yang disarankan untuk Kasir:
Login sebagai Kasir
Buka shift kasir
Masuk ke halaman POS
Pilih produk yang dibeli pelanggan
Tambahkan data pelanggan jika diperlukan
Lakukan checkout
Cetak atau tampilkan struk
Lanjutkan transaksi berikutnya
Setelah operasional selesai, lakukan tutup shift
5. Alur Penggunaan untuk Dapur

Role Dapur digunakan untuk menangani pesanan yang masuk dan proses produksi.

Yang bisa dilakukan dapur:
Melihat ticket pesanan masuk
Mengelola proses produksi
Mengelola resep
Mengelola persediaan dapur
Melihat riwayat proses dapur
Mengatur dashboard dapur
Urutan penggunaan yang disarankan untuk Dapur:
Login sebagai Dapur
Buka dashboard dapur
Cek ticket pesanan yang masuk
Proses pesanan sesuai antrian
Update status pesanan jika diperlukan
Gunakan data resep sebagai acuan produksi
Pantau persediaan bahan dapur
6. Alur Penggunaan untuk Developer

Role Developer digunakan untuk pengelolaan sistem secara menyeluruh.

Yang bisa dilakukan developer:
Membuat admin baru
Melihat daftar toko
Melihat detail toko
Reset password admin
Menghapus toko
Melihat activity logs
Monitoring aktivitas sistem
Urutan penggunaan yang disarankan untuk Developer:
Login sebagai Developer
Buka dashboard developer
Buat atau kelola store
Buat akun admin
Pantau aktivitas setiap toko
Lakukan reset akun admin jika diperlukan
Contoh Alur Operasional Lengkap

Berikut contoh penggunaan aplikasi dari awal sampai transaksi berjalan:

Tahap Setup Awal
Developer membuat atau menyiapkan toko
Admin login ke sistem
Admin menambahkan kategori produk
Admin menambahkan produk
Admin menambahkan bahan baku
Admin membuat resep / BOM
Admin mengisi stok awal
Admin membuat akun kasir
Tahap Operasional Harian
Kasir login
Kasir membuka shift
Kasir melayani transaksi pelanggan
Sistem mencatat transaksi penjualan
Ticket pesanan masuk ke bagian dapur
Dapur memproses pesanan
Admin memantau laporan penjualan
Kasir menutup shift
Admin mengecek laporan shift dan laporan penjualan
Mode Sistem

Project ini memiliki dua pendekatan modul:

Basic

Digunakan untuk kebutuhan POS yang lebih sederhana, seperti:

Produk
Kategori
Persediaan
Penjualan
Shift kasir
Laporan dasar

Folder utama:

basic/
Advance

Digunakan untuk kebutuhan yang lebih lengkap, seperti:

Multi role lebih lengkap
Resep / BOM
Sistem dapur
Supplier
Stock opname
Riwayat aktivitas
Developer tools
Monitoring toko

Folder utama:

advance/
Catatan Penting
Pastikan database sudah di-import sebelum aplikasi dijalankan.
Pastikan file config/db.php sudah sesuai dengan server Anda.
Pastikan folder upload dapat diakses oleh server.
Jika muncul error DB connection failed, periksa:
host database
nama database
username
password
service MySQL aktif atau tidak
Pengembangan Selanjutnya


Kontribusi sangat terbuka untuk pengembangan project ini.


Project ini menggunakan lisensi MIT License.

Jika project ini membantu, jangan lupa beri star di repository.
