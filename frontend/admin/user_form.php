<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo    = db();
$id     = is_post() ? post_int('id') : get_int('id');
$errors = [];
$row    = null;

/* ---------- Nạp dữ liệu khi sửa (chỉ tài khoản sinh viên) ---------- */
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('warning', 'Không tìm thấy tài khoản sinh viên.');
        redirect('admin/users.php');
    }
}

/* ---------- Lưu (thêm mới hoặc cập nhật) ---------- */
if (is_post()) {
    csrf_verify();

    $code     = post_str('student_code');
    $name     = post_str('full_name');
    $email    = mb_strtolower(post_str('email'));
    $phone    = post_str('phone');
    $class    = post_str('class_name');
    $password = post_raw('password');
    $status   = post_str('status') === 'locked' ? 'locked' : 'active';

    if (!valid_student_code($code))  $errors['student_code'] = 'Mã số sinh viên gồm 8-12 chữ số.';
    if (!valid_name($name))          $errors['full_name']    = 'Họ tên phải từ 2 đến 100 ký tự.';
    if (!valid_email($email))        $errors['email']        = 'Email không hợp lệ.';
    if (!valid_phone($phone))        $errors['phone']        = 'Số điện thoại gồm 10 chữ số, bắt đầu bằng 0 (hoặc +84).';
    if ($class === '' || mb_strlen($class) > 50) $errors['class_name'] = 'Vui lòng nhập lớp (tối đa 50 ký tự).';
    if ($row === null || $password !== '') {
        if (!valid_password($password)) $errors['password'] = 'Mật khẩu tối thiểu 8 ký tự, gồm cả chữ và số.';
    }

    if (!$errors) {
        try {
            if ($row) {
                $sql    = 'UPDATE users SET student_code = ?, full_name = ?, email = ?, phone = ?, class_name = ?, status = ?';
                $params = [$code, $name, $email, $phone, $class, $status];
                if ($password !== '') {
                    $sql .= ', password_hash = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ? AND role = 'student'";
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
                flash('success', 'Đã cập nhật tài khoản ' . $name . '.');
            } else {
                $pdo->prepare(
                    "INSERT INTO users (student_code, full_name, email, phone, class_name, password_hash, role, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'student', ?)"
                )->execute([$code, $name, $email, $phone, $class, password_hash($password, PASSWORD_DEFAULT), $status]);
                flash('success', 'Đã thêm sinh viên ' . $name . '.');
            }
            redirect('admin/users.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $msg = $ex->getMessage();
                if (str_contains($msg, 'student_code')) {
                    $errors['student_code'] = 'Mã số sinh viên này đã tồn tại.';
                } else {
                    $errors['email'] = 'Email này đã được sử dụng.';
                }
            } else {
                error_log($ex->getMessage());
                flash('danger', 'Không thể lưu, vui lòng thử lại.');
            }
        }
    }

    // Giữ lại dữ liệu đã nhập khi có lỗi
    $row = array_merge($row ?? [], [
        'student_code' => $code, 'full_name' => $name, 'email' => $email,
        'phone' => $phone, 'class_name' => $class, 'status' => $status,
    ]);
}

$isEdit    = $row !== null && $id > 0;
$pageTitle = $isEdit ? 'Sửa sinh viên' : 'Thêm sinh viên';
$activeNav = 'admin-users';
require __DIR__ . '/../../backend/includes/header.php';

// Hàm lấy giá trị hiển thị: ưu tiên dữ liệu vừa gửi (khi lỗi), sau đó là dữ liệu trong CSDL
$val = fn(string $k) => e((string)($row[$k] ?? ''));
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-2">
        <li class="breadcrumb-item"><a href="<?= e(url('admin/users.php')) ?>">Tài khoản sinh viên</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Sửa' : 'Thêm mới' ?></li>
    </ol>
</nav>
<h1 class="h3 mb-3"><?= $isEdit ? 'Sửa tài khoản sinh viên' : 'Thêm tài khoản sinh viên' ?></h1>

<div class="panel" style="max-width: 720px;">
    <form method="post" action="<?= e(url('admin/user_form.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="student_code">Mã số sinh viên <span class="text-danger">*</span></label>
                <input class="form-control <?= isset($errors['student_code']) ? 'is-invalid' : '' ?>" id="student_code"
                       name="student_code" required maxlength="12" inputmode="numeric" value="<?= $val('student_code') ?>">
                <?= field_error($errors, 'student_code') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="class_name">Lớp <span class="text-danger">*</span></label>
                <input class="form-control <?= isset($errors['class_name']) ? 'is-invalid' : '' ?>" id="class_name"
                       name="class_name" required maxlength="50" value="<?= $val('class_name') ?>">
                <?= field_error($errors, 'class_name') ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="full_name">Họ và tên <span class="text-danger">*</span></label>
                <input class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="full_name"
                       name="full_name" required maxlength="100" value="<?= $val('full_name') ?>">
                <?= field_error($errors, 'full_name') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email"
                       name="email" type="email" required maxlength="150" value="<?= $val('email') ?>">
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Số điện thoại <span class="text-danger">*</span></label>
                <input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone"
                       name="phone" type="tel" required maxlength="13" inputmode="tel" value="<?= $val('phone') ?>">
                <?= field_error($errors, 'phone') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password">Mật khẩu <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?></label>
                <input class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password"
                       name="password" type="password" maxlength="72" autocomplete="new-password"
                       <?= $isEdit ? '' : 'required' ?>
                       placeholder="<?= $isEdit ? 'Để trống nếu không đổi' : 'Tối thiểu 8 ký tự, gồm chữ và số' ?>">
                <?= field_error($errors, 'password') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="active" <?= ($row['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                    <option value="locked" <?= ($row['status'] ?? '') === 'locked' ? 'selected' : '' ?>>Đã khóa</option>
                </select>
            </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mt-4">
            <a class="btn btn-outline-primary" href="<?= e(url('admin/users.php')) ?>">Hủy</a>
            <button class="btn btn-primary" type="submit" data-once><?= $isEdit ? 'Lưu thay đổi' : 'Thêm sinh viên' ?></button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
