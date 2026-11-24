# JB GARAGE — Catatan Modifikasi

## Cara menjalankan (XAMPP)
1. Salin folder `jb_garage` ke `htdocs`, nyalakan Apache + MySQL.
2. Import `database.sql` lewat phpMyAdmin (aman diimpor ulang pada database lama: memakai `IF NOT EXISTS` dan tidak menduplikasi data contoh).
3. Buka `http://localhost/jb_garage/`.

Akun demo (password `Demo1234`): `penjual@jbgarage.test`, `pembeli@jbgarage.test`, `pembeli2@jbgarage.test`.

## Perubahan
1. **Pembeli**: hanya melihat Katalog Iklan (status Aktif) dan daftar Inspektor yang berstatus Tersedia. Dashboard tiruan (riwayat pembelian, dll.) dihapus.
2. **Chat penjual**: klik iklan, isi pesan (kolom tawaran harga opsional untuk nego), lalu lanjut di `chat.html`. Pesan baru diperbarui otomatis tiap 4 detik; badge pesan belum dibaca ada di menu akun.
3. **Penjual**: `dashboard.html` berisi tab Katalog Saya, Inspektor, dan Chat & Negosiasi (pembeli dikelompokkan per iklan, lengkap dengan tawaran terakhir dan pesan belum dibaca).
4. **Foto iklan**: maksimal 20 foto (validasi di `jual.html`, `assets/app.js`, dan `api/listings.php`). File `.htaccess` baru menaikkan batas upload PHP; jika tidak berlaku, atur `post_max_size` dan `max_file_uploads` di `php.ini`.
5. **Konsep dana ditahan dihapus** dari semua teks dan tampilan (Trust Score/KYC yang hanya tampilan tiruan juga ikut dihapus).
6. **Database** (`database.sql`): tabel baru `inspectors`, `conversations`, `messages`; tabel `user_sessions` (tidak pernah dipakai) dihapus; ditambah data contoh inspektor dan chat.

## API baru
- `api/chat.php` — daftar percakapan, isi percakapan, kirim pesan, jumlah belum dibaca
- `api/inspectors.php` — daftar inspektor tersedia

Catatan: folder `screenshots/` masih menampilkan versi lama dan perlu diambil ulang.
