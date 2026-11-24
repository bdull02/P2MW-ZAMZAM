<?php

// ==========================================
// JB GARAGE - DAFTAR INSPEKTOR
// Hanya menampilkan inspektor yang masih tersedia
// ==========================================

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond([
        'success' => false,
        'message' => 'Method tidak diizinkan.'
    ], 405);
}

$q = $pdo->query("
    SELECT
        id, nama, kota, spesialis, pengalaman_tahun,
        tarif_motor, tarif_mobil, rating,
        jumlah_inspeksi, kontak, bio, status
    FROM inspectors
    WHERE status = 'Tersedia'
    ORDER BY rating DESC, jumlah_inspeksi DESC
");

$items = [];

foreach ($q->fetchAll() as $r) {
    $items[] = [
        'id' => (int) $r['id'],
        'name' => $r['nama'],
        'city' => $r['kota'],
        'specialty' => $r['spesialis'],
        'experience' => (int) $r['pengalaman_tahun'],
        'priceMotor' => $r['tarif_motor'] === null ? null : (float) $r['tarif_motor'],
        'priceCar' => $r['tarif_mobil'] === null ? null : (float) $r['tarif_mobil'],
        'rating' => (float) $r['rating'],
        'inspections' => (int) $r['jumlah_inspeksi'],
        'contact' => $r['kontak'],
        'bio' => $r['bio'],
        'status' => $r['status']
    ];
}

respond([
    'success' => true,
    'inspectors' => $items
]);
