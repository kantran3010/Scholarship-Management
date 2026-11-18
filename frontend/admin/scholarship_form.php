<?php
/**
 * Form thêm mới / chỉnh sửa chương trình học bổng (Admin).
 *
 * Các trường và quy tắc validate (phía server):
 *   - title       : 5–200 ký tự (tên chương trình).
 *   - type_id     : phải nằm trong danh sách loại học bổng hiện có (whitelist).
 *   - amount      : số nguyên dương (giá trị mỗi suất, đơn vị đồng VNĐ).
 *   - slots       : số nguyên 1–1000 (số suất học bổng).
 *   - min_gpa     : số thực 0–4 (GPA tối thiểu, thang 4).
 *   - min_conduct : số nguyên 0–100 (điểm rèn luyện tối thiểu).
 *   - open_date   : ngày hợp lệ (Y-m-d), trước hoặc bằng close_date.
 *   - close_date  : ngày hợp lệ (Y-m-d), sau hoặc bằng open_date.
 *   - status      : 'open' hoặc 'closed' (whitelist).
 *   - image       : tùy chọn — JPG/PNG/WEBP, tối đa COVER_MAX_BYTES (2MB).
 *                   Khi sửa: có thể giữ nguyên, đổi, hoặc xóa ảnh cũ.
 *
 * Logic ảnh bìa:
 *   - File ảnh cũ chỉ bị xóa khỏi đĩa SAU KHI cập nhật CSDL thành công.
 *   - Nếu lưu CSDL thất bại: dọn file ảnh mới vừa tải lên (tránh rác).
 *
 * Khi sửa thêm: không cho đặt số suất thấp hơn số hồ sơ đã được duyệt.
 */
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo = db();
$id  = get_int('id');
$row = null;

if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM scholarships WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        abort_page(404, 'Không tìm thấy chương trình này.');
    }
}

$types  = $pdo->query('SELECT id, name FROM scholarship_types ORDER BY name')->fetchAll();
$errors = [];

// Giá trị hiển thị trên form: ưu tiên dữ liệu vừa gửi (khi có lỗi), rồi dữ liệu cũ, rồi mặc định
$v = function (string $key, string $default = '') use ($row) {
    if (is_post()) return post_str($key);
    return (string)($row[$key] ?? $default);
};

if (is_post()) {
    csrf_verify();

    $typeId    = post_int('type_id');
    $title     = post_str('title');
    $desc      = post_str('description');
    $req       = post_str('requirements');
    $amount    = post_str('amount');
    $slots     = post_str('slots');
    $minGpa    = post_str('min_gpa');
    $minCond   = post_str('min_conduct');
    $openDate  = post_str('open_date');
    $closeDate = post_str('close_date');
    $status    = post_str('status') === 'closed' ? 'closed' : 'open';

    if ($typeId <= 0 || !in_array($typeId, array_column($types, 'id'), true)) $errors['type_id'] = 'Vui lòng chọn loại học bổng.';
    if (mb_strlen($title) < 5 || mb_strlen($title) > 200) $errors['title'] = 'Tên chương trình phải từ 5 đến 200 ký tự.';
    if (!ctype_digit($amount) || (int)$amount <= 0) $errors['amount'] = 'Giá trị học bổng phải là số nguyên dương.';
    if (!ctype_digit($slots) || (int)$slots <= 0 || (int)$slots > 1000) $errors['slots'] = 'Số suất phải là số nguyên từ 1 đến 1000.';
    if (!is_numeric($minGpa) || (float)$minGpa < 0 || (float)$minGpa > 4) $errors['min_gpa'] = 'GPA tối thiểu phải từ 0 đến 4.';
    if (!ctype_digit($minCond) || (int)$minCond > 100) $errors['min_conduct'] = 'Điểm rèn luyện tối thiểu phải từ 0 đến 100.';

    $openDt  = DateTime::createFromFormat('Y-m-d', $openDate);
    $closeDt = DateTime::createFromFormat('Y-m-d', $closeDate);
    if (!$openDt)  $errors['open_date']  = 'Ngày mở không hợp lệ.';
    if (!$closeDt) $errors['close_date'] = 'Ngày đóng không hợp lệ.';
    if ($openDt && $closeDt && $closeDt < $openDt) $errors['close_date'] = 'Ngày đóng phải sau hoặc bằng ngày mở.';

    // Khi sửa: không cho đặt số suất thấp hơn số hồ sơ đã được duyệt
    if ($row && !$errors) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE scholarship_id = ? AND status = 'approved'");
        $st->execute([$id]);
        $approved = (int)$st->fetchColumn();
        if ((int)$slots < $approved) {
            $errors['slots'] = "Đã có $approved hồ sơ được duyệt, không thể đặt số suất thấp hơn.";
        }
    }

    // Ảnh bìa: chỉ xử lý khi các trường khác đã hợp lệ, để không lưu file thừa khi form còn lỗi
    $newImage  = null;
    $imageName = $row['image'] ?? null;               // tên ảnh sẽ lưu vào CSDL
    $removeOld = false;                                // có cần xóa file ảnh cũ sau khi lưu thành công không
    if (!$errors) {
        $hasUpload = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
        if ($hasUpload) {
            $res = handle_cover_upload('image');
            if ($res['ok']) {
                $newImage  = $res['stored_name'];
                $imageName = $newImage;
                $removeOld = !empty($row['image']);
            } else {
                $errors['image'] = $res['error'];
            }
        } elseif ($row && post_str('remove_image') === '1') {
            $imageName = null;
            $removeOld = !empty($row['image']);
        }
    }

    if (!$errors) {
        $params = [
            $typeId, $title, $desc, $req, $imageName, (int)$amount, (int)$slots,
            number_format((float)$minGpa, 2, '.', ''), (int)$minCond,
            $openDt->format('Y-m-d'), $closeDt->format('Y-m-d'), $status,
        ];
        try {
            if ($row) {
                $pdo->prepare(
                    'UPDATE scholarships SET type_id=?, title=?, description=?, requirements=?, image=?, amount=?, slots=?,
                     min_gpa=?, min_conduct=?, open_date=?, close_date=?, status=? WHERE id=?'
                )->execute([...$params, $id]);
                flash('success', 'Đã cập nhật chương trình học bổng.');
            } else {
                $pdo->prepare(
                    'INSERT INTO scholarships (type_id, title, description, requirements, image, amount, slots,
                     min_gpa, min_conduct, open_date, close_date, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute($params);
                flash('success', 'Đã thêm chương trình học bổng.');
            }
            if ($removeOld) {
                delete_cover_file($row['image']);   // lưu thành công rồi mới xóa ảnh cũ
            }
            redirect('admin/scholarships.php');
        } catch (PDOException $ex) {
            delete_cover_file($newImage);           // lưu CSDL thất bại: dọn ảnh vừa tải lên
            error_log($ex->getMessage());
            $errors['form'] = 'Không thể lưu, vui lòng thử lại.';
        }
    }
}

$pageTitle = $row ? 'Sửa chương trình' : 'Thêm chương trình';
$activeNav = 'admin-scholarships';
require __DIR__ . '/../../backend/includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($pageTitle) ?></h1>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-danger"><?= e($errors['form']) ?></div>
<?php endif; ?>

<div class="panel" style="max-width: 760px">
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label" for="title">Tên chương trình</label>
                <input class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" id="title" name="title"
                       required maxlength="200" value="<?= e($v('title')) ?>">
                <?= field_error($errors, 'title') ?>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="type_id">Loại học bổng</label>
                <select class="form-select <?= isset($errors['type_id']) ? 'is-invalid' : '' ?>" id="type_id" name="type_id" required>
                    <option value="">-- Chọn loại --</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (string)$t['id'] === $v('type_id') ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'type_id') ?>
            </div>

            <div class="col-12">
                <label class="form-label" for="image">Ảnh bìa (JPG, PNG, WEBP – tối đa 2MB)</label>
                <?php if ($row && $row['image']): ?>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img src="<?= e(cover_url($row)) ?>" alt="Ảnh bìa hiện tại" class="cover-thumb-lg">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                            <label class="form-check-label" for="remove_image">Xóa ảnh này (dùng ảnh mặc định)</label>
                        </div>
                    </div>
                <?php endif; ?>
                <input class="form-control <?= isset($errors['image']) ? 'is-invalid' : '' ?>" type="file" id="image" name="image"
                       accept=".jpg,.jpeg,.png,.webp">
                <div class="form-text">Để trống nếu không muốn đổi ảnh. Chương trình chưa có ảnh sẽ dùng ảnh mặc định.</div>
                <?= field_error($errors, 'image') ?>
            </div>

            <div class="col-12">
                <label class="form-label" for="description">Mô tả chương trình</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e($v('description')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="requirements">Điều kiện xét duyệt (mỗi ý cách nhau bằng dấu chấm phẩy ;)</label>
                <textarea class="form-control" id="requirements" name="requirements" rows="3"><?= e($v('requirements')) ?></textarea>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="amount">Giá trị mỗi suất (đ)</label>
                <input class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>" id="amount" name="amount"
                       required inputmode="numeric" value="<?= e($v('amount')) ?>">
                <?= field_error($errors, 'amount') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="slots">Số suất</label>
                <input class="form-control <?= isset($errors['slots']) ? 'is-invalid' : '' ?>" id="slots" name="slots"
                       required inputmode="numeric" value="<?= e($v('slots')) ?>">
                <?= field_error($errors, 'slots') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="open" <?= $v('status', 'open') === 'open' ? 'selected' : '' ?>>Đang mở</option>
                    <option value="closed" <?= $v('status') === 'closed' ? 'selected' : '' ?>>Đã đóng</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="min_gpa">GPA tối thiểu (thang 4)</label>
                <input class="form-control <?= isset($errors['min_gpa']) ? 'is-invalid' : '' ?>" id="min_gpa" name="min_gpa"
                       required value="<?= e($v('min_gpa', '0')) ?>">
                <?= field_error($errors, 'min_gpa') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="min_conduct">Điểm rèn luyện tối thiểu (thang 100)</label>
                <input class="form-control <?= isset($errors['min_conduct']) ? 'is-invalid' : '' ?>" id="min_conduct" name="min_conduct"
                       required inputmode="numeric" value="<?= e($v('min_conduct', '0')) ?>">
                <?= field_error($errors, 'min_conduct') ?>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="open_date">Ngày mở nhận hồ sơ</label>
                <input class="form-control <?= isset($errors['open_date']) ? 'is-invalid' : '' ?>" type="date" id="open_date" name="open_date"
                       required value="<?= e($v('open_date', date('Y-m-d'))) ?>">
                <?= field_error($errors, 'open_date') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="close_date">Ngày đóng nhận hồ sơ</label>
                <input class="form-control <?= isset($errors['close_date']) ? 'is-invalid' : '' ?>" type="date" id="close_date" name="close_date"
                       required value="<?= e($v('close_date', date('Y-m-d', strtotime('+30 days')))) ?>">
                <?= field_error($errors, 'close_date') ?>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary" type="submit" data-once><?= $row ? 'Lưu thay đổi' : 'Thêm chương trình' ?></button>
            <a class="btn btn-outline-primary" href="<?= e(url('admin/scholarships.php')) ?>">Hủy</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
