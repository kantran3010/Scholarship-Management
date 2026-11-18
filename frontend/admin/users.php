<?php
/**
 * Trang quản lý tài khoản sinh viên (Admin).
 *
 * Chức năng:
 *   - Xem danh sách sinh viên đã đăng ký (tên, email, MSSV, lớp, số hồ sơ, ngày đăng ký).
 *   - Tìm kiếm theo tên, email hoặc MSSV; lọc theo trạng thái (active/locked).
 *   - Khóa / Mở khóa tài khoản (cập nhật users.status; tài khoản bị khóa không đăng nhập được).
 *   - Xóa tài khoản (chỉ được xóa khi sinh viên chưa có hồ sơ nào; nếu có hồ sơ → gợi ý khóa).
 *   - Phân trang với PER_PAGE_ADMIN bản ghi mỗi trang.
 *
 * Bảo mật:
 *   - Không thể khóa/xóa tài khoản admin hoặc tài khoản của chính mình.
 *   - Mọi thao tác POST kiểm tra CSRF token.
 *   - Tìm kiếm dùng Prepared Statement (chống SQL Injection).
 */
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo  = db();
$meId = (int)current_user()['id'];

/* ---------- Khóa / mở khóa tài khoản sinh viên ---------- */
if (is_post()) {
    csrf_verify();
    $userId = post_int('id');
    $action = post_str('action');

    $st = $pdo->prepare('SELECT id, role, status, full_name FROM users WHERE id = ?');
    $st->execute([$userId]);
    $u = $st->fetch();

    if (!$u) {
        flash('warning', 'Không tìm thấy người dùng.');
    } elseif ($u['role'] === 'admin' || (int)$u['id'] === $meId) {
        flash('danger', 'Không thể khóa tài khoản quản trị.');   // tránh tự khóa mình hoặc khóa admin khác
    } elseif ($action === 'delete') {
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE user_id = ?');
        $cnt->execute([$userId]);
        if ((int)$cnt->fetchColumn() > 0) {
            flash('warning', 'Không thể xóa ' . $u['full_name'] . ' vì đã có hồ sơ đăng ký. Hãy khóa tài khoản thay thế.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$userId]);
            flash('success', 'Đã xóa tài khoản ' . $u['full_name'] . '.');
        }
    } elseif ($action === 'lock' || $action === 'unlock') {
        $new = $action === 'lock' ? 'locked' : 'active';
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $userId]);
        flash('success', ($action === 'lock' ? 'Đã khóa tài khoản ' : 'Đã mở khóa tài khoản ') . $u['full_name'] . '.');
    }
    redirect('admin/users.php?' . http_build_query(array_filter([
        'q' => get_str('q'), 'status' => get_str('status'), 'page' => get_int('page', 1) > 1 ? get_int('page') : null,
    ])));
}

/* ---------- Tìm kiếm / lọc / phân trang ---------- */
$q      = get_str('q');
$status = in_array(get_str('status'), ['active', 'locked'], true) ? get_str('status') : '';
$page   = max(1, get_int('page', 1));

$where  = ["u.role = 'student'"];
$params = [];
if ($q !== '') {
    $where[] = '(u.full_name LIKE :q1 OR u.email LIKE :q2 OR u.student_code LIKE :q3)';
    $params[':q1'] = $params[':q2'] = $params[':q3'] = like_value($q);
}
if ($status !== '') {
    $where[] = 'u.status = :status';
    $params[':status'] = $status;
}
$whereSql = implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
bind_all($st, $params);
$st->execute();
$pg = paginate((int)$st->fetchColumn(), PER_PAGE_ADMIN, $page);

$st = $pdo->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM applications a WHERE a.user_id = u.id) AS app_count
     FROM users u
     WHERE $whereSql
     ORDER BY u.created_at DESC, u.id DESC
     LIMIT :limit OFFSET :offset"
);
bind_all($st, $params + [':limit' => $pg['per_page'], ':offset' => $pg['offset']]);
$st->execute();
$users = $st->fetchAll();

$pageTitle = 'Người dùng';
$activeNav = 'admin-users';
require __DIR__ . '/../../backend/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Tài khoản sinh viên</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/user_form.php')) ?>"><i class="bi bi-person-plus me-1"></i>Thêm sinh viên</a>
</div>

<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-5">
        <label class="form-label small mb-1" for="q">Từ khóa</label>
        <input class="form-control" id="q" name="q" maxlength="100" placeholder="Tên, email hoặc MSSV" value="<?= e($q) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-1" for="status">Trạng thái</label>
        <select class="form-select" id="status" name="status">
            <option value="">Tất cả</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
            <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Đã khóa</option>
        </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-fill" type="submit">Lọc</button>
        <a class="btn btn-outline-primary" href="<?= e(url('admin/users.php')) ?>">Xóa</a>
    </div>
</form>

<p class="text-muted small"><?= (int)$pg['total'] ?> sinh viên</p>
<div class="table-wrap">
    <table class="table align-middle">
        <thead><tr><th>Sinh viên</th><th>Email</th><th>Lớp</th><th class="text-end">Hồ sơ</th><th>Ngày đăng ký</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        <?php if (!$users): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Không có sinh viên phù hợp.</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u): $locked = $u['status'] === 'locked'; ?>
            <tr>
                <td>
                    <div class="fw-medium"><?= e($u['full_name']) ?></div>
                    <div class="small text-muted"><?= e($u['student_code']) ?></div>
                </td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['class_name']) ?></td>
                <td class="text-end"><?= (int)$u['app_count'] ?></td>
                <td class="text-nowrap"><?= e(fmt_date($u['created_at'])) ?></td>
                <td><span class="badge text-bg-<?= $locked ? 'danger' : 'success' ?>"><?= $locked ? 'Đã khóa' : 'Hoạt động' ?></span></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/user_form.php?id=' . (int)$u['id'])) ?>" title="Sửa">
                        <i class="bi bi-pencil"></i><span class="d-none d-xl-inline ms-1">Sửa</span></a>
                    <form method="post" class="d-inline"
                          data-confirm="<?= $locked ? 'Mở khóa' : 'Khóa' ?> tài khoản &quot;<?= e($u['full_name']) ?>&quot;?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="<?= $locked ? 'unlock' : 'lock' ?>">
                        <button class="btn btn-sm <?= $locked ? 'btn-outline-success' : 'btn-outline-warning' ?>" type="submit">
                            <?= $locked ? 'Mở khóa' : 'Khóa' ?>
                        </button>
                    </form>
                    <form method="post" class="d-inline"
                          data-confirm="Xóa vĩnh viễn tài khoản &quot;<?= e($u['full_name']) ?>&quot;? Thao tác này không thể hoàn tác.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= pagination_links($pg, ['q' => $q, 'status' => $status]) ?></div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
