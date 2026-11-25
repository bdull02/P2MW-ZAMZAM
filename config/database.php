<?php
// Konfigurasi koneksi PostgreSQL Supabase.
// Ambil kredensial di Supabase Dashboard -> Connect -> Session Pooler / Transaction Pooler.
// Bisa diisi langsung di bawah ini atau lewat environment variable (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
$host     = getenv('DB_HOST') ?: 'aws-1-ap-southeast-1.pooler.supabase.com';
$port     = getenv('DB_PORT') ?: '6543'; // 6543 = Transaction Pooler, 5432 = Session Pooler
$dbname   = getenv('DB_NAME') ?: 'postgres';
$username = getenv('DB_USER') ?: 'postgres.jwtdetbmrhssqkxkcnql';
$password = getenv('DB_PASS') ?: 'YOUR_SUPABASE_DB_PASSWORD';

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // EMULATE_PREPARES => true wajib untuk Supabase Transaction Pooler (port 6543)
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false, 'message'=>'Database Supabase belum terhubung. Jalankan database_supabase.sql dan periksa config/database.php.']);
    exit;
}
