<?php
/**
 * Cấu hình chung của ứng dụng.
 * XAMPP mặc định: user "root", mật khẩu rỗng.
 */

// --- Kết nối cơ sở dữ liệu ---
const DB_HOST    = 'localhost';
const DB_NAME    = 'scholarship_db';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

// --- Đường dẫn gốc của website (không có dấu / ở cuối) ---
// Thư mục dự án đặt tại C:\xampp\htdocs\scholarship -> giữ nguyên '/scholarship'.
// Nếu đổi tên thư mục thì sửa lại cho khớp; nếu chạy bằng "php -S localhost:8000" thì để '' (rỗng).
const BASE_URL = '/hocbong/frontend';   // Apache trỏ vào thư mục public (xem README)

// --- Phân trang ---
const PER_PAGE_USER  = 6;
const PER_PAGE_ADMIN = 10;

// --- Thông tin hiển thị trên website (footer, trang Liên hệ) ---
// Thay bằng thông tin thật của đơn vị trước khi nộp bài / demo.
const SITE_NAME    = 'Hệ thống tiếp nhận hồ sơ học bổng';
const SITE_SCHOOL  = 'Trường Đại học Lao động – Xã hội (Cơ sở II)';
const SITE_ADDRESS = '1018 Tô Ký, Trung Mỹ Tây, Hồ Chí Minh 70000, Việt Nam ';
const SITE_PHONE   = '028 3883 7814';
const SITE_EMAIL   = 'ulsa2@ldxh.edu.vn';

// --- Kênh liên hệ trực tiếp (hiển thị nút ở trang Liên hệ và nút nổi trên mọi trang) ---
// ZALO_PHONE: số điện thoại Zalo của người phụ trách (dạng 0xxxxxxxxx). Bấm nút sẽ mở đúng hồ sơ Zalo này.
// MESSENGER_URL: đường dẫn Messenger, ví dụ https://m.me/ten.trang (trang Facebook của đơn vị), để trống nếu chưa có.
// Để trống (''), nút tương ứng sẽ tự ẩn.
const ZALO_PHONE     = '0363295946';
const MESSENGER_URL  = 'https://web.facebook.com/kantran3010';
// Thời gian tối thiểu giữa hai tin nhắn chat của cùng một người (giây), chống spam.
const CHAT_MIN_INTERVAL = 2;

// --- Ảnh bìa chương trình học bổng (lưu công khai trong assets để trình duyệt hiển thị được) ---
const COVER_DIR          = __DIR__ . '/../../frontend/assets/img/scholarships';
const COVER_MAX_BYTES    = 2 * 1024 * 1024;                  // 2 MB để ảnh không quá nặng
const ALLOWED_COVER_EXT  = ['jpg', 'jpeg', 'png', 'webp'];
const DEFAULT_COVERS     = ['campus-ulsa2.jpg', 'banner-video-ulsa2.jpg'];   // ảnh dùng khi chương trình chưa có ảnh riêng

// --- Tải minh chứng của sinh viên (lưu riêng, bị chặn truy cập trực tiếp) ---
const UPLOAD_DIR        = __DIR__ . '/../storage/uploads';          // thư mục lưu file, đã chặn truy cập trực tiếp bằng .htaccess
const MAX_UPLOAD_BYTES  = 5 * 1024 * 1024;                   // 5 MB mỗi file
const MAX_FILES_PER_APP = 8;                                 // tối đa số minh chứng mỗi hồ sơ
const ALLOWED_UPLOAD_EXT = ['pdf', 'jpg', 'jpeg', 'png'];

// --- Chế độ gỡ lỗi: true khi đang làm bài, false khi demo/nộp bài ---
// QUAN TRỌNG: Đặt false trước khi nộp để không hiển thị lỗi hệ thống ra màn hình
const APP_DEBUG = false;

date_default_timezone_set('Asia/Ho_Chi_Minh');
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
