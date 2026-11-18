<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}

$errors = [];
$next   = safe_next(is_post() ? post_str('next') : get_str('next'));

if (is_post()) {
    csrf_verify();
    // Trang đăng nhập riêng của quản trị viên: tài khoản sinh viên không đăng nhập được ở đây
    $result = attempt_login(strtolower(post_str('email')), post_raw('password'), 'admin');

    if (isset($result['user'])) {
        flash('success', 'Xin chào quản trị viên ' . $result['user']['full_name'] . '!');
        redirect(str_starts_with($next, 'admin/') ? $next : 'admin/index.php');
    }
    $errors[] = $result['error'];
}

$pageTitle = 'Đăng nhập quản trị';
require __DIR__ . '/../../backend/includes/header.php';
?>
<div class="auth-card panel">
    <h1 class="h4 mb-1">Đăng nhập quản trị</h1>
    <p class="text-muted small mb-3">Khu vực dành riêng cho quản trị viên hệ thống.</p>
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger py-2"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="mb-3">
            <label class="form-label" for="email">Email quản trị</label>
            <input class="form-control" type="email" id="email" name="email" required maxlength="150"
                   autocomplete="username" value="<?= old('email') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Mật khẩu</label>
            <input class="form-control" type="password" id="password" name="password" required
                   autocomplete="current-password">
        </div>
        <button class="btn btn-primary w-100" type="submit" data-once>Đăng nhập</button>
    </form>
    <p class="text-muted small mt-3 mb-0">Bạn là sinh viên? <a href="<?= e(url('login.php')) ?>">Đăng nhập tại đây</a></p>
</div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
