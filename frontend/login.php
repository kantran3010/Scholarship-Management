<?php
/**
 * Trang đăng nhập dành cho sinh viên (role = 'student').
 *
 * Quy trình xử lý:
 *   1. Nếu đã đăng nhập → chuyển hướng thẳng về trang chủ theo vai trò.
 *   2. Khi nhận POST: xác minh CSRF token trước.
 *   3. Gọi attempt_login() để kiểm tra email + mật khẩu từ CSDL (bcrypt).
 *   4. Nếu hợp lệ: lưu thông tin vào $_SESSION['user'], chuyển hướng.
 *   5. Nếu có tham số ?next=: sau đăng nhập sẽ quay về trang đó (chống open redirect).
 *
 * Bảo mật:
 *   - Mật khẩu lưu dạng bcrypt (password_hash), kiểm tra bằng password_verify.
 *   - Thông báo lỗi chung (không tiết lộ email có tồn tại hay không).
 *   - session_regenerate_id(true) sau khi đăng nhập thành công (chống session fixation).
 */
require_once __DIR__ . '/../backend/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}

$errors = [];
$next   = safe_next(is_post() ? post_str('next') : get_str('next'));

if (is_post()) {
    csrf_verify();
    $result = attempt_login(strtolower(post_str('email')), post_raw('password'), 'student');

    if (isset($result['user'])) {
        flash('success', 'Xin chào, ' . $result['user']['full_name'] . '!');
        redirect(($next !== '' && !str_starts_with($next, 'admin/')) ? $next : home_for($result['user']));
    }
    $errors[] = $result['error'];
}

$pageTitle = 'Đăng nhập';
require __DIR__ . '/../backend/includes/header.php';
?>

<div class="auth-split-wrap">
    <!-- Brand panel -->
    <div class="auth-split-brand">
        <div class="auth-brand-logo">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="auth-brand-name">Trường Đại học<br>Lao động – Xã hội CS2</div>
        <div class="auth-brand-sub">
            Hệ thống tiếp nhận hồ sơ học bổng trực tuyến dành cho sinh viên ULSA2.
        </div>
        <ul class="auth-brand-features">
            <li><i class="bi bi-shield-check-fill"></i> Bảo mật thông tin sinh viên</li>
            <li><i class="bi bi-bell-fill"></i> Thông báo kết quả xét duyệt kịp thời</li>
            <li><i class="bi bi-bookmark-star-fill"></i> Quản lý giỏ học bổng cá nhân</li>
            <li><i class="bi bi-cloud-arrow-up-fill"></i> Nộp hồ sơ trực tuyến 24/7</li>
        </ul>
    </div>

    <!-- Form panel -->
    <div class="auth-split-form">
        <h1 class="auth-form-title">Chào mừng trở lại!</h1>
        <p class="auth-form-sub">Đăng nhập vào tài khoản sinh viên của bạn.</p>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                <span><?= e($err) ?></span>
            </div>
        <?php endforeach; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email sinh viên</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent">
                        <i class="bi bi-envelope text-muted"></i>
                    </span>
                    <input class="form-control" type="email" id="email" name="email"
                           required maxlength="150" autocomplete="username"
                           placeholder="email@sinh.vien.edu.vn"
                           value="<?= old('email') ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold" for="password">Mật khẩu</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent">
                        <i class="bi bi-lock text-muted"></i>
                    </span>
                    <input class="form-control" type="password" id="password" name="password"
                           required autocomplete="current-password" placeholder="••••••••">
                </div>
            </div>

            <button class="btn btn-primary w-100 py-2 fw-bold" type="submit"
                    style="border-radius:10px;min-height:44px" data-once>
                <i class="bi bi-box-arrow-in-right me-2"></i> Đăng nhập
            </button>
        </form>

        <div class="auth-divider">hoặc</div>

        <p class="text-center text-muted small mb-1">
            Chưa có tài khoản?
            <a href="<?= e(url('register.php')) ?>" class="fw-semibold">Tạo tài khoản ngay</a>
        </p>
        <p class="text-center text-muted small mb-0">
            Quản trị viên?
            <a href="<?= e(url('admin/login.php')) ?>" class="text-muted">Đăng nhập tại đây</a>
        </p>
    </div>
</div>

<?php require __DIR__ . '/../backend/includes/footer.php'; ?>
