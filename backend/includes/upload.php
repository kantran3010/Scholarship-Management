<?php
/**
 * Xử lý tải tập tin lên (minh chứng của sinh viên và ảnh bìa chương trình).
 *
 * Quy trình kiểm tra dùng chung cho mọi loại tập tin:
 *   1. Kiểm tra lỗi upload của PHP;
 *   2. Kiểm tra dung lượng;
 *   3. Kiểm tra phần mở rộng nằm trong danh sách cho phép;
 *   4. Kiểm tra loại tập tin THẬT bằng finfo (không tin đuôi file hay Content-Type do trình duyệt gửi);
 *   5. Đổi tên ngẫu nhiên khi lưu để tránh trùng tên và tránh dò đường dẫn.
 */

/** Bảng đuôi file -> các loại MIME thật được chấp nhận. */
function mime_map_documents(): array
{
    return [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];
}

function mime_map_covers(): array
{
    return [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];
}

/** Dịch mã lỗi upload của PHP sang thông báo tiếng Việt. */
function upload_error_message(int $code): string
{
    $map = [
        UPLOAD_ERR_INI_SIZE   => 'Tập tin vượt quá dung lượng cho phép của máy chủ (kiểm tra upload_max_filesize trong php.ini).',
        UPLOAD_ERR_FORM_SIZE  => 'Tập tin vượt quá dung lượng cho phép của form.',
        UPLOAD_ERR_PARTIAL    => 'Tập tin chỉ được tải lên một phần, vui lòng thử lại.',
        UPLOAD_ERR_NO_TMP_DIR => 'Lỗi máy chủ: thiếu thư mục tạm.',
        UPLOAD_ERR_CANT_WRITE => 'Lỗi máy chủ: không ghi được tập tin.',
    ];
    return $map[$code] ?? 'Lỗi không xác định khi tải tập tin.';
}

/**
 * Hàm lõi: kiểm tra và lưu một tập tin trong $_FILES[$field] vào thư mục $dir.
 * Trả về ['ok'=>true, 'stored_name', 'original_name', 'mime', 'size'] hoặc ['ok'=>false, 'error'].
 */
function store_uploaded_file(string $field, string $dir, array $mimeMap, int $maxBytes, string $typeLabel): array
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Chưa chọn tập tin.'];
    }
    $f = $_FILES[$field];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message((int)$f['error'])];
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        return ['ok' => false, 'error' => 'Tập tin tải lên không hợp lệ.'];
    }
    if ($f['size'] <= 0 || $f['size'] > $maxBytes) {
        $mb = round($maxBytes / 1024 / 1024, 1);
        return ['ok' => false, 'error' => "Tập tin phải nhỏ hơn {$mb}MB."];
    }

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!isset($mimeMap[$ext])) {
        return ['ok' => false, 'error' => "Chỉ chấp nhận tập tin {$typeLabel}."];
    }

    // Đọc "chữ ký" thật của file thay vì tin vào đuôi file
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $f['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $mimeMap[$ext], true)) {
        return ['ok' => false, 'error' => 'Nội dung tập tin không khớp với phần mở rộng .' . $ext . '.'];
    }

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('Không tạo được thư mục lưu trữ: ' . $dir);
        return ['ok' => false, 'error' => 'Lỗi máy chủ: không tạo được thư mục lưu trữ.'];
    }

    // Tên lưu trên đĩa hoàn toàn ngẫu nhiên, không dùng lại tên gốc
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], rtrim($dir, '/') . '/' . $stored)) {
        error_log('move_uploaded_file thất bại: ' . $dir . '/' . $stored);
        return ['ok' => false, 'error' => 'Lỗi máy chủ: không lưu được tập tin.'];
    }

    return [
        'ok'            => true,
        'stored_name'   => $stored,
        'original_name' => mb_substr($f['name'], 0, 255),
        'mime'          => $mime,
        'size'          => (int)$f['size'],
    ];
}

/** Minh chứng của sinh viên: PDF/JPG/PNG tối đa MAX_UPLOAD_BYTES, lưu trong uploads/ (không truy cập trực tiếp được). */
function handle_single_upload(string $field): array
{
    return store_uploaded_file($field, UPLOAD_DIR, mime_map_documents(), MAX_UPLOAD_BYTES, 'PDF, JPG hoặc PNG');
}

/** Ảnh bìa chương trình học bổng: JPG/PNG/WEBP tối đa COVER_MAX_BYTES, lưu công khai trong assets/img/scholarships/. */
function handle_cover_upload(string $field): array
{
    return store_uploaded_file($field, COVER_DIR, mime_map_covers(), COVER_MAX_BYTES, 'ảnh JPG, PNG hoặc WEBP');
}

/** Xóa file minh chứng vật lý (bỏ qua nếu file không tồn tại). */
function delete_uploaded_file(string $storedName): void
{
    $path = rtrim(UPLOAD_DIR, '/') . '/' . basename($storedName);
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Xóa file ảnh bìa vật lý. */
function delete_cover_file(?string $storedName): void
{
    if (!$storedName) {
        return;
    }
    $path = rtrim(COVER_DIR, '/') . '/' . basename($storedName);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Gửi nội dung một minh chứng về trình duyệt (dùng chung cho trang tải xuống của sinh viên và quản trị viên).
 * $doc là một dòng của bảng documents. Hàm này kết thúc script.
 */
function send_document_file(array $doc): void
{
    $path = rtrim(UPLOAD_DIR, '/') . '/' . basename($doc['stored_name']);
    if (!is_file($path)) {
        abort_page(404, 'Tập tin không còn tồn tại trên máy chủ.');
    }

    header('Content-Type: ' . $doc['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($doc['original_name']));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
