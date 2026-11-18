<?php
/**
 * API trò chuyện trực tiếp (JSON).
 *   GET  chat_api.php?action=fetch&after=<id>  – lấy tin nhắn mới của hội thoại hiện tại
 *   POST chat_api.php  (action=send, body, csrf_token) – gửi tin nhắn của khách/sinh viên
 * Mỗi người chỉ đọc và ghi được hội thoại của chính mình (khóa lấy từ session/đăng nhập).
 */
require_once __DIR__ . '/../backend/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function chat_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Quản trị viên không chat trên khu vực công khai bằng API này
if (is_admin()) {
    chat_json(['ok' => false, 'error' => 'Không dùng chức năng này trong phiên quản trị.'], 403);
}

$key = chat_conv_key();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $after = max(0, get_int('after'));
    $st = $pdo->prepare('SELECT id, sender, body, created_at FROM chat_messages WHERE conv_key = ? AND id > ? ORDER BY id ASC LIMIT 100');
    $st->execute([$key, $after]);
    chat_json(['ok' => true, 'messages' => $st->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        chat_json(['ok' => false, 'error' => 'Phiên làm việc đã hết hạn. Hãy tải lại trang.'], 419);
    }
    if (post_str('action') !== 'send') {
        chat_json(['ok' => false, 'error' => 'Yêu cầu không hợp lệ.'], 400);
    }

    // Chống spam: giới hạn tốc độ gửi theo phiên
    $now = microtime(true);
    if (isset($_SESSION['chat_last']) && ($now - $_SESSION['chat_last']) < CHAT_MIN_INTERVAL) {
        chat_json(['ok' => false, 'error' => 'Bạn gửi quá nhanh, vui lòng chờ vài giây.'], 429);
    }

    $body = trim(post_raw('body'));
    $len  = mb_strlen($body);
    if ($len < 1 || $len > 1000) {
        chat_json(['ok' => false, 'error' => 'Tin nhắn phải từ 1 đến 1000 ký tự.'], 422);
    }

    $user = current_user();
    $userId = ($user && $user['role'] === 'student') ? (int)$user['id'] : null;
    $name   = $user ? $user['full_name'] : 'Khách';

    $st = $pdo->prepare('INSERT INTO chat_messages (conv_key, user_id, visitor_name, sender, body, is_read) VALUES (?, ?, ?, ?, ?, 0)');
    $st->execute([$key, $userId, mb_substr($name, 0, 100), 'visitor', $body]);
    $_SESSION['chat_last'] = $now;

    chat_json(['ok' => true, 'message' => [
        'id' => (int)$pdo->lastInsertId(), 'sender' => 'visitor', 'body' => $body, 'created_at' => date('Y-m-d H:i:s'),
    ]]);
}

chat_json(['ok' => false, 'error' => 'Phương thức không được hỗ trợ.'], 405);
