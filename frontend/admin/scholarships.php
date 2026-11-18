<?php
/**
 * Trang quản lý chương trình học bổng (Admin).
 *
 * Chức năng:
 *   - Xem danh sách toàn bộ chương trình (có tìm kiếm, lọc theo loại/trạng thái, phân trang).
 *   - Sửa: chuyển đến admin/scholarship_form.php?id=...
 *   - Xóa: chỉ được xóa khi chưa có hồ sơ nào; nếu đã có hồ sơ thì gợi ý đóng thay vì xóa.
 *          Khi xóa thành công: xóa luôn file ảnh bìa trên đĩa (delete_cover_file).
 *
 * Bảo mật:
 *   - Mọi thao tác POST kiểm tra CSRF token.
 *   - Tham số lọc đều qua bind (chống SQL Injection).
 *   - Xóa: kiểm tra ràng buộc hồ sơ trước (không cho xóa nếu có đơn đăng ký).
 */
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo = db();

/* ---------- Xóa chương trình ---------- */
if (is_post()) {
    csrf_verify();
    if (post_str('action') === 'delete') {
        $id = post_int('id');
        $st = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE scholarship_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('danger', 'Không thể xóa: chương trình đã có hồ sơ đăng ký. Hãy đóng chương trình thay vì xóa.');
        } else {
            // Lấy tên ảnh bìa trước khi xóa bản ghi để dọn luôn file trên đĩa
            $st = $pdo->prepare('SELECT image FROM scholarships WHERE id = ?');
            $st->execute([$id]);
            $image = $st->fetchColumn();

            $st = $pdo->prepare('DELETE FROM scholarships WHERE id = ?');
            $st->execute([$id]);
            if ($st->rowCount()) {
                delete_cover_file($image ?: null);
                flash('success', 'Đã xóa chương trình và ảnh bìa.');
            } else {
                flash('warning', 'Không tìm thấy chương trình.');
            }
        }
    }
    redirect('admin/scholarships.php');
}

/* ---------- Tìm kiếm / lọc / phân trang ---------- */
$q      = get_str('q');
$typeId = get_int('type');
$status = in_array(get_str('status'), ['open', 'closed'], true) ? get_str('status') : '';
$page   = max(1, get_int('page', 1));

$types = $pdo->query('SELECT id, name FROM scholarship_types ORDER BY name')->fetchAll();

$where  = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = 's.title LIKE :q';
    $params[':q'] = like_value($q);
}
if ($typeId > 0) {
    $where[] = 's.type_id = :type_id';
    $params[':type_id'] = $typeId;
}
if ($status !== '') {
    $where[] = 's.status = :status';
    $params[':status'] = $status;
}
$whereSql = implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) FROM scholarships s WHERE $whereSql");
bind_all($st, $params);
$st->execute();
$pg = paginate((int)$st->fetchColumn(), PER_PAGE_ADMIN, $page);

$st = $pdo->prepare(
    "SELECT s.*, t.name AS type_name,
            (SELECT COUNT(*) FROM applications a WHERE a.scholarship_id = s.id AND a.status = 'approved') AS approved_count,
            (SELECT COUNT(*) FROM applications a WHERE a.scholarship_id = s.id AND a.status = 'submitted') AS pending_count
     FROM scholarships s JOIN scholarship_types t ON t.id = s.type_id
     WHERE $whereSql
     ORDER BY s.close_date DESC, s.id DESC
     LIMIT :limit OFFSET :offset"
);
bind_all($st, $params + [':limit' => $pg['per_page'], ':offset' => $pg['offset']]);
$st->execute();
$rows = $st->fetchAll();

$pageTitle = 'Chương trình học bổng';
$activeNav = 'admin-scholarships';
require __DIR__ . '/../../backend/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0">Chương trình học bổng</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/scholarship_form.php')) ?>">Thêm chương trình</a>
</div>

<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-4">
        <label class="form-label small mb-1" for="q">Từ khóa</label>
        <input class="form-control" id="q" name="q" maxlength="100" placeholder="Tên chương trình" value="<?= e($q) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1" for="type">Loại</label>
        <select class="form-select" id="type" name="type">
            <option value="0">Tất cả</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $typeId === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1" for="status">Trạng thái</label>
        <select class="form-select" id="status" name="status">
            <option value="">Tất cả</option>
            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Đang mở</option>
            <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Đã đóng</option>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary flex-fill" type="submit">Lọc</button>
        <a class="btn btn-outline-primary" href="<?= e(url('admin/scholarships.php')) ?>">Xóa</a>
    </div>
</form>

<p class="text-muted small"><?= (int)$pg['total'] ?> chương trình</p>
<div class="table-wrap">
    <table class="table align-middle">
        <thead><tr>
            <th style="width: 84px">Ảnh</th><th>Chương trình</th><th>Hạn nộp</th><th class="text-end">Suất</th>
            <th class="text-end">Chờ duyệt</th><th>Trạng thái</th><th></th>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Không có chương trình phù hợp.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $s):
            $expired = strtotime($s['close_date']) < strtotime(date('Y-m-d'));
        ?>
            <tr>
                <td><img src="<?= e(cover_url($s)) ?>" alt="" class="cover-thumb"></td>
                <td>
                    <div class="fw-medium"><?= e($s['title']) ?></div>
                    <div class="small text-muted"><?= e($s['type_name']) ?> · <?= e(fmt_money($s['amount'])) ?></div>
                </td>
                <td class="text-nowrap"><?= e(fmt_date($s['close_date'])) ?><?php if ($expired): ?> <span class="badge text-bg-light border">Hết hạn</span><?php endif; ?></td>
                <td class="text-end text-nowrap"><?= (int)$s['approved_count'] ?>/<?= (int)$s['slots'] ?></td>
                <td class="text-end"><?= (int)$s['pending_count'] ?></td>
                <td><span class="badge text-bg-<?= $s['status'] === 'open' ? 'success' : 'secondary' ?>"><?= $s['status'] === 'open' ? 'Đang mở' : 'Đã đóng' ?></span></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/applications.php?scholarship_id=' . (int)$s['id'])) ?>">Hồ sơ</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/scholarship_form.php?id=' . (int)$s['id'])) ?>">Sửa</a>
                    <form method="post" class="d-inline" data-confirm="Xóa chương trình &quot;<?= e($s['title']) ?>&quot;?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= pagination_links($pg, ['q' => $q, 'type' => $typeId, 'status' => $status]) ?></div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
