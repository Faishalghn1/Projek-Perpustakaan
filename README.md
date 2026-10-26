# Sistem Perpustakaan Pustaka Digital

Aplikasi administrasi perpustakaan berbasis PHP 8.2, Apache, MariaDB 10.6, dan Docker Compose. Antarmuka dipusatkan pada kebutuhan pemegang perpustakaan: ringkasan operasional, pengelolaan buku dan anggota, serta transaksi peminjaman dan pengembalian. Data disimpan di MariaDB.

## Menjalankan aplikasi

Prasyarat: Docker Desktop dengan Docker Compose.

1. Buat file konfigurasi lokal dari templat:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Ubah nilai `DB_ROOT_PASSWORD` dan `DB_PASSWORD` di `.env`.
3. Jalankan dari direktori akar proyek:

   ```powershell
   docker compose up --build
   ```

4. Buka:
   - Aplikasi: <http://localhost:8080>
   - phpMyAdmin: <http://localhost:8081>

Skema dan data awal dimuat dari `mysql/init.sql` ketika volume MariaDB pertama kali dibuat. Untuk menghentikan layanan, tekan `Ctrl+C` atau jalankan `docker compose down`. Volume database tetap disimpan agar data tidak hilang.

> Halaman pengelolaan belum memiliki autentikasi/otorisasi. Gunakan hanya untuk pengembangan di lingkungan tepercaya; jangan paparkan ke internet atau jaringan publik sebelum kontrol akses ditambahkan.

### Memperbarui database yang sudah ada

Jika volume database dibuat sebelum aturan peminjaman ditambahkan, jalankan migrasi satu kali dari akar proyek. Migrasi mempertahankan transaksi lama dan menetapkan jatuh tempo lama sebagai tanggal pinjam + 14 hari:

```powershell
Get-Content -Raw .\mysql\migrations\001_lending_rules.sql | docker compose exec -T db sh -c 'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
```

Jangan jalankan migrasi yang sama lebih dari sekali. Database baru memperoleh skema terbaru langsung dari `mysql/init.sql`.

## Struktur direktori

```text
Projek-Perpustakaan/
├── .github/
│   └── workflows/
│       └── ci.yml              Pemeriksaan sintaks PHP otomatis (GitHub Actions)
├── mysql/
│   ├── init.sql                Skema Books, Members, Loans, Reservations dan data awal
│   └── migrations/
│       └── 001_lending_rules.sql Migrasi aturan pinjam untuk database lama
├── src/
│   ├── config/
│   │   └── db.php              Koneksi MariaDB menggunakan PDO dan variabel lingkungan
│   ├── books/
│   │   ├── list.php            Daftar, hapus, dan akses ubah buku
│   │   ├── create.php          Formulir tambah buku
│   │   ├── edit.php            Formulir ubah buku
│   │   └── detail.php          Detail buku untuk katalog publik
│   ├── members/
│   │   ├── list.php            Daftar, ubah, dan hapus anggota
│   │   ├── create.php          Formulir registrasi anggota
│   │   └── edit.php            Formulir ubah data anggota
│   ├── loans/
│   │   ├── borrow.php          Transaksi peminjaman buku
│   │   ├── return.php          Pengembalian, denda, dan perpanjangan
│   │   └── queue.php           Antrean reservasi FIFO
│   ├── includes/
│   │   ├── admin.php           Validasi bersama untuk formulir pengelolaan
│   │   └── view.php            Escape HTML, CSRF, dan komponen tampilan bersama
│   ├── assets/
│   │   ├── css/style.css       Tampilan responsif
│   │   └── js/app.js           Interaksi navigasi
│   └── index.php               Dashboard pengelola perpustakaan
├── .env.example                Templat variabel lingkungan
├── .gitignore                  Daftar berkas yang diabaikan Git
├── Dockerfile                  PHP 8.2 Apache dengan PDO/MySQLi
├── docker-compose.yml          Web, MariaDB 10.6, dan phpMyAdmin
└── README.md                   Dokumentasi proyek
```

## Fitur

- **Buku:** CRUD lengkap—daftar, tambah, ubah, dan hapus buku.
- **Anggota:** CRUD lengkap—daftar, registrasi, ubah, dan hapus anggota.
- **Aturan peminjaman:** 14 hari kalender (WIB), maksimal 3 buku aktif per anggota, dan peminjaman baru diblokir selama ada pinjaman yang terlambat.
- **Denda:** Rp5.000 per hari kalender setelah tanggal jatuh tempo (WIB); jumlah final disimpan saat buku dikembalikan. Pengembalian pada tanggal jatuh tempo tidak dikenai denda.
- **Perpanjangan:** satu kali selama 14 hari per pinjaman; tidak tersedia setelah jatuh tempo atau jika judul memiliki antrean aktif.
- **Antrean reservasi:** ketika semua eksemplar sedang dipinjam/direservasi, anggota dapat masuk antrean FIFO. Buku yang kembali ditahan otomatis untuk anggota berikutnya; pinjaman baru dibuat ketika reservasi siap diambil.
- **Transaksi:** peminjaman, pengembalian, perpanjangan, dan alokasi antrean dijalankan secara atomik di database.
- **Dashboard pengelola:** ringkasan judul, eksemplar, anggota, peminjaman aktif, serta daftar transaksi aktif dan stok terendah.
- **Keamanan input:** query database menggunakan prepared statements, output HTML di-escape, formulir perubahan memakai token CSRF, dan nilai formulir divalidasi di server.
- **CI:** GitHub Actions menjalankan pemeriksaan sintaks untuk semua berkas PHP di `src/`.

## Pengujian manual

Checklist tersedia di [`tests/README.md`](./tests/README.md). Uji alur tambah/ubah/hapus buku dan anggota, peminjaman buku tersedia, penolakan stok kosong, pengembalian, serta percobaan mengembalikan pinjaman yang sama dua kali.
