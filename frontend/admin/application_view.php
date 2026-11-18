<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo    = db();
$id     = get_int('id');
$adminId = (int)current_user()['id'];

$st = $pdo->prepare(
    'SELECT a.*, u.full_name, u.student_code, u.email AS student_email, u.phone AS student_phone, u.class_name,
            s.title AS scholarship_title, s.amount, s.slots, s.min_gpa, s.min_conduct, s.close_date, s.status AS scholarship_status
     FROM applications a
     JOIN users u ON u.id = a.user_id
     JOIN scholarships s ON s.id = a.scholarship_id
     WHERE a.id = ?'
);
$st->execute([$id]);
$app = $st->fetch();
if (!$app) {
    abort_page(404, 'Không tìm thấy hồ sơ này.');
}

$st = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE scholarship_id = ? AND status = 'approved'");
$st->execute([$app['scholarship_id']]);
$approvedCount = (int)$st->fetchColumn();
$slotsLeft     = max(0, (int)$app['slots'] - $approvedCount);

/* ---------- Xử lý xét duyệt ---------- */
if (is_post()) {
    csrf_verify();
    $newStatus = post_str('new_status');
    $note      = post_str('note');

    $canDecide = in_array($app['status'], ['submitted', 'need_more'], true);
    $validTargets = ['approved', 'rejected', 'need_more'];

    if (!$canDecide) {
        flash('danger', 'Hồ sơ này không ở trạng thái chờ xét duyệt.');
    } elseif (!in_array($newStatus, $validTargets, true)) {
        flash('danger', 'Hành động không hợp lệ.');
    } elseif (mb_strlen($note) > 500) {
        flash('danger', 'Ghi chú tối đa 500 ký tự.');
    } elseif ($newStatus === 'need_more' && $note === '') {
        flash('danger', 'Vui lòng nhập nội dung cần bổ sung cho sinh viên.');
    } elseif ($newStatus === 'rejected' && $note === '') {
        flash('danger', 'Vui lòng nhập lý do để sinh viên được biết.');
    } elseif ($newStatus === 'approved' && $slotsLeft <= 0) {
        flash('danger', 'Chương trình đã đủ số suất được duyệt, không thể duyệt thêm.');
    } else {
        try {
            $pdo->beginTransaction();

            if ($newStatus === 'approved') {
                // Khóa dòng chương trình rồi đếm lại số đã duyệt NGAY TRONG transaction: nếu hai quản
                // trị viên cùng bấm "Duyệt" cho hai hồ sơ khác nhau gần như cùng lúc, người thứ hai sẽ
                // phải chờ và thấy số liệu mới nhất, tránh duyệt vượt quá số suất của chương trình.
                $st = $pdo->prepare('SELECT slots FROM scholarships WHERE id = ? FOR UPDATE');
                $st->execute([$app['scholarship_id']]);
                $slots = (int)$st->fetchColumn();

                $st = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE scholarship_id = ? AND status = 'approved'");
                $st->execute([$app['scholarship_id']]);
                $approvedNow = (int)$st->fetchColumn();

                if ($approvedNow >= $slots) {
                    $pdo->rollBack();
                    flash('danger', 'Chương trình vừa đủ số suất (có thể quản trị viên khác vừa duyệt). Vui lòng tải lại trang.');
                    redirect('admin/application_view.php?id=' . $id);
                }
            }

            $pdo->prepare(
                'UPDATE applications SET status = ?, reviewer_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?'
            )->execute([$newStatus, $note !== '' ? $note : null, $adminId, $id]);
            $pdo->prepare(
                'INSERT INTO review_logs (application_id, changed_by, from_status, to_status, note) VALUES (?, ?, ?, ?, ?)'
            )->execute([$id, $adminId, $app['status'], $newStatus, $note !== '' ? $note : null]);
            $pdo->commit();

            $msg = ['approved' => 'Đã duyệt hồ sơ.', 'rejected' => 'Đã từ chối hồ sơ.', 'need_more' => 'Đã yêu cầu sinh viên bổ sung hồ sơ.'];
            flash('success', $msg[$newStatus]);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($ex->getMessage());
            flash('danger', 'Không thể cập nhật hồ sơ, vui lòng thử lại.');
        }
        redirect('admin/application_view.php?id=' . $id);
    }
}

$st = $pdo->prepare('SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at');
$st->execute([$id]);
$documents = $st->fetchAll();

$st = $pdo->prepare(
    'SELECT l.*, u.full_name AS actor_name FROM review_logs l JOIN users u ON u.id = l.changed_by
     WHERE l.application_id = ? ORDER BY l.created_at'
);
$st->execute([$id]);
$logs = $st->fetchAll();

$pageTitle = 'Hồ sơ của ' . $app['full_name'];
$activeNav = 'admin-applications';
require __DIR__ . '/../../backend/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= e(url('admin/applications.php')) ?>">Hồ sơ đăng ký</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e($app['full_name']) ?></li>
</ol></nav>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($app['full_name']) ?> <small class="text-muted">· <?= e($app['student_code']) ?></small></h1>
        <div class="text-muted"><?= e($app['scholarship_title']) ?></div>
    </div>
    <?= app_status_badge($app['status']) ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="panel mb-3">
            <h2 class="h5 mb-3">Thông tin sinh viên</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted fw-medium">Lớp</dt><dd class="col-sm-8"><?= e($app['class_name']) ?></dd>
                <dt class="col-sm-4 text-muted fw-medium">Email</dt><dd class="col-sm-8"><?= e($app['student_email']) ?></dd>
                <dt class="col-sm-4 text-muted fw-medium">Điện thoại</dt><dd class="col-sm-8"><?= e($app['student_phone']) ?></dd>
            </dl>
        </div>

        <div class="panel mb-3">
            <h2 class="h5 mb-3">Thông tin hồ sơ</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted fw-medium">GPA đã khai</dt>
                <dd class="col-sm-8"><?= e($app['gpa']) ?> <span class="text-muted small">(yêu cầu tối thiểu <?= rtrim(rtrim(number_format((float)$app['min_gpa'], 2), '0'), '.') ?>)</span>
                    <?= (float)$app['gpa'] < (float)$app['min_gpa'] ? '<span class="badge text-bg-danger ms-1">Chưa đạt</span>' : '<span class="badge text-bg-success ms-1">Đạt</span>' ?>
                </dd>
                <dt class="col-sm-4 text-muted fw-medium">Điểm rèn luyện</dt>
                <dd class="col-sm-8"><?= (int)$app['conduct_score'] ?> <span class="text-muted small">(yêu cầu tối thiểu <?= (int)$app['min_conduct'] ?>)</span>
                    <?= (int)$app['conduct_score'] < (int)$app['min_conduct'] ? '<span class="badge text-bg-danger ms-1">Chưa đạt</span>' : '<span class="badge text-bg-success ms-1">Đạt</span>' ?>
                </dd>
                <dt class="col-sm-4 text-muted fw-medium">Thư giới thiệu</dt>
                <dd class="col-sm-8" style="white-space: pre-line"><?= e($app['statement'] ?: '(không có)') ?></dd>
                <dt class="col-sm-4 text-muted fw-medium">Nộp lúc</dt>
                <dd class="col-sm-8"><?= e(fmt_dt($app['submitted_at'])) ?></dd>
            </dl>
        </div>

        <div class="panel mb-3">
            <h2 class="h5 mb-3">Minh chứng (<?= count($documents) ?>)</h2>
            <?php if (!$documents): ?>
                <p class="text-muted small mb-0">Chưa có minh chứng nào.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($documents as $d): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-medium small"><?= e($d['doc_type']) ?></div>
                                <div class="text-muted small"><?= e($d['original_name']) ?> · <?= number_format($d['file_size'] / 1024, 0) ?> KB</div>
                            </div>
                            <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"
                               href="<?= e(url('admin/document_download.php?id=' . (int)$d['id'])) ?>">Xem</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="panel mb-3">
            <h2 class="h5 mb-2">Số suất chương trình</h2>
            <p class="mb-0">Đã duyệt <strong><?= $approvedCount ?></strong> / <?= (int)$app['slots'] ?> suất — còn lại <strong><?= $slotsLeft ?></strong>.</p>
        </div>

        <?php if (in_array($app['status'], ['submitted', 'need_more'], true)): ?>
            <div class="panel mb-3">
                <h2 class="h5 mb-3">Xét duyệt</h2>
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="note">Ghi chú / lý do (bắt buộc khi từ chối hoặc yêu cầu bổ sung)</label>
                        <textarea class="form-control" id="note" name="note" rows="3" maxlength="500"></textarea>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <button class="btn btn-success" type="submit" name="new_status" value="approved"
                                <?= $slotsLeft <= 0 ? 'disabled' : '' ?>>Duyệt hồ sơ<?= $slotsLeft <= 0 ? ' (hết suất)' : '' ?></button>
                        <button class="btn btn-warning" type="submit" name="new_status" value="need_more">Yêu cầu bổ sung</button>
                        <button class="btn btn-outline-danger" type="submit" name="new_status" value="rejected">Từ chối</button>
                    </div>
                </form>
            </div>
        <?php elseif ($app['reviewer_note']): ?>
            <div class="panel mb-3">
                <h2 class="h5 mb-2">Ghi chú xét duyệt</h2>
                <p class="mb-0"><?= e($app['reviewer_note']) ?></p>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h2 class="h5 mb-3">Lịch sử xử lý</h2>
            <?php if (!$logs): ?>
                <p class="text-muted small mb-0">Chưa có lịch sử.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach (array_reverse($logs) as $l): ?>
                        <li class="py-2 border-bottom small">
                            <div><?= app_status_label($l['from_status'] ?? '') ?: '(mới)' ?> → <?= app_status_label($l['to_status']) ?></div>
                            <div class="text-muted"><?= e($l['actor_name']) ?> · <?= e(fmt_dt($l['created_at'])) ?></div>
                            <?php if ($l['note']): ?><div class="mt-1"><?= e($l['note']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
