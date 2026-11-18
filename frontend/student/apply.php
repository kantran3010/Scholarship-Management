<?php
/**
 * Trang nộp hồ sơ đăng ký học bổng của sinh viên.
 *
 * Các action (POST):
 *   save        – Lưu nháp / cập nhật thông tin hồ sơ (GPA, điểm rèn luyện, thư giới thiệu).
 *   upload      – Tải minh chứng lên (PDF/JPG/PNG, tối đa MAX_FILES_PER_APP file).
 *   delete_doc  – Xóa một minh chứng đã tải lên (xóa cả file vật lý).
 *   submit      – Nộp hồ sơ chính thức (kiểm tra GPA/điểm rèn luyện đủ điều kiện,
 *                 phải có ít nhất 1 minh chứng, chương trình còn đang mở).
 *   cancel      – Hủy hồ sơ đang ở trạng thái 'draft' (xóa hồ sơ + file minh chứng).
 *
 * Trạng thái hồ sơ (status):
 *   draft      → sinh viên đang soạn, chưa nộp (có thể sửa, hủy).
 *   submitted  → đã nộp, chờ admin xét duyệt (không thể sửa).
 *   need_more  → admin yêu cầu bổ sung (có thể sửa, tải thêm minh chứng, nộp lại).
 *   approved   → đã được duyệt (chỉ đọc).
 *   rejected   → không đạt (chỉ đọc).
 *
 * Bảo mật:
 *   - Kiểm tra CSRF token mọi POST.
 *   - Kiểm tra user_id trong WHERE khi UPDATE/DELETE để tránh sửa hồ sơ của người khác.
 *   - Upload: kiểm tra đuôi file, MIME thật (finfo), dung lượng, đổi tên ngẫu nhiên.
 *   - Nộp hồ sơ: dùng transaction để cập nhật trạng thái + ghi log cùng lúc (atomic).
 */
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('student');

$pdo    = db();
$userId = (int)current_user()['id'];
$schId  = get_int('id');

$st = $pdo->prepare('SELECT s.*, t.name AS type_name FROM scholarships s JOIN scholarship_types t ON t.id = s.type_id WHERE s.id = ?');
$st->execute([$schId]);
$program = $st->fetch();
if (!$program) {
    abort_page(404, 'Không tìm thấy chương trình học bổng này.');
}

/** Kiểm tra chương trình còn nhận hồ sơ hay không (dùng cả khi hiển thị lẫn khi xử lý nộp). */
function program_is_open(array $p): bool
{
    return $p['status'] === 'open' && date('Y-m-d') >= $p['open_date'] && date('Y-m-d') <= $p['close_date'];
}

$st = $pdo->prepare('SELECT * FROM applications WHERE scholarship_id = ? AND user_id = ?');
$st->execute([$schId, $userId]);
$app = $st->fetch() ?: null;

$editable = $app === null || in_array($app['status'], ['draft', 'need_more'], true);
$errors   = [];

/* ---------- Xử lý các thao tác POST ---------- */
if (is_post()) {
    csrf_verify();
    $action = post_str('action');

    /* --- Lưu nháp / cập nhật thông tin hồ sơ --- */
    if ($action === 'save' && $editable && !($app === null && !program_is_open($program))) {
        $gpa       = post_str('gpa');
        $conduct   = post_str('conduct_score');
        $statement = post_str('statement');

        if (!is_numeric($gpa) || (float)$gpa < 0 || (float)$gpa > 4) $errors['gpa'] = 'GPA phải từ 0 đến 4.';
        if (!ctype_digit($conduct) || (int)$conduct > 100) $errors['conduct_score'] = 'Điểm rèn luyện phải từ 0 đến 100 (số nguyên).';
        if (mb_strlen($statement) > 2000) $errors['statement'] = 'Thư giới thiệu tối đa 2000 ký tự.';

        if (!$errors) {
            $gpaVal = number_format((float)$gpa, 2, '.', '');
            try {
                if ($app) {
                    $pdo->prepare('UPDATE applications SET gpa=?, conduct_score=?, statement=? WHERE id=?')
                        ->execute([$gpaVal, (int)$conduct, $statement, $app['id']]);
                    flash('success', 'Đã lưu thông tin hồ sơ.');
                } else {
                    $pdo->prepare(
                        "INSERT INTO applications (scholarship_id, user_id, gpa, conduct_score, statement, status)
                         VALUES (?, ?, ?, ?, ?, 'draft')"
                    )->execute([$schId, $userId, $gpaVal, (int)$conduct, $statement]);
                    flash('success', 'Đã tạo hồ sơ nháp. Hãy tải minh chứng rồi nộp hồ sơ.');
                }
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                flash('danger', 'Không thể lưu hồ sơ, vui lòng thử lại.');
            }
        }
        if (!$errors) redirect('student/apply.php?id=' . $schId);
    }

    /* --- Tải minh chứng lên --- */
    if ($action === 'upload' && $app && $editable) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE application_id = ?');
        $st->execute([$app['id']]);
        if ((int)$st->fetchColumn() >= MAX_FILES_PER_APP) {
            flash('danger', 'Bạn chỉ được tải tối đa ' . MAX_FILES_PER_APP . ' minh chứng cho mỗi hồ sơ.');
        } else {
            $docType = post_str('doc_type');
            if ($docType === '' || mb_strlen($docType) > 60) {
                flash('danger', 'Vui lòng chọn loại minh chứng.');
            } else {
                $res = handle_single_upload('document');
                if (!$res['ok']) {
                    flash('danger', $res['error']);
                } else {
                    $pdo->prepare(
                        'INSERT INTO documents (application_id, doc_type, original_name, stored_name, mime_type, file_size)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([$app['id'], $docType, $res['original_name'], $res['stored_name'], $res['mime'], $res['size']]);
                    flash('success', 'Đã tải minh chứng lên.');
                }
            }
        }
        redirect('student/apply.php?id=' . $schId);
    }

    /* --- Xóa một minh chứng --- */
    if ($action === 'delete_doc' && $app && $editable) {
        $docId = post_int('doc_id');
        $st = $pdo->prepare('SELECT stored_name FROM documents WHERE id = ? AND application_id = ?');
        $st->execute([$docId, $app['id']]);
        $doc = $st->fetch();
        if ($doc) {
            $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$docId]);
            delete_uploaded_file($doc['stored_name']);
            flash('success', 'Đã xóa minh chứng.');
        }
        redirect('student/apply.php?id=' . $schId);
    }

    /* --- Nộp hồ sơ chính thức --- */
    if ($action === 'submit' && $app && $editable) {
        if (!program_is_open($program)) {
            flash('danger', 'Chương trình này hiện không nhận hồ sơ (đã đóng hoặc hết hạn).');
        } elseif ((float)$app['gpa'] < (float)$program['min_gpa']) {
            flash('danger', 'GPA của bạn (' . $app['gpa'] . ') chưa đạt mức tối thiểu (' . $program['min_gpa'] . ') của chương trình.');
        } elseif ((int)$app['conduct_score'] < (int)$program['min_conduct']) {
            flash('danger', 'Điểm rèn luyện của bạn chưa đạt mức tối thiểu (' . (int)$program['min_conduct'] . ') của chương trình.');
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE application_id = ?');
            $st->execute([$app['id']]);
            if ((int)$st->fetchColumn() === 0) {
                flash('danger', 'Vui lòng tải lên ít nhất một minh chứng trước khi nộp hồ sơ.');
            } else {
                $pdo->beginTransaction();
                $pdo->prepare(
                    "UPDATE applications SET status='submitted', submitted_at=NOW() WHERE id = ? AND user_id = ?"
                )->execute([$app['id'], $userId]);
                $pdo->prepare(
                    'INSERT INTO review_logs (application_id, changed_by, from_status, to_status, note) VALUES (?, ?, ?, ?, ?)'
                )->execute([$app['id'], $userId, $app['status'], 'submitted', 'Sinh viên nộp hồ sơ']);
                $pdo->commit();
                flash('success', 'Nộp hồ sơ thành công! Hãy theo dõi kết quả xét duyệt tại mục "Hồ sơ của tôi".');
            }
        }
        redirect('student/apply.php?id=' . $schId);
    }

    /* --- Hủy hồ sơ nháp (chưa từng nộp) --- */
    if ($action === 'cancel' && $app && $app['status'] === 'draft') {
        $st = $pdo->prepare('SELECT stored_name FROM documents WHERE application_id = ?');
        $st->execute([$app['id']]);
        $storedNames = $st->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare('DELETE FROM applications WHERE id = ? AND user_id = ?')->execute([$app['id'], $userId]);
        foreach ($storedNames as $name) {
            delete_uploaded_file($name);
        }

        flash('info', 'Đã hủy hồ sơ nháp.');
        redirect('student/apply.php?id=' . $schId);
    }

    $st = $pdo->prepare('SELECT * FROM applications WHERE scholarship_id = ? AND user_id = ?');
    $st->execute([$schId, $userId]);
    $app = $st->fetch() ?: null;
    $editable = $app === null || in_array($app['status'], ['draft', 'need_more'], true);
}

$documents = [];
if ($app) {
    $st = $pdo->prepare('SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at');
    $st->execute([$app['id']]);
    $documents = $st->fetchAll();
}

$docTypes = ['Bảng điểm', 'Xác nhận điểm rèn luyện', 'Giấy xác nhận hoàn cảnh', 'Thư giới thiệu bản thân', 'Chứng nhận khác'];

$pageTitle = 'Hồ sơ: ' . $program['title'];
$activeNav = 'programs';
require __DIR__ . '/../../backend/includes/header.php';
?>

<div class="container py-4">
    <div class="student-page-wrap">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="page-breadcrumb">
            <a href="<?= e(url('index.php')) ?>"><i class="bi bi-house"></i> Trang chủ</a>
            <span class="bc-sep">/</span>
            <a href="<?= e(url('scholarships.php')) ?>">Học bổng</a>
            <span class="bc-sep">/</span>
            <a href="<?= e(url('scholarship_detail.php?id=' . $schId)) ?>"><?= e($program['title']) ?></a>
            <span class="bc-sep">/</span>
            <span class="bc-current">Hồ sơ ứng tuyển</span>
        </nav>

        <!-- Status Notification Banner if applicable -->
        <?php if ($app && $app['status'] === 'need_more'): ?>
            <div class="alert alert-warning d-flex align-items-start gap-3 p-3 mb-4" style="border-radius:12px">
                <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0 text-warning"></i>
                <div>
                    <h3 class="h6 mb-1 fw-bold">Yêu cầu bổ sung hồ sơ</h3>
                    <p class="mb-0 small"><?= e($app['reviewer_note'] ?: 'Hội đồng xét duyệt yêu cầu bạn cập nhật hoặc bổ sung minh chứng. Vui lòng hoàn thiện và nộp lại.') ?></p>
                </div>
            </div>
        <?php elseif ($app && $app['status'] === 'rejected'): ?>
            <div class="alert alert-danger d-flex align-items-start gap-3 p-3 mb-4" style="border-radius:12px">
                <i class="bi bi-x-circle-fill fs-4 flex-shrink-0 text-danger"></i>
                <div>
                    <h3 class="h6 mb-1 fw-bold">Hồ sơ chưa đạt tiêu chuẩn</h3>
                    <p class="mb-0 small"><?= e($app['reviewer_note'] ?: 'Rất tiếc hồ sơ của bạn chưa đáp ứng đủ tiêu chuẩn phân bổ học bổng lần này.') ?></p>
                </div>
            </div>
        <?php elseif ($app && $app['status'] === 'approved'): ?>
            <div class="alert alert-success d-flex align-items-start gap-3 p-3 mb-4" style="border-radius:12px">
                <i class="bi bi-patch-check-fill fs-4 flex-shrink-0 text-success"></i>
                <div>
                    <h3 class="h6 mb-1 fw-bold">Chúc mừng! Hồ sơ của bạn đã được phê duyệt</h3>
                    <p class="mb-0 small"><?= e($app['reviewer_note'] ?: 'Hồ sơ đã được Hội đồng xét duyệt thông qua. Nhà trường sẽ liên hệ nhận học bổng.') ?></p>
                </div>
            </div>
        <?php elseif ($app && $app['status'] === 'submitted'): ?>
            <div class="alert alert-info d-flex align-items-start gap-3 p-3 mb-4" style="border-radius:12px">
                <i class="bi bi-hourglass-split fs-4 flex-shrink-0 text-primary"></i>
                <div>
                    <h3 class="h6 mb-1 fw-bold">Hồ sơ đã được nộp thành công</h3>
                    <p class="mb-0 small">Nộp lúc <?= e(fmt_dt($app['submitted_at'])) ?>. Hồ sơ đang trong hàng đợi chờ Hội đồng thẩm định.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Cột trái: Thông tin học bổng -->
            <div class="col-lg-5">
                <div class="table-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="detail-type-badge mb-0">
                            <i class="bi bi-bookmark-fill me-1"></i><?= e($program['type_name']) ?>
                        </span>
                        <?php if ($app): ?>
                            <?= app_status_badge($app['status']) ?>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border">Chưa nộp</span>
                        <?php endif; ?>
                    </div>

                    <h1 class="h5 fw-bold mb-3"><?= e($program['title']) ?></h1>

                    <div class="p-3 mb-3" style="background:var(--bg-subtle);border-radius:12px;border:1px solid var(--border-subtle)">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Giá trị học bổng:</span>
                            <span class="fw-bold fs-5 text-warning-emphasis"><?= e(fmt_money($program['amount'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Số suất xét cấp:</span>
                            <span class="fw-semibold"><?= (int)$program['slots'] ?> suất</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Hạn cuối nhận hồ sơ:</span>
                            <span class="fw-semibold text-danger"><?= e(fmt_date($program['close_date'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">GPA tối thiểu:</span>
                            <span class="fw-semibold">≥ <?= rtrim(rtrim(number_format((float)$program['min_gpa'], 2), '0'), '.') ?> / 4.0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Điểm rèn luyện:</span>
                            <span class="fw-semibold">≥ <?= (int)$program['min_conduct'] ?> / 100</span>
                        </div>
                    </div>

                    <?php if ($program['requirements']): ?>
                        <h2 class="h6 fw-bold mb-2 text-primary"><i class="bi bi-list-check me-1"></i> Tiêu chí xét duyệt</h2>
                        <p class="small text-secondary mb-3" style="white-space:pre-line"><?= e($program['requirements']) ?></p>
                    <?php endif; ?>

                    <?php if (!program_is_open($program) && (!$app || $editable)): ?>
                        <div class="alert alert-secondary small mb-0 mt-3">
                            <i class="bi bi-lock-fill me-1"></i> Chương trình hiện đã đóng tiếp nhận hồ sơ.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cột phải: Khai báo thông tin & Minh chứng -->
            <div class="col-lg-7">
                <!-- Khối 1: Thông tin học tập cá nhân -->
                <div class="table-card p-4 mb-4">
                    <h2 class="table-card-title mb-3">
                        <i class="bi bi-pencil-square"></i> 1. Khai báo thông tin học tập
                    </h2>

                    <?php if ($editable): ?>
                        <form method="post" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold" for="gpa">
                                        GPA tích lũy (thang 4) <span class="text-danger">*</span>
                                    </label>
                                    <input class="form-control <?= isset($errors['gpa']) ? 'is-invalid' : '' ?>"
                                           type="number" step="0.01" min="0" max="4" id="gpa" name="gpa"
                                           placeholder="VD: 3.45" required
                                           value="<?= e($app['gpa'] ?? '') ?>">
                                    <?= field_error($errors, 'gpa') ?>
                                    <div class="form-text small">Tối thiểu: <?= rtrim(rtrim(number_format((float)$program['min_gpa'], 2), '0'), '.') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold" for="conduct_score">
                                        Điểm rèn luyện (thang 100) <span class="text-danger">*</span>
                                    </label>
                                    <input class="form-control <?= isset($errors['conduct_score']) ? 'is-invalid' : '' ?>"
                                           type="number" min="0" max="100" id="conduct_score" name="conduct_score"
                                           placeholder="VD: 85" required inputmode="numeric"
                                           value="<?= e($app['conduct_score'] ?? '') ?>">
                                    <?= field_error($errors, 'conduct_score') ?>
                                    <div class="form-text small">Tối thiểu: <?= (int)$program['min_conduct'] ?></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="statement">
                                        Thư trình bày nguyện vọng & hoàn cảnh
                                    </label>
                                    <textarea class="form-control <?= isset($errors['statement']) ? 'is-invalid' : '' ?>"
                                              id="statement" name="statement" rows="4" maxlength="2000"
                                              placeholder="Trình bày lý do, hoàn cảnh gia đình hoặc các thành tích nổi bật của bạn..."><?= e($app['statement'] ?? '') ?></textarea>
                                    <?= field_error($errors, 'statement') ?>
                                    <div class="form-text small">Tối đa 2.000 ký tự.</div>
                                </div>
                            </div>
                            <div class="mt-3 text-end">
                                <button class="btn btn-outline-primary" type="submit" style="border-radius:10px;min-height:44px">
                                    <i class="bi bi-save me-1"></i> Lưu thông tin
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-muted fw-medium">GPA đã khai</dt>
                            <dd class="col-sm-8 fw-semibold"><?= e($app['gpa']) ?> / 4.0</dd>
                            <dt class="col-sm-4 text-muted fw-medium">Điểm rèn luyện</dt>
                            <dd class="col-sm-8 fw-semibold"><?= (int)$app['conduct_score'] ?> / 100</dd>
                            <dt class="col-sm-4 text-muted fw-medium">Thư giới thiệu</dt>
                            <dd class="col-sm-8" style="white-space: pre-line"><?= e($app['statement'] ?: '(Không có)') ?></dd>
                        </dl>
                    <?php endif; ?>
                </div>

                <!-- Khối 2: Tài liệu minh chứng -->
                <?php if ($app): ?>
                    <div class="table-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="table-card-title mb-0">
                                <i class="bi bi-paperclip"></i> 2. Minh chứng đính kèm (<?= count($documents) ?>/<?= MAX_FILES_PER_APP ?>)
                            </h2>
                            <span class="badge bg-secondary-subtle text-secondary">Tối đa <?= MAX_FILES_PER_APP ?> tệp</span>
                        </div>

                        <?php if (!$documents): ?>
                            <div class="p-3 mb-3 text-center" style="background:var(--bg-subtle);border-radius:10px;border:1px dashed var(--border-strong)">
                                <i class="bi bi-file-earmark-arrow-up text-muted fs-3 d-block mb-1"></i>
                                <span class="text-muted small">Chưa có minh chứng nào được tải lên. Bạn cần ít nhất 1 minh chứng để nộp hồ sơ.</span>
                            </div>
                        <?php else: ?>
                            <ul class="list-group mb-3" style="border-radius:10px;overflow:hidden">
                                <?php foreach ($documents as $d): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-5"></i>
                                            <div>
                                                <div class="fw-semibold small"><?= e($d['doc_type']) ?></div>
                                                <div class="text-muted small" style="font-size:0.75rem">
                                                    <?= e($d['original_name']) ?> · <?= number_format($d['file_size'] / 1024, 0) ?> KB
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <a class="btn btn-sm btn-outline-primary" style="border-radius:8px"
                                               href="<?= e(url('student/document_download.php?id=' . (int)$d['id'])) ?>">
                                                <i class="bi bi-download"></i> Tải
                                            </a>
                                            <?php if ($editable): ?>
                                                <form method="post" class="d-inline" data-confirm="Xác nhận xóa tài liệu minh chứng này?">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_doc">
                                                    <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger" style="border-radius:8px" type="submit">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ($editable && count($documents) < MAX_FILES_PER_APP): ?>
                            <form method="post" enctype="multipart/form-data" class="p-3" style="background:var(--bg-subtle);border-radius:10px">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="upload">
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-5">
                                        <select class="form-select form-select-sm" name="doc_type" required aria-label="Loại minh chứng">
                                            <option value="">-- Chọn loại minh chứng --</option>
                                            <?php foreach ($docTypes as $dt): ?>
                                                <option value="<?= e($dt) ?>"><?= e($dt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-5">
                                        <input class="form-control form-control-sm" type="file" name="document"
                                               accept=".pdf,.jpg,.jpeg,.png" required aria-label="Chọn tệp minh chứng">
                                    </div>
                                    <div class="col-sm-2">
                                        <button class="btn btn-sm btn-primary w-100" type="submit" style="border-radius:8px">
                                            <i class="bi bi-upload me-1"></i> Tải
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-text text-muted" style="font-size:.75rem">
                                            Định dạng cho phép: PDF, JPG, PNG (tối đa 5MB / file).
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Khối 3: Thao tác nộp hồ sơ -->
                    <?php if ($editable): ?>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-2">
                            <form method="post" data-confirm="Xác nhận nộp hồ sơ chính thức? Sau khi nộp bạn sẽ không thể chỉnh sửa thông tin cho đến khi có yêu cầu từ Hội đồng xét duyệt.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="submit">
                                <button class="btn btn-primary" type="submit" data-once style="border-radius:10px;min-height:44px;padding:0.6rem 1.75rem;font-weight:700">
                                    <i class="bi bi-send-check-fill me-2"></i> Nộp hồ sơ xét duyệt
                                </button>
                            </form>
                            <?php if ($app['status'] === 'draft'): ?>
                                <form method="post" data-confirm="Bạn có chắc chắn muốn hủy hồ sơ nháp này? Tất cả dữ liệu và tệp minh chứng sẽ bị xóa.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <button class="btn btn-outline-danger" type="submit" style="border-radius:10px;min-height:44px">
                                        <i class="bi bi-x-circle me-1"></i> Hủy hồ sơ nháp
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
