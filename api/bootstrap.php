<?php

// Jangan tampilkan warning PHP ke output agar respons JSON tidak rusak
ini_set('display_errors', '0');

function is_https()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

// Memulai session
if (session_status() !== PHP_SESSION_ACTIVE) {

    $secure = is_https();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Header JSON
header('Content-Type: application/json; charset=utf-8');

// Koneksi database
require_once __DIR__ . '/../config/database.php';


// Mengirim response JSON
function respond($data, $code = 200)
{
    http_response_code($code);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// Membaca data JSON dari request
function body_json()
{
    $v = json_decode(
        file_get_contents('php://input'),
        true
    );

    return is_array($v) ? $v : [];
}


// Kunci penandatanganan cookie auth agar login tetap bertahan di serverless (Vercel)
function auth_secret()
{
    $base = getenv('APP_SECRET') ?: getenv('DB_PASS') ?: 'jb-garage-local-dev';
    return hash('sha256', $base . '|jb_garage_auth_v1', true);
}

function set_auth_cookie(array $user, bool $remember = false)
{
    $exp = time() + ($remember ? 7 * 86400 : 86400);
    $payload = rtrim(strtr(base64_encode(json_encode([
        'user' => $user,
        'exp' => $exp
    ])), '+/', '-_'), '=');
    $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, auth_secret(), true)), '+/', '-_'), '=');

    setcookie('jb_auth', $payload . '.' . $sig, [
        'expires' => $remember ? $exp : 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clear_auth_cookie()
{
    setcookie('jb_auth', '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Mengambil data user dari session atau cookie bertanda tangan
function current_user()
{
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    $raw = $_COOKIE['jb_auth'] ?? '';
    if (!is_string($raw) || strpos($raw, '.') === false) {
        return null;
    }

    [$payload, $sig] = explode('.', $raw, 2);
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, auth_secret(), true)), '+/', '-_'), '=');
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    $json = base64_decode(strtr($payload, '-_', '+/'), true);
    $data = $json ? json_decode($json, true) : null;
    if (
        !is_array($data) ||
        empty($data['user']) ||
        !is_array($data['user']) ||
        (int) ($data['exp'] ?? 0) < time()
    ) {
        return null;
    }

    $_SESSION['user'] = $data['user'];
    return $data['user'];
}


// Memastikan user sudah login
function require_user()
{
    $u = current_user();

    if (!$u) {
        respond([
            'success' => false,
            'message' => 'Silakan login terlebih dahulu.'
        ], 401);
    }

    return $u;
}


// Memastikan user memiliki role penjual atau admin
function require_seller()
{
    $u = require_user();

    if (
        $u['role'] !== 'penjual' &&
        $u['role'] !== 'admin'
    ) {
        respond([
            'success' => false,
            'message' => 'Fitur ini khusus penjual.'
        ], 403);
    }

    return $u;
}


// Mengambil data detail iklan
function listing_row($pdo, $id)
{
    $q = $pdo->prepare("
        SELECT
            l.*,
            u.nama_lengkap AS owner_name
        FROM listings l
        JOIN users u
            ON u.id = l.user_id
        WHERE l.id = ?
    ");

    $q->execute([$id]);

    $l = $q->fetch();

    if (!$l) {
        return null;
    }


    // Mengambil gambar iklan
    $im = $pdo->prepare("
        SELECT image_path
        FROM listing_images
        WHERE listing_id = ?
        ORDER BY id
    ");

    $im->execute([$id]);

    $images = array_column(
        $im->fetchAll(),
        'image_path'
    );


    // Mengembalikan data iklan
    return [
        'id' => (string) $l['id'],
        'ownerId' => (int) $l['user_id'],
        'ownerName' => $l['owner_name'],
        'title' => $l['judul'],
        'type' => $l['jenis'],
        'year' => (int) $l['tahun'],
        'price' => (float) $l['harga'],
        'city' => $l['kota'],
        'desc' => $l['deskripsi'],
        'status' => $l['status'],
        'views' => (int) $l['views'],
        'images' => $images,
        'createdAt' => strtotime($l['created_at']) * 1000
    ];
}