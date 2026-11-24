<?php

// ==========================================
// JB GARAGE - CHAT & NEGOSIASI
// Pembeli <-> Penjual, per iklan
// ==========================================
//
// GET  chat.php               -> daftar percakapan milik user (pembeli/penjual)
// GET  chat.php?unread=1      -> jumlah pesan belum dibaca
// GET  chat.php?c=ID&after=N  -> pesan percakapan ID (id > N), sekaligus menandai dibaca
// POST chat.php {listingId, message, offer?}      -> mulai chat dari sebuah iklan
// POST chat.php {conversationId, message, offer?} -> balas pesan

require __DIR__ . '/bootstrap.php';

$u = require_user();
$method = $_SERVER['REQUEST_METHOD'];


// Mengambil percakapan jika user adalah peserta
function conversation_for($pdo, $cid, $uid)
{
    $q = $pdo->prepare("
        SELECT
            c.id, c.listing_id, c.buyer_id, c.seller_id,
            l.judul, l.tahun, l.harga, l.status,
            b.nama_lengkap AS buyer_name,
            s.nama_lengkap AS seller_name,
            (
                SELECT image_path FROM listing_images
                WHERE listing_id = l.id ORDER BY id LIMIT 1
            ) AS image
        FROM conversations c
        JOIN listings l ON l.id = c.listing_id
        JOIN users b ON b.id = c.buyer_id
        JOIN users s ON s.id = c.seller_id
        WHERE c.id = ?
          AND (c.buyer_id = ? OR c.seller_id = ?)
    ");

    $q->execute([$cid, $uid, $uid]);

    return $q->fetch() ?: null;
}


// Validasi isi pesan dan tawaran harga
function read_message_input($d)
{
    $text = trim((string) ($d['message'] ?? ''));

    if ($text === '' || mb_strlen($text) > 1000) {
        respond([
            'success' => false,
            'message' => 'Pesan wajib diisi (maksimal 1000 karakter).'
        ], 422);
    }

    $offer = null;

    if (isset($d['offer']) && $d['offer'] !== '' && $d['offer'] !== null) {

        if (!is_numeric($d['offer'])) {
            respond([
                'success' => false,
                'message' => 'Tawaran harga tidak valid.'
            ], 422);
        }

        $offer = (float) $d['offer'];

        if ($offer < 100000 || $offer > 99999999999) {
            respond([
                'success' => false,
                'message' => 'Tawaran harga minimal Rp 100.000.'
            ], 422);
        }
    }

    return [$text, $offer];
}


// ==========================================
// GET
// ==========================================

if ($method === 'GET') {

    // Jumlah pesan belum dibaca (untuk badge navbar)
    if (isset($_GET['unread'])) {

        $q = $pdo->prepare("
            SELECT COUNT(*)
            FROM messages m
            JOIN conversations c ON c.id = m.conversation_id
            WHERE m.dibaca = 0
              AND m.sender_id <> ?
              AND (c.buyer_id = ? OR c.seller_id = ?)
        ");

        $q->execute([$u['id'], $u['id'], $u['id']]);

        respond([
            'success' => true,
            'unread' => (int) $q->fetchColumn()
        ]);
    }


    // Isi satu percakapan
    if (isset($_GET['c'])) {

        $c = conversation_for($pdo, (int) $_GET['c'], $u['id']);

        if (!$c) {
            respond([
                'success' => false,
                'message' => 'Percakapan tidak ditemukan.'
            ], 404);
        }

        $after = (int) ($_GET['after'] ?? 0);

        // Tandai pesan lawan bicara sebagai sudah dibaca
        $pdo->prepare("
            UPDATE messages
            SET dibaca = 1
            WHERE conversation_id = ?
              AND sender_id <> ?
              AND dibaca = 0
        ")->execute([$c['id'], $u['id']]);

        $q = $pdo->prepare("
            SELECT id, sender_id, isi, harga_tawaran, created_at
            FROM messages
            WHERE conversation_id = ?
              AND id > ?
            ORDER BY id
        ");

        $q->execute([$c['id'], $after]);

        $msgs = [];

        foreach ($q->fetchAll() as $m) {
            $msgs[] = [
                'id' => (int) $m['id'],
                'senderId' => (int) $m['sender_id'],
                'text' => $m['isi'],
                'offer' => $m['harga_tawaran'] === null
                    ? null
                    : (float) $m['harga_tawaran'],
                'createdAt' => strtotime($m['created_at']) * 1000
            ];
        }

        respond([
            'success' => true,
            'me' => (int) $u['id'],
            'conversation' => [
                'id' => (int) $c['id'],
                'listingId' => (int) $c['listing_id'],
                'title' => $c['judul'],
                'year' => (int) $c['tahun'],
                'price' => (float) $c['harga'],
                'status' => $c['status'],
                'image' => $c['image'],
                'buyerId' => (int) $c['buyer_id'],
                'buyerName' => $c['buyer_name'],
                'sellerId' => (int) $c['seller_id'],
                'sellerName' => $c['seller_name']
            ],
            'messages' => $msgs
        ]);
    }


    // Daftar percakapan
    $q = $pdo->prepare("
        SELECT
            c.id, c.listing_id, c.buyer_id, c.seller_id, c.last_message_at,
            l.judul, l.tahun, l.harga,
            b.nama_lengkap AS buyer_name,
            s.nama_lengkap AS seller_name,
            (
                SELECT image_path FROM listing_images
                WHERE listing_id = l.id ORDER BY id LIMIT 1
            ) AS image,
            (
                SELECT isi FROM messages
                WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1
            ) AS last_text,
            (
                SELECT harga_tawaran FROM messages
                WHERE conversation_id = c.id AND harga_tawaran IS NOT NULL
                ORDER BY id DESC LIMIT 1
            ) AS last_offer,
            (
                SELECT COUNT(*) FROM messages
                WHERE conversation_id = c.id AND dibaca = 0 AND sender_id <> ?
            ) AS unread
        FROM conversations c
        JOIN listings l ON l.id = c.listing_id
        JOIN users b ON b.id = c.buyer_id
        JOIN users s ON s.id = c.seller_id
        WHERE c.buyer_id = ? OR c.seller_id = ?
        ORDER BY c.last_message_at DESC, c.id DESC
    ");

    $q->execute([$u['id'], $u['id'], $u['id']]);

    $items = [];

    foreach ($q->fetchAll() as $r) {

        $asSeller = (int) $r['seller_id'] === (int) $u['id'];

        $items[] = [
            'id' => (int) $r['id'],
            'listingId' => (int) $r['listing_id'],
            'title' => $r['judul'],
            'year' => (int) $r['tahun'],
            'price' => (float) $r['harga'],
            'image' => $r['image'],
            'asSeller' => $asSeller,
            'otherName' => $asSeller ? $r['buyer_name'] : $r['seller_name'],
            'lastText' => $r['last_text'],
            'lastOffer' => $r['last_offer'] === null ? null : (float) $r['last_offer'],
            'unread' => (int) $r['unread'],
            'lastAt' => strtotime($r['last_message_at']) * 1000
        ];
    }

    respond([
        'success' => true,
        'conversations' => $items
    ]);
}


// ==========================================
// POST
// ==========================================

if ($method === 'POST') {

    $d = body_json();

    [$text, $offer] = read_message_input($d);

    $cid = (int) ($d['conversationId'] ?? 0);


    // Mulai percakapan baru dari sebuah iklan
    if ($cid === 0) {

        $lid = (int) ($d['listingId'] ?? 0);

        $q = $pdo->prepare("
            SELECT id, user_id, status
            FROM listings
            WHERE id = ?
        ");

        $q->execute([$lid]);

        $l = $q->fetch();

        if (!$l || $l['status'] !== 'Aktif') {
            respond([
                'success' => false,
                'message' => 'Iklan tidak ditemukan atau sudah tidak aktif.'
            ], 404);
        }

        if ((int) $l['user_id'] === (int) $u['id']) {
            respond([
                'success' => false,
                'message' => 'Anda tidak dapat chat dengan iklan milik sendiri.'
            ], 403);
        }

        // Jika sudah pernah chat, pakai percakapan yang ada
        $pdo->prepare("
            INSERT IGNORE INTO conversations (listing_id, buyer_id, seller_id)
            VALUES (?, ?, ?)
        ")->execute([$lid, $u['id'], $l['user_id']]);

        $q = $pdo->prepare("
            SELECT id FROM conversations
            WHERE listing_id = ? AND buyer_id = ?
        ");

        $q->execute([$lid, $u['id']]);

        $cid = (int) $q->fetchColumn();
    }


    $c = conversation_for($pdo, $cid, $u['id']);

    if (!$c) {
        respond([
            'success' => false,
            'message' => 'Percakapan tidak ditemukan.'
        ], 404);
    }


    $pdo->beginTransaction();

    try {

        $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, isi, harga_tawaran)
            VALUES (?, ?, ?, ?)
        ")->execute([$cid, $u['id'], $text, $offer]);

        $mid = (int) $pdo->lastInsertId();

        $pdo->prepare("
            UPDATE conversations
            SET last_message_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$cid]);

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        respond([
            'success' => false,
            'message' => 'Pesan gagal dikirim.'
        ], 500);
    }

    respond([
        'success' => true,
        'conversationId' => $cid,
        'messageId' => $mid
    ]);
}


respond([
    'success' => false,
    'message' => 'Method tidak diizinkan.'
], 405);
