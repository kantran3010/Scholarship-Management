<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('student');

$pdo    = db();
$userId = (int)current_user()['id'];

// Chỉ cho tải minh chứng thuộc hồ sơ của chính sinh viên đang đăng nhập
$st = $pdo->prepare(
    'SELECT d.* FROM documents d
     JOIN applications a ON a.id = d.application_id
     WHERE d.id = ? AND a.user_id = ?'
);
$st->execute([get_int('id'), $userId]);
$doc = $st->fetch();

if (!$doc) {
    abort_page(404, 'Không tìm thấy minh chứng này.');
}
send_document_file($doc);
