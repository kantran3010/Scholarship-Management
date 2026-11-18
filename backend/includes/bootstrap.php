<?php
/**
 * Tệp khởi tạo: được require ở đầu MỌI trang.
 *
 * Thứ tự nạp:
 *   1. config.php  – hằng số cấu hình (DB, BASE_URL, giới hạn upload...)
 *   2. db.php      – hàm db() trả về kết nối PDO singleton
 *   3. functions.php – toàn bộ hàm tiện ích (escape, session, validate, phân trang...)
 *   4. upload.php  – xử lý tải tập tin lên máy chủ
 *   5. components.php – hàm render giao diện dùng lại (thẻ học bổng...)
 *
 * Bảo mật HTTP header được gửi trước bất kỳ HTML nào:
 *   - X-Content-Type-Options: ngăn trình duyệt "đoán" loại nội dung (chống MIME sniffing).
 *   - X-Frame-Options: chặn trang bị nhúng vào iframe từ nguồn khác (chống Clickjacking).
 *   - Referrer-Policy: không gửi thông tin Referer sang domain khác (bảo vệ URL nội bộ).
 *
 * Session an toàn:
 *   - httponly = true : JavaScript không truy cập được cookie phiên (chống XSS đánh cắp session).
 *   - samesite = Lax  : trình duyệt chỉ gửi cookie trong cùng site, giảm nguy cơ CSRF.
 *   - session_name()  : đặt tên cookie riêng thay vì PHPSESSID mặc định (khó đoán hơn).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/upload.php';

// Thiết lập các HTTP Security Header trước khi gửi bất kỳ nội dung nào
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// Cấu hình session an toàn: tên riêng, cookie httponly + SameSite
session_name('SCHSESSID');
session_set_cookie_params([
    'lifetime' => 0,          // hết hạn khi đóng trình duyệt
    'path'     => '/',
    'httponly' => true,        // JavaScript không đọc được cookie phiên (chống XSS)
    'samesite' => 'Lax',       // giảm nguy cơ CSRF từ site khác
]);
session_start();

// Kiểm tra ngay ở mỗi request: nếu tài khoản bị khóa → đăng xuất tức thì
// (không cần đợi đến lần đăng nhập kế tiếp mới có hiệu lực)
refresh_session_user();

// Nạp hàm render giao diện (phải sau session_start vì dùng current_user())
require_once __DIR__ . '/components.php';
