<?php
require_once __DIR__ . '/../backend/includes/bootstrap.php';
require_login();

$pdo = db();
$me  = current_user();

$errors = [];

if (is_post()) {
    csrf_verify();
    $current = post_raw('current_password');
    $new     = post_raw('new_password');
    $confirm = post_raw('confirm_password');

    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$me['id']]);
    $hash = $st->fetchColumn();

    if (!$hash || !password_verify($current, $hash)) {
        $errors['current_password'] = 'Mật khẩu hiện tại không đúng.';
    }
    if (!valid_password($new)) {
        $errors['new_password'] = 'Mật khẩu mới tối thiểu 8 ký tự, gồm cả chữ và số.';
    } elseif ($new !== $confirm) {
        $errors['confirm_password'] = 'Mật khẩu nhập lại không khớp.';
    } elseif (!$errors && password_verify($new, $hash)) {
        $errors['new_password'] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    }

    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);

        // Đổi mật khẩu xong, bắt đăng nhập lại để vô hiệu hóa phiên cũ
        unset($_SESSION[session_key()], $_SESSION['csrf']);
        session_regenerate_id(true);
        flash('success', 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại với mật khẩu mới.');
        redirect(is_admin_area() ? 'admin/login.php' : 'login.php');
    }
}

$pageTitle = 'Cài đặt tài khoản & Mật khẩu';
require __DIR__ . '/../backend/includes/header.php';
?>

<div class="container py-4">
    <div class="student-page-wrap" style="max-width:800px">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="page-breadcrumb">
            <a href="<?= e(url('index.php')) ?>"><i class="bi bi-house"></i> Trang chủ</a>
            <span class="bc-sep">/</span>
            <?php if ($me['role'] === 'student'): ?>
                <a href="<?= e(url('student/index.php')) ?>">Trang sinh viên</a>
                <span class="bc-sep">/</span>
            <?php endif; ?>
            <span class="bc-current">Cài đặt tài khoản</span>
        </nav>

        <!-- User Info Strip -->
        <div class="student-welcome-banner mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="welcome-avatar">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div>
                    <div class="welcome-name"><?= e($me['full_name']) ?></div>
                    <div class="welcome-sub">
                        Email: <?= e($me['email']) ?>
                        <?php if (!empty($me['student_code'])): ?>
                            &nbsp;·&nbsp; MSSV: <?= e($me['student_code']) ?>
                        <?php endif; ?>
                        &nbsp;·&nbsp; Vai trò: <span class="badge bg-light text-dark text-uppercase"><?= e($me['role']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-7">
                <div class="table-card p-4">
                    <h1 class="h5 fw-bold mb-3">Đổi mật khẩu bảo vệ</h1>

                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="current_password">Mật khẩu hiện tại</label>
                            <input class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>"
                                   type="password" id="current_password" name="current_password" required
                                   autocomplete="current-password" placeholder="••••••••">
                            <?= field_error($errors, 'current_password') ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="new_password">Mật khẩu mới</label>
                            <input class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                                   type="password" id="new_password" name="new_password" required minlength="8"
                                   autocomplete="new-password" placeholder="Tối thiểu 8 ký tự, gồm cả chữ và số">
                            <?= field_error($errors, 'new_password') ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold" for="confirm_password">Nhập lại mật khẩu mới</label>
                            <input class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                   type="password" id="confirm_password" name="confirm_password" required
                                   autocomplete="new-password" placeholder="••••••••">
                            <?= field_error($errors, 'confirm_password') ?>
                        </div>

                        <button class="btn btn-primary w-100 py-2 fw-bold" type="submit" data-once style="border-radius:10px;min-height:44px">
                            <i class="bi bi-key-fill me-2"></i> Cập nhật mật khẩu
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-5">
                <div class="table-card p-4">
                    <h2 class="h6 fw-bold mb-3 text-primary"><i class="bi bi-shield-check me-2"></i>Quy chuẩn an toàn</h2>
                    <ul class="detail-requirement-list mb-3" style="font-size:0.85rem">
                        <li>Mật khẩu mới phải có ít nhất 8 ký tự.</li>
                        <li>Chứa cả chữ cái (a-z, A-Z) và chữ số (0-9).</li>
                        <li>Không sử dụng lại mật khẩu cũ gần đây.</li>
                        <li>Không chia sẻ tài khoản hoặc mật khẩu cho người khác.</li>
                    </ul>
                    <div class="alert alert-secondary small mb-0">
                        <i class="bi bi-info-circle me-1"></i> Sau khi đổi mật khẩu, hệ thống sẽ tự động đăng xuất các phiên đăng nhập cũ để bảo đảm an toàn.
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../backend/includes/footer.php'; ?>
