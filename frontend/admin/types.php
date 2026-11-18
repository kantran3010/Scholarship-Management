<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo = db();

/* ---------- Xóa loại học bổng ---------- */
if (is_post()) {
    csrf_verify();
    if (post_str('action') === 'delete') {
        $id = post_int('id');
        $st = $pdo->prepare('SELECT COUNT(*) FROM scholarships WHERE type_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('danger', 'Không thể xóa: đã có chương trình học bổng thuộc loại này.');
        } else {
            $st = $pdo->prepare('DELETE FROM scholarship_types WHERE id = ?');
            $st->execute([$id]);
            flash($st->rowCount() ? 'success' : 'warning', $st->rowCount() ? 'Đã xóa loại học bổng.' : 'Không tìm thấy loại học bổng.');
        }
    }
    redirect('admin/types.php');
}

/* ---------- Thêm / sửa nhanh ngay trên danh sách ---------- */
$editId   = get_int('edit');
$editRow  = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM scholarship_types WHERE id = ?');
    $st->execute([$editId]);
    $editRow = $st->fetch() ?: null;
}

$errors = [];
if (is_post() === false) {
    // không xử lý gì thêm ở đây — chỉ để rõ nhánh GET
}

$pdo2 = $pdo; // (đổi tên biến tránh nhầm lẫn với $pdo ở trên khi đọc lại code)
$types = $pdo->query(
    "SELECT t.*, (SELECT COUNT(*) FROM scholarships s WHERE s.type_id = t.id) AS program_count
     FROM scholarship_types t ORDER BY t.name"
)->fetchAll();

$pageTitle = 'Loại học bổng';
$activeNav = 'admin-types';
require __DIR__ . '/../../backend/includes/header.php';
?>
<h1 class="h3 mb-3">Loại học bổng</h1>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel">
            <h2 class="h5 mb-3"><?= $editRow ? 'Sửa loại học bổng' : 'Thêm loại học bổng' ?></h2>
            <form method="post" action="<?= e(url('admin/type_save.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $editRow ? (int)$editRow['id'] : 0 ?>">
                <div class="mb-3">
                    <label class="form-label" for="name">Tên loại</label>
                    <input class="form-control" id="name" name="name" required maxlength="100"
                           value="<?= e($editRow['name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Mô tả</label>
                    <textarea class="form-control" id="description" name="description" rows="3" maxlength="255"><?= e($editRow['description'] ?? '') ?></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><?= $editRow ? 'Lưu thay đổi' : 'Thêm mới' ?></button>
                    <?php if ($editRow): ?>
                        <a class="btn btn-outline-primary" href="<?= e(url('admin/types.php')) ?>">Hủy sửa</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="table-wrap">
            <table class="table align-middle">
                <thead><tr><th>Tên loại</th><th>Mô tả</th><th class="text-end">Số chương trình</th><th></th></tr></thead>
                <tbody>
                <?php if (!$types): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có loại học bổng nào.</td></tr>
                <?php endif; ?>
                <?php foreach ($types as $t): ?>
                    <tr>
                        <td class="fw-medium"><?= e($t['name']) ?></td>
                        <td class="text-muted small"><?= e($t['description']) ?></td>
                        <td class="text-end"><?= (int)$t['program_count'] ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/types.php?edit=' . (int)$t['id'])) ?>">Sửa</a>
                            <form method="post" class="d-inline"
                                  data-confirm="Xóa loại &quot;<?= e($t['name']) ?>&quot;?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
