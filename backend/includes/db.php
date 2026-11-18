<?php
/**
 * Kết nối CSDL bằng PDO (Singleton Pattern).
 *
 * Sử dụng Singleton để đảm bảo toàn bộ ứng dụng dùng chung MỘT kết nối duy nhất.
 * Gọi db() ở bất kỳ đâu đều nhận lại cùng một đối tượng PDO (không tạo kết nối mới).
 *
 * Cấu hình PDO:
 *   - ERRMODE_EXCEPTION   : mọi lỗi SQL ném ra PDOException (dễ bắt, dễ log).
 *   - FETCH_ASSOC         : fetchAll/fetch trả về mảng kết hợp theo tên cột.
 *   - EMULATE_PREPARES=false : dùng Prepared Statement thật của MySQL (an toàn hơn).
 *
 * Khi kết nối thất bại:
 *   - Ghi vào error_log (không lộ thông tin ra màn hình người dùng).
 *   - Trả về HTTP 500 và thông báo thân thiện rồi dừng script.
 */
function db(): PDO
{
    static $pdo = null;   // biến tĩnh: chỉ tạo kết nối một lần duy nhất

    if ($pdo === null) {
        // Chuỗi kết nối DSN: host, tên CSDL, bộ ký tự UTF-8 đầy đủ (emoji, tiếng Việt)
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lỗi SQL -> ném exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                    // dùng prepared statement thật
            ]);
            // Đồng bộ múi giờ giữa NOW() của MySQL và date() của PHP (UTC+7)
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $ex) {
            // Ghi lỗi vào log, KHÔNG hiển thị chi tiết ra màn hình (bảo mật)
            error_log('DB connect error: ' . $ex->getMessage());
            http_response_code(500);
            exit('Không kết nối được cơ sở dữ liệu. Hãy bật MySQL trong XAMPP và kiểm tra lại config/config.php.');
        }
    }

    return $pdo;
}
