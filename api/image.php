<?php
// Menyajikan foto iklan yang tersimpan di Supabase (untuk hosting serverless seperti Vercel)
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$q = $pdo->prepare("
    SELECT mime_type, encode(data, 'base64') AS b64
    FROM listing_image_blobs
    WHERE id = ?
");
$q->execute([$id]);
$row = $q->fetch();

if (!$row || empty($row['b64'])) {
    http_response_code(404);
    exit;
}

$bytes = base64_decode($row['b64'], true);
if ($bytes === false) {
    http_response_code(500);
    exit;
}

header('Content-Type: ' . $row['mime_type']);
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: public, max-age=31536000, immutable');
echo $bytes;
exit;
