<?php
/**
 * Hàm dùng chung: escape, điều hướng, flash message, CSRF, xác thực & phân quyền,
 * kiểm tra dữ liệu, phân trang, định dạng, trạng thái hồ sơ, giỏ học bổng.
 *
 * Nhóm hàm:
 *   [1] Xuất HTML, đường dẫn, điều hướng   – e(), url(), redirect(), abort_page()
 *   [2] Đọc dữ liệu request                – post_str(), post_raw(), get_str(), get_int()...
 *   [3] Flash message                       – flash(), flash_pull()
 *   [4] CSRF                                – csrf_token(), csrf_field(), csrf_verify()
 *   [5] Xác thực & phân quyền              – current_user(), require_role(), attempt_login()...
 *   [6] Kiểm tra dữ liệu đầu vào (server)  – valid_name(), valid_email(), valid_phone()...
 *   [7] Phân trang                          – paginate(), pagination_links()
 *   [8] Định dạng hiển thị                 – fmt_dt(), fmt_date(), fmt_money(), days_left()
 *   [9] Trạng thái hồ sơ                   – app_statuses(), app_status_badge()
 *  [10] Ảnh bìa & bộ lọc học bổng         – cover_url(), amount_ranges(), scholarship_sorts()
 *  [11] Giỏ học bổng                        – cart_count(), cart_priorities()
 */

/* ------------------------------------------------------------------ */
/* Xuất HTML, đường dẫn, điều hướng                                    */
/* ------------------------------------------------------------------ */

/** Mã hóa ký tự đặc biệt khi in ra HTML (chống XSS). */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Tạo URL tuyệt đối từ đường dẫn tương đối trong dự án. */
function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/** Chuyển hướng rồi dừng script. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Đường dẫn trang hiện tại (kèm query) để quay lại sau khi đăng nhập. */
function current_page(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? 'index.php';
    $dir    = basename(dirname($script));
    $prefix = in_array($dir, ['admin', 'student'], true) ? $dir . '/' : '';
    $page   = $prefix . basename($script);
    $qs     = $_SERVER['QUERY_STRING'] ?? '';
    return $qs !== '' ? $page . '?' . $qs : $page;
}

/** Chỉ chấp nhận đường dẫn nội bộ dạng "student/trang.php?x=1" (chống open redirect). */
function safe_next(string $next): string
{
    if (preg_match('#^((admin|student)/)?[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$#', $next)) {
        return $next;
    }
    return '';
}

/** Hiển thị trang lỗi (403, 404...) bằng layout chung rồi dừng. */
function abort_page(int $code, string $message): void
{
    http_response_code($code);
    $pageTitle = 'Lỗi ' . $code;
    require __DIR__ . '/header.php';
    echo '<div class="alert alert-danger">' . e($message) . '</div>';
    echo '<a class="btn btn-outline-primary" href="' . e(url('index.php')) . '">Về trang chủ</a>';
    require __DIR__ . '/footer.php';
    exit;
}

/* ------------------------------------------------------------------ */
/* Đọc dữ liệu request                                                 */
/* ------------------------------------------------------------------ */

function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Mật khẩu: không trim, vì khoảng trắng có thể là một phần của mật khẩu. */
function post_raw(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? $v : '';
}

function get_str(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    return (is_string($v) && ctype_digit($v)) ? (int)$v : $default;
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? null;
    return (is_string($v) && ctype_digit($v)) ? (int)$v : $default;
}

/** Giá trị cũ của ô nhập (đã escape) để form không bị mất dữ liệu khi báo lỗi. */
function old(string $key, string $default = ''): string
{
    return e(isset($_POST[$key]) ? post_str($key) : $default);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function require_post(): void
{
    if (!is_post()) {
        http_response_code(405);
        exit('Phương thức không được phép.');
    }
}

/** Gắn tham số vào câu lệnh; số nguyên bind kiểu INT (cần cho LIMIT/OFFSET). */
function bind_all(PDOStatement $st, array $params): void
{
    foreach ($params as $key => $value) {
        $st->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

/** Chuẩn bị chuỗi cho LIKE: escape % và _ do người dùng nhập. */
function like_value(string $s): string
{
    return '%' . addcslashes($s, '%_\\') . '%';
}

/* ------------------------------------------------------------------ */
/* Flash message                                                       */
/* ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_pull(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ------------------------------------------------------------------ */
/* CSRF                                                                */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Gọi ở đầu mọi xử lý POST làm thay đổi dữ liệu. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Phiên làm việc không hợp lệ hoặc đã hết hạn. Vui lòng quay lại, tải lại trang và thử lại.');
    }
}

/* ------------------------------------------------------------------ */
/* Xác thực và phân quyền                                              */
/* ------------------------------------------------------------------ */

/** Script đang chạy có nằm trong thư mục admin/ (khu vực quản trị) không? */
function is_admin_area(): bool
{
    return basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin';
}

/**
 * Tên khóa session đang dùng. Quản trị viên và sinh viên có HAI session riêng biệt:
 *   $_SESSION['admin'] cho khu vực /admin, $_SESSION['user'] cho phần còn lại của website.
 * Nhờ vậy hai tài khoản có thể đăng nhập cùng lúc trên một trình duyệt mà không đè lên nhau.
 */
function session_key(): string
{
    return is_admin_area() ? 'admin' : 'user';
}

function current_user(): ?array
{
    return $_SESSION[session_key()] ?? null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

/** Trang chủ theo vai trò sau khi đăng nhập. */
function home_for(array $user): string
{
    return $user['role'] === 'admin' ? 'admin/index.php' : 'student/index.php';
}

/** Bắt buộc đăng nhập (vai trò nào cũng được). */
function require_login(): void
{
    if (current_user() === null) {
        flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect((is_admin_area() ? 'admin/login.php' : 'login.php') . '?next=' . urlencode(current_page()));
    }
}

/**
 * Bắt buộc đăng nhập đúng vai trò 'admin' hoặc 'student'.
 * Khu vực quản trị có trang đăng nhập riêng (admin/login.php).
 */
function require_role(string $role): void
{
    if (current_user() === null) {
        if ($role === 'admin') {
            flash('warning', 'Vui lòng đăng nhập bằng tài khoản quản trị.');
            redirect('admin/login.php?next=' . urlencode(current_page()));
        }
        require_login();
    }
    if (current_user()['role'] !== $role) {
        abort_page(403, $role === 'admin'
            ? 'Bạn không có quyền truy cập khu vực quản trị.'
            : 'Trang này chỉ dành cho tài khoản sinh viên.');
    }
}

/**
 * Xác thực email + mật khẩu cho một vai trò cụ thể ('admin' hoặc 'student').
 * Dùng chung cho login.php (sinh viên) và admin/login.php (quản trị viên).
 * Thành công: lưu thông tin vào session và trả về ['user' => [...]].
 * Thất bại: trả về ['error' => 'thông báo tiếng Việt'].
 */
function attempt_login(string $email, string $password, string $role): array
{
    if ($email === '' || $password === '') {
        return ['error' => 'Vui lòng nhập email và mật khẩu.'];
    }

    $st = db()->prepare(
        'SELECT id, student_code, full_name, email, role, status, password_hash FROM users WHERE email = ?'
    );
    $st->execute([$email]);
    $user = $st->fetch();

    // Thông báo chung, không tiết lộ email có tồn tại hay không
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['error' => 'Email hoặc mật khẩu không đúng.'];
    }
    if ($user['role'] !== $role) {
        return ['error' => $role === 'admin'
            ? 'Tài khoản này không có quyền quản trị.'
            : 'Đây là tài khoản quản trị. Vui lòng đăng nhập tại trang dành cho quản trị viên.'];
    }
    if ($user['status'] === 'locked') {
        return ['error' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.'];
    }

    session_regenerate_id(true);   // chống session fixation
    $key = $role === 'admin' ? 'admin' : 'user';   // session riêng cho quản trị viên
    $_SESSION[$key] = [
        'id'           => (int)$user['id'],
        'student_code' => $user['student_code'],
        'full_name'    => $user['full_name'],
        'email'        => $user['email'],
        'role'         => $user['role'],
    ];
    return ['user' => $_SESSION[$key]];
}

/**
 * Gọi ở mỗi request: nếu tài khoản đang đăng nhập bị khóa hoặc bị xóa thì đăng xuất ngay,
 * để việc "khóa tài khoản" của quản trị viên có hiệu lực tức thì.
 */
function refresh_session_user(): void
{
    foreach (['user', 'admin'] as $key) {
        if (!isset($_SESSION[$key])) {
            continue;
        }
        $st = db()->prepare('SELECT status FROM users WHERE id = ?');
        $st->execute([$_SESSION[$key]['id']]);
        if ($st->fetchColumn() !== 'active') {
            unset($_SESSION[$key], $_SESSION['csrf']);
            flash('danger', 'Tài khoản của bạn đã bị khóa hoặc không còn tồn tại.');
        }
    }
}

/* ------------------------------------------------------------------ */
/* Kiểm tra dữ liệu đầu vào (phía máy chủ)                             */
/* ------------------------------------------------------------------ */

function valid_name(string $s): bool
{
    $len = mb_strlen($s);
    return $len >= 2 && $len <= 100;
}

function valid_email(string $s): bool
{
    return mb_strlen($s) <= 150 && filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
}

/** Số điện thoại Việt Nam: 0xxxxxxxxx hoặc +84xxxxxxxxx. */
function valid_phone(string $s): bool
{
    return (bool)preg_match('/^(0|\+84)[0-9]{9}$/', $s);
}

/** Mã số sinh viên: 8-12 chữ số. */
function valid_student_code(string $s): bool
{
    return (bool)preg_match('/^[0-9]{8,12}$/', $s);
}

/** Mật khẩu tối thiểu 8 ký tự, có ít nhất 1 chữ và 1 số. */
function valid_password(string $s): bool
{
    return strlen($s) >= 8 && preg_match('/[A-Za-z]/', $s) && preg_match('/[0-9]/', $s);
}

/** In thông báo lỗi ngay dưới ô nhập. */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>' : '';
}

/* ------------------------------------------------------------------ */
/* Phân trang                                                          */
/* ------------------------------------------------------------------ */

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return [
        'total'    => $total,
        'per_page' => $perPage,
        'pages'    => $pages,
        'page'     => $page,
        'offset'   => ($page - 1) * $perPage,
    ];
}

/** Sinh thanh phân trang Bootstrap, giữ nguyên các tham số lọc trong $params. */
function pagination_links(array $pg, array $params = []): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    unset($params['page']);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== null);

    $item = function (int $n, string $label, bool $active = false, bool $disabled = false) use ($params): string {
        $cls  = 'page-item' . ($active ? ' active' : '') . ($disabled ? ' disabled' : '');
        $href = '?' . http_build_query($params + ['page' => $n]);
        return '<li class="' . $cls . '"><a class="page-link" href="' . e($href) . '">' . $label . '</a></li>';
    };

    $cur  = $pg['page'];
    $last = $pg['pages'];
    $html = '<nav aria-label="Phân trang"><ul class="pagination justify-content-center flex-wrap">';
    $html .= $item($cur - 1, '&laquo;', false, $cur <= 1);

    $prev = 0;
    for ($i = 1; $i <= $last; $i++) {
        if ($i === 1 || $i === $last || abs($i - $cur) <= 2) {
            if ($prev && $i - $prev > 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $html .= $item($i, (string)$i, $i === $cur);
            $prev = $i;
        }
    }

    $html .= $item($cur + 1, '&raquo;', false, $cur >= $last);
    return $html . '</ul></nav>';
}

/* ------------------------------------------------------------------ */
/* Định dạng                                                           */
/* ------------------------------------------------------------------ */

function fmt_dt(?string $s): string
{
    return $s ? date('d/m/Y H:i', strtotime($s)) : '';
}

function fmt_date(?string $s): string
{
    return $s ? date('d/m/Y', strtotime($s)) : '';
}

/** 8000000 -> "8.000.000 đ" */
function fmt_money($amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}

/** Số ngày còn lại đến hết hạn (0 = hôm nay là ngày cuối, âm = đã quá hạn). */
function days_left(string $closeDate): int
{
    return (int)floor((strtotime($closeDate) - strtotime(date('Y-m-d'))) / 86400);
}

/* ------------------------------------------------------------------ */
/* Trạng thái hồ sơ                                                    */
/* ------------------------------------------------------------------ */

/** [mã trạng thái => [nhãn tiếng Việt, màu badge Bootstrap]] */
function app_statuses(): array
{
    return [
        'draft'     => ['Bản nháp',     'secondary'],
        'submitted' => ['Đã nộp',       'primary'],
        'need_more' => ['Cần bổ sung',  'warning'],
        'approved'  => ['Đã duyệt',     'success'],
        'rejected'  => ['Không đạt',    'danger'],
    ];
}

function app_status_label(string $status): string
{
    return app_statuses()[$status][0] ?? $status;
}

function app_status_badge(string $status): string
{
    $s = app_statuses()[$status] ?? [$status, 'secondary'];
    return '<span class="badge text-bg-' . e($s[1]) . '">' . e($s[0]) . '</span>';
}

/* ------------------------------------------------------------------ */
/* Ảnh bìa chương trình & bộ lọc                                       */
/* ------------------------------------------------------------------ */

/**
 * URL ảnh bìa của một chương trình. Nếu chưa tải ảnh riêng thì dùng ảnh mặc định
 * (luân phiên theo id để các thẻ không giống hệt nhau).
 */
function cover_url(array $program): string
{
    if (!empty($program['image'])) {
        return url('assets/img/scholarships/' . $program['image']);
    }
    $defaults = DEFAULT_COVERS;
    return url('assets/img/' . $defaults[(int)$program['id'] % count($defaults)]);
}

/** Các khoảng giá trị học bổng dùng cho bộ lọc: [mã => [nhãn, từ, đến]] (null = không giới hạn). */
function amount_ranges(): array
{
    return [
        'lt5'   => ['Dưới 5 triệu',        null,     4999999],
        '5to10' => ['Từ 5 đến 10 triệu',   5000000,  10000000],
        'gt10'  => ['Trên 10 triệu',       10000001, null],
    ];
}

/** Các kiểu sắp xếp danh sách học bổng: [mã => [nhãn, mệnh đề ORDER BY]]. */
function scholarship_sorts(): array
{
    return [
        'deadline'    => ['Sắp hết hạn trước',    's.close_date ASC, s.id ASC'],
        'newest'      => ['Mới đăng nhất',        's.created_at DESC, s.id DESC'],
        'name_asc'    => ['Tên A → Z',            's.title ASC'],
        'name_desc'   => ['Tên Z → A',            's.title DESC'],
        'amount_asc'  => ['Giá trị tăng dần',     's.amount ASC, s.id ASC'],
        'amount_desc' => ['Giá trị giảm dần',     's.amount DESC, s.id ASC'],
    ];
}

/** Biểu tượng trực quan hóa danh mục học bổng. */
function category_icon(string $name): string
{
    $n = mb_strtolower($name, 'UTF-8');
    if (str_contains($n, 'khuyến khích')) return 'bi-mortarboard-fill';
    if (str_contains($n, 'vượt khó') || str_contains($n, 'khó khăn')) return 'bi-heart-pulse-fill';
    if (str_contains($n, 'doanh nghiệp') || str_contains($n, 'tài trợ')) return 'bi-building-fill';
    if (str_contains($n, 'khoa học') || str_contains($n, 'nghiên cứu')) return 'bi-lightbulb-fill';
    if (str_contains($n, 'tài năng') || str_contains($n, 'văn thể') || str_contains($n, 'thể thao')) return 'bi-trophy-fill';
    return 'bi-award-fill';
}

/** Biểu tượng cho tiêu chí sắp xếp. */
function sort_icon(string $code): string
{
    return match ($code) {
        'deadline'    => 'bi-hourglass-split',
        'newest'      => 'bi-stars',
        'name_asc'    => 'bi-sort-alpha-down',
        'name_desc'   => 'bi-sort-alpha-up-alt',
        'amount_asc'  => 'bi-sort-numeric-down',
        'amount_desc' => 'bi-sort-numeric-down-alt',
        default       => 'bi-arrow-down-up',
    };
}

/* ------------------------------------------------------------------ */
/* Giỏ học bổng                                                        */
/* ------------------------------------------------------------------ */

/** Số chương trình trong giỏ học bổng của sinh viên đang đăng nhập (0 nếu chưa đăng nhập). */
function cart_count(): int
{
    $u = $_SESSION['user'] ?? null;
    if (!$u || $u['role'] !== 'student') {
        return 0;
    }
    $st = db()->prepare('SELECT COUNT(*) FROM saved_scholarships WHERE user_id = ?');
    $st->execute([$u['id']]);
    return (int)$st->fetchColumn();
}

/** Nhãn mức ưu tiên trong giỏ: [mã => [nhãn, màu badge]]. */
function cart_priorities(): array
{
    return [1 => ['Cao', 'danger'], 2 => ['Trung bình', 'primary'], 3 => ['Thấp', 'secondary']];
}
