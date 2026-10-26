# Checklist pengujian manual

Mulai aplikasi dengan `docker compose up --build`, lalu buka `http://localhost:8080`.

- Pastikan halaman awal adalah dashboard pengelola yang menampilkan ringkasan jumlah buku, eksemplar, anggota, peminjaman aktif, stok terendah, dan aksi operasional.
- Tambahkan buku, ubah judul atau stoknya, pastikan perubahan terlihat di daftar dan dashboard, lalu hapus buku yang tidak memiliki transaksi peminjaman.
- Daftarkan anggota, ubah nama/email/teleponnya, pastikan data baru terlihat pada daftar, lalu hapus anggota yang tidak memiliki catatan peminjaman.
- Catat peminjaman buku untuk anggota yang terdaftar. Pastikan peminjaman aktif muncul pada halaman pengembalian dan stok berkurang satu.
- Pastikan jatuh tempo awal adalah tanggal pinjam + 14 hari. Coba pinjam buku keempat dan pastikan ditolak tanpa mengubah stok.
- Pastikan anggota dengan setidaknya satu pinjaman yang jatuh temponya sudah lewat tidak dapat meminjam sampai semua buku terlambat dikembalikan.
- Coba meminjam buku yang stoknya nol dan pastikan transaksi ditolak tanpa mengubah data.
- Perpanjang pinjaman sebelum jatuh tempo dan tanpa antrean; pastikan jatuh tempo bertambah 14 hari. Pastikan perpanjangan kedua, perpanjangan pinjaman terlambat, dan perpanjangan judul berantrean ditolak.
- Pastikan perpanjangan menolak judul dengan antrean aktif.
- Saat sebuah judul habis stoknya, masukkan dua anggota ke antrean dan pastikan urutan reservasinya FIFO.
- Catat pengembalian. Pastikan tanggal kembali tersimpan dan stok bertambah satu, atau stok tetap tertahan saat eksemplar dialokasikan ke anggota pertama dalam antrean.
- Pastikan pengambilan reservasi siap membuat pinjaman 14 hari tanpa mengurangi stok dua kali.
- Uji pinjaman terlambat dengan mengatur DueDate ke tanggal lampau melalui database; pastikan denda Rp5.000 per hari kalender dan jumlah final tercatat saat pengembalian.
- Coba mengembalikan transaksi yang sama lagi; halaman harus menolak permintaan dan stok tidak boleh bertambah lagi.
- Pastikan penghapusan buku/anggota yang masih direferensikan transaksi ditolak oleh constraint basis data dengan pesan yang jelas.
- Ubah token CSRF formulir secara manual dan pastikan perubahan data ditolak.
