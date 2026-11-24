CREATE DATABASE IF NOT EXISTS jb_garage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jb_garage;
-- Tabel user_sessions tidak pernah dipakai kode PHP (login memakai session PHP), jadi dihapus.
DROP TABLE IF EXISTS user_sessions;
CREATE TABLE IF NOT EXISTS users (
 id INT AUTO_INCREMENT PRIMARY KEY, nama_lengkap VARCHAR(60) NOT NULL, email VARCHAR(100) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL, role ENUM('pembeli','penjual','admin') NOT NULL DEFAULT 'pembeli',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS listings (
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, judul VARCHAR(60) NOT NULL, jenis ENUM('motor','mobil') NOT NULL,
 tahun YEAR NOT NULL, harga DECIMAL(15,2) NOT NULL, kota VARCHAR(40) NOT NULL, deskripsi VARCHAR(500) NOT NULL,
 status ENUM('Aktif','Review','Terjual') NOT NULL DEFAULT 'Review', views INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_listing_filter(jenis,status,harga), INDEX idx_listing_city(kota), INDEX idx_listing_user(user_id),
 CONSTRAINT fk_listings_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS listing_images (
 id INT AUTO_INCREMENT PRIMARY KEY, listing_id INT NOT NULL, image_path VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_images_listing FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS contact_messages (
 id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL, subjek VARCHAR(150), pesan TEXT NOT NULL,
 status ENUM('Belum Dibaca','Sudah Dibaca') DEFAULT 'Belum Dibaca', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS listing_views (
 id INT AUTO_INCREMENT PRIMARY KEY, listing_id INT NOT NULL, viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_views_listing FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
-- ============================================================
-- TABEL BARU: inspektor + chat/negosiasi
-- ============================================================
CREATE TABLE IF NOT EXISTS inspectors (
 id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(80) NOT NULL, kota VARCHAR(40) NOT NULL,
 spesialis ENUM('motor','mobil','motor & mobil') NOT NULL DEFAULT 'motor & mobil',
 pengalaman_tahun TINYINT UNSIGNED NOT NULL DEFAULT 1, tarif_motor DECIMAL(12,2) NULL, tarif_mobil DECIMAL(12,2) NULL,
 rating DECIMAL(2,1) NOT NULL DEFAULT 0.0, jumlah_inspeksi INT UNSIGNED NOT NULL DEFAULT 0,
 kontak VARCHAR(30) NOT NULL, bio VARCHAR(255) NULL,
 status ENUM('Tersedia','Sibuk','Nonaktif') NOT NULL DEFAULT 'Tersedia',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_inspector_status(status), INDEX idx_inspector_city(kota)
) ENGINE=InnoDB;
-- Satu percakapan = satu pembeli membahas satu iklan dengan penjualnya.
CREATE TABLE IF NOT EXISTS conversations (
 id INT AUTO_INCREMENT PRIMARY KEY, listing_id INT NOT NULL, buyer_id INT NOT NULL, seller_id INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_conv_listing_buyer(listing_id,buyer_id), INDEX idx_conv_buyer(buyer_id,last_message_at), INDEX idx_conv_seller(seller_id,last_message_at),
 CONSTRAINT fk_conv_listing FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_conv_buyer FOREIGN KEY(buyer_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_conv_seller FOREIGN KEY(seller_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
-- harga_tawaran diisi jika pembeli/penjual mengajukan penawaran harga (negosiasi), selain itu NULL.
CREATE TABLE IF NOT EXISTS messages (
 id INT AUTO_INCREMENT PRIMARY KEY, conversation_id INT NOT NULL, sender_id INT NOT NULL, isi VARCHAR(1000) NOT NULL,
 harga_tawaran DECIMAL(15,2) NULL, dibaca TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_msg_conv(conversation_id,id),
 CONSTRAINT fk_msg_conv FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_msg_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Password demo untuk semua akun: Demo1234 (hash bcrypt PHP).
INSERT IGNORE INTO users(nama_lengkap,email,password,role) VALUES
('Reza Kusuma','penjual@jbgarage.test','$2y$12$zpROlgYSjtFbxvk57ZLukeq8EsxNah2y4QG8.U8/coTrXumsRPzwm','penjual'),
('Andi Wijaya','pembeli@jbgarage.test','$2y$12$zpROlgYSjtFbxvk57ZLukeq8EsxNah2y4QG8.U8/coTrXumsRPzwm','pembeli'),
('Siti Rahma','pembeli2@jbgarage.test','$2y$12$zpROlgYSjtFbxvk57ZLukeq8EsxNah2y4QG8.U8/coTrXumsRPzwm','pembeli')
ON DUPLICATE KEY UPDATE nama_lengkap=VALUES(nama_lengkap), password=VALUES(password), role=VALUES(role);

-- Data contoh dimasukkan hanya jika judul kendaraan tersebut belum ada.
INSERT INTO listings(user_id,judul,jenis,tahun,harga,kota,deskripsi,status,views)
SELECT u.id,v.judul,v.jenis,v.tahun,v.harga,v.kota,v.deskripsi,v.status,v.views
FROM users u JOIN (
 SELECT 'Honda Beat' judul,'motor' jenis,2021 tahun,12500000 harga,'Bandung' kota,'Honda Beat tahun 2021, kondisi terawat, surat lengkap, siap pakai.' deskripsi,'Aktif' status,324 views UNION ALL
 SELECT 'Honda Scoopy','motor',2020,13500000,'Cimahi','Honda Scoopy tahun 2020, kondisi terawat dan siap digunakan.','Aktif',187 UNION ALL
 SELECT 'Suzuki GSX-S150','motor',2019,18000000,'Bandung','Suzuki GSX-S150 tahun 2019, kondisi baik.','Review',96 UNION ALL
 SELECT 'Honda Brio','mobil',2018,115000000,'Bandung','Honda Brio tahun 2018, kendaraan terawat.','Terjual',633 UNION ALL
 SELECT 'Yamaha NMAX','motor',2019,21000000,'Jakarta','Yamaha NMAX tahun 2019, kondisi terawat.','Aktif',142 UNION ALL
 SELECT 'Honda Vario 125','motor',2020,12800000,'Bandung','Honda Vario 125 tahun 2020, surat lengkap.','Aktif',98 UNION ALL
 SELECT 'Toyota Avanza','mobil',2016,105000000,'Cimahi','Toyota Avanza tahun 2016, kondisi baik.','Aktif',211 UNION ALL
 SELECT 'Daihatsu Ayla','mobil',2017,78000000,'Bandung','Daihatsu Ayla tahun 2017, siap pakai.','Aktif',156 UNION ALL
 SELECT 'Yamaha Aerox 155','motor',2021,20500000,'Lembang','Yamaha Aerox 155 tahun 2021, kondisi terawat.','Aktif',120 UNION ALL
 SELECT 'Honda PCX 160','motor',2022,27000000,'Bandung','Honda PCX 160 tahun 2022, kondisi prima.','Aktif',305 UNION ALL
 SELECT 'Suzuki Ertiga','mobil',2015,112000000,'Garut','Suzuki Ertiga tahun 2015, kondisi terawat.','Aktif',77 UNION ALL
 SELECT 'Kawasaki Ninja 250','motor',2018,38000000,'Bandung','Kawasaki Ninja 250 tahun 2018.','Aktif',250 UNION ALL
 SELECT 'Mitsubishi Xpander','mobil',2019,185000000,'Jakarta','Mitsubishi Xpander tahun 2019, kondisi baik.','Aktif',189 UNION ALL
 SELECT 'Honda CBR150R','motor',2020,25000000,'Sumedang','Honda CBR150R tahun 2020, surat lengkap.','Aktif',134
) v ON u.email='penjual@jbgarage.test'
WHERE NOT EXISTS (SELECT 1 FROM listings old WHERE old.user_id=u.id AND old.judul=v.judul);

-- Kaitkan foto bawaan project ke empat listing contoh (jika belum memiliki foto).

INSERT INTO listing_images (listing_id, image_path)

SELECT
    l.id,
    v.path

FROM listings AS l

JOIN (
    -- FOTO MOTOR

    SELECT
        'Honda Beat' AS judul,
        'assets/img/honda-beat-2021.jpg' AS path

    UNION ALL

    SELECT
        'Honda Scoopy',
        'assets/img/honda-scoopy-2020.jpg'

    UNION ALL

    SELECT
        'Suzuki GSX-S150',
        'assets/img/suzuki-gsx-s150-2019.jpg'

    UNION ALL

    SELECT
        'Yamaha Aerox 155',
        'assets/img/aerox.jpeg'

    UNION ALL

    SELECT
        'Kawasaki Ninja 250',
        'assets/img/ninja.jpg'

    UNION ALL

    SELECT
        'Honda PCX 160',
        'assets/img/pcx.jpg'


    -- FOTO MOBIL

    UNION ALL

    SELECT
        'Honda Brio',
        'assets/img/honda-brio-2018.jpg'

    UNION ALL

    SELECT
        'Suzuki Ertiga',
        'assets/img/ertiga.jpg'

    UNION ALL

    SELECT
        'Mitsubishi Xpander',
        'assets/img/xpander.jpg'

) AS v ON v.judul = l.judul

JOIN users AS u
    ON u.id = l.user_id

WHERE
    u.email = 'penjual@jbgarage.test'

    AND NOT EXISTS (
        SELECT 1
        FROM listing_images AS i
        WHERE
            i.listing_id = l.id
            AND i.image_path = v.path
    );

-- ============================================================
-- DATA CONTOH INSPEKTOR (nama & nomor kontak hanya contoh)
-- ============================================================
INSERT INTO inspectors(nama,kota,spesialis,pengalaman_tahun,tarif_motor,tarif_mobil,rating,jumlah_inspeksi,kontak,bio,status)
SELECT v.* FROM (
 SELECT 'Fauzan Ardiansyah' nama,'Bandung' kota,'motor & mobil' spesialis,6 pengalaman_tahun,150000 tarif_motor,250000 tarif_mobil,4.9 rating,128 jumlah_inspeksi,'081200000001' kontak,'Cek mesin, rangka, kelistrikan, dan keaslian dokumen kendaraan.' bio,'Tersedia' status UNION ALL
 SELECT 'Dedi Hermawan','Cimahi','mobil',9,NULL,275000,4.8,214,'081200000002','Spesialis mobil keluarga (MPV/LCGC), termasuk cek banjir dan tabrakan.','Tersedia' UNION ALL
 SELECT 'Rizky Pratama','Bandung','motor',4,125000,NULL,4.7,96,'081200000003','Fokus motor matic dan sport, cek kilometer dan kondisi CVT.','Tersedia' UNION ALL
 SELECT 'Agus Salim','Jakarta','motor & mobil',11,175000,300000,4.9,352,'081200000004','Berpengalaman di bengkel resmi, laporan inspeksi lengkap dengan foto.','Sibuk' UNION ALL
 SELECT 'Hendra Gunawan','Lembang','motor',3,130000,NULL,4.6,41,'081200000005','Inspeksi motor area Lembang dan sekitarnya.','Tersedia' UNION ALL
 SELECT 'Joko Santoso','Garut','mobil',7,NULL,260000,4.5,87,'081200000006','Inspeksi mobil bekas area Garut dan Tasikmalaya.','Nonaktif'
) v WHERE NOT EXISTS (SELECT 1 FROM inspectors);

-- ============================================================
-- DATA CONTOH CHAT & NEGOSIASI (hanya jika belum ada percakapan)
-- ============================================================
INSERT IGNORE INTO conversations(listing_id,buyer_id,seller_id)
SELECT l.id,b.id,s.id FROM users s JOIN users b ON b.email IN ('pembeli@jbgarage.test','pembeli2@jbgarage.test')
JOIN listings l ON l.user_id=s.id AND ((b.email='pembeli@jbgarage.test' AND l.judul='Honda Beat') OR (b.email='pembeli2@jbgarage.test' AND l.judul='Honda Scoopy') OR (b.email='pembeli@jbgarage.test' AND l.judul='Honda PCX 160'))
WHERE s.email='penjual@jbgarage.test';

INSERT INTO messages(conversation_id,sender_id,isi,harga_tawaran,dibaca)
SELECT c.id,sdr.id,m.isi,m.tawar,m.dibaca FROM conversations c
JOIN listings l ON l.id=c.listing_id
JOIN users b ON b.id=c.buyer_id
JOIN (
 SELECT 'Honda Beat' judul,'pembeli@jbgarage.test' buyer,'B' sender_role,'Halo kak, Honda Beat 2021 masih tersedia? Pajaknya sampai kapan?' isi,NULL tawar,1 dibaca,1 urut UNION ALL
 SELECT 'Honda Beat','pembeli@jbgarage.test','S','Halo, masih ada. Pajak hidup sampai Maret tahun depan, surat lengkap.',NULL,1,2 UNION ALL
 SELECT 'Honda Beat','pembeli@jbgarage.test','B','Boleh nego? Saya tawar segini ya kak.',11500000,0,3 UNION ALL
 SELECT 'Honda Scoopy','pembeli2@jbgarage.test','B','Siang kak, Scoopy-nya bisa dilihat langsung di Cimahi hari Sabtu?',NULL,0,1 UNION ALL
 SELECT 'Honda PCX 160','pembeli@jbgarage.test','B','Apakah sudah pernah diinspeksi? Saya mau minta inspektor ikut cek.',NULL,1,1 UNION ALL
 SELECT 'Honda PCX 160','pembeli@jbgarage.test','S','Belum kak, silakan pilih inspektor dari daftar di website, saya siap antar unitnya.',NULL,0,2
) m ON m.judul=l.judul AND m.buyer=b.email
JOIN (SELECT 'B' r UNION ALL SELECT 'S') x ON x.r=m.sender_role
JOIN users sdr ON sdr.id=IF(x.r='B',c.buyer_id,c.seller_id)
WHERE NOT EXISTS (SELECT 1 FROM messages old WHERE old.conversation_id=c.id)
ORDER BY c.id,m.urut;
