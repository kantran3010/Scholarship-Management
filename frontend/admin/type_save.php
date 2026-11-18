<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');
require_post();
csrf_verify();

$pdo  = db();
$id   = post_int('id');
$name = post_str('name');
$desc = post_str('description');

if ($name === '' || mb_strlen($name) > 100) {
    flash('danger', 'Tên loại học bổng không hợp lệ (tối đa 100 ký tự).');
    redirect('admin/types.php' . ($id ? '?edit=' . $id : ''));
}
if (mb_strlen($desc) > 255) {
    flash('danger', 'Mô tả tối đa 255 ký tự.');
    redirect('admin/types.php' . ($id ? '?edit=' . $id : ''));
}

try {
    if ($id > 0) {
        $st = $pdo->prepare('UPDATE scholarship_types SET name = ?, description = ? WHERE id = ?');
        $st->execute([$name, $desc, $id]);
        flash('success', 'Đã cập nhật loại học bổng.');
    } else {
        $st = $pdo->prepare('INSERT INTO scholarship_types (name, description) VALUES (?, ?)');
        $st->execute([$name, $desc]);
        flash('success', 'Đã thêm loại học bổng.');
    }
} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {
        flash('danger', 'Tên loại học bổng này đã tồn tại.');
    } else {
        error_log($ex->getMessage());
        flash('danger', 'Không thể lưu, vui lòng thử lại.');
    }
}
redirect('admin/types.php');
