<?php
/**
 * Trang đăng ký tài khoản sinh viên.
 *
 * Các trường bắt buộc và quy tắc validate (phía server):
 *   - student_code : 8–12 chữ số (MSSV) – UNIQUE trong CSDL.
 *   - full_name    : 2–100 ký tự.
 *   - email        : định dạng hợp lệ, tối đa 150 ký tự – UNIQUE.
 *   - phone        : 0xxxxxxxxx hoặc +84xxxxxxxxx (10 chữ số).
 *   - class_name   : tối đa 50 ký tự.
 *   - password     : ≥ 8 ký tự, gồm cả chữ và số (không trim).
 *   - confirm      : phải khớp với password.
 *
 * Bảo mật:
 *   - Mật khẩu được băm bằng password_hash() (bcrypt) trước khi lưu vào CSDL.
 *   - Dữ liệu đầu vào kiểm tra cả phía PHP (server-side) trước khi INSERT.
 *   - Sử dụng PDO Prepared Statement để chống SQL Injection.
 *   - Kiểm tra trùng email/MSSV trước khi INSERT (tránh lỗi Duplicate Key khó đọc).
 */
require_once __DIR__ . '/../backend/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}

$errors = [];

if (is_post()) {
    csrf_verify();
    $code     = post_str('student_code');
    $name     = post_str('full_name');
    $email    = strtolower(post_str('email'));
    $phone    = post_str('phone');
    $class    = post_str('class_name');
    $password = post_raw('password');
    $confirm  = post_raw('confirm');

    if (!valid_student_code($code)) $errors['student_code'] = 'Mã số sinh viên gồm 8-12 chữ số.';
    if (!valid_name($name))         $errors['full_name']    = 'Họ tên phải từ 2 đến 100 ký tự.';
    if (!valid_email($email))       $errors['email']        = 'Email không hợp lệ.';
    if (!valid_phone($phone))       $errors['phone']        = 'Số điện thoại gồm 10 chữ số, bắt đầu bằng 0 (hoặc +84).';
    if ($class === '' || mb_strlen($class) > 50) $errors['class_name'] = 'Vui lòng nhập lớp (tối đa 50 ký tự).';
    if (!valid_password($password)) $errors['password']     = 'Mật khẩu tối thiểu 8 ký tự, gồm cả chữ và số.';
    elseif ($password !== $confirm) $errors['confirm']      = 'Mật khẩu nhập lại không khớp.';

    if (!$errors) {
        $pdo = db();
        $st  = $pdo->prepare('SELECT email, student_code FROM users WHERE email = ? OR student_code = ?');
        $st->execute([$email, $code]);
        foreach ($st->fetchAll() as $dup) {
            if ($dup['email'] === $email)       $errors['email']        = 'Email này đã được sử dụng.';
            if ($dup['student_code'] === $code) $errors['student_code'] = 'Mã số sinh viên này đã được đăng ký.';
        }
    }

    if (!$errors) {
        try {
            $pdo ??= db();
            $st = $pdo->prepare(
                "INSERT INTO users (student_code, full_name, email, phone, class_name, password_hash, role)
                 VALUES (?, ?, ?, ?, ?, ?, 'student')"
            );
            $st->execute([$code, $name, $email, $phone, $class, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Tạo tài khoản thành công. Hãy đăng nhập để bắt đầu.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors['email'] = 'Email hoặc mã số sinh viên đã tồn tại.';
            } else {
                error_log($ex->getMessage());
                $errors['form'] = 'Không thể tạo tài khoản lúc này, vui lòng thử lại.';
            }
        }
    }
}

$pageTitle = 'Tạo tài khoản';
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
            Tạo tài khoản để bắt đầu hành trình tìm kiếm học bổng phù hợp với bạn.
        </div>
        <ul class="auth-brand-features">
            <li><i class="bi bi-person-check-fill"></i> Đăng ký miễn phí trong 2 phút</li>
            <li><i class="bi bi-search-heart-fill"></i> Tìm học bổng phù hợp với MSSV</li>
            <li><i class="bi bi-cloud-arrow-up-fill"></i> Nộp hồ sơ trực tuyến 24/7</li>
            <li><i class="bi bi-bell-fill"></i> Nhận thông báo kết quả xét duyệt</li>
        </ul>
    </div>

    <!-- Form panel -->
    <div class="auth-split-form" style="max-width:600px">
        <h1 class="auth-form-title">Tạo tài khoản sinh viên</h1>
        <p class="auth-form-sub">Điền đầy đủ thông tin để bắt đầu.</p>

        <?php if (isset($errors['form'])): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                <span><?= e($errors['form']) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="student_code">Mã số sinh viên</label>
                    <input class="form-control <?= isset($errors['student_code']) ? 'is-invalid' : '' ?>"
                           id="student_code" name="student_code"
                           required inputmode="numeric" maxlength="12"
                           placeholder="VD: 240001234"
                           value="<?= old('student_code') ?>">
                    <?= field_error($errors, 'student_code') ?>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="class_name">Lớp</label>
                    <input class="form-control <?= isset($errors['class_name']) ? 'is-invalid' : '' ?>"
                           id="class_name" name="class_name"
                           required maxlength="50"
                           placeholder="VD: QTKD24A"
                           value="<?= old('class_name') ?>">
                    <?= field_error($errors, 'class_name') ?>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="full_name">Họ và tên</label>
                    <input class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                           id="full_name" name="full_name"
                           required minlength="2" maxlength="100" autocomplete="name"
                           placeholder="Nguyễn Văn A"
                           value="<?= old('full_name') ?>">
                    <?= field_error($errors, 'full_name') ?>
                </div>
                <div class="col-sm-7">
                    <label class="form-label fw-semibold" for="email">Email</label>
                    <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           type="email" id="email" name="email"
                           required maxlength="150" autocomplete="email"
                           placeholder="email@truong.edu.vn"
                           value="<?= old('email') ?>">
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="col-sm-5">
                    <label class="form-label fw-semibold" for="phone">Số điện thoại</label>
                    <input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                           type="tel" id="phone" name="phone"
                           required maxlength="12" autocomplete="tel"
                           placeholder="0912345678"
                           value="<?= old('phone') ?>">
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="password">Mật khẩu</label>
                    <input class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                           type="password" id="password" name="password"
                           required minlength="8" autocomplete="new-password"
                           placeholder="Tối thiểu 8 ký tự, gồm chữ và số">
                    <?= field_error($errors, 'password') ?>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="confirm">Nhập lại mật khẩu</label>
                    <input class="form-control <?= isset($errors['confirm']) ? 'is-invalid' : '' ?>"
                           type="password" id="confirm" name="confirm"
                           required autocomplete="new-password"
                           placeholder="••••••••">
                    <?= field_error($errors, 'confirm') ?>
                </div>
                <div class="col-12">
                    <div class="form-text text-muted" style="font-size:.8rem">
                        <i class="bi bi-info-circle me-1"></i>
                        Mật khẩu tối thiểu 8 ký tự, bao gồm cả chữ cái và chữ số.
                    </div>
                </div>
            </div>

            <button class="btn btn-primary w-100 py-2 fw-bold mt-3"
                    type="submit" style="border-radius:10px;min-height:44px" data-once>
                <i class="bi bi-person-plus-fill me-2"></i> Tạo tài khoản
            </button>
        </form>

        <div class="auth-divider">đã có tài khoản?</div>
        <div class="text-center">
            <a href="<?= e(url('login.php')) ?>" class="btn btn-outline-secondary"
               style="border-radius:10px;min-height:44px;min-width:180px">
                <i class="bi bi-box-arrow-in-right me-2"></i> Đăng nhập
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../backend/includes/footer.php'; ?>
