# Sistem Perpustakaan Demo

[English](README.md) · **Bahasa Indonesia**

Aplikasi perpustakaan berbasis PHP dan MySQL/MariaDB. Pengunjung dapat melihat katalog, sementara petugas dapat mengelola buku dan anggota, mencatat peminjaman dan pengembalian, serta menangani denda.

## Demo GitHub Pages

[Demo katalog](https://rayyanif.github.io/sistem-perpustakaan-demo/) memuat empat contoh buku fiktif. Anda bisa mencoba pencarian dan filter ketersediaan langsung di browser. Untuk masuk sebagai petugas atau mencatat transaksi, jalankan aplikasi PHP dengan basis data sendiri.

## Menjalankan aplikasi lengkap dengan XAMPP

1. Unduh atau kloning repositori ini ke `C:\xampp\htdocs\perpustakaan_terpadu`.
2. Buka XAMPP Control Panel, lalu jalankan **Apache** dan **MySQL**.
3. Buka [phpMyAdmin](http://localhost/phpmyadmin/), pilih **Import**, lalu impor [`schema.sql`](schema.sql). File ini membuat basis data `database_perpustakaan` dengan lima tabel kosong. Gunakan basis data baru untuk uji coba.
4. Jika pengaturan basis data Anda berbeda dari bawaan XAMPP, atur `LIBRARY_DB_HOST`, `LIBRARY_DB_PORT`, `LIBRARY_DB_NAME`, `LIBRARY_DB_USER`, dan `LIBRARY_DB_PASSWORD` di lingkungan server PHP, atau sesuaikan `config.php` lokal.
5. Buka [katalog PHP](http://localhost/perpustakaan_terpadu/index.php). Tambahkan data sendiri atau ikuti langkah di bawah untuk mulai menguji fitur petugas.

### Membuat akun petugas dan rak untuk uji coba

Jalankan perintah berikut di PowerShell, lalu masukkan kata sandi uji coba pilihan Anda untuk membuat hash.

```powershell
& 'C:\xampp\php\php.exe' -r "echo password_hash(readline('Kata sandi uji coba: '), PASSWORD_DEFAULT), PHP_EOL;"
```

Di phpMyAdmin, pilih basis data `database_perpustakaan` dan buka tab **SQL**. Ganti `PASTE_GENERATED_HASH` dengan hash yang ditampilkan di PowerShell, lalu jalankan perintah berikut satu kali pada basis data baru.

```sql
INSERT INTO petugas (id_petugas, nama_petugas, password)
VALUES ('DEMO01', 'Petugas Demo', 'PASTE_GENERATED_HASH');

INSERT INTO rak (id_rak, lokasi_rak)
VALUES ('RAK01', 'Rak A-01');
```

Masuk dengan ID petugas `DEMO01` dan kata sandi yang Anda pilih. Tambahkan satu buku dan satu anggota, lalu pilih **Catat peminjaman** untuk mencoba alurnya.

Contoh buku di demo statis terpisah dari basis data SQL Anda. Simpan kredensial lokal dan hasil ekspor yang memuat data asli anggota, peminjaman, atau petugas di luar repositori.
